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

class GetDailyPlanCommandCenter extends Controller
{
    public function __invoke(string $dailyPlanId, Request $request, CoachPlannerCommandCenterService $service): JsonResponse
    {
        $plan = DailyPlan::query()->whereKey($dailyPlanId)->first();
        if (! $plan || ! $this->canAccessTeam($request, (string) $plan->team_id)) {
            return response()->json([
                'code' => 'PLC-DPF',
                'message' => 'not allowed to view planner command center for this daily plan',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'PLC-DP',
            'message' => 'daily plan command center',
            'status' => 'success',
            'data' => $service->buildForDailyPlan($dailyPlanId, [
                'days' => $this->days($request),
            ]),
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
