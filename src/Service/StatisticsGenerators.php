<?php

namespace App\Service;

class StatisticsGenerators
{
    /**
     * Generate datasets for charts graphics
     *
     * @param array<string, int|float> $counts
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         data: int[]|float[],
     *         backgroundColor: string[],
     *         borderColor: string,
     *         borderRadius: string
     *     }[]
     * }
     */
    public function generateDatasets(array $counts): array
    {
        $datasets = [];
        $labels = array_keys($counts);
        $dataValues = array_values($counts);

        $data = [];

        // Calculate the colors with varying opacities
        $colors = $this->generateColorsWithOpacity($dataValues);

        foreach (array_keys($labels) as $index) {
            $data[] = $dataValues[$index];
        }

        $datasets[] = [
            'data' => $data,
            'backgroundColor' => $colors,
            'borderColor' => "rgb(125, 185, 40)",
            'borderRadius' => "15",
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Generate datasets for session average time
     *
     * @param array<int, array{group: string, averageSessionTime: int|float}> $sessionTime
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         label: string,
     *         data: int[]|float[],
     *         backgroundColor: string[],
     *         borderRadius: string,
     *         tooltips: string[]
     *     }[]
     * }
     */
    public function generateDatasetsSessionAverage(array $sessionTime): array
    {
        $labels = array_column($sessionTime, 'group');
        $averageTimes = array_map(static fn($item) => $item['averageSessionTime'], $sessionTime);

        $averageTimesReadable = array_map(static function ($seconds) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return sprintf('%dh %dm', $hours, $minutes);
        }, $averageTimes);

        // Calculate the colors with varying opacities
        $colors = $this->generateColorsWithOpacity($averageTimes);

        $datasets = [
            [
                'label' => 'Average Session Time',
                'data' => $averageTimes,
                'backgroundColor' => $colors,
                'borderRadius' => "15",
                'tooltips' => $averageTimesReadable, // Human-readable values for tooltips
            ]
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Generate datasets for session total time
     *
     * @param array<int, array{group: string, totalSessionTime: int|float}> $sessionTime
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         label: string,
     *         data: int[]|float[],
     *         backgroundColor: string[],
     *         borderRadius: string,
     *         tooltips: string[]
     *     }[]
     * }
     */
    public function generateDatasetsSessionTotal(array $sessionTime): array
    {
        $labels = array_column($sessionTime, 'group');
        $totalTimes = array_map(static fn($item) => $item['totalSessionTime'], $sessionTime);

        $totalTimesReadable = array_map(static function ($seconds) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return sprintf('%dh %dm', $hours, $minutes);
        }, $totalTimes);

        // Calculate the colors with varying opacities
        $colors = $this->generateColorsWithOpacity($totalTimes);

        $datasets = [
            [
                'label' => 'Total Session Time',
                'data' => $totalTimes,
                'backgroundColor' => $colors,
                'borderRadius' => "15",
                'tooltips' => $totalTimesReadable,
            ]
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Generate datasets for Wi-Fi tags
     *
     * @param array<int, array{standard: string, count: int}> $wifiUsage
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         label: string,
     *         data: int[],
     *         backgroundColor: string[],
     *         borderRadius: string
     *     }[],
     *     rawData: int[]
     * }
     */
    public function generateDatasetsWifiTags(array $wifiUsage): array
    {
        $labels = array_column($wifiUsage, 'standard');
        $counts = array_column($wifiUsage, 'count');

        // Calculate the colors with varying opacities
        $colors = $this->generateColorsWithOpacity($counts);

        $datasets = [
            [
                'label' => 'Wi-Fi Usage',
                'data' => $counts,
                'backgroundColor' => $colors,
                'borderRadius' => "15"
            ]
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
            'rawData' => $counts // Include raw numerical data
        ];
    }

    /**
     * Generate datasets for authentication attempts
     *
     * @param array{Accepted: array<string, int>, Rejected: array<string, int>} $authsCounts
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         label: string,
     *         data: int[],
     *         backgroundColor: string[],
     *         borderRadius: string
     *     }[]
     * }
     */
    public function generateDatasetsAuths(array $authsCounts): array
    {
        $labels = array_keys($authsCounts['Accepted']);
        $acceptedCounts = array_values($authsCounts['Accepted']);
        $rejectedCounts = array_values($authsCounts['Rejected']);

        $datasets = [
            [
                'label' => 'Accepted',
                'data' => $acceptedCounts,
                'backgroundColor' => $this->generateColorsWithOpacity($acceptedCounts),
                'borderRadius' => "15",
            ],
            [
                'label' => 'Rejected',
                'data' => $rejectedCounts,
                'backgroundColor' => $this->generateColorsWithOpacity($rejectedCounts),
                'borderRadius' => "15",
            ]
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Generate datasets for realm counting
     *
     * @param array<string, int> $counts Realm names mapped to counts.
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         data: int[],
     *         backgroundColor: string[],
     *         borderRadius: string
     *     }[]
     * }
     */
    public function generateDatasetsRealmsCounting(array $counts): array
    {
        $datasets = [];
        $labels = array_keys($counts);
        $dataValues = array_values($counts);

        $colors = [];

        // Assign a specific color to the first most used realm
        $colors[] = '#7DB928';

        // Generate colors based on the realm names
        foreach ($labels as $realm) {
            // Generate a color based on the realm name
            $color = $this->generateColorFromRealmName($realm);

            // Add the color to the list
            $colors[] = $color;
        }

        $datasets[] = [
            'data' => $dataValues,
            'backgroundColor' => $colors,
            'borderRadius' => "15",
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Generate datasets for realm traffic
     *
     * @param array<string, array{total_input: int, total_output: int}> $trafficData
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         data: int[],
     *         label: string,
     *         backgroundColor: string[],
     *         borderWidth?: int,
     *         borderRadius: string
     *     }[]
     * }
     */
    public function generateDatasetsRealmsTraffic(array $trafficData): array
    {
        $datasets = [];
        $labels = [];
        $dataValuesInput = [];
        $dataValuesOutput = [];
        $colors = [];

        $colors[] = '#7DB928';

        foreach ($trafficData as $realm => $traffic) {
            $labels[] = $realm;
            $dataValuesInput[] = $traffic['total_input'];
            $dataValuesOutput[] = $traffic['total_output'];
            $colors[] = $this->generateColorFromRealmName($realm);
        }

        $datasets[] = [
            'data' => $dataValuesInput,
            'label' => 'Uploaded',
            'backgroundColor' => $colors,
            'borderWidth' => 1,
            'borderRadius' => "15",
        ];

        $datasets[] = [
            'data' => $dataValuesOutput,
            'label' => 'Downloaded',
            'backgroundColor' => $colors,
            'borderWidth' => 1,
            'borderRadius' => "15",
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    public function generateColorFromRealmName(string $realm): string
    {
        // Generate a hash based on the realm name
        $hash = md5($realm);

        // Extract RGB values from the hash
        $red = hexdec(substr($hash, 0, 2));
        $green = hexdec(substr($hash, 2, 2));
        $blue = hexdec(substr($hash, 4, 2));

        // Format the RGB values into a CSS color string and convert to uppercase
        return strtoupper(sprintf('#%02x%02x%02x', $red, $green, $blue));
    }

    /**
     * Generate colors with varying opacities based on data values
     *
     * @param array<int|float> $values
     * @return string[]
     */
    public function generateColorsWithOpacity(array $values): array
    {
        if (array_filter($values, static fn($value) => $value !== 0) !== []) {
            $maxValue = max($values);
            $colors = [];

            foreach ($values as $value) {
                // Calculate the opacity relative to the max value, scaled to the opacity range
                $opacity = 0.4 + ($value / $maxValue) * (1 - 0.4);
                $opacity = round($opacity, 2); // Round to 2 decimal places for better control
                $colors[] = "rgba(125, 185, 40, {$opacity})";
            }

            return $colors;
        }

        return array_fill(0, count($values), "rgba(125, 185, 40, 1)"); // Default color if no non-zero values
    }
}
