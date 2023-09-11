<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NoEmotesValidator extends ConstraintValidator
{
    /**
     * @param $value
     * @param Constraint $constraint
     * @return void
     * This validator finds emoji characters in the input string.
     * If emojis are present, it indicates a violation using the NoEmotes constraint's stated error message.
     */
    public function validate($value, Constraint $constraint): void
    {
        /** @var NoEmotes $constraint */
        // Define a regular expression pattern to match emojis
        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}]/u';

        if (preg_match($emojiPattern, $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }

}

