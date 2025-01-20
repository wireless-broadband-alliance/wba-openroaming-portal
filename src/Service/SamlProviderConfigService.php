<?php

namespace App\Service;

use App\Repository\SamlProviderRepository;
use Doctrine\ORM\NonUniqueResultException;

class SamlProviderConfigService
{
    private SamlProviderRepository $repository;

    public function __construct(SamlProviderRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get the default SAML provider configuration.
     *
     * @return array|null Returns configuration or null if no provider exists.
     * @throws NonUniqueResultException
     */
    public function getDefaultProviderConfig(): ?array
    {
        $defaultProvider = $this->repository->findDefault();

        if (!$defaultProvider) {
            throw new \RuntimeException('No default SAML provider found in the database.');
        }

        $config = [
            'idpEntityId' => $defaultProvider->getIdpEntityId(),
            'idpSsoUrl' => $defaultProvider->getIdpSsoUrl(),
            'idpX509Cert' => $defaultProvider->getIdpX509Cert(),
            'spEntityId' => $defaultProvider->getSpEntityId(),
            'spAcsUrl' => $defaultProvider->getSpAcsUrl(),
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
