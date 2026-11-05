<?php

namespace App\Api\V4\Controller;

use App\Api\BaseResponseInterface;
use App\Api\V4\BaseResponse;
use App\Entity\User;
use App\Enum\CaptchaAction;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\UserProvider;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\UserRepository;
use App\Service\Captcha\CaptchaValidator;
use App\Service\EventActions;
use App\Service\TOTPService;
use App\Service\TwoFAService;
use App\Service\UserStatusChecker;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class TwoFAController extends AbstractController
{
    public function __construct(
        private readonly UserStatusChecker $userStatusChecker,
        private readonly TwoFAService $twoFAService,
        private readonly TOTPService $TOTPService,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly CaptchaValidator $captchaValidator,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    #[Route(
        '/twoFA/{type}',
        name: 'api_v4_twoFA_enable',
        requirements: [
            'type' => 'totp|email|sms',
        ],
        defaults: [
            'type' => 'email',
        ],
        methods: ['POST']
    )]
    public function twoFAEnable(Request $request, string $type): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse();
        }

        // Single call handles checking provider status, token extraction, and response formatting
        if (
            (
            $captchaError = $this->captchaValidator->validateApiPayload(
                $data,
                $request->getClientIp(),
                CaptchaAction::API_TWO_FA
            )) instanceof BaseResponse
        ) {
            return $captchaError->toResponse();
        }

        $errors = [];
        // Check for missing fields and add them to the array errors
        if (empty($data['uuid'])) {
            $errors[] = 'uuid';
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

        // Check if user exists and is valid
        $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

        if (!$user instanceof User) {
            return new BaseResponse(401, null, 'Invalid credentials')->toResponse();
        }

        if (!$this->userPasswordHasher->isPasswordValid($user, $data['password'])) {
            return new BaseResponse(401, null, 'Invalid credentials')->toResponse();
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user, BaseResponse::class);
        if ($statusCheckerResponse instanceof BaseResponseInterface) {
            return $statusCheckerResponse->toResponse();
        }

        // Enrolment only. If a second factor is already active, replacing it here would let anyone who
        // knows the password take over 2FA (e.g. overwrite the TOTP secret) without proving the
        // current factor. Changing or removing an active method must go through the disable flow first.
        if ($user->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::DISABLED->value) {
            return new BaseResponse(
                409,
                null,
                'Two-factor authentication is already enabled for this account. Disable it before enrolling a new method.'
            )->toResponse();
        }

        // Generate TOTP secret
        if ($type === 'totp') {
            $secret = $this->TOTPService->generateSecret();
            $user->setTwoFAsecret($secret);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Defines the Event to the table
            $eventMetadata = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $user->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::TWO_FA_CODE_ENABLE->value,
                new DateTime(),
                $eventMetadata
            );

            return new BaseResponse(
                200,
                [
                    'message' => 'Two Factor TOTP Secret generated successfully',
                    'totpId' => $user->getTwoFAsecret(),
                ]
            )->toResponse();
        }

        // Generate Email/SMS 2FA code
        $this->twoFAService->generate2FACode(
            $user,
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
            AnalyticalEventType::TWO_FA_CODE_ENABLE->value
        );

        $provider = $user->getUserExternalAuths()[0]?->getProvider();

        if ($provider === UserProvider::SAML->value) {
            $message = 'Two Factor Code sent to: ' . $user->getUserExternalAuths()[0]->getProviderId();
        } else {
            $message = 'Two Factor Code sent to: ' . $user->getUuid();
        }

        return new BaseResponse(
            200,
            [
                'message' => $message,
            ]
        )->toResponse();
    }

    #[Route(
        '/twoFA/validate',
        name: 'api_v4_twoFA_validate',
        methods: ['POST']
    )]
    public function twoFAValidate(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new BaseResponse(
                400,
                null,
                'Invalid JSON format'
            )->toResponse(); # Bad Request Response
        }

        $errors = [];
        // Check for missing fields and add them to the array errors
        if (empty($data['uuid'])) {
            $errors[] = 'uuid';
        }
        if (empty($data['password'])) {
            $errors[] = 'password';
        }
        if (empty($data['type'])) {
            $errors[] = 'type';
        }
        if (empty($data['code'])) {
            $errors[] = 'code';
        }

        if ($errors !== []) {
            return new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )->toResponse();
        }

        $type = (string)$data['type'];
        $code = (string)$data['code'];

        if (!in_array($type, ['totp', 'email', 'sms'], true)) {
            return new BaseResponse(400, null, 'Invalid data: type must be one of totp, email or sms.')->toResponse();
        }

        // Check if user exists are valid
        $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

        if (!$user instanceof User) {
            return new BaseResponse(401, null, 'Invalid credentials')->toResponse();
            // Bad Request Response
        }

        if (!$this->userPasswordHasher->isPasswordValid($user, $data['password'])) {
            return new BaseResponse(401, null, 'Invalid credentials')->toResponse(); # Unauthorized Request Response
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user, BaseResponse::class);
        if ($statusCheckerResponse instanceof BaseResponseInterface) {
            return $statusCheckerResponse->toResponse();
        }

        // Same rule as twoFAEnable(): this endpoint only confirms a new enrolment, it never switches an
        // already active method (that would bypass the current second factor).
        if ($user->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::DISABLED->value) {
            return new BaseResponse(
                409,
                null,
                'Two-factor authentication is already enabled for this account. Disable it before enrolling a new method.'
            )->toResponse();
        }

        // Email/SMS: codes are stored as Argon2id hashes, so they must be verified through the service
        // (which also enforces the expiration time), never compared as plain strings.
        if (($type === 'email' || $type === 'sms') && $this->twoFAService->validate2FACode($user, $code)) {
            $user->setTwoFAtype(
                $type === 'email'
                    ? UserTwoFactorAuthenticationStatus::EMAIL->value
                    : UserTwoFactorAuthenticationStatus::SMS->value
            );
            $this->entityManager->flush();

            // Defines the Event to the table
            $eventMetadata = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $user->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::ENABLE_LOCAL_2FA->value,
                new DateTime(),
                $eventMetadata
            );

            return new BaseResponse(
                200,
                [
                    'message' => 'Two Factor authentication validated successfully!',
                ]
            )->toResponse();
        }

        $secret = $user->getTwoFAsecret();
        if ($type === 'totp' && $secret !== null && $this->TOTPService->verifyTOTP($secret, $code)) {
            $user->setTwoFAtype(UserTwoFactorAuthenticationStatus::TOTP->value);
            $this->entityManager->flush();

            // Defines the Event to the table
            $eventMetadata = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $user->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::ENABLE_TOTP_2FA->value,
                new DateTime(),
                $eventMetadata
            );

            return new BaseResponse(
                200,
                [
                    'message' => 'Two Factor authentication validated successfully!',
                ]
            )->toResponse();
        }

        return new BaseResponse(
            403,
            null,
            'Invalid code'
        )->toResponse();
    }
}
