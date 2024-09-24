<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Service\UserStatusChecker;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class GetCurrentUserController extends AbstractController
{
    private TokenStorageInterface $tokenStorage;
    private UserStatusChecker $userStatusChecker;

    public function __construct(TokenStorageInterface $tokenStorage, UserStatusChecker $userStatusChecker)
    {
        $this->tokenStorage = $tokenStorage;
        $this->userStatusChecker = $userStatusChecker;
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

            $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($currentUser);
            if ($statusCheckerResponse !== null) {
                return $statusCheckerResponse->toResponse();
            }

            // Utilize the toApiResponse method to generate the response content
            $content = $currentUser->toApiResponse([
                'phoneNumber' => $currentUser->getPhoneNumber(),
                'isVerified' => $currentUser->isVerified(),
                'createdAt' => $currentUser->getCreatedAt()?->format(DATE_ATOM),
                'forgotPasswordRequest' => $currentUser->isForgotPasswordRequest(),
            ]);
            return (new BaseResponse(200, $content))->toResponse();
        }

        // Handle the case where the user is not authenticated
        return (new BaseResponse(
            401,
            null,
            'Unauthorized - You do not have permission to access this resource'
        ))->toResponse(); // Bad Request Response
    }
}
