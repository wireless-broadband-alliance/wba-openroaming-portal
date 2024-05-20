<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Setting;
use App\Entity\User;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\PlatformMode;
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
use App\Service\GetSettings;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

/**
 *
 */
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
     */
    public function __construct(
        MailerInterface            $mailer,
        UserRepository             $userRepository,
        ProfileManager             $profileManager,
        ParameterBagInterface      $parameterBag,
        GetSettings                $getSettings,
        SettingRepository          $settingRepository,
        EntityManagerInterface     $entityManager,
        RadiusAuthsRepository      $radiusAuthsRepository,
        RadiusAccountingRepository $radiusAccountingRepository
    )
    {
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
        $this->profileManager = $profileManager;
        $this->parameterBag = $parameterBag;
        $this->getSettings = $getSettings;
        $this->settingRepository = $settingRepository;
        $this->entityManager = $entityManager;
        $this->radiusAuthsRepository = $radiusAuthsRepository;
        $this->radiusAccountingRepository = $radiusAccountingRepository;
    }

    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route('/dashboard', name: 'admin_page')]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboard(Request $request, UserRepository $userRepository): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $page = $request->query->getInt('page', 1); // Get the current page from the query parameter
        $perPage = 15; // Number of users to display per page


        $sort = $request->query->get('sort', 'createdAt');  // Default sort by user creation date
        $order = $request->query->get('order', 'desc'); // Default order: descending

        // Fetch users with the specified sorting
        $users = $userRepository->findExcludingAdmin();

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

        $filter = $request->query->get('filter', 'all'); // Default filter

        // Perform pagination manually
        $totalUsers = count($users); // Get the total number of users

        $totalPages = ceil($totalUsers / $perPage); // Calculate the total number of pages

        $offset = ($page - 1) * $perPage; // Calculate the offset for slicing the users

        $users = array_slice($users, $offset, $perPage); // Fetch the users for the current page

        // Fetch user counts for table header (All/Verified/Banned)
        $allUsersCount = $userRepository->countAllUsersExcludingAdmin();
        $verifiedUsersCount = $userRepository->countVerifiedUsers();
        $bannedUsersCount = $userRepository->countBannedUsers();

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Check if the export users operation is enabled
        $export_users = $this->parameterBag->get('app.export_users');

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'current_user' => $currentUser,
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
            'export_users' => $export_users
        ]);
    }

    /**
     * @param UserRepository $userRepository
     * @return Response
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/dashboard/export/users', name: 'admin_page_export_users')]
    #[IsGranted('ROLE_ADMIN')]
    public function exportUsers(UserRepository $userRepository): Response
    {
        // Check if the export users operation is enabled
        $export_users = $this->parameterBag->get('app.export_users');
        if ($export_users === EmailConfirmationStrategy::NO_EMAIL) {
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
            $sheet->setCellValue('A' . $row, $user->getId());
            $sheet->setCellValue('B' . $row, $user->getUuid());
            $sheet->setCellValue('C' . $row, $user->getEmail());
            $sheet->setCellValue('D' . $row, $user->getPhoneNumber());
            $sheet->setCellValue('E' . $row, $user->getFirstName());
            $sheet->setCellValue('F' . $row, $user->getLastName());
            $sheet->setCellValue('G' . $row, $user->isVerified() ? 'Verified' : 'Not Verified');
            // Determine User Provider
            $userProvider = $this->getUserProvider($user);
            $sheet->setCellValue('H' . $row, $userProvider);
            // Check if the user is Banned
            $sheet->setCellValue('I' . $row, $user->getBannedAt() !== null ? $user->getBannedAt()->format('Y-m-d H:i:s') : 'Not Banned');
            $sheet->setCellValue('J' . $row, $user->getCreatedAt());


            $row++;
        }

        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'users');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        // Return the file as a response
        return $this->file($tempFile, 'users.xlsx');
    }

    // Determine user provider
    public function getUserProvider(User $user): string
    {
        if ($user->getGoogleId() !== null) {
            return UserProvider::Google_Account;
        }

        if ($user->getSamlIdentifier() !== null) {
            return UserProvider::SAML;
        }

        return UserProvider::Portal_Account;
    }

    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route('/dashboard/search', name: 'admin_search', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function searchUsers(Request $request, UserRepository $userRepository): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $searchTerm = $request->query->get('u');
        $page = $request->query->getInt('page', 1);
        $perPage = 15;

        $filter = $request->query->get('filter', 'all'); // Default filter

        $sort = $request->query->get('sort', 'createdAt'); // Default sort by user creation date
        $order = $request->query->get('order', 'desc'); // Default order: descending

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

        if (strlen($searchTerm) > 320) {
            $this->addFlash('error', 'Please enter a search term with fewer than 320 characters.');
            return $this->redirectToRoute('admin_page');
        }

        $totalUsers = count($users);
        $totalPages = ceil($totalUsers / $perPage);
        $offset = ($page - 1) * $perPage;
        $users = array_slice($users, $offset, $perPage);

        $allUsersCount = $userRepository->countAllUsersExcludingAdmin();
        $verifiedUsersCount = $userRepository->countVerifiedUsers();
        $bannedUsersCount = $userRepository->countBannedUsers();

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Check if the export users operation is enabled
        $export_users = $this->parameterBag->get('app.export_users');

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'currentPage' => $page,
            'current_user' => $currentUser,
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
            'export_users' => $export_users
        ]);
    }

    /**
     * @param $id
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route('/dashboard/delete/{id<\d+>}', name: 'admin_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteUsers($id, EntityManagerInterface $em): Response
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        if ($user->getDeletedAt() !== null) {
            $this->addFlash('error_admin', 'This user has already been deleted.');
            return $this->redirectToRoute('admin_page');
        }

        $uuid = $user->getUUID();
        foreach ($user->getEvent() as $event) {
            $em->remove($event);
        }
        $user->setDeletedAt(new DateTime());
        $this->disableProfiles($user);

        $em->persist($user);
        $em->flush();

        $this->addFlash('success_admin', sprintf('User with the UUID "%s" deleted successfully.', $uuid));
        return $this->redirectToRoute('admin_page');
    }


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
    public function editUsers(Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em, MailerInterface $mailer, $id): Response
    {
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
            $email = $user->getEmail();
            $this->addFlash('success_admin', sprintf('"%s" has been updated successfully.', $email));

            return $this->redirectToRoute('admin_page');
        }

        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        $formReset = $this->createForm(ResetPasswordType::class, $user);
        $formReset->handleRequest($request);

        if ($formReset->isSubmitted() && $formReset->isValid()) {
            // get the typed password by the admin
            $newPassword = $formReset->get('password')->getData();
            // Hash the new password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
//            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
//                $verificationCode = $this->generateVerificationCode($user);
//                // Removes the admin access until he confirms his new password
//                $user->setVerificationCode($verificationCode);
//                $user->setPassword($hashedPassword);
//                $user->setIsVerified(0);
//                $em->persist($user);
//                $em->flush();
//                return $this->redirectToRoute('app_dashboard_regenerate_code_admin', ['type' => 'password']);
//            }
            $user->setPassword($hashedPassword);
            $em->flush();

            // Send email to the user with the new password
            $email = (new Email())
                ->from(new Address($emailSender, $nameSender))
                ->to($user->getEmail())
                ->subject('Your Password Reset Details')
                ->html(
                    $this->renderView(
                        'email_activation/email_template_password.html.twig',
                        ['password' => $newPassword, 'isNewUser' => false]
                    )
                );
            $mailer->send($email);
            $this->addFlash('success_admin', sprintf('"%s" has is password updated.', $user->getEmail()));
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


    /**
     * @param string $type Type of action
     * Render a confirmation password form
     * @return Response
     */
    #[Route('/dashboard/confirm/{type}', name: 'admin_confirm_reset')]
    #[IsGranted('ROLE_ADMIN')]
    public function confirmReset(string $type): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        return $this->render('admin/confirm.html.twig', [
            'data' => $data,
            'type' => $type,
            'current_user' => $currentUser,
        ]);
    }

    /**
     * @param RequestStack $requestStack
     * @param EntityManagerInterface $em
     * @param string $type Type of action
     * @return Response
     * Check if the code and then return the correct action
     * @throws Exception
     */
    #[Route('/dashboard/confirm-checker/{type}', name: 'admin_confirm_checker')]
    #[IsGranted('ROLE_ADMIN')]
    public function checkPassword(RequestStack $requestStack, EntityManagerInterface $em, string $type): Response
    {
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
                $this->addFlash('success_admin', 'The setting has been reset successfully!');
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
    public function regenerateCode(string $type): RedirectResponse
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
    protected function createEmailAdmin(string $email): Email
    {
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
            ->htmlTemplate('email_activation/email_template_admin.html.twig')
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
    public function settings_terms(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
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

            // Flush the changes to the database
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
    public function settings_radius(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(RadiusType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

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

            $staticValue = '887FAE2A-F051-4CC9-99BB-8DFD66F553A9';
            if ($submittedData['PAYLOAD_IDENTIFIER'] === $staticValue) {
                $this->addFlash('error_admin', 'Please do not use the default value from the Payload Identifier card.');
                return $this->redirectToRoute('admin_dashboard_settings_radius');
            }

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


            // Flush the changes to the database
            $em->flush();

            $this->addFlash('success_admin', 'Radius configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_radius');
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
    #[Route('/dashboard/settings/status', name: 'admin_dashboard_settings_status')]
    #[IsGranted('ROLE_ADMIN')]
    public function settings_status(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
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
    public function settings_LDAP(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

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

            // Flush the changes to the database
            $em->flush();

            $this->addFlash('success_admin', 'New LDAP configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_LDAP');
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
    #[Route('/dashboard/settings/auth', name: 'admin_dashboard_settings_auth')]
    #[IsGranted('ROLE_ADMIN')]
    public function settings_auth(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
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

            // Flush the changes to the database
            $em->flush();

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
    public function settings_capport(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

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

            // Flush the changes to the database
            $em->flush();

            $this->addFlash('success_admin', 'New CAPPORT configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_capport');
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
    #[Route('/dashboard/settings/sms', name: 'admin_dashboard_settings_sms')]
    #[IsGranted('ROLE_ADMIN')]
    public function settings_sms(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
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

            // Flush the changes to the database
            $em->flush();

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
        $user = $this->getUser();

        // Get the submitted start and end dates from the form
        $startDateString = $request->request->get('startDate');
        $endDateString = $request->request->get('endDate');

        // Convert the date strings to DateTime objects
        if ($startDateString) {
            $startDate = new DateTime($startDateString);
        } else if ($startDateString === "") {
            $startDate = null;
        } else {
            $startDate = (new DateTime())->modify('-1 month');
        }

        if ($endDateString) {
            $endDate = new DateTime($endDateString);
        } else if ($endDateString === "") {
            $endDate = null;
        } else {
            $endDate = new DateTime();
        }

        $fetchChartDevices = $this->fetchChartDevices($startDate, $endDate);
        $fetchChartAuthentication = $this->fetchChartAuthentication($startDate, $endDate);
        $fetchChartPlatformStatus = $this->fetchChartPlatformStatus($startDate, $endDate);
        $fetchChartUserVerified = $this->fetchChartUserVerified($startDate, $endDate);
        $fetchChartSMSEmail = $this->fetchChartSMSEmail($startDate, $endDate);

        return $this->render('admin/statistics.html.twig', [
            'data' => $data,
            'current_user' => $user,
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
     * @return Response
     * @throws \JsonException
     * @throws Exception
     */
    #[Route('/dashboard/statistics/freeradius', name: 'admin_dashboard_statistics_freeradius')]
    #[IsGranted('ROLE_ADMIN')]
    public function freeradiusStatisticsData(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $user = $this->getUser();
        $export_freeradius_statistics = $this->parameterBag->get('app.export_freeradius_statistics');

        // Get the submitted start and end dates from the form
        $startDateString = $request->request->get('startDate');
        $endDateString = $request->request->get('endDate');

        // Convert the date strings to DateTime objects
        if ($startDateString) {
            $startDate = new DateTime($startDateString);
        } else if ($startDateString === "") {
            $startDate = null;
        } else {
            $startDate = (new DateTime())->modify('-1 month');
        }

        if ($endDateString) {
            $endDate = new DateTime($endDateString);
        } else if ($endDateString === "") {
            $endDate = null;
        } else {
            $endDate = new DateTime();
        }

        // Fetch all the required data, graphics etc...
        $fetchChartAuthenticationsFreeradius = $this->fetchChartAuthenticationsFreeradius($startDate, $endDate);
        $fetchChartRealmsFreeradius = $this->fetchChartRealmsFreeradius($startDate, $endDate);
        $fetchChartCurrentAuthFreeradius = $this->fetchChartCurrentAuthFreeradius($startDate, $endDate);
        $fetchChartTrafficFreeradius = $this->fetchChartTrafficFreeradius($startDate, $endDate);
        $fetchChartSessionTimeFreeradius = $this->fetchChartSessionTimeFreeradius($startDate, $endDate);

        // Extract the connection attempts
        $authCounts = [
            'Accepted' => $fetchChartAuthenticationsFreeradius['datasets'][0]['data'][0],
            'Rejected' => $fetchChartAuthenticationsFreeradius['datasets'][0]['data'][1],
        ];

        $totalSessionTimeSeconds = 0;
        $averageSessionTimeSeconds = 0;
        // Iterate over the session data to calculate total and average session times
        foreach ($fetchChartSessionTimeFreeradius as $session) {
            $totalSessionTimeSeconds = $session['totalSessionTime'] + $totalSessionTimeSeconds;
            $averageSessionTimeSeconds = $session['averageSessionTime'] + $averageSessionTimeSeconds;
        }

        // Format total session time
        $totalHours = floor($totalSessionTimeSeconds / 3600);
        $totalMinutes = floor(($totalSessionTimeSeconds % 3600) / 60);
        $sessionTotalTime = sprintf('%dh %dm', $totalHours, $totalMinutes);

        // Format average session time
        $averageHours = floor($averageSessionTimeSeconds / 3600);
        $averageMinutes = floor(($averageSessionTimeSeconds % 3600) / 60);
        $sessionAverageTime = sprintf('%dh %dm', $averageHours, $averageMinutes);

        // Sum all the traffic from the Accounting table
        $totalTraffic = [
            'total_input' => 0,
            'total_output' => 0,
        ];
        foreach ($fetchChartTrafficFreeradius['datasets'] as $dataset) {
            // Check if the dataset is for input or output
            if ($dataset['label'] === 'Uploaded') {
                // Sum the data for total input
                foreach ($dataset['data'] as $sum) {
                    $totalTraffic['total_input'] = $sum + $totalTraffic['total_input'];
                }
            } elseif ($dataset['label'] === 'Downloaded') {
                // Sum the data for total output
                foreach ($dataset['data'] as $sum) {
                    $totalTraffic['total_output'] = $sum + $totalTraffic['total_output'];
                }
            }
        }

        // Extract all realms names
        $realmsNames = $fetchChartRealmsFreeradius['labels'];

        // Sum all the current authentication
        $totalCurrentAuths = 0;
        foreach ($fetchChartCurrentAuthFreeradius['datasets'] as $dataset) {
            // Sum the data points in the current dataset
            $totalCurrentAuths = array_sum($dataset['data']) + $totalCurrentAuths;
        }

        return $this->render('admin/freeradius_statistics.html.twig', [
            'data' => $data,
            'current_user' => $user,
            'realmsUsage' => $realmsNames,
            'authCounts' => $authCounts,
            'totalCurrentAuths' => $totalCurrentAuths,
            'totalTrafficFreeradius' => $totalTraffic,
            'labelsRealmList' => $fetchChartRealmsFreeradius['labels'],
            'datasetsRealmList' => $fetchChartRealmsFreeradius['datasets'],
            'sessionTimeAverage' => $sessionAverageTime,
            'sessionTimeAverageSeconds' => $averageSessionTimeSeconds,
            'sessionTimeTotal' => $sessionTotalTime,
            'sessionTimeTotalSeconds' => $totalSessionTimeSeconds,
            'authAttemptsJson' => json_encode($fetchChartAuthenticationsFreeradius, JSON_THROW_ON_ERROR),
            'selectedStartDate' => $startDate ? $startDate->format('Y-m-d\TH:i') : '',
            'selectedEndDate' => $endDate ? $endDate->format('Y-m-d\TH:i') : '',
            'exportFreeradiusStatistics' => $export_freeradius_statistics,
        ]);
    }

    /**
     * @return Response
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/dashboard/export/freeradius', name: 'admin_page_export_freeradius')]
    #[IsGranted('ROLE_ADMIN')]
    public function exportFreeradius(Request $request): Response
    {
        // Get the submitted start and end dates from the form
        $startDateString = $request->request->get('startDate');
        $endDateString = $request->request->get('endDate');

        // Convert the date strings to DateTime objects
        if ($startDateString) {
            $startDate = new DateTime($startDateString);
        } else if ($startDateString === "") {
            $startDate = null;
        } else {
            $startDate = (new DateTime())->modify('-1 month');
        }

        if ($endDateString) {
            $endDate = new DateTime($endDateString);
        } else if ($endDateString === "") {
            $endDate = null;
        } else {
            $endDate = new DateTime();
        }

        // Fetch all the required data, graphics etc...
        $fetchChartAuthenticationsFreeradius = $this->fetchChartAuthenticationsFreeradius($startDate, $endDate);
        $fetchChartRealmsFreeradius = $this->fetchChartRealmsFreeradius($startDate, $endDate);
        $fetchChartCurrentAuthFreeradius = $this->fetchChartCurrentAuthFreeradius($startDate, $endDate);
        $fetchChartTrafficFreeradius = $this->fetchChartTrafficFreeradius($startDate, $endDate);
        $fetchChartSessionTimeFreeradius = $this->fetchChartSessionTimeFreeradius($startDate, $endDate);

        // Sum all the current authentication
        $totalCurrentAuths = 0;
        foreach ($fetchChartCurrentAuthFreeradius['datasets'] as $dataset) {
            // Sum the data points in the current dataset
            $totalCurrentAuths = array_sum($dataset['data']) + $totalCurrentAuths;
        }

        $totalTraffic = [
            'total_input' => 0,
            'total_output' => 0,
        ];
        // Sum all the traffic from the Accounting table
        foreach ($fetchChartTrafficFreeradius['datasets'] as $dataset) {
            // Check if the dataset is for input or output
            if ($dataset['label'] === 'Uploaded') {
                // Sum the data for total input
                foreach ($dataset['data'] as $sum) {
                    $totalTraffic['total_input'] = $sum + $totalTraffic['total_input'];
                }
            } elseif ($dataset['label'] === 'Downloaded') {
                // Sum the data for total output
                foreach ($dataset['data'] as $sum) {
                    $totalTraffic['total_output'] = $sum + $totalTraffic['total_output'];
                }
            }
        }

        // Create a new PhpSpreadsheet Spreadsheet object
        $spreadsheet = new Spreadsheet();
        $pageOne = $spreadsheet->getActiveSheet();

        // Return realms names and session time to export
        $combinedRealmSessionTime = [];
        foreach ($fetchChartSessionTimeFreeradius as $session) {
            $realm = $session['realm'];
            $totalSessionTime = $session['totalSessionTime'];
            $averageSessionTime = $session['averageSessionTime'];

            $combinedRealmSessionTime[] = [
                'Realm Name' => $realm,
                'Total Session Time (seconds)' => $totalSessionTime,
                'Average Session Time (seconds)' => $averageSessionTime,
            ];
        }

        // Set the titles and their respective content
        $titlesAndContent = [
            'Authentication Attempts' => [
                'Accepted' => $fetchChartAuthenticationsFreeradius['datasets'][0]['data'][0] ?? [],
                'Rejected' => $fetchChartAuthenticationsFreeradius['datasets'][0]['data'][1] ?? [],
            ],
            'Session Time' => $combinedRealmSessionTime,
            'Total of Traffic' => [
                'Uploaded' => $totalTraffic['total_input'],
                'Downloaded' => $totalTraffic['total_output'],
            ],
            'Realms List' => $fetchChartRealmsFreeradius['labels'] ?? [],
            'Current Authenticated per Realm' => $fetchChartCurrentAuthFreeradius['labels'] ?? [],
            'Total Of Current Authentications' => $totalCurrentAuths,
        ];

        $row = 1;
        // Iterate over each title and its content
        foreach ($titlesAndContent as $title => $content) {
            // Set the title in column A
            $pageOne->setCellValue('A' . $row, $title);

            // Check if the content is an array
            if (is_array($content)) {
                // Iterate over the content
                foreach ($content as $key => $value) {
                    // Check if the value is an array
                    if (is_array($value)) {
                        // If the value is an array, convert it to a string representation
                        $formattedValue = json_encode($value);
                    } else {
                        // If the value is not an array, use it directly
                        $formattedValue = $value;
                    }

                    // Set the key and formatted value in columns B and C
                    $pageOne->setCellValue('B' . $row, $key);
                    $pageOne->setCellValue('C' . $row, $formattedValue);

                    // Increment row counter
                    $row++;
                }
            } else {
                // If the content is not an array, set it in column B
                $pageOne->setCellValue('B' . $row, $content);

                // Increment row counter
                $row++;
            }
        }

        // Save the spreadsheet to a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'freeradius_statistics') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $this->file($tempFile, 'freeradiusStatistics.xlsx');
    }

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
                } else if ($googleId) {
                    $userCounts['Google']++;
                } else {
                    $userCounts['Portal']++;
                }
            }
        }

        return $this->generateDatasets($userCounts);
    }

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
     * @throws Exception
     */
    private function fetchChartAuthenticationsFreeradius(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        // Fetch all data with date filtering
        $events = $this->radiusAuthsRepository->findBy(['reply' => ['Access-Accept', 'Access-Reject']]);

        $authsCounts = [
            'Accepted' => 0,
            'Rejected' => 0,
        ];

        $uniqueSeconds = []; // Keep track of unique seconds

        // Filter and count authenticates types based on the date criteria
        foreach ($events as $event) {
            // Convert event date string to DateTime object
            $eventDateTime = new DateTime($event->getAuthdate());

            // Skip events with missing dates
            if (!$eventDateTime) {
                continue;
            }

            // Check if the event date falls within the specified date range
            if (
                (!$startDate || $eventDateTime >= $startDate) &&
                (!$endDate || $eventDateTime <= $endDate)
            ) {
                $reply = $event->getReply();
                $second = $eventDateTime->format('Y-m-d H:i:s'); // Get the second part of the date

                // Check if this second has already been counted
                if (!in_array($second, $uniqueSeconds)) {
                    if ($reply === 'Access-Accept') {
                        $authsCounts['Accepted']++;
                    } elseif ($reply === 'Access-Reject') {
                        $authsCounts['Rejected']++;
                    }

                    // Add the second to the list of counted seconds
                    $uniqueSeconds[] = $second;
                }
            }
        }

        // Return an array containing both the generated datasets and the counts
        return $this->generateDatasetsAuths($authsCounts);
    }

    /**
     * @throws Exception
     */
    private function fetchChartRealmsFreeradius(?DateTime $startDate, ?DateTime $endDate): array
    {
        // Fetch all data with date filtering
        $events = $this->radiusAccountingRepository->findDistinctRealms($startDate, $endDate);

        // Initialize an array to store the counts of each realm
        $realmCounts = [];

        // Count the occurrences of each realm
        foreach ($events as $event) {
            $realm = $event['realm'];

            // Skip if realm is null or empty
            if (!$realm) {
                continue;
            }

            // Increment the count for the realm
            if (!isset($realmCounts[$realm])) {
                $realmCounts[$realm] = 1;
            } else {
                $realmCounts[$realm]++;
            }
        }

        // Sort the realm counts in descending order
        arsort($realmCounts);

        // Return the counts of each realm
        return $this->generateDatasetsRealmsCounting($realmCounts);
    }

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

    private function fetchChartTrafficFreeradius(?DateTime $startDate, ?DateTime $endDate): array
    {
        // Get the traffic using the findTrafficPerRealm query
        $trafficData = $this->radiusAccountingRepository->findTrafficPerRealm($startDate, $endDate)->getResult();

        // Convert the results into the expected format
        $realmTraffic = [];
        foreach ($trafficData as $data) {
            $realm = $data['realm'];
            // Conver the data to GigaBytes
            $totalInput = number_format($data['total_input'] / (1024 * 1024 * 1024), 1);
            $totalOutput = number_format($data['total_output'] / (1024 * 1024 * 1024), 1);

            // Sum the total input and output for each realm
            $realmTraffic[$realm] = [
                'total_input' => $totalInput,
                'total_output' => $totalOutput,
            ];
        }

        // Return the sums traffic of each realm
        return $this->generateDatasetsRealmsTraffic($realmTraffic);
    }

    private function fetchChartSessionTimeFreeradius(?DateTime $startDate, ?DateTime $endDate): array
    {
        $events = $this->radiusAccountingRepository->findSessionTimeRealms($startDate, $endDate);

        $realmSessionTotalTime = [];
        $realmSessionCount = [];

        // Sum the session time and count the sessions
        foreach ($events as $event) {
            $realm = $event['realm'];
            $sessionTime = $event['acctSessionTime'];

            // Add the session time to the total
            if (!isset($realmSessionTotalTime[$realm])) {
                $realmSessionTotalTime[$realm] = $sessionTime;
                $realmSessionCount[$realm] = 1;
            } else {
                $realmSessionTotalTime[$realm] += $sessionTime; // Update total session time
                $realmSessionCount[$realm]++;
            }
        }

        // Calculate the average session time and return both total and average session time
        $result = [];
        foreach ($realmSessionTotalTime as $realm => $totalSessionTime) {
            $count = $realmSessionCount[$realm];
            $averageSessionTime = $count > 0 ? $totalSessionTime / $count : 0;
            $result[] = [
                'realm' => $realm,
                'totalSessionTime' => $totalSessionTime,
                'averageSessionTime' => $averageSessionTime
            ];
        }

        return $result;
    }

    private function generateDatasets(array $counts): array
    {
        $datasets = [];
        $labels = array_keys($counts);
        $dataValues = array_values($counts);

        $data = [];
        $colors = [];

        if (!empty(array_filter($dataValues, static fn($value) => $value !== 0))) {
            foreach ($labels as $index => $type) {
                $brightness = round(($dataValues[$index] / max($dataValues)) * 99); // Calculate brightness relative to the max count
                $data[] = $dataValues[$index];
                $colors[] = "rgba(78, 164, 116, .{$brightness})"; // Generate a different color for each data point
            }
        }

        $datasets[] = [
            'data' => $data,
            'backgroundColor' => $colors,
            'borderColor' => "rgb(78, 164, 116)",
            'borderRadius' => "15",
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    private function generateDatasetsAuths(array $counts): array
    {
        $datasets = [];
        $labels = array_keys($counts);
        $dataValues = array_values($counts);

        $colors = [];

        // Determine the color for each data point based on the type
        foreach ($labels as $type) {
            $color = $type === 'Accepted' ? '#7DB928' : '#FE4068';
            $colors[] = $color;
        }

        $datasets[] = [
            'data' => $dataValues,
            'backgroundColor' => $colors,
            'borderRadius' => "15",
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

        // Assign a specific color to the first realm
        $colors[] = '#7DB928';

        foreach ($trafficData as $realm => $traffic) {
            $labels[] = $realm;
            $dataValuesInput[] = $traffic['total_input'];
            $dataValuesOutput[] = $traffic['total_output'];
            $colors[] = $this->generateColorFromRealmName($realm); // Generate color based on realm name
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
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/customize', name: 'admin_dashboard_customize')]
    #[IsGranted('ROLE_ADMIN')]
    public function customize(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

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
                if (in_array($settingName, ['WELCOME_TEXT', 'PAGE_TITLE', 'WELCOME_DESCRIPTION', 'ADDITIONAL_LABEL', 'CONTACT_EMAIL'])) {
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
                        $destinationDirectory = $this->getParameter('kernel.project_dir') . '/public/resources/uploaded/';

                        $file->move($destinationDirectory, $newFilename);
                        $setting->setValue('/resources/uploaded/' . $newFilename);
                    }
                    // PLS MAKE SURE TO USE THIS COMMAND ON THE WEB CONTAINER chown -R www-data:www-data /var/www/openroaming/public/resources/uploaded/
                }
            }

            $this->addFlash('success_admin', 'Customization settings have been updated successfully.');

            // Flush the changes to the database
            $em->flush();

            return $this->redirectToRoute('admin_dashboard_customize');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'settings' => $settings,
            'form' => $form->createView(),
            'data' => $data,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
        ]);
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
