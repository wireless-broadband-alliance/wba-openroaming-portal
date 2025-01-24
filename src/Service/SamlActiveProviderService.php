<?php

namespace App\Service;

use App\Repository\SamlProviderRepository;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use RuntimeException;

class SamlActiveProviderService
{
    private ?Auth $samlAuth = null;

    public function __construct(private readonly SamlProviderRepository $repository)
    {
    }

    /**
     * @throws Error
     */
    public function getActiveSamlProvider(): Auth
    {
        if ($this->samlAuth === null) {
            // Fetch active provider
            $activeProvider = $this->repository->findOneBy(['isActive' => true]);

            if (!$activeProvider) {
                throw new RuntimeException('No active SAML provider found.');
            }

            // Generate settings dynamically
            $settings = [
                'sp' => [
                    'entityId' => $activeProvider->getSpEntityId(),
                    'assertionConsumerService' => ['url' => $activeProvider->getSpAcsUrl()],
                ],
                'idp' => [
                    'entityId' => $activeProvider->getIdpEntityId(),
                    'singleSignOnService' => ['url' => $activeProvider->getIdpSsoUrl()],
                    'x509cert' => $activeProvider->getIdpX509Cert(),
                ],
            ];

            $this->samlAuth = new Auth($settings);
        }
        return $this->samlAuth;
    }
}
