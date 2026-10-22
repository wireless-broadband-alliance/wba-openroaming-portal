<?php

namespace App\Api\V3\Controller;

use App\Api\V3\BaseResponse;
use App\DTO\Api\UserEmailRegistrationDTO;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\Captcha\CaptchaValidator;
use App\Service\EmailGenerator;
use App\Service\EventActions;
use App\Service\HashArgon2idService;
use App\Service\UserStatusChecker;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class RegistrationLocalController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly EventRepository $eventRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly SettingRepository $settingRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly CaptchaValidator $captchaValidator,
        private readonly EmailGenerator $emailGenerator,
        private readonly ValidatorInterface $validator,
        private readonly UserStatusChecker $userStatusChecker,
        private readonly HashArgon2idService $hashArgon2idService
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
    #[Route('/auth/local/register', name: 'api_v3_auth_local_register', methods: ['POST'])]
    public function localRegister(
        Request $request,
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse();
        }

        $registrationDTO = new UserEmailRegistrationDTO();
        $registrationDTO->email = $data['email'] ?? null;
        $registrationDTO->password = $data['password'] ?? null;
        $registrationDTO->firstName = $data['first_name'] ?? null;
        $registrationDTO->lastName = $data['last_name'] ?? null;
        $registrationDTO->captchaToken = $data['turnstile_token'] ?? $data['recaptcha_token'] ?? null;

        // Validate the registration DTO
        $violations = $this->validator->validate($registrationDTO);

        if (count($violations) > 0) {
            return new BaseResponse(
                400,
                null,
                $violations[0]->getMessage()
            )->toResponse();
        }

        if ((
            $captchaError = $this->captchaValidator->validateApiPayload(
                $data,
                $request->getClientIp()
            )) instanceof BaseResponse) {
            return $captchaError->toResponse();
        }

        if ($this->userRepository->findOneBy(['email' => $registrationDTO->email])) {
            return new BaseResponse(
                200,
                ['message' => 'Registration successful. Please check your email for further instructions']
            )->toResponse();
        }

        if (!$this->userStatusChecker->isValidEmail($registrationDTO->email)) {
            return new BaseResponse(
                403,
                null,
                'Your email domain is not allowed to use this platform.'
            )->toResponse();
        }

        $blockAliases = $this->getParameter('app.block_email_aliases');

        if (
            $blockAliases &&
            str_contains(explode('@', $registrationDTO->email)[0], '+')
        ) {
            return new BaseResponse(
                400,
                null,
                'Email aliases are not allowed for registration.'
            )->toResponse();
        }

        $user = new User();
        $user->setUuid($registrationDTO->email);
        $user->setEmail($registrationDTO->email);

        $hashedPassword = $this->userPasswordHasher->hashPassword(
            $user,
            $registrationDTO->password
        );

        $user->setPassword($hashedPassword);
        $user->setIsVerified(false);
        $user->setTwoFAcode((string)random_int(100000, 999999));
        $user->setTwoFAcodeGeneratedAt(new DateTime());
        $user->setTwoFAcodeIsActive(true);
        $user->setFirstName($registrationDTO->firstName);
        $user->setLastName($registrationDTO->lastName);
        $user->setCreatedAt(new DateTime());

        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($user);
        $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT->value);
        $userExternalAuth->setProviderId(UserProvider::EMAIL->value);

        $this->entityManager->persist($user);
        $this->entityManager->persist($userExternalAuth);
        $this->entityManager->flush();

        $this->emailGenerator->sendRegistrationEmail(
            $user,
            $registrationDTO->password,
            true
        );

        $eventMetaData = [
            EventMetadataKeysType::IP->value => $request->getClientIp(),
            EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
            EventMetadataKeysType::UUID->value => $user->getUuid(),
            EventMetadataKeysType::PLATFORM->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::PLATFORM_MODE->value]
            )->getValue(),
            EventMetadataKeysType::REGISTRATION_TYPE->value => UserProvider::EMAIL->value
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
    #[Route('/auth/local/reset', name: 'api_v3_auth_local_reset', methods: ['POST'])]
    public function localReset(
        Request $request,
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse();
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

        // Single call handles provider check, token resolution, and error response formatting
        if (($captchaError = $this->captchaValidator->validateApiPayload(
                $data,
                $request->getClientIp()
            )) instanceof BaseResponse) {
            return $captchaError->toResponse();
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
                $emailTimerResend = $this->settingRepository->findOneBy(
                    ['name' => SettingName::EMAIL_TIMER_RESEND->value]
                )->getValue();
                $minInterval = new DateInterval('PT' . $emailTimerResend . 'M');
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
                            EventMetadataKeysType::PLATFORM->value => PlatformMode::LIVE->value,
                            EventMetadataKeysType::IP->value => $request->getClientIp(),
                            EventMetadataKeysType::UUID->value => $user->getUuid(),
                        ];
                    }

                    $latestEventMetadata['lastVerificationCodeTime'] = $currentTime->format(
                        DateTimeInterface::ATOM
                    );
                    $latestEvent->setEventMetadata($latestEventMetadata);

                    $this->eventRepository->save($latestEvent, true);

                    // Generate a plain-text verification code for the email link
                    $rawTwoFaCode = bin2hex(random_bytes(16)); // e.g. "a3f890b2e14c76d..."
                    $hashedCode = $this->hashArgon2idService->hash($rawTwoFaCode);
                    $user->setTwoFAcode($hashedCode);
                    $user->setForgotPasswordRequest(true);
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    $this->emailGenerator->sendForgotPasswordEmail($user, $rawTwoFaCode);

                    // Defines the Event to the table
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
        )->toResponse(); // Not Found User doesn't exist request
    }
}
