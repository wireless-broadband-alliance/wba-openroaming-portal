<?php

namespace App\Api\V4\Controller;

use App\Api\V4\BaseResponse;
use App\Controller\GoogleController;
use App\Controller\MicrosoftController;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\UserProvider;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\EmailGenerator;
use App\Service\EventActions;
use App\Service\JWTTokenGenerator;
use App\Service\SamlResolverService;
use App\Service\SendSMS;
use App\Service\UserDeletion\UserDeletionService;
use App\Service\UserStatusChecker;
use DateTime;
use Doctrine\ORM\Exception\ORMException;
use JsonException;
use libphonenumber\PhoneNumber;
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
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

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
        private readonly EmailGenerator $emailGenerator,
        private readonly SendSMS $sendSMS,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws ValidationError
     * @throws Error
     * @throws JsonException
     * @throws ORMException
     */
    #[Route('/userAccount/deletion', name: 'api_v4_user_account_deletion', methods: ['POST'])]
    public function userAccountDeletion(Request $request, Auth $samlAuth): JsonResponse
    {
        $token = $this->tokenStorage->getToken();

        if ($token instanceof TokenInterface && $token->getUser() instanceof User) {
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

            $user = $this->userRepository->findOneByUUIDExcludingAdmin($currentUser->getUuid());
            if (!$user instanceof User) {
                return new BaseResponse(
                    404,
                    null,
                    'Invalid Account: User account not found.'
                )->toResponse();
            }

            $userId = $user->getId(); // event log
            $userUUID = $user->getUuid(); // success message

            $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user);
            if ($statusCheckerResponse instanceof BaseResponse) {
                return $statusCheckerResponse->toResponse();
            }

            foreach ($user->getUserExternalAuths() as $externalAuth) {
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
                    if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
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

                    if (!is_string($samlResponseBase64) || trim($samlResponseBase64) === '') {
                        return new BaseResponse(
                            400,
                            null,
                            'SAML Response not found or invalid'
                        )->toResponse();
                    }

                    $samlResponseData = $this->samlResolverService->decodeSamlResponse(
                        $samlResponseBase64,
                        $this->getParameter('app.saml_idp_entity_id'),
                        $this->getParameter('app.saml_sp_entity_id')
                    );
                    $idpCertificate = $samlResponseData['certificate'];

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
                    if ($email !== $user->getEmail()) {
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
                    $this->googleController->authenticateUserGoogle($user);

                    // Generate JWT Token
                    $token = $this->JWTTokenGenerator->generateToken($user);
                    if (is_array($token) && $token['success'] === false) {
                        $errorMessage = $token['error'] ?? 'Unknown error';
                        $statusCode =
                            $errorMessage === 'Invalid user provided. Verify the user data.' ? 400 : 500;

                        return new BaseResponse($statusCode, null, $errorMessage)->toResponse();
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
                    $this->microsoftController->authenticateUserMicrosoft($user);

                    // Generate JWT Token
                    $token = $this->JWTTokenGenerator->generateToken($user);
                    if (is_array($token) && $token['success'] === false) {
                        $errorMessage = $token['error'] ?? 'Unknown error';
                        $statusCode =
                            $errorMessage === 'Invalid user provided. Verify the user data.' ? 400 : 500;

                        return new BaseResponse($statusCode, null, $errorMessage)->toResponse();
                    }
                }
            }

            // Call the user deletion service
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user->getId()]);
            // Notify the user before their data is wiped
            try {
                if ($user->getEmail() !== null) {
                    $this->emailGenerator->sendUserAccountDeletionConfirmationEmail($user);
                } elseif ($user->getPhoneNumber() instanceof PhoneNumber) {
                    $message = $this->translator->trans('sms_account_deletion', [], 'UserDeletionService');
                    $this->sendSMS->sendSmsNoValidation($user, $message);
                }
            } catch (Throwable) {
                // non-fatal — deletion continues regardless
            }

            $result = $this->userDeletionService->deleteUser(
                $user,
                $userExternalAuths,
                $request,
                $user
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
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::ID->value => $userId,
            ];

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_DELETION_API->value,
                new DateTime(),
                $eventMetadata
            );

            return new BaseResponse(
                200,
                [
                    'message' => sprintf('User with UUID "%s" successfully deleted.', $userUUID)
                ]
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
