<?php

namespace App\Controller;

use App\Entity\Setting;
use App\Entity\User;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\PlatformMode;
use App\Form\CustomType;
use App\Form\ResetPasswordType;
use App\Form\SettingType;
use App\Form\UserUpdateType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use App\Service\ProfileManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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

    /**
     * @param MailerInterface $mailer
     * @param UserRepository $userRepository
     * @param ProfileManager $profileManager
     * @param ParameterBagInterface $parameterBag
     * @param GetSettings $getSettings
     * @param SettingRepository $settingRepository
     */
    public function __construct(
        MailerInterface       $mailer,
        UserRepository        $userRepository,
        ProfileManager        $profileManager,
        ParameterBagInterface $parameterBag,
        GetSettings           $getSettings,
        SettingRepository     $settingRepository,
    )
    {
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
        $this->profileManager = $profileManager;
        $this->parameterBag = $parameterBag;
        $this->getSettings = $getSettings;
        $this->settingRepository = $settingRepository;
    }

    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @return Response
     */
    #[Route('/dashboard', name: 'admin_page')]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboard(Request $request, UserRepository $userRepository): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $page = $request->query->getInt('page', 1); // Get the current page from the query parameter
        $perPage = 50; // Number of users to display per page

        // Fetch all users excluding admins
        $users = $userRepository->findExcludingAdmin();

        // Perform pagination manually
        $totalUsers = count($users); // Get the total number of users

        $totalPages = ceil($totalUsers / $perPage); // Calculate the total number of pages

        $offset = ($page - 1) * $perPage; // Calculate the offset for slicing the users

        $users = array_slice($users, $offset, $perPage); // Fetch the users for the current page

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
            'searchTerm' => null,
            'data' => $data
        ]);
    }

    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @return Response
     */
    #[Route('/dashboard/search', name: 'admin_search', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function searchUsers(Request $request, UserRepository $userRepository): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $searchTerm = $request->query->get('u');
        $page = $request->query->getInt('page', 1);
        $perPage = 25;

        $users = $userRepository->findExcludingAdminWithSearch($searchTerm);

        // Only let the user type more of 3 and less than 320 letters on the search bar
        if (empty($searchTerm) || strlen($searchTerm) < 3) {
            $this->addFlash('error_admin', 'Please enter at least 3 characters to search.');

            return $this->redirectToRoute('admin_page');
        }
        if (strlen($searchTerm) > 320) {
            $this->addFlash('error', 'Please enter a search term with fewer than 320 characters.');
            return $this->redirectToRoute('admin_page');
        }

        $totalUsers = count($users);

        $totalPages = ceil($totalUsers / $perPage);

        $offset = ($page - 1) * $perPage;

        $users = array_slice($users, $offset, $perPage);

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
            'searchTerm' => $searchTerm,
            'data' => $data
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
        $email = $user->getEmail();

        // Remove associated UserRadiusProfile entities
        foreach ($user->getUserRadiusProfiles() as $userRadiusProfile) {
            $em->remove($userRadiusProfile);
        }

        // Now, remove the user
        $em->remove($user);
        $em->flush();

        $this->addFlash('success_admin', sprintf('User with the email "%s" deleted successfully.', $email));
        return $this->redirectToRoute('admin_page');
    }


    /**
     * @param User $user
     * @param Request $request
     * @param UserRepository $userRepository
     * @return Response
     */
    #[Route('/dashboard/edit/{id<\d+>}', name: 'admin_update')]
    #[IsGranted('ROLE_ADMIN')]
    public function editUsers(User $user, Request $request, UserRepository $userRepository): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

        $form = $this->createForm(UserUpdateType::class, $user);

        // Store the initial bannedAt value before form submission
        $initialBannedAtValue = $user->getBannedAt();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();

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
            $this->addFlash('success_admin', sprintf('User with email "%s" updated successfully.', $email));

            return $this->redirectToRoute('admin_page');
        }

        return $this->render(
            'admin/edit.html.twig',
            [
                'form' => $form->createView(),
                'user' => $user
            ]
        );
    }


    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/dashboard/reset/{id<\d+>}', name: 'admin_reset_password')]
    #[IsGranted('ROLE_ADMIN')]
    public function resetPassword(Request $request, $id, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset', ['type' => 'password']);
        }

        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        if (!$user = $this->userRepository->find($id)) {
            throw new NotFoundHttpException('User not found');
        }

        $form = $this->createForm(ResetPasswordType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // get the typed password by the admin
            $newPassword = $form->get('password')->getData();
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
            $this->addFlash('success_admin', sprintf('User with the email "%s" has had their password reset successfully.', $user->getEmail()));
        }

        return $this->render('admin/reset_password.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
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

        return $this->render('admin/confirm_reset.html.twig', [
            'data' => $data,
            'type' => $type
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
            if ($type === 'settingMain') {
                $command = 'php bin/console reset:mainS --yes';
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
                $this->addFlash('success_admin', 'The setting has been rested successfully');
                return $this->redirectToRoute('admin_page');
            }

            if ($type === 'password') {
                // Removes the admin access until he confirms his new password
                $currentUser->setIsVerified(1);
                $em->persist($currentUser);
                $em->flush();
                $this->addFlash('success_admin', 'Your password has been rested successfully');
                return $this->redirectToRoute('admin_page');
            }

            if ($type === 'settingCustom') {
                $command = 'php bin/console reset:customS --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash('success_admin', 'The setting has been rested successfully');
                return $this->redirectToRoute('admin_page');
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
    #[Route('/dashboard/settings', name: 'admin_dashboard_settings')]
    #[IsGranted('ROLE_ADMIN')]
    public function settings(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser->IsVerified()) {
            $this->addFlash('error_admin', 'Your account is not verified. Please check your email.');
            return $this->redirectToRoute('admin_confirm_reset');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(SettingType::class, null, [
            'settings' => $settings, // Pass the settings data to the form
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $excludedSettings = [ // these are the settings related with the customization of the page
                'CUSTOMER_LOGO',
                'OPENROAMING_LOGO',
                'WALLPAPER_IMAGE',
                'PAGE_TITLE',
                'WELCOME_TEXT',
                'WELCOME_DESCRIPTION',
            ];

            foreach ($settings as $setting) {
                $name = $setting->getName();
                // Exclude the settings in $excludedSettings from being updated
                // Check if the submitted data contains the setting's name
                if (!in_array($name, $excludedSettings, true)) {
                    $value = $submittedData[$name] ?? null;
                    // Check if the setting is a text input
                    if ($value === null) {
                        $value = "";
                    }
                    if ($name === 'EMAIL_VERIFICATION' && $submittedData['PLATFORM_MODE'] === PlatformMode::Live) {
                        $value = EmailConfirmationStrategy::EMAIL;
                    }
                    $setting->setValue($value);
                    $em->persist($setting);
                }
            }

            $em->flush();

            $this->addFlash('success_admin', 'Settings updated successfully.');

            return $this->redirectToRoute('admin_page');
        }
        return $this->render('admin/settings.html.twig', [
            'settings' => $settings,
            'form' => $form->createView(),
            'data' => $data,
            'getSettings' => $getSettings,
        ]);
    }

    /**
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/reset', name: 'admin_dashboard_settings_reset')]
    #[IsGranted('ROLE_ADMIN')]
    public function settings_reset(GetSettings $getSettings): Response
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


        return $this->render('admin/settings.html.twig', [
            'data' => $data,
            'getSettings' => $getSettings,
        ]);
    }

    /*This route it's in development, again I need to fix and check for another stuff first
    #[Route('/dashboard/statistics', name: 'admin_dashboard_statistics')]
    #[IsGranted('ROLE_ADMIN')]
    public function statisticsData(EntityManagerInterface $em): JsonResponse
    {
        // Fetch data from your database, for example:
        $data = $em->getRepository(Event::class)->findAll(); // Adjust YourEntity

        $formattedData = [];
        dd($formattedData, $data);

        foreach ($data as $item) {
            $formattedData[] = [
                'label' => $item->getLabel(),
                'date' => $item->getDate()->format('Y-m-d'), // Adjust the date format as needed
                'count' => $item->getCount(),
            ];
        }
        return $this->json($formattedData);
    }

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

            return $this->redirectToRoute('admin_page');
        }

        return $this->render('admin/custom.html.twig', [
            'settings' => $settings,
            'form' => $form->createView(),
            'data' => $data,
            'getSettings' => $getSettings,
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
