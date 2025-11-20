<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class IsLetsEncryptCertificate extends Constraint
{
    public string $message = 'The uploaded certificate is not issued by Let\'s Encrypt.';

    public function validatedBy(): string
    {
        return static::class . 'Validator';
    }
}
