<?php

declare(strict_types=1);

namespace App\Enum;

enum OSType: string
{
    case ANDROID = 'Android';
    case IOS = 'iOS';
    case LINUX = 'Linux';
    case MACOS = 'macOS';
    case WINDOWS = 'Windows';
    case NONE = 'none';
}
