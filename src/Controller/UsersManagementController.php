<?php

namespace App\Controller;

use App\DTO\UserAddDTO;
use App\DTO\UserUpdateDTO;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\UserProvider;
use App\Enum\UserRadiusProfileRevokeReason;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Form\ResetPasswordType;
use App\Form\UserAddType;
use App\Form\UserUpdateType;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EmailGenerator;
use App\Service\EscapeSpreadSheet;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\PasswordResetDashboardService;
use App\Service\ProfileManager;
use App\Service\SendSMS;
use App\Service\TwoFAService;
use App\Service\UserCreationService;
use App\Service\UserDeletion\UserDeletionService;
use App\Service\VerificationCodeEmailGenerator;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use libphonenumber\PhoneNumber;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

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
        private readonly UserDeletionService $userDeletionService,
        private readonly TwoFAService $twoFAService,
        private readonly VerificationCodeEmailGenerator $verificationCodeEmailGenerator,
        private readonly TranslatorInterface $translator,
        private readonly MailerInterface $mailer,
        private readonly PasswordResetDashboardService $passwordResetDashboardService,
        private readonly UserCreationService $userCreationService,
        private readonly EmailGenerator $emailGenerator,
        private readonly SendSMS $sendSMS,
    ) {
    }

    #[Route('/dashboard/user/revoke/{id:user<\d+>}', name: 'admin_dashboard_user_revoke_profiles', methods: ['POST'])]
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
                in_array(AdminRoleType::ROLE_ADMIN->value, $user->getRoles(), true)
                || in_array(AdminRoleType::ROLE_SUPER_ADMIN->value, $user->getRoles(), true)
            )
        ) {
            throw $this->createAccessDeniedException();
        }

        $eventMetaData = [
            EventMetadataKeysType::IP->value => $request->getClientIp(),
            EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
            EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
            EventMetadataKeysType::PERFORMED_ON_UUID->value => $user->getUuid()
        ];

        $this->eventActions->saveEvent(
            $currentUser,
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
    #[Route('/dashboard/export/users', name: 'admin_dashboard_users_export')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function exportUsers(): Response
    {
        // Check if export is enabled
        $exportUsers = (bool) $this->parameterBag->get('app.export_users');
        if (!$exportUsers) {
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
        $includeAdminColumn = $this->isGranted(AdminRoleType::ROLE_SUPER_ADMIN->value);
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

            $sheet->setCellValueExplicit('C' . $row, $user->getEmail() ?? '', DataType::TYPE_STRING);

            // Phone number
            $phoneNumber = $user->getPhoneNumber();
            if ($phoneNumber) {
                $sheet->setCellValueExplicit('D' . $row, $phoneNumber, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue('D' . $row, '');
            }

            $sheet->setCellValueExplicit('E' . $row, (string)$user->getFirstName(), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F' . $row, (string)$user->getLastName(), DataType::TYPE_STRING);
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
            $sheet->setCellValue($createdColumn . $row, $user->getCreatedAt()?->format('Y-m-d H:i:s') ?? '');

            $row++;
        }

        // Output file
        $tempFile = tempnam(sys_get_temp_dir(), 'users');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $this->file($tempFile, 'users.xlsx');
    }

    /**
     * @throws \Random\RandomException
     */
    #[Route('/dashboard/add', name: 'admin_dashboard_add_admin')]
    #[IsGranted(UserAuthenticationVoter::ADMIN_MANAGEMENT_WRITE)]
    public function addUsers(Request $request): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Create & handle form
        $userAddDTO = new UserAddDTO();
        $form = $this->createForm(UserAddType::class, $userAddDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Convert DTO → Entity data before creation
            $newUser = $this->userCreationService->createAdminUser($userAddDTO);

            // Flash message
            $this->addFlash(
                'success',
                $this->translator->trans('addedNewUser', [
                    '%uuid%' => $newUser->getUuid(),
                ], 'controllers')
            );

            $eventMetaData = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                EventMetadataKeysType::ADMIN_ACCOUNT_CREATED->value => $newUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::ADMIN_ADDED_NEW_USER->value,
                new DateTime(),
                $eventMetaData
            );

            return $this->redirectToRoute('admin_dashboard_admins');
        }

        return $this->render('dashboard/actions/add.html.twig', [
            'form' => $form->createView(),
            'userAddDTO' => $userAddDTO,
            'data' => $data,
            'current_user' => $currentUser,
            'context' => FirewallType::DASHBOARD->value,
            'isEditingSelf' => false,
        ]);
    }

    /**
     * Deletes Users from the Portal, encrypts the data before delete and saves it
     */
    /**
     * @throws \JsonException
     * @throws ORMException
     * @throws ExceptionInterface
     */
    #[Route('/dashboard/user/delete/{id:user<\d+>}', name: 'admin_dashboard_user_delete', methods: ['POST'])]
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

        if ($user->getDeletedAt() instanceof DateTimeInterface) {
            $this->addFlash(
                'error',
                $this->translator->trans('userAlreadyDeleted', [], 'controllers')
            );
            return $this->redirectToRoute('admin_page');
        }

        try {
            // Notify the deleted user (via email or SMS)
            if ($user->getEmail() !== null) {
                $this->emailGenerator->sendUserAccountDeletionConfirmationEmail($user);
            } elseif ($user->getPhoneNumber() instanceof PhoneNumber) {
                $message = $this->translator->trans('sms_account_deletion', [], 'UserDeletionService');
                $this->sendSMS->sendSmsNoValidation($user, $message);
            }

            // Notify all admins of the deletion
            $admins = $this->userRepository->findAllAdmins();

            foreach ($admins as $admin) {
                if ($admin->getEmail() !== null) {
                    $this->emailGenerator->sendAdminUserDeletionAccountConfirmationEmail($user, $admin, $currentUser);
                }
            }
        } catch (Throwable) {
            // non-fatal — deletion continues regardless
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

        return $this->redirectToRoute('admin_page');
    }

    /**
     * Handles the edit of the Users (Super-admin || Admin || User)
     */
    /**
     * @throws TransportExceptionInterface
     * @throws \DateMalformedStringException
     * @throws \DateMalformedIntervalStringException
     */
    #[Route('/dashboard/edit/{id:user<\d+>}', name: 'admin_dashboard_user_edit')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function editUsers(
        Request $request,
        User $user
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::USERS_MANAGEMENT_WRITE) ||
            $this->isGranted(UserAuthenticationVoter::ADMIN_MANAGEMENT_WRITE);

        if ($user->getId() !== $currentUser->getId()) {
            if (
                !$this->isGranted(UserAuthenticationVoter::USERS_MANAGEMENT_READ) &&
                !$this->isGranted(UserAuthenticationVoter::ADMIN_MANAGEMENT_READ)
            ) {
                throw $this->createAccessDeniedException();
            }
            if (
                !$this->isGranted(AdminRoleType::ROLE_ADMIN->value)
            ) {
                throw $this->createAccessDeniedException();
            }
        }
        if (!$canWrite && $user->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($user->getDeletedAt() instanceof DateTimeInterface) {
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

        if ($canWrite && $form->isSubmitted() && $form->isValid()) {
            // Use DTO method to map data back
            $userUpdateDTO->updateUser($user, $userUpdateDTO->editingAdmin);

            $uow = $this->entityManager->getUnitOfWork();

            $uow->computeChangeSets();

            $changeset = $uow->getEntityChangeSet($user);

            $formattedChanges = [];

            foreach ($changeset as $field => $change) {
                if (!is_array($change)) {
                    continue;
                }

                [$oldValue, $newValue] = $change;

                $formattedChanges[$field] = [
                    EventMetadataKeysType::OLD_DATA->value => $oldValue,
                    EventMetadataKeysType::NEW_DATA->value => $newValue,
                ];
            }

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

            $eventMetaData = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                EventMetadataKeysType::PERFORMED_ON_UUID->value => $user->getUuid(),
                EventMetadataKeysType::CHANGESET->value => $formattedChanges,
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_FROM_DASHBOARD->value,
                new DateTime(),
                $eventMetaData
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

            // Return to the user profile details
            return $this->redirectToRoute('admin_dashboard_user_show', ['id' => $user->getId()]);
        }

        return $this->render(
            'dashboard/actions/edit.html.twig',
            [
                'form' => $form->createView(),
                'user' => $user,
                'data' => $data,
                'current_user' => $currentUser,
                'context' => FirewallType::DASHBOARD->value,
                'userUpdateDTO' => $userUpdateDTO,
                'isEditingSelf' => $isEditingSelf
            ]
        );
    }

    /**
     * @throws TransportExceptionInterface
     * @throws \DateMalformedIntervalStringException
     * @throws \DateMalformedStringException
     */
    #[Route('/dashboard/user/reset-password/{id:user<\d+>}', name: 'admin_dashboard_user_reset_password')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function resetPassword(Request $request, User $user): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::USERS_MANAGEMENT_WRITE) ||
            $this->isGranted(UserAuthenticationVoter::ADMIN_MANAGEMENT_WRITE);

        if (!$canWrite) {
            throw $this->createAccessDeniedException();
        }

        $formReset = $this->createForm(ResetPasswordType::class, $user);
        $formReset->handleRequest($request);

        if ($formReset->isSubmitted() && $formReset->isValid()) {
            $newPassword = $formReset->get('password')->getData();
            $confirmPassword = $formReset->get('confirmPassword')->getData();

            if ($newPassword !== $confirmPassword) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('PasswordPasswordConfirmationMustMatch', [], 'controllers')
                );
                return $this->redirectToRoute('admin_dashboard_user_edit', ['id' => $user->getId()]);
            }

            $flashes = $this->passwordResetDashboardService->resetPassword(
                $user,
                $newPassword,
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
                $currentUser
            );

            foreach ($flashes as $flash) {
                $this->addFlash($flash['type'], $flash['message']);
            }

            $this->addFlash(
                'success',
                $this->translator->trans('passwordUpdated', ['%uuid%' => $user->getUuid()], 'controllers')
            );

            return $this->redirectToRoute('admin_page');
        }

        return $this->render('dashboard/actions/reset_password.html.twig', [
            'formReset' => $formReset->createView(),
            'user' => $user,
            'data' => $this->getSettings->getSettings(),
            'context' => FirewallType::DASHBOARD->value,
        ]);
    }

    /**
     * @throws \Exception
     * @throws TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route('/dashboard/user/disable2FA/{id<\d+>}', name: 'admin_dashboard_user_disable2FA')]
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

        // Check permissions based on the target user's roles
        $targetIsAdmin = in_array(AdminRoleType::ROLE_ADMIN->value, $user->getRoles(), true) ||
            in_array(AdminRoleType::ROLE_SUPER_ADMIN->value, $user->getRoles(), true);

        if ($targetIsAdmin) {
            if (!$this->isGranted(UserAuthenticationVoter::ADMIN_MANAGEMENT_WRITE)) {
                throw $this->createAccessDeniedException();
            }
        } elseif (!$this->isGranted(UserAuthenticationVoter::USERS_MANAGEMENT_WRITE)) {
            throw $this->createAccessDeniedException();
        }

        $userExternalAuths = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

        // Disable the current associated Profile
        $this->profileManager->disableProfiles(
            $user,
            UserRadiusProfileRevokeReason::TWO_FA_DISABLED_BY->value,
            true
        );

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Change user 2FA status
        $this->twoFAService->disable2FA($user);
        $this->twoFAService->event2FA(
            $request->getClientIp(),
            $user,
            AnalyticalEventType::DISABLED_2FA_BY->value,
            $request->headers->get('User-Agent'),
            $currentUser
        );

        $this->addFlash(
            'success',
            $this->translator->trans('twoFASuccessfullyDisabled', [], 'controllers')
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

        return $this->redirectToRoute('admin_dashboard_user_show', ['id' => $user->getId()]);
    }
}
