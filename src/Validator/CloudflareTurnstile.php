<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class CloudflareTurnstile extends Constraint
{
    public string $message = 'Verification failed. Please to check the turnstile validation and try again.';
}
