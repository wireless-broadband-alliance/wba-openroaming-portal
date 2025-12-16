<?php

namespace App\Validator\Constraints;

use LogicException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IpOrCidrValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof IpOrCidr) {
            throw new LogicException(sprintf(
                'The constraint must be an instance of %s',
                IpOrCidr::class
            ));
        }

        if (null === $value || '' === $value) {
            // Let NotBlank handle empty values if needed
            return;
        }

        if (!is_string($value)) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Single IPv4 or IPv6
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return;
        }

        // CIDR notation regex (IPv4 only)
        if (preg_match('/^(?:\d{1,3}\.){3}\d{1,3}\/(?:\d|[1-2]\d|3[0-2])$/', $value)) {
            return;
        }

        $this->context
            ->buildViolation($constraint->message)
            ->addViolation();
    }
}
