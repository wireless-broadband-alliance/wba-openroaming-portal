<?php

namespace App\Validator\Constraints;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class WarnIfNotEvCertificateValidator extends ConstraintValidator
{
    private const EV_OIDS = [
        '2.23.140.1.1', // CA/B Forum EV TLS
    ];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof UploadedFile) {
            return;
        }

        $content = @file_get_contents($value->getPathname());
        if ($content === false) {
            return;
        }

        $cert = @openssl_x509_read($content);
        if ($cert === false) {
            return;
        }

        $details = openssl_x509_parse($cert, true);
        if ($details === false) {
            return;
        }

        if (!$this->isEvCertificate($details)) {
            $object = $this->context->getObject();
            if ($object !== null && property_exists($object, 'notices') && is_array($object->notices)) {
                $object->notices[] = 'CERTIFICATE_NOT_EV_WARNING';
            }
        }
    }

    private function isEvCertificate(array $details): bool
    {
        $policiesRaw = $details['extensions']['certificatePolicies'] ?? null;

        if (empty($policiesRaw) || !is_string($policiesRaw)) {
            return false;
        }

        preg_match_all('/Policy:\s*([\d.]+)/', $policiesRaw, $matches);

        return array_any($matches[1], fn($oid) => in_array(trim($oid), self::EV_OIDS, true));
    }
}
