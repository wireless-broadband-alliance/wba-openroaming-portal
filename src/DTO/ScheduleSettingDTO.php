<?php

namespace App\DTO;

use App\Repository\SettingRepository;
use App\Service\CronExpressionHelperService;
use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ScheduleSettingDTO
{
    public ?string $advanced = null;

    public ?array $day_of_week = ["*"];
    public ?int $day_of_week_frequency = 1;

    public ?array $day_of_month = ["*"];
    public ?int $day_of_month_frequency = 1;

    public ?array $months_of_the_year = ["*"];
    public ?int $months_of_the_year_frequency = 1;

    public ?DateTimeImmutable $time = null;

    public function __construct(
        ?string $setting = null,
        ?SettingRepository $settingRepository = null,
        ?CronExpressionHelperService $cronExpressionHelperService = null,
        ?string $cronExpression = null,
    ) {
        if (!is_null($settingRepository)) {
            if (!is_null($cronExpression)) {
                $this->advanced = $cronExpression;
            } else {
                $this->advanced = $settingRepository->findOneBy(
                    ['name' => $setting]
                )->getValue() ?? "";
            }
            $parts = $cronExpressionHelperService->recognizeCronFrequency(
                $this->advanced
            )['parts'] ?? [];

            // Extract representative time from minutes/hours (ignoring frequency)
            $hourValues = $parts['hour']['values'] ?? [];
            $minuteValues = $parts['minute']['values'] ?? [];

            $hour = is_array($hourValues) && count($hourValues) > 0 ? (int)min($hourValues) : 0;
            $minute = is_array($minuteValues) && count($minuteValues) > 0 ? (int)min($minuteValues) : 0;

            try {
                $this->time = new DateTimeImmutable()->setTime($hour, $minute);
            } catch (Exception) {
                $this->time = new DateTimeImmutable('00:00');
            }

            $this->day_of_week = $this->setDayValues($parts, "day_of_week");
            $this->day_of_week_frequency = $parts["day_of_week"]['frequency'] ?? 1;

            $this->day_of_month = $this->setDayValues($parts, "day_of_month");
            $this->day_of_month_frequency = $parts["day_of_month"]['frequency'] ?? 1;

            $this->months_of_the_year = $this->setDayValues($parts, "month");
            $this->months_of_the_year_frequency = $parts["month"]['frequency'] ?? 1;
        }
    }

    public function toCronExpression(
        bool $useAdvancedMode,
        CronExpressionHelperService $cronExpressionHelperService
    ): string {
        if ($useAdvancedMode) {
            return $this->advanced;
        }

        $hour = $this->time instanceof DateTimeInterface ? $this->time->format('H') : '0';
        $minute = $this->time instanceof DateTimeInterface ? $this->time->format('i') : '0';

        // Build the cron parts with frequency applied, e.g., day_of_week "1-15/2,20"
        $dayOfMonthExpr = $cronExpressionHelperService->selectAllWithFreqConverter(
            $this->day_of_month,
            $this->day_of_month_frequency
        );

        $monthExpr = $cronExpressionHelperService->selectAllWithFreqConverter(
            $this->months_of_the_year,
            $this->months_of_the_year_frequency
        );

        $dayOfWeekExpr = $cronExpressionHelperService->selectAllWithFreqConverter(
            $this->day_of_week,
            $this->day_of_week_frequency
        );

        return "{$minute} {$hour} {$dayOfMonthExpr} {$monthExpr} {$dayOfWeekExpr}";
    }

    private function setDayValues(array $parts, string $field): array
    {
        // Helper function to expand cron expressions like "28-31/2" into [28, 30]
        // Handle day_of_week, day_of_month, and month values and frequencies

        $raw = (string)($parts[$field]['raw'] ?? '*');

        // Set min and max depending on field
        switch ($field) {
            case 'day_of_week':
                $min = 0;
                $max = 6; // Sunday=0 to Saturday=6
                break;
            case 'day_of_month':
                $min = 1;
                $max = 31;
                break;
            case 'month':
                $min = 1;
                $max = 12; // January=1 to December=12
                break;
            default:
                $min = 0;
                $max = 59;
        }

        if ($raw === '*' || $raw === '') {
            // Set "All" when '*' is in the array so the form ChoiceType can show "All" option checked
            return ['*'];
        }

        // Expand expression like "28-31/2" on the array of values

        return $this->expandCronPart($raw, $min, $max);
    }

    private function expandCronPart(string $expr, int $min, int $max): array
    {
        if ($expr === '*' || $expr === '') {
            // Wildcard means all possible values
            return range($min, $max);
        }

        $result = [];

        // Split comma-separated parts
        foreach (explode(',', $expr) as $part) {
            $step = 1;

            // Check for step, e.g. "28-31/2"
            if (str_contains($part, '/')) {
                [$rangePart, $stepPart] = explode('/', $part, 2);
                $step = (int)$stepPart;
            } else {
                $rangePart = $part;
            }

            // Determine range or single value
            if ($rangePart === '*') {
                $rangeStart = $min;
                $rangeEnd = $max;
            } elseif (str_contains($rangePart, '-')) {
                [$rangeStart, $rangeEnd] = explode('-', $rangePart, 2);
                $rangeStart = (int)$rangeStart;
                $rangeEnd = (int)$rangeEnd;
            } else {
                $rangeStart = $rangeEnd = (int)$rangePart;
            }

            // Add values in the range with the step
            for ($i = $rangeStart; $i <= $rangeEnd; $i += $step) {
                if ($i >= $min && $i <= $max) {
                    $result[] = $i;
                }
            }
        }

        // Remove duplicates and sort values
        return array_values(array_unique($result));
    }

    #[Callback]
    public function validateMonthsFrequency(ExecutionContextInterface $context): void
    {
        if (
            $this->months_of_the_year !== ['*'] &&
            is_array($this->months_of_the_year) &&
            $this->months_of_the_year_frequency !== null &&
            $this->months_of_the_year_frequency > count($this->months_of_the_year)
        ) {
            $context->buildViolation('frequencyNotGreaterSelectedMonths')
                ->atPath('months_of_the_year_frequency')
                ->addViolation();
        }
    }

    #[Callback]
    public function validateDayMonthFrequency(ExecutionContextInterface $context): void
    {
        if (
            $this->day_of_month !== ['*'] &&
            is_array($this->day_of_month) &&
            $this->day_of_month_frequency !== null &&
            $this->day_of_month_frequency > count($this->day_of_month)
        ) {
            $context->buildViolation('frequencyNotGreaterSelectedDays')
                ->atPath('day_of_month_frequency')
                ->addViolation();
        }
    }

    #[Callback]
    public function validateDayWeekFrequency(ExecutionContextInterface $context): void
    {
        if (
            $this->day_of_week !== ['*'] &&
            is_array($this->day_of_week) &&
            $this->day_of_week_frequency !== null &&
            $this->day_of_week_frequency > count($this->day_of_week)
        ) {
            $context->buildViolation('frequencyNotGreaterSelectedDays')
                ->atPath('day_of_week_frequency')
                ->addViolation();
        }
    }

    #[Callback]
    public function validateDayOfWeekAllOption(ExecutionContextInterface $context): void
    {
        if (
            is_array($this->day_of_week) &&
            in_array('*', $this->day_of_week, true) &&
            count($this->day_of_week) > 1
        ) {
            $context->buildViolation('allDaysWithSpecificDaysNotAllowed')
                ->atPath('day_of_week')
                ->addViolation();
        }
    }

    #[Callback]
    public function validateDayOfMonthAllOption(ExecutionContextInterface $context): void
    {
        if (
            is_array($this->day_of_month) &&
            in_array('*', $this->day_of_month, true) &&
            count(
                $this->day_of_month
            ) > 1
        ) {
            $context->buildViolation('allDaysWithSpecificDaysNotAllowed')
                ->atPath('day_of_month')
                ->addViolation();
        }
    }

    #[Callback]
    public function validateMonthsOfYearAllOption(ExecutionContextInterface $context): void
    {
        if (
            is_array($this->months_of_the_year) &&
            in_array('*', $this->months_of_the_year, true) &&
            count(
                $this->months_of_the_year
            ) > 1
        ) {
            $context->buildViolation('allMonthsWithSpecificDaysNotAllowed')
                ->atPath('months_of_the_year')
                ->addViolation();
        }
    }

    #[Callback]
    public function validateCronExpression(ExecutionContextInterface $context): void
    {
        if ($this->advanced === null) {
            return;
        }

        try {
            new CronExpression($this->advanced);
        } catch (InvalidArgumentException) {
            $context->buildViolation('cronExpressionNotValid')
                ->atPath('advanced')
                ->addViolation();
        }
    }
}
