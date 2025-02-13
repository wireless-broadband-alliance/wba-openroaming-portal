<?php

namespace App\Enum;

enum OSTypes: string
{
    case ANDROID = 'Android';
    case IOS = 'iOS';
    case LINUX = 'Linux';
    case MACOS = 'macOS';
    case WINDOWS = 'Windows';
    case NONE = 'none';
}
