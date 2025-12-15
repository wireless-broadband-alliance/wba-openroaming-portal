<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueUUID extends Constraint
{
  public string $message = 'uniqueUUID';

  public function getTargets(): string
  {
    return self::PROPERTY_CONSTRAINT;
  }
}
