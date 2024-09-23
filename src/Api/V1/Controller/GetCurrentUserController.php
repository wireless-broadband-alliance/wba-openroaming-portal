<?php

namespace App\Api\V1\Controller;

use ApiPlatform\Metadata\ApiResource;
use App\Api\V1\BaseResponse;
use App\Entity\User;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
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

            if (!$currentUser->isVerified()) {
                return (
                new BaseResponse(
                    401,
                    ['verification code' => $currentUser->getVerificationCode()],
                    'User account is not verified.'
                ))->toResponse();
            }

            if ($currentUser->getBannedAt()) {
                return (
                new BaseResponse(
                    401,
                    null,
                    'User account is banned from the system.'
                ))->toResponse();
            }

            // Utilize the toApiResponse method to generate the response content
            $content = $currentUser->toApiResponse([
                'phoneNumber' => $currentUser->getPhoneNumber(),
                'isVerified' => $currentUser->isVerified(),
                'bannedAt' => $currentUser->getBannedAt()?->format(DATE_ATOM),
                'deletedAt' => $currentUser->getDeletedAt()?->format(DATE_ATOM),
                'forgotPasswordRequest' => $currentUser->isForgotPasswordRequest(),
            ]);
            return (new BaseResponse(Response::HTTP_OK, $content))->toResponse();
        }

        // Handle the case where the user is not authenticated
        return new JsonResponse(['error' => 'Unauthorized'], 401);
    }
}
