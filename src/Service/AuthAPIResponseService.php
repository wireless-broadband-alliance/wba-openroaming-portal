<?php

namespace App\Service;

use App\Api\V2\BaseResponse;
use App\Entity\RefreshJwtToken;
use App\Entity\User;
use App\Repository\RefreshJwtTokenRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

readonly class AuthAPIResponseService
{
    public function __construct(
        private JWTTokenGenerator $tokenGenerator,
        private EventActions $eventActions,
        private EntityManagerInterface $entityManager,
        private RefreshJwtTokenRepository $refreshJwtTokenRepository
    ) {
    }

    public function handleSuccessfulAuth(Request $request, User $user, string $eventType): JsonResponse
    {
        $now = new DateTimeImmutable();

      // Check for existing valid refresh token
        $existingTokenEntity = $this->refreshJwtTokenRepository->findOneBy([
        'user' => $user,
        'isRevoked' => false,
        ]);

        if ($existingTokenEntity && !$existingTokenEntity->isExpired()) {
          // Reuse existing token
            $accessToken = $existingTokenEntity->getAccessToken();
        } else {
          // Generate new JWT
            $jwt = $this->tokenGenerator->generateToken($user);
            if (is_array($jwt) && $jwt['success'] === false) {
                $errorMessage = $jwt['error'] ?? 'Token generation failed';
                $statusCode = $errorMessage === 'Invalid user provided. Please verify the user data.' ? 400 : 500;

                return new BaseResponse($statusCode, null, $errorMessage)->toResponse();
            }

            if (is_array($jwt)) {
                if (($jwt['success'] ?? false) === true && isset($jwt['token'])) {
                    $accessToken = $jwt['token'];
                } else {
                    $errorMessage = $jwt['error'] ?? 'Token generation failed';
                    $statusCode = $errorMessage === 'Invalid user provided. Please verify the user data.' ? 400 : 500;

                    return new BaseResponse($statusCode, null, $errorMessage)->toResponse();
                }
            } else {
                $accessToken = $jwt;
            }

          // Create new refresh token entity
            $tokenEntity = $existingTokenEntity ?: new RefreshJwtToken();
            $tokenEntity->setUser($user)
            ->setAccessToken($accessToken)
            ->setIsRevoked(false)
            ->setCreatedAt($now)
            ->setExpiredAt($now->modify('+30 days'));

            $this->entityManager->persist($tokenEntity);
            $this->entityManager->flush();
        }

        $eventMetadata = [
        'ip' => $request->getClientIp(),
        'user_agent' => $request->headers->get('User-Agent'),
        'uuid' => $user->getUuid(),
        ];

        $now = new DateTime();
        $this->eventActions->saveEvent($user, $eventType, $now, $eventMetadata);

      // 3. Return consistent API response
        $formattedUserData = $user->toApiResponse(['token' => $accessToken]);

        return new BaseResponse(200, $formattedUserData)->toResponse();
    }
}
