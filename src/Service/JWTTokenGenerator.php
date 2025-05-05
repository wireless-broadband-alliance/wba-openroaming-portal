<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class JWTTokenGenerator
{
    private string $privateKeyJwtPath;
    private string $publicKeyJwtPath;

    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private JWTEncoderInterface $JWTEncoder,
        private UserRepository $userRepository,
        private KernelInterface $kernel,
        private ParameterBagInterface $parameterBag,
    ) {
        $projectDir = $this->kernel->getProjectDir();
        $this->publicKeyJwtPath = "$projectDir/config/jwt/public.pem" ?? $this->parameterBag->get(
            'app.jwt_public_key'
        );
        $this->privateKeyJwtPath = "$projectDir/config/jwt/private.pem" ?? $this->parameterBag->get(
            'app.jwt_secret_key'
        );
    }

    public function generateToken(UserInterface $user): string|array
    {
        if (!$user instanceof User) {
            return [
                'success' => false,
                'error' => 'Invalid user provided. Please verify the user data.',
            ];
        }

        // Check if both private and public keys exist
        if (!file_exists($this->privateKeyJwtPath) || !file_exists($this->publicKeyJwtPath)) {
            return [
                'success' => false,
                'error' => 'JWT key files are missing. Please ensure both private and public keys exist.',
            ];
        }

        $customPayload = [
            'password_hash' => $user->getPassword(),
        ];

        // Generate the JWT token with the current_hashed_password
        return $this->jwtManager->createFromPayload($user, $customPayload);
    }

    public function isJWTTokenValid(string $token): bool
    {
        try {
            $decodedPayload = $this->JWTEncoder->decode($token);
            if (!$decodedPayload) {
                return false;
            }

            $uuid = $decodedPayload['uuid'] ?? null;
            $tokenPassworHash = $decodedPayload['password_hash'] ?? null;

            if (!$uuid || !$tokenPassworHash) {
                return false; // Token does not contain any uuid or password -> is not valid
            }

            $user = $this->userRepository->findOneBy(['uuid' => $uuid]);
            if (!$user) {
                return false; // That user doesn't exist in the DB
            }

            $currentPasswordHash = $user->getPassword();
            return $currentPasswordHash === $tokenPassworHash;
        } catch (JWTDecodeFailureException) {
            return false;
        }
    }
}
