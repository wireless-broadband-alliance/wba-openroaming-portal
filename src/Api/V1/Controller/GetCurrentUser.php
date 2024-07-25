<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class GetCurrentUser extends AbstractController
{
    private TokenStorageInterface $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke(): JsonResponse
    {
        $token = $this->tokenStorage->getToken();

        // Check if the token is present and is of the correct type
        if ($token instanceof TokenInterface && $token->getUser() instanceof User) {
            /** @var User $currentUser */
            $currentUser = $token->getUser();

            // Construct the response content with user details
            $content = [
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

            return (new BaseResponse(Response::HTTP_OK, $content))->toResponse();
        }

        // Handle the case where the user is not authenticated
        return new JsonResponse([
            'error' => 'Unauthorized',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
