<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\BenchmarkCollectionTask;
use App\Models\PlayerTeam;
use App\Services\Intelligence\DecisionEngine;
use App\Services\Intelligence\TeamBenchmarkProfileService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

class CoachWeeklyTeamReportService
{
    public function __construct(
        private readonly WeeklyPlannerRollupService $weeklyPlannerRollupService,
        private readonly TeamBenchmarkProfileService $teamBenchmarkProfileService,
        private readonly DecisionEngine $decisionEngine,
    ) {
    }

    public function buildTeamReport(string $teamId, array $options = []): array
    {
        [$start, $end] = $this->dateWindow($options);
        $includePlayerRows = $this->bool($options['include_player_rows'] ?? true);
        $includeBenchmarkDetails = $this->bool($options['include_benchmark_details'] ?? true);
        $includeNextWeekPriorities = $this->bool($options['include_next_week_priorities'] ?? true);
        $warnings = [
            'Exact before/after intelligence snapshots are not persisted yet. Weekly report uses current FMTRX intelligence as the current state.',
        ];

        try {
            $rollup = $this->weeklyPlannerRollupService->buildTeamWeeklyRollup($teamId, [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $start->diffInDays($end) + 1,
                'include_players' => true,
                'include_benchmark_intelligence' => true,
            ]);
            $playerRows = $includePlayerRows
                ? $this->buildPlayerReportRowsFromRollup($rollup)
                : [];
            $benchmarkReport = $includeBenchmarkDetails
                ? $this->buildBenchmarkReport($teamId, $start->toDateString(), $end->toDateString())
                : $this->emptyBenchmarkReport();
            $teamCompletion = $this->buildTeamCompletion($rollup, $playerRows);
            $reviewSummary = $this->buildReviewSummary($benchmarkReport);
            $trustedData = $this->buildTrustedDataSummary($rollup, $teamId, $warnings);
            $missedWork = $this->buildMissedWorkSummary($playerRows);

            $report = [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'week_label' => $this->weekLabel($start, $end),
                'report_status' => $this->reportStatus($teamCompletion, $benchmarkReport, $rollup),
                'executive_summary' => [],
                'team_completion' => $teamCompletion,
                'player_rows' => $playerRows,
                'benchmark_submission_summary' => $benchmarkReport,
                'review_summary' => $reviewSummary,
                'trusted_data_summary' => $trustedData,
                'missed_work_summary' => $missedWork,
                'coach_follow_ups' => [],
                'next_week_priorities' => [],
                'current_team_intelligence' => [
                    ...$this->buildCurrentTeamIntelligence($teamId, $warnings),
                    'weekly_recommendations' => Arr::wrap($rollup['next_week_recommendations'] ?? []),
                ],
                'warnings' => [],
                'evidence' => [
                    'source' => 'weekly_rollup_player_completion_benchmark_tasks_current_intelligence',
                    'weekly_rollup_status' => $rollup['summary_status'] ?? null,
                    'player_rows_included' => $includePlayerRows,
                    'benchmark_details_included' => $includeBenchmarkDetails,
                    'next_week_priorities_included' => $includeNextWeekPriorities,
                ],
            ];

            $report['coach_follow_ups'] = $this->buildCoachFollowUps($report);
            $report['next_week_priorities'] = $includeNextWeekPriorities
                ? $this->buildNextWeekPriorities($teamId, $report)
                : [];
            $report['executive_summary'] = $this->buildExecutiveSummary($report);
            $report['warnings'] = array_values(array_unique(array_filter([
                ...$warnings,
                ...Arr::wrap($rollup['warnings'] ?? []),
            ])));

            return $report;
        } catch (Throwable $exception) {
            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'week_label' => $this->weekLabel($start, $end),
                'report_status' => 'failed',
                'executive_summary' => [
                    'headline' => 'Weekly team report is not available yet.',
                    'summary_text' => 'FMTRX could not build the weekly team report.',
                    'wins' => [],
                    'concerns' => ['Report generation failed.'],
                    'next_best_action' => 'Refresh the report after checking team data.',
                ],
                'team_completion' => $this->emptyTeamCompletion(),
                'player_rows' => [],
                'benchmark_submission_summary' => $this->emptyBenchmarkReport(),
                'review_summary' => $this->emptyReviewSummary(),
                'trusted_data_summary' => $this->emptyTrustedDataSummary(),
                'missed_work_summary' => $this->emptyMissedWorkSummary(),
                'coach_follow_ups' => [],
                'next_week_priorities' => [],
                'current_team_intelligence' => [],
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'exception' => class_basename($exception),
                ],
            ];
        }
    }

    public function buildPlayerReportRows(string $teamId, string $startDate, string $endDate): array
    {
        $rollup = $this->weeklyPlannerRollupService->buildTeamWeeklyRollup($teamId, [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'include_players' => true,
            'include_benchmark_intelligence' => false,
        ]);

        return $this->buildPlayerReportRowsFromRollup($rollup);
    }

    public function buildBenchmarkReport(string $teamId, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->endOfDay();
        $tasks = BenchmarkCollectionTask::query()
            ->where('team_id', $teamId)
            ->where(function ($query) use ($start, $end): void {
                foreach (['submitted_at', 'reviewed_at', 'promoted_at', 'completed_at', 'created_at', 'updated_at'] as $column) {
                    $query->orWhereBetween($column, [$start->toDateTimeString(), $end->toDateTimeString()]);
                }
            })
            ->get();

        if ($tasks->isEmpty()) {
            return $this->emptyBenchmarkReport([
                'top_remaining_missing_metrics' => $this->topRemainingMissingMetrics($teamId),
            ]);
        }

        $submittedMetricCount = 0;
        $approvedMetricCount = 0;
        $pendingReviewCount = 0;
        $rejectedCount = 0;
        $correctionCount = 0;
        $trustedPromoted = 0;
        $metricRows = [];
        $pendingTasks = [];
        $correctionPlayers = [];

        foreach ($tasks as $task) {
            $submittedValues = $this->submittedValues($task);
            $approvedValues = $task->review_status === BenchmarkCollectionTask::REVIEW_APPROVED ? $this->approvedValues($task) : [];
            $trustedValues = $this->taskIsPromoted($task) ? $this->trustedValues($task) : [];
            $player = $this->playerRow((string) $task->assigned_to_player_id);
            $submittedMetricCount += count($submittedValues);

            if ($task->review_status === BenchmarkCollectionTask::REVIEW_PENDING) {
                $pendingReviewCount += count($submittedValues);
                $pendingTasks[] = [
                    'task_id' => (string) $task->id,
                    'player_id' => $player['player_id'],
                    'player_name' => $player['player_name'],
                    'title' => $task->title,
                    'submitted_at' => $this->dateString($task->submitted_at),
                    'submitted_values_summary' => $this->valueSummaryRows($submittedValues),
                ];
            }
            if ($task->review_status === BenchmarkCollectionTask::REVIEW_APPROVED) {
                $approvedMetricCount += count($approvedValues);
            }
            if ($task->review_status === BenchmarkCollectionTask::REVIEW_REJECTED) {
                $rejectedCount += count($submittedValues);
            }
            if ($task->review_status === BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED) {
                $correctionCount += count($submittedValues);
                $correctionPlayers[$player['player_id']] = $player;
            }
            if ($this->taskIsPromoted($task)) {
                $trustedPromoted += count($trustedValues);
            }

            foreach ($submittedValues as $metricKey => $value) {
                $key = (string) $metricKey;
                $metricRows[$key] ??= [
                    'metric_key' => $key,
                    'display_name' => $this->metricLabel($key),
                    'category' => $this->metricCategory($key),
                    'submitted_count' => 0,
                    'approved_count' => 0,
                    'pending_review_count' => 0,
                    'rejected_count' => 0,
                    'correction_requested_count' => 0,
                    'trusted_count' => 0,
                    'players' => [],
                ];
                $metricRows[$key]['submitted_count']++;
                if ($task->review_status === BenchmarkCollectionTask::REVIEW_APPROVED && array_key_exists($key, $approvedValues)) {
                    $metricRows[$key]['approved_count']++;
                }
                if ($task->review_status === BenchmarkCollectionTask::REVIEW_PENDING) {
                    $metricRows[$key]['pending_review_count']++;
                }
                if ($task->review_status === BenchmarkCollectionTask::REVIEW_REJECTED) {
                    $metricRows[$key]['rejected_count']++;
                }
                if ($task->review_status === BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED) {
                    $metricRows[$key]['correction_requested_count']++;
                }
                if (array_key_exists($key, $trustedValues)) {
                    $metricRows[$key]['trusted_count']++;
                }
                $metricRows[$key]['players'][$player['player_id']] = $player;
            }
        }

        $metricsSubmitted = collect($metricRows)
            ->map(function (array $row): array {
                $row['players'] = array_values($row['players']);

                return $row;
            })
            ->sortByDesc('submitted_count')
            ->values()
            ->all();

        return [
            'submitted_metric_count' => $submittedMetricCount,
            'approved_metric_count' => $approvedMetricCount,
            'pending_review_count' => $pendingReviewCount,
            'rejected_count' => $rejectedCount,
            'correction_requested_count' => $correctionCount,
            'trusted_values_promoted' => $trustedPromoted,
            'metrics_submitted' => $metricsSubmitted,
            'top_collected_metrics' => array_slice($metricsSubmitted, 0, 5),
            'top_remaining_missing_metrics' => $this->topRemainingMissingMetrics($teamId),
            'tasks_pending_review' => array_slice($pendingTasks, 0, 20),
            'players_needing_correction' => array_values($correctionPlayers),
        ];
    }

    public function buildCoachFollowUps(array $report): array
    {
        $followUps = [];
        $benchmark = $report['benchmark_submission_summary'] ?? [];
        $team = $report['team_completion'] ?? [];
        $missed = $report['missed_work_summary'] ?? [];
        $missingMetrics = Arr::wrap($benchmark['top_remaining_missing_metrics'] ?? []);

        if ((int) ($benchmark['pending_review_count'] ?? 0) > 0) {
            $followUps[] = $this->followUp(
                'Review Submitted Benchmark Values',
                'high',
                Arr::wrap($report['review_summary']['tasks_pending_review'] ?? []),
                'Submitted values are waiting for coach review and are not trusted yet.',
                'Open the review queue and approve, reject, or request corrections.',
                'review_submission',
            );
        }

        if ((int) ($benchmark['correction_requested_count'] ?? 0) > 0) {
            $followUps[] = $this->followUp(
                'Send Correction Notes',
                'high',
                Arr::wrap($report['review_summary']['players_needing_correction'] ?? []),
                'Some benchmark values need correction before they can update intelligence.',
                'Send clear correction notes and ask players to resubmit the flagged values.',
                'request_correction',
            );
        }

        if ((int) ($team['players_not_started'] ?? 0) > 0) {
            $players = collect(Arr::wrap($report['player_rows'] ?? []))
                ->filter(fn (array $row): bool => (float) ($row['completion_percentage'] ?? 0) <= 0.0 && (int) ($row['plans_assigned'] ?? 0) > 0)
                ->map(fn (array $row): array => Arr::only($row, ['player_id', 'player_name']))
                ->values()
                ->all();
            $followUps[] = $this->followUp(
                'Check In With Players Who Did Not Start',
                'medium',
                $players,
                'One or more players did not start assigned weekly work.',
                'Send a reminder or check in before next week is published.',
                'check_in',
            );
        }

        if ((int) ($missed['players_with_missed_work'] ?? 0) > 0) {
            $followUps[] = $this->followUp(
                'Follow Up on Missed Work',
                'medium',
                Arr::wrap($missed['players'] ?? []),
                'Some players finished only part of their assigned work.',
                'Review missed items and decide whether to make them up or adjust next week.',
                'send_reminder',
            );
        }

        if ($this->hasMissingMetric($missingMetrics, ['player_context', 'roster_profile', 'dob', 'position'])) {
            $followUps[] = $this->followUp(
                'Complete Roster Profiles',
                'medium',
                [],
                'Roster context is needed for accurate benchmark comparisons.',
                'Update player DOB, position, height, weight, bats, and throws where missing.',
                'collect_baseline',
            );
        }

        if ($this->hasMissingMetric($missingMetrics, ['bench_press', 'squat', 'deadlift', 'mobility_score', 'shoulder_mobility_score', 'hip_mobility_score'])) {
            $followUps[] = $this->followUp(
                'Schedule Strength/Mobility Baseline',
                'medium',
                [],
                'Strength or mobility baselines remain missing for the team.',
                'Add a short strength and mobility collection block next week.',
                'collect_baseline',
            );
        }

        if (empty($followUps)) {
            $followUps[] = $this->followUp(
                'Generate Next Week Plan',
                'low',
                [],
                'Weekly work and coach review do not have urgent blockers.',
                'Generate the next week draft and adjust it for coach priorities.',
                'build_next_plan',
            );
        }

        return $followUps;
    }

    public function buildNextWeekPriorities(string $teamId, array $report): array
    {
        $priorities = [];
        $decision = Arr::wrap($report['current_team_intelligence']['decision_brief'] ?? []);
        $primary = Arr::wrap($decision['primary_focus'] ?? []);
        $rollupRecommendations = Arr::wrap($report['current_team_intelligence']['weekly_recommendations'] ?? []);

        if (! empty($primary)) {
            $priorities[] = $this->priority(
                $primary['title'] ?? $primary['focus'] ?? 'Team Focus',
                'high',
                (string) ($primary['category'] ?? $this->categoryForTitle((string) ($primary['title'] ?? ''))),
                (string) ($primary['why'] ?? 'Current team intelligence identifies this as the primary focus.'),
                (string) ($primary['action'] ?? Arr::get($decision, 'practice_focus.action') ?? null),
                Arr::wrap($decision['players_needing_attention'] ?? []),
                Arr::wrap($decision['evidence'] ?? []),
                20,
                'decision_brief',
            );
        }

        foreach ($rollupRecommendations as $recommendation) {
            if (! is_array($recommendation)) {
                continue;
            }
            $priorities[] = $this->priority(
                (string) ($recommendation['title'] ?? 'Weekly Recommendation'),
                (string) ($recommendation['priority'] ?? 'medium'),
                $this->categoryForTitle((string) ($recommendation['title'] ?? '')),
                (string) ($recommendation['why'] ?? 'Weekly report surfaced this priority.'),
                $recommendation['recommended_plan_block'] ?? null,
                Arr::wrap($recommendation['players'] ?? []),
                [],
                $recommendation['estimated_minutes'] ?? null,
                (string) ($recommendation['source'] ?? 'weekly_rollup'),
            );
        }

        foreach (Arr::wrap($report['benchmark_submission_summary']['top_remaining_missing_metrics'] ?? []) as $metric) {
            if (! is_array($metric)) {
                continue;
            }
            $priorities[] = $this->priority(
                'Collect '.$this->metricLabel((string) ($metric['metric_key'] ?? $metric['display_name'] ?? 'Benchmark Baseline')),
                'medium',
                (string) ($metric['category'] ?? 'data_collection'),
                ($metric['display_name'] ?? 'A benchmark metric').' remains a team data gap.',
                $this->blockForMetric((string) ($metric['metric_key'] ?? 'benchmark')),
                Arr::wrap($metric['players_missing'] ?? []),
                [(string) ($metric['metric_key'] ?? '')],
                15,
                'benchmark_profile',
            );
        }

        if (empty($priorities)) {
            $priorities[] = $this->priority(
                'Build Next Week Plan',
                'low',
                'data_collection',
                'There are no urgent weekly blockers.',
                'Generate next week draft and review coach priorities.',
                [],
                [],
                15,
                'weekly_report',
            );
        }

        return collect($priorities)
            ->unique(fn (array $row): string => strtolower((string) ($row['title'] ?? '')))
            ->values()
            ->take(6)
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            })
            ->all();
    }

    private function buildPlayerReportRowsFromRollup(array $rollup): array
    {
        return collect(Arr::wrap($rollup['player_rollups'] ?? []))
            ->map(function (array $row): array {
                $missedItems = Arr::wrap($row['missed_items'] ?? []);
                $missedCount = collect($missedItems)->sum(fn (array $missed): int => (int) ($missed['missed_count'] ?? 0));
                $approved = (int) ($row['benchmark_values_approved'] ?? 0);
                $pending = (int) ($row['pending_review_count'] ?? 0);
                $corrections = (int) ($row['correction_requested_count'] ?? 0);
                $completion = (float) ($row['completion_percentage'] ?? 0);

                return [
                    'player_id' => (string) ($row['player_id'] ?? ''),
                    'player_name' => (string) ($row['player_name'] ?? 'Player'),
                    'plans_assigned' => (int) ($row['plans_assigned'] ?? 0),
                    'plans_completed' => (int) ($row['plans_completed'] ?? 0),
                    'completion_percentage' => round($completion, 1),
                    'benchmark_values_submitted' => (int) ($row['benchmark_values_submitted'] ?? 0),
                    'pending_review_count' => $pending,
                    'approved_count' => $approved,
                    'correction_requested_count' => $corrections,
                    'missed_items_count' => $missedCount,
                    'trusted_metrics_added' => Arr::wrap($row['trusted_metrics_added'] ?? []),
                    'next_needed_action' => $row['next_recommended_action'] ?? null,
                    'status_label' => $this->playerStatus($row, $completion, $pending, $corrections, $missedCount),
                ];
            })
            ->values()
            ->all();
    }

    private function buildTeamCompletion(array $rollup, array $playerRows): array
    {
        $plan = Arr::wrap($rollup['plan_execution_summary'] ?? []);
        $assignedPlayers = collect($playerRows)->filter(fn (array $row): bool => (int) ($row['plans_assigned'] ?? 0) > 0);
        $totalAssignments = (int) collect($playerRows)->sum('plans_assigned');
        $completedAssignments = (int) collect($playerRows)->sum('plans_completed');
        $notStartedAssignments = (int) collect($playerRows)
            ->filter(fn (array $row): bool => (float) ($row['completion_percentage'] ?? 0) <= 0.0 && (int) ($row['plans_assigned'] ?? 0) > 0)
            ->sum('plans_assigned');
        $inProgressAssignments = max(0, $totalAssignments - $completedAssignments - $notStartedAssignments);

        return [
            'assigned_player_count' => $assignedPlayers->count(),
            'plans_assigned' => (int) ($plan['plans_created'] ?? 0),
            'plans_published' => (int) ($plan['plans_published'] ?? 0),
            'total_assignments' => $totalAssignments,
            'completed_assignments' => $completedAssignments,
            'in_progress_assignments' => $inProgressAssignments,
            'not_started_assignments' => $notStartedAssignments,
            'team_completion_percentage' => $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 1) : 0.0,
            'average_player_completion_percentage' => $this->average(collect($playerRows)->pluck('completion_percentage')->all()),
            'players_completed_all' => collect($playerRows)->filter(fn (array $row): bool => (int) ($row['plans_assigned'] ?? 0) > 0 && (float) ($row['completion_percentage'] ?? 0) >= 100.0)->count(),
            'players_partially_completed' => collect($playerRows)->filter(fn (array $row): bool => (float) ($row['completion_percentage'] ?? 0) > 0.0 && (float) ($row['completion_percentage'] ?? 0) < 100.0)->count(),
            'players_not_started' => collect($playerRows)->filter(fn (array $row): bool => (int) ($row['plans_assigned'] ?? 0) > 0 && (float) ($row['completion_percentage'] ?? 0) <= 0.0)->count(),
        ];
    }

    private function buildReviewSummary(array $benchmarkReport): array
    {
        return [
            'pending_review_count' => (int) ($benchmarkReport['pending_review_count'] ?? 0),
            'oldest_pending_at' => collect(Arr::wrap($benchmarkReport['tasks_pending_review'] ?? []))
                ->pluck('submitted_at')
                ->filter()
                ->sort()
                ->first(),
            'tasks_pending_review' => Arr::wrap($benchmarkReport['tasks_pending_review'] ?? []),
            'correction_requested_count' => (int) ($benchmarkReport['correction_requested_count'] ?? 0),
            'players_needing_correction' => Arr::wrap($benchmarkReport['players_needing_correction'] ?? []),
        ];
    }

    private function buildTrustedDataSummary(array $rollup, string $teamId, array &$warnings): array
    {
        $trusted = Arr::wrap($rollup['trusted_data_summary'] ?? []);
        $profile = [];

        try {
            $profile = $this->teamBenchmarkProfileService->build($teamId, 365);
        } catch (Throwable $exception) {
            $warnings[] = 'Team benchmark profile unavailable for trusted data summary: '.$exception->getMessage();
        }

        return [
            'trusted_values_added' => (int) ($trusted['trusted_values_added'] ?? 0),
            'players_improved' => (int) ($trusted['players_improved'] ?? 0),
            'metrics_improved' => Arr::wrap($trusted['metrics_improved'] ?? []),
            'team_confidence_before' => null,
            'team_confidence_after' => $profile['benchmark_confidence'] ?? null,
            'source_mix_after' => Arr::wrap($profile['source_mix'] ?? []),
            'last_refresh_at' => $trusted['last_refresh_at'] ?? $trusted['last_promotion_at'] ?? null,
        ];
    }

    private function buildMissedWorkSummary(array $playerRows): array
    {
        $players = collect($playerRows)
            ->filter(fn (array $row): bool => (int) ($row['missed_items_count'] ?? 0) > 0 || ((int) ($row['plans_assigned'] ?? 0) > 0 && (float) ($row['completion_percentage'] ?? 0) < 100.0))
            ->map(fn (array $row): array => [
                'player_id' => $row['player_id'],
                'player_name' => $row['player_name'],
                'completion_percentage' => $row['completion_percentage'],
                'missed_items_count' => $row['missed_items_count'],
                'next_needed_action' => $row['next_needed_action'],
            ])
            ->values()
            ->all();

        return [
            'players_with_missed_work' => count($players),
            'missed_plan_count' => collect($playerRows)->filter(fn (array $row): bool => (int) ($row['plans_assigned'] ?? 0) > (int) ($row['plans_completed'] ?? 0))->count(),
            'missed_items_count' => (int) collect($playerRows)->sum('missed_items_count'),
            'players' => $players,
        ];
    }

    private function buildCurrentTeamIntelligence(string $teamId, array &$warnings): array
    {
        $profile = [];
        $decision = [];

        try {
            $profile = $this->teamBenchmarkProfileService->build($teamId, 365);
        } catch (Throwable $exception) {
            $warnings[] = 'Team benchmark profile unavailable: '.$exception->getMessage();
        }

        try {
            $decision = $this->decisionEngine->buildTeamDecisionBrief($teamId, 365);
        } catch (Throwable $exception) {
            $warnings[] = 'Decision brief unavailable: '.$exception->getMessage();
        }

        return [
            'benchmark_confidence' => $profile['benchmark_confidence'] ?? null,
            'source_mix' => Arr::wrap($profile['source_mix'] ?? []),
            'weakest_categories' => array_slice(Arr::wrap($profile['weakest_categories'] ?? []), 0, 5),
            'weakest_metrics' => array_slice(Arr::wrap($profile['weakest_metrics'] ?? []), 0, 5),
            'decision_brief' => [
                'primary_focus' => Arr::wrap($decision['primary_focus'] ?? []),
                'data_collection_priority' => Arr::wrap($decision['data_collection_priority'] ?? []),
                'players_needing_attention' => Arr::wrap($decision['players_needing_attention'] ?? []),
                'practice_focus' => Arr::wrap($decision['practice_focus'] ?? []),
                'evidence' => Arr::wrap($decision['evidence'] ?? []),
            ],
            'weekly_recommendations' => [],
        ];
    }

    private function buildExecutiveSummary(array $report): array
    {
        $team = $report['team_completion'] ?? [];
        $benchmark = $report['benchmark_submission_summary'] ?? [];
        $trusted = $report['trusted_data_summary'] ?? [];
        $followUps = Arr::wrap($report['coach_follow_ups'] ?? []);
        $wins = [];
        $concerns = [];

        if ((int) ($team['players_completed_all'] ?? 0) > 0) {
            $wins[] = (int) $team['players_completed_all'].' player(s) completed all assigned weekly work.';
        }
        if ((int) ($benchmark['approved_metric_count'] ?? 0) > 0) {
            $wins[] = (int) $benchmark['approved_metric_count'].' benchmark value(s) were approved.';
        }
        if ((int) ($trusted['trusted_values_added'] ?? 0) > 0) {
            $wins[] = (int) $trusted['trusted_values_added'].' trusted value(s) improved the team profile.';
        }
        if ((int) ($benchmark['pending_review_count'] ?? 0) > 0) {
            $concerns[] = (int) $benchmark['pending_review_count'].' submitted value(s) still need coach review.';
        }
        if ((int) ($benchmark['correction_requested_count'] ?? 0) > 0) {
            $concerns[] = (int) $benchmark['correction_requested_count'].' value(s) need correction.';
        }
        if ((int) ($team['players_not_started'] ?? 0) > 0) {
            $concerns[] = (int) $team['players_not_started'].' player(s) did not start assigned work.';
        }
        if (empty($wins)) {
            $wins[] = 'No weekly wins are available yet.';
        }
        if (empty($concerns)) {
            $concerns[] = 'No urgent weekly blockers are surfaced.';
        }

        $headline = (int) ($team['total_assignments'] ?? 0) === 0
            ? 'No daily plans were assigned this week.'
            : 'Team completed '.$this->fmt($team['team_completion_percentage'] ?? 0).'% of assigned work this week.';

        return [
            'headline' => $headline,
            'summary_text' => 'Players submitted '.(int) ($benchmark['submitted_metric_count'] ?? 0).' benchmark value(s). '
                .(int) ($benchmark['approved_metric_count'] ?? 0).' were approved, '
                .(int) ($benchmark['pending_review_count'] ?? 0).' are pending review, and '
                .(int) ($benchmark['correction_requested_count'] ?? 0).' need correction.',
            'wins' => $wins,
            'concerns' => $concerns,
            'next_best_action' => $followUps[0]['recommended_action'] ?? ($report['next_week_priorities'][0]['title'] ?? null),
        ];
    }

    private function topRemainingMissingMetrics(string $teamId): array
    {
        try {
            $profile = $this->teamBenchmarkProfileService->build($teamId, 365);
        } catch (Throwable) {
            return [];
        }

        return collect(Arr::wrap($profile['missing_metrics'] ?? []))
            ->filter(fn (array $row): bool => (int) ($row['missing_count'] ?? 0) > 0)
            ->sortByDesc(fn (array $row): int => (int) ($row['missing_count'] ?? 0))
            ->take(8)
            ->values()
            ->all();
    }

    private function reportStatus(array $teamCompletion, array $benchmarkReport, array $rollup): string
    {
        if (($rollup['summary_status'] ?? null) === 'failed') {
            return 'failed';
        }

        if ((int) ($teamCompletion['plans_assigned'] ?? 0) === 0 && (int) ($benchmarkReport['submitted_metric_count'] ?? 0) === 0) {
            return 'empty';
        }

        if ((int) ($benchmarkReport['pending_review_count'] ?? 0) > 0
            || (int) ($benchmarkReport['correction_requested_count'] ?? 0) > 0
            || (int) ($teamCompletion['players_partially_completed'] ?? 0) > 0
            || (int) ($teamCompletion['players_not_started'] ?? 0) > 0) {
            return 'partial';
        }

        return 'complete';
    }

    private function playerStatus(array $row, float $completion, int $pending, int $corrections, int $missedCount): string
    {
        if ($corrections > 0) {
            return 'needs_follow_up';
        }
        if ($pending > 0) {
            return 'pending_review';
        }
        if ((int) ($row['plans_assigned'] ?? 0) > 0 && ($completion <= 0.0 || $missedCount > 0)) {
            return 'missing_work';
        }
        if ((int) ($row['plans_assigned'] ?? 0) > 0 && $completion >= 100.0) {
            return 'complete';
        }

        return 'on_track';
    }

    private function followUp(string $title, string $priority, array $players, string $why, string $action, string $type): array
    {
        $playerRows = collect($players)
            ->map(fn (array $row): array => [
                'player_id' => (string) ($row['player_id'] ?? ''),
                'player_name' => (string) ($row['player_name'] ?? 'Player'),
            ])
            ->filter(fn (array $row): bool => $row['player_id'] !== '' || $row['player_name'] !== 'Player')
            ->unique(fn (array $row): string => $row['player_id'] ?: $row['player_name'])
            ->values()
            ->all();

        return [
            'title' => $title,
            'priority' => $priority,
            'player_ids' => collect($playerRows)->pluck('player_id')->filter()->values()->all(),
            'players' => $playerRows,
            'why' => $why,
            'recommended_action' => $action,
            'action_type' => $type,
        ];
    }

    private function priority(string $title, string $priority, string $category, string $why, ?string $block, array $players, array $metrics, ?int $minutes, string $source): array
    {
        return [
            'rank' => 0,
            'title' => $title,
            'priority' => $priority,
            'category' => $category ?: 'data_collection',
            'why' => $why,
            'suggested_block' => $block,
            'players' => $players,
            'metrics' => array_values(array_filter($metrics)),
            'estimated_minutes' => $minutes,
            'source' => $source,
        ];
    }

    private function emptyTeamCompletion(): array
    {
        return [
            'assigned_player_count' => 0,
            'plans_assigned' => 0,
            'plans_published' => 0,
            'total_assignments' => 0,
            'completed_assignments' => 0,
            'in_progress_assignments' => 0,
            'not_started_assignments' => 0,
            'team_completion_percentage' => 0.0,
            'average_player_completion_percentage' => 0.0,
            'players_completed_all' => 0,
            'players_partially_completed' => 0,
            'players_not_started' => 0,
        ];
    }

    private function emptyBenchmarkReport(array $overrides = []): array
    {
        return [
            'submitted_metric_count' => 0,
            'approved_metric_count' => 0,
            'pending_review_count' => 0,
            'rejected_count' => 0,
            'correction_requested_count' => 0,
            'trusted_values_promoted' => 0,
            'metrics_submitted' => [],
            'top_collected_metrics' => [],
            'top_remaining_missing_metrics' => [],
            'tasks_pending_review' => [],
            'players_needing_correction' => [],
            ...$overrides,
        ];
    }

    private function emptyReviewSummary(): array
    {
        return [
            'pending_review_count' => 0,
            'oldest_pending_at' => null,
            'tasks_pending_review' => [],
            'correction_requested_count' => 0,
            'players_needing_correction' => [],
        ];
    }

    private function emptyTrustedDataSummary(): array
    {
        return [
            'trusted_values_added' => 0,
            'players_improved' => 0,
            'metrics_improved' => [],
            'team_confidence_before' => null,
            'team_confidence_after' => null,
            'source_mix_after' => [],
            'last_refresh_at' => null,
        ];
    }

    private function emptyMissedWorkSummary(): array
    {
        return [
            'players_with_missed_work' => 0,
            'missed_plan_count' => 0,
            'missed_items_count' => 0,
            'players' => [],
        ];
    }

    private function submittedValues(BenchmarkCollectionTask $task): array
    {
        return $this->metricValuesFromPayloads($task, ['submitted_payload', 'approved_payload', 'payload.completion']);
    }

    private function approvedValues(BenchmarkCollectionTask $task): array
    {
        return $this->metricValuesFromPayloads($task, ['approved_payload']);
    }

    private function trustedValues(BenchmarkCollectionTask $task): array
    {
        foreach ([
            'promotion_result.trusted_payload.values',
            'promotion_result.trusted_payload.metric_values',
            'approved_payload.metric_values',
            'approved_payload.values',
        ] as $path) {
            $values = Arr::get($this->taskArray($task), $path);
            if (is_array($values)) {
                return collect($values)
                    ->reject(fn ($value): bool => $value === null || $value === '')
                    ->all();
            }
        }

        return [];
    }

    private function metricValuesFromPayloads(BenchmarkCollectionTask $task, array $payloadPaths): array
    {
        foreach ($payloadPaths as $base) {
            $payload = Arr::get($this->taskArray($task), $base, []);
            if (! is_array($payload)) {
                continue;
            }

            foreach (['metric_values', 'submitted_values', 'actuals', 'values', 'results'] as $key) {
                if (is_array($payload[$key] ?? null)) {
                    return collect($payload[$key])
                        ->reject(fn ($value): bool => $value === null || $value === '')
                        ->all();
                }
            }
        }

        return [];
    }

    private function taskArray(BenchmarkCollectionTask $task): array
    {
        return [
            'payload' => $task->payload ?? [],
            'submitted_payload' => $task->submitted_payload ?? [],
            'approved_payload' => $task->approved_payload ?? [],
            'promotion_result' => $task->promotion_result ?? [],
        ];
    }

    private function taskIsPromoted(BenchmarkCollectionTask $task): bool
    {
        return in_array((string) ($task->promotion_status ?? ''), [
            BenchmarkCollectionTask::PROMOTION_PROMOTED,
            BenchmarkCollectionTask::PROMOTION_PARTIAL,
        ], true);
    }

    private function valueSummaryRows(array $values): array
    {
        return collect($values)
            ->map(fn ($value, string|int $key): array => [
                'key' => (string) $key,
                'label' => $this->metricLabel((string) $key),
                'value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value,
            ])
            ->values()
            ->all();
    }

    private function playerRow(string $playerId): array
    {
        $name = null;
        if ($playerId !== '') {
            $name = PlayerTeam::query()
                ->with('user.profile')
                ->where('user_id', $playerId)
                ->first()?->user?->profile;
        }

        $full = trim((string) (($name?->first_name ?? '').' '.($name?->last_name ?? '')));

        return [
            'player_id' => $playerId,
            'player_name' => $full !== '' ? $full : 'Player '.$playerId,
        ];
    }

    private function hasMissingMetric(array $metrics, array $needles): bool
    {
        foreach ($metrics as $metric) {
            if (! is_array($metric)) {
                continue;
            }
            $key = strtolower((string) ($metric['metric_key'] ?? $metric['display_name'] ?? ''));
            foreach ($needles as $needle) {
                if (str_contains($key, strtolower($needle))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function metricLabel(string $key): string
    {
        return [
            'average_exit_velocity' => 'Average EV',
            'max_exit_velocity' => 'Max EV',
            'hard_hit_percentage' => 'Hard-Hit %',
            'line_drive_percentage' => 'Line-Drive %',
            'hitter_swing_miss_percentage' => 'Swing/Miss %',
            'average_fastball_velocity' => 'Average Fastball',
            'max_fastball_velocity' => 'Max Fastball',
            'strike_percentage' => 'Strike %',
            'long_toss_max_distance' => 'Long Toss Distance',
            'weighted_ball_5oz_velocity' => '5 oz Velocity',
            'bench_press' => 'Bench Press',
            'squat' => 'Squat',
            'deadlift' => 'Deadlift',
            'mobility_score' => 'Mobility Score',
            'player_context' => 'Roster Profile',
            'roster_profile' => 'Roster Profile',
        ][$key] ?? str($key)->replace(['_', '-'], ' ')->title()->toString();
    }

    private function metricCategory(string $key): string
    {
        return match (true) {
            str_contains($key, 'exit_velocity'), str_contains($key, 'hit'), str_contains($key, 'line_drive') => 'hitting',
            str_contains($key, 'fastball'), str_contains($key, 'strike') => 'pitching',
            str_contains($key, 'toss'), str_contains($key, 'weighted_ball') => 'throwing',
            str_contains($key, 'bench'), str_contains($key, 'squat'), str_contains($key, 'deadlift'), str_contains($key, 'pull'), str_contains($key, 'push') => 'strength',
            str_contains($key, 'dash'), str_contains($key, 'jump') => 'athletic',
            str_contains($key, 'mobility'), str_contains($key, 'shoulder'), str_contains($key, 'hip') => 'mobility',
            str_contains($key, 'roster'), str_contains($key, 'context'), str_contains($key, 'dob'), str_contains($key, 'position') => 'roster',
            default => 'data_collection',
        };
    }

    private function categoryForTitle(string $title): string
    {
        $text = strtolower($title);

        return match (true) {
            str_contains($text, 'exit'), str_contains($text, 'barrel'), str_contains($text, 'hit') => 'hitting',
            str_contains($text, 'fastball'), str_contains($text, 'bullpen'), str_contains($text, 'command') => 'pitching',
            str_contains($text, 'throw'), str_contains($text, 'toss'), str_contains($text, 'weighted') => 'throwing',
            str_contains($text, 'strength'), str_contains($text, 'power') => 'strength',
            str_contains($text, 'mobility'), str_contains($text, 'arm care') => 'mobility',
            str_contains($text, 'recovery') => 'recovery',
            str_contains($text, 'roster') => 'roster',
            default => 'data_collection',
        };
    }

    private function blockForMetric(string $metricKey): string
    {
        return match ($this->metricCategory($metricKey)) {
            'hitting' => 'Exit Velocity Baseline',
            'pitching' => 'Bullpen Command Baseline',
            'throwing' => 'Throwing Baseline',
            'strength' => 'Strength Baseline',
            'athletic' => 'Athletic Testing',
            'mobility' => 'Mobility Screen',
            'roster' => 'Roster Cleanup',
            default => 'Benchmark Collection Block',
        };
    }

    private function dateWindow(array $options): array
    {
        $days = max(1, min(365, (int) ($options['days'] ?? 7) ?: 7));
        $end = ! empty($options['end_date'])
            ? CarbonImmutable::parse((string) $options['end_date'])->endOfDay()
            : now()->toImmutable()->endOfDay();
        $start = ! empty($options['start_date'])
            ? CarbonImmutable::parse((string) $options['start_date'])->startOfDay()
            : $end->subDays($days - 1)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return [$start, $end];
    }

    private function weekLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return $start->format('M j').' - '.$end->format('M j, Y');
    }

    private function average(array $values): float
    {
        $numeric = collect($values)->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value);

        return $numeric->isEmpty() ? 0.0 : round($numeric->avg(), 1);
    }

    private function fmt(mixed $value): string
    {
        return number_format((float) $value, 1);
    }

    private function dateString(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
