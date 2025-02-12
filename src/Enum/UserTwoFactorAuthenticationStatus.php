<?php

namespace App\Enum;

enum UserTwoFactorAuthenticationStatus: string
{
    case DISABLED = 'DISABLED';
    case APP = 'APP';
    case SMS = 'SMS';
    case EMAIL = 'EMAIL';

}