<?php

namespace App\Api\V1\Controller;

use App\Service\JWTTokenGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class GenerateTokenController extends AbstractController
{
    private JWTTokenGenerator $tokenGenerator;

    public function __construct(JWTTokenGenerator $tokenGenerator)
    {
        $this->tokenGenerator = $tokenGenerator;
    }

    #[Route('/api/v1/auth/saml', name: 'generate_token_saml', methods: ['POST'])]
    public function generateJwtToken(): JsonResponse
    {
        // Get the authenticated user
        $user = $this->getUser();

        if (!$user) {
            throw new BadCredentialsException('User not authenticated');
        }

        // Generate JWT token
        $token = $this->tokenGenerator->generateToken($user);

        // Return token in response
        return new JsonResponse(['token' => $token]);
    }
}
