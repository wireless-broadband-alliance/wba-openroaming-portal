<?php

namespace App\Service;

use App\Repository\SamlProviderRepository;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use RuntimeException;

class SamlProviderResolverService
{
    private ?Auth $samlAuth = null;

    public function __construct(
        private readonly SamlProviderRepository $repository
    ) {
    }

    /**
     * @throws Error
     */
    public function authSamlProviderByName(string $samlProviderName): Auth
    {
        if (!$this->samlAuth instanceof Auth) {
            // Fetch active provider
            $samlProvider = $this->repository->findOneBy([
                'name' => $samlProviderName,
                'deletedAt' => null,
                'isActive' => true,
            ]);

            if (!$samlProvider) {
                throw new RuntimeException(
                    sprintf(
                        'No active SAML provider found for the name "%s". ' .
                        'Please make sure you have an LDAPCredential associated with this SAML provider and that ' .
                        'the SAML provider is active.',
                        $samlProviderName
                    )
                );
            }

            // Generate settings dynamically
            $settings = [
                'sp' => [
                    'entityId' => $samlProvider->getSpEntityId(),
                    'assertionConsumerService' => ['url' => $samlProvider->getSpAcsUrl()],
                ],
                'idp' => [
                    'entityId' => $samlProvider->getIdpEntityId(),
                    'singleSignOnService' => ['url' => $samlProvider->getIdpSsoUrl()],
                    'x509cert' => $samlProvider->getIdpX509Cert(),
                ],
            ];

            $this->samlAuth = new Auth($settings);
        }
        return $this->samlAuth;
    }

    /**
     * @throws Error
     */
    public function authSamlProviderById(int $samlProviderId): Auth
    {
        if (!$this->samlAuth instanceof Auth) {
            // Fetch active provider by ID
            $samlProvider = $this->repository->findOneBy([
                'id' => $samlProviderId,
                'deletedAt' => null,
                'isActive' => true,
            ]);

            if (!$samlProvider) {
                throw new RuntimeException(
                    sprintf(
                        'No active SAML provider found for the ID "%d". ' .
                        'Please make sure you have an LDAPCredential associated with this SAML provider and that ' .
                        'the SAML provider is active.',
                        $samlProviderId
                    )
                );
            }

            // Generate settings dynamically
            $settings = [
                'sp' => [
                    'entityId' => $samlProvider->getSpEntityId(),
                    'assertionConsumerService' => ['url' => $samlProvider->getSpAcsUrl()],
                ],
                'idp' => [
                    'entityId' => $samlProvider->getIdpEntityId(),
                    'singleSignOnService' => ['url' => $samlProvider->getIdpSsoUrl()],
                    'x509cert' => $samlProvider->getIdpX509Cert(),
                ],
            ];

            $this->samlAuth = new Auth($settings);
        }
        return $this->samlAuth;
    }
}
