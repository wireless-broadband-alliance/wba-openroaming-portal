<?php

namespace App\Service;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SamlResolverService
{
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
