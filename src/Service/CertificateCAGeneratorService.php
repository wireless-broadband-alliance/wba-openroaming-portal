<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\Translation\TranslatorInterface;

class CertificateCAGeneratorService
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    private array $messages = [];

    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Generate the root CA certificate from leaf and chain files.
     *
     * @param File $certFile Leaf certificate (uploaded file)
     * @param File $chainFile Chain certificate bundle (uploaded file)
     *
     * @return string PEM content of the root certificate
     */
    public function generateCA(File $certFile, File $chainFile): ?string
    {
        // Make sure files exist
        $leafPath = $certFile->getRealPath();
        $chainPath = $chainFile->getRealPath();

        if (!$leafPath || !file_exists($leafPath)) {
            $this->messages[] = $this->translator->trans(
                'leafCertificateFileNotFound',
                [],
                'CertificateCAGeneratorService'
            );
            return null;
        }

        if (!$chainPath || !file_exists($chainPath)) {
            $this->messages[] = $this->translator->trans(
                'chainCertificateFileNotFound',
                [],
                'CertificateCAGeneratorService'
            );
            return null;
        }

        // Load contents
        $leafPem = $this->normalizePem(file_get_contents($leafPath));
        $chainPemArray = $this->extractPemCertificates(file_get_contents($chainPath));

        // Deduplicate and pool
        $pool = array_merge([$leafPem], $this->uniqueCerts($chainPemArray));

        // Find root certificate (self-signed)
        $root = null;
        foreach ($pool as $cert) {
            $parsed = openssl_x509_parse($cert);
            if (
                $parsed &&
                isset(
                    $parsed['subject'],
                    $parsed['issuer']
                ) && $parsed['subject'] === $parsed['issuer']
            ) {
                $root = $cert;
                break;
            }
        }

        // fallback: maybe the last certificate in the chain
        if (!$root) {
            $root = end($pool);
            if (!$root) {
                $this->messages[] = $this->translator->trans(
                    'noValidRootCertificateFoundChain',
                    [],
                    'CertificateCAGeneratorService'
                );
                return null;
            }
        }

        // Validate full chain
        $fullChain = $this->buildChain($pool, $root);
        if ($fullChain === false) {
            $this->messages[] = $this->translator->trans(
                'incompleteOrInvalidCertificateChain',
                [],
                'CertificateCAGeneratorService'
            );
            return null;
        }

        return $root;
    }

    /**
     * @param string[] $pool Array of PEM certificates
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
        $parsed = openssl_x509_parse($cert);
        if ($parsed === false) {
            return false;
        }

        // Compare subject and issuer DN
        return $parsed['subject'] === $parsed['issuer'];
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
