<?php

namespace App\Api\V1\Controller;

use App\Enum\UserProvider;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\CaptchaValidator;
use App\Service\JWTTokenGenerator;
use DOMDocument;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use OneLogin\Saml2\Auth;

class AuthsController extends AbstractController
{
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private jwtTokenGenerator $tokenGenerator;
    private UserExternalAuthRepository $userExternalAuthRepository;
    private CaptchaValidator $captchaValidator;


    public function __construct(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        jwtTokenGenerator $tokenGenerator,
        UserExternalAuthRepository $userExternalAuthRepository,
        CaptchaValidator $captchaValidator,
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->tokenGenerator = $tokenGenerator;
        $this->userExternalAuthRepository = $userExternalAuthRepository;
        $this->captchaValidator = $captchaValidator;
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
            return new JsonResponse(['error' => 'Invalid data'], 422);
        }

        $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], 422);
        }

        $hasPortalAccount = false;
        foreach ($user->getUserExternalAuths() as $userExternalAuth) {
            if ($userExternalAuth->getProvider() === UserProvider::PORTAL_ACCOUNT) {
                $hasPortalAccount = true;
                break;
            }
        }

        if (!$hasPortalAccount) {
            return new JsonResponse(['error' => 'Invalid credentials'], 422);
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
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    #[Route('/api/v1/auth/saml', name: 'api_auth_saml', methods: ['POST'])]
    public function authSaml(Request $request, Auth $samlAuth): JsonResponse
    {
        // Validate CAPTCHA
        $captchaResponse = $request->request->get('cf-turnstile-response');
        if (!$captchaResponse || !$this->captchaValidator->validate($captchaResponse, $request->getClientIp())) {
            throw new BadRequestHttpException('CAPTCHA validation failed!');
        }

        // Get SAML Response
        $samlResponseBase64 = $request->request->get('SAMLResponse');
        if (!$samlResponseBase64) {
            return new JsonResponse(['error' => 'SAML Response not found'], 422);
        }

        // Decode Base64 SAML Response
        $samlResponseXml = base64_decode($samlResponseBase64);

        try {
            // Pass the decoded XML to the SAML Auth object
            $samlAuth->processResponse($samlResponseXml);

            // Handle errors
            if ($samlAuth->getErrors()) {
                return new JsonResponse([
                    'error' => 'Invalid SAML assertion',
                    'details' => $samlAuth->getLastErrorReason()
                ], 401);
            }

            // Ensure authentication is successful
            if (!$samlAuth->isAuthenticated()) {
                return new JsonResponse(['error' => 'Authentication failed'], 401);
            }

            // Extract user information from SAML assertion
            $sAMAccountName = $samlAuth->getNameId();

            // Look up the user
            $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['provider_id' => $sAMAccountName]);

            if (!$userExternalAuth) {
                return new JsonResponse(['error' => 'User not found'], 404);
            }

            $user = $userExternalAuth->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Provider not found'], 404);
            }

            // Generate JWT token
            $token = $this->tokenGenerator->generateToken($user);

            // Create API response
            $responseData = $user->toApiResponse(['token' => $token]);

            return new JsonResponse($responseData, 200);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'SAML processing error', 'details' => $e->getMessage()], 500);
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
            return new JsonResponse(['error' => 'Invalid data'], 422);
        }

        if (!isset($data['cf-turnstile-response'])) {
            throw new BadRequestHttpException('CAPTCHA validation failed!');
        }

        if (!$this->captchaValidator->validate($data['cf-turnstile-response'], $request->getClientIp())) {
            throw new BadRequestHttpException('CAPTCHA validation failed!');
        }

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['provider_id' => $data['googleId']]);

        if (!$userExternalAuth) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $user = $userExternalAuth->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Provider not found'], 404);
        }

        $token = $this->tokenGenerator->generateToken($user);

        // Use the toApiResponse method to generate the response
        $responseData = $user->toApiResponse(['token' => $token]);

        return new JsonResponse($responseData, 200);
    }
}
