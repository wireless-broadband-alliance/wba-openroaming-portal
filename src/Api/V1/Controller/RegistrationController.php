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
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Random\RandomException;
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
use Symfony\Component\Validator\Validation;
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
            return (new BaseResponse(
                200,
                ['message' => 'Registration successful. Please check your email for further instructions']
            ))->toResponse(); // False success for RGPD policies
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
            'ip' => $request->getClientIp(),
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
            if ($user->getBannedAt()) {
                // This message exists in case of a user is banned the email/reset, to protect against RGPD
                return (new BaseResponse(200, [
                    // Correct success response
                    'message' => sprintf(
                        'If the email exist, we have sent you a new one to: %s.', // Forbidden request
                        $user->getEmail()
                    )
                ]))->toResponse();
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
                    !$latestEvent ||
                    ($lastVerificationCodeTime
                        instanceof DateTime &&
                        $lastVerificationCodeTime->add(
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
                        'ip' => $request->getClientIp(),
                        'uuid' => $user->getUuid(),
                    ];

                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::USER_ACCOUNT_PASSWORD_RESET_API,
                        new DateTime(),
                        $eventMetadata
                    );

                    return (new BaseResponse(200, [
                        // Correct success response
                        'message' => sprintf(
                            'If the email exist, we have sent you a new one to: %s.', // Actually success
                            $user->getEmail()
                        )
                    ]))->toResponse();
                }
                // This message exists in case of a user spams the email/reset, to protect against RGPD
                return (new BaseResponse(200, [
                    // Correct success response
                    'message' => sprintf(
                        'If the email exist, we have sent you a new one to: %s.',
                        $user->getEmail()
                    )
                ]))->toResponse(); // To many requests
            }
        }

        // This message exists in case of a user spams the email/reset, to protect against RGPD
        return (new BaseResponse(
            200,
            sprintf('If the email exist, we have sent you a new one to: %s', $data['email']),
            null,
        ))->toResponse(); // Not Found User doesn't exist request
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
        UserPasswordHasherInterface $userPasswordHasher,
        PhoneNumberUtil $phoneNumberUtil
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return (new BaseResponse(400, null, 'Invalid JSON format'))->toResponse();
        }

        if (!isset($data['turnstile_token'])) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse();
        }

        if (!$this->captchaValidator->validate($data['turnstile_token'], $request->getClientIp())) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse();
        }

        // Check for missing fields and add them to the array errors
        $errors = [];
        if (empty($data['phone_number'])) {
            $errors[] = 'phone_number';
        }
        if (empty($data['password'])) {
            $errors[] = 'password';
        }
        if (empty($data['country_code'])) {
            $errors[] = 'country_code';
        }
        if (!empty($errors)) {
            return (new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            ))->toResponse();
        }

        // Validate phone number with country code
        try {
            $parsedPhoneNumber = $phoneNumberUtil->parse($data['phone_number'], strtoupper($data['country_code']));
            if ($parsedPhoneNumber && !$phoneNumberUtil->isValidNumber($parsedPhoneNumber)) {
                return (new BaseResponse(
                    400,
                    null,
                    'Invalid phone number format.'
                ))->toResponse();
            }
        } catch (NumberParseException $e) {
            return (new BaseResponse(
                400,
                null,
                'Invalid phone number format or country code.'
            ))->toResponse();
        }

        // Check for existing user with the same phone number
        $formattedPhoneNumber = $phoneNumberUtil->format($parsedPhoneNumber, PhoneNumberFormat::E164);
        if ($this->userRepository->findOneBy(['uuid' => $formattedPhoneNumber])) {
            return (new BaseResponse(200, [
                // phpcs:disable Generic.Files.LineLength.TooLong
                'message' => 'SMS User Account Registered Successfully. A verification code has been sent to your phone.'
                // phpcs:enable
            ]))->toResponse(); // False success for RGPD policies
        }

        // Create and populate the new user entity
        $user = new User();
        $user->setUuid($formattedPhoneNumber);  // Store formatted phone number in UUID field
        $user->setPhoneNumber($parsedPhoneNumber);  // Set the PhoneNumber object directly
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
            'uuid' => $user->getUuid(),
            'provider' => UserProvider::PORTAL_ACCOUNT,
            'registrationType' => UserProvider::PHONE_NUMBER,
            'ip' => $request->getClientIp(),
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION,
            new DateTime(),
            $eventMetaData
        );

        // Send SMS
        try {
            // phpcs:disable Generic.Files.LineLength.TooLong
            $message = "Your account password is: " . $data['password'] . "%0A" . "Verification code is: " . $user->getVerificationCode();
            // phpcs:enable
            $result = $this->sendSMSService->sendSms($user->getPhoneNumber(), $message);

            if ($result) {
                return (new BaseResponse(
                    200,
                    // phpcs:disable Generic.Files.LineLength.TooLong
                    ['message' => 'SMS User Account Registered Successfully. A verification code has been sent to your phone.']
                    // phpcs:enable
                ))->toResponse();
            }
        } catch (\RuntimeException) {
            return (new BaseResponse(500, null, 'Failed to send SMS'))->toResponse(); // Internal Server Error
        }

        // Return fallback response
        return (new BaseResponse(500, null, 'User registered but SMS could not be sent.'))->toResponse();
    }


    /**
     * @param Request $request
     * @param PhoneNumberUtil $phoneNumberUtil
     * @return JsonResponse
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws NonUniqueResultException
     * @throws RandomException
     * @throws RedirectionExceptionInterface
     * @throws \DateMalformedIntervalStringException
     * @throws \DateMalformedStringException
     */
    #[Route('/api/v1/auth/sms/reset', name: 'api_auth_sms_reset', methods: ['POST'])]
    public function smsReset(
        Request $request,
        PhoneNumberUtil $phoneNumberUtil
    ): JsonResponse {
        try {
            $dataRequest = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return (new BaseResponse(400, null, 'Invalid JSON format'))->toResponse(); // Invalid Json
        }
        if (!isset($dataRequest['turnstile_token'])) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse(); // Bad Request Response
        }

        if (!$this->captchaValidator->validate($dataRequest['turnstile_token'], $request->getClientIp())) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed!'))->toResponse(); // Bad Request Response
        }

        if (empty($dataRequest['country_code'])) {
            $errors[] = 'country_code';
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

        // Validate phone number with country code
        try {
            $parsedPhoneNumber = $phoneNumberUtil->parse(
                $dataRequest['phone_number'],
                strtoupper($dataRequest['country_code'])
            );
            if ($parsedPhoneNumber && !$phoneNumberUtil->isValidNumber($parsedPhoneNumber)) {
                return (new BaseResponse(
                    400,
                    null,
                    'Invalid phone number format.'
                ))->toResponse();
            }
        } catch (NumberParseException $e) {
            return (new BaseResponse(
                400,
                null,
                'Invalid phone number format or country code.'
            ))->toResponse();
        }

        // Check for existing user with the same phone number
        $formattedPhoneNumber = $phoneNumberUtil->format($parsedPhoneNumber, PhoneNumberFormat::E164);
        $user = $this->userRepository->findOneBy(['uuid' => $formattedPhoneNumber]);
        if ($user) {
            if ($user->getBannedAt()) {
                // This message exists in case of a user is banned the sms/reset, to protect against RGPD
                return (new BaseResponse(
                    200,
                    'If the phone number exist, we have sent you a new one to: %s',
                    $user->getEmail(),
                    null,
                ))->toResponse(); // Too Many Requests Response
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
                    $maxAttempts = 3;
                    $currentTime = new DateTime();

                    $verificationAttempts = 1;
                    if ($latestEvent) {
                        $latestEventMetadata = $latestEvent->getEventMetadata();
                        $verificationAttempts = $latestEventMetadata['verificationAttempts'] ?? 0;
                        $lastVerificationCodeTime = isset($latestEventMetadata['lastVerificationCodeTime'])
                            ? new DateTime($latestEventMetadata['lastVerificationCodeTime'])
                            : null;

                        if ($lastVerificationCodeTime instanceof DateTime) {
                            $allowedTime = (clone $lastVerificationCodeTime)->add($minInterval);

                            if ($allowedTime > $currentTime) {
                                // Protect against spam and RGPD policies - simulate success without actual resend
                                return (new BaseResponse(200, [
                                    'success' => sprintf(
                                        'If the phone number exists, we have sent a new code to: %s.',
                                        $user->getPhoneNumber()
                                    )
                                ]))->toResponse();
                            }

                            $verificationAttempts++;
                        }
                    }
                    $attemptsLeft = $maxAttempts - $verificationAttempts;
                    if ($attemptsLeft < 0) {
                        return (new BaseResponse(
                            429,
                            null,
                            'Limit of tries exceeded for regeneration. Contact support.'
                        ))->toResponse();
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
                        "Your account password is: %s\n Verification code is: %s",
                        $randomPassword,
                        $user->getVerificationCode()
                    );

                    $result = $this->sendSMSService->sendSms($user->getPhoneNumber(), $message);

                    // Defines the Event to the table
                    $eventMetadata = [
                        'ip' => $request->getClientIp(),
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
                                // phpcs:disable Generic.Files.LineLength.TooLong
                                'If the phone number exists, we have sent a new code to: %s. You have %d attempt(s) left.',
                                //phpcs:enable
                                $user->getUuid(),
                                $attemptsLeft
                            )
                        ]))->toResponse();
                    }
                } catch (\RuntimeException) {
                    return (new BaseResponse(
                        500,
                        null,
                        'An unexpected error occurred while processing the request',
                    ))->toResponse(); // Internal Server Error
                }
            }
        }

        // This message exists in case of the user doesn't exist on the sms/reset, to protect against RGPD
        return (new BaseResponse(
            200,
            sprintf('If the phone number exists, we have sent you a new code to: %s', $dataRequest['phone_number']),
            null,
        ))->toResponse();
    }
}
