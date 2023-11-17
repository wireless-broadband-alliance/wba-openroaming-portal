<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Setting;
use App\Entity\User;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\PlatformMode;
use App\Form\authType;
use App\Form\CapportType;
use App\Form\CustomType;
use App\Form\LDAPType;
use App\Form\RadiusType;
use App\Form\ResetPasswordType;
use App\Form\SMSType;
use App\Form\StatusType;
use App\Form\TermsType;
use App\Form\UserUpdateType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use App\Service\ProfileManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
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

    /**
     * @param MailerInterface $mailer
     * @param UserRepository $userRepository
     * @param ProfileManager $profileManager
     * @param ParameterBagInterface $parameterBag
     * @param GetSettings $getSettings
     * @param SettingRepository $settingRepository
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        MailerInterface        $mailer,
        UserRepository         $userRepository,
        ProfileManager         $profileManager,
        ParameterBagInterface  $parameterBag,
        GetSettings            $getSettings,
        SettingRepository      $settingRepository,
        EntityManagerInterface $entityManager,
    )
    {
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
        $this->profileManager = $profileManager;
        $this->parameterBag = $parameterBag;
        $this->getSettings = $getSettings;
        $this->settingRepository = $settingRepository;
        $this->entityManager = $entityManager;
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

        // Fetch all users excluding admins
        $users = $userRepository->findExcludingAdmin();

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
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

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
        ]);
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

        // Use the updated searchWithFilter method to handle both filter and search term
        $users = $userRepository->searchWithFilter($filter, $searchTerm);

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
        if (!$currentUser->isVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

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
        ]);
    }

    /**
     * @param $id
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route('/dashboard/delete/{id<\d+>}', name: 'admin_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteUsers($id, EntityManagerInterface $em): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        if ($user->getDeletedAt() !== null) {
            $this->addFlash('error_admin', 'This user has already been deleted.');
            return $this->redirectToRoute('admin_page');
        }

        $email = $user->getEmail();
        foreach ($user->getEvent() as $event) {
            $em->remove($event);
        }
        $user->setDeletedAt(new DateTime());
        $this->disableProfiles($user);

        $em->persist($user);
        $em->flush();

        $this->addFlash('success_admin', sprintf('User with the email "%s" deleted successfully.', $email));
        return $this->redirectToRoute('admin_page');
    }


    /**
     * @param User $user
     * @param Request $request
     * @param UserRepository $userRepository
     * @param UserPasswordHasherInterface $passwordHasher
     * @param $id
     * @param EntityManagerInterface $em
     * @param MailerInterface $mailer
     * @return Response
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/dashboard/edit/{id<\d+>}', name: 'admin_update')]
    #[IsGranted('ROLE_ADMIN')]
    public function editUsers(User $user, Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, $id, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

        if (!$user = $this->userRepository->find($id)) {
            throw new NotFoundHttpException('User not found');
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
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $verificationCode = $this->generateVerificationCode($user);
                // Removes the admin access until he confirms his new password
                $user->setVerificationCode($verificationCode);
                $user->setPassword($hashedPassword);
                $user->setIsVerified(0);
                $em->persist($user);
                $em->flush();
                return $this->redirectToRoute('app_dashboard_regenerate_code_admin', ['type' => 'password']);
            }
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
            if ($type === 'password') {
                // Removes the admin access until he confirms his new password
                $currentUser->setIsVerified(1);
                $em->persist($currentUser);
                $em->flush();
                $this->addFlash('success_admin', 'Your password has been reseted successfully');
                return $this->redirectToRoute('admin_page');
            }

            if ($type === 'settingMain') {
                $command = 'php bin/console reset:mainSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                // Run the command
                $process->run();
                // Check if the command executed
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash('success_admin', 'The setting has been reseted successfully');
                return $this->redirectToRoute('admin_dashboard_settings');
            }

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
                $this->addFlash('success_admin', 'The setting has been reseted successfully');
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
                $this->addFlash('success_admin', 'The setting has been reseted successfully');
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
                $this->addFlash('success_admin', 'The Radius configurations has been reseted successfully');
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
                $this->addFlash('success_admin', 'The platform mode status has been reseted successfully');
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
                $this->addFlash('success_admin', 'The LDAP settings has been reseted successfully');
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
                $this->addFlash('success_admin', 'The CAPPORT settings has been reseted successfully');
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
                $this->addFlash('success_admin', 'The authentication settings has been reseted successfully');
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
                $this->addFlash('success_admin', 'The configuration SMS settings has been clear successfully');
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
        $isVerified = $currentUser->isVerified();

        if (!$isVerified && $type === 'password') {
            // Regenerate the verification code for the admin to reset password
            $email = $this->createEmailAdmin($currentUser->getEmail(), true);
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

        if ($type === 'settingMain') {
            // Regenerate the verification code for the admin to reset settings
            $email = $this->createEmailAdmin($currentUser->getEmail(), false);
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingMain']);
        }

        if ($type === 'settingCustom') {
            // Regenerate the verification code for the admin to reset settings
            $email = $this->createEmailAdmin($currentUser->getEmail(), false);
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingCustom']);
        }

        if ($type === 'settingTerms') {
            // Regenerate the verification code for the admin to reset settings
            $email = $this->createEmailAdmin($currentUser->getEmail(), false);
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingTerms']);
        }

        if ($type === 'settingRadius') {
            // Regenerate the verification code for the admin to reset settings
            $email = $this->createEmailAdmin($currentUser->getEmail(), false);
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingRadius']);
        }

        if ($type === 'settingStatus') {
            // Regenerate the verification code for the admin to reset settings
            $email = $this->createEmailAdmin($currentUser->getEmail(), false);
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingStatus']);
        }

        if ($type === 'settingLDAP') {
            // Regenerate the verification code for the admin to reset settings
            $email = $this->createEmailAdmin($currentUser->getEmail(), false);
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingLDAP']);
        }

        if ($type === 'settingCAPPORT') {
            // Regenerate the verification code for the admin to reset settings
            $email = $this->createEmailAdmin($currentUser->getEmail(), false);
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingCAPPORT']);
        }

        if ($type === 'settingAUTH') {
            // Regenerate the verification code for the admin to reset settings
            $email = $this->createEmailAdmin($currentUser->getEmail(), false);
            $this->mailer->send($email);
            $this->addFlash('success_admin', 'We have send to you a new code to: ' . $currentUser->getEmail());
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'settingAUTH']);
        }

        if ($type === 'settingSMS') {
            // Regenerate the verification code for the admin to reset settings
            $email = $this->createEmailAdmin($currentUser->getEmail(), false);
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
    protected function createEmailAdmin(string $email, bool $password): Email
    {
        // Get the values from the services.yaml file using $parameterBag on the __construct
        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        // If the verification code is not provided, generate a new one
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $verificationCode = $this->generateVerificationCode($currentUser);

        if ($password) {
            return (new TemplatedEmail())
                ->from(new Address($emailSender, $nameSender))
                ->to($email)
                ->subject('Your Password Reset Details')
                ->htmlTemplate('email_activation/email_template_admin.html.twig')
                ->context([
                    'verificationCode' => $verificationCode,
                    'resetPassword' => true
                ]);
        }
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
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

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
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

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
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

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

            // Update the 'PLATFORM_MODE' and 'USER_VERIFICATION' settings
            $platformMode = $submittedData['PLATFORM_MODE'] ?? null;
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
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

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
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(authType::class, null, [
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
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

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
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

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

        return $this->render('admin/statistics.html.twig', [
            'data' => $data,
            'current_user' => $user,
            'devicesDataJson' => json_encode($fetchChartDevices, JSON_THROW_ON_ERROR),
            'authenticationDataJson' => json_encode($fetchChartAuthentication, JSON_THROW_ON_ERROR),
            'platformStatusDataJson' => json_encode($fetchChartPlatformStatus, JSON_THROW_ON_ERROR),
            'usersVerifiedDataJson' => json_encode($fetchChartUserVerified, JSON_THROW_ON_ERROR),
            'selectedStartDate' => $startDate ? $startDate->format('Y-m-d\TH:i') : '',
            'selectedEndDate' => $endDate ? $endDate->format('Y-m-d\TH:i') : '',
        ]);
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
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

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
                if (in_array($settingName, ['WELCOME_TEXT', 'PAGE_TITLE', 'WELCOME_DESCRIPTION'])) {
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
