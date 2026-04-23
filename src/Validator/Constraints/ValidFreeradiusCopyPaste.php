<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidFreeradiusCopyPaste extends Constraint
{
    public string $message = 'freeradius_certificate.invalid_bundle';
}
