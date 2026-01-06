<?php

namespace App\Api\V2\Controller;

use App\Api\V2\BaseResponse;
use App\Service\JWTTokenGenerator;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AuthRefreshController extends AbstractController
{
    public function __construct(
        private readonly JWTTokenGenerator $jwtTokenGenerator,
        private readonly UserRepository $userRepository
    ) {
    }

    #[Route('/auth/refresh', name: 'api_v2_auth_refresh', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        // Decode request body
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $currentToken = $data['current_token'] ?? null;

        if (!$currentToken) {
            return new BaseResponse(400, null, 'The current_token is required')->toResponse();
        }

        // Validate the token using the service method
        if (!$this->jwtTokenGenerator->isJWTTokenValid($currentToken)) {
            return new BaseResponse(401, null, 'Invalid token')->toResponse();
        }

        // Decode the payload to check expiration and extract UUID
        $decodedPayload = $this->jwtTokenGenerator->decodeToken($currentToken);
        if (!$decodedPayload) {
            return new BaseResponse(401, null, 'Invalid token')->toResponse();
        }

        // Check token expiration
        $exp = $decodedPayload['exp'] ?? null;
        if (!$exp || $exp < time()) {
            return new BaseResponse(401, null, 'Token has expired')->toResponse();
        }

        // Find the user by UUID
        $uuid = $decodedPayload['uuid'] ?? null;
        if (!$uuid) {
            return new BaseResponse(401, null, 'Invalid token payload')->toResponse();
        }

        $user = $this->userRepository->findOneBy(['uuid' => $uuid]);
        if (!$user) {
            return new BaseResponse(401, null, 'User not found')->toResponse();
        }

        // Generate new token
        $newToken = $this->jwtTokenGenerator->generateToken($user);
        if (is_array($newToken)) {
            return new BaseResponse(
                500,
                null,
                $newToken['error'] ?? 'Token generation failed'
            )->toResponse();
        }

        return new BaseResponse(200, [
            'auth_token' => $newToken,
        ])->toResponse();
    }
}
