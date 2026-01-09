<?php

namespace App\Validator\Constraints;

use App\Enum\DomainMatchType;
use App\Repository\DomainBlacklistRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DomainNotBlacklistedValidator extends ConstraintValidator
{
    public function __construct(
        private readonly DomainBlacklistRepository $domainBlacklistRepository
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DomainNotBlacklisted) {
            return;
        }

        // Skip empty values (let NotBlank handle those)
        if ($value === null || $value === '') {
            return;
        }

        $domain = strtolower(trim((string)$value));

        foreach ($this->domainBlacklistRepository->findAll() as $domainDB) {
            $pattern = $domainDB->getPattern();
            $type = $domainDB->getType();

            // WILDCARD blocks everything
            if ($type === DomainMatchType::WILDCARD) {
                $this->context->buildViolation($constraint->message)->addViolation();
                return;
            }

            // EXACT match
            if ($type === DomainMatchType::EXACT && $domain === $pattern) {
                $this->context->buildViolation($constraint->message)->addViolation();
                return;
            }

            // SUBDOMAIN match
            if (
                $type === DomainMatchType::SUBDOMAIN &&
                ($domain === $pattern || str_ends_with($domain, '.' . $pattern))
            ) {
                $this->context->buildViolation($constraint->message)->addViolation();
                return;
            }
        }
    }
}
