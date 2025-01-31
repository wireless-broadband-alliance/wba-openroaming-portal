<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Controller\GoogleController;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\UserProvider;
use App\Repository\UserRepository;
use App\Service\CaptchaValidator;
use App\Service\EventActions;
use App\Service\JWTTokenGenerator;
use App\Service\UserStatusChecker;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use OneLogin\Saml2\Auth;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class AuthController extends AbstractController
{
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private jwtTokenGenerator $tokenGenerator;
    private CaptchaValidator $captchaValidator;
    private EntityManagerInterface $entityManager;
    private GoogleController $googleController;
    private UserStatusChecker $userStatusChecker;
    private EventActions $eventActions;

    /**
     * @param UserRepository $userRepository
     * @param UserPasswordHasherInterface $passwordHasher
     * @param JWTTokenGenerator $tokenGenerator
     * @param CaptchaValidator $captchaValidator
     * @param EntityManagerInterface $entityManager
     * @param GoogleController $googleController
     * @param UserStatusChecker $userStatusChecker
     * @param EventActions $eventActions
     */
    public function __construct(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        jwtTokenGenerator $tokenGenerator,
        CaptchaValidator $captchaValidator,
        EntityManagerInterface $entityManager,
        GoogleController $googleController,
        UserStatusChecker $userStatusChecker,
        EventActions $eventActions,
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->tokenGenerator = $tokenGenerator;
        $this->captchaValidator = $captchaValidator;
        $this->entityManager = $entityManager;
        $this->googleController = $googleController;
        $this->userStatusChecker = $userStatusChecker;
        $this->eventActions = $eventActions;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/api/v1/auth/local', name: 'api_auth_local', methods: ['POST'])]
    public function authLocal(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return (new BaseResponse(400, null, 'Invalid JSON format'))->toResponse(); # Bad Request Response
        }

        if (!isset($data['turnstile_token'])) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed'))->toResponse(); # Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['turnstile_token'], $request->getClientIp())) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed'))->toResponse(); # Bad Request Response
        }


        // Check for missing fields and add them to the array errors
        if (empty($data['uuid'])) {
            $errors[] = 'uuid';
        }
        if (empty($data['password'])) {
            $errors[] = 'password';
        }
        if (!empty($errors)) {
            return (
            new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )
            )->toResponse();
        }

        // Check if user exists are valid
        $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

        if (!$user) {
            return (new BaseResponse(400, null, 'Invalid credentials'))->toResponse();
            // Bad Request Response
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return (new BaseResponse(401, null, 'Invalid credentials'))->toResponse(); # Unauthorized Request Response
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user);
        if ($statusCheckerResponse !== null) {
            return $statusCheckerResponse->toResponse();
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
            'uuid' => $user->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::AUTH_LOCAL_API,
            new DateTime(),
            $eventMetadata
        );

        // Return success response using BaseResponse
        return (new BaseResponse(200, $responseData))->toResponse(); # Success Response
    }

    /**
     * @param Request $request
     * @param Auth $samlAuth
     * @return JsonResponse
     */
    #[Route('/api/v1/auth/saml', name: 'api_auth_saml', methods: ['POST'])]
    public function authSaml(Request $request, Auth $samlAuth): JsonResponse
    {
        // Get SAML Response
        $samlResponseBase64 = $request->request->get('SAMLResponse');
        if (!$samlResponseBase64) {
            return (new BaseResponse(400, null, 'SAML Response not found'))->toResponse(); // Bad Request
        }

        try {
            // Load and validate the SAML response
            $samlAuth->processResponse();

            // Handle errors from the SAML process
            if ($samlAuth->getErrors()) {
                return (new BaseResponse(
                    401,
                    null,
                    'Invalid SAML Assertion',
                ))->toResponse(); // Unauthorized
            }

            // Ensure the authentication was successful
            if (!$samlAuth->isAuthenticated()) {
                return (new BaseResponse(
                    401,
                    null,
                    'Authentication Failed'
                ))->toResponse(); // Unauthorized
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
                    ->setProvider(UserProvider::SAML)
                    ->setProviderId($sAMAccountName);

                $this->entityManager->persist($userAuth);
                $this->entityManager->flush();
            }

            $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user);
            if ($statusCheckerResponse !== null) {
                return $statusCheckerResponse->toResponse();
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
                AnalyticalEventType::AUTH_SAML_API,
                new DateTime(),
                $eventMetadata
            );

            return (new BaseResponse(200, $responseData))->toResponse(); // Success
        } catch (Exception) {
            return (new BaseResponse(
                500,
                null,
                'SAML processing error',
            ))->toResponse(); // Internal Server Error
        }
    }

    #[Route('/api/v1/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function authGoogle(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return (new BaseResponse(400, null, 'Invalid JSON format'))->toResponse();
        }

        if (!isset($data['code'])) {
            return (new BaseResponse(400, null, 'Missing authorization code!'))->toResponse();
        }

        try {
            $user = $this->googleController->fetchUserFromGoogle($data['code']);
            if (!$user) {
                return (new BaseResponse(400, null, 'This code is not associated with a google account.'))->toResponse(
                );
            }

            $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user);
            if ($statusCheckerResponse !== null) {
                return $statusCheckerResponse->toResponse();
            }

            // Authenticate the user using custom Google authentication function already on the project
            $this->googleController->authenticateUserGoogle($user);

            $token = $this->tokenGenerator->generateToken($user);

            $formattedUserData = $user->toApiResponse(['token' => $token]);

            // Defines the Event to the table
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $user->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::AUTH_GOOGLE_API,
                new DateTime(),
                $eventMetadata
            );

            return (new BaseResponse(200, $formattedUserData, null))->toResponse();
        } catch (IdentityProviderException) {
            // Handle OAuth identity provider-specific errors
            return (new BaseResponse(500, null, 'Authentication failed'))->toResponse();
        } catch (Exception) {
            // Handle any other general errors
            return (new BaseResponse(500, null, 'An error occurred'))->toResponse();
        }
    }
}
