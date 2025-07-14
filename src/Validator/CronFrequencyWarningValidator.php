<?php

namespace App\Validator;

use App\Service\SchedulerService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class CronFrequencyWarningValidator extends ConstraintValidator
{
    public function __construct(
        private readonly SchedulerService $schedulerService
    ) {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        $frequency = $this->schedulerService->verifyHoursAndMinutesFrequency($value);

        if ($frequency) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ frequency }}', $frequency)
                ->addViolation();
        }
    }
}
