<?php

namespace App\Enum;

enum User_Verification_Status
{
    public const VERIFIED = 'verified';
    public const BANNED = 'banned';

    public const MISSING_PUBLIC_KEY_CONTENT = 'MISSING_PUBLIC_KEY_CONTENT';
    public const EMPTY_PUBLIC_KEY_CONTENT = 'EMPTY_PUBLIC_KEY_CONTENT';
}
