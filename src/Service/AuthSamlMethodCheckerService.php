<?php

namespace App\Service;

use App\Repository\SamlProviderRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class AuthSamlMethodCheckerService
{
    public function __construct(
        private readonly SamlProviderRepository $samlProviderRepository,
        private readonly SettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Enable or disable `AUTH_METHOD_SAML_ENABLED` based on active providers.
     */
    public function checkAndUpdateAuthMethodStatus(): void
    {
        $activeSamlProviders = $this->samlProviderRepository->findBy(['isActive' => true, 'deletedAt' => null]);
        if (!$activeSamlProviders) {
            $authMethodSetting = $this->settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_ENABLED']);
            if ($authMethodSetting && $authMethodSetting->getValue() === 'true') {
                $authMethodSetting->setValue('false');
                $this->entityManager->persist($authMethodSetting);
                $this->entityManager->flush();
            }
        }
    }
}
