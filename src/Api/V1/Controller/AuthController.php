<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\UserProvider;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\CaptchaValidator;
use App\Service\JWTTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
    private UserExternalAuthRepository $userExternalAuthRepository;
    private CaptchaValidator $captchaValidator;
    private EntityManagerInterface $entityManager;

    /**
     * @param UserRepository $userRepository
     * @param UserPasswordHasherInterface $passwordHasher
     * @param JWTTokenGenerator $tokenGenerator
     * @param UserExternalAuthRepository $userExternalAuthRepository
     * @param CaptchaValidator $captchaValidator
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        jwtTokenGenerator $tokenGenerator,
        UserExternalAuthRepository $userExternalAuthRepository,
        CaptchaValidator $captchaValidator,
        EntityManagerInterface $entityManager,
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->tokenGenerator = $tokenGenerator;
        $this->userExternalAuthRepository = $userExternalAuthRepository;
        $this->captchaValidator = $captchaValidator;
        $this->entityManager = $entityManager;
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
            return (new BaseResponse(400, 'Invalid JSON format'))->toResponse(); # Bad Request Response
        }

        if (!isset($data['cf-turnstile-response'])) {
            return (new BaseResponse(400, 'CAPTCHA validation failed'))->toResponse(); # Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['cf-turnstile-response'], $request->getClientIp())) {
            return (new BaseResponse(400, 'CAPTCHA validation failed'))->toResponse(); # Bad Request Response
        }

        if (!isset($data['uuid'])) {
            return (new BaseResponse(400, null, 'Invalid data: Missing fields: uuid'))->toResponse(
            ); // Bad Request Response
        }

        if (!isset($data['password'])) {
            return (new BaseResponse(400, null, 'Invalid data: Missing fields: password'))->toResponse(
            );// Bad Request Response
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

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    #[Route('/api/v1/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function authGoogle(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['googleId'])) {
            return (new BaseResponse(400, null, 'Invalid data'))->toResponse(); // Bad Request Response
        }

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['provider_id' => $data['googleId']]);

        if (!$userExternalAuth) {
            // Unauthorized - Provider not allowed
            return (new BaseResponse(401, null, 'Authentication Failed!'))->toResponse();
        }

        $user = $userExternalAuth->getUser();

        if (!$user) {
            // Unauthorized - User not found
            return (new BaseResponse(401, null, 'Authentication Failed!'))->toResponse();
        }

        $token = $this->tokenGenerator->generateToken($user);

        // Use the toApiResponse method to generate the response
        $responseData = $user->toApiResponse(['token' => $token]);

        return (new BaseResponse(200, $responseData))->toResponse(); // Success
    }
}
