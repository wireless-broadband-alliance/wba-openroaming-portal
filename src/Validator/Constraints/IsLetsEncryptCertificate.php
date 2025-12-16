<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class IsLetsEncryptCertificate extends Constraint
{
    public string $message = 'certificateIsLetsEncrypt';

    #[\Override]
    public function validatedBy(): string
    {
        return static::class . 'Validator';
    }
}
