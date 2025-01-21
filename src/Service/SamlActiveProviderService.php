<?php

namespace App\Service;

use App\Repository\SamlProviderRepository;

class SamlActiveProviderService
{
    private SamlProviderRepository $repository;

    public function __construct(SamlProviderRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getActiveSamlProvider(): ?array
    {
        $activeProvider = $this->repository->findOneBy(['isActive' => true]);

        if (!$activeProvider) {
            return null;
        }

        $config = [
            'idpEntityId' => $activeProvider->getIdpEntityId(),
            'idpSsoUrl' => $activeProvider->getIdpSsoUrl(),
            'idpX509Cert' => $activeProvider->getIdpX509Cert(),
            'spEntityId' => $activeProvider->getSpEntityId(),
            'spAcsUrl' => $activeProvider->getSpAcsUrl(),
        ];

        // Validate required URLs
        foreach (['idpSsoUrl', 'spAcsUrl'] as $key) {
            if (empty($config[$key]) || !filter_var($config[$key], FILTER_VALIDATE_URL)) {
                throw new \RuntimeException("Invalid SAML configuration for: $key");
            }
        }

        return $config;
    }
}
