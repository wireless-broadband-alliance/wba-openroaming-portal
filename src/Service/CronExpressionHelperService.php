<?php

namespace App\Service;

use Cron\CronExpression;

class CronExpressionHelperService
{
    private function parseField(string $field): array
    {
        $field = trim($field);

        // 1. Wildcard '*'
        if ($field === '*') {
            return ['type' => 'every', 'frequency' => 1, 'values' => []];
        }

        // 2. Step with wildcard: '*/N'
        if (str_starts_with($field, '*/')) {
            $freqStr = substr($field, 2);
            $freq = (int)$freqStr;
            if ($freq > 0) {
                return ['type' => 'every', 'frequency' => $freq, 'values' => []];
            }
            // Invalid frequency fallback
            return ['type' => 'every', 'frequency' => 1, 'values' => []];
        }

        // 3. Step with range: 'start-end/N'
        $slashPos = strpos($field, '/');
        if ($slashPos !== false) {
            $rangePart = substr($field, 0, $slashPos);
            $freqStr = substr($field, $slashPos + 1);
            $freq = (int)$freqStr;

            // Validate frequency
            if ($freq < 1) {
                $freq = 1;
            }

            // Parse range 'start-end'
            $dashPos = strpos($rangePart, '-');
            if ($dashPos !== false) {
                $startStr = substr($rangePart, 0, $dashPos);
                $endStr = substr($rangePart, $dashPos + 1);

                if (is_numeric($startStr) && is_numeric($endStr)) {
                    $start = (int)$startStr;
                    $end = (int)$endStr;
                    if ($start <= $end) {
                        $values = range($start, $end);
                        return ['type' => 'every', 'frequency' => $freq, 'values' => $values];
                    }
                }
            }

            // If no valid range found, fallback:
            return ['type' => 'every', 'frequency' => $freq, 'values' => []];
        }

        // 4. Comma separated list: 'val1,val2,...'
        if (str_contains($field, ',')) {
            $parts = explode(',', $field);
            $values = [];
            foreach ($parts as $part) {
                $part = trim($part);
                if (is_numeric($part)) {
                    $values[] = (int)$part;
                }
            }
            if (count($values) > 0) {
                return ['type' => 'custom', 'frequency' => 1, 'values' => $values];
            }
            // fallback empty
            return ['type' => 'custom', 'frequency' => 1, 'values' => []];
        }

        // 5. Simple range: 'start-end'
        $dashPos = strpos($field, '-');
        if ($dashPos !== false) {
            $startStr = substr($field, 0, $dashPos);
            $endStr = substr($field, $dashPos + 1);
            if (is_numeric($startStr) && is_numeric($endStr)) {
                $start = (int)$startStr;
                $end = (int)$endStr;
                if ($start <= $end) {
                    $values = range($start, $end);
                    return ['type' => 'custom', 'frequency' => 1, 'values' => $values];
                }
            }
            // fallback empty
            return ['type' => 'custom', 'frequency' => 1, 'values' => []];
        }

        // 6. Single exact value
        if (is_numeric($field)) {
            return ['type' => 'exact', 'frequency' => 1, 'values' => [(int)$field]];
        }

        // 7. Fallback to custom empty
        return ['type' => 'custom', 'frequency' => 1, 'values' => []];
    }

    public function selectAllWithFreqConverter(array $values, int $freq): string
    {
        if ($freq === 1) {
            return '*';
        }
        if (in_array('*', $values, true)) {
            return "*/$freq";
        }
            return $this->buildCronPartWithFrequency($values, $freq);
    }

    /**
     * Build a CRON part (day/month/month_of_year) with frequency, supporting ranges.
     *
     * Example:
     *   values = [1,2,3,5,6,7,10]
     *   freq = 2
     *   => "1-3/2,5-7/2,10/2"
     */
    private function buildCronPartWithFrequency(array $values, int $frequency): string
    {
        if ($values === []) {
            return '*';
        }

        sort($values);

        if ($frequency <= 1) {
            return implode(',', $values);
        }

        // Check if values form a continuous range
        $min = $values[0];
        $max = $values[count($values) - 1];

        // Check if all values between min and max are included
        $expectedRange = range($min, $max);
        if ($values === $expectedRange) {
            // Continuous range: safe to apply step frequency
            return "{$min}-{$max}/{$frequency}";
        }

        // Non-contiguous values: steps with multiple ranges not supported,
        // fallback to listing values without frequency steps
        return implode(',', $values);
    }

    /**
     * Recognize and parse the cron expression into parts with frequency and values.
     */
    public function recognizeCronFrequency(string $cronExpression): array
    {
        try {
            $cron = new CronExpression($cronExpression);
        } catch (\InvalidArgumentException) {
            return [
                'error' => 'Invalid cron expression',
                'raw' => $cronExpression,
            ];
        }

        $minute = $cron->getExpression(CronExpression::MINUTE);
        $hour = $cron->getExpression(CronExpression::HOUR);
        $day = $cron->getExpression(CronExpression::DAY);
        $month = $cron->getExpression(CronExpression::MONTH);
        $weekday = $cron->getExpression(CronExpression::WEEKDAY);

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
        ];
    }
}
