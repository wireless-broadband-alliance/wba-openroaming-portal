<?php

namespace App\Service;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Repository\UserRepository;

class UserStatusChecker
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
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

        if ($user->getBannedAt()) {
            return
                new BaseResponse(
                    403,
                    null,
                    'User account is banned from the system.'
                );
        }


        // Checks if the user has a "forgot_password_request", if yes, send a error with the authentication
        if ($this->userRepository->findOneBy(['id' => $user->getId(), 'forgot_password_request' => true])) {
            return
                new BaseResponse(
                    403,
                    null,
                    'Your request cannot be processed at this time, pending password request'
                );
        }

        return null;
    }
}
