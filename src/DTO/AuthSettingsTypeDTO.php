<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AuthSettingsTypeDTO
{
    public ?string $AUTH_METHOD_SAML_ENABLED = null;
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $AUTH_METHOD_SAML_LABEL = null;
    public ?string $AUTH_METHOD_SAML_DESCRIPTION = null;
    public ?string $PROFILE_LIMIT_DATE_SAML = null;

    public ?string $AUTH_METHOD_GOOGLE_LOGIN_ENABLED = null;
    public ?string $AUTH_METHOD_GOOGLE_LOGIN_LABEL = null;
    public ?string $AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION = null;
    public ?string $VALID_DOMAINS_GOOGLE_LOGIN = null;
    public ?string $PROFILE_LIMIT_DATE_GOOGLE = null;

    public ?string $AUTH_METHOD_MICROSOFT_LOGIN_ENABLED = null;
    public ?string $AUTH_METHOD_MICROSOFT_LOGIN_LABEL = null;
    public ?string $AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION = null;
    public ?string $VALID_DOMAINS_MICROSOFT_LOGIN = null;
    public ?string $PROFILE_LIMIT_DATE_MICROSOFT = null;

    public ?string $AUTH_METHOD_REGISTER_ENABLED = null;
    public ?string $AUTH_METHOD_REGISTER_LABEL = null;
    public ?string $AUTH_METHOD_REGISTER_DESCRIPTION = null;
    public ?string $PROFILE_LIMIT_DATE_EMAIL = null;
    public ?string $EMAIL_TIMER_RESEND = null;
    public ?string $LINK_VALIDITY = null;

    public ?string $AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED = null;
    public ?string $AUTH_METHOD_LOGIN_TRADITIONAL_LABEL = null;
    public ?string $AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION = null;

    public ?string $LOGIN_WITH_UUID_ONLY = null;

    public ?string $AUTH_METHOD_SMS_REGISTER_ENABLED = null;
    public ?string $AUTH_METHOD_SMS_REGISTER_LABEL = null;
    public ?string $AUTH_METHOD_SMS_REGISTER_DESCRIPTION = null;
    public ?string $PROFILE_LIMIT_DATE_SMS = null;
}
