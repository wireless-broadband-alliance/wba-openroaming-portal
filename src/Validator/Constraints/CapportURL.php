<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class CapportURL extends Constraint
{
    public string $messageNotBlank = 'fieldCannotBeBlank';
    public string $messageInvalidUrl = 'valueNotValidURL';
    public string $enabledProperty;

    public function __construct(string $enabledProperty, ?array $groups = null)
    {
        parent::__construct(groups: $groups);
        $this->enabledProperty = $enabledProperty;
    }

    public function validatedBy(): string
    {
        return static::class . 'Validator';
    }
}
