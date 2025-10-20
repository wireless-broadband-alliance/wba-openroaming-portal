<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use DateTimeImmutable;

class ValidPemCertificateValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidPemCertificate) {
            return;
        }

        if (!$value instanceof UploadedFile) {
            return; // Skip validation if no file, let Assert\File handles it
        }

        $contents = @file_get_contents($value->getPathname());
        $certResource = @openssl_x509_read($contents);

        if (!$certResource) {
            $this->context->buildViolation($constraint->invalidFormatMessage)
                ->addViolation();
            return;
        }

        $certInfo = openssl_x509_parse($certResource);
        if ($certInfo && isset($certInfo['validTo_time_t'])) {
            $validTo = new DateTimeImmutable()->setTimestamp((int)$certInfo['validTo_time_t']);
            if ($validTo < new DateTimeImmutable()) {
                $this->context->buildViolation($constraint->expiredMessage)
                    ->addViolation();
            }
        }
    }
}
