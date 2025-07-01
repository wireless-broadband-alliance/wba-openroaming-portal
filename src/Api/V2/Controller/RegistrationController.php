<?php

namespace App\Api\V2\Controller;

use App\Api\V2\BaseResponse;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\OperationMode;
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
use App\Service\VerificationCodeEmailGenerator;
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly EventRepository $eventRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly ParameterBagInterface $parameterBag,
        private readonly SendSMS $sendSMSService,
        private readonly GetSettings $getSettings,
        private readonly SettingRepository $settingRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly CaptchaValidator $captchaValidator,
        private readonly RegistrationEmailGenerator $emailGenerator,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/auth/local/register', name: 'api_v2_auth_local_register', methods: ['POST'])]
    public function localRegister(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse(); // Invalid Json
        }

        $turnstileSetting = $this->settingRepository->findOneBy(['name' => 'TURNSTILE_CHECKER'])->getValue();
        if (!$turnstileSetting) {
            throw new \RuntimeException('Missing settings: TURNSTILE_CHECKER not found');
        }

        if ($turnstileSetting === OperationMode::ON->value) {
            if (!isset($data['turnstile_token'])) {
                return new BaseResponse(400, null, 'CAPTCHA validation failed')->toResponse(); # Bad Request Response
            }

            $turnstileValidation = $this->captchaValidator->validate(
                $data['turnstile_token'],
                $request->getClientIp()
            );

            if (!$turnstileValidation['success']) {
                $errorMessage = $turnstileValidation['error'] ?? 'CAPTCHA validation failed';
                return new BaseResponse(400, null, $errorMessage)->toResponse();
            }
        }

        $errors = [];
        // Check for missing fields and add them to the array errors
        if (empty($data['email'])) {
            $errors[] = 'email';
        }
        if (empty($data['password'])) {
            $errors[] = 'password';
        }
        if ($errors !== []) {
            return new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )->toResponse();
        }

        $emailConstraint = new Assert\Email();
        $emailConstraint->message = 'Invalid email format.';

        $emailViolations = $this->validator->validate($data['email'], $emailConstraint);

        if (count($emailViolations) > 0) {
            $errorMessage = $emailViolations[0]->getMessage();
            return new BaseResponse(400, null, $errorMessage)->toResponse();
        }

        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return new BaseResponse(
                200,
                ['message' => 'Registration successful. Please check your email for further instructions']
            )->toResponse(); // False success for RGPD policies
        }

        $user = new User();
        $user->setUuid($data['email']);
        $user->setEmail($data['email']);
        $hashedPassword = $userPasswordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);
        $user->setIsVerified(false);
        $user->setVerificationCode(random_int(100000, 999999));
        $user->setFirstName($data['first_name'] ?? null);
        $user->setLastName($data['last_name'] ?? null);
        $user->setCreatedAt(new DateTime());

        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($user);
        $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT->value);
        $userExternalAuth->setProviderId(UserProvider::EMAIL->value);

        $this->entityManager->persist($user);
        $this->entityManager->persist($userExternalAuth);
        $this->entityManager->flush();

        $this->emailGenerator->sendRegistrationEmail($user, $data['password']);

        $eventMetaData = [
            'ip' => $request->getClientIp(),
            'uuid' => $user->getEmail(),
            'provider' => UserProvider::PORTAL_ACCOUNT->value,
            'registrationType' => UserProvider::EMAIL->value,
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION->value,
            new DateTime(),
            $eventMetaData
        );

        return new BaseResponse(
            200,
            ['message' => 'Registration successful. Please check your email for further instructions']
        )->toResponse();
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws NonUniqueResultException
     * @throws Exception
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws TransportExceptionInterface
     */
    #[Route('/auth/local/reset', name: 'api_v2_auth_local_reset', methods: ['POST'])]
    public function localReset(
        UserPasswordHasherInterface $userPasswordHasher,
        MailerInterface $mailer,
        Request $request,
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse(); // Invalid Json
        }

        $errors = [];
        if (empty($data['email'])) {
            $errors[] = 'email';
        }
        if ($errors !== []) {
            return new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )->toResponse();
        }

        $turnstileSetting = $this->settingRepository->findOneBy(['name' => 'TURNSTILE_CHECKER'])->getValue();
        if (!$turnstileSetting) {
            throw new \RuntimeException('Missing settings: TURNSTILE_CHECKER not found');
        }

        if ($turnstileSetting === OperationMode::ON->value) {
            if (!isset($data['turnstile_token'])) {
                return new BaseResponse(400, null, 'CAPTCHA validation failed')->toResponse(); # Bad Request Response
            }

            $turnstileValidation = $this->captchaValidator->validate(
                $data['turnstile_token'],
                $request->getClientIp()
            );

            if (!$turnstileValidation['success']) {
                $errorMessage = $turnstileValidation['error'] ?? 'CAPTCHA validation failed';
                return new BaseResponse(400, null, $errorMessage)->toResponse();
            }
        }

        $emailConstraint = new Assert\Email();
        $emailConstraint->message = 'Invalid email format.';

        $emailViolations = $this->validator->validate($data['email'], $emailConstraint);

        if (count($emailViolations) > 0) {
            $errorMessage = $emailViolations[0]->getMessage();
            return new BaseResponse(400, null, $errorMessage)->toResponse();
        }

        $user = $this->userRepository->findOneBy(['email' => $data['email']]);

        if ($user) {
            if ($user->getBannedAt()) {
                // This message exists in case of a user is banned the email/reset, to protect against RGPD
                return new BaseResponse(200, [
                    // Forbidden request
                    'message' => sprintf(
                        'If the Email exists, a new code has been sent to %s.',
                        $user->getEmail()
                    )
                ])->toResponse();
            }

            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);
            $hasValidPortalAccount = false;

            foreach ($userExternalAuths as $auth) {
                if (
                    $auth->getProvider() === UserProvider::PORTAL_ACCOUNT->value &&
                    $auth->getProviderId() === UserProvider::EMAIL->value
                ) {
                    $hasValidPortalAccount = true;
                    break;
                }
            }

            if ($hasValidPortalAccount) {
                $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent(
                    $user,
                    AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value
                );
                $minInterval = new DateInterval('PT2M');
                $currentTime = new DateTime();
                $latestEventMetadata = $latestEvent instanceof Event ? $latestEvent->getEventMetadata() : [];
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
                    if (!$latestEvent instanceof Event) {
                        $latestEvent = new Event();
                        $latestEvent->setUser($user);
                        $latestEvent->setEventDatetime(new DateTime());
                        $latestEvent->setEventName(AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value);
                        $latestEventMetadata = [
                            'platform' => PlatformMode::LIVE->value,
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

                    $email = new TemplatedEmail()
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
                            'emailTitle' => $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue(),
                            'contactEmail' => $this->settingRepository->findOneBy(
                                ['name' => 'CONTACT_EMAIL']
                            )->getValue()
                        ]);

                    $mailer->send($email);

                    // Defines the Event to the table
                    $eventMetadata = [
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent'),
                        'uuid' => $user->getUuid(),
                    ];

                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::USER_ACCOUNT_PASSWORD_RESET_API->value,
                        new DateTime(),
                        $eventMetadata
                    );

                    return new BaseResponse(200, [
                        // Correct success response
                        'message' => sprintf(
                            // Actually success
                            'If the email address exists in our system, we’ve sent a new one to: %s.',
                            $user->getEmail()
                        )
                    ])->toResponse();
                }
                // This message exists in case of user spams the email/reset, to protect against RGPD
                return new BaseResponse(200, [
                    // Correct success response
                    'message' => sprintf(
                        'If the email address exists in our system, we’ve sent a new one to: %s.',
                        $user->getEmail()
                    )
                ])->toResponse(); // To many requests
            }
        }

        // This message exists in case of user tries to spam the email/reset, to protect against RGPD
        return new BaseResponse(
            200,
            sprintf('If the email address exists in our system, we’ve sent a new one to: %s.', $data['email']),
            null,
        )->toResponse(); // Not Found User doesn't exist request
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws NonUniqueResultException
     * @throws Exception
     */
    #[Route('/auth/sms/register', name: 'api_v2_auth_sms_register', methods: ['POST'])]
    public function smsRegister(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        PhoneNumberUtil $phoneNumberUtil
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse();
        }

        $turnstileSetting = $this->settingRepository->findOneBy(['name' => 'TURNSTILE_CHECKER'])->getValue();
        if (!$turnstileSetting) {
            throw new \RuntimeException('Missing settings: TURNSTILE_CHECKER not found');
        }

        if ($turnstileSetting === OperationMode::ON->value) {
            if (!isset($data['turnstile_token'])) {
                return new BaseResponse(400, null, 'CAPTCHA validation failed')->toResponse(); # Bad Request Response
            }

            $turnstileValidation = $this->captchaValidator->validate(
                $data['turnstile_token'],
                $request->getClientIp()
            );

            if (!$turnstileValidation['success']) {
                $errorMessage = $turnstileValidation['error'] ?? 'CAPTCHA validation failed';
                return new BaseResponse(400, null, $errorMessage)->toResponse();
            }
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
        if ($errors !== []) {
            return new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )->toResponse();
        }

        // Validate phone number with country code
        try {
            $parsedPhoneNumber = $phoneNumberUtil->parse(
                $data['phone_number'],
                strtoupper((string)$data['country_code'])
            );
            if ($parsedPhoneNumber && !$phoneNumberUtil->isValidNumber($parsedPhoneNumber)) {
                return new BaseResponse(
                    400,
                    null,
                    'Invalid phone number format.'
                )->toResponse();
            }
        } catch (NumberParseException) {
            return new BaseResponse(
                400,
                null,
                'Invalid phone number format or country code.'
            )->toResponse();
        }

        // Check for existing user with the same phone number
        $formattedPhoneNumber = $phoneNumberUtil->format($parsedPhoneNumber, PhoneNumberFormat::E164);
        if ($this->userRepository->findOneBy(['uuid' => $formattedPhoneNumber])) {
            return new BaseResponse(200, [
                'message' => 'SMS User Account Registered Successfully.' .
                    ' A verification code has been sent to your phone.'
            ])->toResponse(); // False success for RGPD policies
        }

        // Create and populate the new user entity
        $user = new User();
        $user->setUuid($formattedPhoneNumber);  // Store formatted phone number in UUID field
        $user->setPhoneNumber($parsedPhoneNumber);  // Set the PhoneNumber object directly
        $hashedPassword = $userPasswordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);
        $user->setIsVerified(false);
        $user->setVerificationCode(random_int(100000, 999999));
        $user->setFirstName($data['first_name'] ?? null);
        $user->setLastName($data['last_name'] ?? null);
        $user->setCreatedAt(new DateTime());

        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($user);
        $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT->value);
        $userExternalAuth->setProviderId(UserProvider::PHONE_NUMBER->value);

        $this->entityManager->persist($user);
        $this->entityManager->persist($userExternalAuth);
        $this->entityManager->flush();

        // Save user creation event
        $eventMetaData = [
            'uuid' => $user->getUuid(),
            'provider' => UserProvider::PORTAL_ACCOUNT->value,
            'registrationType' => UserProvider::PHONE_NUMBER->value,
            'ip' => $request->getClientIp(),
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION->value,
            new DateTime(),
            $eventMetaData
        );

        // Send SMS
        try {
            $message = "Your account password is: "
                . $data['password'] . "%0A" . "Verification code is: "
                . $user->getVerificationCode();
            $result = $this->sendSMSService->sendSms($user->getPhoneNumber(), $message);

            if ($result) {
                return new BaseResponse(
                    200,
                    [
                        'message' =>
                            'SMS User Account Registered Successfully. A verification code has been sent to your phone.'
                    ]
                )->toResponse();
            }
        } catch (\RuntimeException) {
            return new BaseResponse(500, null, 'Failed to send SMS')->toResponse(); // Internal Server Error
        }

        // Return fallback response
        return new BaseResponse(500, null, 'User registered but SMS could not be sent.')->toResponse();
    }


    /**
     * @throws ClientExceptionInterface
     * @throws NonUniqueResultException
     * @throws RandomException
     * @throws RedirectionExceptionInterface
     * @throws \DateMalformedIntervalStringException
     * @throws \DateMalformedStringException
     */
    #[Route('/auth/sms/reset', name: 'api_v2_auth_sms_reset', methods: ['POST'])]
    public function smsReset(
        Request $request,
        PhoneNumberUtil $phoneNumberUtil
    ): JsonResponse {
        try {
            $dataRequest = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse(); // Invalid Json
        }
        $turnstileSetting = $this->settingRepository->findOneBy(['name' => 'TURNSTILE_CHECKER'])->getValue();
        if (!$turnstileSetting) {
            throw new \RuntimeException('Missing settings: TURNSTILE_CHECKER not found');
        }
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if ($turnstileSetting === OperationMode::ON->value) {
            if (!isset($data['turnstile_token'])) {
                return new BaseResponse(400, null, 'CAPTCHA validation failed')->toResponse(); # Bad Request Response
            }

            $turnstileValidation = $this->captchaValidator->validate(
                $data['turnstile_token'],
                $request->getClientIp()
            );

            if (!$turnstileValidation['success']) {
                $errorMessage = $turnstileValidation['error'] ?? 'CAPTCHA validation failed';
                return new BaseResponse(400, null, $errorMessage)->toResponse();
            }
        }

        $errors = [];
        if (empty($dataRequest['country_code'])) {
            $errors[] = 'country_code';
        }
        if (empty($dataRequest['phone_number'])) {
            $errors[] = 'phone_number';
        }
        if ($errors !== []) {
            return new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )->toResponse();
        }

        // Validate phone number with country code
        try {
            $parsedPhoneNumber = $phoneNumberUtil->parse(
                $dataRequest['phone_number'],
                strtoupper((string)$dataRequest['country_code'])
            );
            if ($parsedPhoneNumber && !$phoneNumberUtil->isValidNumber($parsedPhoneNumber)) {
                return new BaseResponse(
                    400,
                    null,
                    'Invalid phone number format.'
                )->toResponse();
            }
        } catch (NumberParseException) {
            return new BaseResponse(
                400,
                null,
                'Invalid phone number format or country code.'
            )->toResponse();
        }

        // Check for existing user with the same phone number
        $formattedPhoneNumber = $phoneNumberUtil->format($parsedPhoneNumber, PhoneNumberFormat::E164);
        $user = $this->userRepository->findOneBy(['uuid' => $formattedPhoneNumber]);
        if ($user) {
            if ($user->getBannedAt()) {
                // This message exists in case of a user is banned the sms/reset, to protect against RGPD
                return new BaseResponse(
                    200,
                    'If the phone number exists, a new code has been sent to %s.',
                    $user->getEmail(),
                    null,
                )->toResponse(); // Too Many Requests Response
            }

            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);
            $hasValidPortalAccount = false;

            foreach ($userExternalAuths as $auth) {
                if (
                    $auth->getProvider() === UserProvider::PORTAL_ACCOUNT->value &&
                    $auth->getProviderId() === UserProvider::PHONE_NUMBER->value
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
                    if ($latestEvent instanceof Event) {
                        $latestEventMetadata = $latestEvent->getEventMetadata();
                        $verificationAttempts = $latestEventMetadata['verificationAttempts'] ?? 0;
                        $lastVerificationCodeTime = isset($latestEventMetadata['lastVerificationCodeTime'])
                            ? new DateTime($latestEventMetadata['lastVerificationCodeTime'])
                            : null;

                        if ($lastVerificationCodeTime instanceof DateTime) {
                            $allowedTime = (clone $lastVerificationCodeTime)->add($minInterval);

                            if ($allowedTime > $currentTime) {
                                // Protect against spam and RGPD policies - simulate success without actual resend
                                return new BaseResponse(200, [
                                    'success' => sprintf(
                                        'If the phone number exists, a new code has been sent to %s.',
                                        $user->getPhoneNumber()
                                    )
                                ])->toResponse();
                            }

                            $verificationAttempts++;
                        }
                    }
                    $attemptsLeft = $maxAttempts - $verificationAttempts;
                    if ($attemptsLeft < 0) {
                        return new BaseResponse(
                            429,
                            null,
                            'You’ve exceeded the maximum number of attempts to regenerate. Please contact support.'
                        )->toResponse();
                    }

                    // Update or create the event record
                    if (!$latestEvent instanceof Event) {
                        $latestEvent = new Event();
                        $latestEvent->setUser($user);
                        $latestEvent->setEventDatetime($currentTime);
                        $latestEvent->setEventName(AnalyticalEventType::USER_SMS_ATTEMPT->value);
                    }

                    $eventMetadata = [
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent'),
                        'uuid' => $user->getUuid(),
                        'lastVerificationCodeTime' => $currentTime->format(DateTimeInterface::ATOM),
                        'verificationAttempts' => $verificationAttempts,
                    ];
                    $latestEvent->setEventMetadata($eventMetadata);

                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::USER_SMS_ATTEMPT->value,
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
                        'user_agent' => $request->headers->get('User-Agent'),
                        'uuid' => $user->getUuid(),
                    ];

                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::USER_ACCOUNT_PASSWORD_RESET_API->value,
                        new DateTime(),
                        $eventMetadata
                    );

                    if ($result) {
                        return new BaseResponse(200, [
                            'success' => sprintf(
                                'If the phone number exists,' .
                                ' we have sent a new code to: %s. You have %d attempt(s) left.',
                                $user->getUuid(),
                                $attemptsLeft
                            )
                        ])->toResponse();
                    }
                } catch (\RuntimeException) {
                    return new BaseResponse(
                        500,
                        null,
                        'An unexpected error occurred while processing the request',
                    )->toResponse(); // Internal Server Error
                }
            }
        }

        // This message exists in case of the user doesn't exist on the sms/reset, to protect against RGPD
        return new BaseResponse(
            200,
            sprintf('If the phone number exists, we have sent you a new code to: %s', $dataRequest['phone_number']),
            null,
        )->toResponse();
    }
}
