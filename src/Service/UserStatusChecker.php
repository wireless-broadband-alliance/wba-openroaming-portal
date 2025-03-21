<?php

namespace App\Service;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Enum\UserProvider;
use App\Repository\UserRepository;
use DateTimeInterface;

class UserStatusChecker
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    public function checkUserStatus(User $user): ?BaseResponse
    {
        if (!$user->isVerified()) {
            return new BaseResponse(
                401,
                null,
                'User account is not verified.'
            );
        }

        if ($user->getBannedAt() instanceof DateTimeInterface) {
            return
                new BaseResponse(
                    403,
                    null,
                    'User account is banned from the system.'
                );
        }

        // Checks if the user has a "forgot_password_request", if yes, send an error with the authentication
        if ($this->userRepository->findOneBy(['id' => $user->getId(), 'forgot_password_request' => true])) {
            return
                new BaseResponse(
                    403,
                    null,
                    'Your request cannot be processed at this time due to a pending action.' .
                    ' If your account is active, re-login to complete the action.'
                );
        }

        return null;
    }

    public function portalAccountType(User $user): false|string
    {
        $userExternalAuths = $user->getUserExternalAuths();

        foreach ($userExternalAuths as $userExternalAuth) {
            if ($userExternalAuth->getProvider() === UserProvider::PORTAL_ACCOUNT->value) {
                if ($userExternalAuth->getProviderId() === UserProvider::EMAIL->value) {
                    return UserProvider::EMAIL->value;
                }

                if ($userExternalAuth->getProviderId() === UserProvider::PHONE_NUMBER->value) {
                    return UserProvider::PHONE_NUMBER->value;
                }
            }
        }
        return false;
    }
}
