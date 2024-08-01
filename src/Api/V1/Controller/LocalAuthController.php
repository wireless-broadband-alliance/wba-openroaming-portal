<?php

namespace App\Api\V1\Controller;

use App\Repository\UserRepository;
use App\Service\JWTTokenGenerator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class LocalAuthController extends AbstractController
{
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private jwtTokenGenerator $tokenGenerator;

    public function __construct(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        jwtTokenGenerator $tokenGenerator,
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->tokenGenerator = $tokenGenerator;
    }

    /**
     * @throws Exception
     */
    #[Route('/api/v1/auth/local', name: 'api_auth_local', methods: ['POST'])]
    public function authLocal(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['uuid'], $data['password'])) {
            return new JsonResponse(['error' => 'Invalid data'], 404);
        }

        $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials - Missing User'], 404);
        }

        $token = $this->tokenGenerator->generateToken($user);

        $responseData = [
            'token' => $token,
            'user' => [
                'uuid' => $user->getUuid(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'isVerified' => $user->isVerified(),
                'createdAt' => $user->getCreatedAt(),
            ]
        ];

        return new JsonResponse($responseData, 200);
    }
}
