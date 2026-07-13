<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\BenchmarkCollectionTask;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use App\Services\Intelligence\BenchmarkTaskReviewService;
use App\Services\Intelligence\PopulationLearningAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

class DevelopmentHealthAlertService
{
    private const COMPONENT_LABELS = [
        'planning_consistency' => 'Planning Consistency',
        'player_completion' => 'Player Completion',
        'benchmark_coverage' => 'Benchmark Coverage',
        'coach_review_flow' => 'Coach Review Flow',
        'trusted_data_growth' => 'Trusted Data Growth',
        'communication_rhythm' => 'Communication Rhythm',
    ];

    public function __construct(
        private readonly DevelopmentProgramHealthService $developmentProgramHealthService,
        private readonly DevelopmentHealthTrendService $developmentHealthTrendService,
        private readonly BenchmarkTaskReviewService $benchmarkTaskReviewService,
        private readonly CommunicationRhythmService $communicationRhythmService,
        private readonly PopulationLearningAuditService $populationLearningAuditService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTeamAlerts(string $teamId, array $options = []): array
    {
        [$start, $end, $days, $weeks] = $this->dateWindow($options);
        $warnings = [];

        try {
            $trendline = $this->safe(
                fn (): array => $this->developmentHealthTrendService->buildTeamTrendline($teamId, [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'weeks' => $weeks,
                    'period' => 'week',
                    'include_components' => true,
                    'include_recommendations' => true,
                ]),
                [],
                $warnings,
                'Development health trendline',
            );
            $health = $this->healthSnapshotFromTrendline($teamId, $start, $end, $trendline);

            $alerts = [
                ...$this->evaluateHealthDrop($health, $trendline),
                ...$this->evaluateComponentAlerts($health, $trendline),
                ...$this->evaluateReviewQueueAlerts($teamId, ['days' => $days]),
                ...$this->evaluateBenchmarkCoverageAlerts($teamId, [
                    'health' => $health,
                    'trendline' => $trendline,
                ]),
                ...$this->evaluateCommunicationAlerts($teamId, [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'weeks' => $weeks,
                    'health' => $health,
                    'trendline' => $trendline,
                ]),
                ...$this->evaluateMissedWorkAlerts($teamId, $start, $end),
                ...($this->bool($options['include_population_learning'] ?? true) ? $this->evaluatePopulationLearningAlerts([
                    'days' => $days,
                    'metric_key' => $options['population_metric_key'] ?? 'max_exit_velocity',
                ]) : []),
            ];

            $alerts = $this->filterAlerts($alerts, (string) ($options['severity_threshold'] ?? 'medium'));
            $counts = $this->alertCounts($alerts);
            $highest = $alerts[0] ?? [];
            $recommendations = $this->buildAlertRecommendations($alerts);
            $summary = $this->summary($alerts, $counts, $recommendations);

            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'alert_status' => empty($alerts) ? 'none' : (empty($warnings) ? 'active' : 'partial'),
                'summary' => $summary,
                'alerts' => $alerts,
                'alert_counts' => $counts,
                'highest_priority_alert' => $highest,
                'recommended_actions' => $recommendations,
                'warnings' => array_values(array_unique(array_filter([
                    ...$warnings,
                    ...Arr::wrap($health['warnings'] ?? []),
                    ...Arr::wrap($trendline['warnings'] ?? []),
                ]))),
                'evidence' => [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'days' => $days,
                    'weeks' => $weeks,
                    'severity_threshold' => (string) ($options['severity_threshold'] ?? 'medium'),
                    'include_resolved' => $this->bool($options['include_resolved'] ?? false),
                    'source' => 'development_health_score_trendline_planner_review_communication_population_audit',
                    'data_is_persisted' => false,
                    'notifications_sent' => false,
                ],
            ];
        } catch (Throwable $exception) {
            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'alert_status' => 'failed',
                'summary' => [
                    'headline' => 'Health alerts are not available yet.',
                    'active_alert_count' => 0,
                    'critical_count' => 0,
                    'high_count' => 0,
                    'medium_count' => 0,
                    'low_count' => 0,
                    'next_best_action' => null,
                ],
                'alerts' => [],
                'alert_counts' => $this->alertCounts([]),
                'highest_priority_alert' => [],
                'recommended_actions' => [],
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'exception' => class_basename($exception),
                    'data_is_persisted' => false,
                    'notifications_sent' => false,
                ],
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function evaluateHealthDrop(array $health, array $trendline): array
    {
        $overall = Arr::wrap($trendline['overall_trend'] ?? []);
        $delta = $this->numberOrNull($overall['score_delta_vs_previous'] ?? null);
        $current = $this->numberOrNull($overall['current_score'] ?? $health['overall_score_0_100'] ?? null);
        $previous = $this->numberOrNull($overall['previous_score'] ?? null);

        if ($delta === null || $delta > -5.0) {
            return [];
        }

        $drop = abs($delta);
        $severity = $drop >= 20.0 ? 'critical' : ($drop >= 10.0 ? 'high' : 'medium');

        return [[
            ...$this->alert(
                'health_drop_overall',
                'health_drop',
                $severity,
                'Development Health Dropped',
                'Program health dropped from '.$this->value($previous).' to '.$this->value($current).' this period.',
                'This usually means planning, completion, review flow, or communication rhythm needs attention.',
                'Review the biggest declining component and take the highest-priority action.',
                'overall',
                $current,
                $previous,
                $delta,
                'health_score',
            ),
            'evidence' => [
                'trend_direction' => $overall['trend_direction'] ?? null,
                'score_delta_vs_previous' => $delta,
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function evaluateComponentAlerts(array $health, array $trendline): array
    {
        $alerts = [];
        $components = Arr::wrap($health['score_components'] ?? []);
        $trends = Arr::wrap($trendline['component_trends'] ?? []);

        $planning = Arr::wrap($components['planning_consistency'] ?? []);
        $planningScore = $this->numberOrNull($planning['score_0_100'] ?? null);
        $planningTrend = Arr::wrap($trends['planning_consistency'] ?? []);
        $planningDelta = $this->numberOrNull($planningTrend['delta'] ?? null);
        $publishedThisWeek = $this->publishedPlansThisWeek((string) ($health['team_id'] ?? ''), $health['end_date'] ?? null);
        if ($publishedThisWeek <= 0 || ($planningScore !== null && $planningScore < 40.0) || ($planningDelta !== null && $planningDelta <= -10.0)) {
            $alerts[] = [
                ...$this->alert(
                    'planning_gap',
                    'planning_gap',
                    $publishedThisWeek <= 0 ? 'high' : 'medium',
                    $publishedThisWeek <= 0 ? 'No Active Plan Published' : 'Planning Consistency Dropped',
                    $publishedThisWeek <= 0 ? 'No published plan is recorded for the current week.' : 'Planning consistency is below the healthy operating range.',
                    'A published plan is the bridge between FMTRX intelligence and player action.',
                    'Generate or publish this week’s plan.',
                    'planning_consistency',
                    $planningScore,
                    $this->numberOrNull($planningTrend['previous_score'] ?? null),
                    $planningDelta,
                    'planner',
                ),
                'evidence' => [
                    'published_plans_current_week' => $publishedThisWeek,
                    'component_evidence' => $planning['evidence'] ?? [],
                ],
            ];
        }

        $completion = Arr::wrap($components['player_completion'] ?? []);
        $completionScore = $this->numberOrNull($completion['score_0_100'] ?? null);
        $completionEvidence = Arr::wrap($completion['evidence'] ?? []);
        $completionPct = $this->numberOrNull($completionEvidence['average_completion_percentage'] ?? null);
        $completionTrend = Arr::wrap($trends['player_completion'] ?? []);
        $completionDelta = $this->numberOrNull($completionTrend['delta'] ?? null);
        $followUpCount = (int) ($completionEvidence['players_needing_follow_up_count'] ?? 0);
        if (($completionDelta !== null && $completionDelta <= -10.0) || ($completionPct !== null && $completionPct < 50.0) || ($completionScore !== null && $completionScore < 50.0) || $followUpCount >= 3) {
            $severity = ($completionPct !== null && $completionPct < 35.0) || $followUpCount >= 5 ? 'high' : 'medium';
            $alerts[] = [
                ...$this->alert(
                    'completion_drop',
                    'completion_drop',
                    $severity,
                    'Player Completion Dropped',
                    $completionPct !== null ? 'Average player completion is '.$completionPct.'%.' : 'Player completion needs follow-up.',
                    'Completion rate tells the coach whether the plan is becoming behavior, not just a document.',
                    'Follow up with players who have not started or simplify the next plan.',
                    'player_completion',
                    $completionPct ?? $completionScore,
                    $this->numberOrNull($completionTrend['previous_score'] ?? null),
                    $completionDelta,
                    'planner',
                ),
                'evidence' => $completionEvidence,
            ];
        }

        $trusted = Arr::wrap($components['trusted_data_growth'] ?? []);
        $trustedScore = $this->numberOrNull($trusted['score_0_100'] ?? null);
        $trustedEvidence = Arr::wrap($trusted['evidence'] ?? []);
        $trustedTrend = Arr::wrap($trends['trusted_data_growth'] ?? []);
        $trustedDelta = $this->numberOrNull($trustedTrend['delta'] ?? null);
        $approved = (int) ($trustedEvidence['approved_metric_values'] ?? 0);
        $trustedValues = (int) ($trustedEvidence['trusted_values_added'] ?? 0);
        if (($trustedScore !== null && $trustedScore < 50.0) || ($approved > 0 && $trustedValues <= 0)) {
            $alerts[] = [
                ...$this->alert(
                    'trusted_data_stall',
                    'trusted_data_stall',
                    $approved >= 5 ? 'high' : 'medium',
                    'Trusted Data Growth Is Stalled',
                    $approved > 0 ? $approved.' approved value(s) are not reflected as trusted data growth.' : 'Trusted data growth is below the healthy range.',
                    'Trusted data is what lets FMTRX benchmarks and recommendations improve without blindly accepting raw entries.',
                    'Promote approved values and refresh FMTRX intelligence.',
                    'trusted_data_growth',
                    $trustedScore,
                    $this->numberOrNull($trustedTrend['previous_score'] ?? null),
                    $trustedDelta,
                    'benchmark',
                ),
                'metrics' => Arr::wrap($trustedEvidence['metrics_improved'] ?? []),
                'evidence' => $trustedEvidence,
            ];
        }

        return $alerts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function evaluateReviewQueueAlerts(string $teamId, array $options = []): array
    {
        $warnings = [];
        $summary = $this->safe(fn (): array => $this->benchmarkTaskReviewService->buildTeamReviewSummary($teamId), [], $warnings, 'Benchmark review summary');
        $pending = (int) ($summary['pending_count'] ?? 0);
        if ($pending <= 0) {
            return [];
        }

        $oldest = collect(Arr::wrap($summary['pending_tasks'] ?? []))
            ->map(fn (array $task): ?string => $task['submitted_at'] ?? $task['updated_at'] ?? null)
            ->filter()
            ->sort()
            ->first();
        $oldestDays = $this->ageInDays($oldest);
        $severity = ($oldestDays !== null && $oldestDays > 7) || $pending >= 10
            ? 'critical'
            : ((($oldestDays !== null && $oldestDays > 3) || $pending >= 5) ? 'high' : 'medium');

        return [[
            ...$this->alert(
                'review_queue_pending',
                'review_queue',
                $severity,
                'Benchmark Reviews Need Attention',
                $pending.' submitted benchmark value'.($pending === 1 ? ' is' : 's are').' pending coach review.',
                'Fast review turns player submissions into useful coaching data without trusting unverified values.',
                'Approve, reject, or request corrections for submitted benchmark values.',
                'coach_review_flow',
                $pending,
                null,
                null,
                'review',
            ),
            'evidence' => [
                'pending_review_count' => $pending,
                'oldest_pending_at' => $oldest,
                'oldest_pending_days' => $oldestDays,
                'review_summary_success' => (bool) ($summary['ok'] ?? true),
                'days' => (int) ($options['days'] ?? 30),
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function evaluateBenchmarkCoverageAlerts(string $teamId, array $options = []): array
    {
        $health = Arr::wrap($options['health'] ?? []);
        $trendline = Arr::wrap($options['trendline'] ?? []);
        $coverage = Arr::wrap(Arr::get($health, 'score_components.benchmark_coverage', []));
        $evidence = Arr::wrap($coverage['evidence'] ?? []);
        $score = $this->numberOrNull($coverage['score_0_100'] ?? null);
        $coveragePct = $this->numberOrNull($evidence['coverage_percentage'] ?? null);
        $missing = (int) ($evidence['missing_metric_count'] ?? 0);
        $playersWithoutData = (int) ($evidence['players_without_benchmark_metrics'] ?? 0);
        $trend = Arr::wrap(Arr::get($trendline, 'component_trends.benchmark_coverage', []));
        $delta = $this->numberOrNull($trend['delta'] ?? null);

        if (($score === null || $score >= 50.0) && ($coveragePct === null || $coveragePct >= 50.0) && $missing <= 0) {
            return [];
        }

        $stalling = $delta !== null && abs($delta) < 1.0;
        $severity = ($coveragePct !== null && $coveragePct < 25.0) || $playersWithoutData >= 5 ? 'high' : 'medium';

        return [[
            ...$this->alert(
                'benchmark_coverage_stall',
                $playersWithoutData > 0 ? 'missing_baselines' : 'benchmark_coverage',
                $severity,
                $playersWithoutData > 0 ? 'Baseline Data Still Missing' : 'Benchmark Coverage Is Stalling',
                $coveragePct !== null ? 'Benchmark coverage is '.$coveragePct.'% for this team.' : 'Benchmark coverage needs more usable player baselines.',
                'Benchmark coverage determines how confidently FMTRX can compare players and choose next actions.',
                $playersWithoutData > 0 ? 'Schedule a Benchmark Baseline day.' : 'Add roster cleanup and baseline collection blocks to the next plan.',
                'benchmark_coverage',
                $coveragePct ?? $score,
                $this->numberOrNull($trend['previous_score'] ?? null),
                $delta,
                'benchmark',
            ),
            'evidence' => [
                ...$evidence,
                'team_id' => $teamId,
                'coverage_stalling' => $stalling,
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function evaluateCommunicationAlerts(string $teamId, array $options = []): array
    {
        $warnings = [];
        $health = Arr::wrap($options['health'] ?? []);
        $trendline = Arr::wrap($options['trendline'] ?? []);
        $component = Arr::wrap(Arr::get($health, 'score_components.communication_rhythm', []));
        $score = $this->numberOrNull($component['score_0_100'] ?? null);
        $trend = Arr::wrap(Arr::get($trendline, 'component_trends.communication_rhythm', []));
        $delta = $this->numberOrNull($trend['delta'] ?? null);
        $rhythm = $this->safe(fn (): array => $this->communicationRhythmService->buildTeamRhythm($teamId, [
            'start_date' => $options['start_date'] ?? null,
            'end_date' => $options['end_date'] ?? null,
            'weeks' => (int) ($options['weeks'] ?? 8),
        ]), [], $warnings, 'Communication rhythm');
        $latestWeek = Arr::wrap(Arr::wrap($rhythm['weekly_rows'] ?? [])[0] ?? []);
        $streaks = Arr::wrap($rhythm['streaks'] ?? []);
        $currentStreak = (int) ($streaks['current_any_report_streak'] ?? 0);
        $missedWeeks = count(Arr::wrap($rhythm['missed_weeks'] ?? []));
        $noCurrentReport = $latestWeek !== [] && ! (bool) ($latestWeek['has_any_report'] ?? false);

        if (($delta === null || $delta > -10.0) && ! $noCurrentReport && $currentStreak > 0 && ($score === null || $score >= 50.0)) {
            return [];
        }

        $severity = $currentStreak <= 0 && $missedWeeks >= 3 ? 'high' : 'medium';

        return [[
            ...$this->alert(
                'communication_rhythm_drop',
                'communication_drop',
                $severity,
                'Communication Rhythm Dropped',
                $noCurrentReport ? 'No weekly development update is recorded for the current week.' : 'Communication rhythm is below the healthy range.',
                'Weekly sharing keeps coaches, staff, players, and families aligned on development work.',
                'Create a Parent Update or Staff Report from the weekly rollup.',
                'communication_rhythm',
                $score,
                $this->numberOrNull($trend['previous_score'] ?? null),
                $delta,
                'communication',
            ),
            'evidence' => [
                'latest_week' => $latestWeek,
                'current_any_report_streak' => $currentStreak,
                'missed_week_count' => $missedWeeks,
                'warnings' => $warnings ?? [],
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildAlertRecommendations(array $alerts): array
    {
        return collect($alerts)
            ->map(fn (array $alert): array => [
                'title' => $this->actionTitle((string) ($alert['type'] ?? 'none')),
                'priority' => $this->priority((string) ($alert['severity'] ?? 'medium')),
                'why' => (string) ($alert['message'] ?? 'Development health needs attention.'),
                'action' => (string) ($alert['recommended_action'] ?? 'Review the alert and choose the next safe coach action.'),
                'action_type' => $this->actionType((string) ($alert['type'] ?? 'none')),
                'related_alert_ids' => [(string) ($alert['alert_id'] ?? '')],
            ])
            ->filter(fn (array $action): bool => $action['related_alert_ids'][0] !== '')
            ->unique(fn (array $action): string => (string) $action['action_type'].'|'.(string) $action['title'])
            ->sortBy(fn (array $action): int => $this->severityRank((string) $action['priority']))
            ->values()
            ->take(5)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function evaluateMissedWorkAlerts(string $teamId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $plans = DailyPlan::query()
            ->with(['assignments.user.profile', 'progress'])
            ->where('team_id', $teamId)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('status', '!=', 'template')
            ->get();

        $misses = [];
        foreach ($plans as $plan) {
            $progressByUser = $plan->progress->keyBy(fn (DailyPlanProgress $progress): string => (string) $progress->user_id);
            foreach ($plan->assignments as $assignment) {
                $playerId = (string) $assignment->user_id;
                $progress = $progressByUser->get($playerId);
                $started = $progress?->started_at !== null || $progress?->completed_at !== null || $this->progressItemCount($progress) > 0;
                if ($started) {
                    continue;
                }
                $misses[$playerId] ??= [
                    'player_id' => $playerId,
                    'player_name' => $this->playerName($assignment),
                    'missed_count' => 0,
                ];
                $misses[$playerId]['missed_count']++;
            }
        }

        $repeatMisses = collect($misses)
            ->filter(fn (array $row): bool => (int) ($row['missed_count'] ?? 0) >= 2)
            ->sortByDesc('missed_count')
            ->values()
            ->all();

        if (empty($repeatMisses)) {
            return [];
        }

        return [[
            ...$this->alert(
                'repeated_missed_work',
                'missed_work',
                count($repeatMisses) >= 3 ? 'high' : 'medium',
                'Repeated Missed Work',
                count($repeatMisses).' player'.(count($repeatMisses) === 1 ? ' has' : 's have').' missed multiple assigned plans in this period.',
                'Repeated missed work means the development plan may not be reaching the player or may need to be simplified.',
                'Check in with players missing assigned development work.',
                'player_completion',
                count($repeatMisses),
                null,
                null,
                'planner',
            ),
            'players' => array_slice($repeatMisses, 0, 8),
            'evidence' => [
                'plan_count' => $plans->count(),
                'repeat_missed_player_count' => count($repeatMisses),
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function evaluatePopulationLearningAlerts(array $options): array
    {
        $warnings = [];
        $audit = $this->safe(fn (): array => $this->populationLearningAuditService->buildAuditReport([
            'days' => (int) ($options['days'] ?? 30),
            'metric_key' => (string) ($options['metric_key'] ?? 'max_exit_velocity'),
        ]), [], $warnings, 'Population learning audit');
        $guardrail = Arr::wrap($audit['guardrail_summary'] ?? []);
        $trusted = Arr::wrap($audit['trusted_task_summary'] ?? []);
        $excludedRate = $this->numberOrNull($guardrail['exclusion_rate'] ?? null);
        $trustedExcluded = (int) ($trusted['trusted_task_values_excluded'] ?? 0);
        $auditWarnings = Arr::wrap($audit['warnings'] ?? []);
        $hasMappingWarning = collect(Arr::wrap($audit['metrics'] ?? []))
            ->contains(fn (array $metric): bool => in_array('missing_metric_mapping', Arr::wrap($metric['qa_flags'] ?? []), true));

        if (($excludedRate === null || $excludedRate < 40.0) && $trustedExcluded <= 0 && empty($auditWarnings) && ! $hasMappingWarning) {
            return [];
        }

        return [[
            ...$this->alert(
                'population_learning_qa',
                'population_learning_qa',
                ($excludedRate !== null && $excludedRate >= 60.0) || $hasMappingWarning ? 'high' : 'medium',
                'Population Learning Data Quality Needs Review',
                $excludedRate !== null ? 'Population guardrails excluded '.$excludedRate.'% of audited values.' : 'Population learning returned data-quality warnings.',
                'Guardrails keep bad placeholders, impossible values, or broken mappings from polluting FMTRX percentiles.',
                'Review guardrail exclusions and clean collection workflow.',
                null,
                $excludedRate,
                null,
                null,
                'population_learning',
            ),
            'metrics' => collect(Arr::wrap($audit['metrics'] ?? []))->pluck('display_name')->filter()->take(5)->values()->all(),
            'evidence' => [
                'guardrail_summary' => $guardrail,
                'trusted_task_summary' => $trusted,
                'warnings' => array_values(array_unique(array_filter([
                    ...$warnings,
                    ...$auditWarnings,
                ]))),
            ],
        ]];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int, 3: int}
     */
    private function dateWindow(array $options): array
    {
        $days = max(7, min(365, (int) ($options['days'] ?? 30)));
        $weeks = max(1, min(52, (int) ($options['weeks'] ?? 8)));
        $end = ! empty($options['end_date'])
            ? CarbonImmutable::parse((string) $options['end_date'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $start = ! empty($options['start_date'])
            ? CarbonImmutable::parse((string) $options['start_date'])->startOfDay()
            : $end->subDays($days - 1)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        $days = max(1, $start->diffInDays($end) + 1);

        return [$start, $end, $days, $weeks];
    }

    /**
     * @return array<string, mixed>
     */
    private function healthSnapshotFromTrendline(string $teamId, CarbonImmutable $start, CarbonImmutable $end, array $trendline): array
    {
        $components = [];
        foreach (Arr::wrap($trendline['component_trends'] ?? []) as $key => $trend) {
            if (! is_array($trend)) {
                continue;
            }

            $components[(string) $key] = [
                'key' => (string) $key,
                'score_0_100' => $this->numberOrNull($trend['current_score'] ?? null),
                'label' => $trend['display_name'] ?? (self::COMPONENT_LABELS[(string) $key] ?? $this->human((string) $key)),
                'why_it_matters' => $trend['summary'] ?? null,
                'evidence' => [
                    'previous_score' => $this->numberOrNull($trend['previous_score'] ?? null),
                    'delta' => $this->numberOrNull($trend['delta'] ?? null),
                    'trend_direction' => $trend['trend_direction'] ?? null,
                ],
                'warnings' => [],
                'recommended_actions' => [],
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'overall_score_0_100' => $this->numberOrNull(Arr::get($trendline, 'overall_trend.current_score')),
            'overall_label' => Arr::get($trendline, 'period_scores.'.max(0, count(Arr::wrap($trendline['period_scores'] ?? [])) - 1).'.overall_label'),
            'score_components' => $components,
            'warnings' => Arr::wrap($trendline['warnings'] ?? []),
            'evidence' => [
                'source' => 'development_health_trendline_current_period',
                'data_is_persisted' => false,
            ],
        ];
    }

    private function alert(
        string $id,
        string $type,
        string $severity,
        string $title,
        string $message,
        string $why,
        string $action,
        ?string $component,
        mixed $current,
        mixed $previous,
        ?float $delta,
        string $source,
    ): array {
        return [
            'alert_id' => $id,
            'type' => $type,
            'severity' => $this->priority($severity),
            'title' => $title,
            'message' => $message,
            'why_it_matters' => $why,
            'recommended_action' => $action,
            'component' => $component,
            'current_value' => $current,
            'previous_value' => $previous,
            'delta' => $delta,
            'players' => [],
            'metrics' => [],
            'source' => $source,
            'status' => 'active',
            'created_at' => now()->toIso8601String(),
            'evidence' => [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     * @return array<int, array<string, mixed>>
     */
    private function filterAlerts(array $alerts, string $threshold): array
    {
        $minimum = $this->severityRank($this->priority($threshold));

        return collect($alerts)
            ->filter(fn (array $alert): bool => $this->severityRank((string) ($alert['severity'] ?? 'low')) <= $minimum)
            ->unique(fn (array $alert): string => (string) ($alert['alert_id'] ?? ''))
            ->sortBy(fn (array $alert): int => $this->severityRank((string) ($alert['severity'] ?? 'low')))
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     * @return array<string, mixed>
     */
    private function alertCounts(array $alerts): array
    {
        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        $byType = [];
        $byComponent = [];

        foreach ($alerts as $alert) {
            $severity = $this->priority((string) ($alert['severity'] ?? 'low'));
            $counts[$severity]++;
            $type = (string) ($alert['type'] ?? 'unknown');
            $byType[$type] = ($byType[$type] ?? 0) + 1;
            $component = (string) ($alert['component'] ?? '');
            if ($component !== '') {
                $byComponent[$component] = ($byComponent[$component] ?? 0) + 1;
            }
        }

        return [
            ...$counts,
            'by_type' => $byType,
            'by_component' => $byComponent,
        ];
    }

    private function summary(array $alerts, array $counts, array $actions): array
    {
        $active = count($alerts);
        $highest = $alerts[0] ?? null;
        $headline = $active <= 0
            ? 'No development health alerts right now.'
            : $active.' development health alert'.($active === 1 ? '' : 's').' need attention.';

        return [
            'headline' => $headline,
            'active_alert_count' => $active,
            'critical_count' => (int) ($counts['critical'] ?? 0),
            'high_count' => (int) ($counts['high'] ?? 0),
            'medium_count' => (int) ($counts['medium'] ?? 0),
            'low_count' => (int) ($counts['low'] ?? 0),
            'next_best_action' => $actions[0]['action'] ?? $highest['recommended_action'] ?? null,
        ];
    }

    private function actionTitle(string $type): string
    {
        return match ($type) {
            'review_queue' => 'Review Submissions',
            'planning_gap' => 'Publish Plan',
            'completion_drop', 'missed_work' => 'Send Player Follow-Up',
            'benchmark_coverage', 'missing_baselines' => 'Collect Baselines',
            'trusted_data_stall' => 'Promote Trusted Data',
            'communication_drop' => 'Send Weekly Update',
            'population_learning_qa' => 'Review Population QA',
            default => 'Review Development Health',
        };
    }

    private function actionType(string $type): string
    {
        return match ($type) {
            'review_queue' => 'review_submissions',
            'planning_gap' => 'publish_plan',
            'completion_drop', 'missed_work' => 'send_reminder',
            'benchmark_coverage', 'missing_baselines' => 'collect_baselines',
            'trusted_data_stall' => 'promote_data',
            'communication_drop' => 'send_report',
            'health_drop' => 'none',
            'population_learning_qa' => 'none',
            default => 'none',
        };
    }

    private function publishedPlansThisWeek(string $teamId, mixed $referenceDate): int
    {
        if ($teamId === '') {
            return 0;
        }

        $date = $referenceDate ? CarbonImmutable::parse((string) $referenceDate) : CarbonImmutable::now();

        return DailyPlan::query()
            ->where('team_id', $teamId)
            ->where('status', 'published')
            ->whereDate('date', '>=', $date->startOfWeek()->toDateString())
            ->whereDate('date', '<=', $date->endOfWeek()->toDateString())
            ->count();
    }

    private function progressItemCount(?DailyPlanProgress $progress): int
    {
        return $progress && is_array($progress->items) ? count($progress->items) : 0;
    }

    private function playerName(DailyPlanAssignment $assignment): string
    {
        $profile = $assignment->user?->profile;
        $name = trim((string) (($profile?->first_name ?? '').' '.($profile?->last_name ?? '')));

        return $name !== '' ? $name : 'Player '.$assignment->user_id;
    }

    private function ageInDays(?string $date): ?int
    {
        if (! $date) {
            return null;
        }

        try {
            return CarbonImmutable::parse($date)->diffInDays(CarbonImmutable::now());
        } catch (Throwable) {
            return null;
        }
    }

    private function severityRank(string $severity): int
    {
        return [
            'critical' => 0,
            'high' => 1,
            'medium' => 2,
            'low' => 3,
        ][$this->priority($severity)] ?? 3;
    }

    private function priority(string $priority): string
    {
        return in_array($priority, ['critical', 'high', 'medium', 'low'], true) ? $priority : 'medium';
    }

    private function value(mixed $value): string
    {
        return is_numeric($value) ? (string) round((float) $value, 1) : '—';
    }

    private function human(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?: 'unknown'));
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    /**
     * @param callable(): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function safe(callable $callback, array $fallback, array &$warnings, string $label): array
    {
        try {
            $result = $callback();

            return is_array($result) ? $result : $fallback;
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
}
