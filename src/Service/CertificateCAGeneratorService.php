<?php
namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use RuntimeException;

class CertificateCAGeneratorService
{
    public function validateChain(
        ?UploadedFile $leafFile,
        ?UploadedFile $chainFile,
        ?UploadedFile $rootFile = null
    ): array {
        if (!$leafFile || !$chainFile) {
            throw new RuntimeException("Leaf and chain certificates are required.");
        }

        $leafPem = @file_get_contents($leafFile->getRealPath());
        $chainPem = @file_get_contents($chainFile->getRealPath());
        $rootPem = $rootFile ? @file_get_contents($rootFile->getRealPath()) : null;

        if (!$leafPem || !$chainPem) {
            throw new RuntimeException("Cannot read leaf or chain certificates.");
        }

        $leaf = $this->normalizePem($leafPem);
        if (!$leaf) {
            throw new RuntimeException("Leaf certificate is invalid.");
        }

        $chainCerts = $this->uniqueCerts($this->extractPemCertificates($chainPem));

        if ($chainCerts === []) {
            throw new RuntimeException("Chain certificates are invalid.");
        }

        $pool = array_merge([$leaf], $chainCerts);
        $expectedRoot = null;

        if ($rootPem) {
            $normalizedRoot = $this->normalizePem($rootPem);
            $pool[] = $normalizedRoot;
            $expectedRoot = $normalizedRoot;
        }

        $fullChain = $this->buildChain($pool, $expectedRoot);

        if ($fullChain === false) {
            throw new RuntimeException(
                $expectedRoot
                    ? "Untrusted root certificate."
                    : "Incomplete chain."
            );
        }

        return $fullChain;
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
