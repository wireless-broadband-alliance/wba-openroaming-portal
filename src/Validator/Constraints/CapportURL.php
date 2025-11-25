<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class CapportURL extends Constraint
{
    public string $messageNotBlank = 'fieldCannotBeBlank';
    public string $messageInvalidUrl = 'valueNotValidURL';

    public function __construct(public string $enabledProperty, ?array $groups = null)
    {
        parent::__construct(groups: $groups);
    }

    #[\Override]
    public function validatedBy(): string
    {
        return static::class . 'Validator';
    }
}
