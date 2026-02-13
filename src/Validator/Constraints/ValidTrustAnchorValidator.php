<?php

namespace App\Validator\Constraints;

use RuntimeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ValidTrustAnchorValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidTrustAnchor || !$value) {
            return;
        }

        $certFile = $value->{$constraint->certField} ?? null;
        $chainFile = $value->{$constraint->chainField} ?? null;
        $rootFile = $value->{$constraint->rootField} ?? null;

        if (!$certFile instanceof UploadedFile || !$chainFile instanceof UploadedFile) {
            return;
        }

        $leafPem = @file_get_contents($certFile->getRealPath());
        $chainPem = @file_get_contents($chainFile->getRealPath());
        $rootPem = $rootFile instanceof UploadedFile
            ? @file_get_contents($rootFile->getRealPath())
            : null;

        if (!$leafPem || !$chainPem) {
            return;
        }

        $leaf = $this->normalizePem($leafPem);

        $chainCerts = $this->uniqueCerts(
            $this->extractPemCertificates($chainPem)
        );

        if ($chainCerts === []) {
            $this->violate($constraint->invalidCertificateMessage, $constraint->chainField);
            return;
        }

        // Build a pool of all possible issuers
        $pool = array_merge([$leaf], $chainCerts);

        if ($rootPem) {
            $pool[] = $this->normalizePem($rootPem);
        }

        $pool = $this->uniqueCerts($pool);

        if (!$this->buildPathToTrustAnchor($leaf, $pool, $rootPem)) {
            $this->violate(
                $rootPem
                    ? $constraint->untrustedRootMessage
                    : $constraint->incompleteChainMessage,
                $rootPem ? $constraint->rootField : $constraint->chainField
            );
        }
    }

    private function buildPathToTrustAnchor(
        string $current,
        array $pool,
        ?string $expectedRoot,
        array $visited = []
    ): bool {
        $fingerprint = openssl_x509_fingerprint($current);

        if (isset($visited[$fingerprint])) {
            return false; // prevent loops in cross-signed graphs
        }

        $visited[$fingerprint] = true;

        // If a root was supplied → we must reach THAT root
        if ($expectedRoot && $this->certEquals($current, $expectedRoot)) {
            return true;
        }

        // If no root supplied → any self-signed cert is a trust anchor
        if (!$expectedRoot && $this->isSelfSigned($current)) {
            return true;
        }

        // Try every possible issuer candidate
        foreach ($pool as $candidate) {
            if ($this->certEquals($candidate, $current)) {
                continue;
            }

            if (
                $this->verifySignature($current, $candidate) &&
                $this->buildPathToTrustAnchor($candidate, $pool, $expectedRoot, $visited)
            ) {
                return true;
            }
        }

        return false;
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
    ): string {
        if (
            !preg_match(
                '/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s',
                $pem,
                $match
            )
        ) {
            throw new RuntimeException('Invalid PEM format');
        }

        return "-----BEGIN CERTIFICATE-----{$match[1]}-----END CERTIFICATE-----\n";
    }

    private function uniqueCerts(
        array $certs
    ): array {
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

    private function violate(
        string $message,
        string $path
    ): void {
        $this->context->buildViolation($message)
            ->atPath($path)
            ->addViolation();
    }
}
