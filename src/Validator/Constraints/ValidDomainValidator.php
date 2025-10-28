<?php

namespace App\Validator\Constraints;

use App\Service\DomainService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidDomainValidator extends ConstraintValidator
{
    public function __construct(
        private readonly DomainService $domainService
    ) {
    }

    /**
     * @param mixed $value
     * @param Constraint $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidDomain) {
            return;
        }

        // skip empty values (let NotBlank handle those)
        if ($value === null || $value === '') {
            return;
        }

        if (!$this->domainService->isValidDomain($value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
