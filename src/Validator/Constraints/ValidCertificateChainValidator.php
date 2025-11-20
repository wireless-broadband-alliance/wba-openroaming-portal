<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ValidCertificateChainValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$value) {
            return;
        }

        $certFile = $value->{$constraint->certField} ?? null;
        $chainFile = $value->{$constraint->chainField} ?? null;

        if (!$certFile instanceof UploadedFile || !$chainFile instanceof UploadedFile) {
            return; // Other validators (File, etc.) handle missing/invalid types
        }

        $certContent  = @file_get_contents($certFile->getRealPath());
        $chainContent = @file_get_contents($chainFile->getRealPath());

        if (!$certContent || !$chainContent) {
            return;
        }

        $cert = @openssl_x509_read($certContent);
        $chain = @openssl_x509_read($chainContent);

        if (!$cert || !$chain) {
            return; // Already validated elsewhere
        }

        $certData  = openssl_x509_parse($cert);
        $chainData = openssl_x509_parse($chain);

        if (!isset($certData['issuer'], $chainData['subject'])) {
            return;
        }

        // Compare issuer of certificate with subject of chain
        if ($certData['issuer'] !== $chainData['subject']) {
            $this->context->buildViolation($constraint->message)
                ->atPath($constraint->chainField)
                ->addViolation();
        }
    }
}
