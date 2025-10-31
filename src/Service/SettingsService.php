<?php

namespace App\Service;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class SettingsService
{
    public function __construct(
        private SettingRepository $settingRepository,
        private EntityManagerInterface $entityManager
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
     * @param array<string, array{value: int|string|null}> $settingsData
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

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
