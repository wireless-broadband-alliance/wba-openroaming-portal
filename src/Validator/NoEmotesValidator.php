<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
class NoEmotesValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        // Define a regular expression pattern to match emojis
        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}]/u';

        if (preg_match($emojiPattern, $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}

