<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Controller\GoogleController;
use App\Controller\MicrosoftController;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\UserProvider;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\UserRepository;
use App\Service\CaptchaValidator;
use App\Service\EventActions;
use App\Service\JWTTokenGenerator;
use App\Service\SamlResolverService;
use App\Service\TOTPService;
use App\Service\TwoFAAPIService;
use App\Service\TwoFAService;
use App\Service\UserStatusChecker;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JsonException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use OneLogin\Saml2\Auth;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly jwtTokenGenerator $tokenGenerator,
        private readonly CaptchaValidator $captchaValidator,
        private readonly EntityManagerInterface $entityManager,
        private readonly GoogleController $googleController,
        private readonly MicrosoftController $microsoftController,
        private readonly SamlResolverService $samlResolverService,
        private readonly UserStatusChecker $userStatusChecker,
        private readonly EventActions $eventActions,
        private readonly TwoFAAPIService $twoFAAPIService,
        private readonly TwoFAService $twoFAService,
        private readonly TOTPService $TOTPService
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws Exception
     */
    #[Route('/api/v1/auth/local', name: 'api_auth_local', methods: ['POST'])]
    public function authLocal(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse(); # Bad Request Response
        }

        if (!isset($data['turnstile_token'])) {
            return new BaseResponse(400, null, 'CAPTCHA validation failed')->toResponse(); # Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['turnstile_token'], $request->getClientIp())) {
            return new BaseResponse(400, null, 'CAPTCHA validation failed')->toResponse(); # Bad Request Response
        }

        $errors = [];
        // Check for missing fields and add them to the array errors
        if (empty($data['uuid'])) {
            $errors[] = 'uuid';
        }
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

        // Check if user exists are valid
        $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

        if (!$user instanceof User) {
            return new BaseResponse(400, null, 'Invalid credentials')->toResponse();
            // Bad Request Response
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return new BaseResponse(401, null, 'Invalid credentials')->toResponse(); # Unauthorized Request Response
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user);
        if ($statusCheckerResponse instanceof BaseResponse) {
            return $statusCheckerResponse->toResponse();
        }

        $twoFAEnforcementResult = $this->twoFAAPIService->twoFAEnforcementChecker(
            $user,
            $request->attributes->get('_route')
        );

        if ($twoFAEnforcementResult['success'] === false) {
            if ($twoFAEnforcementResult['missing_2fa_setting'] === true) {
                // Return error response when 2fa is missing the TWO_FACTOR_AUTH_STATUS setting
                return new BaseResponse(
                    400,
                    null,
                    $twoFAEnforcementResult['message']
                )->toResponse();
            }
            if (!isset($data['twoFACode'])) {
                return new BaseResponse(
                    400,
                    null,
                    'Missing Two-Factor Authentication code'
                )->toResponse(); # Bad Request Response
            }
            if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::APP->value) {
                if (
                    // Validation for OTPCodes -> 12 codes
                    !$this->twoFAService->validateOTPCodes($user, $data['twoFACode']) &&
                    // Validation for TOTP codes -> Generated By the App
                    !$this->TOTPService->verifyTOTP($user->getTwoFAsecret(), $data['twoFACode'])
                ) {
                    // Return error response only if both validations fail for APPS
                    return new BaseResponse(
                        401,
                        null,
                        $twoFAEnforcementResult['message']
                    )->toResponse();
                }
            } elseif (
                // Validation for 2FACode -> EMAIL/SMS
                !$this->twoFAService->validate2FACode($user, $data['twoFACode']) &&
                // Validation for OTPCodes -> 12 codes
                !$this->twoFAService->validateOTPCodes($user, $data['twoFACode'])
            ) {
                // Return error response only if both validations fail
                return new BaseResponse(
                    401,
                    null,
                    $twoFAEnforcementResult['message']
                )->toResponse();
            }
        }

        // Generate JWT Token
        $token = $this->tokenGenerator->generateToken($user);

        // Prepare response data
        $responseData = $user->toApiResponse([
            'token' => $token,
        ]);

        // Defines the Event to the table
        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'uuid' => $user->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::AUTH_LOCAL_API->value,
            new DateTime(),
            $eventMetadata
        );

        // Return success response using BaseResponse
        return new BaseResponse(200, $responseData)->toResponse(); # Success Response
    }

    #[
        Route('/api/v1/auth/saml', name: 'api_auth_saml', methods: ['POST'])]
    public function authSaml(Request $request, Auth $samlAuth): JsonResponse
    {
        // Get SAML Response
        $samlResponseBase64 = $request->request->get('SAMLResponse');
        if (!$samlResponseBase64) {
            return new BaseResponse(400, null, 'SAML Response not found')->toResponse();
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

        try {
            // Load and validate the SAML response
            $samlAuth->processResponse();

            // Handle errors from the SAML process
            if ($samlAuth->getErrors()) {
                return new BaseResponse(
                    401,
                    null,
                    'Unable to validate SAML assertion',
                )->toResponse(); // Unauthorized
            }

            // Ensure the authentication was successful
            if (!$samlAuth->isAuthenticated()) {
                return new BaseResponse(
                    401,
                    null,
                    'Authentication Failed'
                )->toResponse(); // Unauthorized
            }

            $sAMAccountName = $samlAuth->getNameId();
            $attributes = $samlAuth->getAttributes();

            // Extract necessary attributes
            $uuid = $attributes['samlUuid'][0] ?? null;
            $email = $attributes['urn:oid:1.2.840.113549.1.9.1'][0] ?? null;
            $firstName = $attributes['urn:oid:2.5.4.42'][0] ?? null;
            $lastName = $attributes['urn:oid:2.5.4.4'][0] ?? null;

            // Retrieve or create user based on SAML attributes
            $user = $this->userRepository->findOneBy(['uuid' => $uuid]);

            if (!$user) {
                // User does not exist, create a new user
                $user = new User();
                $user->setEmail($email);
                $user->setFirstName($firstName);
                $user->setLastName($lastName);
                $user->setPassword('notused');
                $user->setUuid($uuid);
                $user->setIsVerified(true);
                $user->setRoles([]);

                // Persist the new user
                $this->entityManager->persist($user);

                // Create and persist the UserExternalAuth entity
                $userAuth = new UserExternalAuth();
                $userAuth->setUser($user)
                    ->setProvider(UserProvider::SAML->value)
                    ->setProviderId($sAMAccountName);

                $this->entityManager->persist($userAuth);
                $this->entityManager->flush();
            }

            $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user);
            if ($statusCheckerResponse instanceof BaseResponse) {
                return $statusCheckerResponse->toResponse();
            }

            $twoFAEnforcementResult = $this->twoFAAPIService->twoFAEnforcementChecker(
                $user,
                $request->attributes->get('_route')
            );

            if ($twoFAEnforcementResult['success'] === false) {
                if ($twoFAEnforcementResult['missing_2fa_setting'] === true) {
                    // Return error response when 2fa is missing the TWO_FACTOR_AUTH_STATUS setting
                    return new BaseResponse(
                        400,
                        null,
                        $twoFAEnforcementResult['message']
                    )->toResponse();
                }

                $twoFACode = $request->request->get('twoFACode');
                if (!$twoFACode) {
                    return new BaseResponse(
                        400,
                        null,
                        'Missing Two-Factor Authentication code'
                    )->toResponse();
                }
                if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::APP->value) {
                    if (
                        // Validation for OTPCodes -> 12 codes
                        !$this->twoFAService->validateOTPCodes($user, $twoFACode) &&
                        // Validation for TOTP codes -> Generated By the App
                        !$this->TOTPService->verifyTOTP($user->getTwoFAsecret(), $twoFACode)
                    ) {
                        // Return error response only if both validations fail for APPS
                        return new BaseResponse(
                            401,
                            null,
                            $twoFAEnforcementResult['message']
                        )->toResponse();
                    }
                } elseif (
                    // Validation for 2FACode -> EMAIL/SMS
                    !$this->twoFAService->validate2FACode($user, $twoFACode) &&
                    // Validation for OTPCodes -> 12 codes
                    !$this->twoFAService->validateOTPCodes($user, $twoFACode)
                ) {
                    // Return error response only if both validations fail
                    return new BaseResponse(
                        401,
                        null,
                        $twoFAEnforcementResult['message']
                    )->toResponse();
                }
            }

            // Generate JWT token for the user
            $token = $this->tokenGenerator->generateToken($user);

            // Use the toApiResponse method to generate the response
            $responseData = $user->toApiResponse([
                'token' => $token,
            ]);

            // Defines the Event to the table
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $user->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::AUTH_SAML_API->value,
                new DateTime(),
                $eventMetadata
            );

            return new BaseResponse(200, $responseData)->toResponse(); // Success
        } catch (Exception) {
            return new BaseResponse(
                500,
                null,
                'SAML processing error',
            )->toResponse(); // Internal Server Error
        }
    }

    #[Route('/api/v1/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function authGoogle(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse();
        }

        if (!isset($data['code'])) {
            return new BaseResponse(400, null, 'Missing authorization code!')->toResponse();
        }

        try {
            $user = $this->googleController->fetchUserFromGoogle($data['code']);
            if (!$user instanceof User) {
                return new BaseResponse(400, null, 'This code is not associated with a google account.')->toResponse();
            }

            $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user);
            if ($statusCheckerResponse instanceof BaseResponse) {
                return $statusCheckerResponse->toResponse();
            }

            // Authenticate the user using custom Google authentication function already on the project
            $this->googleController->authenticateUserGoogle($user);

            $token = $this->tokenGenerator->generateToken($user);

            $formattedUserData = $user->toApiResponse(['token' => $token]);

            // Defines the Event to the table
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $user->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::AUTH_GOOGLE_API->value,
                new DateTime(),
                $eventMetadata
            );

            return new BaseResponse(200, $formattedUserData, null)->toResponse();
        } catch (IdentityProviderException) {
            // Handle OAuth identity provider-specific errors
            return new BaseResponse(500, null, 'Authentication failed')->toResponse();
        } catch (Exception) {
            // Handle any other general errors
            return new BaseResponse(500, null, 'An error occurred')->toResponse();
        }
    }

    #[Route('/api/v1/auth/microsoft', name: 'api_auth_microsoft', methods: ['POST'])]
    public function authMicrosoft(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse();
        }

        if (!isset($data['code'])) {
            return new BaseResponse(400, null, 'Missing authorization code!')->toResponse();
        }

        try {
            $user = $this->microsoftController->fetchUserFromMicrosoft($data['code']);
            if (!$user instanceof User) {
                return new BaseResponse(
                    400,
                    null,
                    'This code is not associated with a microsoft account.'
                )->toResponse();
            }

            $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user);
            if ($statusCheckerResponse instanceof BaseResponse) {
                return $statusCheckerResponse->toResponse();
            }

            // Authenticate the user using custom Google authentication function already on the project
            $this->microsoftController->authenticateUserMicrosoft($user);

            $token = $this->tokenGenerator->generateToken($user);

            $formattedUserData = $user->toApiResponse(['token' => $token]);

            // Defines the Event to the table
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $user->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::AUTH_MICROSOFT_API->value,
                new DateTime(),
                $eventMetadata
            );

            return new BaseResponse(200, $formattedUserData, null)->toResponse();
        } catch (IdentityProviderException) {
            // Handle OAuth identity provider-specific errors
            return new BaseResponse(500, null, 'Authentication failed')->toResponse();
        } catch (Exception) {
            // Handle any other general errors
            return new BaseResponse(500, null, 'An error occurred')->toResponse();
        }
    }
}
