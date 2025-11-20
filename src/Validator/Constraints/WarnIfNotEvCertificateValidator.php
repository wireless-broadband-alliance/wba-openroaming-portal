<?php

namespace App\Validator\Constraints;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class WarnIfNotEvCertificateValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof UploadedFile) {
            return; // skip if no file uploaded
        }

        $content = @file_get_contents($value->getPathname());
        if (!$content) {
            return;
        }

        $cert = @openssl_x509_read($content);
        if (!$cert) {
            return;
        }

        $details = openssl_x509_parse($cert);
        if (!$details) {
            return;
        }

        // EV certificates usually have a specific OID in the policy
        $evOids = ['2.23.140.1.1']; // CA/Browser Forum EV OID

        $isEv = false;
        if (!empty($details['extensions']['certificatePolicies'])) {
            $policies = $details['extensions']['certificatePolicies'];

            // Ensure $policies is always an array
            if (is_string($policies)) {
                $policies = [$policies];
            }

            foreach ($policies as $policy) {
                foreach ($evOids as $oid) {
                    if (str_contains($policy, $oid)) {
                        $isEv = true;
                        break 2;
                    }
                }
            }
        }

        if (!$isEv) {
            // Non-blocking notice: safely add to DTO if available
            $object = $this->context?->getObject(); // null-safe operator
            if ($object && property_exists($object, 'warning') && is_array($object->notices)) { // TODO Check this
                $object->notices[] = 'Certificate is not EV (optional warning).';
            }
        }
    }
}
