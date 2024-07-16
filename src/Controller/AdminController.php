<?php

namespace App\Controller;

use App\Entity\DeletedUserData;
use App\Entity\Event;
use App\Entity\Setting;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\PlatformMode;
use App\Enum\User_Verification_Status;
use App\Enum\UserProvider;
use App\Form\AuthType;
use App\Form\CapportType;
use App\Form\CustomType;
use App\Form\LDAPType;
use App\Form\RadiusType;
use App\Form\ResetPasswordType;
use App\Form\SMSType;
use App\Form\StatusType;
use App\Form\TermsType;
use App\Form\UserUpdateType;
use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\RadiusDb\Repository\RadiusAuthsRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\PgpEncryptionService;
use App\Service\ProfileManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    private ProfileManager $profileManager;
    private ParameterBagInterface $parameterBag;
    private GetSettings $getSettings;
    private SettingRepository $settingRepository;
    private EntityManagerInterface $entityManager;
    private RadiusAuthsRepository $radiusAuthsRepository;
    private RadiusAccountingRepository $radiusAccountingRepository;
    private PgpEncryptionService $pgpEncryptionService;
    private EventActions $eventActions;

    /**
     * @param MailerInterface $mailer
     * @param UserRepository $userRepository
     * @param ProfileManager $profileManager
     * @param ParameterBagInterface $parameterBag
     * @param GetSettings $getSettings
     * @param SettingRepository $settingRepository
     * @param EntityManagerInterface $entityManager
     * @param RadiusAuthsRepository $radiusAuthsRepository
     * @param RadiusAccountingRepository $radiusAccountingRepository
     * @param PgpEncryptionService $pgpEncryptionService
     * @param EventActions $eventActions
     */
    public function __construct(
        MailerInterface $mailer,
        UserRepository $userRepository,
        ProfileManager $profileManager,
        ParameterBagInterface $parameterBag,
        GetSettings $getSettings,
        SettingRepository $settingRepository,
        EntityManagerInterface $entityManager,
        RadiusAuthsRepository $radiusAuthsRepository,
        RadiusAccountingRepository $radiusAccountingRepository,
        PgpEncryptionService $pgpEncryptionService,
        EventActions $eventActions
    ) {
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
        $this->profileManager = $profileManager;
        $this->parameterBag = $parameterBag;
        $this->getSettings = $getSettings;
        $this->settingRepository = $settingRepository;
        $this->entityManager = $entityManager;
        $this->radiusAuthsRepository = $radiusAuthsRepository;
        $this->radiusAccountingRepository = $radiusAccountingRepository;
        $this->pgpEncryptionService = $pgpEncryptionService;
        $this->eventActions = $eventActions;
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
     * @param int $count
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
        #[MapQueryParameter] int $count = 7
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $searchTerm = $request->query->get('u');
        $perPage = $count;

        $filter = $request->query->get('filter', 'all'); // Default filter

        // Use the updated searchWithFilter method to handle both filter and search term
        $users = $userRepository->searchWithFilter($filter, $searchTerm);

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
        $allUsersCount = $userRepository->countAllUsersExcludingAdmin();
        $verifiedUsersCount = $userRepository->countVerifiedUsers();
        $bannedUsersCount = $userRepository->totalBannedUsers();

        // Check if the export users operation is enabled
        $exportUsers = $this->parameterBag->get('app.export_users');
        // Check if the delete action has a public PGP key defined
        $deleteUsers = $this->parameterBag->get('app.pgp_public_key');

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'searchTerm' => null,
            'data' => $data,
            'allUsersCount' => $allUsersCount,
            'verifiedUsersCount' => $verifiedUsersCount,
            'bannedUsersCount' => $bannedUsersCount,
            'activeFilter' => $filter,
            'activeSort' => $sort,
            'activeOrder' => $order,
            'count' => $count,
            'export_users' => $exportUsers,
            'delete_users' => $deleteUsers
        ]);
    }

    /*
    * Handle export of the Users Table on the Main Route
    */
    /**
     * @param UserRepository $userRepository
     * @return Response
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/dashboard/export/users', name: 'admin_page_export_users')]
    #[IsGranted('ROLE_ADMIN')]
    public function exportUsers(UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
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
        $users = $userRepository->findExcludingAdmin();

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
        $sheet->setCellValue('I1', 'Banned At');
        $sheet->setCellValue('J1', 'Created At');

        // Apply the data
        $row = 2;
        foreach ($users as $user) {
            $sheet->setCellValue('A' . $row, $this->escapeSpreadsheetValue($user->getId()));
            $sheet->setCellValue('B' . $row, $this->escapeSpreadsheetValue($user->getUuid()));
            $sheet->setCellValue('C' . $row, $this->escapeSpreadsheetValue($user->getEmail()));
            $sheet->setCellValue('D' . $row, $this->escapeSpreadsheetValue($user->getPhoneNumber()));
            $sheet->setCellValue('E' . $row, $this->escapeSpreadsheetValue($user->getFirstName()));
            $sheet->setCellValue('F' . $row, $this->escapeSpreadsheetValue($user->getLastName()));
            $sheet->setCellValue(
                'G' . $row,
                $this->escapeSpreadsheetValue($user->isVerified() ? 'Verified' : 'Not Verified')
            );
            // Determine User Provider
            $userProvider = $this->getUserProvider($user);
            $sheet->setCellValue('H' . $row, $this->escapeSpreadsheetValue($userProvider));
            // Check if the user is Banned
            $sheet->setCellValue(
                'I' . $row,
                $this->escapeSpreadsheetValue(
                    $user->getBannedAt() !== null ? $user->getBannedAt()->format('Y-m-d H:i:s') : 'Not Banned'
                )
            );
            $sheet->setCellValue('J' . $row, $this->escapeSpreadsheetValue($user->getCreatedAt()));

            $row++;
        }

        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'users');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $eventMetadata = [
            'ip' => $_SERVER['REMOTE_ADDR'],
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

    // Determine user provider
    public function getUserProvider(User $user): string
    {
        if ($user->getGoogleId() !== null) {
            return UserProvider::GOOGLE_ACCOUNT;
        }

        if ($user->getSamlIdentifier() !== null) {
            return UserProvider::SAML;
        }

        return UserProvider::PORTAL_ACCOUNT;
    }

    /*
     * Deletes Users from the Project, this only adds a deletedAt date for legal reasons
     */
    /**
     * @param $id
     * @param EntityManagerInterface $em
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @return Response
     */
    #[Route('/dashboard/delete/{id<\d+>}', name: 'admin_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteUsers(
        $id,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $userPasswordHasher,
    ): Response {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        if ($user->getDeletedAt() !== null) {
            $this->addFlash('error_admin', 'This user has already been deleted.');
            return $this->redirectToRoute('admin_page');
        }

        $getUUID = $user->getUuid();

        $deletedUserData = [
            'uuid' => $user->getUuid(),
            'email' => $user->getEmail() ?? 'This value is empty',
            'phoneNumber' => $user->getPhoneNumber() ?? 'This value is empty',
            'samlIdentifier' => $user->getSamlIdentifier() ?? 'This value is empty',
            'googleId' => $user->getGoogleId() ?? 'This value is empty',
            'fisrtName' => $user->getFirstName() ?? 'This value is empty',
            'lastName' => $user->getLastName() ?? 'This value is empty',
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'bannedAt' => $user->getBannedAt() ? $user->getBannedAt()->format('Y-m-d H:i:s') : null,
            'deletedAt' => new DateTime(),
        ];

        $jsonData = json_encode($deletedUserData);

        // Encrypt JSON data using PGP encryption
        $pgpEncryptedService = new PgpEncryptionService();
        $pgpEncryptedData = $this->pgpEncryptionService->encrypt($jsonData);
        if ($pgpEncryptedData[0] == User_Verification_Status::MISSING_PUBLIC_KEY_CONTENT) {
            $this->addFlash(
                'error_admin',
                'The public key is not set.
             Make sure to define a public key in pgp_public_key/public_key.asc'
            );
            return $this->redirectToRoute('admin_page');
        } else {
            if ($pgpEncryptedData[0] == User_Verification_Status::EMPTY_PUBLIC_KEY_CONTENT) {
                $this->addFlash(
                    'error_admin',
                    'The public key is empty.
             Make sure to define content for the public key in pgp_public_key/public_key.asc'
                );
                return $this->redirectToRoute('admin_page');
            }
        }
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
            'isIP' => $_SERVER['REMOTE_ADDR'],
        ]);

        $user->setUuid($user->getId());
        $user->setEmail('');
        $user->setPhoneNumber('');
        $user->setPassword($user->getId());
        $user->setSamlIdentifier(null);
        $user->setFirstName(null);
        $user->setLastName(null);
        $user->setGoogleId(null);
        $user->setBannedAt(null);
        $user->setDeletedAt(new DateTime());

        $this->disableProfiles($user);
        $em->persist($deletedUserData);
        $em->persist($user);
        $em->flush();

        $eventMetadata = [
            'uuid' => $getUUID,
            'deletedBy' => $currentUser->getUuid(),
            'ip' => $_SERVER['REMOTE_ADDR'],
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

            // Verifies if the bannedAt was submitted and compares the form value "banned" to the current value on the db
            if ($form->get('bannedAt')->getData() && $user->getBannedAt() !== $initialBannedAtValue) {
                // Check if the admin is trying to ban himself
                if ($currentUser->getId() === $user->getId()) {
                    $this->addFlash('error_admin', 'Sorry, administrators cannot ban themselves.');
                    return $this->redirectToRoute('admin_update', ['id' => $user->getId()]);
                }
                $user->setBannedAt(new DateTime());
                $this->disableProfiles($user);
            } else {
                $user->setBannedAt(null);
                $this->enableProfiles($user);
            }
            $userRepository->save($user, true);

            $eventMetadata = [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'edited' => $user->getUuid(),
                'by' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_FROM_UI,
                new DateTime(),
                $eventMetadata
            );

            $email = $user->getEmail();
            $this->addFlash('success_admin', sprintf('"%s" has been updated successfully.', $email));

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

            // Hash the new password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $em->flush();

            // Send email to the user with the new password
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
            $this->addFlash('success_admin', sprintf('"%s" has is password updated.', $user->getEmail()));

            $eventMetadata = [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'edited ' => $user->getUuid(),
                'by' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI,
                new DateTime(),
                $eventMetadata
            );

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
                    'ip' => $_SERVER['REMOTE_ADDR'],
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
                    'ip' => $_SERVER['REMOTE_ADDR'],
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
                    'ip' => $_SERVER['REMOTE_ADDR'],
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
                    'isIP' => $_SERVER['REMOTE_ADDR'],
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
                    'ip' => $_SERVER['REMOTE_ADDR'],
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
                    'ip' => $_SERVER['REMOTE_ADDR'],
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
                    'ip' => $_SERVER['REMOTE_ADDR'],
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
                    'ip' => $_SERVER['REMOTE_ADDR'],
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
        $verificationCode = $this->generateVerificationCode($currentUser);

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
    public function settings_terms(
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

        $form = $this->createForm(TermsType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the submitted data
            $submittedData = $form->getData();

            // Update the 'TOS_LINK' and 'PRIVACY_POLICY_LINK' settings
            $tosLink = $submittedData['TOS_LINK'] ?? null;
            $privacyPolicyLink = $submittedData['PRIVACY_POLICY_LINK'] ?? null;

            // Check if the setting is an empty input
            if ($tosLink === null) {
                $tosLink = "";
            }
            if ($privacyPolicyLink === null) {
                $privacyPolicyLink = "";
            }

            $tosSetting = $settingsRepository->findOneBy(['name' => 'TOS_LINK']);
            if ($tosSetting) {
                $tosSetting->setValue($tosLink);
                $em->persist($tosSetting);
            }

            $privacyPolicySetting = $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK']);
            if ($privacyPolicySetting) {
                $privacyPolicySetting->setValue($privacyPolicyLink);
                $em->persist($privacyPolicySetting);
            }

            $eventMetadata = [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_TERMS_REQUEST,
                new DateTime(),
                $eventMetadata
            );


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
    public function settings_radius(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
        $data = $getSettings->getSettings($this->userRepository, $this->settingRepository);
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

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

                    if ($value === null) {
                        $value = "";
                    }

                    // Check for specific settings that need domain validation
                    if (in_array($settingName, ['RADIUS_REALM_NAME', 'DOMAIN_NAME', 'RADIUS_TLS_NAME', 'NAI_REALM'])) {
                        if (!$this->isValidDomain($value)) {
                            $this->addFlash(
                                'error_admin',
                                "The value for $settingName is not a valid domain or does not resolve to an IP address."
                            );
                            return $this->redirectToRoute('admin_dashboard_settings_radius');
                        }
                    }

                    $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                    if ($setting) {
                        $setting->setValue($value);
                        $em->persist($setting);
                    }
                }

                $eventMetadata = [
                    'ip' => $_SERVER['REMOTE_ADDR'],
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
    public function settings_status(
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
            // Update the 'USER_VERIFICATION', and, if the platform mode is Live, set email verification to ON always
            $emailVerification = ($platformMode === PlatformMode::Live) ? EmailConfirmationStrategy::EMAIL : $submittedData['USER_VERIFICATION'] ?? null;

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
            // Flush the changes to the database
            $em->flush();

            $eventMetadata = [
                'isIP' => $_SERVER['REMOTE_ADDR'],
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
    public function settings_LDAP(
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
                'ip' => $_SERVER['REMOTE_ADDR'],
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
    public function settings_auth(
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

        $form = $this->createForm(AuthType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                'AUTH_METHOD_SAML_ENABLED',
                'AUTH_METHOD_SAML_LABEL',
                'AUTH_METHOD_SAML_DESCRIPTION',

                'AUTH_METHOD_GOOGLE_LOGIN_ENABLED',
                'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
                'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION',
                'VALID_DOMAINS_GOOGLE_LOGIN',

                'AUTH_METHOD_REGISTER_ENABLED',
                'AUTH_METHOD_REGISTER_LABEL',
                'AUTH_METHOD_REGISTER_DESCRIPTION',

                'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED',
                'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
                'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION',

                'AUTH_METHOD_SMS_REGISTER_ENABLED',
                'AUTH_METHOD_SMS_REGISTER_LABEL',
                'AUTH_METHOD_SMS_REGISTER_DESCRIPTION',
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
                'ip' => $_SERVER['REMOTE_ADDR'],
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
    public function settings_capport(
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
                'ip' => $_SERVER['REMOTE_ADDR'],
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
    public function settings_sms(
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
                'SMS_TIMER_RESEND'
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
                'ip' => $_SERVER['REMOTE_ADDR'],
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
            $startDate = new DateTime($startDateString); // convert the value from string to a datatime type
        } else {
            $startDate = (new DateTime())->modify('-1 week'); // return current datetime minus 1 week if he doesn't exist
        }

        if ($endDateString) {
            $endDate = new DateTime($endDateString); // convert the value from string to a datatime type
        } else {
            $endDate = new DateTime(); // return current datetime
        }

        $fetchChartDevices = $this->fetchChartDevices($startDate, $endDate);
        $fetchChartAuthentication = $this->fetchChartAuthentication($startDate, $endDate);
        $fetchChartPlatformStatus = $this->fetchChartPlatformStatus($startDate, $endDate);
        $fetchChartUserVerified = $this->fetchChartUserVerified($startDate, $endDate);
        $fetchChartSMSEmail = $this->fetchChartSMSEmail($startDate, $endDate);

        return $this->render('admin/statistics.html.twig', [
            'data' => $data,
            'devicesDataJson' => json_encode($fetchChartDevices, JSON_THROW_ON_ERROR),
            'authenticationDataJson' => json_encode($fetchChartAuthentication, JSON_THROW_ON_ERROR),
            'platformStatusDataJson' => json_encode($fetchChartPlatformStatus, JSON_THROW_ON_ERROR),
            'usersVerifiedDataJson' => json_encode($fetchChartUserVerified, JSON_THROW_ON_ERROR),
            'SMSEmailDataJson' => json_encode($fetchChartSMSEmail, JSON_THROW_ON_ERROR),
            'selectedStartDate' => $startDate ? $startDate->format('Y-m-d\TH:i') : '',
            'selectedEndDate' => $endDate ? $endDate->format('Y-m-d\TH:i') : '',
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
            $startDate = new DateTime($startDateString); // convert the value from string to a datatime type
        } else {
            $startDate = (new DateTime())->modify('-1 week'); // return current datetime minus 1 week if he doesn't exist
        }

        if ($endDateString) {
            $endDate = new DateTime($endDateString); // convert the value from string to a datatime type
        } else {
            $endDate = new DateTime(); // return current datetime
        }

        // Fetch all the required data, graphics etc...
        $fetchChartAuthenticationsFreeradius = $this->fetchChartAuthenticationsFreeradius($startDate, $endDate);
        $fetchChartRealmsFreeradius = $this->fetchChartRealmsFreeradius($startDate, $endDate);
        $fetchChartCurrentAuthFreeradius = $this->fetchChartCurrentAuthFreeradius($startDate, $endDate);
        $fetchChartTrafficFreeradius = $this->fetchChartTrafficFreeradius($startDate, $endDate);
        $fetchChartSessionAverageFreeradius = $this->fetchChartSessionAverageFreeradius($startDate, $endDate);
        $fetchChartSessionTotalFreeradius = $this->fetchChartSessionTotalFreeradius($startDate, $endDate);
        $fetchChartWifiTags = $this->fetchChartWifiVersion($startDate, $endDate);
        $fetchChartApUsage = $this->fetchChartApUsage($startDate, $endDate);

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
            'selectedStartDate' => $startDate ? $startDate->format('Y-m-d\TH:i') : '',
            'selectedEndDate' => $endDate ? $endDate->format('Y-m-d\TH:i') : '',
            'exportFreeradiusStatistics' => $export_freeradius_statistics,
        ]);
    }


    /**
     * Exports the freeradius data
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
        $fetchChartAuthenticationsFreeradius = $this->fetchChartAuthenticationsFreeradius($startDate, $endDate);
        $fetchChartSessionAverageFreeradius = $this->fetchChartSessionAverageFreeradius($startDate, $endDate);
        $fetchChartSessionTotalFreeradius = $this->fetchChartSessionTotalFreeradius($startDate, $endDate);
        $fetchChartTrafficFreeradius = $this->fetchChartTrafficFreeradius($startDate, $endDate);
        $fetchChartRealmsFreeradius = $this->fetchChartRealmsFreeradius($startDate, $endDate);
        $fetchChartApUsage = $this->fetchChartApUsage($startDate, $endDate);
        $fetchChartWifiTags = $this->fetchChartWifiVersion($startDate, $endDate);

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

        // Prepare the Wifi Standards Usage data for Excel
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
            'ip' => $_SERVER['REMOTE_ADDR'],
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
     * Fetch data related to downloaded profiles devices
     */
    /**
     * @throws Exception
     */
    private function fetchChartDevices(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $repository = $this->entityManager->getRepository(Event::class);

        // Fetch all data without date filtering
        $events = $repository->findBy(['event_name' => 'DOWNLOAD_PROFILE']);

        $profileCounts = [
            'Android' => 0,
            'Windows' => 0,
            'macOS' => 0,
            'iOS' => 0,
        ];

        // Filter and count profile types based on the date criteria
        foreach ($events as $event) {
            $eventDateTime = $event->getEventDatetime();

            if (!$eventDateTime) {
                continue; // Skip events with missing dates
            }

            if (
                (!$startDate || $eventDateTime >= $startDate) &&
                (!$endDate || $eventDateTime <= $endDate)
            ) {
                $eventMetadata = $event->getEventMetadata();

                if (isset($eventMetadata['type'])) {
                    $profileType = $eventMetadata['type'];

                    // Check the profile type and update the corresponding count
                    if (isset($profileCounts[$profileType])) {
                        $profileCounts[$profileType]++;
                    }
                }
            }
        }

        return $this->generateDatasets($profileCounts);
    }

    /**
     * Fetch data related to types of authentication
     */
    /**
     * @throws Exception
     */
    private function fetchChartAuthentication(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $repository = $this->entityManager->getRepository(User::class);

        /* @phpstan-ignore-next-line */
        $users = $repository->findExcludingAdmin();

        $userCounts = [
            'SAML' => 0,
            'Google' => 0,
            'Portal' => 0,
        ];

        // Loop through the users and categorize them based on saml_identifier and google_id
        foreach ($users as $user) {
            $createdAt = $user->getCreatedAt();

            if (
                (!$startDate || $createdAt >= $startDate) &&
                (!$endDate || $createdAt <= $endDate)
            ) {
                $samlIdentifier = $user->getSamlIdentifier();
                $googleId = $user->getGoogleId();

                if ($samlIdentifier) {
                    $userCounts['SAML']++;
                } else {
                    if ($googleId) {
                        $userCounts['Google']++;
                    } else {
                        $userCounts['Portal']++;
                    }
                }
            }
        }

        return $this->generateDatasets($userCounts);
    }

    /**
     * Fetch data related to users created in platform mode - Live/Demo
     */
    /**
     * @throws Exception
     */
    private function fetchChartPlatformStatus(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $repository = $this->entityManager->getRepository(Event::class);

        // Query the database to get events with "event_name" == "USER_CREATION"
        $events = $repository->findBy(['event_name' => 'USER_CREATION']);

        $statusCounts = [
            'Live' => 0,
            'Demo' => 0,
        ];

        // Loop through the events and count the status of the user when created
        foreach ($events as $event) {
            $eventDateTime = $event->getEventDatetime();

            if (!$eventDateTime) {
                continue;
            }
            if (
                (!$startDate || $eventDateTime >= $startDate) &&
                (!$endDate || $eventDateTime <= $endDate)
            ) {
                $eventMetadata = $event->getEventMetadata();

                if (isset($eventMetadata['platform'])) {
                    $statusType = $eventMetadata['platform'];

                    // Check the status type and update the corresponding count
                    if (isset($statusCounts[$statusType])) {
                        $statusCounts[$statusType]++;
                    }
                }
            }
        }

        return $this->generateDatasets($statusCounts);
    }

    /**
     * Fetch data related to verified users
     */
    /**
     * @throws Exception
     */
    private function fetchChartUserVerified(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $repository = $this->entityManager->getRepository(User::class);

        /* @phpstan-ignore-next-line */
        $users = $repository->findExcludingAdmin();

        $userCounts = [
            'Verified' => 0,
            'Need Verification' => 0,
            'Banned' => 0,
        ];

        // Loop through the users and categorize them based on isVerified and bannedAt
        foreach ($users as $user) {
            $createdAt = $user->getCreatedAt();

            if (
                (!$startDate || $createdAt >= $startDate) &&
                (!$endDate || $createdAt <= $endDate)
            ) {
                $verification = $user->isVerified();
                $ban = $user->getBannedAt();

                if ($verification) {
                    $userCounts['Verified']++;
                } else {
                    $userCounts['Need Verification']++;
                }

                if ($ban) {
                    $userCounts['Banned']++;
                }
            }
        }

        return $this->generateDatasets($userCounts);
    }

    /**
     * Fetch data related to User created on the portal with email or sms
     */
    /**
     * @throws Exception
     */
    private function fetchChartSMSEmail(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $repository = $this->entityManager->getRepository(Event::class);

        // Fetch all data without date filtering
        $events = $repository->findBy(['event_name' => 'USER_CREATION']);

        $PortalUsersCounts = [
            'Phone Number' => 0,
            'Email' => 0,
        ];

        // Filter and count users created with email or phone number based on the event USER_CREATION
        foreach ($events as $event) {
            $eventDateTime = $event->getEventDatetime();

            if (!$eventDateTime) {
                continue; // Skip events with missing dates
            }

            if (
                (!$startDate || $eventDateTime >= $startDate) &&
                (!$endDate || $eventDateTime <= $endDate)
            ) {
                $eventMetadata = $event->getEventMetadata();

                if (isset($eventMetadata['sms'])) {
                    // When 'sms' is true, increment sms
                    if ($eventMetadata['sms'] === true) {
                        $PortalUsersCounts['Phone Number']++;
                    } else {
                        $PortalUsersCounts['Email']++;
                    }
                }
            }
        }

        return $this->generateDatasets($PortalUsersCounts);
    }

    /**
     * Fetch data related to authentication attempts on the freeradius database
     */
    /**
     * @throws Exception
     */
    private function fetchChartAuthenticationsFreeradius(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        // Fetch all data with date filtering
        $events = $this->radiusAuthsRepository->findAuthRequests($startDate, $endDate);

        // Calculate the time difference between start and end dates
        $interval = $startDate->diff($endDate);

        // Determine the appropriate time granularity
        if ($interval->days > 365.2) {
            $granularity = 'year';
        } else {
            if ($interval->days > 90) {
                $granularity = 'month';
            } elseif ($interval->days > 30) {
                $granularity = 'week';
            } else {
                $granularity = 'day';
            }
        }

        $authsCounts = [
            'Accepted' => [],
            'Rejected' => [],
        ];

        // Group the events based on the determined granularity
        foreach ($events as $event) {
            // Convert event date string to DateTime object
            $eventDateTime = new DateTime($event->getAuthdate());

            // Determine the time period based on granularity
            switch ($granularity) {
                case 'year':
                    $period = $eventDateTime->format('Y');
                    break;
                case 'month':
                    $period = $eventDateTime->format('Y-m');
                    break;
                case 'week':
                    $period = $eventDateTime->format('o-W'); // 'o' for ISO-8601 year number, 'W' for week number
                    break;
                case 'day':
                default:
                    $period = $eventDateTime->format('Y-m-d');
                    break;
            }

            // Initialize the period if not already set
            if (!isset($authsCounts['Accepted'][$period])) {
                $authsCounts['Accepted'][$period] = [];
                $authsCounts['Rejected'][$period] = [];
            }

            // Use the timestamp down to the second for deduplication
            $timestamp = $eventDateTime->format('Y-m-d H:i:s');

            // Track unique timestamps within the period
            if (!isset($authsCounts['Accepted'][$period][$timestamp]) && !isset($authsCounts['Rejected'][$period][$timestamp])) {
                $reply = $event->getReply();
                if ($reply === 'Access-Accept') {
                    $authsCounts['Accepted'][$period][$timestamp] = true;
                } elseif ($reply === 'Access-Reject') {
                    $authsCounts['Rejected'][$period][$timestamp] = true;
                }
            }
        }

        // Convert the tracked timestamps into counts
        foreach ($authsCounts['Accepted'] as $period => $timestamps) {
            $authsCounts['Accepted'][$period] = count($timestamps);
        }
        foreach ($authsCounts['Rejected'] as $period => $timestamps) {
            $authsCounts['Rejected'][$period] = count($timestamps);
        }

        // Return an array containing both the generated datasets and the counts
        return $this->generateDatasetsAuths($authsCounts);
    }

    /**
     * Fetch data related to realms usage on the freeradius database
     *
     * @throws Exception
     */
    private function fetchChartRealmsFreeradius(?DateTime $startDate, ?DateTime $endDate): array
    {
        list($startDate, $endDate, $granularity) = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
            $this->radiusAccountingRepository
        );

        $events = $this->radiusAccountingRepository->findDistinctRealms($startDate, $endDate);

        $realmCounts = [];

        // Group the realm usage data based on the determined granularity
        foreach ($events as $event) {
            $realm = $event['realm'];
            $date = $event['acctStartTime'];
            $groupKey = $date->format(
                $granularity === 'year' ? 'Y' : ($granularity === 'month' ? 'Y-m' : ($granularity === 'week' ? 'o-W' : 'Y-m-d'))
            );

            if (!$realm) {
                continue;
            }

            if (!isset($realmCounts[$groupKey])) {
                $realmCounts[$groupKey] = [];
            }

            if (!isset($realmCounts[$groupKey][$realm])) {
                $realmCounts[$groupKey][$realm] = 0;
            }

            $realmCounts[$groupKey][$realm]++;
        }

        $result = [];
        foreach ($realmCounts as $groupKey => $realms) {
            foreach ($realms as $realm => $count) {
                $result[] = [
                    'group' => $groupKey,
                    'realm' => $realm,
                    'count' => $count
                ];
            }
        }

        return $result;
    }

    /**
     * Fetch data related to current authentications on the freeradius database
     */
    /**
     * @throws Exception
     */
    private function fetchChartCurrentAuthFreeradius(?DateTime $startDate, ?DateTime $endDate): array
    {
        // Get the active sessions using the findActiveSessions query
        $activeSessions = $this->radiusAccountingRepository->findActiveSessions()->getResult();

        // Convert the results into the expected format
        $realmCounts = [];
        foreach ($activeSessions as $session) {
            $realm = $session['realm'];
            $numUsers = $session['num_users'];
            $realmCounts[$realm] = $numUsers;
        }

        // Return the counts per realm
        return $this->generateDatasetsRealmsCounting($realmCounts);
    }

    /**
     * Fetch data related to traffic passed on the freeradius database
     * @throws Exception
     */
    private function fetchChartTrafficFreeradius(?DateTime $startDate, ?DateTime $endDate): array
    {
        list($startDate, $endDate, $granularity) = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
            $this->radiusAccountingRepository
        );

        $trafficData = $this->radiusAccountingRepository->findTrafficPerRealm($startDate, $endDate)->getResult();
        $realmTraffic = [];

        // Group the traffic data based on the determined granularity
        foreach ($trafficData as $content) {
            $realm = $content['realm'];
            $totalInput = $content['total_input'];
            $totalOutput = $content['total_output'];
            $date = $content['acctStartTime'];
            $groupKey = $date->format(
                $granularity === 'year' ? 'Y' : ($granularity === 'month' ? 'Y-m' : ($granularity === 'week' ? 'o-W' : 'Y-m-d'))
            );

            if (!isset($realmTraffic[$realm])) {
                $realmTraffic[$realm] = [];
            }

            if (!isset($realmTraffic[$realm][$groupKey])) {
                $realmTraffic[$realm][$groupKey] = ['total_input' => 0, 'total_output' => 0];
            }

            $realmTraffic[$realm][$groupKey]['total_input'] += $totalInput;
            $realmTraffic[$realm][$groupKey]['total_output'] += $totalOutput;
        }

        $result = [];
        foreach ($realmTraffic as $realm => $groups) {
            foreach ($groups as $groupKey => $traffic) {
                $result[] = [
                    'realm' => $realm,
                    'group' => $groupKey,
                    'total_input' => $traffic['total_input'],
                    'total_output' => $traffic['total_output']
                ];
            }
        }

        return $result;
    }


    /**
     * Fetch data related to session time (average) on the freeradius database
     */
    private function fetchChartSessionAverageFreeradius(?DateTime $startDate, ?DateTime $endDate): array
    {
        list($startDate, $endDate, $granularity) = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
            $this->radiusAccountingRepository
        );

        $events = $this->radiusAccountingRepository->findSessionTimeRealms($startDate, $endDate);

        $sessionAverageTimes = [];

        // Group the events based on the determined granularity
        foreach ($events as $event) {
            $sessionTime = $event['acctSessionTime'];
            $date = $event['acctStartTime'];
            $groupKey = $date->format(
                $granularity === 'year' ? 'Y' : ($granularity === 'month' ? 'Y-m' : ($granularity === 'week' ? 'o-W' : 'Y-m-d'))
            );

            if (!isset($sessionAverageTimes[$groupKey])) {
                $sessionAverageTimes[$groupKey] = ['totalTime' => 0, 'count' => 0];
            }

            $sessionAverageTimes[$groupKey]['totalTime'] += $sessionTime;
            $sessionAverageTimes[$groupKey]['count']++;
        }

        $result = [];
        foreach ($sessionAverageTimes as $groupKey => $data) {
            $averageSessionTime = $data['count'] > 0 ? $data['totalTime'] / $data['count'] : 0;
            $result[] = [
                'group' => $groupKey,
                'averageSessionTime' => $averageSessionTime
            ];
        }

        return $this->generateDatasetsSessionAverage($result);
    }


    /**
     * Fetch data related to session time (total) on the freeradius database
     */
    private function fetchChartSessionTotalFreeradius(?DateTime $startDate, ?DateTime $endDate): array
    {
        list($startDate, $endDate, $granularity) = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
            $this->radiusAccountingRepository
        );

        $events = $this->radiusAccountingRepository->findSessionTimeRealms($startDate, $endDate);

        $sessionTotalTimes = [];

        // Group the events based on the determined granularity
        foreach ($events as $event) {
            $sessionTime = $event['acctSessionTime'];
            $date = $event['acctStartTime'];
            $groupKey = $date->format(
                $granularity === 'year' ? 'Y' : ($granularity === 'month' ? 'Y-m' : ($granularity === 'week' ? 'o-W' : 'Y-m-d'))
            );

            if (!isset($sessionTotalTimes[$groupKey])) {
                $sessionTotalTimes[$groupKey] = 0;
            }

            $sessionTotalTimes[$groupKey] += $sessionTime;
        }

        $result = [];
        foreach ($sessionTotalTimes as $groupKey => $totalSessionTime) {
            $result[] = [
                'group' => $groupKey,
                'totalSessionTime' => $totalSessionTime
            ];
        }

        return $this->generateDatasetsSessionTotal($result);
    }


    /**
     * Fetch data related to wifi tag usage on the freeradius database
     */
    private function fetchChartWifiVersion(?DateTime $startDate, ?DateTime $endDate): array
    {
        list($startDate, $endDate, $granularity) = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
            $this->radiusAccountingRepository
        );

        $events = $this->radiusAccountingRepository->findWifiVersion($startDate, $endDate);
        $wifiUsage = [];

        // Group the events based on the wifi Standard
        foreach ($events as $event) {
            $connectInfo = $event['connectInfo_start'];
            $wifiStandard = $this->mapConnectInfoToWifiStandard($connectInfo);

            if (!isset($wifiUsage[$wifiStandard])) {
                $wifiUsage[$wifiStandard] = 0;
            }

            $wifiUsage[$wifiStandard]++;
        }

        $result = [];
        foreach ($wifiUsage as $standard => $count) {
            $result[] = [
                'standard' => $standard,
                'count' => $count
            ];
        }

        return $this->generateDatasetsWifiTags($result);
    }

    /**
     * Fetch data related to AP usage on the freeradius database
     *
     * @throws Exception
     */
    private function fetchChartApUsage(?DateTime $startDate, ?DateTime $endDate): array
    {
        list($startDate, $endDate) = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
            $this->radiusAccountingRepository
        );

        $events = $this->radiusAccountingRepository->findApUsage($startDate, $endDate);

        $apCounts = [];

        // Count the usage of each AP
        foreach ($events as $event) {
            $ap = $event['calledStationId'];

            if (!$ap) {
                continue;
            }

            if (!isset($apCounts[$ap])) {
                $apCounts[$ap] = 0;
            }

            $apCounts[$ap]++;
        }

        $result = [];
        foreach ($apCounts as $ap => $count) {
            $result[] = [
                'ap' => $ap,
                'count' => $count
            ];
        }

        // Sort the result array by the count value with the highest usage
        usort($result, static function ($highest, $lowest) {
            return $lowest['count'] <=> $highest['count'];
        });

        return $result;
    }

    /**
     * Generated Datasets for charts graphics
     */
    private function generateDatasets(array $counts): array
    {
        $datasets = [];
        $labels = array_keys($counts);
        $dataValues = array_values($counts);

        $data = [];
        $colors = [];

        // Calculate the colors with varying opacities
        $colors = $this->generateColorsWithOpacity($dataValues);

        foreach ($labels as $index => $type) {
            $data[] = $dataValues[$index];
        }

        $datasets[] = [
            'data' => $data,
            'backgroundColor' => $colors,
            'borderColor' => "rgb(125, 185, 40)",
            'borderRadius' => "15",
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Generate datasets for session average time
     */
    private function generateDatasetsSessionAverage(array $sessionTime): array
    {
        $labels = array_column($sessionTime, 'group');
        $averageTimes = array_map(function ($item) {
            return $item['averageSessionTime']; // Keep numerical values for plotting
        }, $sessionTime);

        $averageTimesReadable = array_map(function ($seconds) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return sprintf('%dh %dm', $hours, $minutes);
        }, $averageTimes);

        // Calculate the colors with varying opacities
        $colors = $this->generateColorsWithOpacity($averageTimes);

        $datasets = [
            [
                'label' => 'Average Session Time',
                'data' => $averageTimes,
                'backgroundColor' => $colors,
                'borderRadius' => "15",
                'tooltips' => $averageTimesReadable, // Human-readable values for tooltips
            ]
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Generate datasets for session total time
     */
    private function generateDatasetsSessionTotal(array $sessionTime): array
    {
        $labels = array_column($sessionTime, 'group');
        $totalTimes = array_map(function ($item) {
            return $item['totalSessionTime'];
        }, $sessionTime);

        $totalTimesReadable = array_map(function ($seconds) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return sprintf('%dh %dm', $hours, $minutes);
        }, $totalTimes);

        // Calculate the colors with varying opacities
        $colors = $this->generateColorsWithOpacity($totalTimes);

        $datasets = [
            [
                'label' => 'Total Session Time',
                'data' => $totalTimes,
                'backgroundColor' => $colors,
                'borderRadius' => "15",
                'tooltips' => $totalTimesReadable,
            ]
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Generate datasets for Wi-Fi tags
     */
    private function generateDatasetsWifiTags(array $wifiUsage): array
    {
        $labels = array_column($wifiUsage, 'standard');
        $counts = array_column($wifiUsage, 'count');

        // Calculate the colors with varying opacities
        $colors = $this->generateColorsWithOpacity($counts);

        $datasets = [
            [
                'label' => 'Wi-Fi Usage',
                'data' => $counts,
                'backgroundColor' => $colors,
                'borderRadius' => "15"
            ]
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
            'rawData' => $counts // Include raw numerical data
        ];
    }

    /**
     * Generate datasets for authentication attempts
     */
    private function generateDatasetsAuths(array $authsCounts): array
    {
        $labels = array_keys($authsCounts['Accepted']);
        $acceptedCounts = array_values($authsCounts['Accepted']);
        $rejectedCounts = array_values($authsCounts['Rejected']);

        $datasets = [
            [
                'label' => 'Accepted',
                'data' => $acceptedCounts,
                'backgroundColor' => '#7DB928',
                'borderRadius' => "15",
            ],
            [
                'label' => 'Rejected',
                'data' => $rejectedCounts,
                'backgroundColor' => '#FE4068',
                'borderRadius' => "15",
            ]
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    private function generateDatasetsRealmsCounting(array $counts): array
    {
        $datasets = [];
        $labels = array_keys($counts);
        $dataValues = array_values($counts);

        $colors = [];

        // Assign a specific color to the first most used realm
        $colors[] = '#7DB928';

        // Generate colors based on the realm names
        foreach ($labels as $realm) {
            // Generate a color based on the realm name
            $color = $this->generateColorFromRealmName($realm);

            // Add the color to the list
            $colors[] = $color;
        }

        $datasets[] = [
            'data' => $dataValues,
            'backgroundColor' => $colors,
            'borderRadius' => "15",
        ];

        // Extract unique colors
        $uniqueColors = array_unique($colors);

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    private function generateDatasetsRealmsTraffic(array $trafficData): array
    {
        $datasets = [];
        $labels = [];
        $dataValuesInput = [];
        $dataValuesOutput = [];
        $colors = [];

        $colors[] = '#7DB928';

        foreach ($trafficData as $realm => $traffic) {
            $labels[] = $realm;
            $dataValuesInput[] = $traffic['total_input'];
            $dataValuesOutput[] = $traffic['total_output'];
            $colors[] = $this->generateColorFromRealmName($realm);
        }

        $datasets[] = [
            'data' => $dataValuesInput,
            'label' => 'Uploaded',
            'backgroundColor' => $colors,
            'borderWidth' => 1,
            'borderRadius' => "15",
        ];

        $datasets[] = [
            'data' => $dataValuesOutput,
            'label' => 'Downloaded',
            'backgroundColor' => $colors,
            'borderWidth' => 1,
            'borderRadius' => "15",
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    private function generateColorFromRealmName(string $realm): string
    {
        // Generate a hash based on the realm name
        $hash = md5($realm);

        // Extract RGB values from the hash
        $red = hexdec(substr($hash, 0, 2));
        $green = hexdec(substr($hash, 2, 2));
        $blue = hexdec(substr($hash, 4, 2));

        // Format the RGB values into a CSS color string and convert to uppercase
        $color = strtoupper(sprintf('#%02x%02x%02x', $red, $green, $blue));

        return $color;
    }


    /**
     * Handles the Page Style on the dasboard
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
                if (in_array(
                    $settingName,
                    [
                        'WELCOME_TEXT',
                        'PAGE_TITLE',
                        'WELCOME_DESCRIPTION',
                        'ADDITIONAL_LABEL',
                        'CONTACT_EMAIL',
                        'CUSTOMER_LOGO_ENABLED'
                    ]
                )) {
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
                        $destinationDirectory = $this->getParameter(
                                'kernel.project_dir'
                            ) . '/public/resources/uploaded/';

                        $file->move($destinationDirectory, $newFilename);
                        $setting->setValue('/resources/uploaded/' . $newFilename);
                    }
                    // PLS MAKE SURE TO USE THIS COMMAND ON THE WEB CONTAINER chown -R www-data:www-data /var/www/openroaming/public/resources/uploaded/
                }
            }

            $this->addFlash('success_admin', 'Customization settings have been updated successfully.');

            $eventMetadata = [
                'ip' => $_SERVER['REMOTE_ADDR'],
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
     * Map connectInfo_start to Wifi standards
     * @param string $connectInfo
     * @return string
     */
    protected function mapConnectInfoToWifiStandard(string $connectInfo): string
    {
        switch (true) {
            case strpos($connectInfo, '802.11be') !== false:
                return 'Wi-Fi 7';
            case strpos($connectInfo, '802.11ax') !== false:
                return 'Wi-Fi 6';
            case strpos($connectInfo, '802.11ac') !== false:
                return 'Wi-Fi 5';
            case strpos($connectInfo, '802.11n') !== false:
                return 'Wi-Fi 4';
            case strpos($connectInfo, '802.11g') !== false:
                return 'Wi-Fi 3';
            case strpos($connectInfo, '802.11a') !== false:
                return 'Wi-Fi 2';
            case strpos($connectInfo, '802.11b') !== false:
                return 'Wi-Fi 1';
            default:
                return 'Unknown';
        }
    }

    /**
     * Determine date range and granularity
     *
     * @param ?DateTime $startDate
     * @param ?DateTime $endDate
     * @param object $repository
     * @return array
     */
    protected function determineDateRangeAndGranularity(?DateTime $startDate, ?DateTime $endDate, $repository): array
    {
        // Calculate the time difference between start and end dates
        $interval = $startDate->diff($endDate);

        // Determine the appropriate time granularity
        if ($interval->days > 365.2) {
            $granularity = 'year';
        } elseif ($interval->days > 90) {
            $granularity = 'month';
        } elseif ($interval->days > 30) {
            $granularity = 'week';
        } else {
            $granularity = 'day';
        }

        return [$startDate, $endDate, $granularity];
    }

    /**
     * Generate colors with varying opacities based on data values
     *
     * @param array $values
     * @param float $minOpacity
     * @param float $maxOpacity
     * @return array
     */
    private function generateColorsWithOpacity(array $values, float $minOpacity = 0.4, float $maxOpacity = 1): array
    {
        if (!empty(array_filter($values, static fn($value) => $value !== 0))) {
            $maxValue = max($values);
            $colors = [];

            foreach ($values as $value) {
                // Calculate the opacity relative to the max value, scaled to the opacity range
                $opacity = $minOpacity + ($value / $maxValue) * ($maxOpacity - $minOpacity);
                $opacity = round($opacity, 2); // Round to 2 decimal places for better control
                $colors[] = "rgba(125, 185, 40, {$opacity})";
            }

            return $colors;
        } else {
            return array_fill(0, count($values), "rgba(125, 185, 40, 1)"); // Default color if no non-zero values
        }
    }

    /**
     * Generate a new verification code for the admin.
     *
     * @param User $user The user for whom the verification code is generated.
     * @return int The generated verification code.
     * @throws Exception
     */
    protected function generateVerificationCode(User $user): int
    {
        // Generate a random verification code with 6 digits
        $verificationCode = random_int(100000, 999999);
        $user->setVerificationCode($verificationCode);
        $this->userRepository->save($user, true);

        return $verificationCode;
    }

    // Validate domain names and check if they resolve to an IP address
    // Validation comes from here: https://www.php.net/manual/en/function.dns-get-record.php
    protected function isValidDomain($domain)
    {
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return false;
        }
        $dnsRecords = @dns_get_record($domain, DNS_A + DNS_AAAA);
        if ($dnsRecords === false || empty($dnsRecords)) {
            return false;
        }
        return true;
    }

    /**
     * Escape a value to prevent spreadsheet injection for the export routes (EXPORT USERS || FREERADIUS)
     * @param mixed $value
     * @return string
     */
    private function escapeSpreadsheetValue($value): string
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        $escapedValue = (string)$value;
        $specialChars = ['=', '@', '-', '+'];
        if (in_array($escapedValue[0] ?? '', $specialChars, true)) {
            $escapedValue = "'" . $escapedValue;
        }
        return $escapedValue;
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
