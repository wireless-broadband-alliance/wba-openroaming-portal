<?php

namespace App\Enum;

enum CodeVerificationType: string
{
    case TWO_FA_VERIFY_RESEND = 'verify2FA';
    case TWO_FA_DISABLE_RESEND = 'disable2FA';
    case TWO_FA_ENABLE_RESEND = 'enable2FA';
    case TWO_FA_VALIDATE_RESEND = 'validate2FA';
    case AUTO_DELETE_RESEND = 'accountDeletion';
}
