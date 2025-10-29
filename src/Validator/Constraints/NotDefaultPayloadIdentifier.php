<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NotDefaultPayloadIdentifier extends Constraint
{
    public string $message = 'notUseDefaultPayloadIdentifierCard';

    #[\Override]
    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
