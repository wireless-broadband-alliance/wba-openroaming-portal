<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\PlatformMode;
use App\Form\RegistrationFormSMSType;
use App\Form\RegistrationFormType;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class RegistrationController extends AbstractController
{
    private UserRepository $userRepository;
    private SettingRepository $settingRepository;
    private GetSettings $getSettings;
    private ParameterBagInterface $parameterBag;

    /**
     * SiteController constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     */
    public function __construct(UserRepository $userRepository, SettingRepository $settingRepository, GetSettings $getSettings, ParameterBagInterface $parameterBag)
    {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
        $this->parameterBag = $parameterBag;
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
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value'] === true) {
            $this->addFlash('error', 'This portal is in Demo mode. It is impossible use this authentication method.');
            return $this->redirectToRoute('app_landing');
        }

        if ($data['EMAIL_REGISTER_ENABLED']['value'] !== true) {
            $this->addFlash('error', 'This authentication method it\'s not enabled!');
            return $this->redirectToRoute('app_landing');
        }

        $user = new User();
        $event = new Event();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->userRepository->findOneBy(['email' => $user->getEmail()])) {
                $this->addFlash('warning', 'User with the same email already exists.');
            } else if ($data['EMAIL_VERIFICATION']['value'] === EmailConfirmationStrategy::EMAIL) {
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
                $event->setUser($user);
                $event->setEventDatetime(new DateTime());
                $event->setEventName(AnalyticalEventType::USER_CREATION);
                $event->setEventMetadata([
                    'platform' => PlatformMode::Live,
                ]);
                $entityManager->persist($event);
                $entityManager->flush();

                $emailSender = $this->parameterBag->get('app.email_address');
                $nameSender = $this->parameterBag->get('app.sender_name');

                // Send email to the user with the verification code
                $email = (new TemplatedEmail())
                    ->from(new Address($emailSender, $nameSender))
                    ->to($user->getEmail())
                    ->subject('Your OpenRoaming Registration Details')
                    ->htmlTemplate('email_activation/email_template_password.html.twig')
                    ->context([
                        'uuid' => $user->getUuid(),
                        'verificationCode' => $user->getVerificationCode(),
                        'isNewUser' => true, // This variable lets the template know if the user it's new our if it's just a password reset request
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

    /**
     * @throws Exception
     */
    #[Route('/register/sms', name: 'app_register_sms')]
    public function registerSMS(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value'] === true) {
            $this->addFlash('error', 'This portal is in Demo mode. It is impossible use this authentication method.');
            return $this->redirectToRoute('app_landing');
        }

        if ($data['AUTH_METHOD_SMS_REGISTER_ENABLED']['value'] !== true) {
            $this->addFlash('error', 'This authentication method it\'s not enabled!');
            return $this->redirectToRoute('app_landing');
        }

        $user = new User();
        $event = new Event();
        $form = $this->createForm(RegistrationFormSMSType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->userRepository->findOneBy(['phoneNumber' => $user->getPhoneNumber()])) {
                $this->addFlash('warning', 'User with the same phone number already exists.');
            } else {
                // Generate a random password
                $randomPassword = bin2hex(random_bytes(4));

                // Hash the password
                $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);

                // Set the hashed password for the user
                $user->setPassword($hashedPassword);
                $user->setUuid($user->getPhoneNumber());
                $user->setCreatedAt(new DateTime());
                $entityManager->persist($user);

                // Defines the Event to the table
                $event->setUser($user);
                $event->setEventDatetime(new DateTime());
                $event->setEventName(AnalyticalEventType::USER_CREATION);
                $event->setEventMetadata([
                    'platform' => PlatformMode::Live,
                    'sms' => true,
                ]);
                $entityManager->persist($event);
                $entityManager->flush();
                dd('Need to rework this register with Facha to send verification sms with codes :D');
                $this->addFlash('success', 'We have sent an message to your phone with your password and verification code');
            }
        }

        return $this->render('site/register_landing_sms.html.twig', [
            'registrationSMSForm' => $form->createView(),
            'data' => $data,
        ]);
    }

    /*
     * Handle the email link click to verify the user account.
     *
     * @param RequestStack $requestStack
     * @param UserRepository $userRepository
     * @return Response
     * @throws NonUniqueResultException
     */
    /**
     * @throws NonUniqueResultException
     */
    #[Route('/login/link', name: 'app_confirm_account')]
    public function confirmAccount(
        RequestStack             $requestStack,
        UserRepository           $userRepository,
        TokenStorageInterface    $tokenStorage,
        EventDispatcherInterface $eventDispatcher,
        EventRepository          $eventRepository
    ): Response
    {
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
                $event = new Event();
                $event->setUser($user);
                $event->setEventDatetime(new DateTime());
                $event->setEventName(AnalyticalEventType::USER_VERIFICATION);
                $eventRepository->save($event, true);

                $this->addFlash('success', 'Your account has been verified, please click below to download the profile!');

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
