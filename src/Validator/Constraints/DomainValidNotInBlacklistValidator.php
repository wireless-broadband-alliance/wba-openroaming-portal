<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Repository\DomainBlacklistRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DomainValidNotInBlacklistValidator extends ConstraintValidator
{
    public function __construct(
        private readonly DomainBlacklistRepository $domainBlacklistRepository
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DomainValidNotInBlacklist) {
            return;
        }

        if ($value === null || $value === '') {
            return;
        }

        // Split and normalize
        $domains = [];

        foreach (explode(',', (string)$value) as $input) {
            $input = strtolower(trim($input));

            if ($input === '') {
                continue;
            }

            // Extract domain from email
            if (str_contains($input, '@')) {
                [, $input] = explode('@', $input, 2);
            }

            $domains[] = $input;
        }

        if ($domains === []) {
            return;
        }

        if ($this->domainBlacklistRepository->matchesAnyDomain($domains)) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
