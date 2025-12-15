<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PasswordsMatchValidator extends ConstraintValidator
{
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof PasswordsMatch) {
      return;
    }

    if (!property_exists($value, 'password') || !property_exists($value, 'confirmPassword')) {
      return;
    }

    if ($value->password === null || $value->confirmPassword === null) {
      return;
    }

    if ($value->password !== $value->confirmPassword) {
      $this->context->buildViolation($constraint->message)
          ->atPath('confirmPassword')
          ->addViolation();
    }
  }
}
