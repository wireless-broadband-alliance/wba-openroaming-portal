<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
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
use App\Service\RegistrationEmailGenerator;
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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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
    private ParameterBagInterface $parameterBag;
    private SendSMS $sendSMSService;
    private GetSettings $getSettings;
    private SettingRepository $settingRepository;
    private UserPasswordHasherInterface $userPasswordHasher;
    private VerificationCodeGenerator $verificationCodeGenerator;
    private CaptchaValidator $captchaValidator;
    private RegistrationEmailGenerator $emailGenerator;
    private ValidatorInterface $validator;


    public function __construct(
        UserRepository $userRepository,
        UserExternalAuthRepository $userExternalAuthRepository,
        EventRepository $eventRepository,
        EntityManagerInterface $entityManager,
        EventActions $eventActions,
        ParameterBagInterface $parameterBag,
        SendSMS $sendSMSService,
        GetSettings $getSettings,
        SettingRepository $settingRepository,
        UserPasswordHasherInterface $userPasswordHasher,
        VerificationCodeGenerator $verificationCodeGenerator,
        CaptchaValidator $captchaValidator,
        RegistrationEmailGenerator $emailGenerator,
        ValidatorInterface $validator
    ) {
        $this->userRepository = $userRepository;
        $this->userExternalAuthRepository = $userExternalAuthRepository;
        $this->eventRepository = $eventRepository;
        $this->entityManager = $entityManager;
        $this->eventActions = $eventActions;
        $this->parameterBag = $parameterBag;
        $this->sendSMSService = $sendSMSService;
        $this->getSettings = $getSettings;
        $this->settingRepository = $settingRepository;
        $this->userPasswordHasher = $userPasswordHasher;
        $this->verificationCodeGenerator = $verificationCodeGenerator;
        $this->captchaValidator = $captchaValidator;
        $this->emailGenerator = $emailGenerator;
        $this->validator = $validator;
    }

    /**
     * @param Request $request
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @return JsonResponse
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/api/v1/auth/local/register', name: 'api_auth_local_register', methods: ['POST'])]
    public function localRegister(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return (new BaseResponse(400, null, 'Invalid JSON format'))->toResponse(); // Invalid Json
        }

        if (!isset($data['turnstile_token'])) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse(); // Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['turnstile_token'], $request->getClientIp())) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse(); // Bad Request Response
        }

        // Check for missing fields and add them to the array errors
        if (empty($data['email'])) {
            $errors[] = 'email';
        }
        if (empty($data['password'])) {
            $errors[] = 'password';
        }
        if (!empty($errors)) {
            return (
            new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )
            )->toResponse();
        }

        $emailConstraint = new Assert\Email();
        $emailConstraint->message = 'Invalid email format.';

        $emailViolations = $this->validator->validate($data['email'], $emailConstraint);

        if (count($emailViolations) > 0) {
            $errorMessage = $emailViolations[0]->getMessage();
            return (new BaseResponse(400, null, $errorMessage))->toResponse();
        }

        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return (new BaseResponse(409, null, 'This User already exists'))->toResponse();
        }

        $user = new User();
        $user->setUuid($data['email']);
        $user->setEmail($data['email']);
        $hashedPassword = $userPasswordHasher->hashPassword($user, $data['password']);
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

        $this->emailGenerator->sendRegistrationEmail($user, $data['password']);

        $eventMetaData = [
            'uuid' => $user->getEmail(),
            'provider' => UserProvider::PORTAL_ACCOUNT,
            'registrationType' => UserProvider::EMAIL,
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION,
            new DateTime(),
            $eventMetaData
        );

        return (new BaseResponse(
            200,
            ['message' => 'Registration successful. Please check your email for further instructions']
        ))->toResponse();
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
     * @throws NonUniqueResultException
     * @throws Exception
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws TransportExceptionInterface
     */
    #[Route('/api/v1/auth/local/reset', name: 'api_auth_local_reset', methods: ['POST'])]
    public function localReset(
        UserPasswordHasherInterface $userPasswordHasher,
        MailerInterface $mailer,
        Request $request,
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return (new BaseResponse(400, null, 'Invalid JSON format'))->toResponse(); // Invalid Json
        }

        if (empty($data['email'])) {
            $errors[] = 'email';
        }
        if (!empty($errors)) {
            return (
            new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            ))->toResponse();
        }

        if (!isset($data['turnstile_token'])) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse(); // Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['turnstile_token'], $request->getClientIp())) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse(); // Bad Request Response
        }

        $emailConstraint = new Assert\Email();
        $emailConstraint->message = 'Invalid email format.';

        $emailViolations = $this->validator->validate($data['email'], $emailConstraint);

        if (count($emailViolations) > 0) {
            $errorMessage = $emailViolations[0]->getMessage();
            return (new BaseResponse(400, null, $errorMessage))->toResponse();
        }

        $user = $this->userRepository->findOneBy(['email' => $data['email']]);

        if ($user) {
            if (!$user->isVerified()) {
                return (new BaseResponse(403, null, 'User account is not verified!'))->toResponse();
            }

            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);
            $hasValidPortalAccount = false;

            foreach ($userExternalAuths as $auth) {
                if (
                    $auth->getProvider() === UserProvider::PORTAL_ACCOUNT &&
                    $auth->getProviderId() === UserProvider::EMAIL
                ) {
                    $hasValidPortalAccount = true;
                    break;
                }
            }

            if ($hasValidPortalAccount) {
                $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent(
                    $user,
                    AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST
                );
                $minInterval = new DateInterval('PT2M');
                $currentTime = new DateTime();
                $latestEventMetadata = $latestEvent ? $latestEvent->getEventMetadata() : [];
                $lastVerificationCodeTime = isset($latestEventMetadata['lastVerificationCodeTime'])
                    ? new DateTime($latestEventMetadata['lastVerificationCodeTime'])
                    : null;

                // Check if enough time has passed since the last password reset request
                if (
                    !$latestEvent || ($lastVerificationCodeTime instanceof DateTime && $lastVerificationCodeTime->add(
                            $minInterval
                        ) < $currentTime)
                ) {
                    if (!$latestEvent) {
                        $latestEvent = new Event();
                        $latestEvent->setUser($user);
                        $latestEvent->setEventDatetime(new DateTime());
                        $latestEvent->setEventName(AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST);
                        $latestEventMetadata = [
                            'platform' => PlatformMode::LIVE,
                            'ip' => $request->getClientIp(),
                            'uuid' => $user->getUuid(),
                        ];
                    }

                    $latestEventMetadata['lastVerificationCodeTime'] = $currentTime->format(DateTimeInterface::ATOM);
                    $latestEvent->setEventMetadata($latestEventMetadata);

                    $this->eventRepository->save($latestEvent, true);

                    $randomPassword = bin2hex(random_bytes(4));
                    $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);
                    $user->setPassword($hashedPassword);
                    $user->setForgotPasswordRequest(true);
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    $email = (new TemplatedEmail())
                        ->from(
                            new Address(
                                $this->parameterBag->get('app.email_address'),
                                $this->parameterBag->get('app.sender_name')
                            )
                        )
                        ->to($user->getEmail())
                        ->subject('OpenRoaming Portal - Password Request')
                        ->htmlTemplate('email/user_forgot_password_request.html.twig')
                        ->context([
                            'password' => $randomPassword,
                            'forgotPasswordUser' => true,
                            'uuid' => $user->getUuid(),
                            'currentPassword' => $randomPassword,
                            'verificationCode' => $user->getVerificationCode(),
                        ]);

                    $mailer->send($email);

                    // Defines the Event to the table
                    $eventMetadata = [
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'uuid' => $user->getUuid(),
                    ];

                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::USER_ACCOUNT_PASSWORD_RESET_API,
                        new DateTime(),
                        $eventMetadata
                    );

                    return (new BaseResponse(200, [
                        'message' => sprintf('We have sent you a new email to: %s.', $user->getEmail())
                    ]))->toResponse();
                }

                return (new BaseResponse(429, null, 'Please wait 2 minutes before trying again.'))->toResponse(
                ); // Too Many Requests Response
            }
        }

        return (new BaseResponse(400, null, 'An error occurred while processing your request.'))->toResponse(
        ); // Bad Request Response
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws NonUniqueResultException
     * @throws Exception
     */
    #[Route('/api/v1/auth/sms/register', name: 'api_auth_sms_register', methods: ['POST'])]
    public function smsRegister(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return (new BaseResponse(400, null, 'Invalid JSON format'))->toResponse(); // Invalid Json
        }
        if (!isset($data['turnstile_token'])) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse(); // Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['turnstile_token'], $request->getClientIp())) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse(); // Bad Request Response
        }

        // Check for missing fields and add them to the array errors
        if (empty($data['phone_number'])) {
            $errors[] = 'phone_number';
        }
        if (empty($data['password'])) {
            $errors[] = 'password';
        }
        if (!empty($errors)) {
            return (
            new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            ))->toResponse();
        }

        if ($this->userRepository->findOneBy(['phoneNumber' => $data['phone_number']])) {
            return (new BaseResponse(409, null, 'This User already exists'))->toResponse(); // Conflict Response
        }

        $user = new User();
        $user->setUuid($data['phone_number']);
        $user->setPhoneNumber($data['phone_number']);
        // Hash the password
        $hashedPassword = $userPasswordHasher->hashPassword($user, $data['password']);
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

        // Save user creation event
        $eventMetaData = [
            'uuid' => $user->getPhoneNumber(),
            'provider' => UserProvider::PORTAL_ACCOUNT,
            'registrationType' => UserProvider::PHONE_NUMBER,
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION,
            new DateTime(),
            $eventMetaData
        );

        // Send SMS
        try {
            $message = "Your account password is: "
                . $data['password']
                . "%0A"
                . "Verification code is: "
                . $user->getVerificationCode();

            $result = $this->sendSMSService->sendSms($user->getPhoneNumber(), $message);

            if ($result) {
                return (new BaseResponse(200, [
                    // phpcs:disable Generic.Files.LineLength.TooLong
                    'message' => 'SMS User Account Registered Successfully. A verification code has been sent to your phone.'
                    // phpcs:enable
                ]))->toResponse();
            }
        } catch (\RuntimeException $e) {
            return (new BaseResponse(500, null, 'Failed to send SMS: ', [
                'details' => $e->getMessage()
            ]))->toResponse(); // Internal Server Error
        }

        // Return fallback response
        return (new BaseResponse(500, null, 'User registered but SMS could not be sent.'))->toResponse();
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
        try {
            $dataRequest = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return (new BaseResponse(400, null, 'Invalid JSON format'))->toResponse(); // Invalid Json
        }

        if (empty($dataRequest['phone_number'])) {
            $errors[] = 'phone_number';
        }
        if (!empty($errors)) {
            return (
            new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            ))->toResponse();
        }

        if (!isset($dataRequest['turnstile_token'])) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse(); // Bad Request Response
        }

        if (!$this->captchaValidator->validate($dataRequest['turnstile_token'], $request->getClientIp())) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse(); // Bad Request Response
        }

        $user = $this->userRepository->findOneBy(['phoneNumber' => $dataRequest['phone_number']]);

        if ($user) {
            if (!$user->isVerified()) {
                return (new BaseResponse(403, null, 'User account is not verified!'))->toResponse();
            }

            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);
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

            $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

            if ($hasValidPortalAccount) {
                try {
                    $randomPassword = bin2hex(random_bytes(4));
                    $hashedPassword = $this->userPasswordHasher->hashPassword($user, $randomPassword);

                    // Retrieve the latest SMS attempt event for the user
                    $latestEvent = $this->eventRepository->findLatestSmsAttemptEvent($user);
                    $smsResendInterval = $data['SMS_TIMER_RESEND']['value']; // Interval in minutes
                    $minInterval = new DateInterval('PT' . $smsResendInterval . 'M');
                    $maxAttempts = 4;
                    $currentTime = new DateTime();
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
                                return (new BaseResponse(
                                    429,
                                    null,
                                    sprintf(
                                        'Please wait %d minute(s) before trying again.',
                                        $data['SMS_TIMER_RESEND']['value']
                                    )
                                ))->toResponse();
                            }

                            $verificationAttempts++;
                            $attemptsLeft = $maxAttempts - $verificationAttempts;

                            if ($attemptsLeft <= 0) {
                                return (new BaseResponse(
                                    429,
                                    null,
                                    'Limit of tries exceeded for regeneration. Contact support.'
                                ))->toResponse();
                            }
                        } else {
                            $verificationAttempts = 1;
                            $attemptsLeft = $maxAttempts - $verificationAttempts;
                        }
                    } else {
                        $verificationAttempts = 1;
                        $attemptsLeft = $maxAttempts - $verificationAttempts;
                    }

                    // Update or create the event record
                    if (!$latestEvent) {
                        $latestEvent = new Event();
                        $latestEvent->setUser($user);
                        $latestEvent->setEventDatetime($currentTime);
                        $latestEvent->setEventName(AnalyticalEventType::USER_SMS_ATTEMPT);
                    }

                    $eventMetadata = [
                        'ip' => $request->getClientIp(),
                        'uuid' => $user->getUuid(),
                        'lastVerificationCodeTime' => $currentTime->format(DateTimeInterface::ATOM),
                        'verificationAttempts' => $verificationAttempts,
                    ];
                    $latestEvent->setEventMetadata($eventMetadata);

                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::USER_SMS_ATTEMPT,
                        $currentTime,
                        $eventMetadata
                    );

                    // Set the hashed password for the user
                    $user->setPassword($hashedPassword);
                    $user->setForgotPasswordRequest(true);
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    // Send SMS
                    $message = sprintf(
                        "Your account password is: %s\nVerification code is: %s",
                        $randomPassword,
                        $user->getVerificationCode()
                    );

                    $result = $this->sendSMSService->sendSms($user->getPhoneNumber(), $message);

                    // Defines the Event to the table
                    $eventMetadata = [
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'uuid' => $user->getUuid(),
                    ];

                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::USER_ACCOUNT_PASSWORD_RESET_API,
                        new DateTime(),
                        $eventMetadata
                    );

                    if ($result) {
                        return (new BaseResponse(200, [
                            'success' => sprintf(
                                'We have sent a new code to: %s. You have %d attempt(s) left.',
                                $user->getPhoneNumber(),
                                $attemptsLeft
                            )
                        ]))->toResponse();
                    }
                } catch (\RuntimeException $e) {
                    return (new BaseResponse(500, null, $e->getMessage()))->toResponse(); // Internal Server Error
                }
            }
        }
        return (new BaseResponse(400, null, 'An error occurred while processing your request'))->toResponse(
        ); // Bad Request Response
    }
}
