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
     * @param array<string, array{value: string|null, description?: string}> $settingsData
     */
    public function updateSettingsFromArray(array $settingsData): void
    {
        foreach ($settingsData as $name => $item) {
            $value = $item['value'] ?? null;

            // Try to fetch existing setting
            $setting = $this->settingRepository->findOneBy(['name' => $name]);

            if ($setting) {
                $setting->setValue($value);
            } else {
                // Create new setting if it doesn't exist
                $setting = new Setting();
                $setting->setName($name);
                $setting->setValue($value);
                $this->entityManager->persist($setting);
            }
        }
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
