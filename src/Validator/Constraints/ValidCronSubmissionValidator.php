<?php

namespace App\Validator\Constraints;

use App\Enum\OperationMode;
use Cron\CronExpression;
use Exception;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use UnexpectedValueException;

class ValidCronSubmissionValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidCronSettings) {
            throw new UnexpectedTypeException($constraint, ValidCronSettings::class);
        }

        if (!is_array($value)) {
            throw new UnexpectedValueException($value, 'array');
        }

        // Get cronSettings from the constraint options instead of constructor injection
        $cronSettings = $constraint->cronSettings;

        foreach ($cronSettings as $settingName) {
            if ($value['use_advanced_mode'] === OperationMode::ON->value) {
                $this->validateAdvanced($value, $settingName);
            } else {
                $this->validateSimple($value, $settingName);
            }
        }
    }

    private function validateAdvanced(array $value, string $settingName): void
    {
        $cronField = "{$settingName}_advanced";
        $expr = $value[$cronField] ?? null;

        if (empty($expr)) {
            $this->context->buildViolation('Please provide a CRON expression for advanced mode.')
                ->atPath($cronField)
                ->addViolation();
            return;
        }

        try {
            new CronExpression($expr);
        } catch (Exception) {
            $this->context->buildViolation('Invalid CRON expression.')
                ->atPath($cronField)
                ->addViolation();
        }
    }

    private function validateSimple(array $value, string $settingName): void
    {
        $time = $value["{$settingName}_time"] ?? null;

        $daysOfWeek = $value["{$settingName}_day_of_week"] ?? [];
        $dayOfWeekFreq = (int)($value["{$settingName}_day_of_week_frequency"] ?? 1);

        $daysOfMonth = $value["{$settingName}_day_of_month"] ?? [];
        $dayOfMonthFreq = (int)($value["{$settingName}_day_of_month_frequency"] ?? 1);

        $monthsOfYear = $value["{$settingName}_months_of_the_year"] ?? [];
        $monthsFreq = (int)($value["{$settingName}_months_of_the_year_frequency"] ?? 1);

        if ($dayOfMonthFreq > 1 && $dayOfWeekFreq > 1) {
            $this->addError(
                "{$settingName}_day_of_month_frequency",
                'Cannot set frequency on both Day of Month and Day of Week at the same time due to cron semantics.'
            );
        }

        if (!$time) {
            $this->addError("{$settingName}_time", 'Please choose a time.');

            return;
        }

        $daysOfWeek = $this->expandAllSelection(
            $daysOfWeek,
            0,
            6
        );
        $daysOfMonth = $this->expandAllSelection(
            $daysOfMonth,
            1,
            31
        );
        $monthsOfYear = $this->expandAllSelection(
            $monthsOfYear,
            1,
            12
        );

        if (empty($daysOfWeek)) {
            $this->addError(
                "{$settingName}_day_of_week",
                'Please choose at least one day of the week.'
            );
        }

        if (empty($daysOfMonth)) {
            $this->addError(
                "{$settingName}_day_of_month",
                'Please choose at least one day of the month.'
            );
        }

        if (empty($monthsOfYear)) {
            $this->addError(
                "{$settingName}_months_of_the_year",
                'Please choose at least one month.'
            );
        }

        $this->checkAllWithExtras($settingName, $daysOfWeek, 'day_of_week');
        $this->checkAllWithExtras($settingName, $daysOfMonth, 'day_of_month');
        $this->checkAllWithExtras($settingName, $monthsOfYear, 'months_of_the_year');

        $fieldsToCheck = [
            'day_of_week' => [$daysOfWeek, $dayOfWeekFreq],
            'day_of_month' => [$daysOfMonth, $dayOfMonthFreq],
            'months_of_the_year' => [$monthsOfYear, $monthsFreq],
        ];

        foreach ($fieldsToCheck as $fieldSuffix => [$selectedValues, $frequency]) {
            if ($frequency <= 1) {
                continue;
            }

            $count = count($selectedValues);
            if ($frequency >= $count && !in_array('*', $selectedValues, true)) {
                $this->addError(
                    "{$settingName}_{$fieldSuffix}_frequency",
                    sprintf(
                        'Frequency (%d) must be less than the number of selected values (%d).',
                        $frequency,
                        $count
                    )
                );
            }
        }

        $minute = (int)$time->format('i');
        $hour = (int)$time->format('H');

        $dayOfMonthPart = $this->buildPart(
            $daysOfMonth,
            $dayOfMonthFreq,
            $monthsFreq,
            $settingName,
            'day_of_month'
        );
        $monthPart = $this->buildPart(
            $monthsOfYear,
            $monthsFreq,
            $monthsFreq,
            $settingName,
            'months_of_the_year'
        );
        $dayOfWeekPart = $this->buildPart(
            $daysOfWeek,
            $dayOfWeekFreq,
            $dayOfWeekFreq,
            $settingName,
            'day_of_week'
        );

        $cronString = sprintf('%s %d %s %s %s', $minute, $hour, $dayOfMonthPart, $monthPart, $dayOfWeekPart);

        try {
            new CronExpression($cronString);
        } catch (Exception) {
            $this->addError("{$settingName}_time", 'Failed to generate a valid CRON expression from input.');
        }
    }

    private function addError(string $path, string $message): void
    {
        $this->context->buildViolation($message)
            ->atPath("[$path]")
            ->addViolation();
    }

    private function checkAllWithExtras(string $settingName, array $values, string $suffix): void
    {
        if (count($values) > 1 && in_array('*', $values, true)) {
            $this->addError(
                "{$settingName}_{$suffix}",
                "Please don't select all values with additional {$suffix}."
            );
        }
    }

    private function buildPart(array $values, int $freq, int $defaultFreq, string $settingName, string $suffix): string
    {
        if (in_array('*', $values, true)) {
            return "*/$defaultFreq";
        }

        return $this->buildCronPartWithFrequency($values, $freq, $settingName, $suffix);
    }

    private function expandAllSelection(array $values, int $min, int $max): array
    {
        if (in_array('all', $values, true)) {
            return range($min, $max);
        }

        return $values;
    }

    private function buildCronPartWithFrequency(
        array $values,
        int $frequency,
        string $form,
        string $settingName
    ): string {
        if ($values === []) {
            return '*';
        }

        sort($values);

        if ($frequency <= 1) {
            return implode(',', $values);
        }

        $min = $values[0];
        $max = $values[count($values) - 1];
        $expectedRange = range($min, $max);

        if ($values === $expectedRange) {
            return "{$min}-{$max}/{$frequency}";
        }

        $this->context->buildViolation(
            'Please don\'t select Non-contiguous values with frequency greater than 1.'
        )
            ->atPath("{$settingName}_time")
            ->addViolation();

        return implode(',', $values);
    }
}
