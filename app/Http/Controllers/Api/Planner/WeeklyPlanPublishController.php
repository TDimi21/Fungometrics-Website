<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\DailyPlan;
use App\Services\Planner\WeeklyPlanPublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class WeeklyPlanPublishController extends Controller
{
    public function list(string $teamId, Request $request, WeeklyPlanPublishService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden('not allowed to view weekly draft plans for this team');
        }

        return response()->json([
            'code' => 'WPP-L',
            'message' => 'weekly draft plans',
            'status' => 'success',
            'data' => $service->listWeeklyDrafts($teamId),
        ], HttpCodes::HTTP_OK);
    }

    public function publishPlan(string $dailyPlanId, Request $request, WeeklyPlanPublishService $service): JsonResponse
    {
        $plan = DailyPlan::query()->find($dailyPlanId);
        if (! $plan || ! $this->canAccessTeam($request, (string) $plan->team_id)) {
            return $this->forbidden('not allowed to publish this Daily Plan');
        }

        $validated = $request->validate($this->publishValidationRules());

        $result = $service->publishDraftDay($dailyPlanId, [
            ...$validated,
            'published_by_user_id' => (string) $request->user()?->id,
        ]);

        return $this->response($result, 'WPP-P', 'weekly draft plan publish result');
    }

    public function publishAndAssignPlan(string $dailyPlanId, Request $request, WeeklyPlanPublishService $service): JsonResponse
    {
        $plan = DailyPlan::query()->find($dailyPlanId);
        if (! $plan || ! $this->canAccessTeam($request, (string) $plan->team_id)) {
            return $this->forbidden('not allowed to publish and assign this Daily Plan');
        }

        $validated = $request->validate($this->publishValidationRules());
        $playerIds = $validated['player_ids'] ?? [];

        $result = $service->publishAndAssign($dailyPlanId, $playerIds, [
            ...$validated,
            'published_by_user_id' => (string) $request->user()?->id,
        ]);

        return $this->response($result, 'WPP-PA', 'weekly draft plan publish and assign result');
    }

    public function publishWeeklyDrafts(string $teamId, Request $request, WeeklyPlanPublishService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden('not allowed to publish weekly draft plans for this team');
        }

        $validated = $request->validate([
            'daily_plan_ids' => ['nullable', 'array'],
            'daily_plan_ids.*' => ['string'],
            ...$this->publishValidationRules(),
        ]);

        $result = $service->publishWeeklyDrafts($teamId, $validated['daily_plan_ids'] ?? [], [
            ...$validated,
            'published_by_user_id' => (string) $request->user()?->id,
        ]);

        return $this->response($result, 'WPP-B', 'weekly draft plans publish result');
    }

    private function publishValidationRules(): array
    {
        return [
            'assign_all' => ['nullable', 'boolean'],
            'player_ids' => ['nullable', 'array'],
            'player_ids.*' => ['string'],
            'republish' => ['nullable', 'boolean'],
            'notify_players' => ['nullable', 'boolean'],
            'scheduled_for' => ['nullable', 'date'],
        ];
    }

    private function canAccessTeam(Request $request, string $teamId): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if (in_array((string) ($user->type ?? ''), ['admin', 'super_admin'], true)) {
            return true;
        }

        return CoachTeam::query()
            ->where('team_id', $teamId)
            ->where('coach_id', (string) $user->id)
            ->exists();
    }

    private function response(array $result, string $code, string $message): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'status' => in_array(($result['status'] ?? ''), ['failed'], true) ? 'error' : 'success',
            'data' => $result,
        ], ($result['status'] ?? null) === 'failed'
            ? HttpCodes::HTTP_UNPROCESSABLE_ENTITY
            : HttpCodes::HTTP_OK);
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'code' => 'WPP-F',
            'message' => $message,
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
