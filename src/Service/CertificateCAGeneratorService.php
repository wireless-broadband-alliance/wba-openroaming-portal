<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CertificateCAGeneratorService
{
    private array $messages = [];
    private array $visitedFingerprints = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Resolve full chain from a leaf certificate
     */
    public function generateCA(string $leafPem): ?array
    {
        $chain = [];
        $current = $this->normalizePem($leafPem);

        if (!$current) {
            $this->messages[] = 'Invalid leaf certificate format';
            return null;
        }

        while (true) {
            $fingerprint = openssl_x509_fingerprint($current);

            if (isset($this->visitedFingerprints[$fingerprint])) {
                $this->messages[] = 'Certificate loop detected';
                return null;
            }

            $this->visitedFingerprints[$fingerprint] = true;

            $chain[] = $current;

            if ($this->isSelfSigned($current)) {
                break;
            }

            $issuerUrl = $this->extractIssuerUrl($current);

            if (!$issuerUrl) {
                $this->messages[] = 'Issuer URL not found in AIA';
                return null;
            }

            $issuerPem = $this->downloadIssuerCertificate($issuerUrl);

            if (!$issuerPem) {
                $this->messages[] = 'Failed to download issuer certificate';
                return null;
            }

            if (!$this->verifySignature($current, $issuerPem)) {
                $this->messages[] = 'Signature verification failed';
                return null;
            }

            $current = $issuerPem;
        }

        return [
            'chain' => $chain,
            'root' => end($chain)
        ];
    }

    /**
     * Extract AIA issuer URL
     */
    private function extractIssuerUrl(string $pem): ?string
    {
        $cert = openssl_x509_parse($pem);

        if (!isset($cert['extensions']['authorityInfoAccess'])) {
            return null;
        }

        $aia = $cert['extensions']['authorityInfoAccess'];

        if (preg_match('/CA Issuers - URI:(.*)/', $aia, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Download issuer certificate
     */
    private function downloadIssuerCertificate(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url);

            $der = $response->getContent();

            return $this->convertDerToPem($der);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Convert DER certificate to PEM
     */
    private function convertDerToPem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    /**
     * Verify signature
     */
    private function verifySignature(string $cert, string $issuer): bool
    {
        $pubKey = openssl_pkey_get_public($issuer);

        if (!$pubKey) {
            return false;
        }

        return openssl_x509_verify($cert, $pubKey) === 1;
    }

    /**
     * Detect self-signed certificate
     */
    private function isSelfSigned(string $cert): bool
    {
        $parsed = openssl_x509_parse($cert);

        if (!$parsed) {
            return false;
        }

        return $parsed['subject'] === $parsed['issuer'];
    }

    /**
     * Normalize PEM format
     */
    private function normalizePem(string $pem): ?string
    {
        if (!preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $pem, $match)) {
            return null;
        }

        return "-----BEGIN CERTIFICATE-----{$match[1]}-----END CERTIFICATE-----\n";
    }
}
