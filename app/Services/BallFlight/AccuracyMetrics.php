<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

final class AccuracyMetrics
{
    /** @param list<float|int|string> $values @return array<string,mixed> */
    public function summarize(array $values): array
    {
        $errors = array_values(array_map('floatval', $values));
        if ($errors === []) return ['count' => 0];
        sort($errors);
        $absolute = array_map('abs', $errors);
        sort($absolute);
        $count = count($errors);

        return [
            'count' => $count,
            'mean_error_ft' => round(array_sum($errors) / $count, 2),
            'mean_absolute_error_ft' => round(array_sum($absolute) / $count, 2),
            'median_error_ft' => round($this->median($errors), 2),
            'median_absolute_error_ft' => round($this->median($absolute), 2),
            'rmse_ft' => round(sqrt(array_sum(array_map(fn ($e) => $e ** 2, $errors)) / $count), 2),
            'p90_absolute_error_ft' => round($this->percentile($absolute, 0.90), 2),
            'within_10_ft_percent' => round(100 * count(array_filter($absolute, fn ($e) => $e <= 10)) / $count, 1),
            'within_15_ft_percent' => round(100 * count(array_filter($absolute, fn ($e) => $e <= 15)) / $count, 1),
            'within_25_ft_percent' => round(100 * count(array_filter($absolute, fn ($e) => $e <= 25)) / $count, 1),
            'largest_overestimate_ft' => round(max($errors), 2),
            'largest_underestimate_ft' => round(min($errors), 2),
        ];
    }

    private function median(array $values): float
    {
        $n = count($values);
        $middle = intdiv($n, 2);
        return $n % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    private function percentile(array $values, float $percentile): float
    {
        return $values[(int) floor((count($values) - 1) * $percentile)];
    }
}
