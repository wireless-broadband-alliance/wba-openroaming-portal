<?php

namespace App\Service;

use App\Repository\SettingRepository;
use OTPHP\TOTP;

class TOTPService
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
    ) {
    }

    /**
     * TOTP -> Time-Based One-Time Password
     * Service that communicates with 2fa applications
     */
    public function generateSecret(): string
    {
        return TOTP::create()->getSecret();
    }

    public function generateTOTP(string $secret): string
    {
        // Create an identifier code to communicate with the app
        $totp = TOTP::create($secret);
        // Identifier labels in the communication
        $totp->setLabel($this->settingRepository->findOneBy(['name' => 'TWO_FACTOR_AUTH_APP_LABEL']));
        $totp->setIssuer($this->settingRepository->findOneBy(['name' => 'TWO_FACTOR_AUTH_APP_ISSUER']));

        return $totp->getProvisioningUri(); // URI for QR Code
    }

    public function verifyTOTP(string $secret, string $code): bool
    {
        // communication with the app using the user secret code to verify the code introduced
        return TOTP::create($secret)->verify($code);
    }
}
