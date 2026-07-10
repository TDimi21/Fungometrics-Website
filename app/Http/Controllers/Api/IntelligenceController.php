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
use App\Services\Intelligence\BenchmarkTaskAssignmentService;
use App\Services\Intelligence\BenchmarkTaskPersistenceService;
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

    public function completeBenchmarkTask(Request $request, string $taskId): JsonResponse
    {
        $task = BenchmarkCollectionTask::query()->find($taskId);
        if (! $task) {
            return $this->notFound('Benchmark task not found');
        }

        if (! $this->coachCanAccessTeam($request, (string) $task->team_id)) {
            return $this->forbidden('You do not have access to this task');
        }

        return response()->json($this->benchmarkTaskPersistenceService->markTaskComplete($taskId, $request->all()));
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

        return response()->json($this->benchmarkTaskPersistenceService->markTaskComplete($taskId, [
            'completed_by_user_id' => (string) $request->user()?->id,
            'source' => 'player_dashboard',
            'payload' => $request->all(),
        ]));
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
