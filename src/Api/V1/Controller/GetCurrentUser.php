<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class GetCurrentUser extends AbstractController
{
    public function __invoke(): JsonResponse
    {
        // Get the current logged-in user
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            $response = new BaseResponse(403, [
                'errors' => [
                    [
                        'status' => '403',
                        'title' => 'Access Denied',
                        'detail' => 'You are not authenticated.',
                    ]
                ]
            ]);
            return $response->toResponse();
        }

        $content = [
            'Entity' => 'User',
            'id' => (string)$currentUser->getId(),
            'attributes' => [
                'uuid' => $currentUser->getUuid(),
                'email' => $currentUser->getEmail(),
                'roles' => $currentUser->getRoles(),
                'isVerified' => $currentUser->isVerified(),
                'saml_identifier' => $currentUser->getSamlIdentifier(),
                'google_id' => $currentUser->getGoogleId(),
                'phone_number' => $currentUser->getPhoneNumber(),
                'first_name' => $currentUser->getFirstName(),
                'last_name' => $currentUser->getLastName(),
                'user_radius_profiles' => $currentUser->getUserRadiusProfiles(),
                'user_external_auths' => $currentUser->getUserExternalAuths(),
                'verification_code' => $currentUser->getVerificationCode(),
                'created_at' => $currentUser->getCreatedAt(),
                'banned_at' => $currentUser->getBannedAt(),
                'deleted_at' => $currentUser->getDeletedAt(),
                'forgot_password_request' => $currentUser->isForgotPasswordRequest(),
            ]
        ];

        $response = new BaseResponse(200, [
            'Entity' => 'User',
            'status' => 200,
            'content' => $content,
        ]);

        return $response->toResponse();
    }
}