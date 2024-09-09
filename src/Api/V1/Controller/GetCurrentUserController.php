<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class GetCurrentUserController extends AbstractController
{
    private TokenStorageInterface $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function __invoke(Request $request): JsonResponse
    {
        $token = $this->tokenStorage->getToken();

        if ($token instanceof TokenInterface && $token->getUser() instanceof User) {
            /** @var User $currentUser */
            $currentUser = $token->getUser();

            // Utilize the toApiResponse method to generate the response content
            $content = $currentUser->toApiResponse([
                'phone_number' => $currentUser->getPhoneNumber(),
                'user_radius_profiles' => $currentUser->getUserRadiusProfiles(),
                'verification_code' => $currentUser->getVerificationCode(),
                'banned_at' => $currentUser->getBannedAt(),
                'deleted_at' => $currentUser->getDeletedAt(),
                'forgot_password_request' => $currentUser->isForgotPasswordRequest(),
            ]);

            return (new BaseResponse(Response::HTTP_OK, $content))->toResponse();
        }

        // Handle the case where the user is not authenticated
        return new JsonResponse(['error' => 'Unauthorized'], 401);
    }
}
