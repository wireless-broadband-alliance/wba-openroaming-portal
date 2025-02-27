<?php

namespace App\Enum;

enum UserVerificationStatus: string
{
    case VERIFIED = 'Verified';
    case BANNED = 'Banned';
    case NEED_VERIFICATION = 'Need Verification';
    case MISSING_PUBLIC_KEY_CONTENT = 'MISSING_PUBLIC_KEY_CONTENT';
    case EMPTY_PUBLIC_KEY_CONTENT = 'EMPTY_PUBLIC_KEY_CONTENT';
}
