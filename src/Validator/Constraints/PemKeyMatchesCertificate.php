<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PemKeyMatchesCertificate extends Constraint
{
    public string $message = 'privateKeyDoesntMatchCertificate';

    // Class-level constraint
    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    #[\Override]
    public function validatedBy(): string
    {
        return static::class . 'Validator';
    }
}
