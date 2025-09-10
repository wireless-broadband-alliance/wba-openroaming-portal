<?php

namespace App\Service;

use App\Enum\OSType;

readonly class OSDetectionService
{
    public function detectDevice($userAgent): string
    {
        $os = OSType::NONE->value;

        // Windows
        if (preg_match('/windows|win32/i', (string)$userAgent)) {
            $os = OSType::WINDOWS->value;
        }

        // macOS
        if (preg_match('/macintosh|mac os x/i', (string)$userAgent)) {
            $os = OSType::MACOS->value;
        }

        // iOS
        if (preg_match('/iphone|ipod|ipad/i', (string)$userAgent)) {
            $os = OSType::IOS->value;
        }

        // Android
        if (preg_match('/android/i', (string)$userAgent)) {
            $os = OSType::ANDROID->value;
        }

        // Linux
//        if (preg_match('/linux/i', $userAgent)) {
//            $os = OSType::LINUX;
//        }

        return $os;
    }
}
