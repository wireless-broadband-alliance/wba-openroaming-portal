<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use App\Form\RegistrationFormSMSType;
use App\Form\RegistrationFormType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SendSMS;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
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
    private UserRepository $userRepository;
    private SettingRepository $settingRepository;
    private GetSettings $getSettings;
    private ParameterBagInterface $parameterBag;
    private SendSMS $sendSMS;
    private TokenStorageInterface $tokenStorage;
    private EventActions $eventActions;

    /**
     * Registration constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     * @param ParameterBagInterface $parameterBag
     * @param SendSMS $sendSMS Calls the sendSMS service
     * @param TokenStorageInterface $tokenStorage Used to authenticate users after register with SMS
     * @param EventActions $eventActions Used to generate event related to the User creation
     */
    public function __construct(
        UserRepository $userRepository,
        SettingRepository $settingRepository,
        GetSettings $getSettings,
        ParameterBagInterface $parameterBag,
        SendSMS $sendSMS,
        TokenStorageInterface $tokenStorage,
        EventActions $eventActions
    ) {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
        $this->parameterBag = $parameterBag;
        $this->sendSMS = $sendSMS;
        $this->tokenStorage = $tokenStorage;
        $this->eventActions = $eventActions;
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

    /*
    * Handle the email registration.
    */
    /**
     * @param Request $request
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param EntityManagerInterface $entityManager
     * @param MailerInterface $mailer
     * @return Response
     * @throws RandomException
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
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
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->userRepository->findOneBy(['email' => $user->getEmail()])) {
                $this->addFlash(
                    'warning',
                    'User with the same email already exists, please try to Login using the link below.'
                );
            } elseif ($data['USER_VERIFICATION']['value'] === EmailConfirmationStrategy::EMAIL) {
                // Generate a random password
                $randomPassword = bin2hex(random_bytes(4));

                // Hash the password
                $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);

                // Set the hashed password for the user
                $user->setPassword($hashedPassword);
                $user->setUuid($user->getEmail());
                $user->setVerificationCode($this->generateVerificationCode($user)); // Set the verification code
                $user->setCreatedAt(new DateTime());
                $entityManager->persist($user);

                // Defines the Event to the table
                $eventMetaData = [
                    'platform' => PlatformMode::LIVE,
                    'uuid' => $user->getUuid(),
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'registrationType' => UserProvider::EMAIL,
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::USER_CREATION,
                    new DateTime(),
                    $eventMetaData
                );

                $emailSender = $this->parameterBag->get('app.email_address');
                $nameSender = $this->parameterBag->get('app.sender_name');

                // Send email to the user with the verification code
                $email = (new TemplatedEmail())
                    ->from(new Address($emailSender, $nameSender))
                    ->to($user->getEmail())
                    ->subject('Your OpenRoaming Registration Details')
                    ->htmlTemplate('email/user_password.html.twig')
                    ->context([
                        'uuid' => $user->getUuid(),
                        'verificationCode' => $user->getVerificationCode(),
                        'isNewUser' => true,
                        // This variable informs if the user it's new our if it's just a password reset request
                        'password' => $randomPassword,
                    ]);

                $this->addFlash('success', 'We have sent an email with your account password and verification code');
                $mailer->send($email);
            }
        }

        return $this->render('site/register_landing.html.twig', [
            'registrationForm' => $form->createView(),
            'data' => $data,
        ]);
    }

    /*
    * Handle the sms registration.
    */
    /**
     * @param Request $request
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param EntityManagerInterface $entityManager
     * @param SessionInterface $session
     * @return Response
     * @throws NonUniqueResultException
     * @throws RandomException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
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
        $form = $this->createForm(RegistrationFormSMSType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->userRepository->findOneBy(['phoneNumber' => $user->getPhoneNumber()])) {
                $this->addFlash(
                    'warning',
                    'User with the same phone number already exists, please try to Login using the link below.'
                );
            } else {
                // Generate a random password
                $randomPassword = bin2hex(random_bytes(4));

                // Hash the password
                $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);

                // Set the hashed password for the user
                $user->setPassword($hashedPassword);
                $user->setUuid($user->getPhoneNumber());
                $user->setVerificationCode($this->generateVerificationCode($user));
                $user->setCreatedAt(new DateTime());
                $entityManager->persist($user);

                // Defines the Event to the table
                $eventMetadata = [
                    'platform' => PlatformMode::LIVE,
                    'uuid' => $user->getUuid(),
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'registrationType' => UserProvider::PHONE_NUMBER,
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::USER_CREATION,
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
                    'We have sent a message to your phone with your password and verification code'
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

    /*
     * Handle the email link click to verify the user account.
     */
    /**
     * @param RequestStack $requestStack
     * @param UserRepository $userRepository
     * @param TokenStorageInterface $tokenStorage
     * @param EventDispatcherInterface $eventDispatcher
     * @return Response
     * @throws NonUniqueResultException
     */
    #[Route('/login/link', name: 'app_confirm_account')]
    public function confirmAccount(
        RequestStack $requestStack,
        UserRepository $userRepository,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        // Get the email and verification code from the URL query parameters
        $uuid = $requestStack->getCurrentRequest()->query->get('uuid');
        $verificationCode = $requestStack->getCurrentRequest()->query->get('verificationCode');

        // Get the user with the matching email, excluding admin users
        $user = $userRepository->findOneByUUIDExcludingAdmin($uuid);

        if ($user && $user->getVerificationCode() === $verificationCode) {
            try {
                // Create a token manually for the user
                $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

                // Set the token in the token storage
                $tokenStorage->setToken($token);

                // Dispatch the login event
                $request = $requestStack->getCurrentRequest();
                $event = new InteractiveLoginEvent($request, $token);
                $eventDispatcher->dispatch($event);

                // Update the verified status and save the user
                $user->setIsVerified(true);
                $userRepository->save($user, true);

                // Defines the Event to the table
                $eventMetadata = [
                    'platform' => PlatformMode::LIVE,
                    'uuid' => $user->getUuid(),
                    'ip' => $_SERVER['REMOTE_ADDR'],
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::USER_VERIFICATION,
                    new DateTime(),
                    $eventMetadata
                );

                $this->addFlash('success', 'Your account has been verified!');

                return $this->redirectToRoute('app_landing');
            } catch (CustomUserMessageAuthenticationException) {
                $this->addFlash('error', 'Authentication failed. Please try to log in manually.');
            }
        } else {
            // If the verification code is invalid or not found, display an error message and redirect to the login page
            $this->addFlash('error', 'Invalid verification code or link expired. Please try to log in manually');
        }

        return $this->redirectToRoute('app_login');
    }
}
