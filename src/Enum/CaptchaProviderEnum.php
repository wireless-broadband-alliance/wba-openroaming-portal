<?php

declare(strict_types=1);

namespace App\Enum;

enum CaptchaProviderEnum: string
{
  case OFF = 'OFF';
  case TURNSTILE = 'TURNSTILE';
  case RECAPTCHA3 = 'RECAPTCHA3';
}
