<?php

namespace App\Service;

use Cron\CronExpression;

class CronExpressionHelperService
{
    private function parseCronField(string $field): array
    {
        // Handles simple lists (e.g., "1,3,5"), returns array of integers
        $values = [];
        $parts = explode(',', $field);

        foreach ($parts as $part) {
            if ($part === '*') {
                continue;
            }
            if (str_contains($part, '-') || str_contains($part, '/')) {
                // Let caller handle ranges or steps
                $values[] = $part;
                continue;
            }
            $values[] = (int)$part;
        }

        return $values;
    }

    private function extractStepInterval(string $field): ?int
    {
        if (!str_contains($field, '/')) {
            return null;
        }

        $parts = explode('/', $field);
        if (count($parts) === 2 && is_numeric($parts[1])) {
            return (int)$parts[1];
        }

        return null;
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

        $minutes = $cron->getExpression(CronExpression::MINUTE);
        $hours = $cron->getExpression(CronExpression::HOUR);
        $daysOfMonth = $cron->getExpression(CronExpression::DAY);
        $months = $cron->getExpression(CronExpression::MONTH);
        $daysOfWeek = $cron->getExpression(CronExpression::WEEKDAY);

        $intervals = [
            'minute' => $this->extractStepInterval($minutes),
            'hour' => $this->extractStepInterval($hours),
            'day_of_month' => $this->extractStepInterval($daysOfMonth),
            'month' => $this->extractStepInterval($months),
            'day_of_week' => $this->extractStepInterval($daysOfWeek),
        ];

        return [
            'frequency' => 'custom', // always UI-handled
            'time' => sprintf('%02d:%02d', (int)$hours, (int)$minutes),
            'raw' => $cronExpression,
            'parts' => [
                'minute' => [
                    'raw' => $minutes,
                    'values' => $this->parseCronField($minutes),
                    'interval' => $intervals['minute'],
                ],
                'hour' => [
                    'raw' => $hours,
                    'values' => $this->parseCronField($hours),
                    'interval' => $intervals['hour'],
                ],
                'day_of_month' => [
                    'raw' => $daysOfMonth,
                    'values' => $this->parseCronField($daysOfMonth),
                    'interval' => $intervals['day_of_month'],
                ],
                'month' => [
                    'raw' => $months,
                    'values' => $this->parseCronField($months),
                    'interval' => $intervals['month'],
                ],
                'day_of_week' => [
                    'raw' => $daysOfWeek,
                    'values' => $this->parseCronField($daysOfWeek),
                    'interval' => $intervals['day_of_week'],
                ],
            ],
        ];
    }

    public function guessFrequencyFromParts(array $parts): ?string
    {
        if (!empty($parts['day_of_week']['values']) && empty($parts['day_of_month']['values'])) {
            return 'weekly';
        }

        if (!empty($parts['day_of_month']['values']) && empty($parts['day_of_week']['values'])) {
            return 'monthly';
        }

        if (empty($parts['day_of_week']['values']) && empty($parts['day_of_month']['values'])) {
            return 'daily';
        }

        return null; // ambiguous or unsupported pattern
    }
}
