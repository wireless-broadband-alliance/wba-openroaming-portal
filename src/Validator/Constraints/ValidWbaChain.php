<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidWbaChain extends Constraint
{
    public string $message = 'The certificate is not signed by a trusted WBA root CA.';

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
