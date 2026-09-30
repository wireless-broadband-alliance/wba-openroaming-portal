<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
final class IpListConstraint extends Constraint
{
    public string $message = 'Invalid IP or subnet: {{ value }}';
}
