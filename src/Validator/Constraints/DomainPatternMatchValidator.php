<?php

namespace App\Validator\Constraints;

use App\DTO\DomainBlacklistAddDTO;
use App\Enum\DomainMatchType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DomainPatternMatchValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof DomainBlacklistAddDTO) {
            return;
        }

        if (!$constraint instanceof DomainPatternMatch) {
            return;
        }

        if ($value->input === null || !$value->matchType instanceof DomainMatchType) {
            return;
        }

        $hasWildcard = str_contains($value->input, '*');

        if ($value->matchType === DomainMatchType::EXACT && $hasWildcard) {
            $this->context
                ->buildViolation($constraint->exactNoWildcard)
                ->atPath('input')
                ->addViolation();
        }

        if ($value->matchType === DomainMatchType::SUBDOMAIN && !str_starts_with($value->input, '*.')) {
            $this->context
                ->buildViolation($constraint->subdomainRequiresWildcard)
                ->atPath('input')
                ->addViolation();
        }
    }
}
