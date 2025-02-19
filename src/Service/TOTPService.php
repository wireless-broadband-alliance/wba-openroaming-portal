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
        // Create an identifier code to communicate with the app
        $totp = TOTP::create($secret);
        // Identifier labels in the communication
        $totp->setLabel('OpenRoaming');
        $totp->setIssuer('OpenRoaming');

        return $totp->getProvisioningUri(); // URI for QR Code
    }

    public function verifyTOTP(string $secret, string $code): bool
    {
        // communication with the app using the user secret code to verify the code introduced
        return TOTP::create($secret)->verify($code);
    }
}
