<?php

namespace App\Validator;

use App\DTO\ScheduleSettingDTO;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class CronNotEmptyValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof CronNotEmpty) {
            throw new UnexpectedTypeException($constraint, CronNotEmpty::class);
        }

        /** @var ?ScheduleSettingDTO $value */

        // custom constraints should ignore null and empty values to allow
        if (is_null($value)) {
            return;
        }

        if (empty($value->advanced)) {
            $this->context->buildViolation($constraint->message)->atPath("advanced")->addViolation();
        }
    }
}
