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
