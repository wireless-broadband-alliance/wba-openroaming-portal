<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class UserAgentOsDetector
{
    public function isWindows10OrBelow(Request $request): bool
    {
        $platform = $request->headers->get('Sec-CH-UA-Platform', '');
        $isWindows = str_contains(strtolower($platform), 'windows');

        if (!$isWindows) {
            return false;
        }

        $platformVersion = $request->headers->get('Sec-CH-UA-Platform-Version', '');
        $majorVersion = (int) explode('.', trim($platformVersion, '"'))[0];

        // If no version hints available, assume Win11+ (don't block)
        if ($majorVersion === 0) {
            return false;
        }

        // Windows 11 reports major version 13+
        return $majorVersion <= 10;
    }

    public function isWindows(Request $request): bool
    {
        $platform = $request->headers->get('Sec-CH-UA-Platform', '');
        if (str_contains(strtolower($platform), 'windows')) {
            return true;
        }

        return str_contains($request->headers->get('User-Agent', ''), 'Windows');
    }
}
