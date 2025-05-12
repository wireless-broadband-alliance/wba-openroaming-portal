<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Controller\GoogleController;
use App\Controller\MicrosoftController;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\UserProvider;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\JWTTokenGenerator;
use App\Service\SamlResolverService;
use App\Service\UserDeletionService;
use App\Service\UserStatusChecker;
use DateTime;
use JsonException;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\ValidationError;
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
        private readonly SamlResolverService $samlResolverService,
        private readonly GoogleController $googleController,
        private readonly MicrosoftController $microsoftController,
        private readonly jwtTokenGenerator $tokenGenerator,
    ) {
    }

    /**
     * @throws ValidationError
     * @throws Error
     * @throws JsonException
     */
    #[Route('/api/v1/userAccount/deletion', name: 'api_user_account_deletion', methods: ['POST'])]
    public function userAccountDeletion(Request $request, Auth $samlAuth): JsonResponse
    {
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
            if (!$isAdminAccount instanceof User) {
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

            foreach ($currentUser->getUserExternalAuths() as $externalAuth) {
                if ($externalAuth->getProvider() === UserProvider::PORTAL_ACCOUNT->value) {
                    try {
                        $data = json_decode(
                            $request->getContent(),
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        );
                    } catch (JsonException) {
                        return new BaseResponse(
                            400,
                            null,
                            'Invalid JSON format'
                        )->toResponse();
                    }

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
                    // Get SAML Response
                    $samlResponseBase64 = $request->request->get('SAMLResponse');
                    if (!$samlResponseBase64) {
                        return new BaseResponse(
                            400,
                            null,
                            'SAML Response not found'
                        )->toResponse();
                    }

                    $samlResponseData = $this->samlResolverService->decodeSamlResponse($samlResponseBase64);
                    $idpEntityId = $samlResponseData['idp_entity_id'];
                    $idpCertificate = $samlResponseData['certificate'];

                    // Compare entity IDs
                    if ($this->getParameter('app.saml_idp_entity_id') !== $idpEntityId) {
                        return new BaseResponse(
                            403,
                            null,
                            'The configured IDP Entity ID does not match the expected value. Access denied.'
                        )->toResponse();
                    }

                    // Compare certificates
                    if ($this->getParameter('app.saml_idp_x509_cert') !== $idpCertificate) {
                        return new BaseResponse(
                            403,
                            null,
                            'The configured certificate does not match the expected value. Access denied.'
                        )->toResponse();
                    }

                    // Load and validate the SAML response
                    $samlAuth->processResponse();

                    // Handle errors from the SAML process
                    if ($samlAuth->getErrors()) {
                        return new BaseResponse(
                            401,
                            null,
                            'Unable to validate SAML assertion',
                        )->toResponse();
                    }

                    // Ensure the authentication was successful
                    if (!$samlAuth->isAuthenticated()) {
                        return new BaseResponse(
                            401,
                            null,
                            'Authentication Failed'
                        )->toResponse();
                    }

                    // Extract email from the SAML assertion attributes
                    $attributes = $samlAuth->getAttributes();
                    $email = $attributes['urn:oid:1.2.840.113549.1.9.1'][0] ?? null;

                    if ($email === null) {
                        return new BaseResponse(
                            400,
                            ['missing_field' => 'email'],
                            'The SAML assertion does not contain a valid email address.'
                        )->toResponse();
                    }

                    // Compare the SAML email with the current user's email
                    if ($email !== $currentUser->getEmail()) {
                        return new BaseResponse(
                            403,
                            null,
                            'Unauthorized: The SAML assertion email does not match the user account email.'
                        )->toResponse();
                    }
                }

                if ($externalAuth->getProvider() === UserProvider::GOOGLE_ACCOUNT->value) {
                    try {
                        $data = json_decode(
                            $request->getContent(),
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        );
                    } catch (JsonException) {
                        return new BaseResponse(
                            400,
                            null,
                            'Invalid JSON format'
                        )->toResponse();
                    }

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

                    // Authenticate the user using a custom Google authentication function already on the project
                    $this->googleController->authenticateUserGoogle($currentUser);

                    // Generate JWT Token
                    $token = $this->tokenGenerator->generateToken($currentUser);
                    if (is_array($token) && isset($token['success']) && $token['success'] === false) {
                        $statusCode = $token['error'] ===
                        'Invalid user provided. Please verify the user data.' ? 400 : 500;
                        return new BaseResponse($statusCode, null, $token['error'])->toResponse();
                    }
                }

                if ($externalAuth->getProvider() === UserProvider::MICROSOFT_ACCOUNT->value) {
                    try {
                        $data = json_decode(
                            $request->getContent(),
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        );
                    } catch (JsonException) {
                        return new BaseResponse(
                            400,
                            null,
                            'Invalid JSON format'
                        )->toResponse();
                    }

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

                    // Authenticate the user using a custom Microsoft authentication function already on the project
                    $this->microsoftController->authenticateUserMicrosoft($currentUser);

                    // Generate JWT Token
                    $token = $this->tokenGenerator->generateToken($currentUser);
                    if (is_array($token) && isset($token['success']) && $token['success'] === false) {
                        $statusCode = $token['error'] ===
                        'Invalid user provided. Please verify the user data.' ? 400 : 500;
                        return new BaseResponse($statusCode, null, $token['error'])->toResponse();
                    }
                }
            }

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
                ['user_uuid' => $userUUID],
                sprintf('User with UUID "%s" successfully deleted.', $userUUID)
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
