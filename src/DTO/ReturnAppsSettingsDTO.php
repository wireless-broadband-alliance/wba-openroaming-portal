<?php

namespace App\DTO;

use App\Enum\SettingName;
use Symfony\Component\Validator\Constraints as Assert;

class ReturnAppsSettingsDTO
{
    #[Assert\NotBlank(message: 'selectOption')]
    public ?string $returnAppsEnabled = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Regex(
        pattern: '/^[a-zA-z0-9_-]*$/u',
        message: 'noSpecialCharacters'
    )]
    public ?string $returnAppsPackageName = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Regex(
        pattern: '/^[a-zA-z0-9_-]*$/u',
        message: 'noSpecialCharacters'
    )]
    #[Assert\Length(min: 3, max: 32, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $returnAppsFingerprint = null;

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null}> $data
     */
    public function __construct(array $data = [])
    {
        $this->returnAppsEnabled = $data[SettingName::RETURN_APPS_ENABLED->value]['value'] ?? null;
        $this->returnAppsPackageName = $data[SettingName::RETURN_APPS_PACKAGE_NAME->value]['value'] ?? null;
        $this->returnAppsFingerprint = $data[SettingName::RETURN_APPS_FINGERPRINTS->value]['value'] ?? null;
    }

    public function toArray(): array
    {
        return [
            SettingName::RETURN_APPS_ENABLED->value =>
                ['value' => $this->returnAppsEnabled],
            SettingName::RETURN_APPS_PACKAGE_NAME->value =>
                ['value' => $this->returnAppsPackageName],
            SettingName::RETURN_APPS_FINGERPRINTS->value =>
                ['value' => $this->returnAppsFingerprint]
        ];
    }

}