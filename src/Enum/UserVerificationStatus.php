<?php

namespace App\Enum;

enum UserVerificationStatus
{
public const VERIFIED = 'Verified';
public const BANNED = 'Banned';
public const NEED_VERIFICATON = 'Need Verification';

public const MISSING_PUBLIC_KEY_CONTENT = 'MISSING_PUBLIC_KEY_CONTENT';
public const EMPTY_PUBLIC_KEY_CONTENT = 'EMPTY_PUBLIC_KEY_CONTENT';
}
