<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidCronSettings extends Constraint
{
    public array $cronSettings = [];

    public function __construct(array $options)
    {
        parent::__construct($options);
        if (isset($options['cronSettings'])) {
            $this->cronSettings = $options['cronSettings'];
        }
    }

    #[\Override]
    public function validatedBy(): string
    {
        return ValidCronSubmissionValidator::class;
    }
}
