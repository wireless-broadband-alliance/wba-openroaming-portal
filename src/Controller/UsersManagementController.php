<?php

namespace App\Controller;

use App\DTO\UserAddDTO;
use App\DTO\UserUpdateDTO;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Entity\UserRadiusProfile;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\PermissionLevel;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Enum\UserRadiusProfileRevokeReason;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Form\ResetPasswordType;
use App\Form\UserAddType;
use App\Form\UserUpdateType;
use App\Repository\EventRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Repository\UserRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EmailGenerator;
use App\Service\EscapeSpreadSheet;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\ProfileManager;
use App\Service\SendSMS;
use App\Service\TwoFAService;
use App\Service\UserDeletionService;
use App\Service\VerificationCodeEmailGenerator;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class UsersManagementController extends AbstractController
{
    public function __construct(
        private readonly ProfileManager $profileManager,
        private readonly EventActions $eventActions,
        private readonly ParameterBagInterface $parameterBag,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly GetSettings $getSettings,
        private readonly EventRepository $eventRepository,
        private readonly SendSMS $sendSMS,
        private readonly UserDeletionService $userDeletionService,
        private readonly TwoFAService $twoFAService,
        private readonly VerificationCodeEmailGenerator $verificationCodeEmailGenerator,
        private readonly TranslatorInterface $translator,
        private readonly UserRadiusProfileRepository $radiusProfileRepository,
        private readonly EmailGenerator $emailGenerator,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
    ) {
    }

    #[Route('/dashboard/revoke/{id:user<\d+>}', name: 'admin_user_revoke_profiles', methods: ['POST'])]
    #[IsGranted(UserAuthenticationVoter::USERS_MANAGEMENT_WRITE)]
    public function revokeUsers(Request $request, User $user): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $revokeProfiles = $this->profileManager->disableProfiles(
            $user,
            UserRadiusProfileRevokeReason::ADMIN_REVOKED_PROFILE->value,
            true
        );

        if (!$revokeProfiles) {
            $this->addFlash(
                'error',
                $this->translator->trans('accountWithoutProfilesAssociated', [], 'controllers')
            );
            return $this->redirectToRoute('admin_page');
        }

        if (
            !$this->isGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)
            && (
                in_array(AdminRoleType::ROLE_ADMIN->value, $user->getRoles())
                || in_array(AdminRoleType::ROLE_SUPER_ADMIN->value, $user->getRoles())
            )
        ) {
            throw $this->createAccessDeniedException();
        }

        $eventMetaData = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'platform' => PlatformMode::LIVE->value,
            'userRevoked' => $user->getUuid(),
            'by' => $currentUser->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::ADMIN_REVOKE_PROFILES->value,
            new DateTime(),
            $eventMetaData
        );

        $this->addFlash(
            'success',
            $this->translator->trans(
                'profileRevoked',
                [
                    '%uuid%' => $user->getUuid()
                ],
                'controllers'
            )
        );

        return $this->redirectToRoute('admin_page');
    }

    /**
     * Handle export of the Users Table on the Main Route
     */
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/dashboard/export/users', name: 'admin_user_export')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function exportUsers(): Response
    {
        // Check if export is enabled
        $exportUsers = $this->parameterBag->get('app.export_users');
        if ($exportUsers === OperationMode::OFF->value) {
            $this->addFlash(
                'error',
                $this->translator->trans('operationDisabledForSecurityReasons', [], 'controllers')
            );
            return $this->redirectToRoute('admin_page');
        }

        // Fetch users excluding admins
        $users = $this->userRepository->findAll();

        // Create spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Base headers
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'UUID');
        $sheet->setCellValue('C1', 'Email');
        $sheet->setCellValue('D1', 'Phone Number');
        $sheet->setCellValue('E1', 'First Name');
        $sheet->setCellValue('F1', 'Last Name');
        $sheet->setCellValue('G1', 'Verification');

        // Show "Is Admin" only if the SUPER ADMIN requested this export
        $includeAdminColumn = $this->isGranted('ROLE_SUPER_ADMIN');
        if ($includeAdminColumn) {
            $sheet->setCellValue('H1', 'Roles');
            $columnOffset = 1;
        } else {
            $columnOffset = 0;
        }

        $sheet->setCellValue(chr(ord('H') + $columnOffset) . '1', '2FA status');
        $sheet->setCellValue(chr(ord('I') + $columnOffset) . '1', 'Provider');
        $sheet->setCellValue(chr(ord('J') + $columnOffset) . '1', 'ProviderId');
        $sheet->setCellValue(chr(ord('K') + $columnOffset) . '1', 'Banned At');
        $sheet->setCellValue(chr(ord('L') + $columnOffset) . '1', 'Created At');

        $row = 2;
        $escapeSpreadSheetService = new EscapeSpreadSheet();

        foreach ($users as $user) {
            $sheet->setCellValue('A' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($user->getId()));

            // UUID (prevent scientific notation)
            $uuid = $user->getUuid();
            if (is_numeric($uuid)) {
                $sheet->setCellValueExplicit('B' . $row, $uuid, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue('B' . $row, $uuid);
            }

            $sheet->setCellValue('C' . $row, $user->getEmail());

            // Phone number
            $phoneNumber = $user->getPhoneNumber();
            if ($phoneNumber) {
                $sheet->setCellValueExplicit('D' . $row, $phoneNumber, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue('D' . $row, '');
            }

            $sheet->setCellValue('E' . $row, $user->getFirstName());
            $sheet->setCellValue('F' . $row, $user->getLastName());
            $sheet->setCellValue('G' . $row, $user->isVerified() ? 'Verified' : 'Not Verified');

            // If SUPER ADMIN → add admin flag
            if ($includeAdminColumn) {
                $roles = array_map(
                    static fn($role) => str_replace('ROLE_', '', $role),
                    $user->getRoles()
                );

                $sheet->setCellValue('H' . $row, implode(', ', $roles));
            }

            // Fetch provider info
            $userExternalAuthRepository = $this->entityManager->getRepository(UserExternalAuth::class);
            $userExternalAuth = $userExternalAuthRepository->findOneBy(['user' => $user]);

            $twoFAColumn = chr(ord('H') + $columnOffset);
            $providerColumn = chr(ord('I') + $columnOffset);
            $providerIdColumn = chr(ord('J') + $columnOffset);
            $bannedColumn = chr(ord('K') + $columnOffset);
            $createdColumn = chr(ord('L') + $columnOffset);

            // 2FA
            $statusEnum = UserTwoFactorAuthenticationStatus::from($user->getTwoFAtype());
            $sheet->setCellValue($twoFAColumn . $row, $statusEnum->name);

            // Provider
            $sheet->setCellValue($providerColumn . $row, $userExternalAuth?->getProvider() ?? 'No Provider');

            // ProviderId
            $sheet->setCellValue($providerIdColumn . $row, $userExternalAuth?->getProviderId() ?? 'No ProviderId');

            // Banned At
            $sheet->setCellValue($bannedColumn . $row, $user->getBannedAt()?->format('Y-m-d H:i:s') ?? 'Not Banned');

            // Created At
            $sheet->setCellValue($createdColumn . $row, $user->getCreatedAt());

            $row++;
        }

        // Output file
        $tempFile = tempnam(sys_get_temp_dir(), 'users');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $this->file($tempFile, 'users.xlsx');
    }

    /**
     * @throws RandomException
     */
    #[Route('/dashboard/add', name: 'dashboard_add_admin')]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function addUsers(Request $request): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $newUser = new User();
        $userAddDTO = new UserAddDTO($this->userPasswordHasher, $this->entityManager, $newUser);

        // Create & handle form
        $form = $this->createForm(UserAddType::class, $userAddDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Convert DTO → Entity data before creation
            $userAddDTO->createUser($newUser);

            // Flash message
            $this->addFlash(
                'success',
                $this->translator->trans('addedNewUser', [
                    '%uuid%' => $newUser->getUuid(),
                ], 'controllers')
            );

            $eventMetaData = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'userAddedBy' => $newUser->getUuid(),
                'by' => $currentUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::ADMIN_ADDED_NEW_USER->value,
                new DateTime(),
                $eventMetaData
            );

            return $this->redirectToRoute('admins_management');
        }

        return $this->render('dashboard/actions/add.html.twig', [
            'form' => $form->createView(),
            'userAddDTO' => $userAddDTO,
            'data' => $data,
            'current_user' => $currentUser,
            'context' => FirewallType::DASHBOARD->value,
        ]);
    }

    /**
     * Deletes Users from the Portal, encrypts the data before delete and saves it
     */
    /**
     * @throws \JsonException
     */
    #[Route('/dashboard/delete/{id:user<\d+>}', name: 'admin_user_delete', methods: ['POST'])]
    #[IsGranted(UserAuthenticationVoter::USERS_MANAGEMENT_WRITE)]
    public function deleteUsers(User $user, Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (
            !$this->isGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)
            && (
                in_array(AdminRoleType::ROLE_ADMIN->value, $user->getRoles())
                || in_array(AdminRoleType::ROLE_SUPER_ADMIN->value, $user->getRoles())
            ) && !$this->isGranted(UserAuthenticationVoter::ADMIN_MANAGEMENT_WRITE)
        ) {
            throw $this->createAccessDeniedException();
        }

        // Fetch user and external auths
        $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user->getId()]);
        $getUserUuid = $user->getUuid();

        if ($user->getDeletedAt() instanceof \DateTimeInterface) {
            $this->addFlash(
                'error',
                $this->translator->trans('userAlreadyDeleted', [], 'controllers')
            );
            return $this->redirectToRoute('admin_page');
        }

        $result = $this->userDeletionService->deleteUser($user, $userExternalAuths, $request, $currentUser);
        // Handle the success or failure response
        if (!$result['success']) {
            $this->addFlash('error', $result['message']);
            return $this->redirectToRoute('admin_page');
        }

        $this->addFlash(
            'success',
            $this->translator->trans(
                'userDeleted',
                [
                    '%uuid%' => $getUserUuid
                ],
                'controllers'
            )
        );

        // Return to the last page where the user was (with searching filters)
        $lastPage = $request->headers->get('referer', '/dashboard');
        return $this->redirect($lastPage);
    }

    /**
     * Handles the edit of the Users (Super-admin || Admin || User)
     */
    /**
     * @throws TransportExceptionInterface
     * @throws \DateMalformedStringException
     * @throws \DateMalformedIntervalStringException
     */
    #[Route('/dashboard/edit/{id:user<\d+>}', name: 'admin_user_edit')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function editUsers(
        Request $request,
        EntityManagerInterface $em,
        User $user
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::USERS_MANAGEMENT_WRITE);

        if ($user->getId() !== $currentUser->getId()) {
            if (!$this->isGranted(UserAuthenticationVoter::USERS_MANAGEMENT_READ)) {
                throw $this->createAccessDeniedException();
            }
            if (
                !$this->isGranted(AdminRoleType::ROLE_ADMIN->value)
            ) {
                throw $this->createAccessDeniedException();
            }
        }

        if ($user->getDeletedAt() instanceof \DateTimeInterface) {
            $this->addFlash(
                'error',
                $this->translator->trans('userAlreadyDeleted', [], 'controllers')
            );

            return $this->redirectToRoute('admin_page');
        }

        $userUpdateDTO = new UserUpdateDTO($user);

        // Set IDs and roles so blockBanSuperAdmin() works correctly
        $userUpdateDTO->editingUserId = $user->getId();
        $userUpdateDTO->currentUserId = $currentUser->getId();
        $userUpdateDTO->roles = $user->getRoles();

        // Determine if a admin is being edited
        $isEditedUserAdmin = in_array(AdminRoleType::ROLE_ADMIN->value, $user->getRoles(), true) ||
            in_array(AdminRoleType::ROLE_SUPER_ADMIN->value, $user->getRoles(), true);
        $isEditingSelf = $user->getId() === $currentUser->getId();

        // Only allow permission editing if super admin editing another admin
        $userUpdateDTO->editingAdmin = $isEditedUserAdmin && !$isEditingSelf;

        // Create & handle form
        $form = $this->createForm(
            UserUpdateType::class,
            $userUpdateDTO,
            [
                'disabled' => !$canWrite,
                'edited_user' => $user
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
            // Use DTO method to map data back
            $userUpdateDTO->updateUser($user, $userUpdateDTO->editingAdmin);

            if ($userUpdateDTO->banned) {
                $this->profileManager->disableProfiles(
                    $user,
                    UserRadiusProfileRevokeReason::ADMIN_BANNED_USER->value,
                    true
                );
            }

            if ($userUpdateDTO->isVerified) {
                $this->profileManager->enableProfiles($user);
            } else {
                $this->profileManager->disableProfiles(
                    $user,
                    UserRadiusProfileRevokeReason::ADMIN_REMOVED_USER_VERIFICATION->value,
                    true
                );
            }

            $this->userRepository->save($user, true);

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_FROM_UI->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'edited' => $user->getUuid(),
                    'by' => $currentUser->getUuid(),
                ]
            );

            $uuid = $user->getUuid();
            $this->addFlash(
                'success',
                $this->translator->trans(
                    'userUpdated',
                    [
                        '%uuid%' => $uuid
                    ],
                    'controllers'
                )
            );

            // Return to the last page where the user was (with searching filters)
            $lastPage = $request->headers->get('referer', '/dashboard');
            return $this->redirect($lastPage);
        }

        $formReset = $this->createForm(ResetPasswordType::class, $user, ['disabled' => !$canWrite]);
        $formReset->handleRequest($request);

        if ($formReset->isSubmitted() && $formReset->isValid() && $canWrite) {
            // get the both typed passwords by the admin
            $newPassword = $formReset->get('password')->getData();
            $confirmPassword = $formReset->get('confirmPassword')->getData();

            if ($newPassword !== $confirmPassword) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('PasswordPasswordConfirmationMustMatch', [], 'controllers')
                );
                return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
            }

            // Get the User Provider && ProviderId
            $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

            // Hash the new password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setForgotPasswordRequest(true);
            $em->flush();

            if ($user->getEmail()) {
                // Send email for the user
                $this->emailGenerator->sendResetPasswordEmailByAdmin($user, $newPassword);

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'edited ' => $user->getUuid(),
                    'by' => $currentUser->getUuid(),
                ];

                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI->value,
                    new DateTime(),
                    $eventMetadata
                );
            }

            if (
                $user->getPhoneNumber() && $userExternalAuth->getProviderId() === UserProvider::PHONE_NUMBER->value
            ) {
                $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent(
                    $user,
                    AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI->value
                );

                $smsResendInterval = null;
                if (is_array($data) && isset($data[SettingName::SMS_TIMER_RESEND->value]['value'])) {
                    $smsResendInterval = $data[SettingName::SMS_TIMER_RESEND->value]['value'];
                }

                if ($smsResendInterval === null) {
                    // Fallback value if the setting is missing just for phpstan be happy
                    $smsResendInterval = 5;
                }

                $minInterval = new DateInterval('PT' . $smsResendInterval . 'M');
                $currentTime = new DateTime();

                // Retrieve the metadata from the latest event
                $latestEventMetadata = $latestEvent instanceof Event ? $latestEvent->getEventMetadata() : [];
                $lastResetAccountPasswordTime = isset($latestEventMetadata['lastResetAccountPasswordTime'])
                    ? new DateTime($latestEventMetadata['lastResetAccountPasswordTime'])
                    : null;
                $resetAttempts = $latestEventMetadata['resetAttempts'] ?? 0;

                if (
                    (!$latestEvent || $resetAttempts < 3)
                    && (
                        !$latestEvent
                        || (
                            $lastResetAccountPasswordTime instanceof DateTime
                            && $lastResetAccountPasswordTime->add($minInterval) < $currentTime
                        )
                    )
                ) {
                    $attempts = $resetAttempts + 1;

                    $message = $this->translator->trans(
                        'newPasswordMessage',
                        ['%password%' => $newPassword],
                        'controllers'
                    );
                    $smsResponse = $this->sendSMS->sendSmsNoValidation($user, $message);

                    if ($smsResponse !== '' && $smsResponse !== '0') {
                        $this->addFlash(
                            'success',
                            $this->translator->trans('passwordSentSMS', [], 'controllers')
                        );

                        $eventMetadata = [
                            'ip' => $request->getClientIp(),
                            'edited' => $user->getUuid(),
                            'by' => $currentUser->getUuid(),
                            'resetAttempts' => $attempts,
                            'lastResetAccountPasswordTime' => $currentTime->format('Y-m-d H:i:s'),
                        ];
                        $this->eventActions->saveEvent(
                            $user,
                            AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI->value,
                            new DateTime(),
                            $eventMetadata
                        );
                    } else {
                        $this->addFlash(
                            'error',
                            $this->translator->trans('passwordNotSentSMS', [], 'controllers')
                        );
                    }
                }
            }
            $this->addFlash(
                'success',
                $this->translator->trans(
                    'passwordUpdated',
                    [
                        '%uuid%' => $user->getUuid()
                    ],
                    'controllers'
                )
            );

            // Return to the last page where the user was (with searching filters)
            $lastPage = $request->headers->get('referer', '/dashboard');
            return $this->redirect($lastPage);
        }

        $lastConnectedProfile = $this->radiusProfileRepository->findUserLastConnection($user);

        if ($lastConnectedProfile instanceof UserRadiusProfile) {
            $lastStartConnection = $lastConnectedProfile->getLastConnectionStartAt();
            $lastStopConnection = $lastConnectedProfile->getLastConnectionStopAt();
        } else {
            $lastStartConnection = null;
            $lastStopConnection = null;
        }

        return $this->render(
            'dashboard/actions/edit.html.twig',
            [
                'form' => $form->createView(),
                'formReset' => $formReset->createView(),
                'user' => $user,
                'data' => $data,
                'current_user' => $currentUser,
                'context' => FirewallType::DASHBOARD->value,
                'userUpdateDTO' => $userUpdateDTO,
                'lastStartConnection' => $lastStartConnection,
                'lastStopConnection' => $lastStopConnection,
                'isEditingSelf' => $isEditingSelf
            ]
        );
    }

    /**
     * Render a confirmation password form
     */
    /**
     * @param string $type Type of action
     */
    #[Route('/dashboard/confirm/{type}', name: 'admin_confirm_reset')]
    #[IsGranted(UserAuthenticationVoter::USERS_MANAGEMENT_WRITE)]
    public function confirmReset(string $type): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        return $this->render('dashboard/actions/confirm.html.twig', [
            'data' => $data,
            'type' => $type,
            'user' => $currentUser,
        ]);
    }

    /**
     * @throws \Exception
     * @throws TransportExceptionInterface
     */
    #[Route('/dashboard/disable2FA/{id<\d+>}', name: 'app_disable2FA_admin')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function disabledBy2FA(
        Request $request,
        int $id,
    ): RedirectResponse {
        if (!$user = $this->userRepository->find($id)) {
            // Get the 'id' parameter from the route URL
            $this->addFlash(
                'error',
                $this->translator->trans('userNotFound', [], 'controllers')
            );
            return $this->redirectToRoute('admin_page');
        }

        $userExternalAuths = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

        // Disable the current associated Profile
        $this->profileManager->disableProfiles(
            $user,
            UserRadiusProfileRevokeReason::TWO_FA_DISABLED_BY->value,
            true
        );

        // Change user 2FA status
        $this->twoFAService->disable2FA($user);
        $this->twoFAService->event2FA(
            $request->getClientIp(),
            $user,
            AnalyticalEventType::DISABLED_2FA_BY->value,
            $request->headers->get('User-Agent')
        );

        if ($user->getEmail()) {
            $this->mailer->send($this->verificationCodeEmailGenerator->createEmail2FADisabledBy($user));
        } elseif (
            $user->getPhoneNumber()
            && $userExternalAuths->getProviderId() === UserProvider::PHONE_NUMBER->value
        ) {
            $message = $this->translator->trans('2faDisabledMessage', [], 'controllers');
            $this->sendSMS->sendSmsNoValidation($user, $message);
            $smsResponse = $this->sendSMS->sendSmsNoValidation($user, $message);

            if ($smsResponse !== '' && $smsResponse !== '0') {
                $this->addFlash(
                    'success',
                    $this->translator->trans('2faDisabledSMSSent', [], 'controllers')
                );
            } else {
                $this->addFlash(
                    'success',
                    $this->translator->trans('2faDisabledSMSFailed', [], 'controllers')
                );
            }
        } else {
            $this->addFlash(
                'success',
                $this->translator->trans('twoFASuccessfullyDisabled', [], 'controllers')
            );
        }

        return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
    }

    #[Route('/dashboard/adminPermissionsAdd/{id:user<\d+>}', name: 'admin_add_permissions')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function giveAdminPermissions(Request $request, User $user): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($user->getId() === $currentUser->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($user->getId() !== $currentUser->getId() &&
            !$this->isGranted(UserAuthenticationVoter::ADMIN_MANAGEMENT_WRITE)) {
            throw $this->createAccessDeniedException();
        }

        $user->setRoles([AdminRoleType::ROLE_ADMIN->value]);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $eventMetaData = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'platform' => PlatformMode::LIVE->value,
            'giveAdminPermissionsTo' => $user->getUuid(),
            'by' => $currentUser->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::ADMIN_ADDED_PERMISSIONS->value,
            new DateTime(),
            $eventMetaData
        );

        return $this->redirect($request->headers->get('Referer'));
    }

    #[Route('/dashboard/adminPermissionsRemove/{id:user<\d+>}', name: 'admin_remove_permissions')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function removeAdminPermissions(Request $request, User $user): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($user->getId() === $currentUser->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($user->getId() !== $currentUser->getId() && !$this->isGranted(
                UserAuthenticationVoter::ADMIN_MANAGEMENT_WRITE
            )) {
            throw $this->createAccessDeniedException();
        }

        $user->setRoles(["ROLE_USER"]);
        $user->setPermissions([]);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $eventMetaData = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'platform' => PlatformMode::LIVE->value,
            'removeAdminPermissionsTo' => $user->getUuid(),
            'by' => $currentUser->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::ADMIN_REMOVED_PERMISSIONS->value,
            new DateTime(),
            $eventMetaData
        );

        return $this->redirect($request->headers->get('Referer'));
    }
}
