<?php

namespace App\Api\V1\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SendSMS;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RegistrationController extends AbstractController
{
    private UserRepository $userRepository;
    private UserExternalAuthRepository $userExternalAuthRepository;
    private EventRepository $eventRepository;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private EventActions $eventActions;
    private TokenStorageInterface $tokenStorage;
    private ParameterBagInterface $parameterBag;
    private SendSMS $sendSMSService;
    private GetSettings $getSettings;
    private SettingRepository $settingRepository;
    private UserPasswordHasherInterface $userPasswordHasher;


    public function __construct(
        UserRepository $userRepository,
        UserExternalAuthRepository $userExternalAuthRepository,
        EventRepository $eventRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        EventActions $eventActions,
        TokenStorageInterface $tokenStorage,
        ParameterBagInterface $parameterBag,
        SendSMS $sendSMSService,
        GetSettings $getSettings,
        SettingRepository $settingRepository,
        UserPasswordHasherInterface $userPasswordHasher,
    ) {
        $this->userRepository = $userRepository;
        $this->userExternalAuthRepository = $userExternalAuthRepository;
        $this->eventRepository = $eventRepository;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->eventActions = $eventActions;
        $this->tokenStorage = $tokenStorage;
        $this->parameterBag = $parameterBag;
        $this->sendSMSService = $sendSMSService;
        $this->getSettings = $getSettings;
        $this->settingRepository = $settingRepository;
        $this->userPasswordHasher = $userPasswordHasher;
    }

    /**
     * @throws Exception
     */
    #[Route('/api/v1/auth/local/register/', name: 'api_auth_local_register', methods: ['POST'])]
    public function localRegister(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['uuid'], $data['password'], $data['email'])) {
            return new JsonResponse(['error' => 'Invalid data'], 422);
        }

        if ($data['uuid'] !== $data['email']) {
            return new JsonResponse([
                'error' => 'Invalid data. 
            Make sure to type both with the same content!'
            ], 422);
        }

        if ($this->userRepository->findOneBy(['email' => $data['uuid']])) {
            return new JsonResponse(['error' => 'This User already exists'], 403);
        }

        $user = new User();
        $user->setUuid($data['uuid']);
        $user->setEmail($data['email']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setIsVerified($data['isVerified'] ?? false);
        $user->setVerificationCode($this->generateVerificationCode($user));
        $user->setFirstName($data['first_name'] ?? null);
        $user->setLastName($data['last_name'] ?? null);
        $user->setCreatedAt(new DateTime($data['createdAt']));

        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($user);
        $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT);
        $userExternalAuth->setProviderId(UserProvider::EMAIL);


        $this->entityManager->persist($user);
        $this->entityManager->persist($userExternalAuth);
        $this->entityManager->flush();

        // Defines the Event to the table
        $eventMetaData = [
            'uuid' => $user->getUuid(),
            'Provider' => UserProvider::PORTAL_ACCOUNT,
            'registrationType' => UserProvider::EMAIL,
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION,
            new DateTime(),
            $eventMetaData
        );

        return new JsonResponse(['message' => 'Local User Account Registered Successfully'], 200);
    }

    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    #[Route('/api/v1/auth/local/reset/', name: 'api_auth_local_reset', methods: ['POST'])]
    public function localReset(UserPasswordHasherInterface $userPasswordHasher, MailerInterface $mailer): JsonResponse
    {
        $token = $this->tokenStorage->getToken();

        // Check if the token is present and is of the correct type
        if ($token instanceof TokenInterface && $token->getUser() instanceof User) {
            /** @var User $currentUser */
            $currentUser = $token->getUser();

            // Check if the user has an external auth with PortalAccount and a valid email as providerId
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $currentUser]);
            $hasValidPortalAccount = false;

            foreach ($userExternalAuths as $auth) {
                if (
                    $auth->getProvider() === UserProvider::PORTAL_ACCOUNT && $auth->getProviderId(
                    ) === UserProvider::EMAIL
                ) {
                    $hasValidPortalAccount = true;
                    break;
                }
            }

            if ($hasValidPortalAccount) {
                $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent(
                    $currentUser,
                    AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST
                );
                $minInterval = new DateInterval('PT2M');
                $currentTime = new DateTime();
                $latestEventMetadata = $latestEvent ? $latestEvent->getEventMetadata() : [];
                $lastVerificationCodeTime = isset($latestEventMetadata['lastVerificationCodeTime'])
                    ? new DateTime($latestEventMetadata['lastVerificationCodeTime'])
                    : null;

                if (
                    !$latestEvent || ($lastVerificationCodeTime instanceof DateTime && $lastVerificationCodeTime->add(
                        $minInterval
                    ) < $currentTime)
                ) {
                    if (!$latestEvent) {
                        $latestEvent = new Event();
                        $latestEvent->setUser($currentUser);
                        $latestEvent->setEventDatetime(new DateTime());
                        $latestEvent->setEventName(AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST);
                        $latestEventMetadata = [
                            'platform' => PlatformMode::LIVE,
                            'ip' => $_SERVER['REMOTE_ADDR'],
                            'uuid' => $currentUser->getUuid(),
                        ];
                    }

                    $latestEventMetadata['lastVerificationCodeTime'] = $currentTime->format(DateTimeInterface::ATOM);
                    $latestEvent->setEventMetadata($latestEventMetadata);

                    $currentUser->setForgotPasswordRequest(true);
                    $this->eventRepository->save($latestEvent, true);

                    $randomPassword = bin2hex(random_bytes(4));
                    $hashedPassword = $userPasswordHasher->hashPassword($currentUser, $randomPassword);
                    $currentUser->setPassword($hashedPassword);
                    $this->entityManager->persist($currentUser);
                    $this->entityManager->flush();

                    $email = (new TemplatedEmail())
                        ->from(
                            new Address(
                                $this->parameterBag->get('app.email_address'),
                                $this->parameterBag->get('app.sender_name')
                            )
                        )
                        ->to($currentUser->getEmail())
                        ->subject('Your Openroaming - Password Request')
                        ->htmlTemplate('email/user_forgot_password_request.html.twig')
                        ->context([
                            'password' => $randomPassword,
                            'forgotPasswordUser' => true,
                            'uuid' => $currentUser->getUuid(),
                            'currentPassword' => $randomPassword,
                            'verificationCode' => $currentUser->getVerificationCode(),
                        ]);

                    $mailer->send($email);

                    return new JsonResponse(
                        ['message' => sprintf('We have sent you a new email to: %s.', $currentUser->getEmail())],
                        200
                    );
                }

                return new JsonResponse(['error' => 'Please wait 2 minutes before trying again.'], 429);
            }

            return new JsonResponse(['error' => 'Invalid credentials - Provider not allowed'], 403);
        }

        return new JsonResponse(['error' => 'Please make sure to place the JWT token'], 400);
    }

    /**
     * @throws Exception
     */
    #[Route('/api/v1/auth/sms/register/', name: 'api_auth_sms_register', methods: ['POST'])]
    public function smsRegister(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['uuid'], $data['password'], $data['phoneNumber'])) {
            return new JsonResponse(['error' => 'Invalid data. Make sure to set all the inputs!'], 422);
        }

        if ($data['uuid'] !== $data['phoneNumber']) {
            return new JsonResponse([
                'error' => 'Invalid data. 
            Make sure to type both with the same content!'
            ], 422);
        }

        if ($this->userRepository->findOneBy(['phoneNumber' => $data['uuid']])) {
            return new JsonResponse(['error' => 'This User already exists'], 403);
        }

        $user = new User();
        $user->setUuid($data['uuid']);
        $user->setPhoneNumber($data['phoneNumber']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setIsVerified($data['isVerified'] ?? false);
        $user->setVerificationCode($this->generateVerificationCode($user));
        $user->setFirstName($data['first_name'] ?? null);
        $user->setLastName($data['last_name'] ?? null);
        $user->setCreatedAt(new DateTime($data['createdAt']));

        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($user);
        $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT);
        $userExternalAuth->setProviderId(UserProvider::PHONE_NUMBER);


        $this->entityManager->persist($user);
        $this->entityManager->persist($userExternalAuth);
        $this->entityManager->flush();

        // Defines the Event to the table
        $eventMetaData = [
            'uuid' => $user->getUuid(),
            'Provider' => UserProvider::PORTAL_ACCOUNT,
            'registrationType' => UserProvider::PHONE_NUMBER,
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION,
            new DateTime(),
            $eventMetaData
        );

        return new JsonResponse(['message' => 'SMS User Account Registered Successfully'], 200);
    }


    /**
     * @throws Exception|\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route('/api/v1/auth/sms/reset/', name: 'api_auth_sms_reset', methods: ['POST'])]
    public function smsReset(): JsonResponse
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $token = $this->tokenStorage->getToken();

        // Check if the token is present and is of the correct type
        if ($token instanceof TokenInterface && $token->getUser() instanceof User) {
            /** @var User $currentUser */
            $currentUser = $token->getUser();

            // Check if the user has an external auth with PortalAccount and a valid phone number as providerId
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $currentUser]);
            $hasValidPortalAccount = false;

            foreach ($userExternalAuths as $auth) {
                if (
                    $auth->getProvider() === UserProvider::PORTAL_ACCOUNT &&
                    $auth->getProviderId() === UserProvider::PHONE_NUMBER
                ) {
                    $hasValidPortalAccount = true;
                    break;
                }
            }

            if ($hasValidPortalAccount) {
                try {
                    $randomPassword = bin2hex(random_bytes(4));
                    // Generate hashed password to be sent via SMS
                    $hashedPassword = $this->userPasswordHasher->hashPassword($currentUser, $randomPassword);

                    // Send SMS
                    $message = "Your account password is: "
                        . $randomPassword
                        . "%0AVerification code is: "
                        . $currentUser->getVerificationCode();

                    $result = $this->sendSMSService->sendSms($currentUser->getPhoneNumber(), $message);

                    if ($result) {
                        // If the service returns true, show the attempts left with a message
                        $latestEvent = $this->eventRepository->findLatestSmsAttemptEvent($currentUser);

                        if ($latestEvent) {
                            $latestEventMetadata = $latestEvent->getEventMetadata();
                            $verificationAttempts = $latestEventMetadata['verificationAttempts'] ?? 0;
                            $attemptsLeft = 3 - $verificationAttempts;
                            $currentUser->setPassword($hashedPassword);
                            $this->entityManager->persist($currentUser);
                            $this->entityManager->flush();

                            $message = sprintf(
                                'We have sent you a new code to: %s. You have %d attempt(s) left.',
                                $currentUser->getPhoneNumber(),
                                $attemptsLeft
                            );
                        }

                        $attempts = 1;
                        $currentTime = new DateTime();
                        // Defines the Event to the table
                        $eventMetadata = [
                            'platform' => PlatformMode::LIVE,
                            'ip' => $_SERVER['REMOTE_ADDR'],
                            'uuid' => $currentUser->getUuid(),
                            'verificationAttempts' => 0,
                            'lastVerificationCodeTime' => $currentTime->format(DateTimeInterface::ATOM)
                        ];

                        $this->eventActions->saveEvent(
                            $currentUser,
                            AnalyticalEventType::USER_SMS_ATTEMPT,
                            new DateTime(),
                            $eventMetadata
                        );
                    } else {
                        return new JsonResponse([
                            'error' => 'Failed to regenerate SMS code. Please, wait '
                                . $data['SMS_TIMER_RESEND']['value']
                                . ' minute(s) before generating a new code.'
                        ], 400);
                    }
                } catch (\RuntimeException $e) {
                    return new JsonResponse(['error' => $e->getMessage()], 400);
                }
            }
            return new JsonResponse(['error' => 'Invalid Credentials, Provider not allowed'], 400);
        }
        return new JsonResponse(['error' => 'Please make sure to place the JWT token'], 400);
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
}
