<?php

namespace App\Service;

use App\Entity\Setting;
use App\Enum\LanguageType;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Repository\SettingTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class SettingsService
{
    public function __construct(
        private SettingRepository $settingRepository,
        private EntityManagerInterface $entityManager,
        private SettingTranslationRepository $settingTranslationRepository,
    ) {
    }

    public function update(string $name, ?string $value): void
    {
        $setting = $this->settingRepository->findOneBy(['name' => $name]);

        if ($setting !== null) {
            $setting->setValue($value);
            $this->entityManager->persist($setting);
        }
    }

    /**
     * Update or create multiple settings from a generic array.
     *
     * @param array<string, array{value: int|string|null|bool}> $settingsData
     */
    public function updateSettingsFromArray(array $settingsData): void
    {
        foreach ($settingsData as $name => $item) {
            $value = $item['value'] ?? null;

            // Try to fetch existing setting
            $setting = $this->settingRepository->findOneBy(['name' => $name]);

            $valueToSet = $value !== null ? (string)$value : null;

            if ($setting) {
                $setting->setValue($valueToSet);
            } else {
                $setting = new Setting();
                $setting->setName($name);
                $setting->setValue($valueToSet);
                $this->entityManager->persist($setting);
            }
        }
    }

    /**
     * Update or create multiple settings from a generic array.
     *
     * @param array<string, array{value: int|string|null}> $settingsData
     */
    public function updateAuthSettingsToTranslateFromArray(
        array $settingsData,
        ?string $locale = LanguageType::EN->value
    ): void {
        $authSettingsToTranslate = [
            SettingName::AUTH_METHOD_SAML_LABEL->value,
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value,
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value,
            SettingName::AUTH_METHOD_REGISTER_LABEL->value,
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value,
            SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value,
            SettingName::AUTH_METHOD_SAML_DESCRIPTION->value,
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value,
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value,
            SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value,
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value,
            SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value,
        ];
        foreach ($settingsData as $name => $item) {
            $value = $item['value'] ?? null;

            // Try to fetch existing setting
            $setting = $this->settingRepository->findOneBy(['name' => $name]);

            if ($setting) {
                if (in_array($name, $authSettingsToTranslate, true)) {
                    // Get the translated setting
                    $settingTranslation = $this->settingTranslationRepository->findOneBy(
                        ['setting' => $setting, 'locale' => $locale]
                    );
                    if ($value === null) {
                        $settingTranslation?->setTranslation('');
                    } else {
                        $settingTranslation?->setTranslation((string)$value);
                    }
                } else {
                    $setting->setValue((string)$value);
                }
            } else {
                // Create new setting if it doesn't exist
                $setting = new Setting();
                $setting->setName($name);
                $setting->setValue((string)$value);
                $this->entityManager->persist($setting);
            }
        }
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
