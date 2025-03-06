<?php

namespace App\Service;

use App\Repository\SamlProviderRepository;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SamlProviderResolverService
{
    private ?Auth $samlAuth = null;

    public function __construct(
        private readonly SamlProviderRepository $repository,
        private readonly RequestStack $requestStack,
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
    public function authSamlProviderById(int $samlProviderId, ?bool $isSAMLApi = false): Auth
    {
        if (!$this->samlAuth instanceof Auth) {
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

            // Generate settings dynamically depending on if it's a response to the landing page of the api
            $request = $this->requestStack->getCurrentRequest();
            $host = $request->getSchemeAndHttpHost();
            $acsUrl = $isSAMLApi ? $host . '/saml/acs' : $samlProvider->getSpAcsUrl();
            $settings = [
                'sp' => [
                    'entityId' => $samlProvider->getSpEntityId(),
                    'assertionConsumerService' => ['url' => $acsUrl],
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

    public function decodeSamlResponse(string $samlResponse): array
    {
        // Decode the SamlResponse for data validation with the DB
        try {
            // Decode the Base64-encoded SAMLResponse
            $decodedSamlResponse = base64_decode($samlResponse, true);

            if ($decodedSamlResponse === false) {
                throw new AuthenticationException('Failed to decode SAMLResponse.');
            }

            // Load the response as an XML document
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->loadXML($decodedSamlResponse);

            // Extract the "Issuer" field (idp_entity_id)
            $issuerNode = $dom->getElementsByTagName('Issuer')->item(0);
            if (!$issuerNode) {
                throw new AuthenticationException('Issuer (idp_entity_id) not found in the SAMLResponse.');
            }
            $idpEntityId = $issuerNode->textContent;

            // Extract the certificate
            $certificateNode = $dom->getElementsByTagName('X509Certificate')->item(0);
            if (!$certificateNode) {
                throw new AuthenticationException('Certificate not found in the SAMLResponse.');
            }
            $certificate = $certificateNode->textContent;

            // Return both the IdP Entity ID and the Certificate
            return [
                'idp_entity_id' => $idpEntityId,
                'certificate' => $certificate,
            ];
        } catch (\Exception $e) {
            throw new AuthenticationException(
                'An error occurred while processing the SAMLResponse: ' . $e->getMessage()
            );
        }
    }
}
