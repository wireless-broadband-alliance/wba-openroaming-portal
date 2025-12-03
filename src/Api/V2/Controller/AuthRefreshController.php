<?php

namespace App\Api\V2\Controller;

use App\Api\V2\BaseResponse;
use App\Repository\UserRepository;
use App\Service\JWTTokenGenerator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AuthRefreshController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly JWTTokenGenerator $jwtTokenGenerator
    ) {
    }

    #[Route('/auth/refresh', name: 'api_v2_auth_refresh', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return new BaseResponse(
                401,
                null,
                'Authorization header missing'
            )->toResponse();
        }

        $oldToken = substr($authHeader, 7);

        try {
            $payload = $this->jwtTokenGenerator->validateToken($oldToken);
        } catch (Exception) {
            return new BaseResponse(
                401,
                null,
                'Invalid token'
            )->toResponse();
        }

        $user = $this->userRepository->findOneBy(['uuid' => $payload['uuid']]);

        if (!$user) {
            return new BaseResponse(
                401,
                null,
                'Invalid user'
            )->toResponse();
        }

        // Generate new token
        $newToken = $this->jwtTokenGenerator->generateToken($user);

        // Handle both string or array response from generateToken()
        if (is_array($newToken)) {
            if ($newToken['success'] === false) {
                return new BaseResponse(
                    500,
                    null,
                    $newToken['error'] ?? 'Token generation failed'
                )->toResponse();
            }
            $newToken = $newToken['token'] ?? null;
        }

        if (!$newToken) {
            return new BaseResponse(500, null, 'Token generation failed')->toResponse();
        }

        return new BaseResponse(200, ['token' => $newToken])->toResponse();
    }
}
