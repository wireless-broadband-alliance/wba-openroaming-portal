<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueUUID extends Constraint
{
  public string $message = 'uniqueUUID';
}

