<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use RuntimeException;
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

        $this->publicKeyJwtPath = file_exists("$projectDir/config/jwt/public.pem")
            ? "$projectDir/config/jwt/public.pem"
            : $this->parameterBag->get('app.jwt_public_key');

        $this->privateKeyJwtPath = file_exists("$projectDir/config/jwt/private.pem")
            ? "$projectDir/config/jwt/private.pem"
            : $this->parameterBag->get('app.jwt_secret_key');
    }

    /**
     * @return string|array{success: bool, error?: string, token?: string}
     */
    public function generateToken(UserInterface $user): string|array
    {
        if (!$user instanceof User) {
            return [
                'success' => false,
                'error' => 'Invalid user provided. Verify the user data.',
            ];
        }

        if (!file_exists($this->privateKeyJwtPath) || !file_exists($this->publicKeyJwtPath)) {
            return [
                'success' => false,
                'error' => 'JWT key files are missing. Please ensure both private and public keys exist.',
            ];
        }

        $customPayload = [
            'id' => $user->getId(),
            'uuid' => $user->getUuid(),
            'password_identifier' => $this->generatePasswordNonce($user->getPassword()),
            'exp' => time() + (int)$this->parameterBag->get('app.jwt_expiration'),
        ];

        return $this->jwtManager->createFromPayload($user, $customPayload);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeToken(string $token): ?array
    {
        try {
            return $this->JWTEncoder->decode($token) ?: null;
        } catch (JWTDecodeFailureException) {
            return null;
        }
    }

    public function isJWTTokenValid(string $token): bool
    {
        // Decode the JWT payload
        $decodedPayload = $this->decodeToken($token);
        if (!$decodedPayload) {
            return false;
        }

        // Extract the UUID and derived password nonce from the token
        $uuid = $decodedPayload['uuid'] ?? null;
        $tokenNonce = $decodedPayload['password_identifier'] ?? null;

        // Token must contain both UUID and nonce
        if (!$uuid || !$tokenNonce) {
            return false;
        }

        // Retrieve the user by UUID
        $user = $this->userRepository->findOneBy(['uuid' => $uuid]);
        if (!$user) {
            return false;
        }

        // Recompute the expected nonce from the current password hash
        $expectedNonce = $this->generatePasswordNonce($user->getPassword());

        // Compare the token's nonce with the expected nonce securely
        return hash_equals($expectedNonce, $tokenNonce);
    }

    private function generatePasswordNonce(string $passwordHash): string
    {
        // Server secret used to derive the nonce (must be different from the JWT secret)
        $appSecret = (string) $this->parameterBag->get('kernel.secret');

        // Apply HMAC to the password hash using the server secret
        $hmac = hash_hmac('sha256', $passwordHash, $appSecret);

        // Extract a portion of the HMAC result to avoid exposing the full hash
        $partial = substr($hmac, 8, 32);

        // Hash the substring to produce the final nonce
        return hash('sha256', $partial);
    }
}
