<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidRsaCertificate extends Constraint
{
    public string $message = 'invalidCertificateType';

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
