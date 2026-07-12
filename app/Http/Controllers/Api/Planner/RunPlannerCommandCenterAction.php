<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\DailyPlan;
use App\Services\Planner\CoachPlannerCommandCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class RunPlannerCommandCenterAction extends Controller
{
    public function __invoke(string $teamId, Request $request, CoachPlannerCommandCenterService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'action_type' => (string) $request->input('action_type', ''),
                'status' => 'failed',
                'message' => 'You do not have access to this team.',
                'result' => [],
                'updated_command_center' => [],
                'warnings' => ['Planner command center action is coach/admin only.'],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'action_type' => ['required', 'string', 'max:80'],
            'daily_plan_id' => ['nullable', 'string'],
            'task_ids' => ['nullable', 'array'],
            'task_ids.*' => ['string'],
            'player_ids' => ['nullable', 'array'],
            'player_ids.*' => ['string'],
            'message' => ['nullable', 'string', 'max:2000'],
            'options' => ['nullable', 'array'],
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dailyPlanId = trim((string) ($validated['daily_plan_id'] ?? ''));
        if ($dailyPlanId !== '') {
            $belongsToTeam = DailyPlan::query()
                ->whereKey($dailyPlanId)
                ->where('team_id', $teamId)
                ->exists();

            if (! $belongsToTeam) {
                return response()->json([
                    'action_type' => (string) $validated['action_type'],
                    'status' => 'failed',
                    'message' => 'Daily Plan does not belong to this team.',
                    'result' => [],
                    'updated_command_center' => $service->buildForTeam($teamId, [
                        'days' => $this->days($request),
                    ]),
                    'warnings' => ['Daily Plan authorization failed.'],
                ], HttpCodes::HTTP_FORBIDDEN);
            }
        }

        $result = $service->runAction($teamId, (string) $validated['action_type'], [
            ...$validated,
            'days' => $this->days($request),
        ], (string) $request->user()?->id);

        return response()->json($result, ($result['status'] ?? null) === 'failed'
            ? HttpCodes::HTTP_UNPROCESSABLE_ENTITY
            : HttpCodes::HTTP_OK);
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

    private function days(Request $request): int
    {
        $days = (int) ($request->query('days', $request->input('days', 365)));

        return max(7, min(365, $days));
    }
}
