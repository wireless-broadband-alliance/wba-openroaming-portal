<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ValidTrustAnchorValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
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

        // Ensure all parsed certs are valid
        foreach ($chainCerts as $intermediatePem) {
            $intermediate = openssl_x509_parse($intermediatePem);
            if (!$intermediate) {
                $this->context->buildViolation($constraint->invalidCertificateMessage)
                    ->atPath($constraint->chainField)
                    ->addViolation();
                return;
            }
            if ($current['issuer'] !== $intermediate['subject']) {
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
            if (!$root || $current['issuer'] !== $root['subject']) {
                $this->context->buildViolation($constraint->untrustedRootMessage)
                    ->atPath($constraint->rootField)
                    ->addViolation();
            }
        } elseif ($current['issuer'] !== $current['subject']) {
            $this->context->buildViolation($constraint->incompleteChainMessage)
                ->atPath($constraint->chainField)
                ->addViolation();
        }
    }

    private function extractPemCertificates(string $pem): array
    {
        preg_match_all(
            '/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s',
            $pem,
            $matches
        );

        return array_map(static fn($data) => "-----BEGIN CERTIFICATE-----$data-----END CERTIFICATE-----", $matches[1] ?? []);
    }
}
