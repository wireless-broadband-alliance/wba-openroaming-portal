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
        pattern: '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/',
        message: 'invalidAndroidPackageName'
    )] // Java-script style
    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $returnAppsPackageNameAndroid = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9]{10}$/',
        message: 'invalidIosTeamId'
    )]
    #[Assert\Length(max: 10, maxMessage: 'maxCharacters')]
    public ?string $returnAppsIosTeamId = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z][a-zA-Z0-9]*(?:-[a-zA-Z0-9]+)*(\.[a-zA-Z][a-zA-Z0-9]*(?:-[a-zA-Z0-9]+)*)+$/',
        message: 'invalidIosBundleId'
    )] // reverse DNS
    #[Assert\Length(max: 155, maxMessage: 'maxCharacters')]
    public ?string $returnAppsIosBundleId = null;

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

    /**
     * @param array<string, array<string, mixed>> $data
     */
    public function __construct(array $data = [])
    {
        $this->returnAppsEnabled = $data[SettingName::RETURN_APPS_ENABLED->value]['value'] ?? null;

        $this->returnAppsPackageNameAndroid =
            $data[SettingName::RETURN_APPS_PACKAGE_NAME_ANDROID->value]['value'] ?? null;

        $iosFull = $data[SettingName::RETURN_APPS_ID_IOS->value]['value'] ?? null;

        if ($iosFull && str_contains((string) $iosFull, '.')) {
            [$teamId, $bundleId] = explode('.', (string) $iosFull, 2);
            $this->returnAppsIosTeamId = $teamId;
            $this->returnAppsIosBundleId = $bundleId;
        }

        // Fingerprints
        $this->fingerprints = array_map(
            static fn($fp) => is_array($fp) ? $fp['fingerprint'] : $fp->getName(),
            $data['fingerprints'] ?? []
        );
    }

    /**
     * @return array<string, array{value: bool|string|null}>
     */
    public function toArray(): array
    {
        $iosFull = null;

        if ($this->returnAppsIosTeamId && $this->returnAppsIosBundleId) {
            $iosFull = $this->returnAppsIosTeamId . '.' . $this->returnAppsIosBundleId;
        }

        return [
            SettingName::RETURN_APPS_ENABLED->value => ['value' => $this->returnAppsEnabled],
            SettingName::RETURN_APPS_PACKAGE_NAME_ANDROID->value => ['value' => $this->returnAppsPackageNameAndroid],
            SettingName::RETURN_APPS_ID_IOS->value => ['value' => $iosFull],
        ];
    }
}
