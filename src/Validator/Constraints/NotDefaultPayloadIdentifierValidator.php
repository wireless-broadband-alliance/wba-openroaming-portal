<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Enum\RadiusDefaultValues;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class NotDefaultPayloadIdentifierValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotDefaultPayloadIdentifier) {
            throw new UnexpectedTypeException($constraint, NotDefaultPayloadIdentifier::class);
        }

        // Ignore null or empty values
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Compare against enum value
        if ($value === RadiusDefaultValues::PAYLOAD_IDENTIFIER->value) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
