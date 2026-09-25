<?php

declare(strict_types=1);

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

        $passwordField = $constraint->passwordField;
        $confirmPasswordField = $constraint->confirmPasswordField;

        if (!property_exists($value, $passwordField) || !property_exists($value, $confirmPasswordField)) {
            return;
        }

        $password = $value->$passwordField;
        $confirmPassword = $value->$confirmPasswordField;

        if ($password === null || $confirmPassword === null) {
            return;
        }

        if ($password !== $confirmPassword) {
            $this->context->buildViolation($constraint->message)
                ->atPath($confirmPasswordField)
                ->addViolation();
        }
    }
}
