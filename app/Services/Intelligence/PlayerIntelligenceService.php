<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class PlayerIntelligenceService
{
    public function __construct(
        private readonly IntelligenceDataAssembler $assembler,
        private readonly IntelligenceSignalEngine $signalEngine,
        private readonly IntelligenceRecommendationEngine $recommendationEngine,
        private readonly IntelligenceSnapshotFormatter $formatter,
        private readonly TrendEngine $trendEngine,
        private readonly ProjectionEngine $projectionEngine,
        private readonly LimiterEngine $limiterEngine,
        private readonly PlayerDNAEngine $dnaEngine,
        private readonly AgeBenchmarkEngine $ageBenchmarkEngine,
        private readonly CompositeBenchmarkEngine $compositeBenchmarkEngine,
        private readonly BenchmarkLibrary $benchmarkLibrary,
    ) {
    }

    public function build(string $teamId, string $playerId, int $days = 60): array
    {
        $assembled = $this->assembler->assembleForPlayer($teamId, $playerId, $days);
        $trendBlocks = $this->trendEngine->analyze($assembled['trend_blocks'] ?? [], $assembled);
        $ageBenchmarks = $this->ageBenchmarkEngine->benchmarkPlayer($assembled);
        $benchmarkProfile = $this->benchmarkProfile($assembled, $ageBenchmarks);
        $projections = $this->projectionEngine->project($trendBlocks, $assembled, $ageBenchmarks);
        $limiters = $this->limiterEngine->detect($assembled, $trendBlocks, $ageBenchmarks);
        $dna = $this->dnaEngine->build($assembled, $trendBlocks, $limiters, $ageBenchmarks);
        $signals = $this->signalEngine->buildSignals($assembled);
        $recommendations = $this->recommendationEngine->buildRecommendations($assembled, $signals, $trendBlocks, $limiters, $dna);

        return $this->formatter->formatPlayerSnapshot(
            $teamId,
            $playerId,
            $assembled,
            $signals,
            $recommendations,
            $trendBlocks,
            $dna,
            $projections,
            $limiters,
            $ageBenchmarks,
            $benchmarkProfile,
        );
    }

    private function benchmarkProfile(array $assembled, array $ageBenchmarks): array
    {
        $player = $assembled['player_context'] ?? [];
        $dob = isset($player['born_date']) && $player['born_date'] ? (string) $player['born_date'] : null;
        $context = [
            'age_group' => $ageBenchmarks['age_group'] ?? BenchmarkDefinitions::AGE_UNKNOWN,
            'age' => $player['age'] ?? null,
            'player_id' => $player['id'] ?? null,
            'position' => $player['positions'] ?? [],
            'level' => $player['level'] ?? null,
            'body_weight' => $assembled['physical_development']['body_weight'] ?? $assembled['assessment_summary']['body_weight'] ?? null,
            'height_inches' => $this->heightInches($player['height_ft'] ?? null, $player['height_in'] ?? null),
            'throws' => $this->nonEmptyString($player['throw_side'] ?? null),
            'bats' => $this->nonEmptyString($player['hit_side'] ?? null),
        ];
        $flatMetrics = $ageBenchmarks['flat_metrics'] ?? [];
        $metrics = [];
        $missingMetrics = [];

        foreach ($flatMetrics as $metricKey => $ageBenchmark) {
            $metricKey = BenchmarkDefinitions::normalizeMetricKey((string) $metricKey);
            $definition = $this->benchmarkLibrary->metric($metricKey);
            $raw = is_array($ageBenchmark) ? ($ageBenchmark['raw_value'] ?? null) : null;

            if (! $definition) {
                $missingMetrics[] = [
                    'metric_key' => $metricKey,
                    'reason' => 'No benchmark definition exists for this metric.',
                ];

                continue;
            }

            if (! is_numeric($raw)) {
                $missingMetrics[] = [
                    'metric_key' => $metricKey,
                    'display_name' => $definition['display_name'] ?? $metricKey,
                    'category' => $definition['category'] ?? 'unknown',
                    'reason' => 'Metric value is missing from the player intelligence payload.',
                ];

                continue;
            }

            $result = $this->compositeBenchmarkEngine->benchmarkMetric($metricKey, (float) $raw, $dob, $context);

            $metrics[] = [
                'metric_key' => $metricKey,
                'display_name' => $definition['display_name'] ?? $metricKey,
                'category' => $definition['category'] ?? 'unknown',
                'raw_value' => $result['raw_value'] ?? (float) $raw,
                'unit' => $result['unit'] ?? $definition['unit'] ?? null,
                'percentile' => $result['percentile_estimate'] ?? null,
                'score_0_100' => $result['score_0_100'] ?? null,
                'label' => $result['label'] ?? $result['benchmark_label'] ?? 'unknown',
                'gap_to_good' => $result['gap_to_good'] ?? null,
                'gap_to_elite' => $result['gap_to_elite'] ?? null,
                'confidence' => $result['confidence'] ?? 'low',
                'source' => $result['source'] ?? 'research_benchmark',
                'evidence' => $result['evidence'] ?? [],
            ];
        }

        return [
            'metrics' => $metrics,
            'category_scores' => $this->categoryScores($metrics),
            'strongest_metrics' => $this->rankMetrics($metrics, 'desc'),
            'weakest_metrics' => $this->rankMetrics($metrics, 'asc'),
            'missing_metrics' => $missingMetrics,
            'benchmark_confidence' => $this->benchmarkConfidence($metrics),
            'source_mix' => $this->sourceMix($metrics),
            'comparison_bucket_key' => $this->benchmarkLibrary->bucketKey($context),
        ];
    }

    private function categoryScores(array $metrics): array
    {
        return collect($metrics)
            ->groupBy('category')
            ->map(function ($categoryMetrics) {
                $values = collect($categoryMetrics)
                    ->pluck('score_0_100')
                    ->filter(fn ($value) => is_numeric($value));

                return [
                    'score_0_100' => $values->isNotEmpty() ? round((float) $values->avg(), 1) : null,
                    'metric_count' => $values->count(),
                ];
            })
            ->all();
    }

    private function rankMetrics(array $metrics, string $direction): array
    {
        $ranked = collect($metrics)
            ->filter(fn (array $metric) => is_numeric($metric['score_0_100'] ?? null))
            ->sortBy(fn (array $metric) => (float) $metric['score_0_100'], SORT_REGULAR, $direction === 'desc')
            ->take(5)
            ->map(fn (array $metric) => [
                'metric_key' => $metric['metric_key'],
                'display_name' => $metric['display_name'],
                'category' => $metric['category'],
                'raw_value' => $metric['raw_value'],
                'unit' => $metric['unit'],
                'score_0_100' => $metric['score_0_100'],
                'percentile' => $metric['percentile'],
                'label' => $metric['label'],
                'confidence' => $metric['confidence'],
            ])
            ->values()
            ->all();

        return $ranked;
    }

    private function benchmarkConfidence(array $metrics): array
    {
        $counts = ['high' => 0, 'medium' => 0, 'low' => 0];

        foreach ($metrics as $metric) {
            $confidence = strtolower((string) ($metric['confidence'] ?? 'low'));
            $confidence = in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'low';
            $counts[$confidence]++;
        }

        $total = max(1, count($metrics));
        $weighted = (($counts['high'] * 3) + ($counts['medium'] * 2) + $counts['low']) / $total;
        $overall = match (true) {
            count($metrics) === 0 => 'low',
            $weighted >= 2.5 => 'high',
            $weighted >= 1.6 => 'medium',
            default => 'low',
        };

        return [
            'overall' => $overall,
            'metric_count' => count($metrics),
            'counts' => $counts,
        ];
    }

    private function sourceMix(array $metrics): array
    {
        $counts = [
            'research' => 0,
            'population' => 0,
            'composite' => 0,
        ];

        foreach ($metrics as $metric) {
            $source = (string) ($metric['source'] ?? '');
            if ($source === 'composite_benchmark') {
                $counts['composite']++;
            } elseif ($source === 'fmtrx_population') {
                $counts['population']++;
            } else {
                $counts['research']++;
            }
        }

        $total = max(1, count($metrics));

        return $counts + [
            'research_share' => round($counts['research'] / $total, 2),
            'population_share' => round($counts['population'] / $total, 2),
            'composite_share' => round($counts['composite'] / $total, 2),
        ];
    }

    private function heightInches(mixed $feet, mixed $inches): ?float
    {
        $feet = is_numeric($feet) ? (float) $feet : null;
        $inches = is_numeric($inches) ? (float) $inches : null;

        if ($feet === null && $inches === null) {
            return null;
        }

        return (($feet ?? 0.0) * 12) + ($inches ?? 0.0);
    }

    private function nonEmptyString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
