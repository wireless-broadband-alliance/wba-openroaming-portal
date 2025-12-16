<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class DomainNotWhitelisted extends Constraint
{
    public string $message = 'domainNotWhitelisted';

    #[\Override]
    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
