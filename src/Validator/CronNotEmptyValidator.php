<?php

namespace App\Validator;

use App\DTO\ScheduleSettingDTO;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class CronNotEmptyValidator extends ConstraintValidator
{
    /**
     * @param ScheduleSettingDTO|null $value
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CronNotEmpty) {
            throw new UnexpectedTypeException($constraint, CronNotEmpty::class);
        }

        // custom constraints should ignore null and empty values to allow
        if (!$value instanceof ScheduleSettingDTO) {
            return;
        }

        if (empty($value->advanced)) {
            $this->context->buildViolation($constraint->message)
                ->atPath("advanced")
                ->addViolation();
        }
    }
}
