<?php

namespace App\Enum;

enum UserTwoFactorAuthenticationStatus
{
    public const DISABLED = 'DISABLED';
    public const APP = 'APP';
    public const SMS = 'SMS';
    public const EMAIL = 'EMAIL';

}