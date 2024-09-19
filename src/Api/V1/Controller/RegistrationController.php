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
use App\Service\CaptchaValidator;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SendSMS;
use App\Service\VerificationCodeGenerator;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class RegistrationController extends AbstractController
{
    private UserRepository $userRepository;
    private UserExternalAuthRepository $userExternalAuthRepository;
    private EventRepository $eventRepository;
    private EntityManagerInterface $entityManager;
    private EventActions $eventActions;
    private TokenStorageInterface $tokenStorage;
    private ParameterBagInterface $parameterBag;
    private SendSMS $sendSMSService;
    private GetSettings $getSettings;
    private SettingRepository $settingRepository;
    private UserPasswordHasherInterface $userPasswordHasher;
    private VerificationCodeGenerator $verificationCodeGenerator;
    private CaptchaValidator $captchaValidator;


    public function __construct(
        UserRepository $userRepository,
        UserExternalAuthRepository $userExternalAuthRepository,
        EventRepository $eventRepository,
        EntityManagerInterface $entityManager,
        EventActions $eventActions,
        TokenStorageInterface $tokenStorage,
        ParameterBagInterface $parameterBag,
        SendSMS $sendSMSService,
        GetSettings $getSettings,
        SettingRepository $settingRepository,
        UserPasswordHasherInterface $userPasswordHasher,
        VerificationCodeGenerator $verificationCodeGenerator,
        CaptchaValidator $captchaValidator,
    ) {
        $this->userRepository = $userRepository;
        $this->userExternalAuthRepository = $userExternalAuthRepository;
        $this->eventRepository = $eventRepository;
        $this->entityManager = $entityManager;
        $this->eventActions = $eventActions;
        $this->tokenStorage = $tokenStorage;
        $this->parameterBag = $parameterBag;
        $this->sendSMSService = $sendSMSService;
        $this->getSettings = $getSettings;
        $this->settingRepository = $settingRepository;
        $this->userPasswordHasher = $userPasswordHasher;
        $this->verificationCodeGenerator = $verificationCodeGenerator;
        $this->captchaValidator = $captchaValidator;
    }

    /**
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param MailerInterface $mailer
     * @param Request $request
     * @return JsonResponse
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws Exception
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route('/api/v1/auth/local/register', name: 'api_auth_local_register', methods: ['POST'])]
    public function localRegister(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['cf-turnstile-response'])) {
            return new JsonResponse(['error' => 'CAPTCHA validation failed!'], 400); # Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['cf-turnstile-response'], $request->getClientIp())) {
            return new JsonResponse(['error' => 'CAPTCHA validation failed!'], 400); # Bad Request Response
        }

        if (!isset($data['uuid'], $data['email'])) {
            return new JsonResponse(['error' => 'Missing data'], 400);
        }

        if ($data['uuid'] !== $data['email']) {
            return new JsonResponse([
                'error' => 'Invalid data!'
            ], 400);
        }

        if ($this->userRepository->findOneBy(['email' => $data['uuid']])) {
            return new JsonResponse(['error' => 'This User already exists'], 409);
        }

        $user = new User();
        $user->setUuid($data['uuid']);
        $user->setEmail($data['email']);
        $randomPassword = bin2hex(random_bytes(4));
        // Hash the password
        $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);
        $user->setPassword($hashedPassword);
        $user->setIsVerified(false);
        $user->setVerificationCode($this->verificationCodeGenerator->generateVerificationCode($user));
        $user->setFirstName($data['first_name'] ?? null);
        $user->setLastName($data['last_name'] ?? null);
        $user->setCreatedAt(new DateTime());

        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($user);
        $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT);
        $userExternalAuth->setProviderId(UserProvider::EMAIL);

        $this->entityManager->persist($user);
        $this->entityManager->persist($userExternalAuth);
        $this->entityManager->flush();

        // Defines the event to the table
        $eventMetaData = [
            'uuid' => $user->getUuid(),
            'provider' => UserProvider::PORTAL_ACCOUNT,
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
                'password' => $randomPassword,
            ]);

        $this->addFlash('success', 'We have sent an email with your account password and verification code');
        $mailer->send($email);

        return new JsonResponse(['message' => 'Local User Account Registered Successfully'], 200);
    }

    /**
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param MailerInterface $mailer
     * @param Request $request
     * @return JsonResponse
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws NonUniqueResultException
     * @throws Exception
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route('/api/v1/auth/local/reset', name: 'api_auth_local_reset', methods: ['POST'])]
    public function localReset(
        UserPasswordHasherInterface $userPasswordHasher,
        MailerInterface $mailer,
        Request $request
    ): JsonResponse {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['cf-turnstile-response'])) {
            return new JsonResponse(['error' => 'CAPTCHA validation failed!'], 400); # Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['cf-turnstile-response'], $request->getClientIp())) {
            return new JsonResponse(['error' => 'CAPTCHA validation failed!'], 400); # Bad Request Response
        }
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
                            'ip' => $request->getClientIp(),
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
                        ->subject('OpenRoaming Portal - Password Request')
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

            return new JsonResponse(['error' => 'Invalid credentials - Provider not allowed.'], 403);
        }

        return new JsonResponse(['error' => 'Please make sure to include the JWT token.'], 400);
    }

    /**
     * @param Request $request
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @return JsonResponse
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws Exception
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route('/api/v1/auth/sms/register', name: 'api_auth_sms_register', methods: ['POST'])]
    public function smsRegister(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['cf-turnstile-response'])) {
            return new JsonResponse(['error' => 'CAPTCHA validation failed!'], 400); # Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['cf-turnstile-response'], $request->getClientIp())) {
            return new JsonResponse(['error' => 'CAPTCHA validation failed!'], 400); # Bad Request Response
        }

        if (!isset($data['uuid'], $data['phoneNumber'])) {
            return new JsonResponse(['error' => 'Missing data!'], 400);
        }

        if ($data['uuid'] !== $data['phoneNumber']) {
            return new JsonResponse([
                'error' => 'Invalid data!'
            ], 400);
        }

        if ($this->userRepository->findOneBy(['phoneNumber' => $data['uuid']])) {
            // Error - Conflict user that already exists with the same phoneNumber
            return new JsonResponse(['error' => 'This User already exists'], 409);
        }

        $user = new User();
        $user->setUuid($data['uuid']);
        $user->setPhoneNumber($data['phoneNumber']);
        $randomPassword = bin2hex(random_bytes(4));
        // Hash the password
        $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);
        $user->setPassword($hashedPassword);
        $user->setIsVerified(false);
        $user->setVerificationCode($this->verificationCodeGenerator->generateVerificationCode($user));
        $user->setFirstName($data['first_name'] ?? null);
        $user->setLastName($data['last_name'] ?? null);
        $user->setCreatedAt(new DateTime());

        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($user);
        $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT);
        $userExternalAuth->setProviderId(UserProvider::PHONE_NUMBER);

        $this->entityManager->persist($user);
        $this->entityManager->persist($userExternalAuth);
        $this->entityManager->flush();

        // Defines the event to the table
        $eventMetaData = [
            'uuid' => $user->getUuid(),
            'provider' => UserProvider::PORTAL_ACCOUNT,
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
     * @param Request $request
     * @return JsonResponse
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws NonUniqueResultException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws Exception
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route('/api/v1/auth/sms/reset', name: 'api_auth_sms_reset', methods: ['POST'])]
    public function smsReset(Request $request): JsonResponse
    {
        $dataRequest = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($dataRequest['cf-turnstile-response'])) {
            return new JsonResponse(['error' => 'CAPTCHA validation failed!'], 400); # Bad Request Response
        }

        if (!$this->captchaValidator->validate($dataRequest['cf-turnstile-response'], $request->getClientIp())) {
            return new JsonResponse(['error' => 'CAPTCHA validation failed!'], 400); # Bad Request Response
        }

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
                    $hashedPassword = $this->userPasswordHasher->hashPassword($currentUser, $randomPassword);

                    // Retrieve the latest SMS attempt event for the user
                    $latestEvent = $this->eventRepository->findLatestSmsAttemptEvent($currentUser);

                    // Retrieve the SMS resend interval from the settings
                    $smsResendInterval = $data['SMS_TIMER_RESEND']['value']; // Interval in minutes
                    $minInterval = new DateInterval('PT' . $smsResendInterval . 'M');
                    $maxAttempts = 4;
                    $currentTime = new DateTime();

                    // Initialize attempts left
                    $attemptsLeft = $maxAttempts;

                    if ($latestEvent) {
                        $latestEventMetadata = $latestEvent->getEventMetadata();
                        $verificationAttempts = $latestEventMetadata['verificationAttempts'] ?? 0;
                        $lastVerificationCodeTime = isset($latestEventMetadata['lastVerificationCodeTime'])
                            ? new DateTime($latestEventMetadata['lastVerificationCodeTime'])
                            : null;

                        if ($lastVerificationCodeTime instanceof DateTime) {
                            $allowedTime = $lastVerificationCodeTime->add($minInterval);

                            if ($allowedTime > $currentTime) {
                                return new JsonResponse([
                                    'error' => 'Please wait '
                                        . $data['SMS_TIMER_RESEND']['value']
                                        . ' minute(s) before trying again.'
                                ], 429);
                            }

                            // Time interval has passed, update attempt count
                            $verificationAttempts++;
                            $attemptsLeft = $maxAttempts - $verificationAttempts;

                            if ($attemptsLeft <= 0) {
                                return new JsonResponse([
                                    'error' => 'Limit of trys exceeded for regeneration. Contact our support for help.'
                                ], 429);
                            }
                        } else {
                            // No previous attempt record found, set attemptsLeft correctly
                            $verificationAttempts = 1;
                            $attemptsLeft = $maxAttempts - $verificationAttempts;
                        }
                    } else {
                        // No previous event, this is the first attempt
                        $verificationAttempts = 1;
                        $attemptsLeft = $maxAttempts - $verificationAttempts;
                    }

                    // Update or create the event record
                    if (!$latestEvent) {
                        $latestEvent = new Event();
                        $latestEvent->setUser($currentUser);
                        $latestEvent->setEventDatetime($currentTime);
                        $latestEvent->setEventName(AnalyticalEventType::USER_SMS_ATTEMPT);
                    }

                    $eventMetadata = [
                        'ip' => $request->getClientIp(),
                        'uuid' => $currentUser->getUuid(),
                        'lastVerificationCodeTime' => $currentTime->format(DateTimeInterface::ATOM),
                        'verificationAttempts' => $verificationAttempts,
                    ];
                    $latestEvent->setEventMetadata($eventMetadata);

                    $this->eventActions->saveEvent(
                        $currentUser,
                        AnalyticalEventType::USER_SMS_ATTEMPT,
                        $currentTime,
                        $eventMetadata
                    );

                    // Set the hashed password for the user
                    $currentUser->setPassword($hashedPassword);
                    $this->entityManager->persist($currentUser);
                    $this->entityManager->flush();

                    // Send SMS
                    $message = "Your account password is: "
                        . $randomPassword
                        . "\n Verification code is: "
                        . $currentUser->getVerificationCode();

                    $result = $this->sendSMSService->sendSms($currentUser->getPhoneNumber(), $message);

                    if ($result) {
                        return new JsonResponse([
                            'success' => sprintf(
                                'We have sent a new code to: %s. You have %d attempt(s) left.',
                                $currentUser->getPhoneNumber(),
                                $attemptsLeft
                            )
                        ], 200);
                    }
                } catch (\RuntimeException $e) {
                    return new JsonResponse(['error' => $e->getMessage()], 500); // Internal Server Error
                }
            }

            return new JsonResponse(['error' => 'Invalid Credentials, Provider not allowed'], 400);
        }
        return new JsonResponse(['error' => 'Please make sure to place the JWT token'], 400);
    }
}
