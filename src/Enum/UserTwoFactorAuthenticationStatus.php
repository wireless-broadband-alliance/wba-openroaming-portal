<?php

namespace App\Enum;

enum UserTwoFactorAuthenticationStatus: int
{
    case DISABLED = 0;
    case TOTP = 1;
    case SMS = 2;
    case EMAIL = 3;
}
