<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\PlayerTeam;
use App\Services\Intelligence\BenchmarkCollectionPlanner;
use App\Services\Intelligence\BenchmarkPracticePlanDailyPlannerAdapter;
use App\Services\Intelligence\BenchmarkRefreshService;
use App\Services\Intelligence\BenchmarkTaskReviewService;
use App\Services\Intelligence\BenchmarkTrustedDataPromotionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class CoachOperatingHomeActionService
{
    public function __construct(
        private readonly CoachOperatingSystemHomeService $homeService,
        private readonly CoachPlannerCommandCenterService $commandCenterService,
        private readonly BenchmarkPracticePlanDailyPlannerAdapter $dailyPlannerAdapter,
        private readonly BenchmarkCollectionPlanner $collectionPlanner,
        private readonly BenchmarkTaskReviewService $reviewService,
        private readonly BenchmarkTrustedDataPromotionService $promotionService,
        private readonly BenchmarkRefreshService $benchmarkRefreshService,
        private readonly DailyPlanReminderService $dailyPlanReminderService,
        private readonly WeeklyReportDeliveryPrepService $weeklyReportDeliveryPrepService,
        private readonly NextWeekPlanGeneratorService $nextWeekPlanGeneratorService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAvailableActions(string $teamId, array $homePayload = [], array $options = []): array
    {
        $homePayload = ! empty($homePayload) ? $homePayload : $this->safe(
            fn (): array => $this->homeService->buildHome($teamId, [
                'days' => $this->days($options['days'] ?? 365),
                'weeks' => $this->weeks($options['weeks'] ?? 8),
            ]),
            []
        );

        $actions = collect($this->actionDefinitions($teamId))
            ->map(function (array $definition, string $actionType) use ($teamId, $homePayload): array {
                $availability = $this->buildActionAvailability($teamId, $actionType, [
                    'home' => $homePayload,
                ]);

                return $this->actionFromDefinition($teamId, $actionType, [
                    ...$definition,
                    ...$availability,
                ]);
            })
            ->values()
            ->all();

        $enabledActions = collect($actions)->filter(fn (array $action): bool => (bool) ($action['enabled'] ?? false))->values()->all();
        $disabledActions = collect($actions)->filter(fn (array $action): bool => ! (bool) ($action['enabled'] ?? false))->values()->all();
        $primary = $this->primaryAction($enabledActions, $homePayload);

        return [
            'team_id' => $teamId,
            'actions' => $actions,
            'primary_action' => $primary,
            'disabled_actions' => $disabledActions,
            'warnings' => array_values(array_filter([
                empty($homePayload) ? 'Operating Home payload was not available; action availability used database fallbacks.' : null,
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function executeAction(string $teamId, string $actionType, array $payload = [], ?string $userId = null): array
    {
        $actionType = $this->normalizeActionType($actionType);
        $definition = $this->actionDefinitions($teamId)[$actionType] ?? null;

        if (! $definition) {
            return $this->executionResult($teamId, $actionType, 'failed', 'This Operating Home action is not available yet.', [], [], []);
        }

        $availability = $this->buildActionAvailability($teamId, $actionType, [
            'payload' => $payload,
        ]);
        $action = $this->actionFromDefinition($teamId, $actionType, [
            ...$definition,
            ...$availability,
        ]);

        if (! (bool) ($action['enabled'] ?? false)) {
            return $this->executionResult($teamId, $actionType, 'skipped', (string) ($action['disabled_reason'] ?? 'This action is not available yet.'), [], [], $action);
        }

        if ((bool) ($action['requires_selection'] ?? false) && ! $this->hasRequiredSelection($actionType, $payload)) {
            return $this->executionResult($teamId, $actionType, 'skipped', $this->selectionMessage($actionType), [], ['No required selection was provided.'], $action);
        }

        if ((bool) ($action['requires_confirmation'] ?? false) && ! $this->confirmed($payload)) {
            return $this->executionResult($teamId, $actionType, 'confirmation_required', 'Confirm this action before FMTRX runs it.', [
                'requires_confirmation' => true,
                'action' => $action,
            ], [], $action);
        }

        if (($definition['mode'] ?? 'navigation') === 'navigation') {
            return $this->executionResult($teamId, $actionType, 'navigation_only', (string) ($definition['navigation_message'] ?? 'Open the existing workflow to continue.'), [
                'target_route' => $action['target_route'],
                'target_section' => $action['target_section'],
            ], [], $action);
        }

        try {
            return match ($actionType) {
                'publish_plan' => $this->runPublishPlan($teamId, $payload, $userId, $action),
                'assign_plan' => $this->runAssignPlan($teamId, $payload, $userId, $action),
                'generate_suggested_plan' => $this->runGenerateSuggestedPlan($teamId, $payload, $action),
                'save_suggested_plan_draft' => $this->runSaveSuggestedPlanDraft($teamId, $payload, $userId, $action),
                'review_submissions' => $this->runReviewSubmissions($teamId, $payload, $action),
                'approve_selected_values' => $this->runCommandAction($teamId, 'approve_values', $payload, $userId, $action),
                'request_corrections' => $this->runCommandAction($teamId, 'request_corrections', $payload, $userId, $action),
                'promote_trusted_data' => $this->runCommandAction($teamId, 'promote_trusted_data', $payload, $userId, $action),
                'refresh_intelligence' => $this->runRefreshIntelligence($teamId, $payload, $action),
                'collect_baselines' => $this->runCollectBaselines($teamId, $payload, $action),
                'send_reminder' => $this->runCommandAction($teamId, 'send_reminder', $payload, $userId, $action),
                'prepare_weekly_report' => $this->runPrepareWeeklyReport($teamId, $payload, $action),
                'prepare_parent_update' => $this->runPrepareParentUpdate($teamId, $payload, $action),
                'generate_next_week_plan' => $this->runGenerateNextWeekPlan($teamId, $payload, $action),
                default => $this->executionResult($teamId, $actionType, 'failed', 'This action is not executable yet.', [], [], $action),
            };
        } catch (Throwable $exception) {
            return $this->executionResult($teamId, $actionType, 'failed', 'Could not complete this Operating Home action.', [
                'exception' => class_basename($exception),
            ], [$exception->getMessage()], $action);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildActionAvailability(string $teamId, string $actionType, array $context = []): array
    {
        $actionType = $this->normalizeActionType($actionType);
        $payload = Arr::wrap($context['payload'] ?? []);
        $plan = $this->planForAction($teamId, $payload, $context);
        $draft = $this->latestDraftPlan($teamId);
        $review = $this->safe(fn (): array => $this->reviewService->buildTeamReviewSummary($teamId), []);
        $promotion = $this->safe(fn (): array => $this->promotionService->buildPromotionStatus($teamId), []);
        $reminder = $plan ? $this->safe(fn (): array => $this->dailyPlanReminderService->buildReminderPreview((string) $plan->id), []) : [];

        $enabled = true;
        $disabledReason = null;
        $selectedPlanId = $plan ? (string) $plan->id : null;

        switch ($actionType) {
            case 'publish_plan':
                $enabled = $draft !== null || ($plan !== null && (string) $plan->status === 'draft');
                $selectedPlanId = $draft ? (string) $draft->id : $selectedPlanId;
                $disabledReason = $enabled ? null : 'No draft plan is available.';
                break;

            case 'assign_plan':
                $enabled = $plan !== null;
                $disabledReason = $enabled ? null : 'No Daily Plan is available to assign.';
                break;

            case 'review_submissions':
            case 'approve_selected_values':
            case 'request_corrections':
                $enabled = (int) ($review['pending_count'] ?? 0) > 0;
                $disabledReason = $enabled ? null : 'No benchmark submissions are waiting for review.';
                break;

            case 'promote_trusted_data':
                $enabled = (int) ($promotion['awaiting_promotion_count'] ?? 0) > 0;
                $disabledReason = $enabled ? null : 'No approved values are waiting for promotion.';
                break;

            case 'send_reminder':
                $enabled = $plan !== null && (int) ($reminder['unacknowledged_count'] ?? 0) > 0;
                $disabledReason = $plan === null
                    ? 'No Daily Plan is available for reminders.'
                    : ($enabled ? null : 'All assigned players have acknowledged the latest update.');
                break;
        }

        return [
            'enabled' => $enabled,
            'disabled_reason' => $disabledReason,
            'payload' => array_filter([
                'daily_plan_id' => $selectedPlanId,
                'review_pending_count' => $review['pending_count'] ?? null,
                'approved_unpromoted_count' => $promotion['awaiting_promotion_count'] ?? null,
                'unacknowledged_count' => $reminder['unacknowledged_count'] ?? null,
            ], fn ($value): bool => $value !== null && $value !== []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshHomeAfterAction(string $teamId, array $options = []): array
    {
        return $this->safe(fn (): array => $this->homeService->buildHome($teamId, [
            'days' => $this->days($options['days'] ?? 365),
            'weeks' => $this->weeks($options['weeks'] ?? 8),
        ]), []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function actionDefinitions(string $teamId): array
    {
        $endpoint = 'coach/teams/'.$teamId.'/operating-system-home/actions/execute';

        return [
            'open_daily_planner' => $this->definition('open_daily_planner', 'Open Daily Planner', 'Open Daily Planner', 'high', 'planner', 'Open the existing Daily Planner workflow.', false, false, 'daily_planner', '/practice-planner', null, null, 'navigation'),
            'publish_plan' => $this->definition('publish_plan', 'Publish Plan', 'Publish Plan', 'critical', 'planner', 'Publish an existing draft plan after coach confirmation.', true, false, 'daily_planner', '/practice-planner', $endpoint, 'POST', 'mutation'),
            'assign_plan' => $this->definition('assign_plan', 'Assign Players', 'Assign Players', 'high', 'planner', 'Assign a plan only to explicitly selected players or all players when confirmed.', true, true, 'daily_planner', '/practice-planner', $endpoint, 'POST', 'mutation'),
            'generate_suggested_plan' => $this->definition('generate_suggested_plan', 'Generate Suggested Plan', 'Generate Suggested Plan', 'high', 'planner', 'Preview an FMTRX suggested plan without saving, publishing, or assigning it.', false, false, 'next_week_plan_draft', '/practice-planner', $endpoint, 'POST', 'preview'),
            'save_suggested_plan_draft' => $this->definition('save_suggested_plan_draft', 'Save Draft Plan', 'Save Draft Plan', 'medium', 'planner', 'Save the suggested plan as a draft only.', true, false, 'daily_planner', '/practice-planner', $endpoint, 'POST', 'mutation'),
            'review_submissions' => $this->definition('review_submissions', 'Review Submitted Benchmark Values', 'Review Submissions', 'high', 'review', 'Open benchmark submissions waiting for coach review.', false, false, 'review_queue', '/practice-planner', $endpoint, 'POST', 'preview'),
            'approve_selected_values' => $this->definition('approve_selected_values', 'Approve Selected Benchmark Values', 'Approve Selected', 'high', 'review', 'Approve only explicitly selected benchmark submissions.', true, true, 'review_queue', '/practice-planner', $endpoint, 'POST', 'mutation'),
            'request_corrections' => $this->definition('request_corrections', 'Request Benchmark Corrections', 'Request Correction', 'medium', 'review', 'Request corrections for explicitly selected submissions.', true, true, 'review_queue', '/practice-planner', $endpoint, 'POST', 'mutation'),
            'promote_trusted_data' => $this->definition('promote_trusted_data', 'Promote Approved Trusted Data', 'Promote Trusted Data', 'high', 'review', 'Promote approved values without bypassing coach review.', true, false, 'review_queue', '/practice-planner', $endpoint, 'POST', 'mutation'),
            'refresh_intelligence' => $this->definition('refresh_intelligence', 'Refresh Intelligence', 'Refresh Intelligence', 'medium', 'benchmark', 'Refresh benchmark intelligence and Operating Home status.', false, false, 'benchmark_intelligence', '/practice-planner', $endpoint, 'POST', 'mutation'),
            'collect_baselines' => $this->definition('collect_baselines', 'Collect Benchmark Baselines', 'Collect Baselines', 'high', 'benchmark', 'Preview the benchmark baseline collection plan.', false, false, 'next_week_plan_draft', '/practice-planner', $endpoint, 'POST', 'preview'),
            'send_reminder' => $this->definition('send_reminder', 'Send Player Reminder', 'Send Reminder', 'medium', 'communication', 'Prepare a reminder for players who have not acknowledged work.', true, false, 'player_plan_progress', '/practice-planner', $endpoint, 'POST', 'mutation'),
            'prepare_weekly_report' => $this->definition('prepare_weekly_report', 'Prepare Weekly Update', 'Prepare Weekly Update', 'medium', 'reports', 'Preview weekly report delivery. Nothing is sent.', false, false, 'weekly_report_delivery', '/practice-planner', $endpoint, 'POST', 'preview'),
            'prepare_parent_update' => $this->definition('prepare_parent_update', 'Prepare Parent Update', 'Prepare Parent Update', 'medium', 'reports', 'Preview a parent-safe weekly update. Nothing is sent.', false, false, 'weekly_report_delivery', '/practice-planner', $endpoint, 'POST', 'preview'),
            'view_alerts' => $this->definition('view_alerts', 'View Alerts', 'View Alerts', 'medium', 'health', 'Open Development Health Alerts.', false, false, 'development_health_alerts', '/practice-planner', null, null, 'navigation'),
            'view_health_score' => $this->definition('view_health_score', 'View Health Score', 'View Health Score', 'medium', 'health', 'Open Development Program Health.', false, false, 'development_program_health', '/practice-planner', null, null, 'navigation'),
            'view_benchmark_intelligence' => $this->definition('view_benchmark_intelligence', 'View Benchmark Intelligence', 'View Benchmark Intelligence', 'medium', 'benchmark', 'Open Benchmark Intelligence.', false, false, 'benchmark_intelligence', '/practice-planner', null, null, 'navigation'),
            'view_communication_rhythm' => $this->definition('view_communication_rhythm', 'View Communication Rhythm', 'View Communication Rhythm', 'medium', 'communication', 'Open Communication Rhythm.', false, false, 'weekly_report_delivery', '/practice-planner', null, null, 'navigation'),
            'generate_next_week_plan' => $this->definition('generate_next_week_plan', 'Generate Next Week Plan', 'Generate Next Week Plan', 'medium', 'planner', 'Preview a next-week plan from the weekly rollup. Nothing is saved or published.', false, false, 'next_week_plan_draft', '/practice-planner', $endpoint, 'POST', 'preview'),
            'open_weekly_calendar' => $this->definition('open_weekly_calendar', 'Open Weekly Calendar', 'Open Weekly Calendar', 'low', 'planner', 'Open the existing weekly calendar draft section.', false, false, 'next_week_calendar_draft', '/practice-planner', null, null, 'navigation'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $actionType, string $title, string $buttonLabel, string $priority, string $category, string $why, bool $requiresConfirmation, bool $requiresSelection, ?string $targetSection, ?string $targetRoute, ?string $apiEndpoint, ?string $method, string $mode): array
    {
        return [
            'action_id' => $this->actionId($actionType, $title),
            'action_type' => $actionType,
            'title' => $title,
            'button_label' => $buttonLabel,
            'priority' => $this->priority($priority),
            'category' => $category,
            'why' => $why,
            'requires_confirmation' => $requiresConfirmation,
            'requires_selection' => $requiresSelection,
            'target_section' => $targetSection,
            'target_route' => $targetRoute,
            'api_endpoint' => $apiEndpoint,
            'method' => $method,
            'mode' => $mode,
            'success_message' => null,
            'safety_notes' => $this->safetyNotes($actionType, $mode),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function actionFromDefinition(string $teamId, string $actionType, array $definition): array
    {
        $payload = Arr::wrap($definition['payload'] ?? []);
        $payload['action_type'] = $actionType;

        return [
            'action_id' => (string) ($definition['action_id'] ?? $this->actionId($actionType, (string) ($definition['title'] ?? $actionType))),
            'action_type' => $actionType,
            'title' => (string) ($definition['title'] ?? $actionType),
            'button_label' => (string) ($definition['button_label'] ?? 'Open'),
            'priority' => $this->priority((string) ($definition['priority'] ?? 'low')),
            'category' => (string) ($definition['category'] ?? 'navigation'),
            'why' => (string) ($definition['why'] ?? 'Open the existing workflow.'),
            'enabled' => (bool) ($definition['enabled'] ?? true),
            'requires_confirmation' => (bool) ($definition['requires_confirmation'] ?? false),
            'requires_selection' => (bool) ($definition['requires_selection'] ?? false),
            'target_route' => $definition['target_route'] ?? null,
            'target_section' => $definition['target_section'] ?? null,
            'api_endpoint' => $definition['api_endpoint'] ?? null,
            'method' => $definition['method'] ?? null,
            'payload' => $payload,
            'success_message' => $definition['success_message'] ?? null,
            'disabled_reason' => (bool) ($definition['enabled'] ?? true) ? null : ($definition['disabled_reason'] ?? 'This action is not available yet.'),
            'safety_notes' => Arr::wrap($definition['safety_notes'] ?? []),
        ];
    }

    private function runPublishPlan(string $teamId, array $payload, ?string $userId, array $action): array
    {
        $planId = $this->planId($payload) ?? (string) ($action['payload']['daily_plan_id'] ?? '');
        $result = $this->commandCenterService->runAction($teamId, 'publish_plan', [
            ...$payload,
            'daily_plan_id' => $planId,
            'days' => $this->days($payload['days'] ?? 365),
        ], $userId);

        return $this->fromCommandResult($teamId, 'publish_plan', $result, $payload, $action);
    }

    private function runAssignPlan(string $teamId, array $payload, ?string $userId, array $action): array
    {
        $planId = $this->planId($payload) ?? (string) ($action['payload']['daily_plan_id'] ?? '');
        if ($planId === '') {
            return $this->executionResult($teamId, 'assign_plan', 'skipped', 'No Daily Plan is available to assign.', [], [], $action);
        }

        $plan = DailyPlan::query()->where('team_id', $teamId)->whereKey($planId)->first();
        if (! $plan) {
            return $this->executionResult($teamId, 'assign_plan', 'failed', 'Daily Plan was not found for this team.', [], ['No matching Daily Plan found.'], $action);
        }

        $requested = (bool) ($payload['assign_all'] ?? false)
            ? $this->teamPlayerIds($teamId)
            : $this->stringList(Arr::wrap($payload['player_ids'] ?? []));
        $validIds = $this->validTeamPlayerIds($teamId, $requested);
        $existing = DailyPlanAssignment::query()
            ->where('plan_id', (string) $plan->id)
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
        $toCreate = array_values(array_diff($validIds, $existing));

        DB::transaction(function () use ($plan, $toCreate): void {
            foreach ($toCreate as $playerId) {
                DailyPlanAssignment::query()->firstOrCreate([
                    'plan_id' => (string) $plan->id,
                    'user_id' => $playerId,
                ]);
            }
        });

        $plan->refresh()->load(['assignments', 'progress']);
        $result = [
            'plan_id' => (string) $plan->id,
            'status' => (string) $plan->status,
            'requested_player_count' => count($requested),
            'valid_player_count' => count($validIds),
            'assigned_count' => count($toCreate),
            'already_assigned_count' => max(0, count($validIds) - count($toCreate)),
            'assigned_player_ids' => $plan->assigned_player_ids,
            'assigned_by_user_id' => $userId,
        ];
        $warnings = [];
        if (empty($validIds)) {
            $warnings[] = 'No valid team players were selected for assignment.';
        }
        if ($plan->progress->isNotEmpty()) {
            $warnings[] = 'Existing player progress was preserved.';
        }

        return $this->executionResult($teamId, 'assign_plan', empty($validIds) ? 'skipped' : 'completed', 'Assignments updated. Existing player progress was preserved.', $result, $warnings, $action, true, $payload);
    }

    private function runGenerateSuggestedPlan(string $teamId, array $payload, array $action): array
    {
        $result = $this->dailyPlannerAdapter->previewMapping($teamId, $this->days($payload['days'] ?? 365));

        return $this->executionResult($teamId, 'generate_suggested_plan', 'completed', 'Generated suggested Daily Plan preview. Nothing was saved, published, or assigned.', $result, Arr::wrap($result['warnings'] ?? []), $action);
    }

    private function runSaveSuggestedPlanDraft(string $teamId, array $payload, ?string $userId, array $action): array
    {
        $result = $this->dailyPlannerAdapter->saveToExistingDailyPlanner($teamId, null, [
            'days' => $this->days($payload['days'] ?? 365),
            'status' => 'draft',
            'assign_all' => false,
            'assigned_player_ids' => $this->stringList(Arr::wrap($payload['player_ids'] ?? [])),
            'created_by_user_id' => $userId,
        ]);

        return $this->executionResult($teamId, 'save_suggested_plan_draft', 'completed', 'Suggested plan saved as a draft. It was not published.', $result, Arr::wrap($result['warnings'] ?? []), $action, true, $payload);
    }

    private function runReviewSubmissions(string $teamId, array $payload, array $action): array
    {
        $result = $this->reviewService->listPendingReviewTasks($teamId);

        return $this->executionResult($teamId, 'review_submissions', 'completed', ((int) ($result['pending_count'] ?? 0)).' submission(s) pending review.', $result, Arr::wrap($result['warnings'] ?? []), $action);
    }

    private function runRefreshIntelligence(string $teamId, array $payload, array $action): array
    {
        $result = $this->benchmarkRefreshService->refreshTeamBenchmarks($teamId, $this->days($payload['days'] ?? 365));

        return $this->executionResult($teamId, 'refresh_intelligence', (string) ($result['refresh_status'] ?? 'completed'), 'Benchmark intelligence refreshed.', $result, Arr::wrap($result['warnings'] ?? []), $action, true, $payload);
    }

    private function runCollectBaselines(string $teamId, array $payload, array $action): array
    {
        $result = $this->collectionPlanner->buildTeamCollectionPlan($teamId, $this->days($payload['days'] ?? 365));

        return $this->executionResult($teamId, 'collect_baselines', 'completed', 'Benchmark baseline collection plan loaded. Nothing was published.', $result, Arr::wrap($result['warnings'] ?? []), $action);
    }

    private function runPrepareWeeklyReport(string $teamId, array $payload, array $action): array
    {
        $result = $this->weeklyReportDeliveryPrepService->prepareDelivery($teamId, [
            ...$payload,
            'audience' => $payload['audience'] ?? 'coach',
            'channel' => $payload['channel'] ?? 'copy',
        ]);

        return $this->executionResult($teamId, 'prepare_weekly_report', 'completed', 'Weekly update prepared for review. Nothing was sent.', $result, Arr::wrap($result['delivery_warnings'] ?? []), $action);
    }

    private function runPrepareParentUpdate(string $teamId, array $payload, array $action): array
    {
        $result = $this->weeklyReportDeliveryPrepService->prepareDelivery($teamId, [
            ...$payload,
            'audience' => 'parents',
            'template' => $payload['template'] ?? 'parent_update',
            'channel' => $payload['channel'] ?? 'copy',
        ]);

        return $this->executionResult($teamId, 'prepare_parent_update', 'completed', 'Parent update prepared for review. Nothing was sent.', $result, Arr::wrap($result['delivery_warnings'] ?? []), $action);
    }

    private function runGenerateNextWeekPlan(string $teamId, array $payload, array $action): array
    {
        $result = $this->nextWeekPlanGeneratorService->generateForTeam($teamId, [
            ...$payload,
            'days' => $this->days($payload['days'] ?? 365),
        ]);

        return $this->executionResult($teamId, 'generate_next_week_plan', (string) ($result['generation_status'] ?? 'completed'), 'Generated next-week plan preview. Nothing was saved or published.', $result, Arr::wrap($result['warnings'] ?? []), $action);
    }

    private function runCommandAction(string $teamId, string $commandActionType, array $payload, ?string $userId, array $action): array
    {
        $result = $this->commandCenterService->runAction($teamId, $commandActionType, [
            ...$payload,
            'daily_plan_id' => $this->planId($payload) ?? ($action['payload']['daily_plan_id'] ?? null),
            'days' => $this->days($payload['days'] ?? 365),
        ], $userId);

        return $this->fromCommandResult($teamId, $this->normalizeActionType((string) ($action['action_type'] ?? $commandActionType)), $result, $payload, $action);
    }

    private function fromCommandResult(string $teamId, string $actionType, array $result, array $payload, array $action): array
    {
        return $this->executionResult(
            $teamId,
            $actionType,
            (string) ($result['status'] ?? 'completed'),
            (string) ($result['message'] ?? $action['success_message'] ?? 'Action completed.'),
            $result['result'] ?? $result,
            Arr::wrap($result['warnings'] ?? []),
            $action,
            true,
            $payload,
            Arr::wrap($result['updated_command_center'] ?? [])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function executionResult(string $teamId, string $actionType, string $status, string $message, array $result, array $warnings, array $action = [], bool $refreshHome = false, array $payload = [], array $updatedCommandCenter = []): array
    {
        return [
            'team_id' => $teamId,
            'action_type' => $actionType,
            'status' => in_array($status, ['completed', 'partial', 'skipped', 'failed', 'navigation_only', 'confirmation_required'], true) ? $status : 'completed',
            'message' => $message,
            'result' => $result,
            'updated_home' => $refreshHome ? $this->refreshHomeAfterAction($teamId, [
                'days' => $this->days($payload['days'] ?? 365),
                'weeks' => $this->weeks($payload['weeks'] ?? 8),
            ]) : [],
            'updated_command_center' => $updatedCommandCenter,
            'warnings' => array_values(array_unique(array_filter(array_map('strval', $warnings)))),
        ];
    }

    private function primaryAction(array $enabledActions, array $homePayload): array
    {
        $primaryType = $this->normalizeActionType((string) data_get($homePayload, 'primary_next_action.action_type', ''));
        $primary = collect($enabledActions)->firstWhere('action_type', $primaryType);

        return is_array($primary)
            ? $primary
            : (collect($enabledActions)->sortByDesc(fn (array $action): int => $this->priorityRank((string) ($action['priority'] ?? 'low')))->first() ?: []);
    }

    private function planForAction(string $teamId, array $payload, array $context = []): ?DailyPlan
    {
        $planId = $this->planId($payload)
            ?? $this->nullableString(data_get($context, 'home.today_plan.daily_plan_id'))
            ?? $this->nullableString(data_get($context, 'home.primary_next_action.payload.daily_plan_id'));

        if ($planId) {
            return DailyPlan::query()->where('team_id', $teamId)->whereKey($planId)->first();
        }

        return $this->activePlan($teamId) ?? $this->latestDraftPlan($teamId);
    }

    private function activePlan(string $teamId): ?DailyPlan
    {
        return DailyPlan::query()
            ->where('team_id', $teamId)
            ->whereIn('status', ['published', 'sent', 'in_progress'])
            ->orderByDesc('date')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function teamPlayerIds(string $teamId): array
    {
        return PlayerTeam::query()
            ->where('team_id', $teamId)
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    /**
     * @param array<int, string> $playerIds
     * @return array<int, string>
     */
    private function validTeamPlayerIds(string $teamId, array $playerIds): array
    {
        if (empty($playerIds)) {
            return [];
        }

        return PlayerTeam::query()
            ->where('team_id', $teamId)
            ->whereIn('user_id', array_values(array_unique($playerIds)))
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    private function latestDraftPlan(string $teamId): ?DailyPlan
    {
        return DailyPlan::query()
            ->where('team_id', $teamId)
            ->where('status', 'draft')
            ->orderByDesc('date')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function hasRequiredSelection(string $actionType, array $payload): bool
    {
        if ($actionType === 'assign_plan') {
            return (bool) ($payload['assign_all'] ?? false) || count($this->stringList(Arr::wrap($payload['player_ids'] ?? []))) > 0;
        }

        if ($actionType === 'request_corrections') {
            return count($this->stringList(Arr::wrap($payload['task_ids'] ?? []))) > 0 && $this->nullableString($payload['message'] ?? null) !== null;
        }

        return count($this->stringList(Arr::wrap($payload['task_ids'] ?? []))) > 0;
    }

    private function selectionMessage(string $actionType): string
    {
        return match ($actionType) {
            'assign_plan' => 'Select players or choose assign all before assigning.',
            'request_corrections' => 'Select review tasks and add a correction message before sending.',
            default => 'Select one or more review tasks first.',
        };
    }

    private function normalizeActionType(string $actionType): string
    {
        return match ($actionType) {
            'approve_values' => 'approve_selected_values',
            'generate_next_plan' => 'generate_suggested_plan',
            'send_weekly_report' => 'prepare_weekly_report',
            default => $actionType,
        };
    }

    private function confirmed(array $payload): bool
    {
        return (bool) ($payload['confirm'] ?? data_get($payload, 'payload.confirm', false));
    }

    private function planId(array $payload): ?string
    {
        return $this->nullableString($payload['daily_plan_id'] ?? $payload['dailyPlanId'] ?? data_get($payload, 'payload.daily_plan_id'));
    }

    /**
     * @return array<int, string>
     */
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

    private function days(mixed $value): int
    {
        return max(7, min(365, (int) ($value ?: 365)));
    }

    private function weeks(mixed $value): int
    {
        return max(1, min(52, (int) ($value ?: 8)));
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

    private function actionId(string $actionType, string $title): string
    {
        return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $actionType.'_'.$title), '_'));
    }

    /**
     * @return array<int, string>
     */
    private function safetyNotes(string $actionType, string $mode): array
    {
        $notes = ['Uses existing FMTRX workflows; no scoring or benchmark formulas are changed.'];
        if ($mode === 'navigation') {
            $notes[] = 'Navigation only; no backend data is changed.';
        }
        if (in_array($actionType, ['publish_plan', 'assign_plan', 'approve_selected_values', 'request_corrections', 'promote_trusted_data', 'send_reminder', 'save_suggested_plan_draft'], true)) {
            $notes[] = 'Requires explicit coach confirmation before any write action.';
        }
        if (in_array($actionType, ['approve_selected_values', 'request_corrections'], true)) {
            $notes[] = 'Only selected task IDs are affected.';
        }
        if ($actionType === 'promote_trusted_data') {
            $notes[] = 'Only approved values can be promoted; pending and rejected values are ignored.';
        }
        if (in_array($actionType, ['prepare_weekly_report', 'prepare_parent_update', 'send_reminder'], true)) {
            $notes[] = 'Nothing is auto-sent; existing preview/manual-copy safety remains active.';
        }

        return $notes;
    }

    /**
     * @template T
     * @param callable():T $callback
     * @param T $fallback
     * @return T
     */
    private function safe(callable $callback, mixed $fallback): mixed
    {
        try {
            $result = $callback();

            return $result ?? $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }
}
