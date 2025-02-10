<?php

namespace App\Service;

use App\Entity\SamlProvider;
use Doctrine\ORM\EntityManagerInterface;

class SamlProviderValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function checkDuplicateSamlProvider(
        string $name,
        string $idpEntityId,
        string $idpSsoUrl,
        string $spEntityId,
        string $spAcsUrl
    ): ?string {
        $existingProviderByName = $this->entityManager->getRepository(SamlProvider::class)->findOneBy([
            'name' => $name,
        ]);
        if ($existingProviderByName) {
            return 'name';
        }

        $existingProviderByIdpEntityId = $this->entityManager->getRepository(SamlProvider::class)->findOneBy([
            'idpEntityId' => $idpEntityId,
        ]);
        if ($existingProviderByIdpEntityId) {
            return 'IDP Entity ID';
        }

        $existingProviderByIdpSsoUrl = $this->entityManager->getRepository(SamlProvider::class)->findOneBy([
            'idpSsoUrl' => $idpSsoUrl,
        ]);
        if ($existingProviderByIdpSsoUrl) {
            return 'IDP SSO URL';
        }

        $existingProviderBySpEntityId = $this->entityManager->getRepository(SamlProvider::class)->findOneBy([
            'spEntityId' => $spEntityId,
        ]);
        if ($existingProviderBySpEntityId) {
            return 'SP Entity ID';
        }

        $existingProviderBySpAcsUrl = $this->entityManager->getRepository(SamlProvider::class)->findOneBy([
            'spAcsUrl' => $spAcsUrl,
        ]);
        if ($existingProviderBySpAcsUrl) {
            return 'SP ACS URL';
        }

        return null;
    }

    /**
     * @throws \JsonException
     */
    public function validateJsonUrlSamlProvider(string $url): ?string
    {
        // Check if URL is valid
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 'The provided URL is not valid.';
        }

        // Make a request to the given URL
        $response = @file_get_contents($url);

        // Validate JSON structure
        if ($response === false || !$this->isValidJson($response)) {
            return 'The response from the URL is not a valid JSON object.';
        }

        return null; // Validation passed
    }

    private function isValidJson(string $json): bool
    {
        return json_validate($json, 512, JSON_INVALID_UTF8_IGNORE);
    }

    /**
     * Validates the X.509 certificate for the SAML Provider to make sure it's valid.
     * Automatically adds "-----BEGIN CERTIFICATE-----" and "-----END CERTIFICATE-----" if they're missing.
     *
     * @param string $certificate The SAML X.509 Certificate (Base64 encoded).
     * @return string|null Returns an error message if invalid, or null if valid.
     */
    public function validateCertificate(string $certificate): ?string
    {
        if ($certificate === '' || $certificate === '0') {
            return 'The certificate is empty.';
        }

        // Add certificate tags if missing
        if (!str_contains($certificate, '-----BEGIN CERTIFICATE-----')) {
            $certificate = "-----BEGIN CERTIFICATE-----\n" . chunk_split(
                $certificate,
                64,
                "\n"
            ) . "-----END CERTIFICATE-----";
        }

        // Ensure the certificate is valid Base64 within the tags
        $matches = [];
        preg_match('/-----BEGIN CERTIFICATE-----(.*)-----END CERTIFICATE-----/s', $certificate, $matches);
        if (empty($matches[1]) || !base64_decode(trim($matches[1]), true)) {
            return 'The certificate is not a valid Base64-encoded string.';
        }

        // Parse the certificate as X.509
        $parsedCert = @openssl_x509_read($certificate);
        if ($parsedCert === false) {
            return 'The certificate is not a valid X.509 certificate.';
        }

        // Extract certificate information
        $certInfo = openssl_x509_parse($certificate);
        if (!$certInfo) {
            return 'Failed to parse the certificate.';
        }

        // Check expiration dates
        $currentTime = time();
        if (isset($certInfo['validFrom_time_t']) && $currentTime < $certInfo['validFrom_time_t']) {
            return 'The certificate is not yet valid. It starts being valid on ' . date(
                'Y-m-d H:i:s',
                $certInfo['validFrom_time_t']
            ) . '.';
        }
        if (isset($certInfo['validTo_time_t']) && $currentTime > $certInfo['validTo_time_t']) {
            return 'The certificate has expired. It expired on ' . date(
                'Y-m-d H:i:s',
                $certInfo['validTo_time_t']
            ) . '.';
        }

        // Check if the Common Name (CN) is present
        if (empty($certInfo['subject']['CN'])) {
            return 'The certificate does not contain a valid Common Name (CN) in its subject.';
        }

        return null;
    }
}
