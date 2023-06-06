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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;


class SiteController extends AbstractController
{

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


    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer; // to send and build the email
    }

    /**
     * @throws Exception
     */
    public function createEmailCode(): Email
    {
        // Get the current user and verification
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $email = $currentUser->getEmail();

        // Generate a random code with 6 digits
        $code = random_int(100000, 999999);

        // Generate the email with the code
        return (new Email())
            ->from(new Address('openroaming@test_email.pt', 'OpenRoaming Testing Emails'))
            ->to($email)
            ->subject('Authentication Code')
            ->text('Your authentication code: ' . $code);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/email', name: 'app_email_code')]
    public function sendCode(UserRepository $userRepository): Response
    {
        // Get the current user and verification
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isVerified = $currentUser->isVerified();

        // Check if the user is already verified
        if ($isVerified === false) {
            // Generate a random code with 6 digits
            $code = random_int(100000, 999999);

            // Get the current user
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            // Save the verification code on the db
            $currentUser->setVerificationCode($code);
            $userRepository->save($currentUser, true);
            // Store the code in the user's database record

            // Create the email message
            $message = $this->createEmailCode($code);

            // Send the email
            $this->mailer->send($message);

            // Render the template with the code
            return $this->render('email_activation/index.html.twig', ['code' => $code]);
        }
        return $this->render('email_activation/ola.html.twig');
    }


    #[Route('/email/check', name: 'app_check_email_code')]
    public function verifyCode(RequestStack $requestStack, UserRepository $userRepository): Response
    {
        // Get the entered code from the form
        $enteredCode = $requestStack->getCurrentRequest()->request->get('code');

        // Get the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Retrieve the verification code from the user entity
        $verificationCode = $currentUser->getVerificationCode();

        // Compare the entered code with the verification code
        $isCodeCorrect = $enteredCode === $verificationCode;

        if ($isCodeCorrect) {
            // Set the user as verified
            $currentUser->setIsVerified(true);
            $userRepository->save($currentUser, true);

            // Code is correct, display success message or perform further actions
            return $this->render('email_activation/success.html.twig', ['correct_code' => true]);
        }

        // Code is incorrect, display error message or redirect
        return $this->render('email_activation/fail.html.twig', ['incorrect_code' => true]);
    }


}
