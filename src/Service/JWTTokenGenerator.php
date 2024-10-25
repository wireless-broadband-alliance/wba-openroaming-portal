<?php

namespace App\Service;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTTokenGenerator
{
    private JWTTokenManagerInterface $jwtManager;
    private JWTEncoderInterface $JWTEncoder;
    private UserRepository $userRepository;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        JWTEncoderInterface $JWTEncoder,
        UserRepository $userRepository
    ) {
        $this->jwtManager = $jwtManager;
        $this->JWTEncoder = $JWTEncoder;
        $this->userRepository = $userRepository;
    }

    public function generateToken(UserInterface $user): string
    {
        if (!$user instanceof User) {
            return (new BaseResponse(400, null, 'Expected an instance of App\Entity\User'))->toResponse();
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
        } catch (JWTDecodeFailureException $e) {
            return false;
        }
    }
}
