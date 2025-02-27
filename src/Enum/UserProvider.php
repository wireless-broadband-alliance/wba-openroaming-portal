<?php

namespace App\Enum;

enum UserProvider: string
{
    case SAML = 'SAML Account';
    case GOOGLE_ACCOUNT = 'Google Account';
    case MICROSOFT_ACCOUNT = 'Microsoft Account';
    case PORTAL_ACCOUNT = 'Portal Account';
    case EMAIL = 'Email';
    case PHONE_NUMBER = 'Phone Number';
}
