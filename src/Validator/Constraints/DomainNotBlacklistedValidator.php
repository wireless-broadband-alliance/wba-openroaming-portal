<?php

namespace App\Validator\Constraints;

use App\Repository\DomainBlacklistRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DomainNotBlacklistedValidator extends ConstraintValidator
{
    public function __construct(
        private readonly DomainBlacklistRepository $domainBlacklistRepository
    ) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DomainNotBlacklisted) {
            return;
        }

        // Let NotBlank / NotNull handle this
        if ($value === null || $value === '') {
            return;
        }

        $domain = strtolower(trim((string) $value));

        if ($this->domainBlacklistRepository->isDomainBlacklisted($domain)) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
