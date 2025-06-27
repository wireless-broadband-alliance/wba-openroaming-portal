<?php

namespace App\Service;

use Cron\CronExpression;

class CronExpressionHelperService
{
    private function parseField(string $field): array
    {
        // Type: every (wildcard or step), exact (single value), or custom
        if ($field === '*') {
            return ['type' => 'every', 'interval' => null, 'values' => []];
        }

        if (str_starts_with($field, '*/')) {
            $interval = (int)substr($field, 2);
            return ['type' => 'every', 'interval' => $interval, 'values' => []];
        }

        // Check if it's a single exact value
        if (is_numeric($field)) {
            return ['type' => 'exact', 'interval' => null, 'values' => [(int)$field]];
        }

        // If it's anything else (e.g., "1,2,3" or "1-5"), treat as custom
        return ['type' => 'custom', 'interval' => null, 'values' => []];
    }

    public function recognizeCronFrequency(string $cronExpression): array
    {
        try {
            $cron = new CronExpression($cronExpression);
        } catch (\InvalidArgumentException $e) {
            return [
                'error' => 'Invalid cron expression',
                'raw' => $cronExpression,
            ];
        }

        // Get raw expressions
        $minute = $cron->getExpression(CronExpression::MINUTE);
        $hour = $cron->getExpression(CronExpression::HOUR);
        $day = $cron->getExpression(CronExpression::DAY);
        $month = $cron->getExpression(CronExpression::MONTH);
        $weekday = $cron->getExpression(CronExpression::WEEKDAY);

        // Parse fields using simple logic (no regex)
        $minuteParsed = $this->parseField($minute);
        $hourParsed = $this->parseField($hour);
        $dayParsed = $this->parseField($day);
        $monthParsed = $this->parseField($month);
        $weekdayParsed = $this->parseField($weekday);

        return [
            'raw' => $cronExpression,
            'parts' => [
                'minute' => array_merge(['raw' => $minute], $minuteParsed),
                'hour' => array_merge(['raw' => $hour], $hourParsed),
                'day_of_month' => array_merge(['raw' => $day], $dayParsed),
                'month' => array_merge(['raw' => $month], $monthParsed),
                'day_of_week' => array_merge(['raw' => $weekday], $weekdayParsed),
            ],
            'time' => sprintf('%02d:%02d', (int)$hour, (int)$minute),
            'frequency' => $this->guessFrequencyFromParts([
                'day_of_week' => $weekdayParsed,
                'day_of_month' => $dayParsed,
            ]),
        ];
    }

    public function guessFrequencyFromParts(array $parts): string
    {
        if ($parts['day_of_week']['type'] !== 'every' && $parts['day_of_month']['type'] === 'every') {
            return 'weekly';
        }

        if ($parts['day_of_month']['type'] !== 'every' && $parts['day_of_week']['type'] === 'every') {
            return 'monthly';
        }

        if (
            $parts['day_of_week']['type'] === 'every' &&
            $parts['day_of_month']['type'] === 'every'
        ) {
            return 'daily';
        }

        return 'custom';
    }
}
