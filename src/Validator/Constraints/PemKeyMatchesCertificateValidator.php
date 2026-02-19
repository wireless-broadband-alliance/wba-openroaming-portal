<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PemKeyMatchesCertificateValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PemKeyMatchesCertificate) {
            return;
        }

        // Dynamically access fields by names configured in the constraint
        $certField = $constraint->certificateField;
        $keyField = $constraint->privateKeyField;

        if (!property_exists($value, $certField) || !property_exists($value, $keyField)) {
            return;
        }

        $certFile = $value->$certField;
        $keyFile = $value->$keyField;

        if (!$certFile instanceof UploadedFile || !$keyFile instanceof UploadedFile) {
            return; // Let other validators handle missing files
        }

        $certContents = @file_get_contents($certFile->getPathname());
        $keyContents = @file_get_contents($keyFile->getPathname());

        if ($certContents === false || $keyContents === false) {
            return;
        }

        $certResource = @openssl_x509_read($certContents);
        $privateKeyResource = @openssl_pkey_get_private($keyContents);

        if (!$certResource || !$privateKeyResource) {
            return;
        }

        $publicKey = openssl_pkey_get_public($certResource);
        if (!$publicKey) {
            return;
        }

        $certDetails = openssl_pkey_get_details($publicKey);
        $keyDetails = openssl_pkey_get_details($privateKeyResource);

        if (!$certDetails || !$keyDetails || ($certDetails['key'] ?? null) !== ($keyDetails['key'] ?? null)) {
            $this->context->buildViolation($constraint->message)
                ->atPath($keyField) // points to the private key field
                ->addViolation();
        }
    }
}
