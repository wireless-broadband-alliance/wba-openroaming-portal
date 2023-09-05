<?php

namespace App\Enum;

enum EventsEnum
{
    public const DOWNLOAD_ANDROID = 'DOWNLOAD_PROFILE_ANDROID';
    public const DOWNLOAD_IOS = 'DOWNLOAD_PROFILE_IOS';
    public const DOWNLOAD_WINDOWS = 'DOWNLOAD_PROFILE_WINDOWS';
    public const USER_CREATION = 'USER_CREATION';
    public const USER_VERIFICATION = 'USER_VERIFICATION';
}
