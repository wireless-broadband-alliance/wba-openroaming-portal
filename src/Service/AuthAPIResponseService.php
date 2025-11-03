<?php

namespace App\Service;

use App\Api\V2\BaseResponse;
use App\Entity\User;
use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

readonly class AuthAPIResponseService
{
    public function __construct(
        private JWTTokenGenerator $tokenGenerator,
        private EventActions $eventActions
    ) {
    }

    public function handleSuccessfulAuth(Request $request, User $user, string $eventType): JsonResponse
    {
        $token = $this->tokenGenerator->generateToken($user);

        if (is_array($token) && $token['success'] === false) {
            $errorMessage = $token['error'] ?? 'Unknown error generating token';
            $statusCode = $errorMessage === 'Invalid user provided. Please verify the user data.' ? 400 : 500;

            return new BaseResponse($statusCode, null, $errorMessage)->toResponse();
        }

        $formattedUserData = $user->toApiResponse(['token' => $token]);

        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'uuid' => $user->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $user,
            $eventType,
            new DateTime(),
            $eventMetadata
        );

        return new BaseResponse(200, $formattedUserData, null)->toResponse();
    }
}
