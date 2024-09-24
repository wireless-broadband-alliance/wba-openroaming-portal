<?php

namespace App\Service;

use App\Api\V1\BaseResponse;
use App\Entity\User;

class UserStatusChecker
{
    public function checkUserStatus(User $user): ?BaseResponse
    {
        if (!$user->isVerified()) {
            return new BaseResponse(
                401,
                ['verification_code' => $user->getVerificationCode()],
                'User account is not verified.'
            );
        }

        if ($user->getBannedAt()) {
            return
                new BaseResponse(
                    401,
                    null,
                    'User account is banned from the system.'
                );
        }

        return null;
    }

    public function checkUserVerification(User $user): ?BaseResponse
    {
        if (!$user->isVerified()) {
            return new BaseResponse(
                401,
                ['verification_code' => $user->getVerificationCode()],
                'User account is not verified.'
            );
        }

        return null;
    }
}
