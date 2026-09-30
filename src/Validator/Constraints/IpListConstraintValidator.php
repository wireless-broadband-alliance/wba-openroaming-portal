<?php

namespace App\Validator\Constraints;

use App\Service\AdminIpProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class IpListConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof IpListConstraint) {
            throw new UnexpectedTypeException($constraint, IpListConstraint::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        foreach (AdminIpProvider::parse((string)$value) as $entry) {
            if (!$this->isValidEntry($entry)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $entry)
                    ->addViolation();
            }
        }
    }

    private function isValidEntry(string $entry): bool
    {
        [$ip, $mask] = array_pad(explode('/', $entry, 2), 2, null);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if ($mask === null) {
            return true;
        }
        $max = str_contains($ip, ':') ? 128 : 32;

        return ctype_digit($mask) && (int)$mask <= $max;
    }
}
