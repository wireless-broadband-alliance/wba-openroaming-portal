<?php

namespace App\Enum;

enum CodeVerificationType: string
{
    case IS_2FA_REQUEST_RESEND = 'IS_2FA_REQUEST_RESEND';
    case IS_USER_ACCOUNT_DELETION = 'IS_USER_ACCOUNT_DELETION';
}
