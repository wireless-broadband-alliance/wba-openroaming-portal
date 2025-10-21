<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use App\DTO\CertificateRadSecUploadDTO;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PemKeyMatchesCertificateValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof PemKeyMatchesCertificate) {
            return;
        }

        // Ensure we have the expected DTO
        if (!$value instanceof CertificateRadSecUploadDTO) {
            return;
        }

        $client = $value->client;
        $key = $value->key;

        if (!$client instanceof UploadedFile || !$key instanceof UploadedFile) {
            return; // Skip if either file is missing, let Assert\File handles it
        }

        $certContents = @file_get_contents($client->getPathname());
        $keyContents = @file_get_contents($key->getPathname());

        $certResource = @openssl_x509_read($certContents);
        $privateKeyResource = @openssl_pkey_get_private($keyContents);

        if (!$certResource || !$privateKeyResource) {
            return; // Skip if either is invalid, let ValidPemCertificate handles it
        }

        $publicKey = openssl_pkey_get_public($certResource);

        if (!$publicKey) {
            return; // Should not happen if certificate is valid
        }

        $certDetails = openssl_pkey_get_details($publicKey);
        $keyDetails = openssl_pkey_get_details($privateKeyResource);

        if (!$certDetails || !$keyDetails || $certDetails['key'] !== $keyDetails['key']) {
            $this->context->buildViolation($constraint->message)
                ->atPath('key') // points to the private key field
                ->addViolation();
        }
    }
}
