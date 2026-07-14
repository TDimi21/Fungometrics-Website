<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\DailyPlan;
use App\Models\BenchmarkCollectionTask;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Services\Intelligence\BenchmarkTaskReviewService;
use App\Services\Intelligence\BenchmarkTrustedDataPromotionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

class CoachOperatingSystemHomeService
{
    public function __construct(
        private readonly CoachPlannerCommandCenterService $commandCenterService,
        private readonly DevelopmentProgramHealthService $healthService,
        private readonly DevelopmentHealthAlertService $alertService,
        private readonly DevelopmentHealthAlertActionService $alertActionService,
        private readonly BenchmarkTaskReviewService $reviewService,
        private readonly BenchmarkTrustedDataPromotionService $promotionService,
        private readonly CommunicationRhythmService $communicationRhythmService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildHome(string $teamId, array $options = []): array
    {
        $options = $this->normalizeOptions($options);
        $warnings = [];
        $context = $this->buildContext($teamId, $options, $warnings);

        $todayPlan = $this->todayPlanFromContext($teamId, $context, $options);
        $nextActionStack = $this->buildNextActionStack($teamId, $context);
        $primaryNextAction = $nextActionStack[0] ?? $this->noActionNeeded();
        $operatingStatus = $this->buildOperatingStatus([
            ...$context,
            'today_plan' => $todayPlan,
            'primary_next_action' => $primaryNextAction,
        ]);
        $summary = $this->operatingSummary($teamId, $context, $todayPlan, $primaryNextAction, $operatingStatus);

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'team_name' => $this->teamName($teamId),
            'date' => $options['date'],
            'home_status' => $operatingStatus['home_status'],
            'operating_summary' => $summary,
            'today_plan' => $todayPlan,
            'primary_next_action' => $primaryNextAction,
            'next_action_stack' => $nextActionStack,
            'health_snapshot' => $this->healthSnapshot($context),
            'alerts_snapshot' => $this->alertsSnapshot($context),
            'benchmark_snapshot' => $this->benchmarkSnapshot($context),
            'review_snapshot' => $this->reviewSnapshot($context),
            'planner_snapshot' => $this->plannerSnapshot($teamId, $context, $options),
            'communication_snapshot' => $this->communicationSnapshot($context),
            'player_attention' => $this->buildAttentionSummary($teamId, $context),
            'quick_links' => $this->quickLinks($teamId),
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'evidence' => [
                'source' => 'planner_health_alerts_benchmarks_reviews_communication',
                'options' => [
                    'date' => $options['date'],
                    'days' => $options['days'],
                    'weeks' => $options['weeks'],
                    'include_health' => $options['include_health'],
                    'include_alerts' => $options['include_alerts'],
                    'include_planner' => $options['include_planner'],
                    'include_benchmarks' => $options['include_benchmarks'],
                    'include_reports' => $options['include_reports'],
                ],
                'subsystems_loaded' => $this->subsystemsLoaded($context),
                'no_automatic_send_approve_trust_or_publish' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTodayCard(string $teamId, array $options = []): array
    {
        $options = $this->normalizeOptions($options);
        $warnings = [];
        $context = [
            'command_center' => $this->safe(
                fn (): array => $this->commandCenterService->buildForTeam($teamId, [
                    'days' => 365,
                    'include_benchmark_gaps' => false,
                    'include_update_suggestions' => false,
                ]),
                [],
                $warnings,
                'Planner command center',
            ),
        ];

        return $this->todayPlanFromContext($teamId, $context, $options);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildNextActionStack(string $teamId, array $context = []): array
    {
        if (empty($context)) {
            $warnings = [];
            $context = $this->buildContext($teamId, $this->normalizeOptions([]), $warnings);
        }

        $actions = [];

        foreach (Arr::wrap($context['alerts']['alerts'] ?? []) as $alert) {
            if (! is_array($alert)) {
                continue;
            }
            $alertActions = collect(Arr::wrap($context['alert_actions']['alerts'] ?? []))
                ->firstWhere('alert_id', $alert['alert_id'] ?? null);
            $action = Arr::wrap($alertActions['primary_action'] ?? [])[0] ?? ($alertActions['primary_action'] ?? []);
            $actions[] = $this->actionRow(
                (string) ($alert['title'] ?? 'Review Development Health Alert'),
                (string) ($alert['severity'] ?? 'medium'),
                'health',
                (string) ($alert['why_it_matters'] ?? $alert['message'] ?? 'A development health alert needs coach attention.'),
                (string) ($alert['recommended_action'] ?? 'Open the alert and take the recommended coach action.'),
                (string) ($action['action_type'] ?? null),
                (string) ($action['button_label'] ?? 'View Alert'),
                (string) ($action['target_section'] ?? 'development_health_alerts'),
                $action['target_route'] ?? null,
                'alerts',
                $action,
            );
        }

        foreach (Arr::wrap($context['command_center']['next_actions'] ?? []) as $action) {
            if (! is_array($action)) {
                continue;
            }
            $actions[] = $this->actionRow(
                (string) ($action['title'] ?? 'Planner Action'),
                (string) ($action['priority'] ?? 'medium'),
                'planner',
                (string) ($action['why'] ?? 'The active Daily Plan needs coach attention.'),
                (string) ($action['action'] ?? 'Open planner operations.'),
                $this->nullableString($action['action_type'] ?? null),
                $this->nullableString($action['button_label'] ?? 'Open Planner'),
                $this->sectionForAction((string) ($action['action_type'] ?? 'open_daily_planner')),
                $action['target_route'] ?? null,
                'command_center',
                $action,
            );
        }

        $review = Arr::wrap($context['review_summary']['data'] ?? $context['review_summary'] ?? []);
        if ((int) ($review['pending_count'] ?? 0) > 0) {
            $actions[] = $this->actionRow(
                'Review Submitted Benchmark Values',
                'high',
                'review',
                ((int) $review['pending_count']).' submitted value(s) are waiting for coach approval.',
                'Approve, reject, or request corrections before values become trusted data.',
                'review_submissions',
                'Review Submissions',
                'review_queue',
                null,
                'review_queue',
            );
        }

        $promotion = Arr::wrap($context['promotion_status'] ?? []);
        if ((int) ($promotion['awaiting_promotion_count'] ?? 0) > 0) {
            $actions[] = $this->actionRow(
                'Promote Approved Trusted Data',
                'high',
                'review',
                ((int) $promotion['awaiting_promotion_count']).' approved task(s) are ready to refresh benchmark intelligence.',
                'Promote approved values through the trusted data workflow.',
                'promote_trusted_data',
                'Promote Trusted Data',
                'review_queue',
                null,
                'trusted_data',
            );
        }

        $benchmarkLight = Arr::wrap($context['benchmark_light'] ?? []);
        $priority = (string) ($benchmarkLight['data_collection_priority'] ?? 'low');
        if (in_array($priority, ['critical', 'high', 'medium'], true)) {
            $actions[] = $this->actionRow(
                'Collect Benchmark Baselines',
                $priority,
                'benchmark',
                'Benchmark confidence is limited because several players are missing baseline data.',
                'Add a roster cleanup or baseline block to the next plan.',
                'collect_baselines',
                'Open Baselines',
                'next_week_plan_draft',
                null,
                'benchmark_coverage',
            );
        }

        $communication = Arr::wrap($context['communication_rhythm'] ?? []);
        $latestWeek = Arr::wrap(Arr::wrap($communication['weekly_rows'] ?? [])[0] ?? []);
        if (($communication['rhythm_score']['label'] ?? null) === 'needs_attention' || ! (bool) ($latestWeek['has_any_report'] ?? true)) {
            $actions[] = $this->actionRow(
                'Create Weekly Update',
                'medium',
                'communication',
                'Weekly communication rhythm is due or inconsistent.',
                'Prepare a parent update or staff report from the weekly rollup.',
                'send_weekly_report',
                'Prepare Weekly Update',
                'weekly_report_delivery',
                null,
                'weekly_report',
            );
        }

        if (empty($actions)) {
            $actions[] = $this->noActionNeeded();
        }

        return collect($actions)
            ->unique(fn (array $action): string => ($action['action_type'] ?? 'none').'|'.$action['title'])
            ->sortBy([
                fn (array $a, array $b): int => $this->priorityRank((string) ($b['priority'] ?? 'low')) <=> $this->priorityRank((string) ($a['priority'] ?? 'low')),
                fn (array $a, array $b): int => strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')),
            ])
            ->values()
            ->map(function (array $action, int $index): array {
                $action['rank'] = $index + 1;

                return $action;
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildAttentionSummary(string $teamId, array $context = []): array
    {
        if (empty($context)) {
            $warnings = [];
            $context = $this->buildContext($teamId, $this->normalizeOptions([]), $warnings);
        }

        $rows = [];

        foreach (Arr::wrap($context['command_center']['player_rows'] ?? []) as $player) {
            if (! is_array($player)) {
                continue;
            }
            $playerId = (string) ($player['player_id'] ?? '');
            if ($playerId === '') {
                continue;
            }

            if (! (bool) ($player['acknowledged'] ?? false)) {
                $rows[] = $this->playerAttention($playerId, (string) ($player['player_name'] ?? 'Player'), 'Player has not acknowledged the active plan.', 'medium', 'missing_work', 'Send reminder or follow up before training.');
            } elseif (! (bool) ($player['completed'] ?? false) && (bool) ($player['started'] ?? false)) {
                $rows[] = $this->playerAttention($playerId, (string) ($player['player_name'] ?? 'Player'), 'Player started but has not completed the assigned plan.', 'medium', 'missing_work', 'Review progress and assign a makeup block if needed.');
            }

            if ((int) ($player['pending_review_count'] ?? 0) > 0) {
                $rows[] = $this->playerAttention($playerId, (string) ($player['player_name'] ?? 'Player'), 'Submitted benchmark values are waiting for review.', 'high', 'pending_review', 'Review submitted values.');
            }

            if ((int) ($player['correction_requested_count'] ?? 0) > 0) {
                $rows[] = $this->playerAttention($playerId, (string) ($player['player_name'] ?? 'Player'), 'Benchmark corrections were requested.', 'medium', 'correction_requested', 'Have the player correct and resubmit values.');
            }
        }

        foreach (Arr::wrap($context['benchmark_light']['players_needing_baselines'] ?? []) as $task) {
            if (! is_array($task)) {
                continue;
            }
            $playerId = (string) ($task['player_id'] ?? '');
            if ($playerId === '') {
                continue;
            }
            $rows[] = $this->playerAttention(
                $playerId,
                (string) ($task['player_name'] ?? $task['name'] ?? 'Player'),
                'Missing benchmark baseline.',
                (string) ($task['priority'] ?? 'medium'),
                'missing_baseline',
                'Collect the missing benchmark baseline.',
            );
        }

        return collect($rows)
            ->unique(fn (array $row): string => $row['player_id'].'|'.$row['category'].'|'.$row['reason'])
            ->sortByDesc(fn (array $row): int => $this->priorityRank((string) ($row['priority'] ?? 'low')))
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildOperatingStatus(array $context = []): array
    {
        $today = Arr::wrap($context['today_plan'] ?? []);
        $health = Arr::wrap($context['health'] ?? []);
        $alerts = Arr::wrap($context['alerts'] ?? []);
        $benchmark = Arr::wrap($context['benchmark_light'] ?? []);
        $review = Arr::wrap($context['review_summary']['data'] ?? $context['review_summary'] ?? []);
        $communication = Arr::wrap($context['communication_rhythm'] ?? []);
        $planner = Arr::wrap($context['planner_counts'] ?? []);

        $criticalAlerts = (int) data_get($alerts, 'alert_counts.critical', 0);
        $highAlerts = (int) data_get($alerts, 'alert_counts.high', 0);
        $pendingReview = (int) ($review['pending_count'] ?? data_get($context, 'command_center.review_queue_summary.pending_review_count', 0));
        $completion = $this->numberOrNull($today['completion_percentage'] ?? null);
        $healthScore = $this->numberOrNull($health['overall_score_0_100'] ?? null);
        $dataPriority = (string) ($benchmark['data_collection_priority'] ?? 'low');
        $latestWeek = Arr::wrap(Arr::wrap($communication['weekly_rows'] ?? [])[0] ?? []);

        if ((int) ($benchmark['player_count'] ?? 0) === 0 && (int) ($planner['total_plan_count'] ?? 0) === 0 && empty($context['command_center']['daily_plan_id'] ?? null)) {
            return ['home_status' => 'empty', 'status_label' => 'no_data'];
        }

        if (empty($today['daily_plan_id']) && (int) ($planner['draft_plan_count'] ?? 0) === 0 && (int) ($planner['published_plan_count'] ?? 0) === 0) {
            return ['home_status' => 'partial', 'status_label' => 'no_plan'];
        }

        if ($criticalAlerts > 0 || ($healthScore !== null && $healthScore < 40.0) || ($completion !== null && $completion < 35.0 && (int) ($today['assigned_count'] ?? 0) > 0)) {
            return ['home_status' => 'partial', 'status_label' => 'at_risk'];
        }

        if ($highAlerts > 0 || $pendingReview > 0 || in_array($dataPriority, ['critical', 'high'], true) || ! (bool) ($latestWeek['has_any_report'] ?? true)) {
            return ['home_status' => 'ready', 'status_label' => 'needs_attention'];
        }

        return ['home_status' => 'ready', 'status_label' => 'on_track'];
    }

    /**
     * @param array<string, mixed> $options
     * @param array<int, string> $warnings
     * @return array<string, mixed>
     */
    private function buildContext(string $teamId, array $options, array &$warnings): array
    {
        $context = [
            'team' => Team::query()->find($teamId),
            'planner_counts' => $this->plannerCounts($teamId, $options),
        ];

        if ($options['include_planner']) {
            $context['command_center'] = $this->safe(
                fn (): array => $this->commandCenterService->buildForTeam($teamId, [
                    'days' => 365,
                    'include_benchmark_gaps' => false,
                    'include_update_suggestions' => false,
                ]),
                [],
                $warnings,
                'Planner command center',
            );
        }

        if ($options['include_benchmarks']) {
            $context['benchmark_light'] = $this->lightweightBenchmarkSnapshot($teamId);
            $context['review_summary'] = $this->safe(
                fn (): array => $this->reviewService->buildTeamReviewSummary($teamId),
                [],
                $warnings,
                'Benchmark review summary',
            );
            $context['promotion_status'] = $this->safe(
                fn (): array => $this->promotionService->buildPromotionStatus($teamId),
                [],
                $warnings,
                'Trusted data promotion status',
            );
        }

        if ($options['include_health'] && ! $options['include_alerts']) {
            $context['health'] = $this->safe(
                fn (): array => $this->healthService->buildTeamHealthScore($teamId, [
                    'days' => $options['days'],
                    'include_weekly_reports' => false,
                    'include_season_archive' => false,
                    'include_population_learning' => false,
                    'include_decision_brief' => false,
                    'benchmark_profile' => [],
                    'collection_plan' => [],
                ]),
                [],
                $warnings,
                'Development program health',
            );
        }

        if ($options['include_alerts']) {
            $context['alerts'] = $this->safe(
                fn (): array => $this->alertService->buildTeamAlerts($teamId, [
                    'days' => $options['days'],
                    'weeks' => $options['weeks'],
                    'severity_threshold' => 'medium',
                    'include_population_learning' => true,
                ]),
                [],
                $warnings,
                'Development health alerts',
            );
            $context['alert_actions'] = [
                'alerts' => collect(Arr::wrap($context['alerts']['alerts'] ?? []))
                    ->map(fn (array $alert): array => $this->alertActionService->buildActionsForAlert($teamId, $alert, [
                        'command_center' => $context['command_center'] ?? [],
                    ]))
                    ->values()
                    ->all(),
            ];
        }

        if ($options['include_health'] && $options['include_alerts']) {
            $context['health'] = $this->lightweightHealthFromOperatingSignals($teamId, $context);
        }

        if ($options['include_reports']) {
            $context['communication_rhythm'] = $this->safe(
                fn (): array => $this->communicationRhythmService->buildTeamRhythm($teamId, [
                    'weeks' => $options['weeks'],
                ]),
                [],
                $warnings,
                'Communication rhythm',
            );
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function todayPlanFromContext(string $teamId, array $context, array $options): array
    {
        $command = Arr::wrap($context['command_center'] ?? []);
        $header = Arr::wrap($command['operating_header'] ?? []);
        $plan = Arr::wrap($command['plan_status'] ?? []);
        $summary = Arr::wrap($command['player_status_summary'] ?? []);
        $dailyPlanId = $this->nullableString($command['daily_plan_id'] ?? $header['daily_plan_id'] ?? null);

        if (! $dailyPlanId) {
            return [
                'daily_plan_id' => null,
                'title' => null,
                'status' => 'missing',
                'scheduled_for' => $options['date'],
                'estimated_minutes' => null,
                'assigned_count' => 0,
                'acknowledged_count' => 0,
                'completed_count' => 0,
                'completion_percentage' => null,
                'pending_review_count' => 0,
                'benchmark_generated' => false,
                'primary_focus' => data_get($context, 'health.summary.next_best_action'),
                'message' => 'No active plan found.',
            ];
        }

        return [
            'daily_plan_id' => $dailyPlanId,
            'title' => $this->nullableString($plan['title'] ?? $header['title'] ?? null) ?? 'Untitled Daily Plan',
            'status' => (string) ($plan['status'] ?? $header['status'] ?? 'unknown'),
            'scheduled_for' => $plan['scheduled_for'] ?? $header['scheduled_for'] ?? null,
            'estimated_minutes' => $plan['estimated_total_minutes'] ?? $header['estimated_total_minutes'] ?? null,
            'assigned_count' => (int) ($summary['assigned_count'] ?? $header['assigned_count'] ?? 0),
            'acknowledged_count' => (int) ($summary['acknowledged_count'] ?? $header['acknowledged_count'] ?? 0),
            'completed_count' => (int) ($summary['completed_count'] ?? $header['completed_count'] ?? 0),
            'completion_percentage' => $this->numberOrNull($summary['completion_percentage'] ?? $header['completion_percentage'] ?? null),
            'pending_review_count' => (int) data_get($command, 'review_queue_summary.pending_review_count', $summary['pending_review_count'] ?? 0),
            'benchmark_generated' => (bool) ($plan['benchmark_generated'] ?? $header['benchmark_generated'] ?? false),
            'primary_focus' => data_get($context, 'health.summary.next_best_action'),
            'message' => $header['next_action_text'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operatingSummary(string $teamId, array $context, array $todayPlan, array $primaryAction, array $status): array
    {
        $statusLabel = (string) ($status['status_label'] ?? 'no_data');
        $teamName = $this->teamName($teamId) ?? 'Team';
        $primaryFocus = data_get($context, 'health.summary.next_best_action')
            ?? null;

        $headline = match ($statusLabel) {
            'on_track' => $teamName.' is on track today.',
            'needs_attention' => $teamName.' needs coach attention today.',
            'at_risk' => $teamName.' has operating risk today.',
            'no_plan' => 'No active plan is published for today.',
            default => 'FMTRX will build this operating view as your team uses the system.',
        };

        $summary = match ($statusLabel) {
            'on_track' => 'The active plan, review queue, alerts, and communication rhythm are in a healthy range.',
            'needs_attention' => 'FMTRX found coach actions in planner, benchmark, review, alerts, or communication workflows.',
            'at_risk' => 'A critical alert, low completion, weak health score, or missing operating workflow needs immediate review.',
            'no_plan' => 'Generate or publish a Daily Plan so players know what to do.',
            default => 'No planner or benchmark workflow activity was found yet.',
        };

        return [
            'headline' => $headline,
            'summary_text' => $summary,
            'status_label' => $statusLabel,
            'primary_focus' => $primaryFocus,
            'next_best_action' => $primaryAction['title'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function healthSnapshot(array $context): array
    {
        $health = Arr::wrap($context['health'] ?? []);
        $trend = Arr::wrap($context['trendline']['overall_trend'] ?? []);
        $alertTrendDirection = data_get($context, 'alerts.highest_priority_alert.evidence.trend_direction');

        return [
            'overall_score_0_100' => $this->numberOrNull($health['overall_score_0_100'] ?? null),
            'label' => $health['overall_label'] ?? null,
            'headline' => data_get($health, 'summary.headline'),
            'primary_strength' => data_get($health, 'summary.primary_strength'),
            'primary_risk' => data_get($health, 'summary.primary_risk'),
            'trend_direction' => (string) ($trend['trend_direction'] ?? $alertTrendDirection ?? 'no_data'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function alertsSnapshot(array $context): array
    {
        $alerts = Arr::wrap($context['alerts'] ?? []);

        return [
            'active_alert_count' => (int) data_get($alerts, 'summary.active_alert_count', count(Arr::wrap($alerts['alerts'] ?? []))),
            'critical_count' => (int) data_get($alerts, 'alert_counts.critical', 0),
            'high_count' => (int) data_get($alerts, 'alert_counts.high', 0),
            'highest_priority_alert' => Arr::wrap($alerts['highest_priority_alert'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function benchmarkSnapshot(array $context): array
    {
        $snapshot = Arr::wrap($context['benchmark_light'] ?? []);

        return [
            'benchmark_confidence' => $snapshot['benchmark_confidence'] ?? null,
            'players_with_benchmark_data' => $snapshot['players_with_benchmark_data'] ?? null,
            'players_without_benchmark_data' => $snapshot['players_without_benchmark_data'] ?? null,
            'missing_critical_count' => (int) ($snapshot['missing_critical_count'] ?? 0),
            'missing_supporting_count' => (int) ($snapshot['missing_supporting_count'] ?? 0),
            'weakest_category' => $snapshot['weakest_category'] ?? null,
            'weakest_metric' => $snapshot['weakest_metric'] ?? null,
            'data_collection_priority' => $snapshot['data_collection_priority'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewSnapshot(array $context): array
    {
        $review = Arr::wrap($context['review_summary']['data'] ?? $context['review_summary'] ?? []);
        $promotion = Arr::wrap($context['promotion_status'] ?? []);

        return [
            'pending_review_count' => (int) ($review['pending_count'] ?? data_get($context, 'command_center.review_queue_summary.pending_review_count', 0)),
            'oldest_pending_at' => collect(Arr::wrap($review['pending_tasks'] ?? []))->pluck('submitted_at')->filter()->sort()->first()
                ?? data_get($context, 'command_center.review_queue_summary.oldest_pending_at'),
            'approved_unpromoted_count' => (int) ($promotion['awaiting_promotion_count'] ?? data_get($context, 'command_center.trusted_data_summary.awaiting_promotion_count', 0)),
            'correction_requested_count' => (int) ($review['correction_requested_count'] ?? data_get($context, 'command_center.player_status_summary.correction_requested_count', 0)),
            'message' => $this->reviewMessage($review, $promotion),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function plannerSnapshot(string $teamId, array $context, array $options): array
    {
        $counts = Arr::wrap($context['planner_counts'] ?? $this->plannerCounts($teamId, $options));
        $summary = Arr::wrap($context['command_center']['player_status_summary'] ?? []);

        return [
            'active_plan_count' => (int) ($counts['active_plan_count'] ?? 0),
            'draft_plan_count' => (int) ($counts['draft_plan_count'] ?? 0),
            'published_plan_count' => (int) ($counts['published_plan_count'] ?? 0),
            'weekly_draft_available' => (bool) ($counts['weekly_draft_available'] ?? false),
            'next_week_plan_available' => (bool) ($counts['next_week_plan_available'] ?? false),
            'completion_percentage' => $this->numberOrNull($summary['completion_percentage'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function communicationSnapshot(array $context): array
    {
        $rhythm = Arr::wrap($context['communication_rhythm'] ?? []);
        $latestWeek = Arr::wrap(Arr::wrap($rhythm['weekly_rows'] ?? [])[0] ?? []);
        $templates = Arr::wrap($rhythm['template_summary'] ?? []);
        $lastReportAt = collect($templates)->pluck('last_used_at')->filter()->sort()->last();

        return [
            'weekly_report_due' => ! (bool) ($latestWeek['has_any_report'] ?? false),
            'last_weekly_report_at' => $lastReportAt,
            'last_report_at' => $lastReportAt,
            'communication_rhythm_label' => data_get($rhythm, 'rhythm_score.label'),
            'rhythm_label' => data_get($rhythm, 'rhythm_score.label'),
            'parent_update_due' => ! (bool) ($latestWeek['has_parent_update'] ?? false),
            'staff_report_due' => ! (bool) ($latestWeek['has_staff_report'] ?? false),
            'message' => (string) ($latestWeek['recommended_action'] ?? data_get($rhythm, 'recommended_actions.0.action') ?? 'Weekly report information is not available yet.'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function quickLinks(string $teamId): array
    {
        return [
            $this->quickLink('Daily Planner', 'open_daily_planner', 'daily_planner'),
            $this->quickLink('Weekly Rollup', 'open_weekly_calendar', 'weekly_rollup'),
            $this->quickLink('Benchmark Intelligence', 'view_benchmark_intelligence', 'benchmark_intelligence'),
            $this->quickLink('Review Queue', 'review_submissions', 'review_queue'),
            $this->quickLink('Reports', 'prepare_weekly_report', 'weekly_report_delivery'),
            $this->quickLink('Alerts', 'view_alerts', 'development_health_alerts'),
            $this->quickLink('Health Score', 'view_health_score', 'development_program_health'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function plannerCounts(string $teamId, array $options): array
    {
        $date = CarbonImmutable::parse((string) $options['date']);
        $start = $date->startOfWeek();
        $end = $date->endOfWeek();
        $plans = DailyPlan::query()
            ->where('team_id', $teamId)
            ->where('status', '!=', 'template')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        return [
            'total_plan_count' => $plans->count(),
            'active_plan_count' => $plans->filter(fn (DailyPlan $plan): bool => in_array((string) $plan->status, ['published', 'sent', 'in_progress'], true))->count(),
            'draft_plan_count' => $plans->where('status', 'draft')->count(),
            'published_plan_count' => $plans->where('status', 'published')->count(),
            'weekly_draft_available' => $plans->where('status', 'draft')->isNotEmpty(),
            'next_week_plan_available' => DailyPlan::query()
                ->where('team_id', $teamId)
                ->where('status', '!=', 'template')
                ->whereDate('date', '>', $end->toDateString())
                ->exists(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function actionRow(string $title, string $priority, string $category, string $why, string $action, ?string $actionType, ?string $buttonLabel, ?string $targetSection, mixed $targetRoute, string $source, array $raw = []): array
    {
        $metadata = $this->homeActionMetadata($actionType, $buttonLabel, $targetSection, $targetRoute, $raw);

        return [
            'rank' => 0,
            'title' => $title,
            'priority' => $this->priority($priority),
            'category' => $category,
            'why' => $why,
            'action' => $action,
            'action_type' => $actionType,
            'button_label' => $buttonLabel,
            'target_section' => $targetSection,
            'target_route' => $targetRoute,
            'enabled' => (bool) ($raw['enabled'] ?? true),
            'source' => $source,
            ...$metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quickLink(string $label, string $actionType, string $targetSection): array
    {
        return [
            'label' => $label,
            'action_type' => $actionType,
            'button_label' => $label,
            'enabled' => true,
            'requires_confirmation' => false,
            'requires_selection' => false,
            'target_section' => $targetSection,
            'target_route' => '/practice-planner',
            'route' => null,
            'api_endpoint' => null,
            'method' => null,
            'disabled_reason' => null,
            'safety_notes' => ['Navigation only; no backend data is changed.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function homeActionMetadata(?string $actionType, ?string $buttonLabel, ?string $targetSection, mixed $targetRoute, array $raw = []): array
    {
        $normalized = match ((string) ($actionType ?? '')) {
            'approve_values' => 'approve_selected_values',
            'generate_next_plan' => 'generate_suggested_plan',
            'send_weekly_report' => 'prepare_weekly_report',
            default => $actionType,
        };

        $mutationActions = [
            'publish_plan',
            'assign_plan',
            'approve_selected_values',
            'request_corrections',
            'promote_trusted_data',
            'send_reminder',
            'save_suggested_plan_draft',
        ];
        $selectionActions = ['assign_plan', 'approve_selected_values', 'request_corrections'];
        $navigationActions = [
            null,
            '',
            'open_daily_planner',
            'view_alerts',
            'view_health_score',
            'view_benchmark_intelligence',
            'view_communication_rhythm',
            'open_weekly_calendar',
            'none',
        ];

        $hasEndpoint = $normalized && ! in_array($normalized, $navigationActions, true);

        return [
            'action_type' => $normalized,
            'button_label' => $buttonLabel,
            'requires_confirmation' => in_array($normalized, $mutationActions, true),
            'requires_selection' => in_array($normalized, $selectionActions, true),
            'target_section' => $targetSection,
            'target_route' => $targetRoute,
            'api_endpoint' => null,
            'method' => $raw['method'] ?? ($hasEndpoint ? 'POST' : null),
            'payload' => Arr::wrap($raw['payload'] ?? []),
            'success_message' => $raw['success_message'] ?? null,
            'disabled_reason' => (bool) ($raw['enabled'] ?? true) ? null : ($raw['disabled_reason'] ?? 'This action is not available yet.'),
            'safety_notes' => $this->homeActionSafetyNotes((string) ($normalized ?? ''), $hasEndpoint),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function homeActionSafetyNotes(string $actionType, bool $hasEndpoint): array
    {
        $notes = ['Uses existing FMTRX workflows; no scoring or benchmark formulas are changed.'];

        if (! $hasEndpoint) {
            $notes[] = 'Navigation only; no backend data is changed.';
        }

        if (in_array($actionType, ['publish_plan', 'assign_plan', 'approve_selected_values', 'request_corrections', 'promote_trusted_data', 'send_reminder', 'save_suggested_plan_draft'], true)) {
            $notes[] = 'Requires explicit coach confirmation before any write action.';
        }

        if ($actionType === 'promote_trusted_data') {
            $notes[] = 'Only approved values can be promoted; pending and rejected values are ignored.';
        }

        return $notes;
    }

    /**
     * @return array<string, mixed>
     */
    private function noActionNeeded(): array
    {
        return $this->actionRow(
            'No Urgent Action',
            'low',
            'planner',
            'No critical operating issue is waiting right now.',
            'Keep current rhythm and refresh after the next player activity.',
            null,
            null,
            null,
            null,
            'operating_home',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function playerAttention(string $playerId, string $name, string $reason, string $priority, string $category, string $action): array
    {
        return [
            'player_id' => $playerId,
            'player_name' => $name,
            'reason' => $reason,
            'priority' => $this->priority($priority),
            'category' => $category,
            'recommended_action' => $action,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeOptions(array $options): array
    {
        return [
            'date' => $this->dateString($options['date'] ?? null),
            'days' => max(7, min(365, (int) ($options['days'] ?? 30))),
            'weeks' => max(1, min(52, (int) ($options['weeks'] ?? 8))),
            'include_health' => $this->bool($options['include_health'] ?? true),
            'include_alerts' => $this->bool($options['include_alerts'] ?? true),
            'include_planner' => $this->bool($options['include_planner'] ?? true),
            'include_benchmarks' => $this->bool($options['include_benchmarks'] ?? true),
            'include_reports' => $this->bool($options['include_reports'] ?? true),
        ];
    }

    private function safe(callable $callback, array $fallback, array &$warnings, string $label): array
    {
        try {
            $result = $callback();
            if (is_array($result)) {
                foreach (Arr::wrap($result['warnings'] ?? []) as $warning) {
                    if (is_string($warning) && trim($warning) !== '') {
                        $warnings[] = $label.': '.$warning;
                    }
                }

                return $result;
            }
        } catch (Throwable $exception) {
            $warnings[] = $label.' unavailable: '.$exception->getMessage();
        }

        return $fallback;
    }

    private function dateString(mixed $value): string
    {
        try {
            return $value ? CarbonImmutable::parse((string) $value)->toDateString() : now()->toDateString();
        } catch (Throwable) {
            return now()->toDateString();
        }
    }

    private function teamName(string $teamId): ?string
    {
        return Team::query()->whereKey($teamId)->value('name');
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function priority(string $priority): string
    {
        return in_array($priority, ['critical', 'high', 'medium', 'low'], true) ? $priority : 'low';
    }

    private function priorityRank(string $priority): int
    {
        return [
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
        ][$this->priority($priority)] ?? 0;
    }

    private function sectionForAction(string $actionType): string
    {
        return match ($actionType) {
            'publish_plan', 'generate_next_plan', 'open_daily_planner' => 'daily_planner',
            'send_reminder' => 'player_plan_progress',
            'review_submissions', 'promote_trusted_data' => 'review_queue',
            'collect_baselines' => 'next_week_plan_draft',
            'send_weekly_report' => 'weekly_report_delivery',
            default => 'coach_command_center',
        };
    }

    private function missingCount(array $profile, string $classification): int
    {
        return collect(Arr::wrap($profile['missing_metrics'] ?? []))
            ->filter(fn (array $row): bool => (string) ($row['classification'] ?? '') === $classification)
            ->sum(fn (array $row): int => (int) ($row['missing_count'] ?? 0));
    }

    /**
     * Lightweight benchmark coverage for the operating home.
     *
     * The full TeamBenchmarkProfileService can be expensive because it builds
     * player intelligence snapshots. The home screen only needs operating
     * coverage, so it reads roster membership and benchmark collection tasks.
     *
     * @return array<string, mixed>
     */
    private function lightweightBenchmarkSnapshot(string $teamId): array
    {
        $rosterRows = PlayerTeam::query()
            ->with('user.profile')
            ->where('team_id', $teamId)
            ->whereNotNull('user_id')
            ->get();

        $rosterIds = $rosterRows
            ->pluck('user_id')
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values();

        $tasks = BenchmarkCollectionTask::query()
            ->where('team_id', $teamId)
            ->get([
                'assigned_to_player_id',
                'status',
                'review_status',
                'promotion_status',
                'metrics',
                'missing_fields',
                'priority',
            ]);

        $dataStatuses = [
            BenchmarkCollectionTask::STATUS_COMPLETED,
        ];
        $dataReviewStatuses = [
            BenchmarkCollectionTask::REVIEW_PENDING,
            BenchmarkCollectionTask::REVIEW_APPROVED,
            BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED,
            BenchmarkCollectionTask::REVIEW_NOT_REQUIRED,
        ];
        $dataPromotionStatuses = [
            BenchmarkCollectionTask::PROMOTION_PROMOTED,
            BenchmarkCollectionTask::PROMOTION_PARTIAL,
        ];

        $playersWithData = $tasks
            ->filter(function (BenchmarkCollectionTask $task) use ($dataStatuses, $dataReviewStatuses, $dataPromotionStatuses): bool {
                return (string) ($task->assigned_to_player_id ?? '') !== ''
                    && (
                        in_array((string) $task->status, $dataStatuses, true)
                        || in_array((string) $task->review_status, $dataReviewStatuses, true)
                        || in_array((string) $task->promotion_status, $dataPromotionStatuses, true)
                    );
            })
            ->pluck('assigned_to_player_id')
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values();

        $playersWithoutData = $rosterIds
            ->reject(fn (string $playerId): bool => $playersWithData->contains($playerId))
            ->values();

        $activeBaselineTasks = $tasks
            ->filter(fn (BenchmarkCollectionTask $task): bool => in_array((string) $task->status, BenchmarkCollectionTask::ACTIVE_STATUSES, true))
            ->count();

        $metricCounts = $tasks
            ->flatMap(fn (BenchmarkCollectionTask $task): array => Arr::wrap($task->metrics))
            ->filter(fn (mixed $metric): bool => is_string($metric) && trim($metric) !== '')
            ->map(fn (mixed $metric): string => trim((string) $metric))
            ->countBy()
            ->sortDesc();

        $topMetric = $metricCounts->keys()->first();
        $playerCount = $rosterIds->count();
        $withoutCount = $playersWithoutData->count();
        $priority = $playerCount === 0
            ? 'low'
            : ($withoutCount >= max(1, (int) ceil($playerCount * 0.5)) ? 'high' : ($withoutCount > 0 ? 'medium' : 'low'));

        $missingPlayers = $rosterRows
            ->filter(fn (PlayerTeam $row): bool => $playersWithoutData->contains((string) $row->user_id))
            ->map(fn (PlayerTeam $row): array => [
                'player_id' => (string) $row->user_id,
                'player_name' => $this->playerTeamName($row),
                'priority' => $priority === 'low' ? 'medium' : $priority,
            ])
            ->values()
            ->all();

        return [
            'benchmark_confidence' => $tasks->isEmpty() ? 'low' : ($withoutCount > 0 ? 'medium' : 'high'),
            'player_count' => $playerCount,
            'metric_count' => (int) $metricCounts->sum(),
            'players_with_benchmark_data' => $playersWithData->count(),
            'players_without_benchmark_data' => $withoutCount,
            'missing_critical_count' => $withoutCount,
            'missing_supporting_count' => $activeBaselineTasks,
            'weakest_category' => is_string($topMetric) ? $this->metricCategory($topMetric) : null,
            'weakest_metric' => is_string($topMetric) ? $this->humanMetric($topMetric) : null,
            'data_collection_priority' => $priority,
            'players_needing_baselines' => $missingPlayers,
            'evidence' => [
                'source' => 'roster_benchmark_collection_tasks',
                'player_count' => $playerCount,
                'benchmark_task_count' => $tasks->count(),
                'active_baseline_task_count' => $activeBaselineTasks,
                'does_not_run_benchmark_scoring' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lightweightHealthFromOperatingSignals(string $teamId, array $context): array
    {
        $alerts = Arr::wrap($context['alerts'] ?? []);
        $benchmark = Arr::wrap($context['benchmark_light'] ?? []);
        $review = Arr::wrap($context['review_summary']['data'] ?? $context['review_summary'] ?? []);
        $planner = Arr::wrap($context['planner_counts'] ?? []);
        $highestAlert = Arr::wrap($alerts['highest_priority_alert'] ?? []);

        $critical = (int) data_get($alerts, 'alert_counts.critical', 0);
        $high = (int) data_get($alerts, 'alert_counts.high', 0);
        $medium = (int) data_get($alerts, 'alert_counts.medium', 0);
        $pendingReview = (int) ($review['pending_count'] ?? 0);
        $noActivePlan = (int) ($planner['active_plan_count'] ?? 0) === 0;
        $benchmarkPriority = (string) ($benchmark['data_collection_priority'] ?? 'low');

        $deductions = ($critical * 25)
            + ($high * 15)
            + ($medium * 7)
            + ($pendingReview > 0 ? 10 : 0)
            + ($noActivePlan ? 15 : 0)
            + (in_array($benchmarkPriority, ['critical', 'high'], true) ? 10 : 0);

        $score = max(0.0, min(100.0, round(100 - $deductions, 1)));
        $label = $score >= 80 ? 'healthy' : ($score >= 60 ? 'stable' : ($score >= 40 ? 'needs_attention' : 'at_risk'));
        $risk = $highestAlert['title'] ?? ($noActivePlan ? 'No Active Plan Published' : null);

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'overall_score_0_100' => $score,
            'overall_label' => $label,
            'summary' => [
                'headline' => 'Operating Health: '.$score.' — '.$this->humanMetric($label),
                'primary_strength' => $pendingReview === 0 ? 'Review Queue Is Current' : null,
                'primary_risk' => $risk,
                'next_best_action' => $highestAlert['recommended_action'] ?? ($noActivePlan ? 'Generate or publish this week’s plan.' : null),
            ],
            'evidence' => [
                'source' => 'operating_home_alert_planner_benchmark_review_signals',
                'uses_existing_alert_output' => true,
                'does_not_change_development_health_formula' => true,
                'critical_alert_count' => $critical,
                'high_alert_count' => $high,
                'medium_alert_count' => $medium,
                'pending_review_count' => $pendingReview,
                'active_plan_count' => (int) ($planner['active_plan_count'] ?? 0),
                'benchmark_data_collection_priority' => $benchmarkPriority,
            ],
        ];
    }

    private function playerTeamName(PlayerTeam $row): string
    {
        $profile = $row->user?->profile;
        $name = trim(((string) ($profile?->first_name ?? '')).' '.((string) ($profile?->last_name ?? '')));

        return $name !== '' ? $name : 'Player';
    }

    private function humanMetric(string $metric): string
    {
        return ucwords(str_replace('_', ' ', $metric));
    }

    private function metricCategory(string $metric): string
    {
        $metric = strtolower($metric);

        if (str_contains($metric, 'exit_velocity') || str_contains($metric, 'hard_hit') || str_contains($metric, 'line_drive') || str_contains($metric, 'swing_miss')) {
            return 'hitting';
        }

        if (str_contains($metric, 'fastball') || str_contains($metric, 'strike') || str_contains($metric, 'long_toss') || str_contains($metric, 'weighted_ball')) {
            return 'pitching';
        }

        if (str_contains($metric, 'bench') || str_contains($metric, 'squat') || str_contains($metric, 'deadlift') || str_contains($metric, 'pull') || str_contains($metric, 'push')) {
            return 'strength';
        }

        if (str_contains($metric, 'mobility') || str_contains($metric, 'shoulder') || str_contains($metric, 'hip') || str_contains($metric, 'spine')) {
            return 'mobility';
        }

        if (str_contains($metric, 'dash') || str_contains($metric, 'jump')) {
            return 'athletic';
        }

        return 'benchmark';
    }

    private function reviewMessage(array $review, array $promotion): string
    {
        if ((int) ($review['pending_count'] ?? 0) > 0) {
            return 'Submitted values are waiting for coach review.';
        }
        if ((int) ($promotion['awaiting_promotion_count'] ?? 0) > 0) {
            return 'Approved values are ready to promote into trusted data.';
        }
        if ((int) ($review['correction_requested_count'] ?? 0) > 0) {
            return 'Some submitted values need correction.';
        }

        return 'No pending reviews.';
    }

    /**
     * @return array<string, bool>
     */
    private function subsystemsLoaded(array $context): array
    {
        return [
            'planner' => ! empty($context['command_center']),
            'health' => ! empty($context['health']),
            'alerts' => ! empty($context['alerts']),
            'benchmarks' => ! empty($context['benchmark_light']),
            'review' => ! empty($context['review_summary']),
            'communication' => ! empty($context['communication_rhythm']),
        ];
    }
}
