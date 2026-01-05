<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ValidTrustAnchorValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidTrustAnchor) {
            return;
        }

        if (!$value) {
            return;
        }

        $certFile = $value->{$constraint->certField} ?? null;
        $chainFile = $value->{$constraint->chainField} ?? null;
        $rootFile = $value->{$constraint->rootField} ?? null;

        if (!$certFile instanceof UploadedFile || !$chainFile instanceof UploadedFile) {
            return;
        }

        $certPem = @file_get_contents($certFile->getRealPath());
        $chainPem = @file_get_contents($chainFile->getRealPath());
        $rootPem = $rootFile instanceof UploadedFile ? @file_get_contents($rootFile->getRealPath()) : null;

        if (!$certPem || !$chainPem) {
            return;
        }

        $chainCerts = $this->extractPemCertificates($chainPem);
        $current = openssl_x509_parse($certPem);

        if (!is_array($current) || !isset($current['issuer'])) {
            $this->context->buildViolation($constraint->invalidCertificateMessage)
                ->atPath($constraint->certField)
                ->addViolation();
            return;
        }

        // Ensure all parsed certs are valid
        foreach ($chainCerts as $intermediatePem) {
            $intermediate = openssl_x509_parse($intermediatePem);

            if (!is_array($intermediate) || !isset($intermediate['subject'])) {
                $this->context->buildViolation($constraint->invalidCertificateMessage)
                    ->atPath($constraint->chainField)
                    ->addViolation();
                return;
            }

            if (!isset($current['issuer']) || $current['issuer'] !== $intermediate['subject']) {
                $this->context->buildViolation($constraint->incompleteChainMessage)
                    ->atPath($constraint->chainField)
                    ->addViolation();
                return;
            }

            $current = $intermediate;
        }

        // Check final trust anchor
        if ($rootPem) {
            $root = openssl_x509_parse($rootPem);

            if (
                !is_array($root)
                || !isset($current['issuer'], $root['subject'])
                || $current['issuer'] !== $root['subject']
            ) {
                $this->context->buildViolation($constraint->untrustedRootMessage)
                    ->atPath($constraint->rootField)
                    ->addViolation();
            }
        } elseif (!isset($current['issuer'], $current['subject']) || $current['issuer'] !== $current['subject']) {
            $this->context->buildViolation($constraint->incompleteChainMessage)
                ->atPath($constraint->chainField)
                ->addViolation();
        }
    }

    /**
     * Extract individual PEM certificates from a chain.
     *
     * @param string $pem Full PEM chain content
     * @return string[] List of individual PEM certificates
     */
    private function extractPemCertificates(string $pem): array
    {
        preg_match_all(
            '/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s',
            $pem,
            $matches
        );

        /** @var string[] $matches [1] always exists from preg_match_all */
        return array_map(
            static fn(string $data): string => "-----BEGIN CERTIFICATE-----$data-----END CERTIFICATE-----",
            (array)$matches[1]
        );
    }
}
