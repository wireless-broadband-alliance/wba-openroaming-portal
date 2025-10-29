<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidCronSettings extends Constraint
{
    /**
     * @var string[] List of cron setting names
     */
    public array $cronSettings = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options)
    {
        parent::__construct($options);

        if (isset($options['cronSettings']) && is_array($options['cronSettings'])) {
            $this->cronSettings = array_map(strval(...), $options['cronSettings']);
        }
    }

    #[\Override]
    public function validatedBy(): string
    {
        return ValidCronSubmissionValidator::class;
    }
}
