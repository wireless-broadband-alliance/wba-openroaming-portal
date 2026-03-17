<?php

namespace App\Validator\Constraints;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class WarnIfNotEvCertificateValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof UploadedFile) {
            return; // skip if no file uploaded
        }

        $content = @file_get_contents($value->getPathname());
        if ($content === false) {
            return;
        }

        $cert = @openssl_x509_read($content);
        if ($cert === false) {
            return;
        }

        $details = openssl_x509_parse($cert);
        if ($details === false) {
            return;
        }

        // EV certificates usually have a specific OID in the policy
        $evOids = ['2.23.140.1.1']; // CA/Browser Forum EV OID

        $isEv = false;

        if (!empty($details['extensions']['certificatePolicies'])) {
            $policies = $details['extensions']['certificatePolicies'];

            if (is_string($policies)) {
                $policies = [$policies];
            }

            foreach ($policies as $policy) {
                foreach ($evOids as $oid) {
                    if (str_contains((string) $policy, $oid)) {
                        $isEv = true;
                        break 2;
                    }
                }
            }
        }

        if (!$isEv) {
            // Non-blocking notice: safely add to DTO if available
            $object = $this->context->getObject();

            if ($object !== null && property_exists($object, 'notices') && is_array($object->notices)) {
                $object->notices[] = 'CERTIFICATE_NOT_EV_WARNING';
            }
        }
    }
}
