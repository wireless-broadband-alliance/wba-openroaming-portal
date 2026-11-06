<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The CAPTCHA "action" each protected endpoint expects.
 *
 * The browser widget sends it when the token is created (Turnstile `action`, reCAPTCHA v3
 * `grecaptcha.execute(..., {action})`), the provider echoes it back in siteverify, and the server
 * compares it with the endpoint's own value. A token solved for one form can therefore not be
 * replayed on another endpoint (e.g. a login token on the SMS reset).
 *
 * Values use only letters and underscores, as reCAPTCHA requires.
 */
enum CaptchaAction: string
{
    // Web forms
    case LOGIN = 'login';
    case DASHBOARD_LOGIN = 'dashboard_login';
    case MAGIC_LINK = 'magic_link';
    case REGISTER = 'register';
    case REGISTER_SMS = 'register_sms';
    case FORGOT_PASSWORD_EMAIL = 'forgot_password_email';
    case FORGOT_PASSWORD_SMS = 'forgot_password_sms';
    case CHANGE_PASSWORD = 'change_password';
    case RESET_PASSWORD = 'reset_password';

    // API (apps must send these as the reCAPTCHA v3 action)
    case API_LOGIN = 'api_login';
    case API_REGISTER = 'api_register';
    case API_REGISTER_SMS = 'api_register_sms';
    case API_RESET_EMAIL = 'api_reset_email';
    case API_RESET_SMS = 'api_reset_sms';
    case API_TWO_FA = 'api_two_fa';

    public function isApi(): bool
    {
        return str_starts_with($this->value, 'api_');
    }
}
