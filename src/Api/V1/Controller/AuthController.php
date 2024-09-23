<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Controller\GoogleController;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\UserProvider;
use App\Repository\UserRepository;
use App\Service\CaptchaValidator;
use App\Service\JWTTokenGenerator;
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

    /**
     * @param UserRepository $userRepository
     * @param UserPasswordHasherInterface $passwordHasher
     * @param JWTTokenGenerator $tokenGenerator
     * @param CaptchaValidator $captchaValidator
     * @param EntityManagerInterface $entityManager
     * @param GoogleController $googleController
     */
    public function __construct(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        jwtTokenGenerator $tokenGenerator,
        CaptchaValidator $captchaValidator,
        EntityManagerInterface $entityManager,
        GoogleController $googleController,
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->tokenGenerator = $tokenGenerator;
        $this->captchaValidator = $captchaValidator;
        $this->entityManager = $entityManager;
        $this->googleController = $googleController;
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

        if (!isset($data['turnstileToken'])) {
            return (new BaseResponse(400, null, 'CAPTCHA validation failed'))->toResponse(); # Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['turnstileToken'], $request->getClientIp())) {
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
                ['fieldsMissing' => $errors],
                'Invalid data: Missing required fields.'
            )
            )->toResponse();
        }

        // Check if user exists are valid
        $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

        if (!$user) {
            return (new BaseResponse(400, null, 'Invalid data: Missing User'))->toResponse();
            // Bad Request Response
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return (new BaseResponse(401, null, 'Invalid data: Invalid Password'))->toResponse(
            ); # Unauthorized Request Response
        }

        // Generate JWT Token
        $token = $this->tokenGenerator->generateToken($user);

        // Prepare response data
        $responseData = $user->toApiResponse([
            'token' => $token,
        ]);

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
                return (new BaseResponse(401, null, 'Invalid SAML Assertion', [
                    'details' => $samlAuth->getLastErrorReason()
                ]))->toResponse(); // Unauthorized
            }

            // Ensure the authentication was successful
            if (!$samlAuth->isAuthenticated()) {
                return (new BaseResponse(401, null, 'Authentication Failed'))->toResponse(); // Unauthorized
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
                $user->setRoles([]); // Set roles if needed or keep empty

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

            // Generate JWT token for the user
            $token = $this->tokenGenerator->generateToken($user);

            // Use the toApiResponse method to generate the response
            $responseData = $user->toApiResponse([
                'token' => $token,
            ]);

            return (new BaseResponse(200, $responseData))->toResponse(); // Success
        } catch (Exception $e) {
            return (new BaseResponse(500, null, 'Unexpected error', [
                'details' => $e->getMessage()
            ]))->toResponse(); // Internal Server Error
        }
    }

    #[Route('/api/v1/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function authGoogle(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return (new BaseResponse(400, null, 'Invalid JSON format'))->toResponse();
        }

        if (!isset($data['code'])) {
            return (new BaseResponse(400, null, 'Missing authorization code!'))->toResponse();
        }

        // Define a dummy code for testing purposes
        $dummyCode = 'openroaming';

        try {
            // Simulate a user for testing purposes if the dummy code is provided
            if ($data['code'] === $dummyCode) {
                $user = new User();
                $user->setUuid('john_doe@example.com')
                    ->setEmail('john_doe@example.com')
                    ->setFirstname('John')
                    ->setLastname('Doe')
                    ->setCreatedAt(new DateTime());

                $userExternalAuth = new UserExternalAuth();
                $userExternalAuth->setProvider(UserProvider::GOOGLE_ACCOUNT)
                    ->setProviderId('DUMMY_GOOGLE_USER_ACCOUNT');

                $user->addUserExternalAuth($userExternalAuth);
            } else {
                // Fetch real user info from Google using the provided authorization code
                $user = $this->googleController->fetchUserFromGoogle($data['code']);
            }

            // If user retrieval fails
            if ($user === null) {
                return (new BaseResponse(400, null, 'User creation failed or email is not allowed.'))->toResponse();
            }

            // Authenticate the user using custom Google authentication function already on the project
            $this->googleController->authenticateUserGoogle($user);

            $token = $this->tokenGenerator->generateToken($user);

            $formattedUserData = $user->toApiResponse(['token' => $token]);

            return (new BaseResponse(200, $formattedUserData, null))->toResponse();
        } catch (IdentityProviderException $e) {
            // Handle OAuth identity provider-specific errors
            return (new BaseResponse(500, null, 'Authentication failed: ' . $e->getMessage()))->toResponse();
        } catch (Exception $e) {
            // Handle any other general errors
            return (new BaseResponse(500, null, 'An error occurred: ' . $e->getMessage()))->toResponse();
        }
    }
}
