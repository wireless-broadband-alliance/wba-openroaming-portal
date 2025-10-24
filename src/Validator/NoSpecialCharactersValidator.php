<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NoSpecialCharactersValidator extends ConstraintValidator
{
    /**
     * @return void
     * This validator finds emoji characters in the input string.
     * If emojis are present, it indicates a violation using the NoSpecialCharacters constraint's stated error message.
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        // Compare if he can replace all the inputs for the values specified down bellow to ''
        // If he can, get the confirmation validator message from NoSpecialCharacters.php
        /** @var NoSpecialCharacters $constraint */
        if (preg_replace('/[^<>(_;ç)%]/', '', (string) $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
