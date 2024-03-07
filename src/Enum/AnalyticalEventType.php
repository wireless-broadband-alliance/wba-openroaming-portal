<?php

namespace App\Enum;

enum AnalyticalEventType
{
    public const DOWNLOAD_PROFILE = 'DOWNLOAD_PROFILE';
    public const USER_CREATION = 'USER_CREATION';
    public const USER_VERIFICATION = 'USER_VERIFICATION';
    public const USER_SMS_ATTEMPT = 'USER_SMS_ATTEMPT';
    public const USER_ACCOUNT_UPDATE = 'USER_ACCOUNT_UPDATE';
    public const USER_ACCOUNT_UPDATE_PASSWORD = 'USER_ACCOUNT_UPDATE_PASSWORD';
}
