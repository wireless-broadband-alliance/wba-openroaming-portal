<?php

namespace App\Enum;

enum UserProvider
{
    public const SAML = 'SAML';
    public const Google_Account = 'Google Account';
    public const Portal_Account = 'Portal Account';
}
