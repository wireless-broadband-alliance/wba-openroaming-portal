<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidFreeradiusHTTPChallenge extends Constraint
{
    public string $message = 'freeradius_certificate.invalid_bundle';
}
