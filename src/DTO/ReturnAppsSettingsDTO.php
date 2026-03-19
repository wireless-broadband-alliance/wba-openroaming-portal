<?php

namespace App\DTO;

use App\Enum\SettingName;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

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

    /**
     * @var list<string>
     */
    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public array $returnAppsFingerprint = [];

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null}> $data
     */
    public function __construct(array $data = [])
    {
        $this->returnAppsEnabled = $data[SettingName::RETURN_APPS_ENABLED->value]['value'] ?? null;
        $this->returnAppsPackageName = $data[SettingName::RETURN_APPS_PACKAGE_NAME->value]['value'] ?? null;
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