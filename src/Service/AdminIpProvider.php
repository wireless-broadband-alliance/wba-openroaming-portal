<?php

namespace App\Service;

use App\Enum\SettingName;

readonly class AdminIpProvider
{
    public function __construct(
        private GetSettings $getSettings
    ) {
    }

    /** @return string[]
     * @throws \JsonException
     */
    public function getAllowedIps(): array
    {
        $settingKey = SettingName::ADMIN_ALLOWED_IPS->value;
        $settings = $this->getSettings->getSpecificSettings([$settingKey]);
        $rawIps = $settings[$settingKey]['value'] ?? '';

        return self::parse($rawIps);
    }

    /** @return string[]
     * @throws \JsonException
     */
    public static function parse(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        // Handle JSON array formats like ["192.0.0.1", "10.0.0.0/8"]
        if (str_starts_with($raw, '[')) {
            $decoded = json_decode(
                $raw,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            if (is_array($decoded)) {
                $ips = array_filter($decoded, fn($item) => is_string($item) && trim($item) !== '');
                return array_values(array_unique(array_map('trim', $ips)));
            }
        }

        // Fallback for line-separated, comma-separated, or space-separated values
        $lines = preg_split('/[\r\n,;\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if ($lines === false) {
            return [];
        }

        // Strip any residual brackets/quotes
        $cleaned = array_map(
            static fn(string $item): string => trim($item, " \t\n\r\0\x0B\"'[]"),
            $lines
        );

        return array_values(array_unique(array_filter($cleaned, static fn(string $v) => $v !== '')));
    }
}
