<?php

namespace App\Service;

use RuntimeException;

class CertificateCAGeneratorService
{
    public function generateCA(
        string $certPath,
        string $chainPath,
        string $outputCAPath
    ): string {
        // Load files
        $leafPem = $this->normalizePem(file_get_contents($certPath));
        $chainPem = $this->extractPemCertificates(file_get_contents($chainPath));

        if (!$leafPem || !$chainPem) {
            throw new RuntimeException("Leaf or chain certificates cannot be read.");
        }

        // Deduplicate
        $pool = array_merge([$leafPem], $this->uniqueCerts($chainPem));

        // Try to find the root (self-signed cert)
        $root = null;
        foreach ($pool as $cert) {
            if ($this->isSelfSigned($cert)) {
                $root = $cert;
                break;
            }
        }

        if (!$root) {
            throw new RuntimeException("No valid root certificate found in chain.");
        }

        // Optional: validate the chain fully
        $fullChain = $this->buildChain($pool, $root);
        if ($fullChain === false) {
            throw new RuntimeException("Incomplete or invalid certificate chain.");
        }

        // Write CA.pem
        file_put_contents($outputCAPath, $root);

        return $outputCAPath;
    }

    /**
     * @param string[] $pool Array of PEM certificates
     * @param string|null $expectedRoot
     * @return array|false
     */
    private function buildChain(array $pool, ?string $expectedRoot = null): array|false
    {
        $leaf = array_shift($pool);
        $chain = [$leaf];
        $current = $leaf;

        while (true) {
            $found = false;
            foreach ($pool as $i => $candidate) {
                if ($this->certEquals($current, $candidate)) {
                    continue;
                }

                if ($this->verifySignature($current, $candidate)) {
                    $chain[] = $candidate;
                    $current = $candidate;
                    unset($pool[$i]);
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                if ($expectedRoot && $this->certEquals($current, $expectedRoot)) {
                    break;
                }

                if (!$expectedRoot && $this->isSelfSigned($current)) {
                    break;
                }

                return false; // incomplete chain
            }
        }

        if ($expectedRoot && !$this->certEquals(end($chain), $expectedRoot)) {
            return false;
        }

        return $chain;
    }

    private function certEquals(string $a, string $b): bool
    {
        return openssl_x509_fingerprint($a) === openssl_x509_fingerprint($b);
    }

    private function verifySignature(string $cert, string $issuer): bool
    {
        $pubKey = openssl_pkey_get_public($issuer);
        if ($pubKey === false) {
            return false;
        }

        return openssl_x509_verify($cert, $pubKey) === 1;
    }

    private function isSelfSigned(string $cert): bool
    {
        $pubKey = openssl_pkey_get_public($cert);
        if ($pubKey === false) {
            return false;
        }

        return openssl_x509_verify($cert, $pubKey) === 1;
    }

    private function normalizePem(
        string $pem
    ): ?string {
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
     * @param string[] $certs
     * @return string[]
     */
    private function uniqueCerts(array $certs): array
    {
        return array_values(array_unique(array_map(trim(...), $certs)));
    }

    /**
     * Extract individual PEM certificates from a bundle.
     *
     * @return string[]
     */
    private function extractPemCertificates(
        string $pem
    ): array {
        preg_match_all(
            '/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s',
            $pem,
            $matches
        );

        return array_map(
            static fn(string $body): string => "-----BEGIN CERTIFICATE-----{$body}-----END CERTIFICATE-----\n",
            $matches[1]
        );
    }
}
