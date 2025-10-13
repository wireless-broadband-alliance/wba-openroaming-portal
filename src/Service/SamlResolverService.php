<?php

namespace App\Service;

use DOMDocument;
use DOMXPath;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SamlResolverService
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    public function decodeSamlResponse(string $samlResponse, string $expectedIdpEntityId): array
    {
        // Decode the SamlResponse for data validation with the DB
        try {
            // Decode the Base64-encoded SAMLResponse
            $decodedSamlResponse = base64_decode($samlResponse, true);

            if ($decodedSamlResponse === false) {
                throw new AuthenticationException(
                    $this->translator->trans('failedDecodeSAMLResponse', [], 'SamlResolverService')
                );
            }

            // Load the response as an XML document
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->loadXML($decodedSamlResponse);

            // Extract the "Issuer" field (idp_entity_id)
            $issuerNode = $dom->getElementsByTagName('Issuer')->item(0);
            if (!$issuerNode) {
                throw new AuthenticationException(
                    $this->translator->trans('issuerNotFoundSAMLResponse', [], 'SamlResolverService')
                );
            }
            $idpEntityId = $issuerNode->textContent;

            // Get all Issuer nodes (Response + Assertion)
            $issuers = $this->getIssuers($dom);

            if ($issuers === []) {
                throw new AuthenticationException(
                    $this->translator->trans('issuerNotFoundSAMLResponse', [], 'SamlResolverService')
                );
            }

            // Validate each issuer (like OneLogin)
            foreach ($issuers as $issuer) {
                $trimmedIssuer = trim($issuer);
                if ($trimmedIssuer === '' || $trimmedIssuer === '0' || $trimmedIssuer !== $expectedIdpEntityId) {
                    throw new AuthenticationException(
                        $this->translator->trans(
                            'invalidIssuerSAMLResponse',
                            [
                                '%expected%' => $expectedIdpEntityId,
                                '%got%' => $trimmedIssuer ?: 'empty',
                            ],
                            'SamlResolverService'
                        )
                    );
                }
            }

            // Extract the certificate
            $certificateNode = $dom->getElementsByTagName('X509Certificate')->item(0);
            if (!$certificateNode) {
                throw new AuthenticationException(
                    $this->translator->trans('certificateNotFoundSAMLResponse', [], 'SamlResolverService')
                );
            }
            $certificate = $certificateNode->textContent;

            // Return both the IdP Entity ID and the Certificate
            return [
                'idp_entity_id' => $idpEntityId,
                'certificate' => $certificate,
            ];
        } catch (\Exception $e) {
            throw new AuthenticationException(
                $this->translator->trans(
                    'certificateNotFoundSAMLResponse',
                    ['%message%' => $e->getMessage()],
                    'SamlResolverService'
                )
            );
        }
    }

    /**
     * Extract issuers from both the SAML Response and the Assertion.
     *
     * @return string[]
     */
    private function getIssuers(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

        $issuers = [];

        // Response-level issuer
        $responseIssuerNodes = $xpath->query('/samlp:Response/saml:Issuer');
        if ($responseIssuerNodes && $responseIssuerNodes->length === 1) {
            $issuers[] = $responseIssuerNodes->item(0)->textContent;
        } elseif ($responseIssuerNodes && $responseIssuerNodes->length > 1) {
            throw new AuthenticationException(
                $this->translator->trans('multipleIssuerSAMLResponse', [], 'SamlResolverService')
            );
        }

        // Assertion-level issuer
        $assertionIssuerNodes = $xpath->query('//saml:Assertion/saml:Issuer');
        if ($assertionIssuerNodes && $assertionIssuerNodes->length === 1) {
            $issuers[] = $assertionIssuerNodes->item(0)->textContent;
        } elseif ($assertionIssuerNodes && $assertionIssuerNodes->length > 1) {
            throw new AuthenticationException(
                $this->translator->trans('multipleAssertionIssuerSAMLResponse', [], 'SamlResolverService')
            );
        }

        return $issuers;
    }
}
