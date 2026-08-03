<?php

declare(strict_types=1);

namespace App\Services\Blast;

final class BlastBenchmarkComparator
{
    public function compare(float $value, array $benchmark): array
    {
        $min = (float) $benchmark['min'];
        $max = (float) $benchmark['max'];
        [$status, $label] = match ((string) $benchmark['mode']) {
            'higher_is_better' => $value < $min ? ['below_benchmark', 'Below benchmark'] : ($value > $max ? ['above_benchmark', 'Above benchmark'] : ['in_range', 'In range']),
            'lower_is_better' => $value < $min ? ['faster_than_range', 'Faster than benchmark range'] : ($value > $max ? ['slower_than_range', 'Slower than benchmark range'] : ['in_range', 'In range']),
            'target_range' => $value < $min ? ['below_range', 'Below target range'] : ($value > $max ? ['above_range', 'Above target range'] : ['in_range', 'In range']),
            default => ['unsupported', 'Benchmark unavailable'],
        };
        return ['status' => $status, 'label' => $label, 'min' => $min, 'max' => $max, 'unit' => (string) $benchmark['unit']];
    }
}
