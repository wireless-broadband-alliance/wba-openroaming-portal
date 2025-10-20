<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PemKeyMatchesCertificate extends Constraint
{
    public string $message = 'privateKeyDoesntMatchCertificate';

    // Class-level constraint
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return static::class.'Validator';
    }
}
