<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\OSTypes;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\PasswordAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
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

    /**
     * SiteController constructor.
     *
     * @param MailerInterface $mailer The mailer service used for sending emails.
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param ParameterBagInterface $parameterBag The parameter bag for accessing application configuration.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     */
    public function __construct(MailerInterface $mailer, UserRepository $userRepository, ParameterBagInterface $parameterBag, SettingRepository $settingRepository)
    {
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
        $this->parameterBag = $parameterBag;
        $this->settingRepository = $settingRepository;
    }

    private function getSettings(SettingRepository $settingRepository, Request $request, RequestStack $requestStack,): array
    {
        $data = [];

        // Branding
        $data['title'] = $settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
        $data['customerLogoName'] = $settingRepository->findOneBy(['name' => 'CUSTOMER_LOGO'])->getValue();
        $data['openroamingLogoName'] = $settingRepository->findOneBy(['name' => 'OPENROAMING_LOGO'])->getValue();
        $data['wallpaperImageName'] = $settingRepository->findOneBy(['name' => 'WALLPAPER_IMAGE'])->getValue();
        $data['welcomeText'] = $settingRepository->findOneBy(['name' => 'WELCOME_TEXT'])->getValue();
        $data['welcomeDescription'] = $settingRepository->findOneBy(['name' => 'WELCOME_DESCRIPTION'])->getValue();
        $data['contactEmail'] = $settingRepository->findOneBy(['name' => 'CONTACT_EMAIL'])->getValue();
        // Demo Mode
        $data['demoMode'] = $settingRepository->findOneBy(['name' => 'DEMO_MODE'])->getValue() === 'true';
        $data['demoModeWhiteLabel'] = $settingRepository->findOneBy(['name' => 'DEMO_WHITE_LABEL'])->getValue() === 'true';
        // Auth Providers
        // SAML
        $data['SAML_ENABLED'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_ENABLED'])->getValue() === 'true';
        $data['SAML_LABEL'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_LABEL'])->getValue();
        $data['SAML_DESCRIPTION'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_DESCRIPTION'])->getValue();
        // Legal Stuff
        $data['TOS_LINK'] = $settingRepository->findOneBy(['name' => 'TOS_LINK'])->getValue();
        $data['PRIVACY_POLICY_LINK'] = $settingRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK'])->getValue();
        /// Verification Form
        $data['VERIFICATION_FORM'] = false;
        ///
        $userAgent = $request->headers->get('User-Agent');
        $actionName = $requestStack->getCurrentRequest()->attributes->get('_route');
        $data['os'] = [
            'selected' => $payload['radio-os'] ?? $this->detectDevice($userAgent),
            'items' => [
                OSTypes::WINDOWS => ['alt' => 'Windows Logo'],
                OSTypes::IOS => ['alt' => 'Apple Logo'],
                OSTypes::ANDROID => ['alt' => 'Android Logo']
            ]
        ];

        return $data;
    }

    #[Route('/', name: 'app_landing')]
    public function landing(Request $request, UserPasswordHasherInterface $userPasswordHasher, UserAuthenticatorInterface $userAuthenticator, PasswordAuthenticator $authenticator, EntityManagerInterface $entityManager, RequestStack $requestStack, SettingRepository $settingRepository): Response
    {
        // Call the getSettings function to retrieve the data
        $data = $this->getSettings($this->settingRepository, $request, $requestStack);

        // Check if the user is logged in
        if ($this->getUser()) {
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $verification = $currentUser->isVerified();
            // Check if the user is verified
            if (!$verification) {
                $this->addFlash('error', 'Your account is not verified to download a profile!');
                return $this->redirectToRoute('app_email_code');
            }
        }

        ///
        $userAgent = $request->headers->get('User-Agent');
        $actionName = $requestStack->getCurrentRequest()->attributes->get('_route');
        if ($data['demoMode']) {
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
                    $user->setEmail($payload['email']);
                    $user->setPassword($userPasswordHasher->hashPassword($user, uniqid("", true)));
                    $user->setUuid(str_replace('@', "-DEMO-" . uniqid("", true) . "-", $user->getEmail()));

                    $entityManager->persist($user);
                    $entityManager->flush();
                    $userAuthenticator->authenticateUser(
                        $user,
                        $authenticator,
                        $request
                    );
                    return $this->redirectToRoute('app_email_code');
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
        return $this->render('site/landing.html.twig', $data);
    }

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
     * @param int|null $verificationCode The verification code to include in the email.
     * @return Email The email with the code.
     * @throws Exception
     */
    protected function createEmailCode(string $email, ?int $verificationCode = null): Email
    {
        // Get the values from the services.yaml file using $parameterBag on the __construct
        $Email = $this->parameterBag->get('app.email_address');
        $Name = $this->parameterBag->get('app.sender_name');

        if ($verificationCode === null) {
            // If the verification code is not provided, generate a new one
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $verificationCode = $this->generateVerificationCode($currentUser);
        }

        return (new Email())
            ->from(new Address($Email, $Name))
            ->to($email)
            ->subject('Your OpenRoaming Authentication Code is: ' . $verificationCode)
            ->text('Your authentication code: ' . $verificationCode);
    }


    /**
     * Send the verification code email to the user.
     *
     * @param string $email The user's email address.
     * @param int|null $verificationCode The verification code.
     * @return void
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    protected function sendEmail(string $email, ?int $verificationCode = null): void
    {
        if ($verificationCode === null) {
            // If the verification code is not provided, generate a new one
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $verificationCode = $this->generateVerificationCode($currentUser);
        }

        // Create the email message with the verification code
        $message = $this->createEmailCode($email, $verificationCode);

        // Send the email
        $this->mailer->send($message);
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
            $newCode = $this->generateVerificationCode($currentUser);
            $this->sendEmail($currentUser->getEmail(), $newCode);
        }
        $this->addFlash('success', 'We have send to you a new code to: ' . $currentUser->getEmail());
        return $this->redirectToRoute('app_email_code');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/email', name: 'app_email_code')]
    #[IsGranted('ROLE_USER')]
    public function sendCode(Request $request, RequestStack $requestStack): Response
    {
        // Call the getSettings function to retrieve the data
        $data = $this->getSettings($this->settingRepository, $request, $requestStack);

        // Modify the needed values
        $data['demoMode'] = false;
        $data['VERIFICATION_FORM'] = true;
        $data['BTS_DOWNLOAD'] = false;

        // Get the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser->isVerified()) {
            // Send the email with the verification code
            $this->sendEmail($currentUser->getEmail(), $currentUser->getVerificationCode());

            // Render the template with the verification code
            return $this->render('site/landing.html.twig', [
                'code' => $currentUser->getVerificationCode(),
                ...$data,
            ]);
        }

        // User is already verified, render the landing template
        return $this->redirectToRoute('app_landing');
    }


    #[Route('/email/check', name: 'app_check_email_code')]
    #[IsGranted('ROLE_USER')]
    public function verifyCode(RequestStack $requestStack, UserRepository $userRepository): Response
    {
        // Get the entered code from the form
        $enteredCode = $requestStack->getCurrentRequest()->request->get('code');

        // Get the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($enteredCode === $currentUser->getVerificationCode()) {
            // Set the user as verified
            $currentUser->setIsVerified(true);
            $userRepository->save($currentUser, true);
            $this->addFlash('success', 'Your account is now successfully verified');
            return $this->redirectToRoute('app_landing');
        }

        // Code is incorrect, display error message and redirect again to the check email page
        $this->addFlash('error', 'The verification code is incorrect. Please try again.');
        return $this->redirectToRoute('app_email_code');
    }


}
