<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\BenchmarkCollectionTask;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use App\Models\DailyPlanRevision;
use App\Services\Intelligence\BenchmarkTaskPersistenceService;
use App\Services\Intelligence\BenchmarkTaskReviewService;
use App\Services\Intelligence\BenchmarkPracticePlanDailyPlannerAdapter;
use App\Services\Intelligence\BenchmarkRefreshService;
use App\Services\Intelligence\BenchmarkTrustedDataPromotionService;
use App\Services\Intelligence\PracticePlanUpdateSuggestionService;
use App\Services\Intelligence\TeamBenchmarkProfileService;
use Illuminate\Support\Arr;
use Throwable;

class CoachPlannerCommandCenterService
{
    public function __construct(
        private readonly DailyPlanPlayerUpdateService $playerUpdateService,
        private readonly DailyPlanReminderService $dailyPlanReminderService,
        private readonly BenchmarkTaskPersistenceService $taskPersistence,
        private readonly BenchmarkTaskReviewService $taskReviewService,
        private readonly BenchmarkTrustedDataPromotionService $trustedDataPromotionService,
        private readonly BenchmarkRefreshService $benchmarkRefreshService,
        private readonly BenchmarkPracticePlanDailyPlannerAdapter $dailyPlannerAdapter,
        private readonly TeamBenchmarkProfileService $teamBenchmarkProfileService,
        private readonly PracticePlanUpdateSuggestionService $practicePlanUpdateSuggestionService,
    ) {
    }

    public function buildForTeam(string $teamId, array $options = []): array
    {
        $dailyPlanId = $this->nullableString($options['daily_plan_id'] ?? $options['dailyPlanId'] ?? null);
        if ($dailyPlanId) {
            return $this->buildForDailyPlan($dailyPlanId, $options);
        }

        $plan = $this->activeDailyPlanForTeam($teamId);
        if (! $plan) {
            $payload = $this->emptyPayload($teamId, null, [
                'No active Daily Plan was found for this team.',
            ], $options);
            $payload['next_actions'] = $this->buildNextActions($payload);

            return $payload;
        }

        return $this->buildForDailyPlan((string) $plan->id, $options);
    }

    public function buildForDailyPlan(string $dailyPlanId, array $options = []): array
    {
        $plan = DailyPlan::query()
            ->with(['assignments.user.profile', 'progress', 'revisions'])
            ->whereKey($dailyPlanId)
            ->first();

        if (! $plan) {
            $payload = $this->emptyPayload((string) ($options['team_id'] ?? ''), $dailyPlanId, [
                'Daily Plan was not found.',
            ], $options);
            $payload['next_actions'] = $this->buildNextActions($payload);

            return $payload;
        }

        $teamId = (string) $plan->team_id;
        $warnings = [];
        $evidence = [
            'daily_plan_loaded' => true,
            'daily_plan_id' => (string) $plan->id,
            'team_id' => $teamId,
        ];

        $taskRows = $this->taskRowsForPlan($teamId, (string) $plan->id);
        $taskRowsByPlayer = collect($taskRows['tasks'])
            ->groupBy(fn (array $task): string => (string) ($task['assigned_to_player_id'] ?? ''))
            ->map(fn ($rows): array => collect($rows)->values()->all())
            ->all();
        $evidence['benchmark_task_scope'] = $taskRows['scope'];
        $evidence['benchmark_task_count'] = count($taskRows['tasks']);

        $playerRows = $this->buildPlayerRows((string) $plan->id, [
            'tasks_by_player' => $taskRowsByPlayer,
        ]);
        $playerSummary = $this->playerStatusSummary($playerRows);
        $planStatus = $this->planStatus($plan, $playerSummary, $warnings, (int) ($options['days'] ?? 365));
        $benchmarkWorkflow = $this->benchmarkWorkflowSummary($teamId, $taskRows['tasks']);
        $reviewQueue = $this->reviewQueueSummary($teamId, $taskRows['tasks']);
        $trustedData = $this->trustedDataSummary($teamId, $taskRows['tasks']);
        $remainingGaps = $this->remainingBenchmarkGaps($teamId, (int) ($options['days'] ?? 365), $warnings);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'daily_plan_id' => (string) $plan->id,
            'plan_status' => $planStatus,
            'player_status_summary' => $playerSummary,
            'player_rows' => $playerRows,
            'benchmark_workflow_summary' => $benchmarkWorkflow,
            'review_queue_summary' => $reviewQueue,
            'trusted_data_summary' => $trustedData,
            'remaining_benchmark_gaps' => $remainingGaps,
            'next_actions' => [],
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'evidence' => $evidence,
        ];
        $payload['next_actions'] = $this->buildNextActions($payload);

        return $payload;
    }

    public function buildPlayerRows(string $dailyPlanId, array $options = []): array
    {
        $plan = DailyPlan::query()
            ->with(['assignments.user.profile', 'progress'])
            ->whereKey($dailyPlanId)
            ->first();

        if (! $plan) {
            return [];
        }

        $ackRows = collect(Arr::wrap($this->safe(fn (): array => $this->playerUpdateService->buildTeamAcknowledgementStatus($dailyPlanId), [])['players_not_acknowledged'] ?? []))
            ->keyBy(fn (array $row): string => (string) ($row['player_id'] ?? ''));
        $ackGoodRows = collect(Arr::wrap($this->safe(fn (): array => $this->playerUpdateService->buildTeamAcknowledgementStatus($dailyPlanId), [])['players_acknowledged'] ?? []))
            ->keyBy(fn (array $row): string => (string) ($row['player_id'] ?? ''));

        $progressByUser = $plan->progress
            ->keyBy(fn (DailyPlanProgress $progress): string => (string) $progress->user_id);
        $totalItems = $this->countPlanItems(is_array($plan->buckets) ? $plan->buckets : []);
        $tasksByPlayer = $options['tasks_by_player'] ?? [];

        return $plan->assignments
            ->map(function (DailyPlanAssignment $assignment) use ($progressByUser, $totalItems, $tasksByPlayer, $ackRows, $ackGoodRows): array {
                $playerId = (string) $assignment->user_id;
                $progress = $progressByUser->get($playerId);
                $tasks = Arr::wrap($tasksByPlayer[$playerId] ?? []);
                $completedItems = $this->completedPlanItemCount($progress);
                $actualTotalItems = max($totalItems, $this->progressItemCount($progress));
                $completionPercentage = $actualTotalItems > 0
                    ? round(($completedItems / $actualTotalItems) * 100, 1)
                    : 0.0;
                $taskCounts = $this->taskCounts($tasks);
                $ack = $ackGoodRows->get($playerId) ?? $ackRows->get($playerId) ?? [];
                $acknowledged = (bool) ($ack['acknowledged'] ?? false);
                $completed = $progress?->completed_at !== null || ($actualTotalItems > 0 && $completedItems >= $actualTotalItems);
                $started = $progress?->started_at !== null || $completed || $this->progressItemCount($progress) > 0;

                return [
                    'player_id' => $playerId,
                    'player_name' => $this->assignmentPlayerName($assignment),
                    'assigned' => true,
                    'acknowledged' => $acknowledged,
                    'acknowledged_at' => $ack['acknowledged_at'] ?? null,
                    'started' => $started,
                    'completed' => $completed,
                    'completed_items' => $completedItems,
                    'total_items' => $actualTotalItems,
                    'completion_percentage' => $completionPercentage,
                    'benchmark_items_completed' => $taskCounts['completed'],
                    'benchmark_values_submitted' => $taskCounts['submitted_values'],
                    'pending_review_count' => $taskCounts['pending_review'],
                    'approved_count' => $taskCounts['approved'],
                    'correction_requested_count' => $taskCounts['correction_requested'],
                    'last_activity_at' => $this->latestDate([
                        $progress?->updated_at?->toIso8601String(),
                        $progress?->started_at?->toIso8601String(),
                        $progress?->completed_at?->toIso8601String(),
                        $ack['acknowledged_at'] ?? null,
                        ...$this->taskDates($tasks),
                    ]),
                    'next_needed_action' => $this->playerNextAction($acknowledged, $started, $completed, $taskCounts),
                ];
            })
            ->values()
            ->all();
    }

    public function buildNextActions(array $status): array
    {
        $actions = [];
        $teamId = (string) ($status['team_id'] ?? '');
        $plan = $status['plan_status'] ?? [];
        $summary = $status['player_status_summary'] ?? [];
        $benchmark = $status['benchmark_workflow_summary'] ?? [];
        $review = $status['review_queue_summary'] ?? [];
        $trusted = $status['trusted_data_summary'] ?? [];
        $gaps = Arr::wrap($status['remaining_benchmark_gaps'] ?? []);
        $dailyPlanId = $status['daily_plan_id'] ?? null;

        if (! $dailyPlanId) {
            $actions[] = $this->nextAction(
                'Generate Today\'s Plan',
                'critical',
                'No active Daily Plan is saved for this team.',
                'Create or save today\'s Daily Plan so players have one clear workout path.',
                'coach',
                [],
                'Generate Next Plan',
                'generate_next_plan',
                $teamId,
                null,
            );

            return $actions;
        }

        if (($plan['status'] ?? 'unknown') === 'draft') {
            $actions[] = $this->nextAction(
                'Publish and Assign Plan',
                'critical',
                'The Daily Plan is still a draft, so players cannot act on it yet.',
                'Publish the plan and confirm the assigned players.',
                'coach',
                [],
                'Publish Plan',
                'publish_plan',
                $teamId,
                $dailyPlanId,
            );
        }

        if ((int) ($summary['not_acknowledged_count'] ?? 0) > 0) {
            $actions[] = $this->nextAction(
                'Send Player Reminders',
                'high',
                ((int) $summary['not_acknowledged_count']).' assigned player(s) have not acknowledged the latest plan update.',
                'Send the existing Daily Plan reminder so players review the update before training.',
                'players',
                $this->playerIdsByNeed($status['player_rows'] ?? [], 'acknowledge'),
                'Send Reminder',
                'send_reminder',
                $teamId,
                $dailyPlanId,
            );
        }

        if ((int) ($summary['assigned_count'] ?? 0) > 0 && (int) ($summary['started_count'] ?? 0) === 0 && ($plan['status'] ?? '') !== 'draft') {
            $actions[] = $this->nextAction(
                'Check Readiness Before Start',
                'medium',
                'No assigned players have started the workout yet.',
                'Confirm readiness and make sure the team opens the player workout screen.',
                'team',
                [],
                null,
                'none',
                $teamId,
                $dailyPlanId,
            );
        }

        if ((int) ($review['pending_review_count'] ?? 0) > 0) {
            $actions[] = $this->nextAction(
                'Review Submitted Benchmark Values',
                'critical',
                ((int) $review['pending_review_count']).' benchmark submission(s) are waiting on coach review.',
                'Open the benchmark review queue, approve trusted values, or request corrections.',
                'coach',
                array_values(array_unique(array_filter(array_map(
                    fn (array $task): string => (string) ($task['player_id'] ?? ''),
                    Arr::wrap($review['tasks_pending_review'] ?? [])
                )))),
                'Review Submissions',
                'review_submissions',
                $teamId,
                $dailyPlanId,
            );
        }

        if ((int) ($trusted['awaiting_promotion_count'] ?? 0) > 0) {
            $actions[] = $this->nextAction(
                'Promote Trusted Benchmark Data',
                'high',
                ((int) $trusted['awaiting_promotion_count']).' approved benchmark task(s) are waiting to be promoted.',
                'Promote approved values into the trusted benchmark data workflow.',
                'coach',
                [],
                'Promote Trusted Data',
                'promote_trusted_data',
                $teamId,
                $dailyPlanId,
            );
        }

        if ((int) ($trusted['trusted_values_added'] ?? 0) > 0 && empty($trusted['last_refresh_at'])) {
            $actions[] = $this->nextAction(
                'Refresh Benchmark Intelligence',
                'medium',
                'Trusted benchmark values were added and the command center does not see a refresh timestamp yet.',
                'Refresh team benchmarks so the new values affect profiles, gaps, and recommendations.',
                'coach',
                [],
                'Refresh Intelligence',
                'refresh_intelligence',
                $teamId,
                $dailyPlanId,
            );
        }

        if (! empty($gaps)) {
            $topGap = $gaps[0];
            $actions[] = $this->nextAction(
                'Add Missing Baseline Block',
                $this->priority((string) ($topGap['priority'] ?? 'medium')),
                'Benchmark gaps remain, led by '.($topGap['display_name'] ?? 'missing baseline data').'.',
                'Add or keep a short baseline collection block in the next plan.',
                'team',
                array_values(array_filter(array_map(
                    fn (array $player): string => (string) ($player['player_id'] ?? ''),
                    Arr::wrap($topGap['players'] ?? [])
                ))),
                'Collect Baselines',
                'collect_baselines',
                $teamId,
                $dailyPlanId,
            );
        }

        if (($plan['latest_revision_number'] ?? null) !== null) {
            $actions[] = $this->nextAction(
                'View Acknowledgement Status',
                'low',
                'The Daily Plan has revision history that players may need to acknowledge.',
                'Open the acknowledgement and revision status for this plan.',
                'coach',
                [],
                'View Acknowledgements',
                'acknowledge_status',
                $teamId,
                $dailyPlanId,
            );
        }

        if (empty($actions)) {
            $actions[] = $this->nextAction(
                'Plan Looks Current',
                'low',
                'Assigned work is complete or no urgent planner workflow is waiting.',
                'Mark the day complete or generate the next plan when ready.',
                'coach',
                [],
                null,
                'none',
                $teamId,
                $dailyPlanId,
            );
        }

        return collect($actions)
            ->sortBy(fn (array $action): int => $this->priorityRank($action['priority'] ?? 'low'))
            ->values()
            ->take(6)
            ->all();
    }

    public function runAction(string $teamId, string $actionType, array $payload = [], ?string $actorUserId = null): array
    {
        $days = max(7, min(365, (int) ($payload['days'] ?? Arr::get($payload, 'options.days', 365))));
        $dailyPlanId = $this->nullableString($payload['daily_plan_id'] ?? $payload['dailyPlanId'] ?? null);
        $taskIds = $this->stringList(Arr::wrap($payload['task_ids'] ?? $payload['taskIds'] ?? []));
        $playerIds = $this->stringList(Arr::wrap($payload['player_ids'] ?? $payload['playerIds'] ?? []));
        $message = $this->nullableString($payload['message'] ?? null);
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        $dryRun = (bool) ($payload['dry_run'] ?? $payload['dryRun'] ?? false);
        $warnings = [];
        $result = [];

        if ($dryRun) {
            return $this->actionResult($teamId, $dailyPlanId, $actionType, 'skipped', 'Dry run: action was not executed.', [
                'would_execute' => $actionType,
                'daily_plan_id' => $dailyPlanId,
                'task_ids' => $taskIds,
                'player_ids' => $playerIds,
                'message_required' => $actionType === 'request_corrections',
            ], [], $days);
        }

        try {
            switch ($actionType) {
                case 'publish_plan':
                    $plan = $this->teamPlan($teamId, $dailyPlanId);
                    if (! $plan) {
                        return $this->actionResult($teamId, $dailyPlanId, $actionType, 'failed', 'Daily Plan was not found for this team.', [], ['No matching Daily Plan found.'], $days);
                    }

                    if ($plan->status === 'published') {
                        return $this->actionResult($teamId, (string) $plan->id, $actionType, 'skipped', 'Plan is already published.', ['plan_id' => (string) $plan->id], [], $days);
                    }

                    $plan->status = 'published';
                    $plan->published_at ??= now();
                    $plan->save();
                    $result = [
                        'plan_id' => (string) $plan->id,
                        'status' => $plan->status,
                        'published_at' => $plan->published_at?->toIso8601String(),
                    ];

                    return $this->actionResult($teamId, (string) $plan->id, $actionType, 'completed', 'Plan published.', $result, [], $days);

                case 'send_reminder':
                    $plan = $this->teamPlan($teamId, $dailyPlanId);
                    if (! $plan) {
                        return $this->actionResult($teamId, $dailyPlanId, $actionType, 'failed', 'Daily Plan was not found for this team.', [], ['No matching Daily Plan found.'], $days);
                    }

                    $result = empty($playerIds)
                        ? $this->dailyPlanReminderService->sendReminderToUnacknowledged((string) $plan->id, $actorUserId, $options)
                        : $this->dailyPlanReminderService->sendReminderToPlayers((string) $plan->id, $playerIds, $actorUserId, $options);
                    $selectedCount = (int) Arr::get($result, 'send_result.selected_player_count', $result['unacknowledged_count'] ?? 0);
                    $status = $selectedCount > 0 ? 'completed' : 'skipped';
                    $warnings = Arr::wrap($result['warnings'] ?? []);

                    return $this->actionResult($teamId, (string) $plan->id, $actionType, $status, $selectedCount > 0 ? 'Reminder prepared for '.$selectedCount.' player(s).' : 'No players need a reminder.', $result, $warnings, $days);

                case 'review_submissions':
                    $result = $this->taskReviewService->listPendingReviewTasks($teamId);

                    return $this->actionResult($teamId, $dailyPlanId, $actionType, 'completed', ((int) ($result['pending_count'] ?? 0)).' submission(s) pending review.', $result, Arr::wrap($result['warnings'] ?? []), $days);

                case 'approve_values':
                    if (empty($taskIds)) {
                        return $this->actionResult($teamId, $dailyPlanId, $actionType, 'skipped', 'Select one or more review tasks before approving.', [], ['No task IDs were provided.'], $days);
                    }

                    $result = $this->reviewSelectedTasks($teamId, $taskIds, fn (string $taskId): array => $this->taskReviewService->approveTask($taskId, $actorUserId, [
                        'days' => $days,
                    ]));

                    return $this->actionResult($teamId, $dailyPlanId, $actionType, $result['failed_count'] > 0 ? 'partial' : 'completed', $result['approved_count'].' task(s) approved.', $result, $result['warnings'], $days);

                case 'request_corrections':
                    if (empty($taskIds)) {
                        return $this->actionResult($teamId, $dailyPlanId, $actionType, 'skipped', 'Select one or more review tasks before requesting corrections.', [], ['No task IDs were provided.'], $days);
                    }

                    if (! $message) {
                        return $this->actionResult($teamId, $dailyPlanId, $actionType, 'skipped', 'Add a correction message before sending.', [], ['Correction message is required.'], $days);
                    }

                    $result = $this->reviewSelectedTasks($teamId, $taskIds, fn (string $taskId): array => $this->taskReviewService->requestCorrection($taskId, $message, $actorUserId), 'correction_requested_count');

                    return $this->actionResult($teamId, $dailyPlanId, $actionType, $result['failed_count'] > 0 ? 'partial' : 'completed', $result['correction_requested_count'].' correction request(s) sent.', $result, $result['warnings'], $days);

                case 'promote_trusted_data':
                    if (! empty($taskIds)) {
                        $result = $this->promoteSelectedTasks($teamId, $taskIds, $actorUserId, $days);

                        return $this->actionResult($teamId, $dailyPlanId, $actionType, $result['failed_count'] > 0 ? 'partial' : 'completed', $result['promoted_count'].' task(s) promoted.', $result, $result['warnings'], $days);
                    }

                    $result = $this->trustedDataPromotionService->promoteTeamApprovedTasks($teamId, [
                        'promoted_by_user_id' => $actorUserId,
                        'days' => $days,
                    ]);
                    $warnings = Arr::wrap($result['warnings'] ?? []);

                    return $this->actionResult($teamId, $dailyPlanId, $actionType, ((int) ($result['failed_count'] ?? 0)) > 0 ? 'partial' : 'completed', ((int) ($result['promoted_count'] ?? 0)).' approved task(s) promoted.', $result, $warnings, $days);

                case 'refresh_intelligence':
                    $result = $this->benchmarkRefreshService->refreshTeamBenchmarks($teamId, $days);

                    return $this->actionResult($teamId, $dailyPlanId, $actionType, 'completed', 'Benchmark intelligence refreshed.', $result, Arr::wrap($result['warnings'] ?? []), $days);

                case 'generate_next_plan':
                    $result = $this->dailyPlannerAdapter->previewMapping($teamId, $days);

                    return $this->actionResult($teamId, $dailyPlanId, $actionType, 'completed', 'Generated next Daily Plan preview. Nothing was published.', $result, Arr::wrap($result['warnings'] ?? []), $days, false);

                case 'acknowledge_status':
                    $plan = $this->teamPlan($teamId, $dailyPlanId);
                    if (! $plan) {
                        return $this->actionResult($teamId, $dailyPlanId, $actionType, 'failed', 'Daily Plan was not found for this team.', [], ['No matching Daily Plan found.'], $days);
                    }

                    $result = $this->playerUpdateService->buildTeamAcknowledgementStatus((string) $plan->id);

                    return $this->actionResult($teamId, (string) $plan->id, $actionType, 'completed', 'Acknowledgement status loaded.', $result, Arr::wrap($result['warnings'] ?? []), $days);

                case 'open_daily_planner':
                case 'view_revision_history':
                case 'assign_plan':
                case 'collect_baselines':
                case 'none':
                    return $this->actionResult($teamId, $dailyPlanId, $actionType, 'skipped', 'This action opens an existing screen or guidance and does not run a backend workflow.', [
                        'target_route' => $this->targetRouteForAction($actionType, $teamId, $dailyPlanId),
                    ], [], $days);

                default:
                    return $this->actionResult($teamId, $dailyPlanId, $actionType, 'failed', 'Unknown command center action.', [], ['Unsupported action type: '.$actionType], $days);
            }
        } catch (Throwable $exception) {
            return $this->actionResult($teamId, $dailyPlanId, $actionType, 'failed', 'Could not complete action. Try again.', [
                'exception' => class_basename($exception),
            ], [$exception->getMessage()], $days);
        }
    }

    private function emptyPayload(string $teamId, ?string $dailyPlanId, array $warnings, array $options = []): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'daily_plan_id' => $dailyPlanId,
            'plan_status' => [
                'daily_plan_id' => $dailyPlanId,
                'title' => null,
                'status' => 'unknown',
                'scheduled_for' => null,
                'published_at' => null,
                'latest_revision_number' => null,
                'latest_revision_at' => null,
                'has_unpublished_suggestions' => false,
                'estimated_total_minutes' => null,
                'block_count' => 0,
                'benchmark_generated' => false,
            ],
            'player_status_summary' => $this->playerStatusSummary([]),
            'player_rows' => [],
            'benchmark_workflow_summary' => [
                'benchmark_items_total' => 0,
                'benchmark_items_completed' => 0,
                'submitted_metric_count' => 0,
                'pending_review_count' => 0,
                'approved_count' => 0,
                'promoted_count' => 0,
                'trusted_payload_only_count' => 0,
                'manual_review_count' => 0,
                'refresh_status' => null,
                'rescore_status' => null,
            ],
            'review_queue_summary' => [
                'pending_review_count' => 0,
                'oldest_pending_at' => null,
                'tasks_pending_review' => [],
            ],
            'trusted_data_summary' => [
                'trusted_values_added' => 0,
                'players_improved' => 0,
                'metrics_improved' => [],
                'last_promotion_at' => null,
                'last_refresh_at' => null,
            ],
            'remaining_benchmark_gaps' => $teamId !== '' ? $this->remainingBenchmarkGaps($teamId, (int) ($options['days'] ?? 365), $warnings) : [],
            'next_actions' => [],
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'evidence' => [
                'daily_plan_loaded' => false,
                'days' => (int) ($options['days'] ?? 365),
            ],
        ];
    }

    private function activeDailyPlanForTeam(string $teamId): ?DailyPlan
    {
        $today = now()->toDateString();

        return DailyPlan::query()
            ->where('team_id', $teamId)
            ->where('status', '!=', 'template')
            ->orderByRaw('CASE WHEN date = ? THEN 0 WHEN date > ? THEN 1 ELSE 2 END', [$today, $today])
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function planStatus(DailyPlan $plan, array $playerSummary, array &$warnings, int $days): array
    {
        $latestRevision = $plan->revisions
            ->sortByDesc('revision_number')
            ->first();
        $suggestions = $this->safe(fn (): array => $this->practicePlanUpdateSuggestionService->suggestUpdatesForDailyPlan((string) $plan->id, [
            'days' => $days,
        ]), []);
        if (! empty($suggestions['warnings'])) {
            $warnings = [...$warnings, ...Arr::wrap($suggestions['warnings'])];
        }

        return [
            'daily_plan_id' => (string) $plan->id,
            'title' => $this->nullableString($plan->name),
            'status' => $this->derivedPlanStatus($plan, $playerSummary),
            'scheduled_for' => $plan->date?->format('Y-m-d'),
            'published_at' => $plan->published_at?->toIso8601String(),
            'latest_revision_number' => $latestRevision ? (int) $latestRevision->revision_number : null,
            'latest_revision_at' => $latestRevision?->created_at?->toIso8601String(),
            'has_unpublished_suggestions' => count(Arr::wrap($suggestions['suggestions'] ?? [])) > 0,
            'estimated_total_minutes' => $plan->estimated_minutes !== null
                ? (int) $plan->estimated_minutes
                : $this->estimateMinutes(is_array($plan->buckets) ? $plan->buckets : []),
            'block_count' => count(Arr::wrap($plan->buckets)),
            'benchmark_generated' => $this->hasBenchmarkGenerated(is_array($plan->buckets) ? $plan->buckets : []),
        ];
    }

    private function derivedPlanStatus(DailyPlan $plan, array $summary): string
    {
        $status = (string) ($plan->status ?? 'unknown');
        if ($status === 'draft') {
            return 'draft';
        }

        if ((int) ($summary['assigned_count'] ?? 0) > 0 && (int) ($summary['completed_count'] ?? 0) >= (int) ($summary['assigned_count'] ?? 0)) {
            return 'completed';
        }

        if ((int) ($summary['started_count'] ?? 0) > 0) {
            return 'in_progress';
        }

        if ($status === 'published' && (int) ($summary['assigned_count'] ?? 0) > 0) {
            return 'sent';
        }

        return in_array($status, ['published', 'dismissed'], true) ? $status : 'unknown';
    }

    private function playerStatusSummary(array $rows): array
    {
        $assigned = count($rows);
        $acknowledged = count(array_filter($rows, fn (array $row): bool => (bool) ($row['acknowledged'] ?? false)));
        $started = count(array_filter($rows, fn (array $row): bool => (bool) ($row['started'] ?? false)));
        $completed = count(array_filter($rows, fn (array $row): bool => (bool) ($row['completed'] ?? false)));
        $pendingReview = array_sum(array_map(fn (array $row): int => (int) ($row['pending_review_count'] ?? 0), $rows));
        $approved = array_sum(array_map(fn (array $row): int => (int) ($row['approved_count'] ?? 0), $rows));
        $corrections = array_sum(array_map(fn (array $row): int => (int) ($row['correction_requested_count'] ?? 0), $rows));

        return [
            'assigned_count' => $assigned,
            'acknowledged_count' => $acknowledged,
            'not_acknowledged_count' => max(0, $assigned - $acknowledged),
            'started_count' => $started,
            'completed_count' => $completed,
            'not_started_count' => max(0, $assigned - $started),
            'pending_review_count' => $pendingReview,
            'approved_submission_count' => $approved,
            'correction_requested_count' => $corrections,
            'completion_percentage' => $assigned > 0 ? round(($completed / $assigned) * 100, 1) : 0.0,
            'acknowledgement_percentage' => $assigned > 0 ? round(($acknowledged / $assigned) * 100, 1) : 0.0,
        ];
    }

    private function taskRowsForPlan(string $teamId, string $dailyPlanId): array
    {
        $result = $this->safe(fn (): array => $this->taskPersistence->listTeamTasks($teamId), []);
        $all = collect(Arr::wrap($result['tasks'] ?? []))->values();
        $linked = $all
            ->filter(fn (array $task): bool => $this->taskDailyPlanId($task) === $dailyPlanId)
            ->values();

        if ($linked->isNotEmpty()) {
            return [
                'scope' => 'daily_plan_linked',
                'tasks' => $linked->all(),
            ];
        }

        return [
            'scope' => 'team_active_fallback',
            'tasks' => $all
                ->filter(fn (array $task): bool => ! in_array((string) ($task['status'] ?? ''), [
                    BenchmarkCollectionTask::STATUS_DISMISSED,
                ], true))
                ->values()
                ->all(),
        ];
    }

    private function benchmarkWorkflowSummary(string $teamId, array $tasks): array
    {
        $trusted = $this->safe(fn (): array => $this->trustedDataPromotionService->buildPromotionStatus($teamId), []);

        return [
            'benchmark_items_total' => count($tasks),
            'benchmark_items_completed' => count(array_filter($tasks, fn (array $task): bool => ($task['status'] ?? null) === BenchmarkCollectionTask::STATUS_COMPLETED)),
            'submitted_metric_count' => array_sum(array_map(fn (array $task): int => $this->submittedValueCount($task), $tasks)),
            'pending_review_count' => count(array_filter($tasks, fn (array $task): bool => ($task['review_status'] ?? null) === BenchmarkCollectionTask::REVIEW_PENDING)),
            'approved_count' => count(array_filter($tasks, fn (array $task): bool => ($task['review_status'] ?? null) === BenchmarkCollectionTask::REVIEW_APPROVED)),
            'promoted_count' => count(array_filter($tasks, fn (array $task): bool => in_array((string) ($task['promotion_status'] ?? ''), [
                BenchmarkCollectionTask::PROMOTION_PROMOTED,
                BenchmarkCollectionTask::PROMOTION_PARTIAL,
            ], true))),
            'trusted_payload_only_count' => count(array_filter($tasks, fn (array $task): bool => ($task['promotion_mode'] ?? null) === BenchmarkCollectionTask::MODE_TRUSTED_PAYLOAD_ONLY)),
            'manual_review_count' => (int) ($trusted['manual_review_count'] ?? count(array_filter($tasks, fn (array $task): bool => ($task['promotion_mode'] ?? null) === BenchmarkCollectionTask::MODE_MANUAL_REVIEW))),
            'refresh_status' => $this->latestPromotionNestedValue($tasks, 'refresh.refresh_status'),
            'rescore_status' => $this->latestPromotionNestedValue($tasks, 'rescore.rescore_status'),
        ];
    }

    private function reviewQueueSummary(string $teamId, array $tasks): array
    {
        $reviewSummary = $this->safe(fn (): array => $this->taskReviewService->buildTeamReviewSummary($teamId), []);
        $pending = collect($tasks)
            ->filter(fn (array $task): bool => ($task['review_status'] ?? null) === BenchmarkCollectionTask::REVIEW_PENDING)
            ->values();
        if ($pending->isEmpty()) {
            $pending = collect(Arr::wrap($reviewSummary['pending_tasks'] ?? []));
        }

        return [
            'pending_review_count' => $pending->count(),
            'oldest_pending_at' => $pending
                ->map(fn (array $task): ?string => $task['submitted_at'] ?? $task['completed_at'] ?? $task['updated_at'] ?? null)
                ->filter()
                ->sort()
                ->first(),
            'tasks_pending_review' => $pending
                ->take(10)
                ->map(fn (array $task): array => [
                    'task_id' => $task['task_id'] ?? $task['id'] ?? null,
                    'player_id' => $task['player_id'] ?? $task['assigned_to_player_id'] ?? null,
                    'player_name' => $task['player_name'] ?? $task['assigned_to_player_name'] ?? 'Player',
                    'title' => $task['title'] ?? 'Benchmark Task',
                    'submitted_at' => $task['submitted_at'] ?? null,
                    'submitted_values_summary' => $task['submitted_values_summary'] ?? $this->submittedValuesSummary($task),
                ])
                ->values()
                ->all(),
        ];
    }

    private function trustedDataSummary(string $teamId, array $tasks): array
    {
        $trusted = $this->safe(fn (): array => $this->trustedDataPromotionService->buildPromotionStatus($teamId), []);
        $promoted = collect(Arr::wrap($trusted['promoted_tasks'] ?? []));
        if ($promoted->isEmpty()) {
            $promoted = collect($tasks)
                ->filter(fn (array $task): bool => in_array((string) ($task['promotion_status'] ?? ''), [
                    BenchmarkCollectionTask::PROMOTION_PROMOTED,
                    BenchmarkCollectionTask::PROMOTION_PARTIAL,
                ], true))
                ->values();
        }

        return [
            'trusted_values_added' => $promoted->sum(fn (array $task): int => count($this->trustedPayloadValues($task))),
            'awaiting_promotion_count' => (int) ($trusted['awaiting_promotion_count'] ?? 0),
            'players_improved' => $promoted
                ->pluck('assigned_to_player_id')
                ->filter()
                ->unique()
                ->count(),
            'metrics_improved' => $promoted
                ->flatMap(fn (array $task): array => array_keys($this->trustedPayloadValues($task)))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'last_promotion_at' => $promoted
                ->pluck('promoted_at')
                ->filter()
                ->sort()
                ->last(),
            'last_refresh_at' => $promoted
                ->map(fn (array $task): ?string => Arr::get($task, 'promotion_result.refresh.refreshed_at') ?? Arr::get($task, 'promotion_result.rescore.generated_at'))
                ->filter()
                ->sort()
                ->last(),
        ];
    }

    private function remainingBenchmarkGaps(string $teamId, int $days, array &$warnings): array
    {
        $profile = $this->safe(fn (): array => $this->teamBenchmarkProfileService->build($teamId, $days), []);
        if (! empty($profile['warnings'])) {
            $warnings = [...$warnings, ...Arr::wrap($profile['warnings'])];
        }

        return collect(Arr::wrap($profile['missing_metrics'] ?? []))
            ->map(function (array $gap): array {
                $players = collect(Arr::wrap($gap['players_missing'] ?? $gap['players'] ?? []))
                    ->map(fn (array $player): array => [
                        'player_id' => (string) ($player['player_id'] ?? ''),
                        'player_name' => (string) ($player['player_name'] ?? 'Player'),
                        'missing_fields' => Arr::wrap($player['missing_fields'] ?? []),
                    ])
                    ->filter(fn (array $player): bool => $player['player_id'] !== '')
                    ->values()
                    ->all();

                return [
                    'display_name' => $this->displayLabel((string) ($gap['display_name'] ?? $gap['metric_key'] ?? 'Benchmark Gap')),
                    'category' => (string) ($gap['category'] ?? 'benchmark'),
                    'missing_count' => (int) ($gap['missing_count'] ?? count($players)),
                    'eligible_count' => (int) ($gap['eligible_count'] ?? $gap['player_count'] ?? max(1, count($players))),
                    'players' => $players,
                    'priority' => $this->gapPriority($gap),
                ];
            })
            ->filter(fn (array $gap): bool => (int) $gap['missing_count'] > 0)
            ->sortBy(fn (array $gap): int => $this->priorityRank($gap['priority']))
            ->values()
            ->take(10)
            ->all();
    }

    private function taskCounts(array $tasks): array
    {
        return [
            'completed' => count(array_filter($tasks, fn (array $task): bool => ($task['status'] ?? null) === BenchmarkCollectionTask::STATUS_COMPLETED)),
            'submitted_values' => array_sum(array_map(fn (array $task): int => $this->submittedValueCount($task), $tasks)),
            'pending_review' => count(array_filter($tasks, fn (array $task): bool => ($task['review_status'] ?? null) === BenchmarkCollectionTask::REVIEW_PENDING)),
            'approved' => count(array_filter($tasks, fn (array $task): bool => ($task['review_status'] ?? null) === BenchmarkCollectionTask::REVIEW_APPROVED)),
            'correction_requested' => count(array_filter($tasks, fn (array $task): bool => ($task['review_status'] ?? null) === BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED)),
        ];
    }

    private function taskDailyPlanId(array $task): ?string
    {
        foreach ([
            'daily_plan_id',
            'submitted_payload.daily_plan_id',
            'approved_payload.daily_plan_id',
            'payload.daily_plan_id',
            'payload.completion.daily_plan_id',
            'payload.progress.payload.daily_plan_id',
            'promotion_result.trusted_payload.daily_plan_id',
        ] as $path) {
            $value = Arr::get($task, $path);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function submittedValueCount(array $task): int
    {
        return count($this->submittedMetricValues($task));
    }

    private function submittedMetricValues(array $task): array
    {
        foreach (['submitted_payload', 'approved_payload', 'payload.completion'] as $base) {
            $payload = Arr::get($task, $base, []);
            if (! is_array($payload)) {
                continue;
            }

            foreach (['metric_values', 'submitted_values', 'actuals', 'values', 'results'] as $key) {
                if (is_array($payload[$key] ?? null)) {
                    return collect($payload[$key])
                        ->filter(fn ($value): bool => $value !== null && $value !== '')
                        ->all();
                }
            }
        }

        return [];
    }

    private function submittedValuesSummary(array $task): array
    {
        return collect($this->submittedMetricValues($task))
            ->map(fn ($value, string|int $key): array => [
                'key' => (string) $key,
                'label' => $this->displayLabel((string) $key),
                'value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value,
            ])
            ->values()
            ->all();
    }

    private function trustedPayloadValues(array $task): array
    {
        foreach ([
            'promotion_result.trusted_payload.values',
            'promotion_result.trusted_payload.metric_values',
            'approved_payload.metric_values',
            'approved_payload.values',
        ] as $path) {
            $values = Arr::get($task, $path);
            if (is_array($values)) {
                return collect($values)
                    ->filter(fn ($value): bool => $value !== null && $value !== '')
                    ->all();
            }
        }

        return [];
    }

    private function completedPlanItemCount(?DailyPlanProgress $progress): int
    {
        if (! $progress || ! is_array($progress->items)) {
            return 0;
        }

        return collect($progress->items)
            ->filter(fn ($item): bool => $this->itemIsCompleted($item))
            ->count();
    }

    private function progressItemCount(?DailyPlanProgress $progress): int
    {
        return $progress && is_array($progress->items) ? count($progress->items) : 0;
    }

    private function itemIsCompleted(mixed $item): bool
    {
        if ($item === true) {
            return true;
        }

        if (! is_array($item)) {
            return false;
        }

        return (bool) ($item['completed'] ?? false)
            || in_array((string) ($item['status'] ?? ''), ['complete', 'completed', 'done'], true)
            || ! empty($item['completed_at'])
            || ! empty($item['actuals'])
            || ! empty($item['values'])
            || ! empty($item['metric_values']);
    }

    private function countPlanItems(array $buckets): int
    {
        return collect($buckets)
            ->sum(fn (array $bucket): int => count(Arr::wrap($bucket['items'] ?? [])));
    }

    private function estimateMinutes(array $buckets): int
    {
        return $this->countPlanItems($buckets) * 4;
    }

    private function hasBenchmarkGenerated(array $buckets): bool
    {
        foreach ($buckets as $bucket) {
            if ($this->arrayContainsBenchmarkSignal($bucket)) {
                return true;
            }
        }

        return false;
    }

    private function arrayContainsBenchmarkSignal(array $value): bool
    {
        foreach ($value as $key => $child) {
            $key = (string) $key;
            if (str_contains($key, 'benchmark') || str_contains($key, 'metric')) {
                return true;
            }

            if (is_array($child) && $this->arrayContainsBenchmarkSignal($child)) {
                return true;
            }

            if (is_string($child) && str_contains(strtolower($child), 'benchmark')) {
                return true;
            }
        }

        return false;
    }

    private function assignmentPlayerName(DailyPlanAssignment $assignment): string
    {
        $profile = $assignment->user?->profile;
        $name = trim((string) (($profile?->first_name ?? '').' '.($profile?->last_name ?? '')));

        return $name !== '' ? $name : 'Player '.$assignment->user_id;
    }

    private function playerNextAction(bool $acknowledged, bool $started, bool $completed, array $taskCounts): ?string
    {
        if (! $acknowledged) {
            return 'Acknowledge updated plan';
        }

        if ((int) ($taskCounts['pending_review'] ?? 0) > 0) {
            return 'Coach review needed';
        }

        if ((int) ($taskCounts['correction_requested'] ?? 0) > 0) {
            return 'Correction requested';
        }

        if (! $started) {
            return 'Start workout';
        }

        if (! $completed) {
            return 'Finish remaining work';
        }

        return 'Complete';
    }

    private function playerIdsByNeed(array $rows, string $need): array
    {
        return collect($rows)
            ->filter(function (array $row) use ($need): bool {
                return match ($need) {
                    'acknowledge' => ! (bool) ($row['acknowledged'] ?? false),
                    default => false,
                };
            })
            ->pluck('player_id')
            ->filter()
            ->values()
            ->all();
    }

    private function taskDates(array $tasks): array
    {
        return collect($tasks)
            ->flatMap(fn (array $task): array => [
                $task['submitted_at'] ?? null,
                $task['reviewed_at'] ?? null,
                $task['completed_at'] ?? null,
                $task['promoted_at'] ?? null,
                $task['updated_at'] ?? null,
            ])
            ->filter()
            ->values()
            ->all();
    }

    private function latestDate(array $dates): ?string
    {
        return collect($dates)
            ->filter()
            ->map(fn (string $date): string => $date)
            ->sort()
            ->last();
    }

    private function latestPromotionNestedValue(array $tasks, string $path): ?string
    {
        return collect($tasks)
            ->sortByDesc(fn (array $task): string => (string) ($task['promoted_at'] ?? $task['updated_at'] ?? ''))
            ->map(fn (array $task): mixed => Arr::get($task, 'promotion_result.'.$path))
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->first();
    }

    private function nextAction(
        string $title,
        string $priority,
        string $why,
        string $action,
        string $target,
        array $playerIds,
        ?string $buttonLabel,
        string $actionType,
        ?string $teamId = null,
        ?string $dailyPlanId = null,
        bool $enabled = true,
        ?string $disabledReason = null,
        array $payload = [],
    ): array {
        $metadata = $this->actionMetadata($actionType, $teamId, $dailyPlanId, $playerIds, $payload);
        $enabled = $enabled && (bool) $metadata['enabled'];

        return [
            'action_id' => $this->actionId($actionType, $title),
            'title' => $title,
            'priority' => $this->priority($priority),
            'why' => $why,
            'action' => $action,
            'target' => $target,
            'player_ids' => array_values(array_unique(array_filter(array_map('strval', $playerIds)))),
            'button_label' => $buttonLabel,
            'action_type' => $actionType,
            'enabled' => $enabled,
            'requires_confirmation' => (bool) $metadata['requires_confirmation'],
            'target_route' => $metadata['target_route'],
            'api_endpoint' => $metadata['api_endpoint'],
            'method' => $metadata['method'],
            'payload' => $metadata['payload'],
            'success_message' => $metadata['success_message'],
            'disabled_reason' => $enabled ? null : ($disabledReason ?: $metadata['disabled_reason']),
        ];
    }

    private function actionMetadata(string $actionType, ?string $teamId, ?string $dailyPlanId, array $playerIds, array $payload): array
    {
        $endpoint = $teamId ? 'coach/teams/'.$teamId.'/planner-command-center/action' : null;
        $basePayload = array_filter([
            'action_type' => $actionType,
            'daily_plan_id' => $dailyPlanId,
            'player_ids' => $playerIds,
            ...$payload,
        ], fn ($value): bool => $value !== null && $value !== []);

        $map = [
            'publish_plan' => ['Publish Plan', true, 'Plan published.'],
            'assign_plan' => ['Assign to Players', false, 'Assignments are edited in the Daily Planner.'],
            'send_reminder' => ['Send Reminder', true, 'Reminder prepared.'],
            'review_submissions' => ['Review Submissions', false, 'Review queue opened.'],
            'approve_values' => ['Approve Selected', true, 'Selected values approved.'],
            'request_corrections' => ['Request Correction', true, 'Correction request sent.'],
            'promote_trusted_data' => ['Promote Trusted Data', true, 'Trusted data promoted.'],
            'refresh_intelligence' => ['Refresh Intelligence', false, 'Benchmark intelligence refreshed.'],
            'generate_next_plan' => ['Generate Next Plan', false, 'Generated next plan preview.'],
            'open_daily_planner' => ['Open Daily Planner', false, null],
            'view_revision_history' => ['View Revision History', false, null],
            'acknowledge_status' => ['View Acknowledgements', false, 'Acknowledgement status loaded.'],
            'collect_baselines' => ['Collect Baselines', false, 'Baseline collection guidance opened.'],
            'none' => [null, false, null],
        ];

        $known = array_key_exists($actionType, $map);
        $needsPlan = in_array($actionType, ['publish_plan', 'send_reminder', 'acknowledge_status', 'view_revision_history'], true);
        $enabled = $known && (! $needsPlan || $dailyPlanId !== null);
        if (in_array($actionType, ['assign_plan', 'open_daily_planner', 'view_revision_history', 'collect_baselines', 'none'], true)) {
            $endpoint = null;
        }

        return [
            'enabled' => $enabled,
            'requires_confirmation' => (bool) ($map[$actionType][1] ?? false),
            'target_route' => $this->targetRouteForAction($actionType, $teamId, $dailyPlanId),
            'api_endpoint' => $endpoint,
            'method' => $endpoint ? 'POST' : null,
            'payload' => $basePayload,
            'success_message' => $map[$actionType][2] ?? null,
            'disabled_reason' => $known
                ? ($needsPlan && ! $dailyPlanId ? 'A saved Daily Plan is required for this action.' : null)
                : 'This action is not available yet.',
        ];
    }

    private function actionId(string $actionType, string $title): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $actionType.'_'.$title), '_'));
    }

    private function gapPriority(array $gap): string
    {
        $priority = $gap['priority'] ?? null;
        if ($priority) {
            return $this->priority((string) $priority);
        }

        $category = (string) ($gap['category'] ?? '');
        $missing = (int) ($gap['missing_count'] ?? 0);

        if ($category === 'context' || $missing >= 5) {
            return 'high';
        }

        return $missing >= 2 ? 'medium' : 'low';
    }

    private function priority(string $priority): string
    {
        return in_array($priority, ['critical', 'high', 'medium', 'low'], true) ? $priority : 'medium';
    }

    private function priorityRank(string $priority): int
    {
        return match ($this->priority($priority)) {
            'critical' => 0,
            'high' => 1,
            'medium' => 2,
            default => 3,
        };
    }

    private function actionResult(string $teamId, ?string $dailyPlanId, string $actionType, string $status, string $message, array $result, array $warnings, int $days, bool $includeUpdatedCommandCenter = true): array
    {
        $updated = $includeUpdatedCommandCenter
            ? $this->buildForTeam($teamId, [
                'daily_plan_id' => $dailyPlanId,
                'days' => $days,
            ])
            : null;

        return [
            'action_type' => $actionType,
            'status' => in_array($status, ['completed', 'partial', 'skipped', 'failed'], true) ? $status : 'failed',
            'message' => $message,
            'result' => $result,
            'updated_command_center' => $updated,
            'warnings' => array_values(array_unique(array_filter(array_map('strval', $warnings)))),
        ];
    }

    private function teamPlan(string $teamId, ?string $dailyPlanId): ?DailyPlan
    {
        if (! $dailyPlanId) {
            return null;
        }

        return DailyPlan::query()
            ->whereKey($dailyPlanId)
            ->where('team_id', $teamId)
            ->first();
    }

    private function reviewSelectedTasks(string $teamId, array $taskIds, callable $callback, string $successKey = 'approved_count'): array
    {
        $validIds = $this->teamTaskIds($teamId, $taskIds);
        $warnings = [];
        $results = [];
        $success = 0;
        $failed = 0;

        foreach ($taskIds as $taskId) {
            if (! in_array($taskId, $validIds, true)) {
                $warnings[] = 'Task '.$taskId.' does not belong to this team or was not found.';
                $failed++;
                continue;
            }

            $result = $callback($taskId);
            $results[] = $result;
            if (($result['ok'] ?? false) === true) {
                $success++;
            } else {
                $failed++;
                $warnings[] = $result['message'] ?? $result['error'] ?? 'Task '.$taskId.' could not be reviewed.';
            }
        }

        return [
            $successKey => $success,
            'failed_count' => $failed,
            'results' => $results,
            'warnings' => array_values(array_unique(array_filter($warnings))),
        ];
    }

    private function promoteSelectedTasks(string $teamId, array $taskIds, ?string $actorUserId, int $days): array
    {
        $validIds = $this->teamTaskIds($teamId, $taskIds);
        $warnings = [];
        $results = [];
        $promoted = 0;
        $failed = 0;

        foreach ($taskIds as $taskId) {
            if (! in_array($taskId, $validIds, true)) {
                $warnings[] = 'Task '.$taskId.' does not belong to this team or was not found.';
                $failed++;
                continue;
            }

            $result = $this->trustedDataPromotionService->promoteApprovedTask($taskId, $actorUserId, [
                'days' => $days,
            ]);
            $results[] = $result;
            if (in_array((string) ($result['promotion_status'] ?? ''), [
                BenchmarkCollectionTask::PROMOTION_PROMOTED,
                BenchmarkCollectionTask::PROMOTION_PARTIAL,
                BenchmarkCollectionTask::PROMOTION_SKIPPED,
            ], true)) {
                $promoted++;
            } else {
                $failed++;
                $warnings = [...$warnings, ...Arr::wrap($result['warnings'] ?? [])];
            }
        }

        return [
            'promoted_count' => $promoted,
            'failed_count' => $failed,
            'results' => $results,
            'warnings' => array_values(array_unique(array_filter($warnings))),
        ];
    }

    private function teamTaskIds(string $teamId, array $taskIds): array
    {
        if (empty($taskIds)) {
            return [];
        }

        return BenchmarkCollectionTask::query()
            ->where('team_id', $teamId)
            ->whereIn('id', $taskIds)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    private function targetRouteForAction(string $actionType, ?string $teamId, ?string $dailyPlanId): ?string
    {
        return match ($actionType) {
            'open_daily_planner', 'assign_plan', 'collect_baselines' => '/practice-planner',
            'view_revision_history' => $dailyPlanId ? '/practice-planner?dailyPlanId='.$dailyPlanId.'&panel=revisions' : '/practice-planner',
            'acknowledge_status' => $dailyPlanId ? '/practice-planner?dailyPlanId='.$dailyPlanId.'&panel=acknowledgements' : '/practice-planner',
            default => null,
        };
    }

    private function stringList(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($value): string => trim((string) $value),
            $values
        ))));
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private function displayLabel(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', $value);

        return ucwords(trim($value));
    }

    private function safe(callable $callback, array $fallback): array
    {
        try {
            $result = $callback();

            return is_array($result) ? $result : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }
}
