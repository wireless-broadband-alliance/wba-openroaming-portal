<?php

namespace App\Api\V3\Controller;

use App\Api\V3\BaseResponse;
use App\DTO\Api\UserSMSRegistrationDTO;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\Captcha\CaptchaValidator;
use App\Service\EventActions;
use App\Service\SendSMS;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class RegistrationSMSController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly EventRepository $eventRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly SendSMS $sendSMSService,
        private readonly SettingRepository $settingRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly CaptchaValidator $captchaValidator,
        private readonly ValidatorInterface $validator,
        private readonly PhoneNumberUtil $phoneNumberUtil,
    ) {
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws NonUniqueResultException
     * @throws Exception
     */
    #[Route('/auth/sms/register', name: 'api_v3_auth_sms_register', methods: ['POST'])]
    public function smsRegister(
        Request $request
    ): JsonResponse {
        try {
            $data = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return new BaseResponse(
                400,
                null,
                'Invalid JSON format'
            )->toResponse();
        }

        $registrationDTO = new UserSMSRegistrationDTO();
        $registrationDTO->phoneNumber = $data['phone_number'] ?? null;
        $registrationDTO->countryCode = $data['country_code'] ?? null;
        $registrationDTO->password = $data['password'] ?? null;
        $registrationDTO->firstName = $data['first_name'] ?? null;
        $registrationDTO->lastName = $data['last_name'] ?? null;
        $registrationDTO->captchaToken = $data['turnstile_token'] ?? $data['captcha_token'] ?? $data['recaptcha_token'] ?? null;

        // Validate the registration DTO
        $violations = $this->validator->validate($registrationDTO);

        if (count($violations) > 0) {
            $errors = [];

            foreach ($violations as $violation) {
                $propertyPath = $violation->getPropertyPath();
                $errors[$propertyPath][] = $violation->getMessage();
            }

            return new BaseResponse(
                400,
                $errors,
                'Invalid data.'
            )->toResponse();
        }

        // Single call handles checking provider status, token extraction, and response formatting
        if ($captchaError = $this->captchaValidator->validateApiPayload($data, $request->getClientIp())) {
            return $captchaError->toResponse();
        }

        // Validate phone number with country code
        try {
            $parsedPhoneNumber = $this->phoneNumberUtil->parse(
                $registrationDTO->phoneNumber,
                strtoupper($registrationDTO->countryCode)
            );

            if (!$this->phoneNumberUtil->isValidNumber($parsedPhoneNumber)) {
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

        // Store the phone number in E.164 format
        $formattedPhoneNumber = $this->phoneNumberUtil->format(
            $parsedPhoneNumber,
            PhoneNumberFormat::E164
        );

        // Check for an existing user with the same phone number
        if ($this->userRepository->findOneBy(['uuid' => $formattedPhoneNumber])) {
            return new BaseResponse(
                200,
                [
                    'message' =>
                        'SMS User Account Registered Successfully.'
                        . ' A verification code has been sent to your phone.'
                ]
            )->toResponse(); // False success for RGPD policies
        }

        // Create and populate the new user entity
        $user = new User();
        $user->setUuid($formattedPhoneNumber);
        $user->setPhoneNumber($parsedPhoneNumber);

        $hashedPassword = $this->userPasswordHasher->hashPassword(
            $user,
            $registrationDTO->password
        );

        $user->setPassword($hashedPassword);
        $user->setIsVerified(false);
        $user->setTwoFAcode((string)random_int(100000, 999999));
        $user->setTwoFAcodeIsActive(true);
        $user->setTwoFAcodeGeneratedAt(new DateTime());
        $user->setFirstName($registrationDTO->firstName);
        $user->setLastName($registrationDTO->lastName);
        $user->setCreatedAt(new DateTime());

        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($user);
        $userExternalAuth->setProvider(
            UserProvider::PORTAL_ACCOUNT->value
        );
        $userExternalAuth->setProviderId(
            UserProvider::PHONE_NUMBER->value
        );

        $this->entityManager->persist($user);
        $this->entityManager->persist($userExternalAuth);
        $this->entityManager->flush();

        // Save user creation event
        $eventMetaData = [
            EventMetadataKeysType::IP->value => $request->getClientIp(),
            EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
            EventMetadataKeysType::UUID->value => $user->getUuid(),
            EventMetadataKeysType::PLATFORM->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::PLATFORM_MODE->value]
            )->getValue(),
            EventMetadataKeysType::REGISTRATION_TYPE->value =>
                UserProvider::PHONE_NUMBER->value
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION->value,
            new DateTime(),
            $eventMetaData
        );

        // Send SMS
        try {
            $message = 'Your account password is: '
                . $registrationDTO->password
                . '%0A'
                . 'Verification code is: '
                . $user->getTwoFAcode();

            $result = $this->sendSMSService->sendSmsNoValidation(
                $user,
                $message
            );

            if ($result !== '' && $result !== '0') {
                return new BaseResponse(
                    200,
                    [
                        'message' =>
                            'SMS User Account Registered Successfully.'
                            . ' A verification code has been sent to your phone.'
                    ]
                )->toResponse();
            }
        } catch (\RuntimeException) {
            return new BaseResponse(
                500,
                null,
                'Failed to send SMS'
            )->toResponse();
        }

        return new BaseResponse(
            500,
            null,
            'User registered but SMS could not be sent.'
        )->toResponse();
    }

    /**
     * @throws ClientExceptionInterface
     * @throws NonUniqueResultException
     * @throws RandomException
     * @throws RedirectionExceptionInterface
     * @throws \DateMalformedIntervalStringException
     * @throws \DateMalformedStringException
     */
    #[Route('/auth/sms/reset', name: 'api_v3_auth_sms_reset', methods: ['POST'])]
    public function smsReset(
        Request $request
    ): JsonResponse {
        try {
            $dataRequest = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse();
        }

        // Single call handles provider check, token extraction, and response formatting
        if ($captchaError = $this->captchaValidator->validateApiPayload($dataRequest, $request->getClientIp())) {
            return $captchaError->toResponse();
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
            $parsedPhoneNumber = $this->phoneNumberUtil->parse(
                $dataRequest['phone_number'],
                strtoupper((string)$dataRequest['country_code'])
            );
            if (!$this->phoneNumberUtil->isValidNumber($parsedPhoneNumber)) {
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
        $formattedPhoneNumber = $this->phoneNumberUtil->format(
            $parsedPhoneNumber,
            PhoneNumberFormat::E164
        );
        $user = $this->userRepository->findOneBy(['uuid' => $formattedPhoneNumber]);

        if ($user) {
            if ($user->getBannedAt()) {
                // Protect against RGPD / User enumeration
                return new BaseResponse(
                    200,
                    [
                        'success' => sprintf(
                            'If the phone number exists, a new code has been sent to %s.',
                            $user->getPhoneNumber()
                        ),
                    ]
                )->toResponse();
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

            if ($hasValidPortalAccount) {
                try {
                    $randomPassword = bin2hex(random_bytes(4));
                    $hashedPassword = $this->userPasswordHasher->hashPassword($user, $randomPassword);

                    // Retrieve the latest SMS attempt event for the user
                    $latestEvent = $this->eventRepository->findLatestSmsAttemptEvent($user);
                    $smsResendInterval = $this->settingRepository->findOneBy(
                        ['name' => SettingName::SMS_TIMER_RESEND->value]
                    )?->getValue();
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
                                    ),
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
                        EventMetadataKeysType::IP->value => $request->getClientIp(),
                        EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                        EventMetadataKeysType::UUID->value => $user->getUuid(),
                        EventMetadataKeysType::VERIFICATION_ATTEMPTS->value => $verificationAttempts,
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
                    $message = "Your account password is: " . $randomPassword;
                    $result = $this->sendSMSService->sendSmsNoValidation($user, $message);

                    // Log reset event
                    $eventMetadata = [
                        EventMetadataKeysType::IP->value => $request->getClientIp(),
                        EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                        EventMetadataKeysType::UUID->value => $user->getUuid(),
                    ];

                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::USER_ACCOUNT_PASSWORD_RESET_API->value,
                        new DateTime(),
                        $eventMetadata
                    );

                    if ($result !== '' && $result !== '0') {
                        return new BaseResponse(200, [
                            'success' => sprintf(
                                'If the phone number exists, we have sent a new code to: %s. You have %d attempt(s) left.',
                                $user->getUuid(),
                                $attemptsLeft
                            ),
                        ])->toResponse();
                    }
                } catch (\RuntimeException) {
                    return new BaseResponse(
                        500,
                        null,
                        'An unexpected error occurred while processing the request',
                    )->toResponse();
                }
            }
        }

        // Protect against RGPD / User enumeration if user does not exist
        return new BaseResponse(
            200,
            sprintf('If the phone number exists, we have sent you a new code to: %s', $dataRequest['phone_number']),
        )->toResponse();
    }
}
