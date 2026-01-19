<?php

namespace App\Service;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SamlResolverService
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @return array{idp_entity_id: string, certificate: string}
     */
    public function decodeSamlResponse(string $samlResponse, string $expectedIdpEntityId): array
    {
        // Decode the SamlResponse for data validation with the DB
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

        // Create XPath and register namespaces
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

        // Extract the Audience field
        $audienceNodes = $xpath->query('//saml:Conditions/saml:AudienceRestriction/saml:Audience');
        if ($audienceNodes && $audienceNodes->length > 0) {
            $firstNode = $audienceNodes->item(0);

            // Ensure the node is a DOMNode
            if (!$firstNode instanceof DOMNode) {
                throw new AuthenticationException(
                    $this->translator->trans('audienceNotFoundSAMLResponse', [], 'SamlResolverService')
                );
            }

            $idpEntityId = trim($firstNode->textContent);
        } else {
            throw new AuthenticationException(
                $this->translator->trans('audienceNotFoundSAMLResponse', [], 'SamlResolverService')
            );
        }

        // Validate the Audience (Response + Assertion)
        $audiences = $this->getAudiences($dom);

        if ($audiences === []) {
            throw new AuthenticationException(
                $this->translator->trans('audienceNotFoundSAMLResponse', [], 'SamlResolverService')
            );
        }

        foreach ($audiences as $audience) {
            $trimmedAudience = trim((string) $audience);
            if ($trimmedAudience === '' || $trimmedAudience === '0' || $trimmedAudience !== $expectedIdpEntityId) {
                throw new AuthenticationException(
                    $this->translator->trans(
                        'invalidIssuerSAMLResponse',
                        [
                            '%expected%' => $expectedIdpEntityId,
                            '%got%' => $trimmedAudience ?: 'empty',
                        ],
                        'SamlResolverService'
                    )
                );
            }
        }

        $certificateNode = $dom->getElementsByTagName('X509Certificate')->item(0);
        if (!$certificateNode) {
            throw new AuthenticationException(
                $this->translator->trans('certificateNotFoundSAMLResponse', [], 'SamlResolverService')
            );
        }

        $certificate = trim($certificateNode->textContent);

        // Return both the Audience (idp_entity_id) and the Certificate
        return [
            'idp_entity_id' => $idpEntityId,
            'certificate'   => $certificate,
        ];
    }

    /**
     * Extract Audience values from both the SAML Response and the Assertion.
     * @return string[]
     */
    private function getAudiences(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

        $audiences = [];

        // Audience(s) defined in the Assertion's Conditions
        $audienceNodes = $xpath->query('//saml:Conditions/saml:AudienceRestriction/saml:Audience');

        if ($audienceNodes && $audienceNodes->length > 0) {
            foreach ($audienceNodes as $node) {
                if ($node instanceof DOMNode) {
                    $audiences[] = trim($node->textContent);
                }
            }
        } else {
            throw new AuthenticationException(
                $this->translator->trans('audienceNotFoundSAMLResponse', [], 'SamlResolverService')
            );
        }

        return $audiences;
    }
}
