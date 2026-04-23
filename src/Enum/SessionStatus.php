<?php

declare(strict_types=1);

namespace App\Enum;

enum SessionStatus: string
{
    case VERIFIED = 'session_verified';
    case FORGOT_PASSWORD_UUID = 'forgot_password_uuid';
    case TWO_FACTOR_CONTEXT = '2fa_context';
    case SYSTEM_RESET_REQUEST = 'system_reset_request';
    case INSTALLATION_STARTED = 'session_installation_started';
    case CERTIFICATE_STARTED = 'session_certificate_started';
    case INSTALLATION_VERIFICATION = 'installation_verification';
    case CERTIFICATE_VERIFICATION = 'certificate_verification';
    case FREERADIUS_SETUP_PROCESS_TYPE = 'freeradiusSetupProcessType';
}
