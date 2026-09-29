<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
final class IpListConstraint extends Constraint
{
    public string $message = 'Invalid IP or subnet: {{ value }}';
}
