<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Services\Intelligence\BenchmarkCollectionPlanner;
use App\Services\Intelligence\TeamBenchmarkProfileService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

class DevelopmentHealthTrendService
{
    private const COMPONENTS = [
        'planning_consistency' => 'Planning Consistency',
        'player_completion' => 'Player Completion',
        'benchmark_coverage' => 'Benchmark Coverage',
        'coach_review_flow' => 'Coach Review Flow',
        'trusted_data_growth' => 'Trusted Data Growth',
        'communication_rhythm' => 'Communication Rhythm',
    ];

    public function __construct(
        private readonly DevelopmentProgramHealthService $developmentProgramHealthService,
        private readonly TeamBenchmarkProfileService $teamBenchmarkProfileService,
        private readonly BenchmarkCollectionPlanner $benchmarkCollectionPlanner,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTeamTrendline(string $teamId, array $options = []): array
    {
        [$start, $end, $period, $periodCount] = $this->dateWindow($options);
        $includeComponents = $this->bool($options['include_components'] ?? true);
        $includeRecommendations = $this->bool($options['include_recommendations'] ?? true);
        $warnings = [];

        try {
            $benchmarkDays = max(30, min(365, (int) ($options['benchmark_days'] ?? 365)));
            $benchmarkProfile = $this->safe(
                fn (): array => $this->teamBenchmarkProfileService->build($teamId, $benchmarkDays),
                [],
                $warnings,
                'Team benchmark profile',
            );
            $collectionPlan = $this->safe(
                fn (): array => $this->benchmarkCollectionPlanner->buildTeamCollectionPlanFromData($teamId, $benchmarkDays, $benchmarkProfile, null),
                [],
                $warnings,
                'Benchmark collection plan',
            );

            $periodScores = $this->buildPeriodScores($teamId, $start->toDateString(), $end->toDateString(), [
                ...$options,
                'period' => $period,
                'benchmark_profile' => $benchmarkProfile,
                'collection_plan' => $collectionPlan,
                'include_components' => $includeComponents,
            ]);
            $overallTrend = $this->comparePeriods($periodScores);
            $componentTrends = $includeComponents ? $this->buildComponentTrends($periodScores) : [];
            $biggestImprovements = $this->biggestComponentMoves($componentTrends, 'improving');
            $biggestDeclines = $this->biggestComponentMoves($componentTrends, 'declining');
            $trendline = [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'period' => $period,
                'period_count' => count($periodScores),
                'trend_status' => $this->trendStatus($periodScores),
                'overall_trend' => $overallTrend,
                'period_scores' => $periodScores,
                'component_trends' => $componentTrends,
                'biggest_improvements' => $biggestImprovements,
                'biggest_declines' => $biggestDeclines,
                'trend_recommendations' => [],
                'warnings' => array_values(array_unique(array_filter([
                    ...$warnings,
                    ...$this->missingComponentWarnings($componentTrends),
                ]))),
                'evidence' => [
                    'requested_period_count' => $periodCount,
                    'include_components' => $includeComponents,
                    'include_recommendations' => $includeRecommendations,
                    'source' => 'development_program_health_live_weekly_scores',
                    'data_is_persisted' => false,
                ],
            ];
            $trendline['trend_recommendations'] = $includeRecommendations
                ? $this->buildTrendRecommendations($trendline)
                : [];

            return $trendline;
        } catch (Throwable $exception) {
            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'period' => $period,
                'period_count' => 0,
                'trend_status' => 'failed',
                'overall_trend' => $this->emptyOverallTrend('Trendline is not available yet.'),
                'period_scores' => [],
                'component_trends' => [],
                'biggest_improvements' => [],
                'biggest_declines' => [],
                'trend_recommendations' => [],
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'exception' => class_basename($exception),
                    'data_is_persisted' => false,
                ],
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildPeriodScores(string $teamId, string $startDate, string $endDate, array $options = []): array
    {
        $period = (string) ($options['period'] ?? 'week');
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->endOfDay();
        $periods = $this->periods($start, $end, $period);
        $rows = [];

        foreach ($periods as $index => $periodRow) {
            $periodStart = $periodRow['start'];
            $periodEnd = $periodRow['end'];
            $health = $this->developmentProgramHealthService->buildTeamHealthScore($teamId, [
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'days' => max(1, $periodStart->diffInDays($periodEnd) + 1),
                'benchmark_profile' => $options['benchmark_profile'] ?? null,
                'collection_plan' => $options['collection_plan'] ?? null,
                'include_weekly_reports' => true,
                'include_season_archive' => false,
                'include_population_learning' => false,
                'include_decision_brief' => false,
            ]);

            $rows[] = [
                'period_index' => $index + 1,
                'period_start_date' => $periodStart->toDateString(),
                'period_end_date' => $periodEnd->toDateString(),
                'period_label' => $this->periodLabel($periodStart, $periodEnd),
                'overall_score_0_100' => $health['overall_score_0_100'] ?? null,
                'overall_label' => $health['overall_label'] ?? 'no_data',
                'component_scores' => $this->componentScores($health),
                'top_strength' => Arr::get($health, 'summary.primary_strength'),
                'top_risk' => Arr::get($health, 'summary.primary_risk'),
                'next_best_action' => Arr::get($health, 'summary.next_best_action'),
                'warnings' => Arr::wrap($health['warnings'] ?? []),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $periodScores
     * @return array<string, mixed>
     */
    public function comparePeriods(array $periodScores): array
    {
        $periods = array_values($periodScores);
        if (count($periods) < 2) {
            return $this->emptyOverallTrend('More weeks are needed to show a trend.', $periods[0]['overall_score_0_100'] ?? null);
        }

        $current = $periods[count($periods) - 1];
        $previous = $periods[count($periods) - 2];
        $starting = $periods[0];
        $currentScore = $this->numberOrNull($current['overall_score_0_100'] ?? null);
        $previousScore = $this->numberOrNull($previous['overall_score_0_100'] ?? null);
        $startingScore = $this->numberOrNull($starting['overall_score_0_100'] ?? null);
        $deltaPrevious = $currentScore !== null && $previousScore !== null ? round($currentScore - $previousScore, 1) : null;
        $deltaStart = $currentScore !== null && $startingScore !== null ? round($currentScore - $startingScore, 1) : null;
        $direction = $this->trendDirection($deltaPrevious, $currentScore, $previousScore);

        return [
            'current_score' => $currentScore,
            'previous_score' => $previousScore,
            'starting_score' => $startingScore,
            'score_delta_vs_previous' => $deltaPrevious,
            'score_delta_vs_start' => $deltaStart,
            'trend_direction' => $direction,
            'trend_label' => $this->trendLabel($direction),
            'summary' => $this->overallSummary($direction, $deltaPrevious),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $periodScores
     * @return array<string, array<string, mixed>>
     */
    public function buildComponentTrends(array $periodScores): array
    {
        $periods = array_values($periodScores);
        $current = count($periods) > 0 ? $periods[count($periods) - 1] : [];
        $previous = count($periods) > 1 ? $periods[count($periods) - 2] : [];
        $trends = [];

        foreach (self::COMPONENTS as $component => $displayName) {
            $currentScore = $this->numberOrNull(Arr::get($current, "component_scores.$component"));
            $previousScore = $this->numberOrNull(Arr::get($previous, "component_scores.$component"));
            $delta = $currentScore !== null && $previousScore !== null ? round($currentScore - $previousScore, 1) : null;
            $direction = $this->trendDirection($delta, $currentScore, $previousScore);

            $trends[$component] = [
                'current_score' => $currentScore,
                'previous_score' => $previousScore,
                'delta' => $delta,
                'trend_direction' => $direction,
                'summary' => $this->componentSummary($displayName, $direction, $delta),
                'display_name' => $displayName,
                'evidence' => [
                    'current_period' => $current['period_label'] ?? null,
                    'previous_period' => $previous['period_label'] ?? null,
                ],
            ];
        }

        return $trends;
    }

    /**
     * @param array<string, mixed> $trendline
     * @return array<int, array<string, mixed>>
     */
    public function buildTrendRecommendations(array $trendline): array
    {
        $recommendations = [];
        $components = Arr::wrap($trendline['component_trends'] ?? []);
        $overallDirection = (string) Arr::get($trendline, 'overall_trend.trend_direction', 'no_data');

        if (($components['planning_consistency']['trend_direction'] ?? null) === 'declining') {
            $recommendations[] = $this->recommendation('Stabilize Weekly Planning', 'high', 'planning_consistency', 'Planning consistency dropped compared to last week.', "Generate and publish next week's plan earlier.", 'publish_plan');
        }

        if (($components['player_completion']['trend_direction'] ?? null) === 'declining') {
            $recommendations[] = $this->recommendation('Follow Up on Player Completion', 'high', 'player_completion', 'Player completion dropped from the previous week.', 'Check in with players who missed assigned work and simplify the next plan if needed.', 'player_follow_up');
        }

        $benchmark = $components['benchmark_coverage'] ?? [];
        if (($benchmark['trend_direction'] ?? null) === 'improving') {
            $recommendations[] = $this->recommendation('Keep Collecting Baselines', 'medium', 'benchmark_coverage', 'Benchmark coverage improved.', 'Continue collecting missing strength, mobility, and throwing baselines.', 'benchmark_collection');
        } elseif (($benchmark['trend_direction'] ?? null) === 'declining' || (($benchmark['current_score'] ?? null) !== null && (float) $benchmark['current_score'] < 60.0)) {
            $recommendations[] = $this->recommendation('Prioritize Benchmark Baselines', 'high', 'benchmark_coverage', 'Benchmark coverage is still limiting FMTRX intelligence.', 'Add baseline collection blocks to the next plan.', 'benchmark_collection');
        }

        if (($components['coach_review_flow']['trend_direction'] ?? null) === 'declining') {
            $recommendations[] = $this->recommendation('Clear Review Queue', 'high', 'coach_review_flow', 'Pending review is slowing trusted data growth.', 'Review submitted benchmark values before generating the next plan.', 'review_queue');
        }

        $trusted = $components['trusted_data_growth'] ?? [];
        if (($trusted['current_score'] ?? null) === null || (float) ($trusted['current_score'] ?? 0) < 60.0) {
            $recommendations[] = $this->recommendation('Promote Approved Data', 'high', 'trusted_data_growth', 'Approved values are not becoming trusted benchmark data quickly enough.', 'Promote approved submissions and refresh intelligence.', 'trusted_data');
        }

        if (($components['communication_rhythm']['trend_direction'] ?? null) === 'declining') {
            $recommendations[] = $this->recommendation('Send Weekly Development Update', 'medium', 'communication_rhythm', 'Communication rhythm dropped this week.', 'Create a Parent Update or Staff Report after reviewing the weekly rollup.', 'weekly_report');
        }

        if ($overallDirection === 'improving') {
            $recommendations[] = $this->recommendation('Keep Current Operating Rhythm', 'low', 'overall', 'The development system is improving.', 'Keep the weekly cycle: plan, complete, review, promote, report.', null);
        }

        if (empty($recommendations)) {
            $recommendations[] = $this->recommendation('Build More Health History', 'medium', 'overall', 'More weekly operating data is needed before FMTRX can rank a clear trend action.', 'Publish plans, collect completion, review submissions, and send weekly updates.', null);
        }

        return collect($recommendations)
            ->unique(fn (array $row): string => (string) ($row['title'] ?? ''))
            ->sortByDesc(fn (array $row): int => $this->priorityRank((string) ($row['priority'] ?? 'low')))
            ->values()
            ->take(5)
            ->all();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string, 3: int}
     */
    private function dateWindow(array $options): array
    {
        $period = (string) ($options['period'] ?? 'week');
        $period = $period === 'week' ? 'week' : 'week';
        $weeks = max(1, min(52, (int) ($options['weeks'] ?? 8)));
        $end = ! empty($options['end_date'])
            ? CarbonImmutable::parse((string) $options['end_date'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $start = ! empty($options['start_date'])
            ? CarbonImmutable::parse((string) $options['start_date'])->startOfDay()
            : $end->startOfWeek()->subWeeks($weeks - 1)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return [$start, $end, $period, count($this->periods($start, $end, $period))];
    }

    /**
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function periods(CarbonImmutable $start, CarbonImmutable $end, string $period): array
    {
        $rows = [];
        $cursor = $period === 'week' ? $start->startOfWeek() : $start->startOfWeek();

        while ($cursor->lessThanOrEqualTo($end)) {
            $periodStart = $cursor->greaterThan($start) ? $cursor : $start;
            $periodEnd = $cursor->endOfWeek()->lessThan($end) ? $cursor->endOfWeek() : $end;
            $rows[] = [
                'start' => $periodStart->startOfDay(),
                'end' => $periodEnd->endOfDay(),
            ];
            $cursor = $cursor->addWeek()->startOfWeek();
        }

        return $rows;
    }

    private function periodLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return $start->format('M j').' - '.$end->format('M j');
    }

    /**
     * @return array<string, float|null>
     */
    private function componentScores(array $health): array
    {
        $scores = [];
        foreach (self::COMPONENTS as $component => $displayName) {
            $scores[$component] = $this->numberOrNull(Arr::get($health, "score_components.$component.score_0_100"));
        }

        return $scores;
    }

    /**
     * @param array<int, array<string, mixed>> $periodScores
     */
    private function trendStatus(array $periodScores): string
    {
        if (empty($periodScores)) {
            return 'empty';
        }

        $scores = collect($periodScores)->pluck('overall_score_0_100')->filter(fn ($score): bool => is_numeric($score));
        if ($scores->isEmpty()) {
            return 'empty';
        }

        return $scores->count() === count($periodScores) ? 'complete' : 'partial';
    }

    /**
     * @param array<string, array<string, mixed>> $componentTrends
     * @return array<int, array<string, mixed>>
     */
    private function biggestComponentMoves(array $componentTrends, string $direction): array
    {
        return collect($componentTrends)
            ->filter(function (array $trend) use ($direction): bool {
                $delta = $this->numberOrNull($trend['delta'] ?? null);
                if ($delta === null) {
                    return false;
                }

                return $direction === 'improving' ? $delta > 0 : $delta < 0;
            })
            ->sortBy(fn (array $trend): float => $direction === 'improving'
                ? -1 * abs((float) ($trend['delta'] ?? 0))
                : (float) ($trend['delta'] ?? 0))
            ->map(function (array $trend, string $component) use ($direction): array {
                $delta = round((float) ($trend['delta'] ?? 0), 1);
                $displayName = (string) ($trend['display_name'] ?? $this->human($component));

                return [
                    'component' => $component,
                    'display_name' => $displayName,
                    'delta' => $delta,
                    'message' => $direction === 'improving'
                        ? $displayName.' improved by '.abs($delta).' points.'
                        : $displayName.' dropped by '.abs($delta).' points.',
                    'evidence' => $trend['evidence'] ?? [],
                ];
            })
            ->values()
            ->take(4)
            ->all();
    }

    private function emptyOverallTrend(string $summary, mixed $currentScore = null): array
    {
        return [
            'current_score' => $this->numberOrNull($currentScore),
            'previous_score' => null,
            'starting_score' => $this->numberOrNull($currentScore),
            'score_delta_vs_previous' => null,
            'score_delta_vs_start' => null,
            'trend_direction' => 'no_data',
            'trend_label' => 'No Data',
            'summary' => $summary,
        ];
    }

    private function trendDirection(?float $delta, ?float $current, ?float $previous): string
    {
        if ($current === null || $previous === null || $delta === null) {
            return 'no_data';
        }

        return match (true) {
            $delta >= 5.0 => 'improving',
            $delta <= -5.0 => 'declining',
            default => 'stable',
        };
    }

    private function trendLabel(string $direction): string
    {
        return [
            'improving' => 'Improving',
            'declining' => 'Declining',
            'stable' => 'Stable',
            'no_data' => 'No Data',
        ][$direction] ?? 'No Data';
    }

    private function overallSummary(string $direction, ?float $delta): string
    {
        return match ($direction) {
            'improving' => 'Development program health improved by '.abs((float) $delta).' points from the previous week.',
            'declining' => 'Development program health dropped by '.abs((float) $delta).' points from the previous week.',
            'stable' => 'Development program health is stable compared to the previous week.',
            default => 'More weeks are needed to show a trend.',
        };
    }

    private function componentSummary(string $displayName, string $direction, ?float $delta): string
    {
        return match ($direction) {
            'improving' => $displayName.' improved by '.abs((float) $delta).' points.',
            'declining' => $displayName.' dropped by '.abs((float) $delta).' points.',
            'stable' => $displayName.' stayed within five points of last week.',
            default => $displayName.' needs more weekly data before a trend is available.',
        };
    }

    /**
     * @param array<string, array<string, mixed>> $componentTrends
     * @return array<int, string>
     */
    private function missingComponentWarnings(array $componentTrends): array
    {
        return collect($componentTrends)
            ->filter(fn (array $trend): bool => ($trend['trend_direction'] ?? null) === 'no_data')
            ->map(fn (array $trend): string => ($trend['display_name'] ?? 'Component').' is missing current or previous period data.')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function recommendation(string $title, string $priority, string $component, string $why, string $action, ?string $actionType): array
    {
        return [
            'title' => $title,
            'priority' => $priority,
            'component' => $component,
            'why' => $why,
            'action' => $action,
            'action_type' => $actionType,
        ];
    }

    /**
     * @param callable(): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function safe(callable $callback, array $fallback, array &$warnings, string $label): array
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            $warnings[] = $label.' unavailable: '.$exception->getMessage();

            return $fallback;
        }
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return ! in_array(strtolower($value), ['0', 'false', 'no', 'off'], true);
        }

        return (bool) $value;
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    private function priorityRank(string $priority): int
    {
        return [
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
        ][$priority] ?? 0;
    }

    private function human(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?: 'unknown'));
    }
}
