<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class CamelCaseValidator extends ConstraintValidator
{
    /**
     * @param CamelCase $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        /* @var $constraint CamelCase */
        if (null === $value || '' === $value) {
            return;
        }

        if (!preg_match('/^[a-z]+([A-Z][a-z]*)*$/', (string)$value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ string }}', $value)
                ->addViolation();
        }
    }
}
