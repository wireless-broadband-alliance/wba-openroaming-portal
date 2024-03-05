<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\OSTypes;
use App\Enum\PlatformMode;
use App\Form\AccountUserUpdateLandingType;
use App\Form\NewPasswordSetupType;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\PasswordAuthenticator;
use App\Service\GetSettings;
use App\Service\SendSMS;
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

    /**
     * SiteController constructor.
     *
     * @param MailerInterface $mailer The mailer service used for sending emails.
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param ParameterBagInterface $parameterBag The parameter bag for accessing application configuration.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     */
    public function __construct(MailerInterface $mailer, UserRepository $userRepository, ParameterBagInterface $parameterBag, SettingRepository $settingRepository, GetSettings $getSettings)
    {
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
        $this->parameterBag = $parameterBag;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
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
        }

        $userAgent = $request->headers->get('User-Agent');
        $actionName = $requestStack->getCurrentRequest()->attributes->get('_route');
        if ($data['PLATFORM_MODE']['value']) {
            if ($request->isMethod('POST')) {
                $payload = $request->request->all();
                if (empty($payload['radio-os']) && empty($payload['detected-os'])) {
                    $this->addFlash('error', 'Please select OS');
                } else if (!$this->getUser() && (empty($payload['email']) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL))) {
                    $this->addFlash('error', 'Please a enter a valid email');
                } else if (!$this->getUser() && (empty($payload['terms']) || $payload['terms'] !== 'on')) {
                    $this->addFlash('error', 'Please agree to the Terms of Service');
                } else if ($this->getUser() === null) {
                    $user = new User();
                    $event = new Event();

                    $user->setEmail($payload['email']);
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
        $formPassword = $this->createForm(NewPasswordSetupType::class, $this->getUser());

        return $this->render('site/landing.html.twig', [
                'form' => $form->createView(),
                'formPassword' => $formPassword->createView()
            ] + $data);
    }


    /**
     * Account widget about setting of the user
     *
     * @return RedirectResponse
     * @throws Exception
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route('/account/user', name: 'app_site_account_user', methods: ['POST'])]
    public function accountUser(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $form = $this->createForm(AccountUserUpdateLandingType::class, $this->getUser());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Your account information has been updated');

            // Redirect the user upon successful form submission
            return $this->redirectToRoute('app_landing');
        }

        $formPassword = $this->createForm(NewPasswordSetupType::class, $this->getUser());
        $formPassword->handleRequest($request);

        if ($formPassword->isSubmitted()) {
            /** @var User $user */
            $user = $this->getUser();

            $currentPassword = $formPassword->get('password')->getData();
            $newPassword = $formPassword->get('newPassword')->getData();
            $confirmPassword = $formPassword->get('confirmPassword')->getData();

            // Check if the new password and confirm password match
            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Please make sure to type the same password on both fields!');
                return $this->redirectToRoute('app_landing');
            }

            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);

            $user->setPassword($hashedPassword);
            dd($currentPassword, $newPassword, $confirmPassword);

            $em->flush();

            $this->addFlash('success', 'Your password has been updated successfully!');
            return $this->redirectToRoute('app_landing');
        }

        dd('This form is not valid');
        return $this->redirectToRoute('app_landing');
    }


    /**
     * @param $userAgent
     * @return string
     */
    private function detectDevice($userAgent)
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
    protected function generateVerificationCode(User $user): int
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
    protected function createEmailCode(string $email): Email
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
            ->htmlTemplate('email_activation/email_template.html.twig')
            ->context([
                'verificationCode' => $verificationCode,
            ]);
    }

    /**
     * Regenerate the verification code for the user and send a new email.
     *
     * @return RedirectResponse A redirect response.
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    #[Route('/email/regenerate', name: 'app_regenerate_email_code')]
    #[IsGranted('ROLE_USER')]
    public function regenerateCode(): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isVerified = $currentUser->isVerified();

        if (!$isVerified) {
            // Regenerate the verification code for the user
            $email = $this->createEmailCode($currentUser->getEmail());
            $this->mailer->send($email);
        }
        $this->addFlash('success', 'We have send to you a new code to: ' . $currentUser->getEmail());
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
        if (!$currentUser->isVerified()) {
            // Render the template with the verification code
            return $this->render('site/landing.html.twig', [
                ...$data,
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
        // Get the entered code from the form
        $enteredCode = $requestStack->getCurrentRequest()->request->get('code');

        // Get the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();

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

        try {
            $result = $sendSmsService->regenerateSmsCode($currentUser);

            if ($result) {
                // If he gets true from the service, show the attempts left with a message
                $latestEvent = $eventRepository->findLatestSmsAttemptEvent($currentUser);

                // Check if $latestEvent to avoid null conflicts
                if ($latestEvent) {
                    $attemptsLeft = 3 - $latestEvent->getVerificationAttemptSms();
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
