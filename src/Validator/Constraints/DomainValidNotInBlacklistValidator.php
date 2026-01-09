<?php

namespace App\Validator\Constraints;

use App\Enum\DomainMatchType;
use App\Repository\DomainBlacklistRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DomainValidNotInBlacklistValidator extends ConstraintValidator
{
    public function __construct(private readonly DomainBlacklistRepository $domainBlacklistRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DomainValidNotInBlacklist) {
            return;
        }

        if ($value === null || $value === '') {
            return;
        }

        // Split the input by commas (in case multiple emails/domains)
        $inputs = array_map(trim(...), explode(',', (string)$value));

        foreach ($this->domainBlacklistRepository->findAll() as $domainDB) {
            $pattern = strtolower($domainDB->getPattern());
            $type = $domainDB->getType();

            foreach ($inputs as $input) {
                $input = strtolower($input);

                // Extract domain part if it's an email
                if (str_contains($input, '@')) {
                    [$local, $domain] = explode('@', $input, 2);
                } else {
                    $domain = $input;
                }

                if ($type === DomainMatchType::WILDCARD) {
                    // Block everything
                    $this->context->buildViolation($constraint->message)->addViolation();
                    return;
                }

                if ($type === DomainMatchType::EXACT && $domain === $pattern) {
                    $this->context->buildViolation($constraint->message)->addViolation();
                    return;
                }

                // Block subdomains: domain matches exactly or ends with ".pattern"
                if (
                    $type === DomainMatchType::SUBDOMAIN
                    && ($domain === $pattern || str_ends_with($domain, '.' . $pattern))
                ) {
                    $this->context->buildViolation($constraint->message)->addViolation();
                    return;
                }
            }
        }
    }
}
