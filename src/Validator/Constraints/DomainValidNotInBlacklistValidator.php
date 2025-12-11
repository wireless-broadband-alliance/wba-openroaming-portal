<?php

namespace App\Validator\Constraints;

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

        // skip empty values (let NotBlank handle those)
        if ($value === null || $value === '') {
            return;
        }

        // Split the valid domains into an array and trim whitespace
        $validDomains = explode(',', $value);
        $validDomains = array_map(trim(...), $validDomains);

        // Validate Blacklist domains
        foreach ($this->domainBlacklistRepository->findAll() as $domainDB) {
            if (in_array($domainDB->getDomain(), $validDomains, true)) {
                $this->context->buildViolation($constraint->message)->addViolation();
                return;
            }
        }
    }
}
