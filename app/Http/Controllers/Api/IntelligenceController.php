<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BenchmarkCollectionTask;
use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\Intelligence\BenchmarkCollectionPlanner;
use App\Services\Intelligence\BenchmarkRefreshService;
use App\Services\Intelligence\BenchmarkTaskAssignmentService;
use App\Services\Intelligence\BenchmarkTaskCompletionService;
use App\Services\Intelligence\BenchmarkTaskPersistenceService;
use App\Services\Intelligence\BenchmarkTaskReviewService;
use App\Services\Intelligence\BenchmarkTrustedDataPromotionService;
use App\Services\Intelligence\CoachActionPracticePlanner;
use App\Services\Intelligence\DecisionEngine;
use App\Services\Intelligence\PlayerIntelligenceService;
use App\Services\Intelligence\TeamIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class IntelligenceController extends Controller
{
    public function __construct(
        private readonly TeamIntelligenceService $teamIntelligence,
        private readonly PlayerIntelligenceService $playerIntelligence,
        private readonly DecisionEngine $decisionEngine,
        private readonly BenchmarkCollectionPlanner $benchmarkCollectionPlanner,
        private readonly BenchmarkTaskAssignmentService $benchmarkTaskAssignmentService,
        private readonly BenchmarkTaskPersistenceService $benchmarkTaskPersistenceService,
        private readonly BenchmarkTaskCompletionService $benchmarkTaskCompletionService,
        private readonly BenchmarkRefreshService $benchmarkRefreshService,
        private readonly BenchmarkTaskReviewService $benchmarkTaskReviewService,
        private readonly BenchmarkTrustedDataPromotionService $benchmarkTrustedDataPromotionService,
        private readonly CoachActionPracticePlanner $coachActionPracticePlanner,
    ) {
    }

    public function team(Request $request, string $teamId): JsonResponse
    {
        if (! Team::query()->whereKey($teamId)->exists()) {
            return $this->notFound('Team not found');
        }

        if (! $this->coachCanAccessTeam($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        $days = $this->days($request);
        $snapshot = $this->teamIntelligence->build($teamId, $days);
        try {
            $snapshot['decision_brief'] = $this->decisionEngine->buildTeamDecisionBrief($teamId, $days);
        } catch (\Throwable $exception) {
            Log::warning('IntelligenceController decision brief unavailable: '.$exception->getMessage(), [
                'team_id' => $teamId,
                'days' => $days,
            ]);

            $snapshot['decision_brief'] = null;
        }

        try {
            $snapshot['benchmark_collection_plan'] = $this->benchmarkCollectionPlanner->buildTeamCollectionPlan($teamId, $days);
        } catch (\Throwable $exception) {
            Log::warning('IntelligenceController benchmark collection plan unavailable: '.$exception->getMessage(), [
                'team_id' => $teamId,
                'days' => $days,
            ]);

            $snapshot['benchmark_collection_plan'] = null;
        }

        try {
            $snapshot['benchmark_task_assignments'] = $this->benchmarkTaskAssignmentService->buildAssignableTasks($teamId, $days);
        } catch (\Throwable $exception) {
            Log::warning('IntelligenceController benchmark task assignments unavailable: '.$exception->getMessage(), [
                'team_id' => $teamId,
                'days' => $days,
            ]);

            $snapshot['benchmark_task_assignments'] = null;
        }

        try {
            $snapshot['coach_action_practice_plan'] = $this->coachActionPracticePlanner->buildPracticePlanFromCoachActions($teamId, $days);
        } catch (\Throwable $exception) {
            Log::warning('IntelligenceController coach action practice plan unavailable: '.$exception->getMessage(), [
                'team_id' => $teamId,
                'days' => $days,
            ]);

            $snapshot['coach_action_practice_plan'] = null;
        }

        $snapshot['benchmark_refresh_status'] = $this->benchmarkRefreshService->buildRefreshStatus($teamId, null, $days);
        $snapshot['benchmark_task_review_summary'] = $this->benchmarkTaskReviewService->buildTeamReviewSummary($teamId);
        $snapshot['benchmark_task_promotion_status'] = $this->benchmarkTrustedDataPromotionService->buildPromotionStatus($teamId);

        return response()->json($snapshot);
    }

    public function player(Request $request, string $teamId, string $playerId): JsonResponse
    {
        if (! Team::query()->whereKey($teamId)->exists()) {
            return $this->notFound('Team not found');
        }

        if (! User::query()->whereKey($playerId)->exists()) {
            return $this->notFound('Player not found');
        }

        if (! $this->coachCanAccessTeam($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        if (! PlayerTeam::query()->where('team_id', $teamId)->where('user_id', $playerId)->exists()) {
            return $this->forbidden('Player is not linked to this team');
        }

        return response()->json(
            $this->playerIntelligence->build($teamId, $playerId, $this->days($request))
        );
    }

    public function listBenchmarkTasks(Request $request, string $teamId): JsonResponse
    {
        if (! $this->teamIsAccessible($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        return response()->json($this->benchmarkTaskPersistenceService->listTeamTasks($teamId, [
            'status' => $request->query('status'),
            'player_id' => $request->query('player_id'),
            'task_type' => $request->query('task_type'),
        ]));
    }

    public function generateBenchmarkTasks(Request $request, string $teamId): JsonResponse
    {
        if (! $this->teamIsAccessible($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        return response()->json($this->benchmarkTaskAssignmentService->buildAssignableTasks($teamId, $this->days($request)));
    }

    public function saveBenchmarkDrafts(Request $request, string $teamId): JsonResponse
    {
        if (! $this->teamIsAccessible($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        $validated = $request->validate([
            'tasks' => ['nullable', 'array'],
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
        ]);

        $tasks = $validated['tasks'] ?? null;
        if ($tasks === null) {
            $generated = $this->benchmarkTaskAssignmentService->buildAssignableTasks($teamId, $this->days($request));
            $tasks = [
                ...($generated['team_tasks'] ?? []),
                ...($generated['assignable_tasks'] ?? []),
            ];
        }

        $result = $this->benchmarkTaskPersistenceService->saveDraftTasks($teamId, $tasks, (string) $request->user()?->id);
        $result['saved_tasks'] = $this->benchmarkTaskPersistenceService->listTeamTasks($teamId)['tasks'] ?? [];

        return response()->json($result);
    }

    public function assignBenchmarkTasks(Request $request, string $teamId): JsonResponse
    {
        if (! $this->teamIsAccessible($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        $validated = $request->validate([
            'task_ids' => ['nullable', 'array'],
            'task_ids.*' => ['string'],
        ]);

        $result = $this->benchmarkTaskPersistenceService->assignTasks(
            $teamId,
            $validated['task_ids'] ?? [],
            (string) $request->user()?->id,
        );
        $result['saved_tasks'] = $this->benchmarkTaskPersistenceService->listTeamTasks($teamId)['tasks'] ?? [];

        return response()->json($result);
    }

    public function listBenchmarkTaskReviews(Request $request, string $teamId): JsonResponse
    {
        if (! $this->teamIsAccessible($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        $summary = $this->benchmarkTaskReviewService->buildTeamReviewSummary($teamId);
        $pending = $this->benchmarkTaskReviewService->listPendingReviewTasks($teamId, [
            'player_id' => $request->query('player_id'),
            'task_type' => $request->query('task_type'),
            'priority' => $request->query('priority'),
        ]);

        return response()->json([
            ...$summary,
            'pending_tasks' => $pending['tasks'] ?? $summary['pending_tasks'] ?? [],
            'pending_count' => $pending['pending_count'] ?? $summary['pending_count'] ?? 0,
        ]);
    }

    public function completeBenchmarkTask(Request $request, string $taskId): JsonResponse
    {
        $task = BenchmarkCollectionTask::query()->find($taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        if (! $this->coachCanAccessTeam($request, (string) $task->team_id)) {
            return $this->forbidden('You do not have access to this task');
        }

        $payload = [
            'completed_by_user_id' => (string) $request->user()?->id,
            'source' => 'coach_dashboard',
            'payload' => $request->all(),
        ];
        $result = $this->benchmarkTaskPersistenceService->markTaskComplete($taskId, $payload);
        if ($result['ok'] ?? false) {
            $review = $this->benchmarkTaskReviewService->recordCompletionSubmission(
                $taskId,
                (string) $request->user()?->id,
                $payload,
            );
            $result['review'] = $review;
            $result['task'] = $review['task'] ?? $result['task'] ?? null;
        }

        return response()->json($this->withCompletionRefresh($result, $taskId, $this->days($request)));
    }

    public function approveBenchmarkTask(Request $request, string $taskId): JsonResponse
    {
        $task = BenchmarkCollectionTask::query()->find($taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        if (! $this->coachCanAccessTeam($request, (string) $task->team_id)) {
            return $this->forbidden('You do not have access to this task');
        }

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
            'approved_payload' => ['nullable', 'array'],
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
        ]);

        $result = $this->benchmarkTaskReviewService->approveTask(
            $taskId,
            (string) $request->user()?->id,
            [
                ...$validated,
                'days' => $this->days($request),
            ],
        );

        return response()->json($result, ($result['ok'] ?? false) ? HttpCodes::HTTP_OK : HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function rejectBenchmarkTask(Request $request, string $taskId): JsonResponse
    {
        $task = BenchmarkCollectionTask::query()->find($taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        if (! $this->coachCanAccessTeam($request, (string) $task->team_id)) {
            return $this->forbidden('You do not have access to this task');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $result = $this->benchmarkTaskReviewService->rejectTask(
            $taskId,
            $validated['reason'],
            (string) $request->user()?->id,
        );

        return response()->json($result, ($result['ok'] ?? false) ? HttpCodes::HTTP_OK : HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function requestBenchmarkTaskCorrection(Request $request, string $taskId): JsonResponse
    {
        $task = BenchmarkCollectionTask::query()->find($taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        if (! $this->coachCanAccessTeam($request, (string) $task->team_id)) {
            return $this->forbidden('You do not have access to this task');
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $result = $this->benchmarkTaskReviewService->requestCorrection(
            $taskId,
            $validated['message'],
            (string) $request->user()?->id,
        );

        return response()->json($result, ($result['ok'] ?? false) ? HttpCodes::HTTP_OK : HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function listBenchmarkTaskPromotions(Request $request, string $teamId): JsonResponse
    {
        if (! $this->teamIsAccessible($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        return response()->json($this->benchmarkTrustedDataPromotionService->buildPromotionStatus($teamId));
    }

    public function previewBenchmarkTaskPromotion(Request $request, string $taskId): JsonResponse
    {
        $task = BenchmarkCollectionTask::query()->find($taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        if (! $this->coachCanAccessTeam($request, (string) $task->team_id)) {
            return $this->forbidden('You do not have access to this task');
        }

        return response()->json($this->benchmarkTrustedDataPromotionService->previewPromotion($taskId));
    }

    public function promoteBenchmarkTask(Request $request, string $taskId): JsonResponse
    {
        $task = BenchmarkCollectionTask::query()->find($taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        if (! $this->coachCanAccessTeam($request, (string) $task->team_id)) {
            return $this->forbidden('You do not have access to this task');
        }

        $validated = $request->validate([
            'overwrite' => ['nullable', 'boolean'],
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
        ]);

        $result = $this->benchmarkTrustedDataPromotionService->promoteApprovedTask(
            $taskId,
            (string) $request->user()?->id,
            [
                'overwrite' => (bool) ($validated['overwrite'] ?? false),
                'days' => $this->days($request),
            ],
        );

        return response()->json($result, ($result['promotion_status'] ?? null) === BenchmarkCollectionTask::PROMOTION_FAILED
            ? HttpCodes::HTTP_UNPROCESSABLE_ENTITY
            : HttpCodes::HTTP_OK);
    }

    public function promoteApprovedBenchmarkTasks(Request $request, string $teamId): JsonResponse
    {
        if (! $this->teamIsAccessible($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        $validated = $request->validate([
            'overwrite' => ['nullable', 'boolean'],
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
        ]);

        return response()->json($this->benchmarkTrustedDataPromotionService->promoteTeamApprovedTasks($teamId, [
            'overwrite' => (bool) ($validated['overwrite'] ?? false),
            'days' => $this->days($request),
            'promoted_by_user_id' => (string) $request->user()?->id,
        ]));
    }

    public function dismissBenchmarkTask(Request $request, string $taskId): JsonResponse
    {
        $task = BenchmarkCollectionTask::query()->find($taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        if (! $this->coachCanAccessTeam($request, (string) $task->team_id)) {
            return $this->forbidden('You do not have access to this task');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->benchmarkTaskPersistenceService->dismissTask($taskId, $validated['reason'] ?? null));
    }

    public function benchmarkTaskCompletionWorkflow(Request $request, string $taskId): JsonResponse
    {
        $task = BenchmarkCollectionTask::query()->find($taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        if (! $this->coachCanAccessTeam($request, (string) $task->team_id)) {
            return $this->forbidden('You do not have access to this task');
        }

        return response()->json($this->benchmarkTaskCompletionService->getCompletionWorkflow($taskId));
    }

    public function completeBenchmarkTaskWithPayload(Request $request, string $taskId): JsonResponse
    {
        $task = BenchmarkCollectionTask::query()->find($taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        if (! $this->coachCanAccessTeam($request, (string) $task->team_id)) {
            return $this->forbidden('You do not have access to this task');
        }

        $result = $this->benchmarkTaskCompletionService->completeTaskWithPayload(
            $taskId,
            $request->all(),
            (string) $request->user()?->id,
        );

        return response()->json($result, ($result['ok'] ?? false) ? HttpCodes::HTTP_OK : HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function listPlayerBenchmarkTasks(Request $request): JsonResponse
    {
        $playerId = $this->authenticatedPlayerId($request);
        if (! $playerId) {
            return $this->forbidden('Player account could not be resolved');
        }

        return response()->json($this->benchmarkTaskPersistenceService->listPlayerTasks($playerId, [
            'team_id' => $request->query('team_id'),
            'status' => $request->query('status'),
            'task_type' => $request->query('task_type'),
            'include_dismissed' => $request->boolean('include_dismissed'),
        ]));
    }

    public function showPlayerBenchmarkTask(Request $request, string $taskId): JsonResponse
    {
        $playerId = $this->authenticatedPlayerId($request);
        if (! $playerId) {
            return $this->forbidden('Player account could not be resolved');
        }

        $result = $this->benchmarkTaskPersistenceService->getPlayerTask($taskId, $playerId);
        if (! ($result['ok'] ?? false)) {
            return $this->notFound('Benchmark task not found');
        }

        return response()->json($result);
    }

    public function startPlayerBenchmarkTask(Request $request, string $taskId): JsonResponse
    {
        $task = $this->playerVisibleTaskForRequest($request, $taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        return response()->json($this->benchmarkTaskPersistenceService->startTask($taskId, [
            'started_by_user_id' => (string) $request->user()?->id,
            'source' => 'player_dashboard',
        ]));
    }

    public function completePlayerBenchmarkTask(Request $request, string $taskId): JsonResponse
    {
        $task = $this->playerVisibleTaskForRequest($request, $taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        $payload = [
            'completed_by_user_id' => (string) $request->user()?->id,
            'source' => 'player_dashboard',
            'payload' => $request->all(),
        ];
        $result = $this->benchmarkTaskPersistenceService->markTaskComplete($taskId, $payload);
        if ($result['ok'] ?? false) {
            $review = $this->benchmarkTaskReviewService->recordCompletionSubmission(
                $taskId,
                (string) $request->user()?->id,
                $payload,
            );
            $result['review'] = $review;
            $result['task'] = $review['task'] ?? $result['task'] ?? null;
        }

        return response()->json($this->withCompletionRefresh($result, $taskId, $this->days($request)));
    }

    public function dismissPlayerBenchmarkTask(Request $request, string $taskId): JsonResponse
    {
        $task = $this->playerVisibleTaskForRequest($request, $taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->benchmarkTaskPersistenceService->dismissTask($taskId, $validated['reason'] ?? 'Dismissed by player'));
    }

    public function playerBenchmarkTaskCompletionWorkflow(Request $request, string $taskId): JsonResponse
    {
        $task = $this->playerVisibleTaskForRequest($request, $taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        return response()->json(
            $this->benchmarkTaskCompletionService->getCompletionWorkflow($taskId, (string) $request->user()?->id)
        );
    }

    public function playerBenchmarkTaskReviewStatus(Request $request, string $taskId): JsonResponse
    {
        $task = $this->playerVisibleTaskForRequest($request, $taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        return response()->json(
            $this->benchmarkTaskReviewService->reviewStatusForTask($taskId, (string) $request->user()?->id)
        );
    }

    public function completePlayerBenchmarkTaskWithPayload(Request $request, string $taskId): JsonResponse
    {
        $task = $this->playerVisibleTaskForRequest($request, $taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        $result = $this->benchmarkTaskCompletionService->completeTaskWithPayload(
            $taskId,
            $request->all(),
            (string) $request->user()?->id,
        );

        return response()->json($result, ($result['ok'] ?? false) ? HttpCodes::HTTP_OK : HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function refreshTeamBenchmarks(Request $request, string $teamId): JsonResponse
    {
        if (! $this->teamIsAccessible($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        return response()->json($this->benchmarkRefreshService->refreshTeamBenchmarks($teamId, $this->days($request)));
    }

    private function days(Request $request): int
    {
        $days = (int) ($request->query('days', $request->input('days', 365)));

        return max(7, min(365, $days));
    }

    private function teamIsAccessible(Request $request, string $teamId): bool
    {
        return Team::query()->whereKey($teamId)->exists()
            && $this->coachCanAccessTeam($request, $teamId);
    }

    private function authenticatedPlayerId(Request $request): ?string
    {
        $user = $request->user();
        if (! $user || (string) $user->type !== 'player') {
            return null;
        }

        return (string) $user->id;
    }

    private function playerVisibleTaskForRequest(Request $request, string $taskId): ?BenchmarkCollectionTask
    {
        $playerId = $this->authenticatedPlayerId($request);
        if (! $playerId) {
            return null;
        }

        return BenchmarkCollectionTask::query()
            ->whereKey($taskId)
            ->where('assigned_to_player_id', $playerId)
            ->where('status', '!=', BenchmarkCollectionTask::STATUS_DRAFT)
            ->first();
    }

    private function withCompletionRefresh(array $result, string $taskId, int $days): array
    {
        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        try {
            $result['refresh'] = $this->benchmarkRefreshService->refreshAfterTaskCompletion($taskId, [
                'days' => $days,
            ]);
        } catch (\Throwable $exception) {
            $result['refresh'] = [
                'task_id' => $taskId,
                'refreshed_at' => now()->toIso8601String(),
                'refresh_status' => 'failed',
                'changed_signals' => [],
                'warnings' => [$exception->getMessage()],
            ];
        }

        return $result;
    }

    private function coachCanAccessTeam(Request $request, string $teamId): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return CoachTeam::query()
            ->where('team_id', $teamId)
            ->where('coach_id', (string) $user->id)
            ->exists();
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json([
            'code' => 'INTEL-NF',
            'message' => $message,
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_NOT_FOUND);
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'code' => 'INTEL-AUTH',
            'message' => $message,
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
