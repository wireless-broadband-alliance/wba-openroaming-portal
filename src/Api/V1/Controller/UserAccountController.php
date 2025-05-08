<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\UserProvider;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\JWTTokenGenerator;
use App\Service\UserDeletionService;
use App\Service\UserStatusChecker;
use DateTime;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class UserAccountController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UserStatusChecker $userStatusChecker,
        private readonly JWTTokenGenerator $JWTTokenGenerator,
        private readonly EventActions $eventActions,
        private readonly UserRepository $userRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly UserDeletionService $userDeletionService,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @throws \JsonException
     */
    #[Route('/api/v1/userAccount/deletion', name: 'api_user_account_deletion', methods: ['POST'])]
    public function userAccountDeletion(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse(); # Bad Request Response
        }

        $token = $this->tokenStorage->getToken();

        if ($token instanceof TokenInterface && $token->getUser() instanceof User) {
            /** @var User $currentUser */
            $currentUser = $token->getUser();
            /** @phpstan-ignore-next-line */
            $jwtTokenString = $token->getCredentials();

            if (!$this->JWTTokenGenerator->isJWTTokenValid($jwtTokenString)) {
                return new BaseResponse(
                    401,
                    null,
                    'Invalid Request: JWT Token is invalid!'
                )->toResponse();
            }

            $userUUID = $currentUser->getUuid();
            $isAdminAccount = $this->userRepository->findOneByUUIDExcludingAdmin($userUUID);
            if (!$isAdminAccount) {
                return new BaseResponse(
                    404,
                    null,
                    'Invalid Account: User account not found.'
                )->toResponse();
            }

            $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($currentUser);
            if ($statusCheckerResponse instanceof BaseResponse) {
                return $statusCheckerResponse->toResponse();
            }

            if ($currentUser->getDeletedAt() !== null) {
                return new BaseResponse(
                    400,
                    null,
                    'This user has already been deleted.'
                )->toResponse();
            }

            foreach ($currentUser->getUserExternalAuths() as $externalAuth) {
                if ($externalAuth->getProvider() === UserProvider::PORTAL_ACCOUNT->value) {
                    $errors = [];
                    if (empty($data['password'])) {
                        $errors[] = 'password';
                    }
                    if ($errors !== []) {
                        return new BaseResponse(
                            400,
                            ['missing_fields' => $errors],
                            'Invalid data: Missing required fields.'
                        )->toResponse();
                    }

                    // Verify the password supplied matches the hashed password stored in the User entity
                    if (!$this->passwordHasher->isPasswordValid($currentUser, $data['password'])) {
                        return new BaseResponse(
                            401, // Unauthorized
                            null,
                            'Invalid credentials: The provided password is incorrect.'
                        )->toResponse();
                    }
                }

                if ($externalAuth->getProvider() === UserProvider::SAML->value) {
                    // TODO ask for payload -> check the saml assertion is valid the same email of uuid field
                    $errors = [];
                    // Check for missing fields and add them to the array errors
                    if (empty($data['samlAssertion'])) {
                        $errors[] = 'samlAssertion';
                    }
                    if ($errors !== []) {
                        return new BaseResponse(
                            400,
                            ['missing_fields' => $errors],
                            'Invalid data: Missing required fields.'
                        )->toResponse();
                    }
                    dd('saml account');
                }
                if ($externalAuth->getProvider() === UserProvider::GOOGLE_ACCOUNT->value) {
                    // TODO ask for payload -> check the google code if valid, make a request with the code
                    $errors = [];
                    // Check for missing fields and add them to the array errors
                    if (empty($data['code'])) {
                        $errors[] = 'code';
                    }
                    if ($errors !== []) {
                        return new BaseResponse(
                            400,
                            ['missing_fields' => $errors],
                            'Invalid data: Missing required fields.'
                        )->toResponse();
                    }
                    dd('google account');
                }
                if ($externalAuth->getProvider() === UserProvider::MICROSOFT_ACCOUNT->value) {
                    // TODO ask for payload -> check the microsoft code if vali, make a request with the code
                    $errors = [];
                    // Check for missing fields and add them to the array errors
                    if (empty($data['code'])) {
                        $errors[] = 'code';
                    }
                    if ($errors !== []) {
                        return new BaseResponse(
                            400,
                            ['missing_fields' => $errors],
                            'Invalid data: Missing required fields.'
                        )->toResponse();
                    }
                    dd('microsoft account');
                }
            }

            dd('starting user account deletion');

            // Call the user deletion service
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $currentUser->getId()]);
            $result = $this->userDeletionService->deleteUser(
                $currentUser,
                $userExternalAuths,
                $request,
                $currentUser
            );

            if (!$result['success']) {
                return new BaseResponse(
                    500, // Or use a more specific status code if applicable
                    null,
                    $result['message'] ?? 'An error occurred while deleting the user.'
                )->toResponse();
            }

            // Defines the Event to the table
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $userUUID,
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::USER_ACCOUNT_DELETION_API->value,
                new DateTime(),
                $eventMetadata
            );

            return new BaseResponse(
                200,
                null,
                'User successfully deleted.'
            )->toResponse();
        }

        // Handle the case where the user is not authenticated
        return new BaseResponse(
            403,
            null,
            'Unauthorized - You do not have permission to access this resource'
        )->toResponse(); // Bad Request Response
    }
}
