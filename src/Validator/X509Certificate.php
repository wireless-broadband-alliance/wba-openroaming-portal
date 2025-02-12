<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class X509Certificate extends Constraint
{
    public string $message = 'The provided X509 certificate is invalid: {{ error }}.';
}
