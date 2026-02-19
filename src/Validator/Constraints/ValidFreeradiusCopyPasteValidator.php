<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidFreeradiusCopyPasteValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidFreeradiusHTTPChallenge) {
            return;
        }

        if (!$value) {
            return; // let NotBlank handle empty values
        }

        $certs = preg_split("/(?=-----BEGIN )/", $value, -1, PREG_SPLIT_NO_EMPTY);

        $leafCert = null;
        $privateKey = null;

        foreach ($certs as $pem) {
            if (str_contains($pem, 'PRIVATE KEY')) {
                $privateKey = openssl_pkey_get_private($pem);
            } elseif (str_contains($pem, 'CERTIFICATE')) {
                // Typically, the first certificate is the leaf
                if (!$leafCert) {
                    $leafCert = openssl_x509_read($pem);
                }
            }
        }

        if (!$leafCert || !$privateKey) {
            $this->context->buildViolation('freeradius_certificate.invalid_bundle')
                ->addViolation();
            return;
        }

        // Test if private key matches the leaf certificate
        $csr = @openssl_csr_new([], $privateKey, ['digest_alg' => 'sha256']);
        if ($csr === false) {
            $this->context->buildViolation('freeradius_certificate.mismatch_key')
                ->addViolation();
            return;
        }

        // Check expiration
        $certData = openssl_x509_parse($leafCert);
        if ($certData === false) {
            $this->context->buildViolation('freeradius_certificate.invalid_bundle')
                ->addViolation();
            return;
        }

        if ($certData['validTo_time_t'] < time()) {
            $this->context->buildViolation('freeradius_certificate.expired')
                ->addViolation();
        }
    }
}
