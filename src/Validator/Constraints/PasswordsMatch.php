<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class PasswordsMatch extends Constraint
{
    public string $message = 'passwordsDoNotMatch';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
