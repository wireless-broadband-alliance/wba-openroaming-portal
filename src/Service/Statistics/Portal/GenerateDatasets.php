<?php

namespace App\Service\Statistics\Portal;

class GenerateDatasets
{
    /**
     * Generate datasets for charts graphics
     *
     * @param array<string, int|float> $counts
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         data: int[],
     *         backgroundColor: string[],
     *         borderColor: string,
     *         borderRadius: string
     *     }[]
     * }
     */
    public function generateDatasets(array $counts): array
    {
        $labels = array_keys($counts);

        // Convert all values to int to satisfy PHPStan
        $data = array_map(static fn($value) => (int) round($value), array_values($counts));

        $backgroundColors = $this->generateColorsWithOpacity($data);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => "rgb(125, 185, 40)",
                    'borderRadius' => "15",
                ],
            ],
        ];
    }

    /**
     * Generate colors with varying opacities based on data values
     *
     * @param array<int|float> $values
     * @return string[]
     */
    public function generateColorsWithOpacity(array $values): array
    {
        if ($values === []) {
            return []; // or array_fill(0, 0, "rgba(125, 185, 40, 1)");
        }

        // Filter out zero values
        $nonZeroValues = array_filter($values, static fn($value) => $value !== 0);

        if ($nonZeroValues !== []) {
            $maxValue = max($nonZeroValues); // safe: nonZeroValues is non-empty
            $colors = [];

            foreach ($values as $value) {
                $opacity = 0.4 + ($value / $maxValue) * (1 - 0.4);
                $opacity = round($opacity, 2);
                $colors[] = "rgba(125, 185, 40, {$opacity})";
            }

            return $colors;
        }

        // If all values are zero, return default colors
        return array_fill(0, count($values), "rgba(125, 185, 40, 1)");
    }
}
