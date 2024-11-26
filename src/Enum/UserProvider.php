<?php

namespace App\Enum;

enum UserProvider
{
public const SAML = 'SAML Account';
public const GOOGLE_ACCOUNT = 'Google Account';
public const PORTAL_ACCOUNT = 'Portal Account';
public const EMAIL = 'Email';
public const PHONE_NUMBER = 'Phone Number';
}
