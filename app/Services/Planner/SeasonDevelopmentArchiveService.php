<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Services\Intelligence\TeamBenchmarkProfileService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

class SeasonDevelopmentArchiveService
{
    public function __construct(
        private readonly WeeklyPlannerRollupService $weeklyPlannerRollupService,
        private readonly CoachWeeklyTeamReportService $coachWeeklyTeamReportService,
        private readonly CommunicationRhythmService $communicationRhythmService,
        private readonly TeamBenchmarkProfileService $teamBenchmarkProfileService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTeamSeasonArchive(string $teamId, array $options = []): array
    {
        [$start, $end, $weeks] = $this->seasonWindow($options);
        $warnings = [
            'Before/after benchmark snapshots are not persisted yet. Season archive uses best-effort weekly activity and current benchmark confidence.',
        ];

        try {
            $includePlayers = $this->bool($options['include_player_rows'] ?? true);
            $includeBenchmark = $this->bool($options['include_benchmark_progress'] ?? true);
            $includeCommunication = $this->bool($options['include_communication_rhythm'] ?? true);
            $includeReports = $this->bool($options['include_report_delivery'] ?? true);
            $includeWeeklyReports = $this->bool($options['include_weekly_reports'] ?? true);

            $timeline = $this->buildSeasonTimeline($teamId, $start->toDateString(), $end->toDateString(), [
                'include_player_rows' => $includePlayers,
                'include_report_delivery' => $includeReports || $includeCommunication,
                'include_weekly_reports' => $includeWeeklyReports,
            ]);
            $benchmark = $includeBenchmark
                ? $this->buildSeasonBenchmarkProgress($teamId, $start->toDateString(), $end->toDateString())
                : $this->emptyBenchmarkProgress();
            $communication = $includeCommunication || $includeReports
                ? $this->buildSeasonCommunicationSummary($teamId, $start->toDateString(), $end->toDateString())
                : $this->emptyCommunicationSummary();
            $players = $includePlayers
                ? $this->buildPlayerDevelopmentSummary($timeline)
                : [];
            $planner = $this->buildPlannerProgress($timeline, $players);
            $totals = $this->buildSeasonTotals($timeline, $benchmark, $planner, $communication, $weeks);

            $archive = [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'season_start_date' => $start->toDateString(),
                'season_end_date' => $end->toDateString(),
                'season_label' => $this->seasonLabel($start, $end),
                'archive_status' => $this->archiveStatus($totals, $warnings),
                'executive_summary' => [],
                'season_totals' => $totals,
                'weekly_timeline' => $timeline,
                'benchmark_progress' => $benchmark,
                'planner_progress' => $planner,
                'communication_summary' => $communication,
                'player_development_summary' => $players,
                'season_highlights' => [],
                'season_concerns' => [],
                'recommended_next_steps' => [],
                'warnings' => array_values(array_unique(array_filter($warnings))),
                'evidence' => [
                    'source' => 'weekly_rollups_benchmark_tasks_delivery_history_communication_rhythm',
                    'weeks_requested' => $weeks,
                    'include_player_rows' => $includePlayers,
                    'include_benchmark_progress' => $includeBenchmark,
                    'include_report_delivery' => $includeReports,
                    'include_communication_rhythm' => $includeCommunication,
                    'include_weekly_reports' => $includeWeeklyReports,
                    'read_only' => true,
                ],
            ];

            $coachSummary = $this->buildSeasonCoachSummary($archive);
            $archive['executive_summary'] = $coachSummary['executive_summary'];
            $archive['season_highlights'] = $coachSummary['season_highlights'];
            $archive['season_concerns'] = $coachSummary['season_concerns'];
            $archive['recommended_next_steps'] = $coachSummary['recommended_next_steps'];
            $archive['archive_status'] = $this->archiveStatus($totals, $archive['warnings']);

            return $archive;
        } catch (Throwable $exception) {
            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'season_start_date' => $start->toDateString(),
                'season_end_date' => $end->toDateString(),
                'season_label' => $this->seasonLabel($start, $end),
                'archive_status' => 'failed',
                'executive_summary' => [
                    'headline' => 'Season archive is not available yet.',
                    'summary_text' => 'FMTRX could not build the season archive from the current planner and report data.',
                    'top_wins' => [],
                    'top_concerns' => ['Season archive generation failed.'],
                    'season_story' => 'No season story is available until the archive can be generated.',
                    'next_best_action' => 'Refresh the archive after checking team data.',
                ],
                'season_totals' => $this->emptySeasonTotals($weeks),
                'weekly_timeline' => [],
                'benchmark_progress' => $this->emptyBenchmarkProgress(),
                'planner_progress' => $this->emptyPlannerProgress(),
                'communication_summary' => $this->emptyCommunicationSummary(),
                'player_development_summary' => [],
                'season_highlights' => [],
                'season_concerns' => ['Season archive generation failed.'],
                'recommended_next_steps' => ['Refresh the archive after checking team planner data.'],
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'exception' => class_basename($exception),
                    'read_only' => true,
                ],
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildSeasonTimeline(string $teamId, string $startDate, string $endDate, array $options = []): array
    {
        return $this->buildWeeklyRows($teamId, $startDate, $endDate, $options);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildWeeklyArchiveRows(string $teamId, string $startDate, string $endDate): array
    {
        return $this->buildWeeklyRows($teamId, $startDate, $endDate, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSeasonBenchmarkProgress(string $teamId, string $startDate, string $endDate): array
    {
        $report = $this->coachWeeklyTeamReportService->buildBenchmarkReport($teamId, $startDate, $endDate);
        $profile = $this->currentBenchmarkProfile($teamId);

        $trustedMetricRows = collect(Arr::wrap($report['metrics_submitted'] ?? []))
            ->filter(fn (array $metric): bool => (int) ($metric['trusted_count'] ?? 0) > 0)
            ->values();
        $players = $trustedMetricRows
            ->flatMap(fn (array $metric): array => Arr::wrap($metric['players'] ?? []))
            ->filter(fn (array $player): bool => (string) ($player['player_id'] ?? '') !== '')
            ->unique(fn (array $player): string => (string) ($player['player_id'] ?? ''))
            ->map(fn (array $player): array => [
                'player_id' => (string) ($player['player_id'] ?? ''),
                'player_name' => (string) ($player['player_name'] ?? 'Player'),
            ])
            ->values()
            ->all();

        return [
            'starting_benchmark_confidence' => null,
            'current_benchmark_confidence' => $profile['benchmark_confidence'] ?? null,
            'trusted_values_added' => (int) ($report['trusted_values_promoted'] ?? 0),
            'metrics_improved' => $trustedMetricRows
                ->map(fn (array $metric): string => (string) ($metric['metric_key'] ?? $metric['display_name'] ?? ''))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'players_with_new_trusted_data' => $players,
            'top_collected_metrics' => array_slice(Arr::wrap($report['top_collected_metrics'] ?? []), 0, 8),
            'remaining_missing_metrics' => array_slice(Arr::wrap($report['top_remaining_missing_metrics'] ?? []), 0, 8),
            'population_learning_status' => Arr::wrap($profile['source_mix'] ?? []),
            'submitted_metric_count' => (int) ($report['submitted_metric_count'] ?? 0),
            'approved_metric_count' => (int) ($report['approved_metric_count'] ?? 0),
            'pending_review_count' => (int) ($report['pending_review_count'] ?? 0),
            'correction_requested_count' => (int) ($report['correction_requested_count'] ?? 0),
            'evidence' => [
                'pending_values_excluded_from_trusted' => true,
                'rejected_values_excluded_from_trusted' => true,
                'source' => 'benchmark_collection_tasks_weekly_report_service',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSeasonCommunicationSummary(string $teamId, string $startDate, string $endDate): array
    {
        $rhythm = $this->safeCommunicationRhythm($teamId, $startDate, $endDate);
        $score = Arr::wrap($rhythm['rhythm_score'] ?? []);
        $health = Arr::wrap($rhythm['delivery_health_summary'] ?? []);
        $rows = collect(Arr::wrap($rhythm['weekly_rows'] ?? []));

        return [
            'reports_created' => (int) ($health['total_records'] ?? 0),
            'reports_shared' => (int) ($health['sent_count'] ?? 0) + (int) ($health['copy_only_count'] ?? 0),
            'parent_updates' => (int) ($score['weeks_with_parent_update'] ?? 0),
            'staff_reports' => (int) ($score['weeks_with_staff_report'] ?? 0),
            'player_summaries' => (int) ($score['weeks_with_player_summary'] ?? 0),
            'copy_only_count' => (int) ($health['copy_only_count'] ?? 0),
            'blocked_count' => (int) ($health['blocked_count'] ?? 0) + (int) ($health['unsupported_count'] ?? 0) + (int) ($health['failed_count'] ?? 0),
            'communication_rhythm_label' => $score['label'] ?? null,
            'communication_rhythm_score' => $score['score_0_100'] ?? null,
            'missed_communication_weeks' => Arr::wrap($rhythm['missed_weeks'] ?? []),
            'weekly_rows' => $rows->values()->all(),
            'recommended_actions' => Arr::wrap($rhythm['recommended_actions'] ?? []),
            'evidence' => [
                'source' => 'weekly_report_delivery_history',
                'private_report_bodies_exposed' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSeasonCoachSummary(array $archive): array
    {
        $totals = Arr::wrap($archive['season_totals'] ?? []);
        $benchmark = Arr::wrap($archive['benchmark_progress'] ?? []);
        $planner = Arr::wrap($archive['planner_progress'] ?? []);
        $communication = Arr::wrap($archive['communication_summary'] ?? []);
        $highlights = [];
        $concerns = [];
        $nextSteps = [];
        $hasActivity = (int) ($totals['daily_plans_created'] ?? 0) > 0
            || (int) ($totals['benchmark_values_submitted'] ?? 0) > 0
            || (int) ($totals['reports_created'] ?? 0) > 0;

        if (! $hasActivity) {
            return [
                'executive_summary' => [
                    'headline' => 'No season archive data found yet.',
                    'summary_text' => 'Assign plans, collect benchmark values, and create weekly reports to build the season archive.',
                    'top_wins' => ['No season highlights are available yet.'],
                    'top_concerns' => ['No planner, benchmark, or communication activity is recorded for this window.'],
                    'season_story' => 'This season window does not have enough activity for a development story yet.',
                    'next_best_action' => 'Assign a weekly plan and create the first weekly development update.',
                ],
                'season_highlights' => ['No season highlights are available yet.'],
                'season_concerns' => ['No planner, benchmark, or communication activity is recorded for this window.'],
                'recommended_next_steps' => ['Assign a weekly plan and create the first weekly development update.'],
            ];
        }

        if ((float) ($totals['average_completion_percentage'] ?? 0) >= 75.0) {
            $highlights[] = 'Team completed '.$this->fmt($totals['average_completion_percentage']).'% of assigned development work.';
        }
        if ((int) ($totals['trusted_values_promoted'] ?? 0) > 0) {
            $highlights[] = (int) $totals['trusted_values_promoted'].' trusted benchmark value(s) were promoted this season.';
        }
        if ((int) ($communication['parent_updates'] ?? 0) > 0) {
            $highlights[] = 'Parent updates were created in '.(int) $communication['parent_updates'].' week(s).';
        }
        if (! empty($benchmark['top_collected_metrics'][0])) {
            $metric = $benchmark['top_collected_metrics'][0];
            $highlights[] = ($metric['display_name'] ?? $this->human($metric['metric_key'] ?? 'Benchmark')).' was the most collected benchmark metric.';
        }

        if ((int) ($totals['pending_reviews_remaining'] ?? 0) > 0) {
            $concerns[] = (int) $totals['pending_reviews_remaining'].' benchmark value(s) are still pending coach review.';
            $nextSteps[] = 'Review all pending benchmark submissions.';
        }
        if (! empty($benchmark['remaining_missing_metrics'])) {
            $concerns[] = 'Benchmark baselines remain incomplete for some players.';
            $nextSteps[] = 'Finish missing benchmark baselines for priority metrics.';
        }
        if ((int) ($planner['players_needing_follow_up_count'] ?? 0) > 0) {
            $concerns[] = (int) $planner['players_needing_follow_up_count'].' player(s) need follow-up for missed or incomplete work.';
            $nextSteps[] = 'Follow up with players who missed assigned development work.';
        }
        if ((float) ($communication['communication_rhythm_score'] ?? 0) < 60.0) {
            $concerns[] = 'Communication rhythm needs a more consistent weekly update cadence.';
            $nextSteps[] = 'Create a parent-safe or staff weekly development update.';
        }

        if (empty($highlights)) {
            $highlights[] = 'No season highlights are available yet.';
        }
        if (empty($concerns)) {
            $concerns[] = 'No urgent season concerns are surfaced.';
        }
        if (empty($nextSteps)) {
            $nextSteps[] = 'Generate next week\'s plan from the latest benchmark profile.';
        }

        $headline = ((int) ($totals['daily_plans_created'] ?? 0) === 0 && (int) ($totals['reports_created'] ?? 0) === 0)
            ? 'No season archive data found yet.'
            : 'Season archive covers '.(int) ($totals['weeks_analyzed'] ?? 0).' week(s) with '.(int) ($totals['daily_plans_published'] ?? 0).' published plan(s).';

        $summaryText = 'FMTRX tracked '.(int) ($totals['assigned_workouts'] ?? 0).' assigned workout(s), '
            .(int) ($totals['completed_workouts'] ?? 0).' completed workout(s), '
            .(int) ($totals['benchmark_values_approved'] ?? 0).' approved benchmark value(s), and '
            .(int) ($totals['reports_sent_or_copied'] ?? 0).' shared report action(s).';

        return [
            'executive_summary' => [
                'headline' => $headline,
                'summary_text' => $summaryText,
                'top_wins' => array_slice($highlights, 0, 4),
                'top_concerns' => array_slice($concerns, 0, 4),
                'season_story' => $this->seasonStory($archive, $highlights, $concerns),
                'next_best_action' => $nextSteps[0] ?? null,
            ],
            'season_highlights' => array_values(array_unique($highlights)),
            'season_concerns' => array_values(array_unique($concerns)),
            'recommended_next_steps' => array_values(array_unique($nextSteps)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildWeeklyRows(string $teamId, string $startDate, string $endDate, array $options): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfWeek();
        $end = CarbonImmutable::parse($endDate)->endOfWeek();
        $includePlayers = $this->bool($options['include_player_rows'] ?? true);
        $includeReports = $this->bool($options['include_report_delivery'] ?? true);
        $communicationRows = $includeReports
            ? collect(Arr::wrap($this->safeCommunicationRhythm($teamId, $start->toDateString(), $end->toDateString())['weekly_rows'] ?? []))
                ->keyBy(fn (array $row): string => (string) ($row['week_start_date'] ?? ''))
            : collect();
        $rows = [];
        $index = 1;

        for ($weekStart = $start; $weekStart->lessThanOrEqualTo($end); $weekStart = $weekStart->addWeek()) {
            $weekEnd = $weekStart->endOfWeek();
            $rollup = $this->weeklyPlannerRollupService->buildTeamWeeklyRollup($teamId, [
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'include_players' => $includePlayers,
                'include_benchmark_intelligence' => false,
            ]);
            $plan = Arr::wrap($rollup['plan_execution_summary'] ?? []);
            $benchmark = Arr::wrap($rollup['benchmark_collection_summary'] ?? []);
            $review = Arr::wrap($rollup['review_summary'] ?? []);
            $trusted = Arr::wrap($rollup['trusted_data_summary'] ?? []);
            $recommendations = Arr::wrap($rollup['next_week_recommendations'] ?? []);
            $communication = Arr::wrap($communicationRows->get($weekStart->toDateString(), []));
            $reportsCreated = (int) ($communication['prepared_count'] ?? 0)
                + (int) ($communication['copy_only_count'] ?? 0)
                + (int) ($communication['sent_count'] ?? 0)
                + (int) ($communication['blocked_count'] ?? 0)
                + (int) ($communication['failed_count'] ?? 0);
            $reportsShared = (int) ($communication['copy_only_count'] ?? 0) + (int) ($communication['sent_count'] ?? 0);
            $pending = (int) ($review['pending_review_count'] ?? 0);
            $completion = $plan['average_completion_percentage'] ?? null;

            $rows[] = [
                'week_index' => $index++,
                'week_start_date' => $weekStart->toDateString(),
                'week_end_date' => $weekEnd->toDateString(),
                'week_label' => (string) ($rollup['week_label'] ?? $weekStart->format('M j').' - '.$weekEnd->format('M j, Y')),
                'status_label' => $this->weeklyStatus($plan, $benchmark, $pending, $reportsCreated, $communication),
                'headline' => $this->weeklyHeadline($plan, $benchmark, $reportsShared, $pending),
                'plans_published' => (int) ($plan['plans_published'] ?? 0),
                'plans_created' => (int) ($plan['plans_created'] ?? 0),
                'assigned_workouts' => (int) ($plan['total_assigned_players'] ?? 0),
                'completed_workouts' => (int) ($plan['total_completed_assignments'] ?? 0),
                'team_completion_percentage' => $completion === null ? null : round((float) $completion, 1),
                'benchmark_values_submitted' => (int) ($benchmark['metric_values_submitted'] ?? 0),
                'benchmark_values_approved' => (int) ($benchmark['metric_values_approved'] ?? 0),
                'trusted_values_promoted' => (int) ($trusted['trusted_values_added'] ?? $benchmark['trusted_values_promoted'] ?? 0),
                'pending_review_count' => $pending,
                'reports_created' => $reportsCreated,
                'reports_shared' => $reportsShared,
                'primary_focus' => $recommendations[0]['title'] ?? Arr::get($rollup, 'intelligence_changes.primary_focus_after'),
                'top_collected_metrics' => array_slice(Arr::wrap($benchmark['metrics_collected'] ?? []), 0, 5),
                'top_remaining_gaps' => array_slice(Arr::wrap($benchmark['top_missing_metrics_remaining'] ?? []), 0, 5),
                'coach_notes' => [],
                'warnings' => array_values(array_unique(array_filter([
                    ...Arr::wrap($rollup['warnings'] ?? []),
                    $communication['recommended_action'] ?? null,
                ]))),
                'player_rollups' => $includePlayers ? Arr::wrap($rollup['player_rollups'] ?? []) : [],
                'evidence' => [
                    'rollup_status' => $rollup['summary_status'] ?? null,
                    'communication_status' => $communication['status_label'] ?? null,
                    'private_report_bodies_exposed' => false,
                ],
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $timeline
     * @return array<string, mixed>
     */
    private function buildPlannerProgress(array $timeline, array $playerRows): array
    {
        $rows = collect($timeline);
        $focuses = $rows
            ->pluck('primary_focus')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->map(fn (int $count, string $focus): array => [
                'focus' => $focus,
                'week_count' => $count,
            ])
            ->values()
            ->all();
        $missed = $rows
            ->filter(fn (array $row): bool => in_array((string) ($row['status_label'] ?? ''), ['missed', 'incomplete'], true))
            ->map(fn (array $row): array => [
                'week_label' => $row['week_label'],
                'status_label' => $row['status_label'],
                'completion_percentage' => $row['team_completion_percentage'],
                'plans_published' => $row['plans_published'],
            ])
            ->values()
            ->all();

        return [
            'plans_created' => (int) $rows->sum('plans_created'),
            'plans_published' => (int) $rows->sum('plans_published'),
            'completion_percentage' => $this->average($rows->pluck('team_completion_percentage')->filter(fn ($value): bool => $value !== null)->all()),
            'players_completed_all_count' => collect($playerRows)->filter(fn (array $row): bool => (int) ($row['plans_assigned'] ?? 0) > 0 && (float) ($row['completion_percentage'] ?? 0) >= 100.0)->count(),
            'players_needing_follow_up_count' => collect($playerRows)->filter(fn (array $row): bool => (int) ($row['plans_assigned'] ?? 0) > 0 && (float) ($row['completion_percentage'] ?? 0) < 100.0)->count(),
            'most_common_plan_focuses' => array_slice($focuses, 0, 8),
            'missed_work_trends' => array_slice($missed, 0, 8),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $timeline
     * @return array<int, array<string, mixed>>
     */
    private function buildPlayerDevelopmentSummary(array $timeline): array
    {
        $players = [];

        foreach ($timeline as $week) {
            foreach (Arr::wrap($week['player_rollups'] ?? []) as $row) {
                $playerId = (string) ($row['player_id'] ?? '');
                if ($playerId === '') {
                    continue;
                }
                $players[$playerId] ??= [
                    'player_id' => $playerId,
                    'player_name' => (string) ($row['player_name'] ?? 'Player'),
                    'plans_assigned' => 0,
                    'plans_completed' => 0,
                    'completion_samples' => [],
                    'benchmark_values_submitted' => 0,
                    'benchmark_values_approved' => 0,
                    'trusted_metrics_added' => [],
                    'pending_review_count' => 0,
                    'correction_requested_count' => 0,
                    'development_notes' => [],
                    'next_recommended_action' => null,
                ];
                $players[$playerId]['plans_assigned'] += (int) ($row['plans_assigned'] ?? 0);
                $players[$playerId]['plans_completed'] += (int) ($row['plans_completed'] ?? 0);
                if ((int) ($row['plans_assigned'] ?? 0) > 0) {
                    $players[$playerId]['completion_samples'][] = (float) ($row['completion_percentage'] ?? 0);
                }
                $players[$playerId]['benchmark_values_submitted'] += (int) ($row['benchmark_values_submitted'] ?? 0);
                $players[$playerId]['benchmark_values_approved'] += (int) ($row['benchmark_values_approved'] ?? 0);
                $players[$playerId]['pending_review_count'] += (int) ($row['pending_review_count'] ?? 0);
                $players[$playerId]['correction_requested_count'] += (int) ($row['correction_requested_count'] ?? 0);
                $players[$playerId]['trusted_metrics_added'] = array_values(array_unique([
                    ...Arr::wrap($players[$playerId]['trusted_metrics_added']),
                    ...Arr::wrap($row['trusted_metrics_added'] ?? []),
                ]));
                if (! empty($row['missed_items'])) {
                    $players[$playerId]['development_notes'][] = count(Arr::wrap($row['missed_items'])).' week(s) included missed items.';
                }
                if (! empty($row['next_recommended_action'])) {
                    $players[$playerId]['next_recommended_action'] = (string) $row['next_recommended_action'];
                }
            }
        }

        return collect($players)
            ->map(function (array $row): array {
                $samples = Arr::wrap($row['completion_samples'] ?? []);
                $row['completion_percentage'] = ! empty($samples)
                    ? $this->average($samples)
                    : $this->percent((int) $row['plans_completed'], max(1, (int) $row['plans_assigned']));
                unset($row['completion_samples']);
                $row['development_notes'] = array_values(array_unique(array_filter($row['development_notes'])));
                $row['next_recommended_action'] = $row['next_recommended_action'] ?: $this->playerNextAction($row);

                return $row;
            })
            ->sortBy('player_name')
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $timeline
     * @return array<string, mixed>
     */
    private function buildSeasonTotals(array $timeline, array $benchmark, array $planner, array $communication, int $weeks): array
    {
        $rows = collect($timeline);

        return [
            'weeks_analyzed' => $weeks,
            'daily_plans_created' => (int) $rows->sum('plans_created'),
            'daily_plans_published' => (int) $rows->sum('plans_published'),
            'assigned_workouts' => (int) $rows->sum('assigned_workouts'),
            'completed_workouts' => (int) $rows->sum('completed_workouts'),
            'average_completion_percentage' => (float) ($planner['completion_percentage'] ?? 0.0),
            'benchmark_values_submitted' => (int) ($benchmark['submitted_metric_count'] ?? $rows->sum('benchmark_values_submitted')),
            'benchmark_values_approved' => (int) ($benchmark['approved_metric_count'] ?? $rows->sum('benchmark_values_approved')),
            'trusted_values_promoted' => (int) ($benchmark['trusted_values_added'] ?? $rows->sum('trusted_values_promoted')),
            'pending_reviews_remaining' => (int) ($benchmark['pending_review_count'] ?? $rows->sum('pending_review_count')),
            'reports_created' => (int) ($communication['reports_created'] ?? $rows->sum('reports_created')),
            'reports_sent_or_copied' => (int) ($communication['reports_shared'] ?? $rows->sum('reports_shared')),
            'parent_updates_created' => (int) ($communication['parent_updates'] ?? 0),
            'staff_reports_created' => (int) ($communication['staff_reports'] ?? 0),
            'communication_rhythm_score' => $communication['communication_rhythm_score'] ?? null,
        ];
    }

    private function weeklyStatus(array $plan, array $benchmark, int $pending, int $reportsCreated, array $communication): string
    {
        if ($pending > 0 || (int) ($communication['blocked_count'] ?? 0) > 0 || (int) ($communication['failed_count'] ?? 0) > 0) {
            return 'needs_review';
        }
        if ((int) ($plan['plans_created'] ?? 0) === 0 && (int) ($benchmark['metric_values_submitted'] ?? 0) === 0 && $reportsCreated === 0) {
            return 'missed';
        }
        if ((int) ($plan['plans_published'] ?? 0) > 0 && (float) ($plan['average_completion_percentage'] ?? 0) >= 80.0 && $reportsCreated > 0) {
            return 'strong';
        }
        if ((float) ($plan['average_completion_percentage'] ?? 0) >= 50.0 || (int) ($benchmark['metric_values_approved'] ?? 0) > 0 || $reportsCreated > 0) {
            return 'solid';
        }

        return 'incomplete';
    }

    private function weeklyHeadline(array $plan, array $benchmark, int $reportsShared, int $pending): string
    {
        if ((int) ($plan['plans_created'] ?? 0) === 0 && (int) ($benchmark['metric_values_submitted'] ?? 0) === 0 && $reportsShared === 0) {
            return 'No planner, benchmark, or communication activity recorded.';
        }
        if ($pending > 0) {
            return $pending.' benchmark value(s) need coach review.';
        }
        if ((int) ($plan['plans_published'] ?? 0) > 0) {
            return (int) $plan['plans_published'].' plan(s) published with '.$this->fmt($plan['average_completion_percentage'] ?? 0).'% completion.';
        }
        if ((int) ($benchmark['metric_values_approved'] ?? 0) > 0) {
            return (int) $benchmark['metric_values_approved'].' benchmark value(s) approved.';
        }

        return $reportsShared.' report action(s) shared.';
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int}
     */
    private function seasonWindow(array $options): array
    {
        $weeks = max(1, min(52, (int) ($options['weeks'] ?? 12) ?: 12));
        $end = ! empty($options['season_end_date'])
            ? CarbonImmutable::parse((string) $options['season_end_date'])->endOfWeek()
            : CarbonImmutable::now()->endOfWeek();
        $start = ! empty($options['season_start_date'])
            ? CarbonImmutable::parse((string) $options['season_start_date'])->startOfWeek()
            : $end->startOfWeek()->subWeeks($weeks - 1);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfWeek(), $start->endOfWeek()];
        }

        $weeks = max(1, (int) floor($start->startOfWeek()->diffInDays($end->endOfWeek()) / 7) + 1);

        return [$start, $end, $weeks];
    }

    private function safeCommunicationRhythm(string $teamId, string $startDate, string $endDate): array
    {
        try {
            return $this->communicationRhythmService->buildTeamRhythm($teamId, [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        } catch (Throwable) {
            return [
                'rhythm_score' => [],
                'weekly_rows' => [],
                'audience_summary' => [],
                'delivery_health_summary' => [],
                'missed_weeks' => [],
                'recommended_actions' => [],
            ];
        }
    }

    private function currentBenchmarkProfile(string $teamId): array
    {
        try {
            return $this->teamBenchmarkProfileService->build($teamId, 365);
        } catch (Throwable) {
            return [];
        }
    }

    private function archiveStatus(array $totals, array $warnings): string
    {
        if ((int) ($totals['daily_plans_created'] ?? 0) === 0
            && (int) ($totals['benchmark_values_submitted'] ?? 0) === 0
            && (int) ($totals['reports_created'] ?? 0) === 0) {
            return 'empty';
        }

        return empty($warnings) ? 'complete' : 'partial';
    }

    private function playerNextAction(array $row): ?string
    {
        if ((int) ($row['pending_review_count'] ?? 0) > 0) {
            return 'Coach review needed.';
        }
        if ((int) ($row['correction_requested_count'] ?? 0) > 0) {
            return 'Resubmit corrected benchmark values.';
        }
        if ((int) ($row['plans_assigned'] ?? 0) > 0 && (float) ($row['completion_percentage'] ?? 0) < 100.0) {
            return 'Follow up on missed assigned work.';
        }
        if ((int) ($row['benchmark_values_approved'] ?? 0) > 0) {
            return 'Use approved data in the next development plan.';
        }

        return null;
    }

    private function seasonStory(array $archive, array $highlights, array $concerns): string
    {
        $label = (string) ($archive['season_label'] ?? 'This season');
        $totals = Arr::wrap($archive['season_totals'] ?? []);

        return $label.' shows '.(int) ($totals['daily_plans_published'] ?? 0).' published plan(s), '
            .$this->fmt($totals['average_completion_percentage'] ?? 0).'% average completion, and '
            .(int) ($totals['reports_sent_or_copied'] ?? 0).' shared report action(s). '
            .'Top win: '.($highlights[0] ?? 'More data is needed.').' '
            .'Top concern: '.($concerns[0] ?? 'No urgent concern surfaced.');
    }

    private function emptySeasonTotals(int $weeks = 0): array
    {
        return [
            'weeks_analyzed' => $weeks,
            'daily_plans_created' => 0,
            'daily_plans_published' => 0,
            'assigned_workouts' => 0,
            'completed_workouts' => 0,
            'average_completion_percentage' => 0.0,
            'benchmark_values_submitted' => 0,
            'benchmark_values_approved' => 0,
            'trusted_values_promoted' => 0,
            'pending_reviews_remaining' => 0,
            'reports_created' => 0,
            'reports_sent_or_copied' => 0,
            'parent_updates_created' => 0,
            'staff_reports_created' => 0,
            'communication_rhythm_score' => null,
        ];
    }

    private function emptyBenchmarkProgress(): array
    {
        return [
            'starting_benchmark_confidence' => null,
            'current_benchmark_confidence' => null,
            'trusted_values_added' => 0,
            'metrics_improved' => [],
            'players_with_new_trusted_data' => [],
            'top_collected_metrics' => [],
            'remaining_missing_metrics' => [],
            'population_learning_status' => [],
        ];
    }

    private function emptyPlannerProgress(): array
    {
        return [
            'plans_created' => 0,
            'plans_published' => 0,
            'completion_percentage' => 0.0,
            'players_completed_all_count' => 0,
            'players_needing_follow_up_count' => 0,
            'most_common_plan_focuses' => [],
            'missed_work_trends' => [],
        ];
    }

    private function emptyCommunicationSummary(): array
    {
        return [
            'reports_created' => 0,
            'reports_shared' => 0,
            'parent_updates' => 0,
            'staff_reports' => 0,
            'player_summaries' => 0,
            'copy_only_count' => 0,
            'blocked_count' => 0,
            'communication_rhythm_label' => null,
            'communication_rhythm_score' => null,
            'missed_communication_weeks' => [],
        ];
    }

    private function seasonLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return $start->format('M j, Y').' - '.$end->format('M j, Y');
    }

    private function average(array $values): float
    {
        $numbers = collect($values)
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): float => (float) $value)
            ->values();

        return $numbers->isEmpty() ? 0.0 : round($numbers->avg(), 1);
    }

    private function percent(int|float $part, int|float $total): float
    {
        return $total > 0 ? round(((float) $part / (float) $total) * 100, 1) : 0.0;
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function fmt(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 1, '.', '');
    }

    private function human(mixed $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', (string) ($value ?: 'Benchmark')));
    }
}
