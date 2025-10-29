<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CapportURLValidator extends ConstraintValidator
{
    /**
     * @param mixed $value
     * @param Constraint $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CapportURL) {
            throw new UnexpectedTypeException($constraint, CapportURL::class);
        }

        $object = $this->context->getObject();

        // Only validate if the controlling property is 'true'
        if (!isset($object->{$constraint->enabledProperty}) || $object->{$constraint->enabledProperty} !== 'true') {
            return;
        }

        // Check for empty
        if (empty($value)) {
            $this->context->buildViolation($constraint->messageNotBlank)
                ->addViolation();
            return;
        }

        // Validate URL
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->context->buildViolation($constraint->messageInvalidUrl)
                ->addViolation();
        }
    }
}
