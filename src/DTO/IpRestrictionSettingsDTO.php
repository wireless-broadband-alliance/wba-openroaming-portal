<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\SettingName;
use App\Service\AdminIpProvider;
use App\Validator\Constraints as CustomAssert;
use JsonException;
use Symfony\Component\Validator\Constraints as Assert;

class IpRestrictionSettingsDTO
{
    /**
     * @var array<int, string>
     */
    #[Assert\All([
        new CustomAssert\IpOrCidr(),
    ])]
    private array $allowedIps;

    /**
     * @param array<string, mixed> $settingsData
     * @throws JsonException
     */
    public function __construct(array $settingsData = [])
    {
        $settingKey = SettingName::ADMIN_ALLOWED_IPS->value;
        $rawValue = $settingsData[$settingKey]['value'] ?? '';

        // Reuse the same parser as the request subscriber (JSON or comma/line separated)
        $this->allowedIps = AdminIpProvider::parse((string)$rawValue);
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedIps(): array
    {
        return $this->allowedIps;
    }

    /**
     * @param array<int, string|null> $allowedIps
     */
    public function setAllowedIps(array $allowedIps): void
    {
        // Drop empty rows submitted by the collection and normalize
        $clean = array_map(
            static fn(?string $ip): string => trim((string)$ip),
            $allowedIps
        );

        $this->allowedIps = array_values(
            array_unique(array_filter($clean, static fn(string $ip): bool => $ip !== ''))
        );
    }

    /**
     * Shape expected by SettingsService::updateSettingsFromArray().
     *
     * @return array<string, array{value: string}>
     * @throws JsonException
     */
    public function toArray(): array
    {
        return [
            SettingName::ADMIN_ALLOWED_IPS->value => [
                'value' => json_encode($this->allowedIps, JSON_THROW_ON_ERROR),
            ],
        ];
    }
}
