<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\OSTypes;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\PasswordAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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


class SiteController extends AbstractController
{
    private MailerInterface $mailer;
    private UserRepository $userRepository;

    public function __construct(MailerInterface $mailer, UserRepository $userRepository)
    {
        $this->mailer = $mailer; // to send and build the email
        $this->userRepository = $userRepository; // to call the User Repository and save the changes
    }

    #[Route('/', name: 'app_landing')]
    public function landing(Request $request, UserPasswordHasherInterface $userPasswordHasher, UserAuthenticatorInterface $userAuthenticator, PasswordAuthenticator $authenticator, EntityManagerInterface $entityManager, RequestStack $requestStack, SettingRepository $settingRepository): Response
    {
        //Branding
        $data['title'] = $settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
        $data['customerLogoName'] = $settingRepository->findOneBy(['name' => 'CUSTOMER_LOGO'])->getValue();
        $data['openroamingLogoName'] = $settingRepository->findOneBy(['name' => 'OPENROAMING_LOGO'])->getValue();
        $data['wallpaperImageName'] = $settingRepository->findOneBy(['name' => 'WALLPAPER_IMAGE'])->getValue();
        $data['welcomeText'] = $settingRepository->findOneBy(['name' => 'WELCOME_TEXT'])->getValue();
        $data['welcomeDescription'] = $settingRepository->findOneBy(['name' => 'WELCOME_DESCRIPTION'])->getValue();
        $data['contactEmail'] = $settingRepository->findOneBy(['name' => 'CONTACT_EMAIL'])->getValue();
        //Demo Mode
        $data['demoMode'] = $settingRepository->findOneBy(['name' => 'DEMO_MODE'])->getValue() === 'true';
        $data['demoModeWhiteLabel'] = $settingRepository->findOneBy(['name' => 'DEMO_WHITE_LABEL'])->getValue() === 'true';
        //Auth Providers
        //SAML
        $data['SAML_ENABLED'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_ENABLED'])->getValue() === 'true';
        $data['SAML_LABEL'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_LABEL'])->getValue();
        $data['SAML_DESCRIPTION'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_DESCRIPTION'])->getValue();
        //Legal Stuff
        $data['TOS_LINK'] = $settingRepository->findOneBy(['name' => 'TOS_LINK'])->getValue();
        $data['PRIVACY_POLICY_LINK'] = $settingRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK'])->getValue();

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
     */
    public function generateVerificationCode(User $user): int
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
     */
    public function createEmailCode(string $email, ?int $verificationCode = null): Email
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($verificationCode === null) {
            // If the verification code is not provided, generate a new one
            $verificationCode = $this->generateVerificationCode($currentUser);
        }

        $Email = 'openroaming@test_email.pt';
        $Name = 'OpenRoaming Testing Emails';

        return (new Email())
            ->from(new Address($Email, $Name))
            ->to($email)
            ->subject('Authentication Code')
            ->text('Your authentication code: ' . $verificationCode);
    }

    /**
     * Regenerate the verification code for the user and send a new email.
     *
     * @throws TransportExceptionInterface
     * @throws Exception
     *
     * @return RedirectResponse A redirect response.
     */
    #[Route('/email/regenerate', name: 'app_regenerate_email_code')]
    #[IsGranted('!ROLE_VERIFIED')]
    public function regenerateCode(): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isVerified = $currentUser->isVerified();

        if (!$isVerified) {
            // Regenerate the verification code for the user
            $newCode = $this->generateVerificationCode($currentUser);

            // Create the email message with the new verification code
            $message = $this->createEmailCode($currentUser->getEmail(), $newCode);

            // Send the email
            $this->mailer->send($message);
        }

        return $this->redirectToRoute('app_email_code');
    }


    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/email', name: 'app_email_code')]
    #[IsGranted('ROLE_USER')]
    public function sendCode(): Response
    {
        //gets the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isVerified = $currentUser->isVerified();
        // check if his verified
        if (!$isVerified) {
            // Create the email message with the verification code
            $message = $this->createEmailCode($currentUser->getEmail(), $currentUser->getVerificationCode());

            // Send the email
            $this->mailer->send($message);

            // Render the template with the verification code
            return $this->render('email_activation/index.html.twig', [
                'code' => $currentUser->getVerificationCode(),
                'incorrect_code' => null,
                'verified' => true
            ]);
        }

        // User is already verified, render the landing template
        return $this->render('site/landing.html.twig', ['verified' => true]);
    }


    #[Route('/email/check', name: 'app_check_email_code')]
    public function verifyCode(RequestStack $requestStack, UserRepository $userRepository): Response
    {
        // Get the entered code from the form
        $enteredCode = $requestStack->getCurrentRequest()->request->get('code');

        // Get the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Compare the entered code with the verification code
        $isCodeCorrect = $enteredCode === $currentUser->getVerificationCode();

        if ($isCodeCorrect) {
            // Set the user as verified
            $currentUser->setIsVerified(true);
            $currentUser->setRoles(['ROLE_VERIFIED']);
            $userRepository->save($currentUser, true);

            $this->addFlash('success', 'Your account is now successfully verified');
            // Render the landing template with the data layout
            return $this->render('site/landing.html.twig');

        }
        // Code is incorrect, display error message and redirect again to the check email page
        return $this->render('email_activation/index.html.twig', ['incorrect_code' => true]);
    }


}
