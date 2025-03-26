<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use App\Enum\UserRadiusProfileRevokeReason;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Form\ResetPasswordType;
use App\Form\UserUpdateType;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        private readonly SettingRepository $settingRepository,
        private readonly EventRepository $eventRepository,
        private readonly SendSMS $sendSMS,
        private readonly UserDeletionService $userDeletionService,
        private readonly TwoFAService $twoFAService,
        private readonly VerificationCodeEmailGenerator $verificationCodeEmailGenerator,
    ) {
    }

    #[Route('/dashboard/revoke/{id<\d+>}', name: 'admin_user_revoke_profiles', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function revokeUsers(
        Request $request,
        UserRepository $userRepository,
        $id
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $user = $userRepository->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_landing');
        }
        $revokeProfiles = $this->profileManager->disableProfiles(
            $user,
            UserRadiusProfileRevokeReason::ADMIN_REVOKED_PROFILE->value,
            true
        );
        if (!$revokeProfiles) {
            $this->addFlash('error_admin', 'This account doesn\'t have profiles associated!');
            return $this->redirectToRoute('admin_page');
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
            'success_admin',
            sprintf(
                'Profile associated "%s" have been revoked.',
                $user->getUuid()
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
    #[IsGranted('ROLE_ADMIN')]
    public function exportUsers(
        Request $request
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Check if the export users operation is enabled
        $exportUsers = $this->parameterBag->get('app.export_users');
        if ($exportUsers === OperationMode::OFF->value) {
            $this->addFlash('error_admin', 'This operation is disabled for security reasons');
            return $this->redirectToRoute('admin_page');
        }

        // Fetch all users excluding admins
        $users = $this->userRepository->findExcludingAdmin();

        // Create a PHPSpreadsheet object
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Define each respective header for the User table
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'UUID');
        $sheet->setCellValue('C1', 'Email');
        $sheet->setCellValue('D1', 'Phone Number');
        $sheet->setCellValue('E1', 'First Name');
        $sheet->setCellValue('F1', 'Last Name');
        $sheet->setCellValue('G1', 'Verification');
        $sheet->setCellValue('H1', 'Provider');
        $sheet->setCellValue('I1', 'ProviderId');
        $sheet->setCellValue('J1', 'Banned At');
        $sheet->setCellValue('K1', 'Created At');

        // Apply the data
        $row = 2;

        $escapeSpreadSheetService = new EscapeSpreadSheet();

        foreach ($users as $user) {
            $sheet->setCellValue('A' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($user->getId()));

            // Check if UUID might be a phone number and format accordingly
            $uuid = $user->getUuid();
            if (is_numeric($uuid)) {
                // If UUID is numeric, treat it as a string to prevent scientific notation
                $sheet->setCellValueExplicit(
                    'B' . $row,
                    $escapeSpreadSheetService->escapeSpreadsheetValue($uuid),
                    DataType::TYPE_STRING
                );
            } else {
                $sheet->setCellValue('B' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($uuid));
            }

            $sheet->setCellValue('C' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($user->getEmail()));

            // Handle Phone Number
            $phoneNumber = $user->getPhoneNumber();
            if ($phoneNumber) {
                $sheet->setCellValueExplicit(
                    'D' . $row,
                    $escapeSpreadSheetService->escapeSpreadsheetValue($phoneNumber),
                    DataType::TYPE_STRING
                );
            } else {
                $sheet->setCellValue('D' . $row, '');
            }

            $sheet->setCellValue('E' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($user->getFirstName()));
            $sheet->setCellValue('F' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($user->getLastName()));
            $sheet->setCellValue(
                'G' . $row,
                $escapeSpreadSheetService->escapeSpreadsheetValue($user->isVerified() ? 'Verified' : 'Not Verified')
            );

            // Determine User Provider && ProviderId
            $userExternalAuthRepository = $this->entityManager->getRepository(UserExternalAuth::class);
            $userExternalAuth = $userExternalAuthRepository->findOneBy(['user' => $user]);

            $provider = $userExternalAuth !== null ? $userExternalAuth->getProvider() : 'No Provider';
            $sheet->setCellValue('H' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($provider));

            $providerID = $userExternalAuth !== null ? $userExternalAuth->getProviderId() : 'No ProviderId';
            $sheet->setCellValue('I' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($providerID));

            // Check if the user is Banned
            $sheet->setCellValue(
                'J' . $row,
                $escapeSpreadSheetService->escapeSpreadsheetValue(
                    $user->getBannedAt() !== null ? $user->getBannedAt()->format('Y-m-d H:i:s') : 'Not Banned'
                )
            );
            $sheet->setCellValue('K' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($user->getCreatedAt()));

            $row++;
        }

        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'users');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'uuid' => $currentUser->getUuid(),
        ];
        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::EXPORT_USERS_TABLE_REQUEST->value,
            new DateTime(),
            $eventMetadata
        );

        // Return the file as a response
        return $this->file($tempFile, 'users.xlsx');
    }

    /**
     * Deletes Users from the Portal, encrypts the data before delete and saves it
     */
    /**
     * @throws \JsonException
     */
    #[Route('/dashboard/delete/{id<\d+>}', name: 'admin_user_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteUsers(
        int $id,
        Request $request,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Fetch user and external auths
        $user = $this->userRepository->find($id);
        $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $id]);
        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }
        $getUserUuid = $user->getUuid();

        if ($user->getDeletedAt() !== null) {
            $this->addFlash('error_admin', 'This user has already been deleted.');
            return $this->redirectToRoute('admin_page');
        }

        $result = $this->userDeletionService->deleteUser($user, $userExternalAuths, $request, $currentUser);
        // Handle the success or failure response
        if (!$result['success']) {
            $this->addFlash('error_admin', $result['message']);
            return $this->redirectToRoute('admin_page');
        }

        $this->addFlash('success_admin', sprintf('User with the UUID "%s" deleted successfully.', $getUserUuid));
        return $this->redirectToRoute('admin_page');
    }

    /**
     * Handles the edit of the Users by the admin
     */
    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/dashboard/edit/{id<\d+>}', name: 'admin_user_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function editUsers(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        $id
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$user = $this->userRepository->find($id)) {
            // Get the 'id' parameter from the route URL
            $this->addFlash('error_admin', 'The user does not exist.');
            return $this->redirectToRoute('admin_page');
        }

        if ($user->getDeletedAt() !== null) {
            $this->addFlash('error_admin', 'This user has already been deleted.');
            return $this->redirectToRoute('admin_page');
        }

        // Store the initial bannedAt value before form submission
        $initialBannedAtValue = $user->getBannedAt();

        $form = $this->createForm(UserUpdateType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();

            // Verifies if the isVerified is removed to the logged account
            if (($currentUser->getId() === $user->getId()) && $form->get('isVerified')->getData() === 0) {
                $user->isVerified();
                $this->addFlash('error_admin', 'Sorry, administrators cannot remove is own verification.');
                return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
            }

            // Verifies if the bannedAt was submitted and compares the form value "banned" to the current value
            if ($form->get('bannedAt')->getData() && $user->getBannedAt() !== $initialBannedAtValue) {
                if ($currentUser->getId() === $user->getId()) {
                    $this->addFlash('error_admin', 'Sorry, administrators cannot ban themselves.');
                    return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
                }
                $user->setBannedAt(new DateTime());
                $this->profileManager->disableProfiles(
                    $user,
                    UserRadiusProfileRevokeReason::ADMIN_BANNED_USER->value
                );
            } else {
                $user->setBannedAt(null);
                if ($form->get('isVerified')->getData()) {
                    $this->profileManager->enableProfiles($user);
                } else {
                    $this->profileManager->disableProfiles(
                        $user,
                        UserRadiusProfileRevokeReason::ADMIN_REMOVED_USER_VERIFICATION->value,
                        true
                    );
                }
            }

            $userRepository->save($user, true);

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'edited' => $user->getUuid(),
                'by' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_FROM_UI->value,
                new DateTime(),
                $eventMetadata
            );

            $uuid = $user->getUuid();
            $this->addFlash('success_admin', sprintf('"%s" has been updated successfully.', $uuid));

            return $this->redirectToRoute('admin_page');
        }

        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        $formReset = $this->createForm(ResetPasswordType::class, $user);
        $formReset->handleRequest($request);

        if ($formReset->isSubmitted() && $formReset->isValid()) {
            // get the both typed passwords by the admin
            $newPassword = $formReset->get('password')->getData();
            $confirmPassword = $formReset->get('confirmPassword')->getData();

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error_admin', 'Both the password and password confirmation fields must match.');
                return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
            }

            // Get the User Provider && ProviderId
            $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

            // Hash the new password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $em->flush();

            if ($user->getEmail()) {
                $supportTeam = $data['title']['value'];
                // Send email
                $email = new Email()
                    ->from(new Address($emailSender, $nameSender))
                    ->to($user->getEmail())
                    ->subject('Your Password Reset Details')
                    ->html(
                        $this->renderView(
                            'email/user_password.html.twig',
                            ['password' => $newPassword, 'isNewUser' => false, 'supportTeam' => $supportTeam]
                        )
                    );
                $mailer->send($email);

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

            if ($user->getPhoneNumber() && $userExternalAuth->getProviderId() === UserProvider::PHONE_NUMBER->value) {
                $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent(
                    $user,
                    AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI
                );
                // Retrieve the SMS resend interval from the settings
                $smsResendInterval = $data['SMS_TIMER_RESEND']['value'];
                $minInterval = new DateInterval('PT' . $smsResendInterval . 'M');
                $currentTime = new DateTime();

                // Retrieve the metadata from the latest event
                $latestEventMetadata = $latestEvent instanceof \App\Entity\Event ? $latestEvent->getEventMetadata(
                ) : [];
                $lastResetAccountPasswordTime = isset($latestEventMetadata['lastResetAccountPasswordTime'])
                    ? new DateTime($latestEventMetadata['lastResetAccountPasswordTime'])
                    : null;
                $resetAttempts = $latestEventMetadata['resetAttempts'] ?? 0;

                if (
                    (!$latestEvent || $resetAttempts < 3)
                    && (!$latestEvent
                        || ($lastResetAccountPasswordTime instanceof DateTime
                            && $lastResetAccountPasswordTime->add(
                                $minInterval
                            ) < $currentTime))
                ) {
                    $attempts = $resetAttempts + 1;

                    $message = "Your new account password is: " . $newPassword . "%0A";
                    $this->sendSMS->sendSmsNoValidation($user->getPhoneNumber(), $message);

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
                }
            }
            $this->addFlash('success_admin', sprintf('"%s" is password was updated.', $user->getUuid()));
            return $this->redirectToRoute('admin_page');
        }

        return $this->render(
            'admin/edit.html.twig',
            [
                'form' => $form->createView(),
                'formReset' => $formReset->createView(),
                'user' => $user,
                'data' => $data,
                'current_user' => $currentUser,
                'context' => FirewallType::DASHBOARD->value,
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
    #[IsGranted('ROLE_ADMIN')]
    public function confirmReset(string $type): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        return $this->render('admin/confirm.html.twig', [
            'data' => $data,
            'type' => $type
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/dashboard/disable2FA/{id<\d+>}', name: 'app_disable2FA_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function disabledBy2FA(
        Request $request,
        $id,
        MailerInterface $mailer
    ): RedirectResponse {
        if (!$user = $this->userRepository->find($id)) {
            // Get the 'id' parameter from the route URL
            $this->addFlash('error_admin', 'The user does not exist.');
            return $this->redirectToRoute('admin_page');
        }
        // Get the User Provider && ProviderId
        $userExternalAuths = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

        // Disable current associated Profile
        $this->profileManager->disableProfiles(
            $user,
            UserRadiusProfileRevokeReason::TWO_FA_DISABLED_BY->value,
            true
        );

        // Change user 2fa status
        $user->setTwoFAtype(UserTwoFactorAuthenticationStatus::DISABLED->value);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->twoFAService->event2FA(
            $request->getClientIp(),
            $user,
            AnalyticalEventType::DISABLE_2FA->value,
            $request->headers->get('User-Agent')
        );

        if ($user->getEmail()) {
            $mailer->send($this->verificationCodeEmailGenerator->createEmail2FADisabledBy($user));
        } elseif (
            $user->getPhoneNumber() &&
            $userExternalAuths->getProviderId() === UserProvider::PHONE_NUMBER->value
        ) {
            $message = "Your OpenRoaming 2FA has been disabled. Please re-enable it as soon as possible.";
            $this->sendSMS->sendSmsNoValidation($user->getPhoneNumber(), $message);
        }

        $this->addFlash(
            'success_admin',
            'Two factor authentication successfully disabled'
        );
        return $this->redirectToRoute('admin_user_edit', [
            'id' => $user->getId(),
        ]);
    }
}
