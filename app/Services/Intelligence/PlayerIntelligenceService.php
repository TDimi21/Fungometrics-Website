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
        private readonly StrengthBenchmarkService $strengthBenchmarkService,
    ) {
    }

    public function build(string $teamId, string $playerId, int $days = 60): array
    {
        $assembled = $this->assembler->assembleForPlayer($teamId, $playerId, $days);
        $trendBlocks = $this->trendEngine->analyze($assembled['trend_blocks'] ?? [], $assembled);
        $ageBenchmarks = $this->ageBenchmarkEngine->benchmarkPlayer($assembled);
        $benchmarkProfile = $this->benchmarkProfile($assembled, $ageBenchmarks, $days);
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

    private function benchmarkProfile(array $assembled, array $ageBenchmarks, int $days = 365): array
    {
        $player = $assembled['player_context'] ?? [];
        $team = $assembled['team_context'] ?? [];
        $dob = isset($player['born_date']) && $player['born_date'] ? (string) $player['born_date'] : null;
        $bodyWeight = $assembled['physical_development']['body_weight'] ?? $assembled['assessment_summary']['body_weight'] ?? null;
        $heightInches = $this->heightInches($player['height_ft'] ?? null, $player['height_in'] ?? null);
        $rawContext = [
            'age_group' => $ageBenchmarks['age_group'] ?? BenchmarkDefinitions::AGE_UNKNOWN,
            'dob' => $dob,
            'age' => $player['age'] ?? null,
            'player_id' => $player['id'] ?? null,
            'team_id' => $player['team_id'] ?? $team['id'] ?? null,
            'position' => $player['positions'] ?? [],
            'role' => $player['positions'] ?? [],
            'level' => $player['level'] ?? null,
            'body_weight' => $bodyWeight,
            'bodyweight' => $bodyWeight,
            'height_inches' => $heightInches,
            'height' => $heightInches,
            'throws' => $this->nonEmptyString($player['throw_side'] ?? null),
            'bats' => $this->nonEmptyString($player['hit_side'] ?? null),
            'throw_side' => $this->nonEmptyString($player['throw_side'] ?? null),
            'hit_side' => $this->nonEmptyString($player['hit_side'] ?? null),
            'population_days' => max(1, $days),
        ];
        $context = array_merge($rawContext, $this->benchmarkLibrary->normalizeBenchmarkContext($rawContext));
        $contextEvidence = $this->contextEvidence($context);
        $flatMetrics = $ageBenchmarks['flat_metrics'] ?? [];
        $metrics = [];
        $missingMetrics = [];

        foreach ($flatMetrics as $metricKey => $ageBenchmark) {
            $metricKey = BenchmarkDefinitions::normalizeMetricKey((string) $metricKey);
            $definition = $this->benchmarkLibrary->metric($metricKey);
            $raw = is_array($ageBenchmark) ? ($ageBenchmark['raw_value'] ?? null) : null;

            if ( ! $definition) {
                $missingMetrics[] = [
                    'metric_key' => $metricKey,
                    'reason' => 'No benchmark definition exists for this metric.',
                ];

                continue;
            }

            if ( ! is_numeric($raw)) {
                $missingMetrics[] = [
                    'metric_key' => $metricKey,
                    'display_name' => $definition['display_name'] ?? $metricKey,
                    'category' => $definition['category'] ?? 'unknown',
                    'reason' => 'Metric value is missing from the player intelligence payload.',
                ];

                continue;
            }

            $result = $this->compositeBenchmarkEngine->benchmarkMetric($metricKey, (float) $raw, $dob, $context);

            if ( ! is_numeric($result['score_0_100'] ?? null)) {
                $missingMetrics[] = [
                    'metric_key' => $metricKey,
                    'display_name' => $definition['display_name'] ?? $metricKey,
                    'category' => $definition['category'] ?? 'unknown',
                    'reason' => $result['evidence']['reason'] ?? 'Metric value is missing from the player intelligence payload.',
                ];

                continue;
            }

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
                'source_mix' => $this->metricSourceMix($result),
                'population_percentile' => $this->numericPercentile($result['population_percentile']['percentile'] ?? null),
                'research_percentile' => $this->numericPercentile($result['research_percentile']['percentile_estimate'] ?? $result['percentile_estimate'] ?? null),
                'population_bucket_key' => $result['population_percentile']['selected_bucket_key'] ?? $result['population_percentile']['bucket_key'] ?? null,
                'population_bucket_level' => $result['population_percentile']['selected_bucket_level'] ?? null,
                'population_attempted_buckets' => $result['population_percentile']['attempted_buckets'] ?? [],
                'population_percentile_detail' => $result['population_percentile'] ?? null,
                'research_percentile_detail' => $result['research_percentile'] ?? null,
                'population_policy' => $result['population_policy'] ?? null,
                'evidence' => $this->metricEvidence($result, $contextEvidence),
            ];
        }

        $strengthBenchmark = $this->strengthBenchmarkService->benchmark(
            $assembled['physical_development'] ?? [],
            null,
            $context,
        );
        $strengthKeys = collect($strengthBenchmark['metrics'] ?? [])->pluck('metric_key')->all();
        $metrics = collect($metrics)
            ->reject(fn (array $metric): bool => in_array($metric['metric_key'] ?? null, $strengthKeys, true)
                || (($metric['metric_key'] ?? null) === 'squat' && (in_array('front_squat', $strengthKeys, true) || in_array('back_squat', $strengthKeys, true))))
            ->values()
            ->all();
        foreach ($strengthBenchmark['metrics'] ?? [] as $strengthMetric) {
            if (($strengthMetric['available'] ?? false) !== true) {
                continue;
            }
            $test = $strengthMetric['test'] ?? [];
            $benchmark = $strengthMetric['benchmark'] ?? [];
            $goal = $strengthMetric['goal'] ?? [];
            $rawValue = $test['estimated_1rm'] ?? $test['actual_load'] ?? $test['actual_value'] ?? null;
            $metrics[] = [
                'metric_key' => $strengthMetric['metric_key'],
                'display_name' => $strengthMetric['label'],
                'category' => $strengthMetric['category'],
                'raw_value' => $rawValue,
                'relative_value' => $test['relative_strength'] ?? null,
                'unit' => $strengthMetric['unit'] ?? null,
                'percentile' => $benchmark['percentile'] ?? null,
                'score_0_100' => $benchmark['percentile'] ?? null,
                'label' => $benchmark['classification'] ?? 'Benchmark Needs Data',
                'goal' => $goal['target_value'] ?? null,
                'gap' => $goal['gap'] ?? null,
                'confidence' => $benchmark['confidence'] ?? 'insufficient',
                'source' => $benchmark['source_type'] ?? 'benchmark_needs_data',
                'peer_group' => array_filter([
                    $benchmark['age_group'] ?? null,
                    $benchmark['bodyweight_band'] ?? null,
                    $benchmark['level'] ?? null,
                ]),
                'evidence' => $strengthMetric['evidence'] ?? [],
                'data_quality' => $strengthMetric['data_quality'] ?? [],
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
            'comparison_bucket_key' => $this->benchmarkLibrary->bucketKeyForLevel($context, BenchmarkLibrary::BUCKET_EXACT_PEER),
            'comparison_context' => [
                'age_group' => $context['age_group'],
                'dob' => $dob,
                'position' => $context['position'],
                'role' => $context['role'] ?? null,
                'level' => $context['level'],
                'bodyweight' => $context['body_weight'],
                'bodyweight_band' => $context['bodyweight_band'],
                'height' => $context['height_inches'],
                'height_band' => $context['height_band'],
                'throws' => $context['throws'],
                'bats' => $context['bats'],
                'team_id' => $context['team_id'],
                'player_id' => $context['player_id'],
            ],
            'strength_v1' => $strengthBenchmark,
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
            ->sortBy(fn (array $metric) => (float) $metric['score_0_100'], SORT_REGULAR, 'desc' === $direction)
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
                'source' => $metric['source'] ?? 'research_benchmark',
                'source_mix' => $metric['source_mix'] ?? [],
                'population_percentile' => $metric['population_percentile'] ?? null,
                'research_percentile' => $metric['research_percentile'] ?? null,
                'population_bucket_key' => $metric['population_bucket_key'] ?? null,
                'population_bucket_level' => $metric['population_bucket_level'] ?? null,
                'population_policy' => $metric['population_policy'] ?? null,
            ])
            ->values()
            ->all();

        return $ranked;
    }

    private function benchmarkConfidence(array $metrics): array
    {
        $counts = ['high' => 0, 'medium' => 0, 'low' => 0];

        foreach ($metrics as $metric) {
            $confidence = mb_strtolower((string) ($metric['confidence'] ?? 'low'));
            $confidence = in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'low';
            $counts[$confidence]++;
        }

        $total = max(1, count($metrics));
        $weighted = (($counts['high'] * 3) + ($counts['medium'] * 2) + $counts['low']) / $total;
        $overall = match (true) {
            0 === count($metrics) => 'low',
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
        $populationBucketCounts = [];

        foreach ($metrics as $metric) {
            $source = (string) ($metric['source'] ?? '');
            if (in_array($source, ['composite', 'composite_benchmark'], true)) {
                $counts['composite']++;
            } elseif ('fmtrx_population' === $source) {
                $counts['population']++;
            } else {
                $counts['research']++;
            }

            $bucketCount = $this->numberOrNull($metric['source_mix']['population_bucket_count'] ?? null);
            if (null !== $bucketCount) {
                $populationBucketCounts[] = $bucketCount;
            }
        }

        $total = max(1, count($metrics));
        $averageBucketCount = ! empty($populationBucketCounts) ? round(array_sum($populationBucketCounts) / count($populationBucketCounts), 1) : 0.0;

        return $counts + [
            'research_count' => $counts['research'],
            'population_count' => $counts['population'],
            'composite_count' => $counts['composite'],
            'average_population_bucket_count' => $averageBucketCount,
            'percent_research' => round(($counts['research'] / $total) * 100, 1),
            'percent_population' => round(($counts['population'] / $total) * 100, 1),
            'percent_composite' => round(($counts['composite'] / $total) * 100, 1),
            'research_share' => round($counts['research'] / $total, 2),
            'population_share' => round($counts['population'] / $total, 2),
            'composite_share' => round($counts['composite'] / $total, 2),
        ];
    }

    private function metricSourceMix(array $result): array
    {
        $sourceMix = is_array($result['source_mix'] ?? null) ? $result['source_mix'] : [];
        $population = is_array($result['population_percentile'] ?? null) ? $result['population_percentile'] : [];

        return [
            'research_weight' => $this->numberOrNull($sourceMix['research_weight'] ?? null) ?? 1.0,
            'population_weight' => $this->numberOrNull($sourceMix['population_weight'] ?? null) ?? 0.0,
            'population_bucket_count' => (int) ($sourceMix['population_bucket_count'] ?? $population['bucket_count'] ?? 0),
            'population_confidence' => $sourceMix['population_confidence'] ?? $population['confidence'] ?? 'insufficient',
            'population_usable' => (bool) ($sourceMix['population_usable'] ?? $population['usable'] ?? false),
            'selected_bucket_key' => $sourceMix['selected_bucket_key'] ?? $population['selected_bucket_key'] ?? $population['bucket_key'] ?? null,
            'selected_bucket_level' => $sourceMix['selected_bucket_level'] ?? $population['selected_bucket_level'] ?? null,
            'attempted_bucket_count' => (int) ($sourceMix['attempted_bucket_count'] ?? (is_array($population['attempted_buckets'] ?? null) ? count($population['attempted_buckets']) : 0)),
        ];
    }

    private function metricEvidence(array $result, array $contextEvidence): array
    {
        $evidence = $result['evidence'] ?? [];

        if ( ! is_array($evidence)) {
            $evidence = ['message' => $evidence];
        }

        if ( ! empty($contextEvidence)) {
            $evidence['context_warnings'] = $contextEvidence;
        }

        return $evidence;
    }

    private function numericPercentile(mixed $value): ?float
    {
        $value = $this->numberOrNull($value);

        return null === $value ? null : round($value, 1);
    }

    private function numberOrNull(mixed $value): ?float
    {
        if (null === $value || '' === $value || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function contextEvidence(array $context): array
    {
        $missing = [];

        foreach ([
            'age_group' => BenchmarkDefinitions::AGE_UNKNOWN,
            'position' => 'unknown',
            'level' => 'unknown',
            'bodyweight_band' => 'unknown',
            'height_band' => 'unknown',
            'throws' => 'unknown',
            'bats' => 'unknown',
        ] as $key => $emptyValue) {
            $value = $context[$key] ?? null;
            if ($value === $emptyValue || null === $value || '' === $value || [] === $value) {
                $missing[] = $key;
            }
        }

        if (empty($missing)) {
            return [];
        }

        return ['Population benchmark bucket used fallback context for missing fields: '.implode(', ', $missing).'.'];
    }

    private function bodyWeightBand(mixed $value): string
    {
        $value = $this->numberOrNull($value);
        if (null === $value || $value <= 0) {
            return 'unknown';
        }

        return match (true) {
            $value < 120 => 'under_120',
            $value < 150 => '120_149',
            $value < 180 => '150_179',
            $value < 210 => '180_209',
            default => '210_plus',
        };
    }

    private function heightBand(mixed $value): string
    {
        $value = $this->numberOrNull($value);
        if (null === $value || $value <= 0) {
            return 'unknown';
        }

        return match (true) {
            $value < 63 => 'under_63',
            $value < 66 => '63_65',
            $value < 69 => '66_68',
            $value < 72 => '69_71',
            $value < 75 => '72_74',
            default => '75_plus',
        };
    }

    private function heightInches(mixed $feet, mixed $inches): ?float
    {
        $feet = is_numeric($feet) ? (float) $feet : null;
        $inches = is_numeric($inches) ? (float) $inches : null;

        if (null === $feet && null === $inches) {
            return null;
        }

        return (($feet ?? 0.0) * 12) + ($inches ?? 0.0);
    }

    private function nonEmptyString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return '' !== $value ? $value : null;
    }
}
