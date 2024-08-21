<?php

namespace App\Api\V1\Controller;

use App\Entity\UserExternalAuth;
use App\Enum\UserProvider;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\CaptchaValidator;
use App\Service\JWTTokenGenerator;
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
     * @return JsonResponse
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/api/v1/auth/saml', name: 'api_auth_saml', methods: ['POST'])]
    public function authSaml(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['cf-turnstile-response'])) {
            throw new BadRequestHttpException('CAPTCHA validation failed!');
        }

        if (!$this->captchaValidator->validate($data['cf-turnstile-response'], $request->getClientIp())) {
            throw new BadRequestHttpException('CAPTCHA validation failed!');
        }

        if (!isset($data['sAMAccountName'])) {
            return new JsonResponse(['error' => 'Invalid data'], 422);
        }

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['provider_id' => $data['sAMAccountName']]);

        if (!$userExternalAuth) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $user = $userExternalAuth->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'This user does not have a provider associated'], 404);
        }

        $token = $this->tokenGenerator->generateToken($user);

        // Use the toApiResponse method to generate the response
        $responseData = $user->toApiResponse(['token' => $token]);

        return new JsonResponse($responseData, 200);
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
