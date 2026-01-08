<?php

namespace App\Api\V3\Controller;

use App\Api\V3\BaseResponse;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\CaptchaValidator;
use App\Service\EventActions;
use App\Service\JWTTokenGenerator;
use App\Service\TOTPService;
use App\Service\TwoFAService;
use App\Service\UserStatusChecker;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class TwoFAController extends AbstractController
{
    public function __construct(
        private readonly UserStatusChecker $userStatusChecker,
        private readonly TwoFAService $twoFAService,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly JWTTokenGenerator $JWTTokenGenerator,
        private readonly TOTPService $TOTPService,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly SettingRepository $settingRepository,
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
        name: 'api_v3_twoFA_enable',
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
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse(); # Bad Request Response
        }

        $turnstileSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::TURNSTILE_CHECKER->value
        ])->getValue();
        if (!$turnstileSetting) {
            throw new RuntimeException('Missing settings: TURNSTILE_CHECKER not found');
        }

        if ($turnstileSetting === OperationMode::ON->value) {
            if (!isset($data['turnstile_token'])) {
                return new BaseResponse(
                    400,
                    null,
                    'CAPTCHA validation failed'
                )->toResponse(); # Bad Request Response
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

        // Check if user exists are valid
        $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

        if (!$user instanceof User) {
            return new BaseResponse(401, null, 'Invalid credentials')->toResponse();
            // Bad Request Response
        }

        if (!$this->userPasswordHasher->isPasswordValid($user, $data['password'])) {
            return new BaseResponse(401, null, 'Invalid credentials')->toResponse(); # Unauthorized Request Response
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user);
        if ($statusCheckerResponse instanceof BaseResponse) {
            return $statusCheckerResponse->toResponse();
        }

        // Only manually update the 2fa totp if the user checks it
        if ($type === 'totp') {
            $secret = $this->TOTPService->generateSecret();
            $user->setTwoFAsecret($secret);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Defines the Event to the table
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $user->getUuid(),
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
                    'totpId' => $user->getTwoFAsecret()
                ]
            )->toResponse();
        }

        // Auto-detects if the user did select email||sms
        $this->twoFAService->generate2FACode(
            $user,
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
            AnalyticalEventType::TWO_FA_CODE_ENABLE->value
        );

        $provider = $user->getUserExternalAuths()[0]->getProvider();

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
        name: 'api_v3_twoFA_validate',
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

        $token = $this->tokenStorage->getToken();

        if ($token instanceof TokenInterface && $token->getUser() instanceof User) {
            /** @var User $currentUser */
            $currentUser = $token->getUser();
            // This line is begin ignore because the getCredentials belongs to another service
            /** @phpstan-ignore-next-line */
            $jwtTokenString = $token->getCredentials();

            if (!$this->JWTTokenGenerator->isJWTTokenValid($jwtTokenString)) {
                return new BaseResponse(
                    401,
                    null,
                    'JWT Token is invalid!'
                )->toResponse();
            }

            $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($currentUser);

            if (
                ($data['type'] === 'email' || $data['type'] === 'sms') &&
                $data['code'] === $currentUser->getTwoFAcode()
            ) {
                if ($data['type'] === 'email') {
                    $currentUser->setTwoFAtype(UserTwoFactorAuthenticationStatus::EMAIL->value);
                }
                if ($data['type'] === 'sms') {
                    $currentUser->setTwoFAtype(UserTwoFactorAuthenticationStatus::SMS->value);
                }
                // Defines the Event to the table
                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
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

            if (
                $data['type'] === 'totp' && $this->TOTPService->verifyTOTP(
                    $currentUser->getTwoFAsecret(),
                    $data['code']
                )
            ) {
                $currentUser->setTwoFAtype(UserTwoFactorAuthenticationStatus::TOTP->value);

                // Defines the Event to the table
                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
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

        // Handle the case where the user is not authenticated
        return new BaseResponse(
            403,
            null,
            'Unauthorized - You do not have permission to access this resource'
        )->toResponse(); // Bad Request Response
    }
}
