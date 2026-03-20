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
        pattern: '/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z][a-zA-Z0-9_]*)+$/',
        message: 'invalidAndroidPackageName'
    )]
    #[Assert\Length(max: 255)]
    public ?string $returnAppsPackageNameAndroid = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)+$/',
        message: 'invalidIosBundleId'
    )]
    #[Assert\Length(max: 255)]
    public ?string $returnAppsIdIOS = null;

    /**
     * @var string[]
     */
    #[Assert\All([
        new Assert\NotBlank(message: 'fingerprintCannotBeBlank'),
        new Assert\Regex(
            pattern: '/^([A-Fa-f0-9]{2}:){31}[A-Fa-f0-9]{2}$/',
            message: 'invalidSha256Fingerprint'
        )
    ])]
    public array $fingerprints = [];

    public function __construct(array $data = [])
    {
        $this->returnAppsEnabled = $data[SettingName::RETURN_APPS_ENABLED->value]['value'] ?? null;
        $this->returnAppsPackageNameAndroid = $data[SettingName::RETURN_APPS_PACKAGE_NAME_ANDROID->value]['value'] ?? null;
        $this->returnAppsIdIOS = $data[SettingName::RETURN_APPS_ID_IOS->value]['value'] ?? null;

        // Flatten the fingerprints to just the string value
        $this->fingerprints = array_map(
            static fn($fp) => is_array($fp) ? $fp['fingerprint'] : $fp->getName(),
            $data['fingerprints'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            SettingName::RETURN_APPS_ENABLED->value => ['value' => $this->returnAppsEnabled],
            SettingName::RETURN_APPS_PACKAGE_NAME_ANDROID->value => ['value' => $this->returnAppsPackageNameAndroid],
            SettingName::RETURN_APPS_ID_IOS->value => ['value' => $this->returnAppsIdIOS],
            // fingerprints are not part of settings storage
        ];
    }
}
