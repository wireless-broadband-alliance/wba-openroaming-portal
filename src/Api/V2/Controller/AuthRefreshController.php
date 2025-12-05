<?php

namespace App\Api\V2\Controller;

use App\Api\V2\BaseResponse;
use App\Repository\RefreshJwtTokenRepository;
use App\Service\JWTTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AuthRefreshController extends AbstractController
{
    public function __construct(
        private readonly RefreshJwtTokenRepository $refreshTokenRepository,
        private readonly JWTTokenGenerator $jwtTokenGenerator,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

  /**
   * @throws RandomException
   * @throws \JsonException
   */
    #[Route('/auth/refresh', name: 'api_v2_auth_refresh', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $currentTokenValue = $data['current_token'] ?? null;

        if (!$currentTokenValue) {
            return new BaseResponse(400, null, 'The current_token is required')->toResponse();
        }

      // Fetch refresh token
        $accessToken = $this->refreshTokenRepository->findOneBy([
        'accessToken' => $currentTokenValue
        ]);

        if (!$accessToken || $accessToken->isExpired() || $accessToken->isRevoked()) {
            return new BaseResponse(401, null, 'Invalid or expired refresh token')->toResponse();
        }

        $user = $accessToken->getUser();
        if (!$user) {
            return new BaseResponse(401, null, 'Invalid user')->toResponse();
        }

        $accessToken->setIsRevoked(true);
        $this->entityManager->persist($accessToken);
        $this->entityManager->flush();

      // Generate new access token
        $newAccessToken = $this->jwtTokenGenerator->generateToken($user);

        if (is_array($newAccessToken)) {
            if ($newAccessToken['success'] === false) {
                return new BaseResponse(
                    500,
                    null,
                    $newAccessToken['error'] ?? 'Token generation failed'
                )->toResponse();
            }

            if (!isset($newAccessToken['token'])) {
                return new BaseResponse(500, null, 'Token generation failed')->toResponse();
            }

            $newAccessToken = $newAccessToken['token'];
        }

        if (!$newAccessToken) {
            return new BaseResponse(500, null, 'Token generation failed')->toResponse();
        }


      // Return new refresh token
        $newRefreshToken = $this->refreshTokenRepository->createForUser($user);

        return new BaseResponse(200, [
        'access_token' => $newAccessToken,
        'refresh_token' => $newRefreshToken->getAccessToken(),
        'expires_in' => 3600,
        ])->toResponse();
    }
}
