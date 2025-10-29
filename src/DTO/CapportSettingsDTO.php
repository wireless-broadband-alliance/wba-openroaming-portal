<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use App\Enum\SettingName;
use App\Validator\Constraints as CustomAssert;

class CapportSettingsDTO
{
    #[Assert\NotBlank(message: 'selectOption')]
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $capportEnabled = null;

    #[CustomAssert\CapportURL('capportEnabled')]
    public ?string $capportPortalUrl = null;

    #[CustomAssert\CapportURL('capportEnabled')]
    public ?string $capportVenueInfoUrl = null;

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null, description?: string}> $data
     */
    public function __construct(array $data = [])
    {
        $this->capportEnabled = $data[SettingName::CAPPORT_ENABLED->value]['value'] ?? null;
        $this->capportPortalUrl = $data[SettingName::CAPPORT_PORTAL_URL->value]['value'] ?? null;
        $this->capportVenueInfoUrl = $data[SettingName::CAPPORT_VENUE_INFO_URL->value]['value'] ?? null;
    }

    /**
     * Map the DTO back to an array for SettingsService.
     *
     * @return array<string, array{value: string|null}>
     */
    public function toArray(): array
    {
        return [
            SettingName::CAPPORT_ENABLED->value => ['value' => $this->capportEnabled],
            SettingName::CAPPORT_PORTAL_URL->value => ['value' => $this->capportPortalUrl],
            SettingName::CAPPORT_VENUE_INFO_URL->value => ['value' => $this->capportVenueInfoUrl],
        ];
    }
}
