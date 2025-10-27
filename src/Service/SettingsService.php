<?php

namespace App\Service;

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

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
