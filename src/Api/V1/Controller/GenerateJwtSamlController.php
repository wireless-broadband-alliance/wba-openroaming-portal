<?php

namespace App\Api\V1\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class GenerateJwtSamlController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private JWTTokenManagerInterface $jwtTokenManager;

    public function __construct(EntityManagerInterface $entityManager, JWTTokenManagerInterface $jwtTokenManager)
    {
        $this->entityManager = $entityManager;
        $this->jwtTokenManager = $jwtTokenManager;
    }

    public function __invoke(Request $request): JsonResponse
    {
        // Decode JSON request body
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $samlUuid = $data['samlUuid'] ?? null;

        // Check if samlUuid is present
        if (!$samlUuid) {
            throw new BadRequestHttpException('SAML UUID is missing');
        }

        // Find the user by SAML UUID
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['uuid' => $samlUuid]);

        // Check if user exists
        if (!$user) {
            throw new BadCredentialsException('Invalid SAML UUID');
        }

        // Generate JWT token
        $token = $this->jwtTokenManager->create($user);

        // Return token in response
        return new JsonResponse(['token' => $token]);
    }
}