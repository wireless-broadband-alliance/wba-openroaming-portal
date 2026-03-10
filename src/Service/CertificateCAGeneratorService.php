<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class CertificateCAGeneratorService
{
    /** @var string[] Messages collected during generation */
    private array $messages = [];

    /** @var array<string, bool> Keeps track of visited certificate fingerprints */
    private array $visitedFingerprints = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Generate the trusted root CA from a leaf certificate.
     *
     * @param File $certFile Leaf certificate
     * @param File|null $chainFile Optional chain file to help build the chain
     *
     * @return string|null PEM content of the trusted root CA
     */
    public function generateCA(File $certFile, ?File $chainFile = null): ?string
    {
        $leafPem = $this->normalizePem(file_get_contents($certFile->getRealPath()) ?: '');
        if (!$leafPem) {
            $this->messages[] = $this->translator->trans(
                'invalidLeafCertificate',
                [],
                'CertificateCAGeneratorService'
            );

            return null;
        }

        $pool = [$leafPem];
        if ($chainFile instanceof File) {
            $chainContent = file_get_contents($chainFile->getRealPath()) ?: '';
            $pool = array_merge($pool, $this->extractPemCertificates($chainContent));
        }

        $current = $leafPem;
        $this->visitedFingerprints = [];

        while (true) {
            $fp = (string) openssl_x509_fingerprint($current);

            if (isset($this->visitedFingerprints[$fp])) {
                return null;
            }

            $this->visitedFingerprints[$fp] = true;

            // If self-signed and trusted, return as root
            if ($this->isSelfSigned($current) && $this->isTrustedRoot($current)) {
                return $current;
            }

            // Try to find issuer from pool
            $issuer = $this->findIssuerInPool($current, $pool);
            if ($issuer) {
                $current = $issuer;
                continue;
            }

            // Try to fetch issuer via AIA
            $issuerUrl = $this->extractIssuerUrl($current);
            if ($issuerUrl) {
                $issuerPem = $this->downloadIssuerCertificate($issuerUrl);
                if ($issuerPem && $this->verifySignature($current, $issuerPem)) {
                    $current = $issuerPem;
                    continue;
                }
            }

            // Fallback: find issuer in system trust store
            $issuerPem = $this->findIssuerInTrustStore($current);
            if ($issuerPem && $this->verifySignature($current, $issuerPem)) {
                $current = $issuerPem;
                continue;
            }

            $this->messages[] = $this->translator->trans(
                'unableToResolveTrustedRoot',
                [],
                'CertificateCAGeneratorService'
            );
            return null;
        }
    }

    /**
     * @param string $cert
     * @param string[] $pool
     *
     * @return string|null
     */
    private function findIssuerInPool(string $cert, array $pool): ?string
    {
        foreach ($pool as $candidate) {
            if ($this->certEquals($cert, $candidate)) {
                continue;
            }
            if ($this->verifySignature($cert, $candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Extract AIA issuer URL from a certificate
     */
    private function extractIssuerUrl(string $pem): ?string
    {
        $parsed = openssl_x509_parse($pem);
        if (empty($parsed['extensions']['authorityInfoAccess'])) {
            return null;
        }
        if (
            preg_match(
                '/CA Issuers - URI:(.*)/',
                (string)$parsed['extensions']['authorityInfoAccess'],
                $matches
            )
        ) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Download certificate from AIA URL
     */
    private function downloadIssuerCertificate(string $url): ?string
    {
        try {
            $der = $this->httpClient->request('GET', $url)->getContent();
            return $this->convertDerToPem($der);
        } catch (Throwable) {
            return null;
        }
    }

    private function convertDerToPem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    /**
     * Verify cert signature
     */
    private function verifySignature(string $cert, string $issuer): bool
    {
        $pubKey = openssl_pkey_get_public($issuer);
        if (!$pubKey) {
            return false;
        }
        return openssl_x509_verify($cert, $pubKey) === 1;
    }

    private function isSelfSigned(string $cert): bool
    {
        $parsed = openssl_x509_parse($cert);
        return $parsed && isset($parsed['subject'], $parsed['issuer']) && $parsed['subject'] === $parsed['issuer'];
    }

    /**
     * Check if certificate is in system trust store
     */
    private function isTrustedRoot(string $cert): bool
    {
        $store = file_get_contents('/etc/ssl/certs/ca-certificates.crt') ?: '';
        return array_any(
            $this->extractPemCertificates($store),
            fn($trusted) => $this->certEquals($cert, $trusted)
        );
    }

    /**
     * Find issuer in system trust store
     */
    private function findIssuerInTrustStore(string $cert): ?string
    {
        $store = file_get_contents('/etc/ssl/certs/ca-certificates.crt') ?: '';
        return array_find(
            $this->extractPemCertificates($store),
            fn($trusted) => $this->verifySignature($cert, $trusted)
        );
    }

    private function certEquals(string $a, string $b): bool
    {
        return openssl_x509_fingerprint($a) === openssl_x509_fingerprint($b);
    }

    private function normalizePem(string $pem): ?string
    {
        if (
            !preg_match(
                '/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s',
                $pem,
                $match
            )
        ) {
            return null;
        }
        return "-----BEGIN CERTIFICATE-----{$match[1]}-----END CERTIFICATE-----\n";
    }

    /**
     * @param string $pem
     * @return string[]
     */
    private function extractPemCertificates(string $pem): array
    {
        preg_match_all(
            '/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s',
            $pem,
            $matches
        );
        return array_map(
            static fn($body) => "-----BEGIN CERTIFICATE-----{$body}-----END CERTIFICATE-----\n",
            $matches[1]
        );
    }
}
