<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidPemCertificate extends Constraint
{
    public string $invalidFormatMessage = 'mustBeValidCertPEMX509';
    public string $expiredMessage = 'certPEMX509Expired';

    // This makes it usable on a property
    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }

    // Optional for later if its need it can create a custom validator class here
    public function validatedBy(): string
    {
        return static::class.'Validator';
    }
}
