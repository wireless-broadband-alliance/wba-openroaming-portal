<?php

namespace App\Api\V1\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class GenerateJwtSamlController
{
    private EntityManagerInterface $entityManager;
    private JWTTokenManagerInterface $jwtTokenManager;

    public function __construct(EntityManagerInterface $entityManager, JWTTokenManagerInterface $jwtTokenManager)
    {
        $this->entityManager = $entityManager;
        $this->jwtTokenManager = $jwtTokenManager;
    }

    #[Route('/api/auth/saml', name: 'api_auth_saml', methods: ['POST'])]
    public function samlLogin(Request $request): JsonResponse
    {
        // Decode JSON request body
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $samlAccountName = $data['sAMAccountName'] ?? null;

        // Check if sAMAccountName is present
        if (!$samlAccountName) {
            throw new BadCredentialsException('sAMAccountName is missing');
        }

        // Find the user by sAMAccountName
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['saml_identifier' => $samlAccountName]);

        // Check if user exists
        if (!$user) {
            throw new BadCredentialsException('Invalid sAMAccountName');
        }

        // Generate JWT token
        $token = $this->jwtTokenManager->create($user);

        // Return token in response
        return new JsonResponse(['token' => $token]);
    }
}
