<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\PlayerTeam;

class TeamBenchmarkProfileService
{
    public function __construct(
        private readonly PlayerIntelligenceService $playerIntelligenceService,
        private readonly BenchmarkLibrary $benchmarkLibrary,
    ) {}

    public function build(string $teamId, int $days = 365): array
    {
        $days = max(7, min(365, $days));
        $playerIds = PlayerTeam::query()
            ->where('team_id', $teamId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $playerProfiles = [];
        $allMetrics = [];
        $missingCounts = [];
        $lowConfidence = [];

        foreach ($playerIds as $playerId) {
            $snapshot = $this->playerIntelligenceService->build($teamId, $playerId, $days);
            $profile = is_array($snapshot['benchmark_profile'] ?? null) ? $snapshot['benchmark_profile'] : [];
            $playerMetrics = is_array($profile['metrics'] ?? null) ? $profile['metrics'] : [];
            $missingMetrics = is_array($profile['missing_metrics'] ?? null) ? $profile['missing_metrics'] : [];
            $playerName = $this->playerName($snapshot);

            foreach ($missingMetrics as $missingMetric) {
                $metricKey = (string) ($missingMetric['metric_key'] ?? 'unknown');
                $missingCounts[$metricKey] ??= [
                    'metric_key' => $metricKey,
                    'display_name' => $missingMetric['display_name'] ?? $metricKey,
                    'category' => $missingMetric['category'] ?? 'unknown',
                    'missing_count' => 0,
                    'player_count' => $playerIds->count(),
                    'players' => [],
                    'reason' => $missingMetric['reason'] ?? 'Metric value is missing.',
                ];
                $missingCounts[$metricKey]['missing_count']++;
                $missingCounts[$metricKey]['players'][] = [
                    'player_id' => $playerId,
                    'name' => $playerName,
                ];
            }

            if (empty($playerMetrics)) {
                $playerProfiles[] = [
                    'player_id' => $playerId,
                    'name' => $playerName,
                    'average_score' => null,
                    'metric_count' => 0,
                    'weakest_metrics' => [],
                    'weakest_category' => null,
                    'benchmark_profile' => $profile,
                ];

                continue;
            }

            foreach ($playerMetrics as $metric) {
                if (! is_numeric($metric['score_0_100'] ?? null)) {
                    continue;
                }

                $metric['player_id'] = $playerId;
                $metric['player_name'] = $playerName;
                $allMetrics[] = $metric;

                if (($metric['confidence'] ?? 'low') === 'low') {
                    $lowConfidence[] = $metric;
                }
            }

            $playerProfiles[] = [
                'player_id' => $playerId,
                'name' => $playerName,
                'average_score' => $this->averageScore($playerMetrics),
                'metric_count' => count($playerMetrics),
                'weakest_metrics' => $this->rankMetrics($playerMetrics, 'asc', 3),
                'weakest_category' => $this->weakestCategory($playerMetrics),
                'benchmark_profile' => $profile,
            ];
        }

        $categoryScores = $this->categoryScores($allMetrics);
        $metricScores = $this->metricScores($allMetrics);

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'player_count' => $playerIds->count(),
            'metric_count' => count($allMetrics),
            'category_scores' => $categoryScores,
            'strongest_categories' => $this->rankCategories($categoryScores, 'desc'),
            'weakest_categories' => $this->rankCategories($categoryScores, 'asc'),
            'strongest_metrics' => $this->rankTeamMetrics($metricScores, 'desc'),
            'weakest_metrics' => $this->rankTeamMetrics($metricScores, 'asc'),
            'players_above_team_average' => $this->playersAroundAverage($playerProfiles, $allMetrics, 'above'),
            'players_below_team_average' => $this->playersAroundAverage($playerProfiles, $allMetrics, 'below'),
            'players_needing_attention' => $this->playersNeedingAttention($playerProfiles),
            'missing_metrics' => $this->missingMetrics($missingCounts),
            'team_gaps' => $this->teamGaps($categoryScores, $metricScores, $missingCounts, $playerIds->count()),
            'benchmark_confidence' => $this->benchmarkConfidence($allMetrics),
            'source_mix' => $this->sourceMix($allMetrics),
            'evidence' => [
                'days' => $days,
                'player_ids' => $playerIds->all(),
                'players_with_benchmark_metrics' => collect($playerProfiles)->filter(fn (array $profile) => ($profile['metric_count'] ?? 0) > 0)->count(),
                'players_without_benchmark_metrics' => collect($playerProfiles)->filter(fn (array $profile) => ($profile['metric_count'] ?? 0) === 0)->count(),
                'low_confidence_metric_count' => count($lowConfidence),
                'population_minimums' => [
                    'low' => PopulationPercentileEngine::MIN_LOW_CONFIDENCE,
                    'medium' => PopulationPercentileEngine::MIN_MEDIUM_CONFIDENCE,
                    'high' => PopulationPercentileEngine::MIN_HIGH_CONFIDENCE,
                ],
            ],
        ];
    }

    private function categoryScores(array $metrics): array
    {
        return collect($metrics)
            ->groupBy('category')
            ->map(function ($categoryMetrics, string $category) {
                $scores = collect($categoryMetrics)
                    ->pluck('score_0_100')
                    ->filter(fn ($value) => is_numeric($value));
                $score = $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : null;

                return [
                    'category' => $category,
                    'score_0_100' => $score,
                    'percentile_estimate' => $score,
                    'player_count' => collect($categoryMetrics)->pluck('player_id')->unique()->count(),
                    'metric_count' => $scores->count(),
                    'label' => $this->label($score),
                    'confidence' => $this->confidenceForMetrics($categoryMetrics->all()),
                ];
            })
            ->values()
            ->all();
    }

    private function metricScores(array $metrics): array
    {
        return collect($metrics)
            ->groupBy('metric_key')
            ->map(function ($metricRows, string $metricKey) {
                $definition = $this->benchmarkLibrary->metric($metricKey);
                $scores = collect($metricRows)
                    ->pluck('score_0_100')
                    ->filter(fn ($value) => is_numeric($value));
                $score = $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : null;

                return [
                    'metric_key' => $metricKey,
                    'display_name' => $definition['display_name'] ?? str_replace('_', ' ', $metricKey),
                    'category' => $definition['category'] ?? ($metricRows[0]['category'] ?? 'unknown'),
                    'score_0_100' => $score,
                    'percentile_estimate' => $score,
                    'player_count' => collect($metricRows)->pluck('player_id')->unique()->count(),
                    'metric_count' => $scores->count(),
                    'label' => $this->label($score),
                    'confidence' => $this->confidenceForMetrics($metricRows->all()),
                    'players' => collect($metricRows)->map(fn (array $metric) => [
                        'player_id' => $metric['player_id'] ?? null,
                        'name' => $metric['player_name'] ?? 'Unknown Player',
                        'score_0_100' => $metric['score_0_100'] ?? null,
                        'raw_value' => $metric['raw_value'] ?? null,
                        'label' => $metric['label'] ?? null,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function playersAroundAverage(array $playerProfiles, array $allMetrics, string $direction): array
    {
        $teamAverage = $this->averageScore($allMetrics);
        if ($teamAverage === null) {
            return [];
        }

        return collect($playerProfiles)
            ->filter(fn (array $profile) => is_numeric($profile['average_score'] ?? null))
            ->filter(fn (array $profile) => $direction === 'above'
                ? (float) $profile['average_score'] >= $teamAverage
                : (float) $profile['average_score'] < $teamAverage)
            ->sortBy(fn (array $profile) => (float) $profile['average_score'], SORT_REGULAR, $direction === 'above')
            ->take(8)
            ->map(fn (array $profile) => [
                'player_id' => $profile['player_id'],
                'name' => $profile['name'],
                'average_score' => $profile['average_score'],
                'metric_count' => $profile['metric_count'],
                'weakest_category' => $profile['weakest_category'],
            ])
            ->values()
            ->all();
    }

    private function playersNeedingAttention(array $playerProfiles): array
    {
        return collect($playerProfiles)
            ->filter(fn (array $profile) => ($profile['metric_count'] ?? 0) === 0 || (is_numeric($profile['average_score'] ?? null) && (float) $profile['average_score'] < 50))
            ->sortBy(fn (array $profile) => $profile['average_score'] ?? -1)
            ->take(10)
            ->map(fn (array $profile) => [
                'player_id' => $profile['player_id'],
                'name' => $profile['name'],
                'average_score' => $profile['average_score'],
                'metric_count' => $profile['metric_count'],
                'weakest_category' => $profile['weakest_category'],
                'weakest_metrics' => $profile['weakest_metrics'],
                'reason' => ($profile['metric_count'] ?? 0) === 0
                    ? 'No benchmark metrics are available for this player.'
                    : 'Player benchmark average is below the team attention threshold.',
            ])
            ->values()
            ->all();
    }

    private function teamGaps(array $categoryScores, array $metricScores, array $missingCounts, int $playerCount): array
    {
        $gaps = [];

        foreach ($categoryScores as $category) {
            if (is_numeric($category['score_0_100'] ?? null) && (float) $category['score_0_100'] < 50) {
                $gaps[] = [
                    'id' => 'category_'.$category['category'].'_low',
                    'category' => $category['category'],
                    'title' => ucfirst((string) $category['category']).' benchmark weakness',
                    'why' => 'Team '.$category['category'].' benchmark score is '.$category['score_0_100'].'.',
                    'affected_count' => $category['player_count'] ?? 0,
                    'evidence' => $category,
                ];
            }
        }

        foreach ($metricScores as $metric) {
            $score = $this->numberOrNull($metric['score_0_100'] ?? null);
            if ($score === null || $score >= 50) {
                continue;
            }

            $gaps[] = [
                'id' => 'metric_'.$metric['metric_key'].'_low',
                'category' => $metric['category'],
                'title' => $metric['display_name'].' below benchmark',
                'why' => $metric['display_name'].' team benchmark score is '.$metric['score_0_100'].'.',
                'affected_count' => $metric['player_count'] ?? 0,
                'evidence' => $metric,
            ];
        }

        foreach ($missingCounts as $missing) {
            $missingCount = (int) ($missing['missing_count'] ?? 0);
            if ($playerCount > 0 && ($missingCount / $playerCount) >= 0.5) {
                $gaps[] = [
                    'id' => 'missing_'.$missing['metric_key'],
                    'category' => $missing['category'] ?? 'data',
                    'title' => ($missing['display_name'] ?? $missing['metric_key']).' missing often',
                    'why' => $missingCount.' of '.$playerCount.' players are missing this benchmark metric.',
                    'affected_count' => $missingCount,
                    'evidence' => $missing,
                ];
            }
        }

        return array_values(array_slice($gaps, 0, 12));
    }

    private function missingMetrics(array $missingCounts): array
    {
        return collect($missingCounts)
            ->sortByDesc('missing_count')
            ->map(function (array $missing) {
                $missing['players'] = array_values(array_slice($missing['players'] ?? [], 0, 8));

                return $missing;
            })
            ->values()
            ->all();
    }

    private function rankCategories(array $categories, string $direction): array
    {
        return collect($categories)
            ->filter(fn (array $category) => is_numeric($category['score_0_100'] ?? null))
            ->sortBy(fn (array $category) => (float) $category['score_0_100'], SORT_REGULAR, $direction === 'desc')
            ->take(5)
            ->values()
            ->all();
    }

    private function rankTeamMetrics(array $metrics, string $direction): array
    {
        return collect($metrics)
            ->filter(fn (array $metric) => is_numeric($metric['score_0_100'] ?? null))
            ->sortBy(fn (array $metric) => (float) $metric['score_0_100'], SORT_REGULAR, $direction === 'desc')
            ->take(8)
            ->values()
            ->all();
    }

    private function rankMetrics(array $metrics, string $direction, int $limit): array
    {
        return collect($metrics)
            ->filter(fn (array $metric) => is_numeric($metric['score_0_100'] ?? null))
            ->sortBy(fn (array $metric) => (float) $metric['score_0_100'], SORT_REGULAR, $direction === 'desc')
            ->take($limit)
            ->map(fn (array $metric) => [
                'metric_key' => $metric['metric_key'] ?? null,
                'display_name' => $metric['display_name'] ?? null,
                'category' => $metric['category'] ?? null,
                'score_0_100' => $metric['score_0_100'] ?? null,
                'label' => $metric['label'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function weakestCategory(array $metrics): ?array
    {
        $categories = $this->categoryScores($metrics);
        $weakest = $this->rankCategories($categories, 'asc')[0] ?? null;

        return is_array($weakest) ? $weakest : null;
    }

    private function averageScore(array $metrics): ?float
    {
        $scores = collect($metrics)
            ->pluck('score_0_100')
            ->filter(fn ($value) => is_numeric($value));

        return $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : null;
    }

    private function benchmarkConfidence(array $metrics): string
    {
        if (empty($metrics)) {
            return 'low';
        }

        $counts = collect($metrics)->countBy(fn (array $metric) => $this->normalizeConfidence((string) ($metric['confidence'] ?? 'low')));
        $weighted = (($counts->get('high', 0) * 3) + ($counts->get('medium', 0) * 2) + $counts->get('low', 0)) / max(1, count($metrics));

        return match (true) {
            $weighted >= 2.5 => 'high',
            $weighted >= 1.6 => 'medium',
            default => 'low',
        };
    }

    private function confidenceForMetrics(array $metrics): string
    {
        return $this->benchmarkConfidence($metrics);
    }

    private function sourceMix(array $metrics): array
    {
        $counts = ['research' => 0, 'population' => 0, 'composite' => 0];

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

        return [
            'research_share' => round($counts['research'] / $total, 2),
            'population_share' => round($counts['population'] / $total, 2),
            'composite_share' => round($counts['composite'] / $total, 2),
            'counts' => $counts,
        ];
    }

    private function label(?float $score): string
    {
        if ($score === null) {
            return 'unknown';
        }

        return match (true) {
            $score >= 95 => 'elite',
            $score >= 75 => 'good',
            $score >= 50 => 'average',
            $score >= 25 => 'below_average',
            default => 'critical',
        };
    }

    private function playerName(array $snapshot): string
    {
        $player = $snapshot['summary']['player'] ?? [];
        $name = trim((string) ($player['name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        $name = trim((string) ($player['first_name'] ?? '').' '.(string) ($player['last_name'] ?? ''));

        return $name !== '' ? $name : (string) ($snapshot['player_id'] ?? 'Unknown Player');
    }

    private function normalizeConfidence(string $confidence): string
    {
        return in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'low';
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
