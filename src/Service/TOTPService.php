<?php

namespace App\Service;

use OTPHP\TOTP;

class TOTPService
{
    public function generateSecret(): string
    {
        return TOTP::create()->getSecret();
    }

    public function generateTOTP(string $secret): string
    {
        $totp = TOTP::create($secret);
        $totp->setLabel('OpenRoaming');
        $totp->setIssuer('OpenRoaming');

        return $totp->getProvisioningUri(); // URI for QR Code
    }

    public function verifyTOTP(string $secret, string $code): bool
    {
        return TOTP::create($secret)->verify($code);
    }
}
