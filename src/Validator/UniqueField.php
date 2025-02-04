<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueField extends Constraint
{
    public string $message = 'The value "{{ value }}" is already in use.';
    public string $field;

    #[\Override]
    public function getRequiredOptions(): array
    {
        return ['field'];
    }
}
