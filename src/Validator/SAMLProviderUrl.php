<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class SAMLProviderUrl extends Constraint
{
    public $message = 'The provided SAML Provider URL ({{ url }}) is invalid: {{ reason }}.';
}
