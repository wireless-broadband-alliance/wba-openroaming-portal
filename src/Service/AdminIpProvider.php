<?php

namespace App\Service;

use App\Enum\SettingName;

readonly class AdminIpProvider
{
    public function __construct(
        private GetSettings $getSettings
    ) {
    }

    /** @return string[] */
    public function getAllowedIps(): array
    {
        $settingKey = SettingName::ADMIN_ALLOWED_IPS->value;

        // Pass an array of setting names
        $settings = $this->getSettings->getSpecificSettings([$settingKey]);

        // Extract the string value from the returned nested array
        $rawIps = $settings[$settingKey]['value'] ?? '';

        return self::parse($rawIps);
    }

    /** @return string[] */
    public static function parse(string $raw): array
    {
        $lines = preg_split('/[\r\n,;\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($lines ?: []));
    }
}
