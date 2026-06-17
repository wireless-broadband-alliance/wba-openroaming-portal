<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ValidCertificateChainValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidCertificateChain) {
            return;
        }

        if (!$value) {
            return;
        }

        $certFile = $value->{$constraint->certField} ?? null;
        $chainFile = $value->{$constraint->chainField} ?? null;

        if (!$certFile instanceof UploadedFile || !$chainFile instanceof UploadedFile) {
            return; // Other validators (File, etc.) handle missing/invalid types
        }

        $certContent = @file_get_contents($certFile->getRealPath());
        $chainContent = @file_get_contents($chainFile->getRealPath());

        if (!$certContent || !$chainContent) {
            return;
        }

        $cert = @openssl_x509_read($certContent);
        if (!$cert) {
            return;
        }

        $certData = openssl_x509_parse($cert);
        if (!$certData || !isset($certData['issuer'])) {
            return;
        }

        // Extract ALL certs from the chain PEM file
        $chainCerts = $this->parsePemBundle($chainContent);
        if ($chainCerts === []) {
            return;
        }

        // Check if ANY cert in the chain has a subject matching the leaf's issuer
        $issuerFound = false;
        foreach ($chainCerts as $chainCertPem) {
            $chainCert = @openssl_x509_read($chainCertPem);
            if (!$chainCert) {
                continue;
            }

            $chainData = openssl_x509_parse($chainCert);
            if (!$chainData || !isset($chainData['subject'])) {
                continue;
            }

            if ($certData['issuer'] === $chainData['subject']) {
                $issuerFound = true;
                break;
            }
        }

        if (!$issuerFound) {
            $this->context->buildViolation($constraint->message)
                ->atPath($constraint->chainField)
                ->addViolation();
        }
    }

    /** @return list<string> */
    private function parsePemBundle(string $content): array
    {
        preg_match_all(
            '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',
            $content,
            $matches
        );

        return $matches[0];
    }
}
