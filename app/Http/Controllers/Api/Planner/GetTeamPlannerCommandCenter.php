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

class GetTeamPlannerCommandCenter extends Controller
{
    public function __invoke(string $teamId, Request $request, CoachPlannerCommandCenterService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'PLC-F',
                'message' => 'not allowed to view planner command center for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        $dailyPlanId = trim((string) $request->query('dailyPlanId', $request->query('daily_plan_id', '')));
        if ($dailyPlanId !== '') {
            $belongsToTeam = DailyPlan::query()
                ->whereKey($dailyPlanId)
                ->where('team_id', $teamId)
                ->exists();

            if (! $belongsToTeam) {
                return response()->json([
                    'code' => 'PLC-PF',
                    'message' => 'daily plan does not belong to this team',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }
        }

        try {
            $data = $service->buildForTeam($teamId, [
                'daily_plan_id' => $dailyPlanId !== '' ? $dailyPlanId : null,
                'days' => $this->days($request),
            ]);
        } catch (\Throwable $e) {
            // The command center is a heavy aggregation over many planner/benchmark
            // tables — if any of them is missing (e.g. not migrated on this env) or the
            // build fails, degrade gracefully so the rest of the planner still loads
            // instead of showing "could not load".
            \Illuminate\Support\Facades\Log::warning('planner command center build failed: '.$e->getMessage());
            return response()->json([
                'code' => 'PLC-EMPTY',
                'message' => 'coach planner command center unavailable',
                'status' => 'success',
                'data' => null,
            ], HttpCodes::HTTP_OK);
        }

        return response()->json([
            'code' => 'PLC',
            'message' => 'coach planner command center',
            'status' => 'success',
            'data' => $data,
        ], HttpCodes::HTTP_OK);
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
        $days = (int) $request->query('days', 365);

        return max(7, min(365, $days));
    }
}
