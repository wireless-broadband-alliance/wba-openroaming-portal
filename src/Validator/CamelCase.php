<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class CamelCase extends Constraint
{
    public string $message = 'The value "{{ string }}" must be in camelCase format (e.g., mySamlProvider).';
}
