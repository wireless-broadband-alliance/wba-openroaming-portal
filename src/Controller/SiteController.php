<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\OSTypes;
use App\Enum\PlatformMode;
use App\Form\AccountUserUpdateLandingType;
use App\Form\ForgotPasswordEmailType;
use App\Form\ForgotPasswordSMSType;
use App\Form\NewPasswordAccountType;
use App\Form\RegistrationFormType;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\PasswordAuthenticator;
use App\Service\GetSettings;
use App\Service\SendSMS;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

/**
 * @method getParameterBag()
 */
class SiteController extends AbstractController
{
    private MailerInterface $mailer;
    private UserRepository $userRepository;
    private ParameterBagInterface $parameterBag;
    private SettingRepository $settingRepository;
    private GetSettings $getSettings;
    private EventRepository $eventRepository;

    /**
     * SiteController constructor.
     *
     * @param MailerInterface $mailer The mailer service used for sending emails.
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param ParameterBagInterface $parameterBag The parameter bag for accessing application configuration.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     * @param EventRepository $eventRepository The entity returns the last events data related to each user.
     */
    public function __construct(MailerInterface $mailer, UserRepository $userRepository, ParameterBagInterface $parameterBag, SettingRepository $settingRepository, GetSettings $getSettings, EventRepository $eventRepository)
    {
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
        $this->parameterBag = $parameterBag;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
        $this->eventRepository = $eventRepository;
    }

    /**
     * @param Request $request
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param UserAuthenticatorInterface $userAuthenticator
     * @param PasswordAuthenticator $authenticator
     * @param EntityManagerInterface $entityManager
     * @param RequestStack $requestStack
     * @return Response
     */
    #[Route('/', name: 'app_landing')]
    public function landing(Request $request, UserPasswordHasherInterface $userPasswordHasher, UserAuthenticatorInterface $userAuthenticator, PasswordAuthenticator $authenticator, EntityManagerInterface $entityManager, RequestStack $requestStack): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Check if the user is logged in and verification of the user
        // And Check if the user dont have a forgot_password_request active
        if (isset($data["USER_VERIFICATION"]["value"]) &&
            $data["USER_VERIFICATION"]["value"] === EmailConfirmationStrategy::EMAIL &&
            $this->getUser()) {
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $verification = $currentUser->isVerified();

            // Check if the user is verified
            if (!$verification) {
                $this->addFlash('error', 'Your account is not verified to download a profile!');
                return $this->redirectToRoute('app_email_code');
            }

            // Checks if the user has a "forgot_password_request", if yes, return to password reset form
            if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => true])) {
                $this->addFlash('error', 'You need to confirm the new password before download a profile!');
                return $this->redirectToRoute('app_site_forgot_password_checker');
            }
        }

        $userAgent = $request->headers->get('User-Agent');
        $actionName = $requestStack->getCurrentRequest()->attributes->get('_route');
        if ($data['PLATFORM_MODE']['value']) {
            if ($request->isMethod('POST')) {
                $payload = $request->request->all();
                if (empty($payload['radio-os']) && empty($payload['detected-os'])) {
                    $this->addFlash('error', 'Please select OS');
                } else if ($this->getUser() === null) {
                    $user = new User();
                    $event = new Event();
                    $form = $this->createForm(RegistrationFormType::class, $user);
                    $form->handleRequest($request);
                    if ($form->isSubmitted() && $form->isValid()) {
                        $user = $form->getData();

                        $user->setEmail($user->getEmail());
                        $user->setCreatedAt(new \DateTime());
                        $user->setPassword($userPasswordHasher->hashPassword($user, uniqid("", true)));
                        $user->setUuid(str_replace('@', "-DEMO-" . uniqid("", true) . "-", $user->getEmail()));
                        $entityManager->persist($user);

                        $event->setUser($user);
                        $event->setEventDatetime(new DateTime());
                        $event->setEventName(AnalyticalEventType::USER_CREATION);
                        $event->setEventMetadata([
                            'platform' => PlatformMode::Demo,
                            'sms' => false,
                        ]);
                        $entityManager->persist($event);
                        $entityManager->flush();
                        $userAuthenticator->authenticateUser(
                            $user,
                            $authenticator,
                            $request
                        );
                    }

                    if ($data["USER_VERIFICATION"]['value'] === EmailConfirmationStrategy::EMAIL) {
                        return $this->redirectToRoute('app_regenerate_email_code');
                    }
                    if ($data["USER_VERIFICATION"]['value'] === EmailConfirmationStrategy::NO_EMAIL) {
                        return $this->redirectToRoute('app_landing');
                    }
                }

                if (!array_key_exists('radio-os', $payload)) {
                    if (!array_key_exists('detected-os', $payload)) {
                        $os = $request->query->get('os');
                        if (!empty($os)) {
                            $payload['radio-os'] = $os;
                        } else {
                            return $this->redirectToRoute($actionName);
                        }
                    } else {
                        $payload['radio-os'] = $payload['detected-os'];
                    }

                }
                if ($this->getUser() !== null && $payload['radio-os'] !== 'none') {
                    /*
                     * Overriding macOS to iOS due to the profiles being the same and there being no route for the macOS
                     * enum value, so the UI shows macOS but on the logic to generate the profile iOS is used instead
                    */
                    if ($payload['radio-os'] === OSTypes::MACOS) {
                        $payload['radio-os'] = OSTypes::IOS;
                    }
                    return $this->redirectToRoute('profile_' . strtolower($payload['radio-os']), ['os' => $payload['radio-os']]);

                }
            }

        } else if ($request->isMethod('POST')) {
            $payload = $request->request->all();
            if (empty($payload['radio-os']) && empty($payload['detected-os'])) {
                $this->addFlash('error', 'Please select OS');
            }
            if (!array_key_exists('radio-os', $payload)) {
                if (!array_key_exists('detected-os', $payload)) {
                    $os = $request->query->get('os');
                    if (!empty($os)) {
                        $payload['radio-os'] = $os;
                    } else {
                        return $this->redirectToRoute($actionName);
                    }
                } else {
                    $payload['radio-os'] = $payload['detected-os'];
                }

            }
            if ($this->getUser() !== null && $payload['radio-os'] !== 'none') {
                /*
                    * Overriding macOS to iOS due to the profiles being the same and there being no route for the macOS
                    * enum value, so the UI shows macOS but on the logic to generate the profile iOS is used instead
                   */
                if ($payload['radio-os'] === OSTypes::MACOS) {
                    $payload['radio-os'] = OSTypes::IOS;
                }
                return $this->redirectToRoute('profile_' . strtolower($payload['radio-os']), ['os' => $payload['radio-os']]);
            }
        }

        $os = $request->query->get('os');
        if (!empty($os)) {
            $payload['radio-os'] = $os;
        }

        $data['os'] = [
            'selected' => $payload['radio-os'] ?? $this->detectDevice($userAgent),
            'items' => [
                OSTypes::WINDOWS => ['alt' => 'Windows Logo'],
                OSTypes::IOS => ['alt' => 'Apple Logo'],
                OSTypes::ANDROID => ['alt' => 'Android Logo']
            ]
        ];

        $form = $this->createForm(AccountUserUpdateLandingType::class, $this->getUser());
        $formPassword = $this->createForm(NewPasswordAccountType::class, $this->getUser());
        $formResgistrationDemo = $this->createForm(RegistrationFormType::class, $this->getUser());

        return $this->render('site/landing.html.twig', [
            'form' => $form->createView(),
            'formPassword' => $formPassword->createView(),
            'registrationFormDemo' => $formResgistrationDemo->createView(),
            'data' => $data
        ]);
    }


    /**
     * Widget with data about the account of the user / upload new password
     *
     * @return RedirectResponse
     * @throws Exception
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route('/account/user', name: 'app_site_account_user', methods: ['POST'])]
    public function accountUser(
        Request                     $request,
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        /** @var User $user */
        $user = $this->getUser();
        $oldFirstName = $user->getFirstName();
        $oldLastName = $user->getLastName();

        $form = $this->createForm(AccountUserUpdateLandingType::class, $this->getUser());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = new Event();

            $event->setUser($user);
            $event->setEventDatetime(new DateTime());
            $event->setEventName(AnalyticalEventType::USER_ACCOUNT_UPDATE);
            $event->setEventMetadata([
                'platform' => PlatformMode::Live,
                'Old data' => [
                    'First Name' => $oldFirstName,
                    'Last Name' => $oldLastName,
                ],
                'New data' => [
                    'First Name' => $user->getFirstName(),
                    'Last Name' => $user->getLastName(),
                ],
            ]);

            $em->persist($event);
            $em->persist($user);

            $em->flush();

            $this->addFlash('success', 'Your account information has been updated');

            // Redirect the user upon successful form submission
            return $this->redirectToRoute('app_landing');
        }

        $formPassword = $this->createForm(NewPasswordAccountType::class, $this->getUser());
        $formPassword->handleRequest($request);

        if ($formPassword->isSubmitted() && $formPassword->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $currentPasswordDB = $user->getPassword();
            $typedPassword = $formPassword->get('password')->getData();

            // Compare the typed password with the hashed password from the database
            if (!password_verify($typedPassword, $currentPasswordDB)) {
                $this->addFlash('error', 'Current password Invalid. Please try again.');
                return $this->redirectToRoute('app_landing');
            }

            if ($formPassword->get('newPassword')->getData() !== $formPassword->get('confirmPassword')->getData()) {
                $this->addFlash('error', 'Please make sure to type the same password on both fields. If the problem keep occurring contact our support!');
                return $this->redirectToRoute('app_landing');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $formPassword->get('newPassword')->getData()));
            $event = new Event();
            $event->setUser($user);
            $event->setEventDatetime(new DateTime());
            $event->setEventName(AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD);
            $event->setEventMetadata([
                'platform' => PlatformMode::Live,
            ]);

            $em->persist($event);
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Your password has been updated successfully!');
        }

        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/forgot-password/email', name: 'app_site_forgot_password_email')]
    public function forgotPasswordUserEmail(
        Request                     $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface      $entityManager,
        MailerInterface             $mailer
    ): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        if ($this->getUser()) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value'] === true) {
            $this->addFlash('error', 'The portal is in Demo mode - it is not possible to use this verification method.');
            return $this->redirectToRoute('app_landing');
        }

        if ($data['EMAIL_REGISTER_ENABLED']['value'] !== true) {
            $this->addFlash('error', 'This verification method it\'s not enabled!');
            return $this->redirectToRoute('app_landing');
        }

        $user = new User();
        $event = new Event();
        $form = $this->createForm(ForgotPasswordEmailType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->userRepository->findOneBy(['email' => $user->getEmail(), 'googleId' => null]);
            if ($user) {
                $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent($user, AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST);
                $minInterval = new DateInterval('PT2M');
                $currentTime = new DateTime();
                // Check if enough time has passed since the last attempt
                if (!$latestEvent || ($latestEvent->getLastVerificationCodeTime() instanceof DateTime &&
                        $latestEvent->getLastVerificationCodeTime()->add($minInterval) < $currentTime)) {

                    // Save event with attempt count and current time
                    if (!$latestEvent) {
                        $latestEvent = new Event();
                        $latestEvent->setUser($user);
                        $latestEvent->setEventDatetime(new DateTime());
                        $latestEvent->setEventName(AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST);
                        $latestEvent->setEventMetadata([
                            'platform' => PlatformMode::Live,
                            'email' => $user->getEmail(),
                        ]);
                    }
                    $latestEvent->setLastVerificationCodeTime($currentTime);
                    $user->setForgotPasswordRequest(true);
                    $this->eventRepository->save($latestEvent, true);

                    $randomPassword = bin2hex(random_bytes(4));
                    $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);
                    $user->setPassword($hashedPassword);
                    $entityManager->persist($user);
                    $entityManager->flush();

                    $email = (new TemplatedEmail())
                        ->from(new Address($this->parameterBag->get('app.email_address'), $this->parameterBag->get('app.sender_name')))
                        ->to($user->getEmail())
                        ->subject('Your Openroaming - Password Request')
                        ->htmlTemplate('email/user_forgot_password_request.html.twig')
                        ->context([
                            'password' => $randomPassword,
                            'forgotPasswordUser' => true,
                            'uuid' => $user->getUuid(),
                            'currentPassword' => $randomPassword,
                            'verificationCode' => $user->getVerificationCode(),
                        ]);

                    $mailer->send($email);

                    $message = sprintf('We have sent you a new email to: %s.', $user->getEmail());
                    $this->addFlash('success', $message);
                } else {
                    // Inform the user to wait before trying again
                    $this->addFlash('warning', 'Please wait 2 minutes before trying again.');
                }
            } else {
                $this->addFlash('warning', 'This email doesn\'t exist, please submit a valid email from the system! And make sure to only type emails from the platform and not from another providers.');
            }

        }
        return $this->render('site/forgot_password_email_landing.html.twig', [
            'forgotPasswordEmailForm' => $form->createView(),
            'data' => $data,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/forgot-password/sms', name: 'app_site_forgot_password_sms')]
    public function forgotPasswordUserSMS(
        Request                     $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface      $entityManager,
        RequestStack                $requestStack,
    ): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        if ($this->getUser()) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value'] === true) {
            $this->addFlash('error', 'The portal is in Demo mode - it is not possible to use this verification method.');
            return $this->redirectToRoute('app_landing');
        }

        if ($data['EMAIL_REGISTER_ENABLED']['value'] !== true) {
            $this->addFlash('error', 'This verification method it\'s not enabled!');
            return $this->redirectToRoute('app_landing');
        }

        $user = new User();
        $event = new Event();
        $form = $this->createForm(ForgotPasswordSMSType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->userRepository->findOneBy(['phoneNumber' => $user->getPhoneNumber()]);
            if ($user) {
                $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent($user, AnalyticalEventType::FORGOT_PASSWORD_SMS_REQUEST);
                $minInterval = new DateInterval('PT2M');
                $currentTime = new DateTime();
                // Check if the user has not exceeded the attempt limit
                if (!$latestEvent || $latestEvent->getVerificationAttempts() < 3) {
                    // Check if enough time has passed since the last attempt
                    if (!$latestEvent || ($latestEvent->getLastVerificationCodeTime() instanceof DateTime &&
                            $latestEvent->getLastVerificationCodeTime()->add($minInterval) < $currentTime)) {
                        // Increment the attempt count
                        $attempts = (!$latestEvent) ? 1 : $latestEvent->getVerificationAttempts() + 1;

                        // Save event with attempt count and current time
                        if (!$latestEvent) {
                            $latestEvent = new Event();
                            $latestEvent->setUser($user);
                            $latestEvent->setEventDatetime(new DateTime());
                            $latestEvent->setEventName(AnalyticalEventType::FORGOT_PASSWORD_SMS_REQUEST);
                            $latestEvent->setEventMetadata([
                                'platform' => PlatformMode::Live,
                                'phoneNumber' => $user->getPhoneNumber(),
                            ]);
                        }
                        $latestEvent->setVerificationAttempts($attempts);
                        $latestEvent->setLastVerificationCodeTime($currentTime);
                        $user->setForgotPasswordRequest(true);
                        $this->eventRepository->save($latestEvent, true);

                        // save new password hashed on the db for the user
                        $randomPassword = bin2hex(random_bytes(4));
                        $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);
                        $user->setPassword($hashedPassword);
                        $entityManager->persist($user);
                        $entityManager->flush();

                        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
                        $apiUrl = $this->parameterBag->get('app.budget_api_url');

                        // Fetch SMS credentials from the database
                        $username = $data['SMS_USERNAME']['value'];
                        $userId = $data['SMS_USER_ID']['value'];
                        $handle = $data['SMS_HANDLE']['value'];
                        $from = $data['SMS_FROM']['value'];
                        $recipient = $user->getPhoneNumber();

                        // Check if the user can get the SMS password and link
                        if ($user && $attempts < 3) {
                            $client = HttpClient::create();
                            $uuid = $user->getUuid();
                            $uuid = urlencode($uuid);
                            $verificationCode = $user->getVerificationCode();
                            $domainName = "/login/link/?uuid=$uuid&verificationCode=$verificationCode";
                            $message = "Current: " . $randomPassword . "\n" . "Please login: " . $requestStack->getCurrentRequest()->getSchemeAndHttpHost() . $domainName;
                            // Adjust the API endpoint and parameters based on the Budget SMS documentation
                            $apiUrl .= "?username=$username&userid=$userId&handle=$handle&to=$recipient&from=$from&msg=$message";
                            $response = $client->request('GET', $apiUrl);
                            // Handle the API response as needed
                            $statusCode = $response->getStatusCode();
                            $content = $response->getContent();
                        }
                        $attemptsLeft = 3 - $latestEvent->getVerificationAttempts();
                        $message = sprintf('We have sent you a message to: %s. You have %d attempt(s) left.', $user->getPhoneNumber(), $attemptsLeft);
                        $this->addFlash('success', $message);
                    } else {
                        // Inform the user to wait before trying again
                        $this->addFlash('warning', 'Please wait 2 minutes before trying again.');
                    }
                } else {
                    $this->addFlash('warning', 'You have exceed the limits for verification password. Please contact our support for help.');
                }
            } else {
                $this->addFlash('warning', 'This phone number doesn\'t exist, please submit a valid one from the system!');
            }
        }
        return $this->render('site/forgot_password_sms_landing.html.twig', [
            'forgotPasswordSMSForm' => $form->createView(),
            'data' => $data,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/forgot-password/checker', name: 'app_site_forgot_password_checker')]
    public function forgotPasswordUserChecker(
        Request                     $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface      $entityManager,
        MailerInterface             $mailer,
        UserPasswordHasherInterface $passwordHasher,
    ): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        if ($data['PLATFORM_MODE']['value'] == true) {
            $this->addFlash('error', 'The portal is in Demo mode - it is not possible to use this verification method!');
            return $this->redirectToRoute('app_landing');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            $this->addFlash('error', 'You must be logged in to access this page.');
            return $this->redirectToRoute('app_landing');
        }

        // Checks if the user has a "forgot_password_request", if don't, return to landing page
        if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => false])) {
            $this->addFlash('error', 'You can\'t access this page if you don\'t have a request!');
            return $this->redirectToRoute('app_landing');
        }

        $user = new User();
        $event = new Event();
        $form = $this->createForm(NewPasswordAccountType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $currentPasswordDB = $user->getPassword();
            $typedPassword = $form->get('password')->getData();

            // Compare the typed password with the hashed password from the database
            if (!password_verify($typedPassword, $currentPasswordDB)) {
                $this->addFlash('error', 'Current password Invalid. Please try again.');
                return $this->redirectToRoute('app_landing');
            }

            if ($form->get('newPassword')->getData() !== $form->get('confirmPassword')->getData()) {
                $this->addFlash('error', 'Please make sure to type the same password on both fields. If the problem keep occurring contact our support!');
                return $this->redirectToRoute('app_landing');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $form->get('newPassword')->getData()));
            $user->setForgotPasswordRequest(false);
            $event = new Event();
            $event->setUser($user);
            $event->setEventDatetime(new DateTime());
            $event->setEventName(AnalyticalEventType::FORGOT_PASSWORD_REQUEST_ACCEPTED);
            $event->setEventMetadata([
                'platform' => PlatformMode::Live,
            ]);

            $entityManager->persist($event);
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Your password has been updated successfully!');
            return $this->redirectToRoute('app_landing');
        }

        return $this->render('site/forgot_password_checker_landing.html.twig', [
            'forgotPasswordChecker' => $form->createView(),
            'data' => $data,
        ]);
    }


    /**
     * @param $userAgent
     * @return string
     */
    private
    function detectDevice($userAgent)
    {
        $os = OSTypes::NONE;

        // Windows
        if (preg_match('/windows|win32/i', $userAgent)) {
            $os = OSTypes::WINDOWS;
        }

        // macOS
        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $os = OSTypes::MACOS;
        }

        // iOS
        if (preg_match('/iphone|ipod|ipad/i', $userAgent)) {
            $os = OSTypes::IOS;
        }

        // Android
        if (preg_match('/android/i', $userAgent)) {
            $os = OSTypes::ANDROID;
        }

        // Linux
//        if (preg_match('/linux/i', $userAgent)) {
//            $os = OSTypes::LINUX;
//        }

        return $os;
    }

    /**
     * Generate a new verification code for the user.
     *
     * @param User $user The user for whom the verification code is generated.
     * @return int The generated verification code.
     * @throws Exception
     */
    protected
    function generateVerificationCode(User $user): int
    {
        // Generate a random verification code with 6 digits
        $verificationCode = random_int(100000, 999999);
        $user->setVerificationCode($verificationCode);
        $this->userRepository->save($user, true);

        return $verificationCode;
    }

    /**
     * Create an email message with the verification code.
     *
     * @param string $email The recipient's email address.
     * @return Email The email with the code.
     * @throws Exception
     */
    protected
    function createEmailCode(string $email): Email
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
            ->subject('Your OpenRoaming Authentication Code is: ' . $verificationCode)
            ->htmlTemplate('email/user_code.html.twig')
            ->context([
                'verificationCode' => $verificationCode,
            ]);
    }

    /**
     * Regenerate the verification code for the user and send a new email.
     *
     * @param EventRepository $eventRepository
     * @param MailerInterface $mailer
     * @return RedirectResponse A redirect response.
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    #[Route('/email/regenerate', name: 'app_regenerate_email_code')]
    #[IsGranted('ROLE_USER')]
    public function regenerateCode(EventRepository $eventRepository, MailerInterface $mailer): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isVerified = $currentUser->isVerified();

        if (!$isVerified) {
            $latestEvent = $eventRepository->findLatestRequestAttemptEvent($currentUser, AnalyticalEventType::USER_EMAIL_ATTEMPT);
            $minInterval = new DateInterval('PT2M');
            $currentTime = new DateTime();

            // Check if enough time has passed since the last attempt
            if (!$latestEvent || ($latestEvent->getLastVerificationCodeTime() instanceof DateTime &&
                    $latestEvent->getLastVerificationCodeTime()->add($minInterval) < $currentTime)) {

                // Increment the attempt count
                $attempts = (!$latestEvent) ? 1 : $latestEvent->getVerificationAttempts() + 1;

                $email = $this->createEmailCode($currentUser->getEmail());
                $mailer->send($email);

                // Save event with attempt count and current time
                if (!$latestEvent) {
                    $latestEvent = new Event();
                    $latestEvent->setUser($currentUser);
                    $latestEvent->setEventDatetime(new DateTime());
                    $latestEvent->setEventName(AnalyticalEventType::USER_EMAIL_ATTEMPT);
                    $latestEvent->setEventMetadata([
                        'platform' => PlatformMode::Live,
                        'email' => $currentUser->getEmail(),
                    ]);
                }

                $latestEvent->setVerificationAttempts($attempts);
                $latestEvent->setLastVerificationCodeTime($currentTime);
                $eventRepository->save($latestEvent, true);

                $message = sprintf('We have sent you a new code to: %s.', $currentUser->getEmail());
                $this->addFlash('success', $message);
            } else {
                // Inform the user to wait before trying again
                $this->addFlash('error', 'Please wait 2 minutes before trying again.');
            }
        }

        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws Exception
     */
    #[Route('/email', name: 'app_email_code')]
    #[IsGranted('ROLE_USER')]
    public function sendCode(): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Get the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            $this->addFlash('error', 'You must be logged in to access this page.');
            return $this->redirectToRoute('app_landing');
        }

        if (!$currentUser->isVerified()) {
            // Render the template with the verification code
            return $this->render('site/landing.html.twig', [
                'data' => $data,
            ]);
        }

        // User is already verified, render the landing template
        return $this->redirectToRoute('app_landing');
    }


    /**
     * @param RequestStack $requestStack
     * @param UserRepository $userRepository
     * @param EventRepository $eventRepository
     * @return Response
     */
    #[Route('/email/check', name: 'app_check_email_code')]
    #[IsGranted('ROLE_USER')]
    public function verifyCode(RequestStack $requestStack, UserRepository $userRepository, EventRepository $eventRepository): Response
    {
        // Get the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            $this->addFlash('error', 'You must be logged in to access this page.');
            return $this->redirectToRoute('app_landing');
        }

        // Checks if the user has a "forgot_password_request", if yes, return to password reset form
        if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => true])) {
            $this->addFlash('error', 'You need to confirm the new password before download a profile!');
            return $this->redirectToRoute('app_site_forgot_password_checker');
        }

        // Get the entered code from the form
        $enteredCode = $requestStack->getCurrentRequest()->request->get('code');

        if ($enteredCode === $currentUser->getVerificationCode()) {
            $event = new Event();
            // Set the user as verified
            $currentUser->setIsVerified(true);
            $userRepository->save($currentUser, true);

            $event->setUser($currentUser);
            $event->setEventDatetime(new DateTime());
            $event->setEventName(AnalyticalEventType::USER_VERIFICATION);
            $eventRepository->save($event, true);

            $this->addFlash('success', 'Your account is now successfully verified');
            return $this->redirectToRoute('app_landing');
        }

        // Code is incorrect, display error message and redirect again to the check email page
        $this->addFlash('error', 'The verification code is incorrect. Please try again.');
        return $this->redirectToRoute('app_email_code');
    }


    /**
     * Regenerate the verification code for the user and send a new SMS.
     *
     * @return RedirectResponse A redirect response.
     * @throws Exception
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route('/sms/regenerate', name: 'app_regenerate_sms_code')]
    #[IsGranted('ROLE_USER')]
    public function regenerateCodeSMS(EventRepository $eventRepository, SendSMS $sendSmsService): RedirectResponse
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            $this->addFlash('error', 'You must be logged in to access this page.');
            return $this->redirectToRoute('app_landing');
        }

        // Checks if the user has a "forgot_password_request", if yes, return to password reset form
        if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => true])) {
            $this->addFlash('error', 'You need to confirm the new password before download a profile!');
            return $this->redirectToRoute('app_site_forgot_password_checker');
        }

        try {
            $result = $sendSmsService->regenerateSmsCode($currentUser);

            if ($result) {
                // If he gets true from the service, show the attempts left with a message
                $latestEvent = $eventRepository->findLatestSmsAttemptEvent($currentUser);

                // Check if $latestEvent to avoid null conflicts
                if ($latestEvent) {
                    $attemptsLeft = 3 - $latestEvent->getVerificationAttempts();
                    $message = sprintf('We have sent you a new code to: %s. You have %d attempt(s) left.', $currentUser->getPhoneNumber(), $attemptsLeft);
                    $this->addFlash('success', $message);
                }
            } else {
                // If regeneration failed, show an appropriate error message
                $this->addFlash('error', 'Failed to regenerate SMS code. Please, wait ' . $data['SMS_TIMER_RESEND']['value'] . ' minute(s) before generating a new code.');
            }
        } catch (\RuntimeException $e) {
            // Handle generic exception and display a message to the user
            $this->addFlash('error', $e->getMessage());
        } catch (Exception) {
            // Handle exceptions thrown by the service (e.g., network issues, API errors)
            $this->addFlash('error', 'An error occurred while regenerating the SMS code. Please try again later.');
        }
        return $this->redirectToRoute('app_landing');
    }
}
