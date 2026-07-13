<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Services\Intelligence\BenchmarkCollectionPlanner;
use App\Services\Intelligence\DecisionEngine;
use App\Services\Intelligence\PopulationLearningAuditService;
use App\Services\Intelligence\TeamBenchmarkProfileService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

class DevelopmentProgramHealthService
{
    private const DEFAULT_WEIGHTS = [
        'planning_consistency' => 20,
        'player_completion' => 20,
        'benchmark_coverage' => 20,
        'coach_review_flow' => 15,
        'trusted_data_growth' => 15,
        'communication_rhythm' => 10,
    ];

    public function __construct(
        private readonly WeeklyPlannerRollupService $weeklyPlannerRollupService,
        private readonly TeamBenchmarkProfileService $teamBenchmarkProfileService,
        private readonly BenchmarkCollectionPlanner $benchmarkCollectionPlanner,
        private readonly CommunicationRhythmService $communicationRhythmService,
        private readonly SeasonCommunicationRhythmService $seasonCommunicationRhythmService,
        private readonly WeeklyReportDeliveryAnalyticsService $weeklyReportDeliveryAnalyticsService,
        private readonly SeasonArchiveDeliveryAnalyticsService $seasonArchiveDeliveryAnalyticsService,
        private readonly DecisionEngine $decisionEngine,
        private readonly PopulationLearningAuditService $populationLearningAuditService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTeamHealthScore(string $teamId, array $options = []): array
    {
        [$start, $end, $days] = $this->dateWindow($options);
        $componentsPayload = $this->buildScoreComponents($teamId, [
            ...$options,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
        ]);
        $components = $componentsPayload['score_components'] ?? [];
        $warnings = Arr::wrap($componentsPayload['warnings'] ?? []);
        $overallScore = $this->weightedAverage($components);
        $overallLabel = $this->overallLabel($overallScore);
        $strengths = $this->strengths($components);
        $risks = $this->risks($components, $warnings);
        $actions = $this->buildHealthRecommendations($components);
        $trendSignals = $this->trendSignals($components, $componentsPayload['source_data'] ?? []);
        $summary = $this->summary($overallScore, $overallLabel, $strengths, $risks, $actions);

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'overall_score_0_100' => $overallScore,
            'overall_label' => $overallLabel,
            'summary' => $summary,
            'score_components' => $components,
            'strengths' => $strengths,
            'risks' => $risks,
            'highest_leverage_actions' => array_slice($actions, 0, 5),
            'operating_recommendations' => $actions,
            'trend_signals' => $trendSignals,
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'evidence' => [
                'days' => $days,
                'component_weights' => self::DEFAULT_WEIGHTS,
                'available_component_count' => collect($components)->filter(fn (array $component): bool => is_numeric($component['score_0_100'] ?? null))->count(),
                'source' => 'planner_rollup_benchmark_profile_reviews_trusted_data_communication_rhythm',
                'include_weekly_reports' => $this->bool($options['include_weekly_reports'] ?? true),
                'include_season_archive' => $this->bool($options['include_season_archive'] ?? true),
                'include_population_learning' => $this->bool($options['include_population_learning'] ?? false),
                'include_decision_brief' => $this->bool($options['include_decision_brief'] ?? false),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildScoreComponents(string $teamId, array $options = []): array
    {
        [$start, $end, $days] = $this->dateWindow($options);
        $benchmarkDays = max(30, min(365, (int) ($options['benchmark_days'] ?? 365)));
        $warnings = [];

        $weeklyRollup = $this->safe(
            fn (): array => $this->weeklyPlannerRollupService->buildTeamWeeklyRollup($teamId, [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'include_players' => true,
                'include_benchmark_intelligence' => false,
            ]),
            [],
            $warnings,
            'Weekly planner rollup',
        );

        $benchmarkProfile = is_array($options['benchmark_profile'] ?? null)
            ? $options['benchmark_profile']
            : $this->safe(
                fn (): array => $this->teamBenchmarkProfileService->build($teamId, $benchmarkDays),
                [],
                $warnings,
                'Team benchmark profile',
            );

        $decisionBrief = [];
        if ($this->bool($options['include_decision_brief'] ?? false)) {
            $decisionBrief = $this->safe(
                fn (): array => $this->decisionEngine->buildTeamDecisionBrief($teamId, $benchmarkDays),
                [],
                $warnings,
                'Decision brief',
            );
        }

        $collectionPlan = is_array($options['collection_plan'] ?? null)
            ? $options['collection_plan']
            : $this->safe(
                fn (): array => $this->benchmarkCollectionPlanner->buildTeamCollectionPlanFromData($teamId, $benchmarkDays, $benchmarkProfile, $decisionBrief ?: null),
                [],
                $warnings,
                'Benchmark collection plan',
            );

        $weeklyCommunication = [];
        $weeklyDelivery = [];
        if ($this->bool($options['include_weekly_reports'] ?? true)) {
            $weeks = max(1, min(52, (int) ceil($days / 7)));
            $weeklyCommunication = $this->safe(
                fn (): array => $this->communicationRhythmService->buildTeamRhythm($teamId, [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'weeks' => $weeks,
                ]),
                [],
                $warnings,
                'Weekly communication rhythm',
            );
            $weeklyDelivery = $this->safe(
                fn (): array => $this->weeklyReportDeliveryAnalyticsService->buildTeamAnalytics($teamId, [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'days' => $days,
                ]),
                [],
                $warnings,
                'Weekly report delivery analytics',
            );
        }

        $seasonCommunication = [];
        $seasonDelivery = [];
        if ($this->bool($options['include_season_archive'] ?? true)) {
            $months = max(1, min(24, (int) ceil($days / 30)));
            $seasonCommunication = $this->safe(
                fn (): array => $this->seasonCommunicationRhythmService->buildTeamRhythm($teamId, [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'months' => $months,
                ]),
                [],
                $warnings,
                'Season communication rhythm',
            );
            $seasonDelivery = $this->safe(
                fn (): array => $this->seasonArchiveDeliveryAnalyticsService->buildTeamAnalytics($teamId, [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'days' => $days,
                ]),
                [],
                $warnings,
                'Season archive delivery analytics',
            );
        }

        $populationLearning = [];
        if ($this->bool($options['include_population_learning'] ?? false)) {
            $populationLearning = $this->safe(
                fn (): array => $this->populationLearningAuditService->buildAuditReport([
                    'days' => $benchmarkDays,
                    'metric_key' => 'max_exit_velocity',
                ]),
                [],
                $warnings,
                'Population learning audit',
            );
        }

        $data = [
            'team_id' => $teamId,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'benchmark_days' => $benchmarkDays,
            'weekly_rollup' => $weeklyRollup,
            'benchmark_profile' => $benchmarkProfile,
            'collection_plan' => $collectionPlan,
            'decision_brief' => $decisionBrief,
            'weekly_communication' => $weeklyCommunication,
            'weekly_delivery_analytics' => $weeklyDelivery,
            'season_communication' => $seasonCommunication,
            'season_delivery_analytics' => $seasonDelivery,
            'population_learning_audit' => $populationLearning,
        ];

        return [
            'score_components' => [
                'planning_consistency' => $this->scorePlanningConsistency($data),
                'player_completion' => $this->scorePlayerCompletion($data),
                'benchmark_coverage' => $this->scoreBenchmarkCoverage($data),
                'coach_review_flow' => $this->scoreCoachReviewFlow($data),
                'trusted_data_growth' => $this->scoreTrustedDataGrowth($data),
                'communication_rhythm' => $this->scoreCommunicationRhythm($data),
            ],
            'warnings' => $warnings,
            'source_data' => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scorePlanningConsistency(array $data): array
    {
        $plan = Arr::get($data, 'weekly_rollup.plan_execution_summary', []);
        $created = (int) ($plan['plans_created'] ?? 0);
        $published = (int) ($plan['plans_published'] ?? 0);
        $completed = (int) ($plan['plans_completed'] ?? 0);
        $benchmarkGenerated = (int) ($plan['benchmark_generated_plan_count'] ?? 0);

        if ($created <= 0) {
            return $this->component(
                'planning_consistency',
                null,
                'No Plans Found',
                'Daily plans are the operating layer that turns intelligence into assigned work.',
                [
                    'plans_created' => 0,
                    'plans_published' => 0,
                ],
                ['No daily plans were found in this date range.'],
                ['Create and publish the next daily plan from the planner command center.'],
            );
        }

        $publishRate = $this->percent($published, $created);
        $score = match (true) {
            $published >= 5 && $publishRate >= 80.0 => 92.0,
            $published >= 3 && $publishRate >= 60.0 => 80.0,
            $published >= 1 && $publishRate >= 40.0 => 64.0,
            $created > 0 => 45.0,
            default => null,
        };

        if ($benchmarkGenerated > 0 && is_numeric($score)) {
            $score = min(100.0, $score + 4.0);
        }

        return $this->component(
            'planning_consistency',
            $score,
            $published > 0 ? 'Plans Are Reaching Players' : 'Plans Need Publishing',
            'Consistent planning keeps development work visible, assigned, and reviewable.',
            [
                'plans_created' => $created,
                'plans_published' => $published,
                'plans_completed' => $completed,
                'publish_rate' => $publishRate,
                'benchmark_generated_plan_count' => $benchmarkGenerated,
            ],
            $published <= 0 ? ['Plans exist, but none are published for players.'] : [],
            $published <= 0
                ? ['Publish the next plan and assign it to active players.']
                : ['Keep publishing plans on a predictable weekly rhythm.'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function scorePlayerCompletion(array $data): array
    {
        $plan = Arr::get($data, 'weekly_rollup.plan_execution_summary', []);
        $players = Arr::get($data, 'weekly_rollup.player_completion_summary', []);
        $assigned = (int) ($plan['total_assigned_players'] ?? 0);
        $completed = (int) ($plan['total_completed_assignments'] ?? 0);
        $completion = (float) ($plan['average_completion_percentage'] ?? 0);

        if ($assigned <= 0) {
            return $this->component(
                'player_completion',
                null,
                'No Assigned Player Work',
                'Player completion shows whether development plans are actually being executed.',
                ['assigned_players' => 0],
                ['No player assignments were found in this date range.'],
                ['Assign the next published plan to players so completion can be tracked.'],
            );
        }

        $score = match (true) {
            $completion >= 85.0 => 92.0,
            $completion >= 70.0 => 82.0,
            $completion >= 55.0 => 67.0,
            $completion >= 35.0 => 49.0,
            $completion > 0.0 => 25.0,
            default => 10.0,
        };

        $followUps = Arr::wrap($players['players_needing_follow_up'] ?? []);

        return $this->component(
            'player_completion',
            $score,
            $completion >= 70.0 ? 'Players Are Completing Work' : 'Completion Needs Follow-Up',
            'Completion rate tells the coach whether the plan is becoming behavior, not just a document.',
            [
                'assigned_players' => $assigned,
                'completed_assignments' => $completed,
                'average_completion_percentage' => round($completion, 1),
                'players_needing_follow_up_count' => count($followUps),
            ],
            $completion < 55.0 ? ['Team completion is below the healthy operating range.'] : [],
            $completion < 70.0
                ? ['Follow up with players who missed or partially completed assigned work.']
                : ['Keep the current assignment and reminder rhythm.'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function scoreBenchmarkCoverage(array $data): array
    {
        $profile = Arr::wrap($data['benchmark_profile'] ?? []);
        $collectionPlan = Arr::wrap($data['collection_plan'] ?? []);
        $playerCount = (int) ($profile['player_count'] ?? 0);
        $playersWithData = (int) Arr::get($profile, 'evidence.players_with_benchmark_metrics', 0);
        $playersWithoutData = (int) Arr::get($profile, 'evidence.players_without_benchmark_metrics', max(0, $playerCount - $playersWithData));
        $metricCount = (int) ($profile['metric_count'] ?? 0);

        if ($playerCount <= 0) {
            return $this->component(
                'benchmark_coverage',
                null,
                'No Roster Benchmark Context',
                'Benchmark coverage tells FMTRX whether the team can make age-aware development decisions.',
                ['player_count' => 0],
                ['No players were found for this team benchmark profile.'],
                ['Confirm roster/team membership before collecting benchmark baselines.'],
            );
        }

        $coverage = $this->percent($playersWithData, $playerCount);
        $score = match (true) {
            $coverage >= 80.0 => 92.0,
            $coverage >= 60.0 => 82.0,
            $coverage >= 40.0 => 67.0,
            $coverage >= 20.0 => 49.0,
            default => 25.0,
        };

        if ($metricCount <= 0) {
            $score = 10.0;
        }

        $missingMetricCount = (int) Arr::get($collectionPlan, 'evidence.missing_metric_count', count(Arr::wrap($profile['missing_metrics'] ?? [])));

        return $this->component(
            'benchmark_coverage',
            $score,
            $coverage >= 60.0 ? 'Benchmark Coverage Is Useful' : 'Benchmark Baselines Are Thin',
            'Benchmark coverage determines how confidently FMTRX can compare players and choose next actions.',
            [
                'player_count' => $playerCount,
                'players_with_benchmark_metrics' => $playersWithData,
                'players_without_benchmark_metrics' => $playersWithoutData,
                'coverage_percentage' => $coverage,
                'benchmark_metric_count' => $metricCount,
                'benchmark_confidence' => $profile['benchmark_confidence'] ?? null,
                'collection_plan_priority' => $collectionPlan['priority_level'] ?? null,
                'missing_metric_count' => $missingMetricCount,
            ],
            $coverage < 40.0 ? ['Most players still need benchmark baselines.'] : [],
            $coverage < 80.0
                ? ['Use the benchmark collection plan to fill roster, EV, bullpen, strength, mobility, and throwing gaps.']
                : ['Keep benchmark baselines fresh as new sessions are logged.'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function scoreCoachReviewFlow(array $data): array
    {
        $review = Arr::get($data, 'weekly_rollup.review_summary', []);
        $benchmark = Arr::get($data, 'weekly_rollup.benchmark_collection_summary', []);
        $pending = (int) ($review['pending_review_count'] ?? 0);
        $approved = (int) ($review['approved_count'] ?? 0);
        $rejected = (int) ($review['rejected_count'] ?? 0);
        $corrections = (int) ($review['correction_requested_count'] ?? 0);
        $submitted = (int) ($benchmark['metric_values_submitted'] ?? 0);
        $totalReviewedTasks = $approved + $rejected + $corrections;
        $totalTasks = $totalReviewedTasks + $pending;

        if ($totalTasks <= 0 && $submitted <= 0) {
            return $this->component(
                'coach_review_flow',
                null,
                'No Review Queue Activity',
                'Coach review protects benchmark quality before player-entered values become trusted data.',
                ['pending_review_count' => 0, 'submitted_metric_values' => 0],
                [],
                ['When players submit benchmark values, review them before using them as trusted data.'],
            );
        }

        $reviewRate = $this->percent($totalReviewedTasks, max(1, $totalTasks));
        $oldestPendingDays = $this->ageInDays($review['oldest_pending_at'] ?? null);
        $score = match (true) {
            $pending === 0 => 92.0,
            $pending <= 2 && $reviewRate >= 60.0 => 78.0,
            $pending <= 5 => 62.0,
            default => 38.0,
        };

        if ($oldestPendingDays !== null && $oldestPendingDays >= 7) {
            $score = min($score, 45.0);
        }

        return $this->component(
            'coach_review_flow',
            $score,
            $pending === 0 ? 'Review Queue Is Current' : 'Benchmark Review Needs Attention',
            'Fast review turns player submissions into useful coaching data without trusting unverified values.',
            [
                'pending_review_count' => $pending,
                'approved_count' => $approved,
                'rejected_count' => $rejected,
                'correction_requested_count' => $corrections,
                'review_rate' => $reviewRate,
                'oldest_pending_at' => $review['oldest_pending_at'] ?? null,
                'oldest_pending_days' => $oldestPendingDays,
            ],
            $pending > 0 ? [$pending.' benchmark submission(s) are waiting for coach review.'] : [],
            $pending > 0
                ? ['Review pending benchmark submissions and approve, reject, or request correction.']
                : ['Keep reviewing submitted metrics before the next plan cycle.'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function scoreTrustedDataGrowth(array $data): array
    {
        $trusted = Arr::get($data, 'weekly_rollup.trusted_data_summary', []);
        $benchmark = Arr::get($data, 'weekly_rollup.benchmark_collection_summary', []);
        $populationAudit = Arr::wrap($data['population_learning_audit'] ?? []);
        $trustedValues = (int) ($trusted['trusted_values_added'] ?? 0);
        $playersImproved = (int) ($trusted['players_improved'] ?? 0);
        $approved = (int) ($benchmark['metric_values_approved'] ?? 0);
        $submitted = (int) ($benchmark['metric_values_submitted'] ?? 0);

        if ($trustedValues <= 0 && $approved <= 0 && $submitted <= 0) {
            return $this->component(
                'trusted_data_growth',
                null,
                'No New Trusted Data',
                'Trusted data growth shows whether completed work is improving benchmark confidence over time.',
                ['trusted_values_added' => 0, 'submitted_metric_values' => 0],
                [],
                ['Collect and review benchmark tasks so approved values can update player intelligence.'],
            );
        }

        $score = match (true) {
            $trustedValues >= 10 => 94.0,
            $trustedValues >= 5 => 84.0,
            $trustedValues >= 1 => 72.0,
            $approved > 0 => 55.0,
            $submitted > 0 => 35.0,
            default => null,
        };

        return $this->component(
            'trusted_data_growth',
            $score,
            $trustedValues > 0 ? 'Trusted Data Is Growing' : 'Submitted Data Needs Promotion',
            'Trusted data is what lets FMTRX benchmarks and recommendations improve without blindly accepting raw entries.',
            [
                'trusted_values_added' => $trustedValues,
                'players_improved' => $playersImproved,
                'approved_metric_values' => $approved,
                'submitted_metric_values' => $submitted,
                'metrics_improved' => Arr::wrap($trusted['metrics_improved'] ?? []),
                'population_learning_source' => $populationAudit['source'] ?? null,
            ],
            $trustedValues <= 0 && $submitted > 0 ? ['Metric values were submitted but not promoted to trusted data in this window.'] : [],
            $trustedValues <= 0
                ? ['Review and approve submitted benchmark values so trusted data can refresh.']
                : ['Use the updated trusted data when generating the next practice plan.'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function scoreCommunicationRhythm(array $data): array
    {
        $weeklyScore = Arr::get($data, 'weekly_communication.rhythm_score.score_0_100');
        $seasonScore = Arr::get($data, 'season_communication.rhythm_score.score_0_100');
        $scores = collect([$weeklyScore, $seasonScore])->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->values();

        if ($scores->isEmpty()) {
            return $this->component(
                'communication_rhythm',
                null,
                'No Communication Rhythm Yet',
                'Communication rhythm tells whether plans, progress, and reports are being shared consistently.',
                ['weekly_rhythm_score' => null, 'season_rhythm_score' => null],
                [],
                ['Create a weekly report or season development packet to start communication rhythm tracking.'],
            );
        }

        $score = round((float) $scores->avg(), 1);
        $weeklyHealth = Arr::wrap($data['weekly_delivery_analytics']['delivery_health'] ?? []);
        $seasonHealth = Arr::wrap($data['season_delivery_analytics']['delivery_health'] ?? []);

        return $this->component(
            'communication_rhythm',
            $score,
            $score >= 70.0 ? 'Communication Rhythm Is Active' : 'Communication Rhythm Needs Consistency',
            'A consistent communication rhythm keeps coaches, staff, players, and parents aligned without exposing private details.',
            [
                'weekly_rhythm_score' => $weeklyScore,
                'season_rhythm_score' => $seasonScore,
                'weekly_reports_sent' => $weeklyHealth['sent_count'] ?? null,
                'season_packets_sent' => $seasonHealth['sent_count'] ?? null,
                'weekly_copy_only_rate' => $weeklyHealth['copy_only_rate'] ?? null,
                'season_copy_only_rate' => $seasonHealth['copy_only_rate'] ?? null,
            ],
            $score < 50.0 ? ['Communication activity is inconsistent or missing in this window.'] : [],
            $score < 70.0
                ? ['Send a weekly parent-safe update or staff report from the report workflow.']
                : ['Keep the current weekly and season communication rhythm.'],
        );
    }

    /**
     * @param array<string, array<string, mixed>> $components
     * @return array<int, array<string, mixed>>
     */
    public function buildHealthRecommendations(array $components): array
    {
        $recommendations = [];

        foreach ($components as $key => $component) {
            $score = $component['score_0_100'] ?? null;
            $actions = Arr::wrap($component['recommended_actions'] ?? []);
            $risks = Arr::wrap($component['risks'] ?? []);

            if (is_numeric($score) && (float) $score >= 70.0 && empty($risks)) {
                continue;
            }

            $priority = $this->priorityForComponent($score, $key);
            $recommendations[] = [
                'title' => $this->recommendationTitle($key, $score),
                'priority' => $priority,
                'category' => $this->recommendationCategory($key),
                'why' => $risks[0] ?? $component['why_it_matters'] ?? 'This workflow needs attention.',
                'action' => $actions[0] ?? 'Review this workflow and choose the next coach action.',
                'source_component' => $key,
                'button_label' => null,
                'action_type' => null,
            ];
        }

        return collect($recommendations)
            ->sortByDesc(fn (array $row): int => $this->priorityRank((string) ($row['priority'] ?? 'low')))
            ->values()
            ->all();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int}
     */
    private function dateWindow(array $options): array
    {
        $days = max(1, min(365, (int) ($options['days'] ?? 30)));
        $end = ! empty($options['end_date'])
            ? CarbonImmutable::parse((string) $options['end_date'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $start = ! empty($options['start_date'])
            ? CarbonImmutable::parse((string) $options['start_date'])->startOfDay()
            : $end->subDays($days - 1)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return [$start, $end, max(1, $start->diffInDays($end) + 1)];
    }

    /**
     * @return array<string, mixed>
     */
    private function component(string $key, mixed $score, string $headline, string $why, array $evidence, array $risks, array $actions): array
    {
        $numericScore = is_numeric($score) ? round((float) $score, 1) : null;

        return [
            'score_0_100' => $numericScore,
            'label' => $numericScore === null ? 'no_data' : $this->overallLabel($numericScore),
            'weight' => self::DEFAULT_WEIGHTS[$key] ?? 0,
            'headline' => $headline,
            'why_it_matters' => $why,
            'evidence' => $evidence,
            'risks' => array_values(array_filter($risks)),
            'recommended_actions' => array_values(array_filter($actions)),
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

    /**
     * @param array<string, array<string, mixed>> $components
     */
    private function weightedAverage(array $components): ?float
    {
        $weighted = 0.0;
        $weight = 0.0;

        foreach ($components as $component) {
            if (! is_numeric($component['score_0_100'] ?? null)) {
                continue;
            }

            $componentWeight = (float) ($component['weight'] ?? 0);
            $weighted += (float) $component['score_0_100'] * $componentWeight;
            $weight += $componentWeight;
        }

        if ($weight <= 0.0) {
            return null;
        }

        return round($weighted / $weight, 1);
    }

    private function overallLabel(?float $score): string
    {
        return match (true) {
            $score === null => 'no_data',
            $score >= 90.0 => 'elite',
            $score >= 75.0 => 'strong',
            $score >= 60.0 => 'stable',
            $score >= 40.0 => 'needs_attention',
            $score > 0.0 => 'at_risk',
            default => 'no_data',
        };
    }

    private function percent(int|float $part, int|float $total): float
    {
        if ((float) $total <= 0.0) {
            return 0.0;
        }

        return round(((float) $part / (float) $total) * 100, 1);
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

    private function ageInDays(mixed $date): ?int
    {
        if (! $date) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $date)->startOfDay()->diffInDays(CarbonImmutable::now()->startOfDay());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $components
     * @return array<int, array<string, mixed>>
     */
    private function strengths(array $components): array
    {
        return collect($components)
            ->filter(fn (array $component): bool => is_numeric($component['score_0_100'] ?? null) && (float) $component['score_0_100'] >= 75.0)
            ->sortByDesc('score_0_100')
            ->map(fn (array $component, string $key): array => [
                'component' => $key,
                'title' => $component['headline'] ?? $this->human($key),
                'score_0_100' => $component['score_0_100'],
                'why' => $component['why_it_matters'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, array<string, mixed>> $components
     * @return array<int, array<string, mixed>>
     */
    private function risks(array $components, array $warnings): array
    {
        $risks = collect($components)
            ->filter(fn (array $component): bool => ! is_numeric($component['score_0_100'] ?? null) || (float) $component['score_0_100'] < 60.0)
            ->sortBy(fn (array $component): float => is_numeric($component['score_0_100'] ?? null) ? (float) $component['score_0_100'] : -1.0)
            ->map(fn (array $component, string $key): array => [
                'component' => $key,
                'title' => $component['headline'] ?? $this->human($key),
                'score_0_100' => $component['score_0_100'] ?? null,
                'risk' => Arr::wrap($component['risks'] ?? [])[0] ?? 'This workflow does not have enough usable data yet.',
            ])
            ->values()
            ->all();

        foreach ($warnings as $warning) {
            $risks[] = [
                'component' => 'system_warning',
                'title' => 'Health Input Warning',
                'score_0_100' => null,
                'risk' => $warning,
            ];
        }

        return $risks;
    }

    /**
     * @param array<int, array<string, mixed>> $strengths
     * @param array<int, array<string, mixed>> $risks
     * @param array<int, array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    private function summary(?float $score, string $label, array $strengths, array $risks, array $actions): array
    {
        $scoreText = $score === null ? 'No Data' : number_format($score, 1);
        $primaryStrength = $strengths[0]['title'] ?? null;
        $primaryRisk = $risks[0]['title'] ?? null;
        $nextAction = $actions[0]['title'] ?? 'Keep collecting operating data';

        return [
            'headline' => 'Development Program Health: '.$scoreText.' — '.$this->human($label),
            'summary_text' => $score === null
                ? 'Program health will appear after plans, player progress, benchmark data, and communication history are collected.'
                : 'FMTRX combined planner execution, player completion, benchmark coverage, review flow, trusted data growth, and communication rhythm.',
            'primary_strength' => $primaryStrength,
            'primary_risk' => $primaryRisk,
            'next_best_action' => $nextAction,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $components
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function trendSignals(array $components, array $data): array
    {
        $signals = [];
        $completion = Arr::get($data, 'weekly_rollup.plan_execution_summary.average_completion_percentage');
        if (is_numeric($completion)) {
            $signals[] = [
                'type' => (float) $completion >= 70.0 ? 'stable' : ((float) $completion <= 35.0 ? 'declining' : 'unknown'),
                'label' => 'Player Completion',
                'message' => 'Current player completion is '.number_format((float) $completion, 1).'%.',
                'evidence' => ['average_completion_percentage' => round((float) $completion, 1)],
            ];
        }

        $trustedValues = Arr::get($data, 'weekly_rollup.trusted_data_summary.trusted_values_added');
        if (is_numeric($trustedValues) && (int) $trustedValues > 0) {
            $signals[] = [
                'type' => 'improving',
                'label' => 'Trusted Data Growth',
                'message' => (int) $trustedValues.' trusted value(s) were added in this window.',
                'evidence' => ['trusted_values_added' => (int) $trustedValues],
            ];
        }

        $communicationScore = $components['communication_rhythm']['score_0_100'] ?? null;
        if (is_numeric($communicationScore)) {
            $signals[] = [
                'type' => (float) $communicationScore >= 70.0 ? 'stable' : 'declining',
                'label' => 'Communication Rhythm',
                'message' => 'Communication rhythm score is '.number_format((float) $communicationScore, 1).'.',
                'evidence' => ['communication_rhythm_score' => round((float) $communicationScore, 1)],
            ];
        }

        if (empty($signals)) {
            $signals[] = [
                'type' => 'unknown',
                'label' => 'Operating Trend',
                'message' => 'More planner and completion history is needed before FMTRX can describe a trend.',
                'evidence' => [],
            ];
        }

        return $signals;
    }

    private function priorityForComponent(mixed $score, string $key): string
    {
        if (! is_numeric($score)) {
            return in_array($key, ['planning_consistency', 'benchmark_coverage'], true) ? 'high' : 'medium';
        }

        return match (true) {
            (float) $score < 40.0 => 'critical',
            (float) $score < 60.0 => 'high',
            (float) $score < 75.0 => 'medium',
            default => 'low',
        };
    }

    private function recommendationTitle(string $key, mixed $score): string
    {
        return match ($key) {
            'planning_consistency' => is_numeric($score) ? 'Tighten Planning Rhythm' : 'Create the First Operating Plan',
            'player_completion' => 'Improve Player Completion',
            'benchmark_coverage' => 'Improve Benchmark Coverage',
            'coach_review_flow' => 'Clear Coach Review Queue',
            'trusted_data_growth' => 'Convert Submissions to Trusted Data',
            'communication_rhythm' => 'Improve Communication Rhythm',
            default => 'Review Program Health',
        };
    }

    private function recommendationCategory(string $key): string
    {
        return match ($key) {
            'planning_consistency' => 'planning',
            'player_completion' => 'completion',
            'benchmark_coverage' => 'benchmark',
            'coach_review_flow' => 'review',
            'trusted_data_growth' => 'trusted_data',
            'communication_rhythm' => 'communication',
            default => 'planning',
        };
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
