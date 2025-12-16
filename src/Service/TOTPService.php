<?php

namespace App\Service;

use App\Enum\SettingName;
use App\Repository\SettingRepository;
use InvalidArgumentException;
use OTPHP\TOTP;

readonly class TOTPService
{
    public function __construct(
        private SettingRepository $settingRepository,
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
        if ($secret === '' || $secret === '0') {
            throw new InvalidArgumentException('TOTP secret cannot be empty.');
        }

        $labelSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::TWO_FACTOR_AUTH_APP_LABEL->value
        ]);
        $issuerSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::TWO_FACTOR_AUTH_APP_ISSUER->value
        ]);

        $label = $labelSetting?->getValue();
        $issuer = $issuerSetting?->getValue();

        if (empty($label)) {
            throw new InvalidArgumentException('TOTP label cannot be empty.');
        }

        if (empty($issuer)) {
            throw new InvalidArgumentException('TOTP issuer cannot be empty.');
        }

        $totp = TOTP::create($secret);
        $totp->setLabel($label);
        $totp->setIssuer($issuer);

        return $totp->getProvisioningUri(); // URI for QR Code
    }

    public function verifyTOTP(string $secret, string $code): bool
    {
        if ($secret === '' || $secret === '0') {
            throw new InvalidArgumentException('TOTP secret cannot be empty.');
        }

        if ($code === '' || $code === '0') {
            throw new InvalidArgumentException('TOTP code cannot be empty.');
        }

        // communication with the app using the user secret code to verify the code introduced
        return TOTP::create($secret)->verify($code);
    }
}
