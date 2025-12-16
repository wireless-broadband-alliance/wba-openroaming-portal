<?php

namespace App\Validator\Constraints;

use App\Repository\DomainBlacklistRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DomainNotBlacklistedValidator extends ConstraintValidator
{
    public function __construct(private readonly DomainBlacklistRepository $domainBlacklistRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DomainNotBlacklisted) {
            return;
        }

        // skip empty values (let NotBlank handle those)
        if ($value === null || $value === '') {
            return;
        }

        // Verify if domain is in the blacklist table
        foreach ($this->domainBlacklistRepository->findAll() as $domainDB) {
            if ($domainDB->getDomain() === $value) {
                $this->context->buildViolation($constraint->message)->addViolation();
                return;
            }
        }
    }
}
