<?php

declare(strict_types=1);

namespace App\Service\Statistics;

readonly class DashboardFormatter
{
    public function formatTraffic(array $traffic): array
    {
        $input = 0;
        $output = 0;

        foreach ($traffic as $row) {
            $input += $row['input'] ?? 0;
            $output += $row['output'] ?? 0;
        }

        return [
            // raw values (for charts, progress bars, calculations)
            'input_bytes' => $input,
            'output_bytes' => $output,

            // formatted values (for display only)
            'input_human' => $this->formatBytes($input),
            'output_human' => $this->formatBytes($output),
        ];
    }

    public function sum(array $values): float
    {
        return array_sum($values);
    }

    public function formatSeconds(int|float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return sprintf('%dh %02dm', $hours, $minutes);
    }

    public function formatBytes(int|float $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            return number_format($bytes / (1024 ** 3), 2) . ' GB';
        }

        if ($bytes >= 1024 ** 2) {
            return number_format($bytes / (1024 ** 2), 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
