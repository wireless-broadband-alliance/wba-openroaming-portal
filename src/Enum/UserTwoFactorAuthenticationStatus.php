<?php

namespace App\Enum;

enum UserTwoFactorAuthenticationStatus: int
{
    case DISABLED = 0;
    case APP = 1;
    case SMS = 2;
    case EMAIL = 3;
}
