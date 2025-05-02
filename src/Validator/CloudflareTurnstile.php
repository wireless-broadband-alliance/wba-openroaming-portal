<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class CloudflareTurnstile extends Constraint
{
    public string $message = 'Invalid Turnstile validation response';
}
