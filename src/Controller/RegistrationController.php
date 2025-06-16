<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use App\Form\RegistrationFormSMSType;
use App\Form\RegistrationFormType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\RegistrationEmailGenerator;
use App\Service\SendSMS;
use App\Service\VerificationCodeEmailGenerator;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class RegistrationController extends AbstractController
{
    /**
     * Registration constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     * @param SendSMS $sendSMS Calls the sendSMS service
     * @param TokenStorageInterface $tokenStorage Used to authenticate users after register with SMS
     * @param EventActions $eventActions Used to generate event related to the User creation
     * @param RegistrationEmailGenerator $emailGenerator Used to generate and send emails for the user
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly GetSettings $getSettings,
        private readonly SendSMS $sendSMS,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EventActions $eventActions,
        private readonly RegistrationEmailGenerator $emailGenerator,
    ) {
    }

    /**
     * Handle the email registration.
     */
    /**
     * @throws RandomException
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value'] === true) {
            $this->addFlash(
                'error',
                'The portal is in Demo mode - it is not possible to use this authentication method.'
            );
            return $this->redirectToRoute('app_landing');
        }

        if ($data['EMAIL_REGISTER_ENABLED']['value'] !== true) {
            $this->addFlash('error', 'This authentication method it\'s not enabled!');
            return $this->redirectToRoute('app_landing');
        }

        $user = new User();
        $userAuths = new UserExternalAuth();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->userRepository->findOneBy(['email' => $user->getEmail()])) {
                $this->addFlash(
                    'warning',
                    'User with the same email already exists, please try to Login using the link below.'
                );
            } elseif ($data['USER_VERIFICATION']['value'] === OperationMode::ON->value) {
                // Generate a random password
                $randomPassword = bin2hex(random_bytes(4));

                // Hash the password
                $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);

                // Set the hashed password for the user
                $user->setPassword($hashedPassword);
                $user->setUuid($user->getEmail());
                $user->setVerificationCode(random_int(100000, 999999));
                $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
                $userAuths->setProviderId(UserProvider::EMAIL->value);
                $userAuths->setUser($user);
                $entityManager->persist($user);
                $entityManager->persist($userAuths);

                // Defines the Event to the table
                $eventMetaData = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'platform' => PlatformMode::LIVE->value,
                    'uuid' => $user->getUuid(),
                    'registrationType' => UserProvider::EMAIL->value,
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::USER_CREATION->value,
                    new DateTime(),
                    $eventMetaData
                );

                $this->emailGenerator->sendRegistrationEmail($user, $randomPassword);

                $this->addFlash(
                    'success',
                    'We have sent an email with your account password and verification code'
                );
            }
        }

        return $this->render('site/register_landing.html.twig', [
            'registrationForm' => $form->createView(),
            'data' => $data,
        ]);
    }

    /**
     * Handle the sms registration.
     */
    /**
     * @throws NonUniqueResultException
     * @throws RandomException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/register/sms', name: 'app_register_sms')]
    public function registerSMS(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value'] === true) {
            $this->addFlash(
                'error',
                'The portal is in Demo mode - it is not possible to use this authentication method.'
            );
            return $this->redirectToRoute('app_landing');
        }

        if ($data['AUTH_METHOD_SMS_REGISTER_ENABLED']['value'] !== true) {
            $this->addFlash('error', 'This authentication method is not enabled!');
            return $this->redirectToRoute('app_landing');
        }

        $user = new User();
        $userAuths = new UserExternalAuth();
        $form = $this->createForm(RegistrationFormSMSType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->userRepository->findOneBy(['phoneNumber' => $user->getPhoneNumber()])) {
                $this->addFlash(
                    'warning',
                    'User with the same phone number already exists, please try to "Log in" using the link below.'
                );
            } else {
                // Generate a random password
                $randomPassword = bin2hex(random_bytes(4));

                // Hash the password
                $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);

                // Set the hashed password for the user
                $user->setPassword($hashedPassword);

                if (!is_null($user->getPhoneNumber())) {
                    $user->setUuid(
                        "+" . $user->getPhoneNumber()->getCountryCode() . $user->getPhoneNumber()->getNationalNumber()
                    );
                }

                $user->setVerificationCode(random_int(100000, 999999));
                $user->setCreatedAt(new DateTime());
                $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
                $userAuths->setProviderId(UserProvider::PHONE_NUMBER->value);
                $userAuths->setUser($user);
                $entityManager->persist($user);
                $entityManager->persist($userAuths);

                // Defines the Event to the table
                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'platform' => PlatformMode::LIVE->value,
                    'uuid' => $user->getUuid(),
                    'registrationType' => UserProvider::PHONE_NUMBER->value,
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::USER_CREATION->value,
                    new DateTime(),
                    $eventMetadata
                );

                $verificationCode = $user->getVerificationCode();

                // Send SMS
                $message = "Your account password is: "
                    . $randomPassword
                    . "%0A"
                    . "Verification code is: "
                    . $verificationCode;
                $this->sendSMS->sendSms($user->getPhoneNumber(), $message);
                $this->addFlash(
                    'success',
                    'We have sent a message to your phone with your password and verification code' . $randomPassword
                );

                // Authenticate the user
                $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
                $this->tokenStorage->setToken($token);

                // Store the authentication token in the session
                $session->set('_security_main', serialize($token));

                // Redirect the user after successful registration
                return $this->redirectToRoute('app_landing');
            }
        }

        return $this->render('site/register_landing_sms.html.twig', [
            'registrationSMSForm' => $form->createView(),
            'data' => $data,
        ]);
    }

    /**
     * Handle the email link click to verify the user account.
     */
    /**
     * @throws NonUniqueResultException
     */
    #[Route('/login/link', name: 'app_confirm_account')]
    public function confirmAccount(
        Request $request,
        UserRepository $userRepository,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        // Get the email and verification code from the URL query parameters
        $uuid = $request->query->get('uuid');
        $verificationCode = $request->query->get('verificationCode');

        // Get the user with the matching email, excluding admin users
        $user = $userRepository->findOneByUUIDExcludingAdmin($uuid);

        // Check if the user has been previously verified
        if ($user && $user->isVerified() && !$user->isForgotPasswordRequest()) {
            $this->addFlash(
                'error',
                'This account is already verified.'
            );
            return $this->redirectToRoute('app_login', ['uuid' => $uuid]);
        }

        if ($user && $user->getVerificationCode() === $verificationCode) {
            try {
                // Create a token manually for the user
                $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

                // Set the token in the token storage
                $tokenStorage->setToken($token);

                // Dispatch the login event
                $event = new InteractiveLoginEvent($request, $token);
                $eventDispatcher->dispatch($event);

                // Update the verified status and save the user
                $user->setIsVerified(true);
                $userRepository->save($user, true);

                // Defines the Event to the table
                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'platform' => PlatformMode::LIVE->value,
                    'uuid' => $user->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::USER_VERIFICATION->value,
                    new DateTime(),
                    $eventMetadata
                );

                $this->addFlash(
                    'success',
                    'Your account has been verified!'
                );

                return $this->redirectToRoute('app_landing');
            } catch (CustomUserMessageAuthenticationException) {
                $this->addFlash(
                    'error',
                    'Authentication failed. Please try to log in manually.'
                );
            }
        } else {
            // If the verification code is invalid or not found, display an error message and redirect to the login page
            $this->addFlash(
                'error',
                'Invalid verification code or link expired. Please try to log in manually'
            );
        }

        return $this->redirectToRoute('app_login');
    }
}
