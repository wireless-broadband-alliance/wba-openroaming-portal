<?php

namespace App\Service;

use App\Api\V3\BaseResponse;
use App\Entity\User;
use App\Enum\EventMetadataKeysType;
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
            EventMetadataKeysType::IP->value => $request->getClientIp(),
            EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
            EventMetadataKeysType::UUID->value => $user->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $user,
            $eventType,
            new DateTime(),
            $eventMetadata
        );

        return new BaseResponse(200, $formattedUserData)->toResponse();
    }
}
