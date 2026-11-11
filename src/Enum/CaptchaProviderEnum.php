<?php

declare(strict_types=1);

namespace App\Enum;

enum CaptchaProviderEnum: string
{
    case OFF = 'OFF';
    case TURNSTILE = 'TURNSTILE';
    case RECAPTCHA3 = 'RECAPTCHA3';

    public function isTurnstile(): bool
    {
        return $this === self::TURNSTILE;
    }

    public function isRecaptcha3(): bool
    {
        return $this === self::RECAPTCHA3;
    }

    public function isOff(): bool
    {
        return $this === self::OFF;
    }
}
