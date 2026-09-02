<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class CurrentPasswordValid extends Constraint
{
    public string $message = 'currentPasswordIncorrect';

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
