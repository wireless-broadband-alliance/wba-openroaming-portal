<?php

namespace App\Api\V4\Controller;

use App\Api\V4\BaseResponse;
use App\DTO\Api\UserSMSRegistrationDTO;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\CaptchaAction;
use App\Enum\EventMetadataKeysType;
use App\Enum\ForgotPasswordEnum;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\Captcha\CaptchaValidator;
use App\Service\EventActions;
use App\Service\HashArgon2idService;
use App\Service\PasswordGenerator;
use App\Service\PasswordResetAttemptGuard;
use App\Service\SendSMS;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\LockMode;
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
    private const string INVALID_CODE_MESSAGE = 'Invalid or expired verification code.';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly SendSMS $sendSMSService,
        private readonly SettingRepository $settingRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly CaptchaValidator $captchaValidator,
        private readonly ValidatorInterface $validator,
        private readonly PhoneNumberUtil $phoneNumberUtil,
        private readonly PasswordGenerator $passwordGenerator,
        private readonly PasswordResetAttemptGuard $passwordResetAttemptGuard,
        private readonly EventRepository $eventRepository,
        private readonly HashArgon2idService $hashArgon2idService,
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
    #[Route('/auth/sms/register', name: 'api_v4_auth_sms_register', methods: ['POST'])]
    public function smsRegister(
        Request $request
    ): JsonResponse {
        $SMSRegistrationEnabled = $this->settingRepository->findOneBy([
            'name' => SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value
        ]);

        if (!$SMSRegistrationEnabled || $SMSRegistrationEnabled->getValue() === 'false') {
            return new BaseResponse(
                400,
                null,
                'SMS registration is disabled by the administration.'
            )->toResponse();
        }

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
        $registrationDTO->captchaToken = $data['turnstile_token'] ??
            $data['captcha_token'] ?? $data['recaptcha_token'] ?? null;

        // Validate the registration DTO
        $violations = $this->validator->validate($registrationDTO);

        if (count($violations) > 0) {
            return new BaseResponse(
                400,
                null,
                $violations[0]->getMessage()
            )->toResponse();
        }

        // Single call handles checking provider status, token extraction, and response formatting
        if (
            (
            $captchaError = $this->captchaValidator->validateApiPayload(
                $data,
                $request->getClientIp(),
                CaptchaAction::API_REGISTER_SMS
            )) instanceof BaseResponse
        ) {
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
            // Never echo the password back: the user chose it, and SMS content stays in provider logs
            // and on the device lock screen. Only the verification code is needed.
            $message = 'Verification code is: ' . $user->getTwoFAcode();

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
     * @throws \DateMalformedStringException
     */
    #[Route('/auth/sms/reset', name: 'api_v4_auth_sms_reset', methods: ['POST'])]
    public function smsReset(
        Request $request
    ): JsonResponse {
        try {
            $dataRequest = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse();
        }

        // Single call handles provider check, token extraction, and response formatting
        if (
            ($captchaError = $this->captchaValidator->validateApiPayload(
                $dataRequest,
                $request->getClientIp(),
                CaptchaAction::API_RESET_SMS
            )) instanceof BaseResponse
        ) {
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
                    // Cooldown + attempts limit, checked and recorded atomically per user (shared with the web
                    // forgot-password flow). Nothing below runs unless the attempt was accepted and saved.
                    $attempt = $this->passwordResetAttemptGuard->tryRegisterAttempt(
                        $user,
                        true,
                        $request->getClientIp(),
                        $request->headers->get('User-Agent')
                    );

                    if (!$attempt[PasswordResetAttemptGuard::ALLOWED]) {
                        $reason = $attempt[PasswordResetAttemptGuard::REASON];
                        if ($reason === ForgotPasswordEnum::ATTEMPTS_EXCEEDED->value) {
                            return new BaseResponse(
                                429,
                                null,
                                'You’ve exceeded the maximum number of attempts to regenerate. '
                                . 'Please contact support.'
                            )->toResponse();
                        }

                        // Cooldown: protect against spam and RGPD policies - simulate success without actual resend
                        return new BaseResponse(200, [
                            'success' => sprintf(
                                'If the phone number exists, a new code has been sent to %s.',
                                $user->getUuid()
                            ),
                        ])->toResponse();
                    }

                    // Generate a single-use verification code challenge (OTP)
                    $verificationCode = (string)random_int(100000, 999999);
                    $user->setTwoFAcode($verificationCode);
                    $user->setTwoFAcodeIsActive(true);
                    $user->setTwoFAcodeGeneratedAt(new DateTime());

                    // DO NOT mutate existing password or set forgotPasswordRequest at this stage
                    $this->entityManager->flush();

                    // Send verification code via SMS
                    $message = sprintf('Your password reset verification code is: %s', $verificationCode);
                    $result = $this->sendSMSService->sendSmsNoValidation($user, $message);

                    // Log reset request event
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
                                'If the phone number exists, we have sent a verification code to: %s. '
                                . 'You have %d attempt(s) left.',
                                $user->getUuid(),
                                $attempt[PasswordResetAttemptGuard::ATTEMPTS_LEFT]
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

    /**
     * Verify the SMS challenge code and only then update the user's password.
     *
     * @throws Exception
     */
    #[Route('/auth/sms/reset/confirm', name: 'api_v4_auth_sms_reset_confirm', methods: ['POST'])]
    public function smsResetConfirm(
        Request $request
    ): JsonResponse {
        try {
            $dataRequest = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse();
        }

        $errors = [];
        if (empty($dataRequest['country_code'])) {
            $errors[] = 'country_code';
        }
        if (empty($dataRequest['phone_number'])) {
            $errors[] = 'phone_number';
        }
        if (empty($dataRequest['code'])) {
            $errors[] = 'code';
        }
        if ($errors !== []) {
            return new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )->toResponse();
        }

        // Validate phone number format
        try {
            $parsedPhoneNumber = $this->phoneNumberUtil->parse(
                $dataRequest['phone_number'],
                strtoupper((string)$dataRequest['country_code'])
            );
            if (!$this->phoneNumberUtil->isValidNumber($parsedPhoneNumber)) {
                return new BaseResponse(400, null, 'Invalid phone number format.')->toResponse();
            }
        } catch (NumberParseException) {
            return new BaseResponse(400, null, 'Invalid phone number format or country code.')->toResponse();
        }

        $formattedPhoneNumber = $this->phoneNumberUtil->format(
            $parsedPhoneNumber,
            PhoneNumberFormat::E164
        );
        $user = $this->userRepository->findOneBy(['uuid' => $formattedPhoneNumber]);

        // Same answer for every failure (unknown number, banned, wrong, expired or locked code), so the response
        // never reveals whether an account exists or why the code was refused
        $invalidCode = new BaseResponse(400, null, self::INVALID_CODE_MESSAGE)->toResponse();

        if (!$user || $user->getBannedAt()) {
            return $invalidCode;
        }

        // Serialise confirmations per user with a database row lock (SELECT ... FOR UPDATE), held until the
        // transaction commits: parallel guesses, from any web container, cannot all slip in under the attempts
        // limit. The refresh also reloads the code fields, in case a concurrent request has just invalidated them.
        $response = $this->entityManager->wrapInTransaction(
            fn(): JsonResponse => $this->confirmLockedCode($user, $request, (string)$dataRequest['code'])
        );

        /** @var JsonResponse $response */
        return $response;
    }

    /**
     * Must only run inside the transaction of smsResetConfirm(); takes the row lock on the user first.
     *
     * @throws Exception
     */
    private function confirmLockedCode(User $user, Request $request, string $code): JsonResponse
    {
        $this->entityManager->refresh($user, LockMode::PESSIMISTIC_WRITE);

        // Same answer for every failure, see smsResetConfirm()
        $invalidCode = new BaseResponse(400, null, self::INVALID_CODE_MESSAGE)->toResponse();

        $storedCode = $user->getTwoFAcode();
        $generatedAt = $user->getTwoFAcodeGeneratedAt();

        if (!$user->getTwoFAcodeIsActive() || $storedCode === null || !$generatedAt instanceof DateTimeInterface) {
            return $invalidCode;
        }

        // Limits come from the dashboard SMS settings page (User Authentication > SMS > Configuration):
        // "Time Interval To Reset Attempts" (minutes) is how long the code stays valid and
        // "Number of Attempts" is how many wrong codes are allowed before it is invalidated
        $codeValidityMinutes = $this->getSmsSettingValue(SettingName::SMS_TIME_INTERVAL_TO_RESET_ATTEMPTS);
        $maxConfirmAttempts = $this->getSmsSettingValue(SettingName::SMS_ATTEMPTS_NUMBER);

        if ($this->isCodeExpired($generatedAt, $codeValidityMinutes)) {
            $this->invalidateCode($user);

            return $invalidCode;
        }

        // Wrong guesses are counted per code (only since it was generated), so a new /reset starts a new count
        $failedAttempts = count(
            $this->eventRepository->findLastEvents(
                $user,
                AnalyticalEventType::USER_ACCOUNT_PASSWORD_RESET_API_CONFIRM_FAILED->value,
                DateTime::createFromInterface($generatedAt)
            )
        );

        if ($failedAttempts >= $maxConfirmAttempts) {
            $this->invalidateCode($user);

            return $invalidCode;
        }

        // The code is stored as an Argon2id hash (HashedStringType): compare with password_verify, which is
        // also constant-time
        if (!$this->hashArgon2idService->verifyHash($code, $storedCode)) {
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_PASSWORD_RESET_API_CONFIRM_FAILED->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $user->getUuid(),
                ]
            );

            // That was the last allowed guess: burn the code, the user has to request a new one
            if ($failedAttempts + 1 >= $maxConfirmAttempts) {
                $this->invalidateCode($user);
            }

            return $invalidCode;
        }

        // Generate strong random replacement password and update account state after successful verification
        $randomPassword = $this->passwordGenerator->generate();
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $randomPassword));
        $user->setForgotPasswordRequest(false);
        $this->invalidateCode($user);

        return new BaseResponse(200, [
            'success' => 'Password updated successfully.',
            'new_password' => $randomPassword,
        ])->toResponse();
    }

    private function isCodeExpired(DateTimeInterface $generatedAt, int $validityMinutes): bool
    {
        return new DateTime()->getTimestamp() - $generatedAt->getTimestamp() >= $validityMinutes * 60;
    }

    /**
     * Reads an integer setting from the dashboard SMS settings page. A missing setting reads as 0, which makes
     * every code count as expired or out of attempts, so the endpoint fails closed instead of allowing anything.
     */
    private function getSmsSettingValue(SettingName $setting): int
    {
        return (int)$this->settingRepository->findOneBy(['name' => $setting->value])?->getValue();
    }

    /**
     * Makes the current code unusable (used, expired or too many wrong guesses).
     */
    private function invalidateCode(User $user): void
    {
        $user->setTwoFAcodeIsActive(false);
        $user->setTwoFAcode(null);
        $user->setTwoFAcodeGeneratedAt(null);
        $this->entityManager->flush();
    }
}
