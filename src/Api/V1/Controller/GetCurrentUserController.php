<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Service\EventActions;
use App\Service\JWTTokenGenerator;
use App\Service\UserStatusChecker;
use DateTime;
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
    private JWTTokenGenerator $JWTTokenGenerator;
    private EventActions $eventActions;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        UserStatusChecker $userStatusChecker,
        JWTTokenGenerator $JWTTokenGenerator,
        EventActions $eventActions
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->userStatusChecker = $userStatusChecker;
        $this->JWTTokenGenerator = $JWTTokenGenerator;
        $this->eventActions = $eventActions;
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
            // This line is begin ignore because the getCredentials belongs to another service
            /** @phpstan-ignore-next-line */
            $jwtTokenString = $token->getCredentials();

            if (!$this->JWTTokenGenerator->isJWTTokenValid($jwtTokenString)) {
                return (new BaseResponse(
                    401,
                    null,
                    'JWT Token is invalid!'
                ))->toResponse();
            }

            $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($currentUser);
            if ($statusCheckerResponse !== null) {
                return $statusCheckerResponse->toResponse();
            }

            // Utilize the toApiResponse method to generate the response content
            $content = $currentUser->toApiResponse([
                'phone_number' => $currentUser->getPhoneNumber(),
                'is_verified' => $currentUser->isVerified(),
                'created_at' => $currentUser->getCreatedAt()?->format(DATE_ATOM),
                'forgot_password_request' => $currentUser->isForgotPasswordRequest(),
            ]);

            // Defines the Event to the table
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::GET_USER_API,
                new DateTime(),
                $eventMetadata
            );

            return (new BaseResponse(200, $content))->toResponse();
        }

        // Handle the case where the user is not authenticated
        return (new BaseResponse(
            403,
            null,
            'Unauthorized - You do not have permission to access this resource'
        ))->toResponse(); // Bad Request Response
    }
}
