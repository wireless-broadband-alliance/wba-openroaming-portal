<?php

namespace App\Api\V1\Controller;

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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
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
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['cf-turnstile-response'])) {
            throw new BadRequestHttpException('CAPTCHA validation failed!');
        }

        if (!$this->captchaValidator->validate($data['cf-turnstile-response'], $request->getClientIp())) {
            throw new BadRequestHttpException('CAPTCHA validation failed!');
        }

        if (!isset($data['uuid'], $data['password'])) {
            return new JsonResponse(['error' => 'Invalid data'], 400); # Bad Request Response
        }

        $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401); # Unauthorized Request Response
        }

        $token = $this->tokenGenerator->generateToken($user);

        $responseData = $user->toApiResponse([
            'token' => $token,
        ]);

        return new JsonResponse($responseData, 200);
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
            return new JsonResponse(['error' => 'SAML Response not found'], 400); // Bad Request
        }

        try {
            // Load and validate the SAML response
            $samlAuth->processResponse();

            // Handle errors from the SAML process
            if ($samlAuth->getErrors()) {
                return new JsonResponse([
                    'error' => 'Invalid SAML Assertion',
                    'details' => $samlAuth->getLastErrorReason()
                ], 401); // Unauthorized
            }

            // Ensure the authentication was successful
            if (!$samlAuth->isAuthenticated()) {
                throw new BadCredentialsException('Authentication Failed!');
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
            return new JsonResponse($responseData, 200);
        } catch (Exception $e) {
            return new JsonResponse([
                'error' => 'Unexpected error',
                'details' => $e->getMessage()
            ], 500); // Internal Server Error
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    #[Route('/api/v1/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function authGoogle(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['googleId'])) {
            return new JsonResponse(['error' => 'Invalid data'], 400);# Bad Request Response
        }

        if (!isset($data['cf-turnstile-response'])) {
            throw new BadRequestHttpException('CAPTCHA validation failed!');
        }

        if (!$this->captchaValidator->validate($data['cf-turnstile-response'], $request->getClientIp())) {
            throw new BadRequestHttpException('CAPTCHA validation failed!');
        }

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['provider_id' => $data['googleId']]);

        if (!$userExternalAuth) {
            return new JsonResponse(['error' => 'Authentication Failed!'], 401); // Unauthorized - Provider not Allowed
        }

        $user = $userExternalAuth->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Authentication Failed!'], 401); // Unauthorized - Provider not Allowed
        }

        $token = $this->tokenGenerator->generateToken($user);

        // Use the toApiResponse method to generate the response
        $responseData = $user->toApiResponse(['token' => $token]);

        return new JsonResponse($responseData, 200);
    }
}
