<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ValidRsaCertificateValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof UploadedFile) {
            return;
        }

        $content = file_get_contents($value->getPathname());
        if ($content === false) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Normalize Windows EOLs
        $content = str_replace("\r\n", "\n", $content);

        // Split into individual certificates in PEM file
        preg_match_all(
            '/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s',
            $content,
            $matches
        );

        if (empty($matches[0])) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        foreach ($matches[0] as $pemCert) {
            $certResource = @openssl_x509_read($pemCert);
            if (!$certResource) {
                $this->context->buildViolation($constraint->message)->addViolation();
                return;
            }

            $pubKey = openssl_pkey_get_public($certResource);
            if (!$pubKey) {
                $this->context->buildViolation($constraint->message)->addViolation();
                return;
            }

            $keyDetails = openssl_pkey_get_details($pubKey);
            if ($keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
                $this->context->buildViolation($constraint->message)->addViolation();
                return;
            }
        }
    }
}
