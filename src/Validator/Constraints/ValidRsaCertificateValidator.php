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
        $cert = openssl_x509_read($content);

        if ($cert === false) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        $details = openssl_x509_parse($cert);
        if (!is_array($details) || !isset($details['sig_alg_name'])) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        if (stripos($details['sig_alg_name'], 'RSA') === false) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
