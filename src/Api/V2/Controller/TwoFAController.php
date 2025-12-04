<?php

namespace App\Api\V2\Controller;

use App\Api\V2\BaseResponse;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\UserProvider;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Service\EventActions;
use App\Service\JWTTokenGenerator;
use App\Service\TOTPService;
use App\Service\TwoFAService;
use App\Service\UserStatusChecker;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

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
    ) {
    }

    #[Route(
        '/twoFA/{type}',
        name: 'api_v2_twoFA_enable',
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
            if ($statusCheckerResponse instanceof BaseResponse) {
                return $statusCheckerResponse->toResponse();
            }

            // Only manually update the 2fa totp if the user checks it
            if ($type === 'totp') {
                $secret = $this->TOTPService->generateSecret();
                $currentUser->setTwoFAsecret($secret);
                $this->entityManager->persist($currentUser);
                $this->entityManager->flush();

              // Defines the Event to the table
                $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::TWO_FA_CODE_ENABLE->value,
                    new DateTime(),
                    $eventMetadata
                );

                return new BaseResponse(
                    200,
                    [
                    'message' => 'Two Factor TOTP Secret generated successfully',
                    'totpId' => $currentUser->getTwoFAsecret()
                    ]
                )->toResponse();
            }

            // Auto-detects if the user did select email||sms
            $this->twoFAService->generate2FACode(
                $currentUser,
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
                AnalyticalEventType::TWO_FA_CODE_ENABLE->value
            );

            $provider = $currentUser->getUserExternalAuths()[0]->getProvider();

            if ($provider === UserProvider::SAML->value) {
                $message = 'Two Factor Code sent to: ' . $currentUser->getUserExternalAuths()[0]->getProviderId();
            } else {
                $message = 'Two Factor Code sent to: ' . $currentUser->getUuid();
            }

            return new BaseResponse(
                200,
                [
                    'message' => $message,
                    ]
            )->toResponse();
        }

      // Handle the case where the user is not authenticated
        return new BaseResponse(
            403,
            null,
            'Unauthorized - You do not have permission to access this resource'
        )->toResponse(); // Bad Request Response
    }

    #[Route(
        '/twoFA/validate',
        name: 'api_v2_twoFA_validate',
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
            if ($statusCheckerResponse instanceof BaseResponse) {
                return $statusCheckerResponse->toResponse();
            }

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
