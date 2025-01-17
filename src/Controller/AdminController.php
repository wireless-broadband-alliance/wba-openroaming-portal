<?php

namespace App\Controller;

use App\Entity\DeletedUserData;
use App\Entity\Event;
use App\Entity\Setting;
use App\Entity\TextEditor;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\PlatformMode;
use App\Enum\TextEditorName;
use App\Enum\UserProvider;
use App\Enum\UserVerificationStatus;
use App\Form\AuthType;
use App\Form\CapportType;
use App\Form\CustomType;
use App\Form\LDAPType;
use App\Form\RadiusType;
use App\Form\ResetPasswordType;
use App\Form\RevokeProfilesType;
use App\Form\SMSType;
use App\Form\StatusType;
use App\Form\TermsType;
use App\Form\UserUpdateType;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\CertificateService;
use App\Service\Domain;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\PgpEncryptionService;
use App\Service\ProfileManager;
use App\Service\SanitizeHTML;
use App\Service\SendSMS;
use App\Service\Statistics;
use App\Service\VerificationCodeGenerator;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    private MailerInterface $mailer;
    private UserRepository $userRepository;
    private UserExternalAuthRepository $userExternalAuthRepository;
    private ProfileManager $profileManager;
    private ParameterBagInterface $parameterBag;
    private GetSettings $getSettings;
    private SettingRepository $settingRepository;
    private EntityManagerInterface $entityManager;
    private PgpEncryptionService $pgpEncryptionService;
    private EventActions $eventActions;
    private VerificationCodeGenerator $verificationCodeGenerator;
    private SendSMS $sendSMS;
    private EventRepository $eventRepository;

    /**
     * @param MailerInterface $mailer
     * @param UserRepository $userRepository
     * @param UserExternalAuthRepository $userExternalAuthRepository
     * @param ProfileManager $profileManager
     * @param ParameterBagInterface $parameterBag
     * @param GetSettings $getSettings
     * @param SettingRepository $settingRepository
     * @param EntityManagerInterface $entityManager
     * @param PgpEncryptionService $pgpEncryptionService
     * @param EventActions $eventActions
     * @param VerificationCodeGenerator $verificationCodeGenerator
     * @param SendSMS $sendSMS
     * @param EventRepository $eventRepository
     */
    public function __construct(
        MailerInterface $mailer,
        UserRepository $userRepository,
        UserExternalAuthRepository $userExternalAuthRepository,
        ProfileManager $profileManager,
        ParameterBagInterface $parameterBag,
        GetSettings $getSettings,
        SettingRepository $settingRepository,
        EntityManagerInterface $entityManager,
        PgpEncryptionService $pgpEncryptionService,
        EventActions $eventActions,
        VerificationCodeGenerator $verificationCodeGenerator,
        SendSMS $sendSMS,
        EventRepository $eventRepository
    ) {
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
        $this->userExternalAuthRepository = $userExternalAuthRepository;
        $this->profileManager = $profileManager;
        $this->parameterBag = $parameterBag;
        $this->getSettings = $getSettings;
        $this->settingRepository = $settingRepository;
        $this->entityManager = $entityManager;
        $this->pgpEncryptionService = $pgpEncryptionService;
        $this->eventActions = $eventActions;
        $this->verificationCodeGenerator = $verificationCodeGenerator;
        $this->sendSMS = $sendSMS;
        $this->eventRepository = $eventRepository;
    }

    /*
    * Dashboard Page Main Route
    */
    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @param int $page
     * @param string $sort
     * @param string $order
     * @param int|null $count
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route('/dashboard', name: 'admin_page')]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboard(
        Request $request,
        UserRepository $userRepository,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] string $sort = 'createdAt',
        #[MapQueryParameter] string $order = 'desc',
        #[MapQueryParameter] ?int $count = 7
    ): Response {
        if (!is_int($count) || $count <= 0) {
            return $this->redirectToRoute('admin_page');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $searchTerm = $request->query->get('u');
        $perPage = $count;

        $filter = $request->query->get('filter', 'all'); // Default filter

        // Use the updated searchWithFilter method to handle both filter and search term
        $users = $userRepository->searchWithFilter($filter, $searchTerm);

        // Fetch UserExternalAuth entities for the paginated users
        $userExternalAuths = [];
        foreach ($users as $user) {
            $auths = $this->userExternalAuthRepository->findBy(['user' => $user]);
            $userExternalAuths[$user->getId()] = $auths;
        }

        // Sort the users based on the specified column and order
        usort($users, static function ($user1, $user2) use ($sort, $order) {
            // This function is used to sort the arrays uuid and created_at
            // and compares them with the associated users with the highest number to the lowest id from both arrays
            $value1 = $sort === 'createdAt' ? $user1->getCreatedAt() : $user1->getUuid();
            $value2 = $sort === 'createdAt' ? $user2->getCreatedAt() : $user2->getUuid();

            if ($order === 'asc') { // Check if the order is "asc" or "desc"
                //and returns the correct result between arrays
                return $value1 <=> $value2; // -1
            }

            return $value2 <=> $value1; // +1
        });


        // Perform pagination manually
        $totalUsers = count($users); // Get the total number of users

        $totalPages = ceil($totalUsers / $perPage); // Calculate the total number of pages

        $offset = ($page - 1) * $perPage; // Calculate the offset for slicing the users

        $users = array_slice($users, $offset, $perPage); // Fetch the users for the current page

        // Fetch user counts for table header (All/Verified/Banned)
        $allUsersCount = $userRepository->countAllUsersExcludingAdmin($searchTerm, $filter);
        $verifiedUsersCount = $userRepository->countVerifiedUsers($searchTerm);
        $bannedUsersCount = $userRepository->totalBannedUsers($searchTerm);

        // Check if the export users operation is enabled
        $exportUsers = $this->parameterBag->get('app.export_users');
        // Check if the delete action has a public PGP key defined
        $deleteUsers = $this->parameterBag->get('app.pgp_public_key');
        // Create form views
        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $this->getUser());

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'userExternalAuths' => $userExternalAuths,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'searchTerm' => $searchTerm,
            'data' => $data,
            'allUsersCount' => $allUsersCount,
            'verifiedUsersCount' => $verifiedUsersCount,
            'bannedUsersCount' => $bannedUsersCount,
            'activeFilter' => $filter,
            'activeSort' => $sort,
            'activeOrder' => $order,
            'count' => $count,
            'export_users' => $exportUsers,
            'delete_users' => $deleteUsers,
            'ApUsage' => null,
            'formRevokeProfiles' => $formRevokeProfiles
        ]);
    }

    #[Route('/dashboard/revoke/{id<\d+>}', name: 'admin_revoke_profiles', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function revokeUsers(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        $id
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $user = $userRepository->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_landing');
        }
        $revokeProfiles = $this->profileManager->disableProfiles($user, true);
        if (!$revokeProfiles) {
            $this->addFlash('error_admin', 'This account doesn\'t have profiles associated!');
            return $this->redirectToRoute('admin_page');
        }

        $eventMetaData = [
            'platform' => PlatformMode::LIVE,
            'userRevoked' => $user->getUuid(),
            'ip' => $request->getClientIp(),
            'by' => $currentUser->getUuid(),
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::ADMIN_REVOKE_PROFILES,
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

    /*
    * Handle export of the Users Table on the Main Route
    */
    /**
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/dashboard/export/users', name: 'admin_page_export_users')]
    #[IsGranted('ROLE_ADMIN')]
    public function exportUsers(
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Check if the export users operation is enabled
        $exportUsers = $this->parameterBag->get('app.export_users');
        if ($exportUsers === EmailConfirmationStrategy::NO_EMAIL) {
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
        foreach ($users as $user) {
            $sheet->setCellValue('A' . $row, $this->escapeSpreadsheetValue($user->getId()));

            // Check if UUID might be a phone number and format accordingly
            $uuid = $user->getUuid();
            if (is_numeric($uuid)) {
                // If UUID is numeric, treat it as a string to prevent scientific notation
                $sheet->setCellValueExplicit(
                    'B' . $row,
                    $this->escapeSpreadsheetValue($uuid),
                    DataType::TYPE_STRING
                );
            } else {
                $sheet->setCellValue('B' . $row, $this->escapeSpreadsheetValue($uuid));
            }

            $sheet->setCellValue('C' . $row, $this->escapeSpreadsheetValue($user->getEmail()));

            // Handle Phone Number
            $phoneNumber = $user->getPhoneNumber();
            if ($phoneNumber) {
                $sheet->setCellValueExplicit(
                    'D' . $row,
                    $this->escapeSpreadsheetValue($phoneNumber),
                    DataType::TYPE_STRING
                );
            } else {
                $sheet->setCellValue('D' . $row, '');
            }

            $sheet->setCellValue('E' . $row, $this->escapeSpreadsheetValue($user->getFirstName()));
            $sheet->setCellValue('F' . $row, $this->escapeSpreadsheetValue($user->getLastName()));
            $sheet->setCellValue(
                'G' . $row,
                $this->escapeSpreadsheetValue($user->isVerified() ? 'Verified' : 'Not Verified')
            );

            // Determine User Provider && ProviderId
            $userExternalAuthRepository = $this->entityManager->getRepository(UserExternalAuth::class);
            $userExternalAuth = $userExternalAuthRepository->findOneBy(['user' => $user]);

            $provider = $userExternalAuth !== null ? $userExternalAuth->getProvider() : 'No Provider';
            $sheet->setCellValue('H' . $row, $this->escapeSpreadsheetValue($provider));

            $providerID = $userExternalAuth !== null ? $userExternalAuth->getProviderId() : 'No ProviderId';
            $sheet->setCellValue('I' . $row, $this->escapeSpreadsheetValue($providerID));

            // Check if the user is Banned
            $sheet->setCellValue(
                'J' . $row,
                $this->escapeSpreadsheetValue(
                    $user->getBannedAt() !== null ? $user->getBannedAt()->format('Y-m-d H:i:s') : 'Not Banned'
                )
            );
            $sheet->setCellValue('K' . $row, $this->escapeSpreadsheetValue($user->getCreatedAt()));

            $row++;
        }

        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'users');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'uuid' => $currentUser->getUuid(),
        ];
        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::EXPORT_USERS_TABLE_REQUEST,
            new DateTime(),
            $eventMetadata
        );

        // Return the file as a response
        return $this->file($tempFile, 'users.xlsx');
    }

    /*
    * Deletes Users from the Portal, encrypts the data before delete and saves it
    */
    /**
     * @param $id
     * @param EntityManagerInterface $em
     * @param Request $request
     * @return Response
     * @throws \JsonException
     */
    #[Route('/dashboard/delete/{id<\d+>}', name: 'admin_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteUsers(
        $id,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $user = $this->userRepository->find($id);
        $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $id]);

        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        if ($user->getDeletedAt() !== null) {
            $this->addFlash('error_admin', 'This user has already been deleted.');
            return $this->redirectToRoute('admin_page');
        }

        $getUUID = $user->getUuid();

        // Prepare user data for encryption
        $deletedUserData = [
            'id' => $user->getId(),
            'uuid' => $user->getUuid(),
            'email' => $user->getEmail() ?? 'This value is empty',
            'phoneNumber' => $user->getPhoneNumber() ?? 'This value is empty',
            'firstName' => $user->getFirstName() ?? 'This value is empty',
            'lastName' => $user->getLastName() ?? 'This value is empty',
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'bannedAt' => $user->getBannedAt() ? $user->getBannedAt()->format('Y-m-d H:i:s') : null,
            'deletedAt' => new DateTime(),
        ];

        // Prepare external auth data for encryption
        $deletedUserExternalAuthData = [];
        foreach ($userExternalAuths as $externalAuth) {
            $deletedUserExternalAuthData[] = [
                'provider' => $externalAuth->getProvider(),
                'providerId' => $externalAuth->getProviderId()
            ];
        }

        // Combine user data and external auth data
        $combinedData = [
            'user' => $deletedUserData,
            'externalAuths' => $deletedUserExternalAuthData,
        ];
        $jsonDataCombined = json_encode($combinedData, JSON_THROW_ON_ERROR);

        // Encrypt combined JSON data using PGP encryption
        $pgpEncryptedService = new PgpEncryptionService();
        $pgpEncryptedData = $this->pgpEncryptionService->encrypt($jsonDataCombined);

        // Handle encryption errors
        if ($pgpEncryptedData[0] === UserVerificationStatus::MISSING_PUBLIC_KEY_CONTENT) {
            $this->addFlash(
                'error_admin',
                'The public key is not set. 
            Make sure to define a public key in pgp_public_key/public_key.asc'
            );
            return $this->redirectToRoute('admin_page');
        }

        if ($pgpEncryptedData[0] === UserVerificationStatus::EMPTY_PUBLIC_KEY_CONTENT) {
            $this->addFlash(
                'error_admin',
                'The public key is empty. 
            Make sure to define content for the public key in pgp_public_key/public_key.asc'
            );
            return $this->redirectToRoute('admin_page');
        }

        // Persist encrypted data
        $deletedUserData = new DeletedUserData();
        $deletedUserData->setPgpEncryptedJsonFile($pgpEncryptedData);
        $deletedUserData->setUser($user);

        $event = new Event();
        $event->setUser($user);
        $event->setEventDatetime(new DateTime());
        $event->setEventName(AnalyticalEventType::DELETED_USER_BY);
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $event->setEventMetadata([
            'deletedBy' => $currentUser->getUuid(),
            'ip' => $request->getClientIp(),
        ]);

        // Update user entity
        $user->setUuid($user->getId());
        $user->setEmail(null);
        $user->setPhoneNumber(null);
        $user->setPassword($user->getId());
        $user->setFirstName(null);
        $user->setLastName(null);
        $user->setDeletedAt(new DateTime());

        // Update external auth entity
        foreach ($userExternalAuths as $externalAuth) {
            $em->remove($externalAuth);
        }

        // Persist changes
        $this->disableProfiles($user);
        $em->persist($deletedUserData);
        $em->persist($user);
        $em->flush();

        // Save deletion event
        $eventMetadata = [
            'uuid' => $getUUID,
            'deletedBy' => $currentUser->getUuid(),
            'ip' => $request->getClientIp(),
        ];
        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::DELETED_USER_BY,
            new DateTime(),
            $eventMetadata
        );

        $this->addFlash('success_admin', sprintf('User with the UUID "%s" deleted successfully.', $getUUID));
        return $this->redirectToRoute('admin_page');
    }

    /*
    * Handles the edit of the Users by the admin
    */
    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @param UserPasswordHasherInterface $passwordHasher
     * @param EntityManagerInterface $em
     * @param MailerInterface $mailer
     * @param $id
     * @return Response
     * @throws TransportExceptionInterface
     */
    #[Route('/dashboard/edit/{id<\d+>}', name: 'admin_update')]
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
            if (($currentUser->getId() === $user->getId()) && $form->get('isVerified')->getData() == 0) {
                $user->isVerified();
                $this->addFlash('error_admin', 'Sorry, administrators cannot remove is own verification.');
                return $this->redirectToRoute('admin_update', ['id' => $user->getId()]);
            }

            // Verifies if the bannedAt was submitted and compares the form value "banned" to the current value
            if ($form->get('bannedAt')->getData() && $user->getBannedAt() !== $initialBannedAtValue) {
                if ($currentUser->getId() === $user->getId()) {
                    $this->addFlash('error_admin', 'Sorry, administrators cannot ban themselves.');
                    return $this->redirectToRoute('admin_update', ['id' => $user->getId()]);
                }
                $user->setBannedAt(new DateTime());
                $this->disableProfiles($user);
            } else {
                $user->setBannedAt(null);
                if ($form->get('isVerified')->getData()) {
                    $this->enableProfiles($user);
                } else {
                    $this->disableProfiles($user);
                }
            }

            $userRepository->save($user, true);

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'edited' => $user->getUuid(),
                'by' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_FROM_UI,
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
                return $this->redirectToRoute('admin_update', ['id' => $user->getId()]);
            }

            // Get the User Provider && ProviderId
            $userExternalAuthRepository = $this->entityManager->getRepository(UserExternalAuth::class);
            $userExternalAuth = $userExternalAuthRepository->findOneBy(['user' => $user]);

            // Hash the new password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $em->flush();

            if ($user->getEmail() && $userExternalAuth->getProviderId() == UserProvider::EMAIL) {
                // Send email
                $email = (new Email())
                    ->from(new Address($emailSender, $nameSender))
                    ->to($user->getEmail())
                    ->subject('Your Password Reset Details')
                    ->html(
                        $this->renderView(
                            'email/user_password.html.twig',
                            ['password' => $newPassword, 'isNewUser' => false]
                        )
                    );
                $mailer->send($email);

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'edited ' => $user->getUuid(),
                    'by' => $currentUser->getUuid(),
                ];

                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI,
                    new DateTime(),
                    $eventMetadata
                );
            }

            if ($user->getPhoneNumber() && $userExternalAuth->getProviderId() == UserProvider::PHONE_NUMBER) {
                $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent(
                    $user,
                    AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI
                );
                // Retrieve the SMS resend interval from the settings
                $smsResendInterval = $data['SMS_TIMER_RESEND']['value'];
                $minInterval = new DateInterval('PT' . $smsResendInterval . 'M');
                $currentTime = new DateTime();

                // Retrieve the metadata from the latest event
                $latestEventMetadata = $latestEvent ? $latestEvent->getEventMetadata() : [];
                $lastResetAccountPasswordTime = isset($latestEventMetadata['lastResetAccountPasswordTime'])
                    ? new DateTime($latestEventMetadata['lastResetAccountPasswordTime'])
                    : null;
                $resetAttempts = isset(
                    $latestEventMetadata['resetAttempts']
                ) ? $latestEventMetadata['resetAttempts'] : 0;

                if (!$latestEvent || $resetAttempts < 3) {
                    // Check if enough time has passed since the last reset
                    if (
                        !$latestEvent || ($lastResetAccountPasswordTime instanceof DateTime &&
                            $lastResetAccountPasswordTime->add($minInterval) < $currentTime)
                    ) {
                        $attempts = $resetAttempts + 1;

                        $message = "Your new account password is: " . $newPassword . "%0A";
                        $this->sendSMS->sendSmsReset($user->getPhoneNumber(), $message);

                        $eventMetadata = [
                            'ip' => $request->getClientIp(),
                            'edited' => $user->getUuid(),
                            'by' => $currentUser->getUuid(),
                            'resetAttempts' => $attempts,
                            'lastResetAccountPasswordTime' => $currentTime->format('Y-m-d H:i:s'),
                        ];
                        $this->eventActions->saveEvent(
                            $user,
                            AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI,
                            new DateTime(),
                            $eventMetadata
                        );
                    }
                }
            }
            $this->addFlash('success_admin', sprintf('"%s" has is password updated.', $user->getUuid()));
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
            ]
        );
    }

    /*
     * Render a confirmation password form
     */
    /**
     * @param string $type Type of action
     * @return Response
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

    /*
     * Check if the code and then return the correct action
     */
    /**
     * @param RequestStack $requestStack
     * @param EntityManagerInterface $em
     * @param string $type Type of action
     * @return Response
     * @throws Exception
     */
    #[Route('/dashboard/confirm-checker/{type}', name: 'admin_confirm_checker')]
    #[IsGranted('ROLE_ADMIN')]
    public function checkSettings(
        RequestStack $requestStack,
        EntityManagerInterface $em,
        Request $request,
        string $type
    ): Response {
        // Get the entered code from the form
        $enteredCode = $requestStack->getCurrentRequest()->request->get('code');
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($enteredCode === $currentUser->getVerificationCode()) {
            if ($type === 'settingCustom') {
                $command = 'php bin/console reset:customSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash('success_admin', 'The setting has been reset successfully!');
                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_PAGE_STYLE_RESET_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_customize');
            }

            if ($type === 'settingTerms') {
                $command = 'php bin/console reset:termsSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash('success_admin', 'The terms and policies settings has been reset successfully!');

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_TERMS_RESET_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_terms');
            }

            if ($type === 'settingRadius') {
                $command = 'php bin/console reset:radiusSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash('success_admin', 'The Radius configurations has been reset successfully!');

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_RADIUS_CONF_RESET_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_radius');
            }

            if ($type === 'settingStatus') {
                $command = 'php bin/console reset:statusSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash('success_admin', 'The platform mode status has been reset successfully!');

                $event = new Event();
                $event->setUser($currentUser);
                $event->setEventDatetime(new DateTime());
                $event->setEventName(AnalyticalEventType::SETTING_PLATFORM_STATUS_RESET_REQUEST);
                $event->setEventMetadata([
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid()
                ]);

                $em->persist($event);
                $em->flush();
                return $this->redirectToRoute('admin_dashboard_settings_status');
            }

            if ($type === 'settingLDAP') {
                $command = 'php bin/console reset:ldapSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash('success_admin', 'The LDAP settings has been reset successfully!');

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_LDAP_CONF_RESET_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_LDAP');
            }

            if ($type === 'settingCAPPORT') {
                $command = 'php bin/console reset:capportSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash('success_admin', 'The CAPPORT settings has been reset successfully!');

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_CAPPORT_CONF_RESET_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_capport');
            }

            if ($type === 'settingAUTH') {
                $command = 'php bin/console reset:authSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash('success_admin', 'The authentication settings has been reset successfully!');

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_AUTHS_CONF_RESET_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_auth');
            }

            if ($type === 'settingSMS') {
                $command = 'php bin/console reset:smsSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash('success_admin', 'The configuration SMS settings has been clear successfully!');

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_SMS_CONF_CLEAR_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_sms');
            }
        }

        $this->addFlash('error_admin', 'The verification code is incorrect. Please try again.');
        return $this->redirectToRoute('admin_confirm_reset', ['type' => $type]);
    }

    /**
     * Regenerate the verification code for the user and send a new email.
     *
     * @param string $type Type of action
     * @return RedirectResponse A redirect response.
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    #[Route('/dashboard/regenerate/{type}', name: 'app_dashboard_regenerate_code_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function regenerateCode(string $type, EntityManagerInterface $entityManager): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        // Regenerate the verification code for the admin to reset settings

        if ($type === 'settingCustom') {
            $email = $this->createEmailAdmin($currentUser->getEmail());
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingCustom']);
        }

        if ($type === 'settingTerms') {
            $email = $this->createEmailAdmin($currentUser->getEmail());
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingTerms']);
        }

        if ($type === 'settingRadius') {
            $email = $this->createEmailAdmin($currentUser->getEmail());
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingRadius']);
        }

        if ($type === 'settingStatus') {
            $email = $this->createEmailAdmin($currentUser->getEmail());
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingStatus']);
        }

        if ($type === 'settingLDAP') {
            $email = $this->createEmailAdmin($currentUser->getEmail());
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingLDAP']);
        }

        if ($type === 'settingCAPPORT') {
            $email = $this->createEmailAdmin($currentUser->getEmail());
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingCAPPORT']);
        }

        if ($type === 'settingAUTH') {
            $email = $this->createEmailAdmin($currentUser->getEmail());
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingAUTH']);
        }

        if ($type === 'settingSMS') {
            $email = $this->createEmailAdmin($currentUser->getEmail());
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingSMS']);
        }

        return $this->redirectToRoute('admin_page');
    }

    /**
     * Create an email message with the verification code.
     *
     * @param string $email The recipient's email address.
     * @return Email The email with the code.
     * @throws Exception
     */
    protected function createEmailAdmin(
        string $email
    ): Email {
        // Get the values from the services.yaml file using $parameterBag on the __construct
        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        // If the verification code is not provided, generate a new one
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $verificationCode = $this->verificationCodeGenerator->generateVerificationCode($currentUser);

        return (new TemplatedEmail())
            ->from(new Address($emailSender, $nameSender))
            ->to($email)
            ->subject('Your Settings Reset Details')
            ->htmlTemplate('email/admin_reset.html.twig')
            ->context([
                'verificationCode' => $verificationCode,
                'resetPassword' => false
            ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/terms', name: 'admin_dashboard_settings_terms')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsTerms(
        Request $request,
        EntityManagerInterface $em,
        GetSettings $getSettings
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $textEditorRepository = $em->getRepository(TextEditor::class);
        $tosTextEditor = $textEditorRepository->findOneBy(['name' => TextEditorName::TOS]);
        if (!$tosTextEditor) {
            $tosTextEditor = new TextEditor();
            $tosTextEditor->setName(TextEditorName::TOS);
            $tosTextEditor->setContent('');
            $em->persist($tosTextEditor);
        }
        $privacyPolicyTextEditor = $textEditorRepository->findoneBy(['name' => TextEditorName::PRIVACY_POLICY]);
        if (!$privacyPolicyTextEditor) {
            $privacyPolicyTextEditor = new TextEditor();
            $privacyPolicyTextEditor->setName(TextEditorName::PRIVACY_POLICY);
            $privacyPolicyTextEditor->setContent('');
            $em->persist($privacyPolicyTextEditor);
        }
        $em->flush();

        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        foreach ($settings as $setting) {
            if ($setting->getName() === 'TOS_EDITOR' || $setting->getName() === 'PRIVACY_POLICY_EDITOR') {
                $em->remove($setting);
                $em->flush();
            }
        }

        $tosTextEditorSetting = new Setting();
        $tosTextEditorSetting->setName('TOS_EDITOR');
        $tosTextEditorSetting->setValue($tosTextEditor->getContent());
        $privacyPolicyTextEditorSetting = new Setting();
        $privacyPolicyTextEditorSetting->setName('PRIVACY_POLICY_EDITOR');
        $privacyPolicyTextEditorSetting->setValue($privacyPolicyTextEditor->getContent());

        $settings = array_merge($settings, [$tosTextEditorSetting, $privacyPolicyTextEditorSetting]);

        $form = $this->createForm(TermsType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the submitted data
            $submittedData = $form->getData();

            // Update settings
            $tos = $submittedData['TOS'];
            $privacyPolicy = $submittedData['PRIVACY_POLICY'];
            $tosLink = $submittedData['TOS_LINK'] ?? null;
            $privacyPolicyLink = $submittedData['PRIVACY_POLICY_LINK'] ?? null;
            $tosTextEditor = $submittedData['TOS_EDITOR'] ?? '';
            $privacyPolicyTextEditor = $submittedData['PRIVACY_POLICY_EDITOR'] ?? '';


            $tosSetting = $settingsRepository->findOneBy(['name' => 'TOS']);
            if ($tosSetting) {
                $tosSetting->setValue($tos);
                $em->persist($tosSetting);
            }

            $privacyPolicySetting = $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY']);
            if ($privacyPolicySetting) {
                $privacyPolicySetting->setValue($privacyPolicy);
                $em->persist($privacyPolicySetting);
            }

            $tosLinkSetting = $settingsRepository->findOneBy(['name' => 'TOS_LINK']);
            if ($tosLinkSetting) {
                $tosLinkSetting->setValue($tosLink);
                $em->persist($tosLinkSetting);
            }

            $privacyPolicyLinkSetting = $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK']);
            if ($privacyPolicyLinkSetting) {
                $privacyPolicyLinkSetting->setValue($privacyPolicyLink);
                $em->persist($privacyPolicyLinkSetting);
            }
            $sanitizeHtml = new SanitizeHTML();
            if ($tosTextEditor) {
                $tosEditorSetting = $textEditorRepository->findOneBy(['name' => TextEditorName::TOS]);
                if ($tosEditorSetting) {
                    $cleanHTML = $sanitizeHtml->sanitizeHtml($tosTextEditor);
                    $tosEditorSetting->setContent($cleanHTML);
                }
                $em->persist($tosEditorSetting);
            }

            if ($privacyPolicyTextEditor) {
                $privacyPolicyEditorSetting = $textEditorRepository->findOneBy([
                    'name' => TextEditorName::PRIVACY_POLICY
                ]);
                if ($privacyPolicyEditorSetting) {
                    $cleanHTML = $sanitizeHtml->sanitizeHtml($privacyPolicyTextEditor);
                    $privacyPolicyEditorSetting->setContent($cleanHTML);
                }
                $em->persist($privacyPolicyEditorSetting);
            }
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_TERMS_REQUEST,
                new DateTime(),
                $eventMetadata
            );


            $em->flush();
            $this->addFlash('success_admin', 'Terms and Policies links changes have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_terms');
        }


        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/radius', name: 'admin_dashboard_settings_radius')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsRadius(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
        $data = $getSettings->getSettings($this->userRepository, $this->settingRepository);
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $domainService = new Domain();
        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(RadiusType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $staticValue = '887FAE2A-F051-4CC9-99BB-8DFD66F553A9';
            if ($submittedData['PAYLOAD_IDENTIFIER'] === $staticValue) {
                $this->addFlash('error_admin', 'Please do not use the default value from the Payload Identifier card.');
            } else {
                $settingsToUpdate = [
                    'RADIUS_REALM_NAME',
                    'DISPLAY_NAME',
                    'PAYLOAD_IDENTIFIER',
                    'OPERATOR_NAME',
                    'DOMAIN_NAME',
                    'RADIUS_TLS_NAME',
                    'NAI_REALM',
                    'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH',
                    'PROFILES_ENCRYPTION_TYPE_IOS_ONLY',
                ];

                foreach ($settingsToUpdate as $settingName) {
                    $value = $submittedData[$settingName] ?? null;

                    // Check for specific settings that need domain validation
                    if (
                        in_array(
                            $settingName,
                            [
                                'RADIUS_REALM_NAME',
                                'DOMAIN_NAME',
                                'RADIUS_TLS_NAME',
                                'NAI_REALM'
                            ]
                        ) && !$domainService->isValidDomain($value)
                    ) {
                        $this->addFlash(
                            'error_admin',
                            "The value for $settingName is not a valid domain or does not resolve to an IP address."
                        );
                        return $this->redirectToRoute('admin_dashboard_settings_radius');
                    }

                    $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                    if ($setting) {
                        $setting->setValue($value);
                        $em->persist($setting);
                    }
                }

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_RADIUS_CONF_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                $this->addFlash('success_admin', 'Radius configuration have been applied successfully.');
                return $this->redirectToRoute('admin_dashboard_settings_radius');
            }
        }

        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'form' => $form->createView()
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/status', name: 'admin_dashboard_settings_status')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsStatus(
        Request $request,
        EntityManagerInterface $em,
        GetSettings $getSettings
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(StatusType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the submitted data
            $submittedData = $form->getData();

            // Update the 'PLATFORM_MODE', 'USER_VERIFICATION' and 'TURNSTILE_CHECKER' settings
            $platformMode = $submittedData['PLATFORM_MODE'] ?? null;
            $turnstileChecker = $submittedData['TURNSTILE_CHECKER'] ?? null;
            $userDeleteTime = $submittedData['USER_DELETE_TIME'] ?? 5;
            // Update the 'USER_VERIFICATION', and, if the platform mode is Live, set email verification to ON always
            $emailVerification = ($platformMode === PlatformMode::LIVE) ?
                EmailConfirmationStrategy::EMAIL : $submittedData['USER_VERIFICATION'] ?? null;

            $platformModeSetting = $settingsRepository->findOneBy(['name' => 'PLATFORM_MODE']);
            if ($platformModeSetting) {
                $platformModeSetting->setValue($platformMode);
                $em->persist($platformModeSetting);
            }

            $emailVerificationSetting = $settingsRepository->findOneBy(['name' => 'USER_VERIFICATION']);
            if ($emailVerificationSetting) {
                $emailVerificationSetting->setValue($emailVerification);
                $em->persist($emailVerificationSetting);
            }

            $turnstileCheckerSetting = $settingsRepository->findOneBy(['name' => 'TURNSTILE_CHECKER']);
            if ($turnstileCheckerSetting) {
                $turnstileCheckerSetting->setValue($turnstileChecker);
                $em->persist($turnstileCheckerSetting);
            }
            $userDeleteTimeSetting = $settingsRepository->findOneBy(['name' => 'USER_DELETE_TIME']);
            if ($userDeleteTimeSetting) {
                $userDeleteTimeSetting->setValue($userDeleteTime);
                $em->persist($userDeleteTimeSetting);
            }
            // Flush the changes to the database
            $em->flush();

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid()
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_PLATFORM_STATUS_REQUEST,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash('success_admin', 'The new changes have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_status');
        }


        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/LDAP', name: 'admin_dashboard_settings_LDAP')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsLDAP(
        Request $request,
        EntityManagerInterface $em,
        GetSettings $getSettings
    ): Response {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(LDAPType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                'SYNC_LDAP_ENABLED',
                'SYNC_LDAP_SERVER',
                'SYNC_LDAP_BIND_USER_DN',
                'SYNC_LDAP_BIND_USER_PASSWORD',
                'SYNC_LDAP_SEARCH_BASE_DN',
                'SYNC_LDAP_SEARCH_FILTER',
            ];

            foreach ($settingsToUpdate as $settingName) {
                $value = $submittedData[$settingName] ?? null;

                // Check if any submitted data is empty
                if ($value === null) {
                    $value = "";
                }

                $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                if ($setting) {
                    $setting->setValue($value);
                    $em->persist($setting);
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_LDAP_CONF_REQUEST,
                new DateTime(),
                $eventMetadata
            );


            $this->addFlash('success_admin', 'New LDAP configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_LDAP');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'form' => $form->createView()
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/auth', name: 'admin_dashboard_settings_auth')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsAuths(
        Request $request,
        EntityManagerInterface $em,
        GetSettings $getSettings,
        CertificateService $certificateService
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $certificatePath = $this->getParameter('kernel.project_dir') . '/signing-keys/cert.pem';
        $certificateLimitDate = strtotime($certificateService->getCertificateExpirationDate($certificatePath));
        $realTime = time();
        $timeLeft = round(($certificateLimitDate - $realTime) / (60 * 60 * 24)) - 1;
        $profileLimitDate = ((int)$timeLeft);
        if ($profileLimitDate < 0) {
            $profileLimitDate = 0;
        }

        $defaultTimeZone = date_default_timezone_get();
        $dateTime = (new DateTime())
            ->setTimestamp($certificateLimitDate)
            ->setTimezone(new \DateTimeZone($defaultTimeZone));

        // Convert to human-readable format
        $humanReadableExpirationDate = $dateTime->format('Y-m-d H:i:s T');
        $form = $this->createForm(AuthType::class, null, [
            'settings' => $settings,
            'profileLimitDate' => $profileLimitDate,
            'humanReadableExpirationDate' => $humanReadableExpirationDate
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                'AUTH_METHOD_SAML_ENABLED',
                'AUTH_METHOD_SAML_LABEL',
                'AUTH_METHOD_SAML_DESCRIPTION',
                'PROFILE_LIMIT_DATE_SAML',

                'AUTH_METHOD_GOOGLE_LOGIN_ENABLED',
                'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
                'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION',
                'VALID_DOMAINS_GOOGLE_LOGIN',
                'PROFILE_LIMIT_DATE_GOOGLE',

                'AUTH_METHOD_REGISTER_ENABLED',
                'AUTH_METHOD_REGISTER_LABEL',
                'AUTH_METHOD_REGISTER_DESCRIPTION',
                'PROFILE_LIMIT_DATE_EMAIL',

                'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED',
                'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
                'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION',

                'AUTH_METHOD_SMS_REGISTER_ENABLED',
                'AUTH_METHOD_SMS_REGISTER_LABEL',
                'AUTH_METHOD_SMS_REGISTER_DESCRIPTION',
                'PROFILE_LIMIT_DATE_SMS',
            ];

            $labelsFields = [
                'AUTH_METHOD_SAML_LABEL',
                'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
                'AUTH_METHOD_REGISTER_LABEL',
                'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
                'AUTH_METHOD_SMS_REGISTER_LABEL',
            ];

            foreach ($settingsToUpdate as $settingName) {
                $value = $submittedData[$settingName] ?? null;

                // Check if the setting is a label, to be impossible to set it null of empty
                if (in_array($settingName, $labelsFields)) {
                    if ($value === null || $value === "") {
                        continue;
                    }
                }

                $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                if ($settingName === 'VALID_DOMAINS_GOOGLE_LOGIN') {
                    if ($setting) {
                        $setting->setValue($value);
                        $em->persist($setting);
                    }
                    continue;
                }

                if ($setting) {
                    $setting->setValue($value);
                    $em->persist($setting);
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_AUTHS_CONF_REQUEST,
                new DateTime(),
                $eventMetadata
            );
            $this->addFlash('success_admin', 'New authentication configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_auth');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
            'profileLimitDate' => $profileLimitDate,
            'humanReadableExpirationDate' => $humanReadableExpirationDate
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/capport', name: 'admin_dashboard_settings_capport')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCAPPORT(
        Request $request,
        EntityManagerInterface $em,
        GetSettings $getSettings
    ): Response {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(CapportType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                'CAPPORT_ENABLED',
                'CAPPORT_PORTAL_URL',
                'CAPPORT_VENUE_INFO_URL',
            ];

            foreach ($settingsToUpdate as $settingName) {
                $value = $submittedData[$settingName] ?? null;

                // Check if any submitted data is empty
                if ($value === null) {
                    $value = "";
                }

                $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                if ($setting) {
                    $setting->setValue($value);
                    $em->persist($setting);
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_CAPPORT_CONF_REQUEST,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash('success_admin', 'New CAPPORT configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_capport');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'form' => $form->createView()
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/sms', name: 'admin_dashboard_settings_sms')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsSMS(
        Request $request,
        EntityManagerInterface $em,
        GetSettings $getSettings
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(SMSType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                'SMS_USERNAME',
                'SMS_USER_ID',
                'SMS_HANDLE',
                'SMS_FROM',
                'SMS_TIMER_RESEND',
                'DEFAULT_REGION_PHONE_INPUTS'
            ];

            foreach ($settingsToUpdate as $settingName) {
                $value = $submittedData[$settingName] ?? null;

                // Check if any submitted data is empty
                if ($value === null) {
                    $value = "";
                }

                $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                if ($setting) {
                    $setting->setValue($value);
                    $em->persist($setting);
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_SMS_CONF_REQUEST,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash('success_admin', 'New SMS configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_sms');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Render Statistics  about the Portal data
     */
    /**
     * @param Request $request
     * @return Response
     * @throws \JsonException
     * @throws Exception
     */
    #[Route('/dashboard/statistics', name: 'admin_dashboard_statistics')]
    #[IsGranted('ROLE_ADMIN')]
    public function statisticsData(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Get the submitted start and end dates from the form
        $startDateString = $request->request->get('startDate');
        $endDateString = $request->request->get('endDate');

        // Convert the date strings to DateTime objects
        if ($startDateString) {
            $startDate = new DateTime($startDateString);
        } else {
            $startDate = (new DateTime())->modify(
                '-1 week'
            );
        }

        if ($endDateString) {
            $endDate = new DateTime($endDateString);
        } else {
            $endDate = new DateTime();
        }

        $interval = $startDate->diff($endDate);

        if ($interval->days > 366) {
            $this->addFlash('error_admin', 'Maximum date range is 1 year');
            return $this->redirectToRoute('admin_dashboard_statistics');
        }

        $statisticsService = new Statistics($this->entityManager);
        $fetchChartDevices = $statisticsService->fetchChartDevices($startDate, $endDate);
        $fetchChartAuthentication = $statisticsService->fetchChartAuthentication($startDate, $endDate);
        $fetchChartPlatformStatus = $statisticsService->fetchChartPlatformStatus($startDate, $endDate);
        $fetchChartUserVerified = $statisticsService->fetchChartUserVerified($startDate, $endDate);
        $fetchChartSMSEmail = $statisticsService->fetchChartSMSEmail($startDate, $endDate);

        $memory_before = memory_get_usage();
        $memory_after = memory_get_usage();
        $memory_diff = $memory_after - $memory_before;

        // Check that the memory usage does not exceed the PHP memory limit of 128M
        if ($memory_diff > 128 * 1024 * 1024) {
            $this->addFlash(
                'error_admin',
                'The data you requested is too large to be processed. Please try a smaller date range.'
            );
            return $this->redirectToRoute('admin_dashboard_statistics');
        }

        return $this->render('admin/statistics.html.twig', [
            'data' => $data,
            'devicesDataJson' => json_encode($fetchChartDevices, JSON_THROW_ON_ERROR),
            'authenticationDataJson' => json_encode($fetchChartAuthentication, JSON_THROW_ON_ERROR),
            'platformStatusDataJson' => json_encode($fetchChartPlatformStatus, JSON_THROW_ON_ERROR),
            'usersVerifiedDataJson' => json_encode($fetchChartUserVerified, JSON_THROW_ON_ERROR),
            'SMSEmailDataJson' => json_encode($fetchChartSMSEmail, JSON_THROW_ON_ERROR),
            'selectedStartDate' => $startDate->format('Y-m-d\TH:i'),
            'selectedEndDate' => $endDate->format('Y-m-d\TH:i'),
        ]);
    }

    /**
     * Render Statistics about the freeradius data
     */
    /**
     * @param Request $request
     * @param int $page
     * @return Response
     * @throws \JsonException
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    #[Route('/dashboard/statistics/freeradius', name: 'admin_dashboard_statistics_freeradius')]
    #[IsGranted('ROLE_ADMIN')]
    public function freeradiusStatisticsData(
        Request $request,
        #[MapQueryParameter] int $page = 1,
    ): Response {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $user = $this->getUser();
        $export_freeradius_statistics = $this->parameterBag->get('app.export_freeradius_statistics');

        // Get the submitted start and end dates from the form
        $startDateString = $request->request->get('startDate');
        $endDateString = $request->request->get('endDate');

        // Convert the date strings to DateTime objects
        if ($startDateString) {
            $startDate = new DateTime($startDateString);
        } else {
            $startDate = (new DateTime())->modify(
                '-1 week'
            );
        }

        if ($endDateString) {
            $endDate = new DateTime($endDateString);
        } else {
            $endDate = new DateTime();
        }

        $interval = $startDate->diff($endDate);
        if ($interval->y > 1) {
            $this->addFlash('error_admin', 'Maximum date range is 1 year');
            return $this->redirectToRoute('admin_dashboard_statistics_freeradius');
        }

        // Fetch all the required data, graphics etc...
        $statisticsService = new Statistics($this->entityManager);
        $fetchChartAuthenticationsFreeradius = $statisticsService->fetchChartAuthenticationsFreeradius($startDate, $endDate);
        $fetchChartRealmsFreeradius = $statisticsService->fetchChartRealmsFreeradius($startDate, $endDate);
        $fetchChartCurrentAuthFreeradius = $statisticsService->fetchChartCurrentAuthFreeradius();
        $fetchChartTrafficFreeradius = $statisticsService->fetchChartTrafficFreeradius($startDate, $endDate);
        $fetchChartSessionAverageFreeradius = $statisticsService->fetchChartSessionAverageFreeradius($startDate, $endDate);
        $fetchChartSessionTotalFreeradius = $statisticsService->fetchChartSessionTotalFreeradius($startDate, $endDate);
        $fetchChartWifiTags = $statisticsService->fetchChartWifiVersion($startDate, $endDate);
        $fetchChartApUsage = $statisticsService->fetchChartApUsage($startDate, $endDate);

        $memory_before = memory_get_usage();
        $memory_after = memory_get_usage();
        $memory_diff = $memory_after - $memory_before;

        // Check that the memory usage does not exceed the PHP memory limit of 128M
        if ($memory_diff > 128 * 1024 * 1024) {
            $this->addFlash(
                'error_admin',
                'The data you requested is too large to be processed. Please try a smaller date range.'
            );
            return $this->redirectToRoute('admin_dashboard_statistics_freeradius');
        }

        // Extract the connection attempts
        $authCounts = [
            'Accepted' => array_sum($fetchChartAuthenticationsFreeradius['datasets'][0]['data']),
            'Rejected' => array_sum($fetchChartAuthenticationsFreeradius['datasets'][1]['data']),
        ];

        // Extract all realms names and usage
        $realmsUsage = [];
        foreach ($fetchChartRealmsFreeradius as $content) {
            $realm = $content['realm'];
            $count = $content['count'];

            if (isset($realmsUsage[$realm])) {
                $realmsUsage[$realm] += $count;
            } else {
                $realmsUsage[$realm] = $count;
            }
        }

        // Sum all the current authentication
        $totalCurrentAuths = 0;
        foreach ($fetchChartCurrentAuthFreeradius['datasets'] as $dataset) {
            // Sum the data points in the current dataset
            $totalCurrentAuths = array_sum($dataset['data']) + $totalCurrentAuths;
        }

        // Sum all the traffic from the Accounting table
        $totalTraffic = [
            'total_input' => 0,
            'total_output' => 0,
        ];
        foreach ($fetchChartTrafficFreeradius as $content) {
            $totalTraffic['total_input'] += $content['total_input'];
            $totalTraffic['total_output'] += $content['total_output'];
        }
        $totalTraffic['total_input'] = number_format($totalTraffic['total_input'] / (1024 * 1024 * 1024), 1);
        $totalTraffic['total_output'] = number_format($totalTraffic['total_output'] / (1024 * 1024 * 1024), 1);

        // Extract the average time
        $averageTimes = $fetchChartSessionAverageFreeradius['datasets'][0]['data'];
        $totalAverageTimeSeconds = array_sum($averageTimes);

        // Convert the total average time to human-readable format
        $totalAverageTimeReadable = sprintf(
            '%dh %dm',
            floor($totalAverageTimeSeconds / 3600),
            floor(($totalAverageTimeSeconds % 3600) / 60)
        );

        // Extract the total time
        $totalTimes = $fetchChartSessionTotalFreeradius['datasets'][0]['data'];
        $totalTimeSeconds = array_sum($totalTimes);

        // Convert the total time to human-readable format
        $totalTimeReadable = sprintf(
            '%dh %dm',
            floor($totalTimeSeconds / 3600),
            floor(($totalTimeSeconds % 3600) / 60)
        );

        $perPage = 3;
        $totalApCount = count($fetchChartApUsage);
        $totalPages = ceil($totalApCount / $perPage);
        $offset = ($page - 1) * $perPage;
        $fetchChartApUsage = array_slice($fetchChartApUsage, $offset, $perPage);

        return $this->render('admin/freeradius_statistics.html.twig', [
            'data' => $data,
            'current_user' => $user,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'totalApCount' => $totalApCount,
            'realmsUsage' => $realmsUsage,
            'authCounts' => $authCounts,
            'totalCurrentAuths' => $totalCurrentAuths,
            'totalTrafficFreeradius' => $totalTraffic,
            'sessionTimeAverage' => $totalAverageTimeReadable,
            'totalTime' => $totalTimeReadable,
            'authAttemptsJson' => json_encode($fetchChartAuthenticationsFreeradius, JSON_THROW_ON_ERROR),
            'sessionTimeJson' => json_encode($fetchChartSessionAverageFreeradius, JSON_THROW_ON_ERROR),
            'totalTimeJson' => json_encode($fetchChartSessionTotalFreeradius, JSON_THROW_ON_ERROR),
            'wifiTagsJson' => json_encode($fetchChartWifiTags, JSON_THROW_ON_ERROR),
            'ApUsage' => $fetchChartApUsage,
            'selectedStartDate' => $startDate->format('Y-m-d\TH:i'),
            'selectedEndDate' => $endDate->format('Y-m-d\TH:i'),
            'exportFreeradiusStatistics' => $export_freeradius_statistics,
            'paginationApUsage' => true
        ]);
    }


    /**
     * Exports the freeradius data
     * @throws Exception
     */
    #[Route('/dashboard/export/freeradius', name: 'admin_page_export_freeradius')]
    #[IsGranted('ROLE_ADMIN')]
    public function exportFreeradius(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Get the submitted start and end dates from the form
        $startDateString = $request->query->get('startDate');
        $endDateString = $request->query->get('endDate');

        // Convert the date strings to DateTime objects
        $startDate = $startDateString ? new DateTime($startDateString) : (new DateTime())->modify('-1 week');
        $endDate = $endDateString ? new DateTime($endDateString) : new DateTime();

        // Fetch the authentication data
        $statisticsService = new Statistics($this->entityManager);
        $fetchChartAuthenticationsFreeradius = $statisticsService->fetchChartAuthenticationsFreeradius($startDate, $endDate);
        $fetchChartSessionAverageFreeradius = $statisticsService->fetchChartSessionAverageFreeradius($startDate, $endDate);
        $fetchChartSessionTotalFreeradius = $statisticsService->fetchChartSessionTotalFreeradius($startDate, $endDate);
        $fetchChartTrafficFreeradius = $statisticsService->fetchChartTrafficFreeradius($startDate, $endDate);
        $fetchChartRealmsFreeradius = $statisticsService->fetchChartRealmsFreeradius($startDate, $endDate);
        $fetchChartApUsage = $statisticsService->fetchChartApUsage($startDate, $endDate);
        $fetchChartWifiTags = $statisticsService->fetchChartWifiVersion($startDate, $endDate);

        // Prepare the authentication data for Excel
        $authData = [];
        foreach ($fetchChartAuthenticationsFreeradius['labels'] as $index => $auth_date) {
            $accepted = $fetchChartAuthenticationsFreeradius['datasets'][0]['data'][$index] ?? 0;
            $rejected = $fetchChartAuthenticationsFreeradius['datasets'][1]['data'][$index] ?? 0;

            $authData[] = [
                'auth_date' => $auth_date,
                'Accepted' => $accepted,
                'Rejected' => $rejected,
            ];
        }

        // Prepare the session average data for Excel
        $sessionData = [];
        foreach ($fetchChartSessionAverageFreeradius['labels'] as $index => $session_date) {
            $sessionAverage = $fetchChartSessionAverageFreeradius['datasets'][0]['tooltips'][$index] ?? 0;
            $sessionData[] = [
                'session_date' => $session_date,
                'average_time' => $sessionAverage,
            ];
        }

        // Prepare the session total data for Excel
        $totalTimeData = [];
        foreach ($fetchChartSessionTotalFreeradius['labels'] as $index => $session_date) {
            $sessionTotal = $fetchChartSessionTotalFreeradius['datasets'][0]['tooltips'][$index] ?? 0;
            $totalTimeData[] = [
                'session_date' => $session_date,
                'total_time' => $sessionTotal,
            ];
        }

        // Prepare the total traffic data for Excel
        $trafficData = [];
        foreach ($fetchChartTrafficFreeradius as $session_date) {
            $realm = $fetchChartTrafficFreeradius[0]['realm'] ?? 0;
            $totalInput = $fetchChartTrafficFreeradius[0]['total_input'] ?? 0;
            $totalOutput = $fetchChartTrafficFreeradius[0]['total_output'] ?? 0;

            $trafficData[] = [
                'realm' => $realm,
                'total_input_flat' => $totalInput,
                'total_input' => number_format($totalInput / (1024 * 1024 * 1024), 1),
                'total_output_flat' => $totalOutput,
                'total_output' => number_format($totalOutput / (1024 * 1024 * 1024), 1)
            ];
        }

        // Prepare the realm Usage data for Excel
        $realmUsageData = [];
        foreach ($fetchChartRealmsFreeradius as $session_date) {
            $realm = $fetchChartRealmsFreeradius[0]['realm'] ?? 0;
            $totalCount = $fetchChartRealmsFreeradius[0]['count'] ?? 0;

            $realmUsageData[] = [
                'realm' => $realm,
                'total_count' => $totalCount,
            ];
        }

        // Prepare the realm Usage data for Excel
        $realmUsageData = [];
        foreach ($fetchChartRealmsFreeradius as $session_date) {
            $realm = $fetchChartRealmsFreeradius[0]['realm'] ?? 0;
            $totalCount = $fetchChartRealmsFreeradius[0]['count'] ?? 0;

            $realmUsageData[] = [
                'realm' => $realm,
                'total_count' => $totalCount,
            ];
        }

        // Prepare the AP Usage data for Excel
        $apUsageData = [];
        foreach ($fetchChartApUsage as $index => $session_date) {
            $apName = $fetchChartApUsage[$index]['ap'] ?? 0;
            $apUsage = $fetchChartApUsage[$index]['count'] ?? 0;
            $apUsageData[] = [
                'ap_Name' => $apName,
                'ap_Usage' => $apUsage,
            ];
        }

        // Prepare the Wi-Fi Standards Usage data for Excel
        $wifiStandardsData = [];
        foreach ($fetchChartWifiTags['labels'] as $index => $wifi_Standards) {
            $wifiUsage = $fetchChartWifiTags['datasets'][0]['data'][$index] ?? 0;
            $wifiStandardsData[] = [
                'wifi_Standards' => $wifi_Standards,
                'wifi_Usage' => $wifiUsage,
            ];
        }

        // Create a new Spreadsheet object
        $spreadsheet = new Spreadsheet();

        // Fill the first sheet with authentication data
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Authentications');
        $sheet1->setCellValue('A1', 'Date')
            ->setCellValue('B1', 'Accepted')
            ->setCellValue('C1', 'Rejected');

        $row = 2;
        foreach ($authData as $data) {
            $sheet1->setCellValue('A' . $row, $this->escapeSpreadsheetValue($data['auth_date']))
                ->setCellValue('B' . $row, $this->escapeSpreadsheetValue($data['Accepted']))
                ->setCellValue('C' . $row, $this->escapeSpreadsheetValue($data['Rejected']));
            $row++;
        }

        $sheet1->getColumnDimension('A')->setWidth(20);
        $sheet1->getColumnDimension('B')->setWidth(15);
        $sheet1->getColumnDimension('C')->setWidth(15);

        // Create a new sheet for Average session data
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Session Average');
        $sheet2->setCellValue('A1', 'Date')
            ->setCellValue('B1', 'Average Session Time');

        $row = 2;
        foreach ($sessionData as $data) {
            $sheet2->setCellValue('A' . $row, $this->escapeSpreadsheetValue($data['session_date']))
                ->setCellValue('B' . $row, $this->escapeSpreadsheetValue($data['average_time']));
            $row++;
        }

        $sheet2->getColumnDimension('A')->setWidth(20);
        $sheet2->getColumnDimension('B')->setWidth(15);

        // Create a new sheet for Total session data
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Session Total');
        $sheet3->setCellValue('A1', 'Date')
            ->setCellValue('B1', 'Total Session Time');

        $row = 2;
        foreach ($totalTimeData as $data) {
            $sheet3->setCellValue('A' . $row, $this->escapeSpreadsheetValue($data['session_date']))
                ->setCellValue('B' . $row, $this->escapeSpreadsheetValue($data['total_time']));
            $row++;
        }

        $sheet3->getColumnDimension('A')->setWidth(20);
        $sheet3->getColumnDimension('B')->setWidth(15);

        // Create a new sheet for Total Traffic data
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Total of Traffic');
        $sheet4->setCellValue('A1', 'Realm Name')
            ->setCellValue('B1', 'Uploads Flat')
            ->setCellValue('C1', 'Uploads')
            ->setCellValue('D1', 'Downloads Flat')
            ->setCellValue('E1', 'Downloads');

        $row = 2;
        foreach ($trafficData as $data) {
            $sheet4->setCellValue('A' . $row, $this->escapeSpreadsheetValue($data['realm']))
                ->setCellValue('B' . $row, $this->escapeSpreadsheetValue($data['total_input_flat']))
                ->setCellValue('C' . $row, $this->escapeSpreadsheetValue($data['total_input']))
                ->setCellValue('D' . $row, $this->escapeSpreadsheetValue($data['total_output_flat']))
                ->setCellValue('E' . $row, $this->escapeSpreadsheetValue($data['total_output']));
            $row++;
        }

        $sheet4->getColumnDimension('A')->setWidth(20);
        $sheet4->getColumnDimension('B')->setWidth(20);
        $sheet4->getColumnDimension('C')->setWidth(20);
        $sheet4->getColumnDimension('D')->setWidth(20);
        $sheet4->getColumnDimension('E')->setWidth(20);

        // Create a new sheet for Realm Usage data
        $sheet5 = $spreadsheet->createSheet();
        $sheet5->setTitle('Realm Usage');
        $sheet5->setCellValue('A1', 'Realm Name')
            ->setCellValue('B1', 'Usage');

        $row = 2;
        foreach ($realmUsageData as $data) {
            $sheet5->setCellValue('A' . $row, $this->escapeSpreadsheetValue($data['realm']))
                ->setCellValue('B' . $row, $this->escapeSpreadsheetValue($data['total_count']));
            $row++;
        }

        $sheet5->getColumnDimension('A')->setWidth(20);
        $sheet5->getColumnDimension('B')->setWidth(20);

        // Create a new sheet for Access Points Usage data
        $sheet6 = $spreadsheet->createSheet();
        $sheet6->setTitle('Access Points Usage');
        $sheet6->setCellValue('A1', 'MAC ADDRESS:SSID')
            ->setCellValue('B1', 'Usage');

        $row = 2;
        foreach ($apUsageData as $data) {
            $sheet6->setCellValue('A' . $row, $this->escapeSpreadsheetValue($data['ap_Name']))
                ->setCellValue('B' . $row, $this->escapeSpreadsheetValue($data['ap_Usage']));
            $row++;
        }

        $sheet6->getColumnDimension('A')->setWidth(40);
        $sheet6->getColumnDimension('B')->setWidth(20);

        // Create a new sheet for Wifi Standards Usage data
        $sheet7 = $spreadsheet->createSheet();
        $sheet7->setTitle('Wifi Standards Usage');
        $sheet7->setCellValue('A1', 'Wifi Standard Name')
            ->setCellValue('B1', 'Usage');

        $row = 2;
        foreach ($wifiStandardsData as $data) {
            $sheet7->setCellValue('A' . $row, $this->escapeSpreadsheetValue($data['wifi_Standards']))
                ->setCellValue('B' . $row, $this->escapeSpreadsheetValue($data['wifi_Usage']));
            $row++;
        }

        $sheet7->getColumnDimension('A')->setWidth(20);
        $sheet7->getColumnDimension('B')->setWidth(20);

        // Save the spreadsheet to a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'freeradius_statistics') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'uuid' => $currentUser->getUuid(),
        ];
        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::EXPORT_FREERADIUS_STATISTICS_REQUEST,
            new DateTime(),
            $eventMetadata
        );


        return $this->file($tempFile, 'freeradiusStatistics.xlsx');
    }


    /**
     * Handles the Page Style on the dashboard
     */
    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/customize', name: 'admin_dashboard_customize')]
    #[IsGranted('ROLE_ADMIN')]
    public function customize(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        // Create the form with the CustomType and pass the relevant settings
        $form = $this->createForm(CustomType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle submitted data and update the settings accordingly
            $submittedData = $form->getData();

            // Update the settings based on the form submission
            foreach ($settings as $setting) {
                $settingName = $setting->getName();

                // Check if the setting is in the allowed settings for customization
                if (
                    in_array(
                        $settingName,
                        [
                            'WELCOME_TEXT',
                            'PAGE_TITLE',
                            'WELCOME_DESCRIPTION',
                            'ADDITIONAL_LABEL',
                            'CONTACT_EMAIL',
                            'CUSTOMER_LOGO_ENABLED'
                        ]
                    )
                ) {
                    // Get the value from the submitted form data
                    $submittedValue = $submittedData[$settingName];

                    // Update the setting value
                    $setting->setValue($submittedValue);
                } elseif (in_array($settingName, ['CUSTOMER_LOGO', 'OPENROAMING_LOGO', 'WALLPAPER_IMAGE'])) {
                    // Handle file uploads for logos and wallpaper image
                    $file = $form->get($settingName)->getData();

                    if ($file) { // submits the new file to the respective path
                        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        // Use a unique id for the uploaded file to avoid overwriting
                        $newFilename = $originalFilename . '-' . uniqid() . '.' . $file->guessExtension();

                        // Set the destination directory based on the setting name
                        $destinationDirectory = $this->getParameter('kernel.project_dir')
                            . '/public/resources/uploaded/';

                        $file->move($destinationDirectory, $newFilename);
                        $setting->setValue('/resources/uploaded/' . $newFilename);
                    }
                    // PLS MAKE SURE TO USE THIS COMMAND ON THE WEB CONTAINER
                    // chown -R www-data:www-data /var/www/openroaming/public/resources/uploaded/
                }
            }

            $this->addFlash('success_admin', 'Customization settings have been updated successfully.');

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_PAGE_STYLE_REQUEST,
                new DateTime(),
                $eventMetadata
            );

            return $this->redirectToRoute('admin_dashboard_customize');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'settings' => $settings,
            'form' => $form->createView(),
            'data' => $data,
            'getSettings' => $getSettings
        ]);
    }


    /**
     * Escape a value to prevent spreadsheet injection for the export routes (EXPORT USERS || FREERADIUS)
     * @param mixed $value
     * @return string
     */
    private function escapeSpreadsheetValue(mixed $value): string
    {
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        $escapedValue = (string)$value;

        // Remove specific characters
        $charactersToRemove = ['=', '(', ')'];
        return str_replace($charactersToRemove, '', $escapedValue);
    }

    /**
     * @param $user
     * @return void
     */
    private function disableProfiles($user): void
    {
        $this->profileManager->disableProfiles($user);
    }

    /**
     * @param $user
     * @return void
     */
    private function enableProfiles($user): void
    {
        $this->profileManager->enableProfiles($user);
    }
}
