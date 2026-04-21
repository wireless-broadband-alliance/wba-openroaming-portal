<?php

declare(strict_types=1);

namespace App\Enum;

enum SettingsConfigType: string
{
    case TRUSTED_PROXIES = 'TRUSTED_PROXIES';
    case TURNSTILE_KEY = 'TURNSTILE_KEY';
    case TURNSTILE_SECRET = 'TURNSTILE_SECRET';
    case JWT_SECRET_KEY = 'JWT_SECRET_KEY';
    case JWT_PUBLIC_KEY = 'JWT_PUBLIC_KEY';
    case JWT_PASSPHRASE = 'JWT_PASSPHRASE';
}
