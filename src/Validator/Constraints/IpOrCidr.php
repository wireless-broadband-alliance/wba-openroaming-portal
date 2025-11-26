<?php

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class IpOrCidr extends Constraint
{
    public string $message = 'notValidIpOrCidr';

    public function validatedBy(): string
    {
        return static::class.'Validator';
    }
}
