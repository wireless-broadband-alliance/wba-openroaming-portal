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

    public function __construct(array $data = [])
    {
        $this->returnAppsEnabled = $data[SettingName::RETURN_APPS_ENABLED->value]['value'] ?? null;
        $this->returnAppsPackageNameAndroid = $data[SettingName::RETURN_APPS_PACKAGE_NAME_ANDROID->value]['value'] ?? null;
        $this->returnAppsIdIOS = $data[SettingName::RETURN_APPS_ID_IOS->value]['value'] ?? null;
    }

    public function toArray(): array
    {
        return [
            SettingName::RETURN_APPS_ENABLED->value => ['value' => $this->returnAppsEnabled],
            SettingName::RETURN_APPS_PACKAGE_NAME_ANDROID->value => ['value' => $this->returnAppsPackageNameAndroid],
            SettingName::RETURN_APPS_ID_IOS->value => ['value' => $this->returnAppsIdIOS],
        ];
    }
}
