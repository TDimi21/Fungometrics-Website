<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\WeeklyPlannerRollupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetWeeklyPlannerRollup extends Controller
{
    public function __invoke(string $teamId, Request $request, WeeklyPlannerRollupService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'WPR-F',
                'message' => 'not allowed to view weekly planner rollup for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'WPR',
            'message' => 'weekly planner rollup',
            'status' => 'success',
            'data' => $service->buildTeamWeeklyRollup($teamId, [
                'start_date' => $request->query('start_date'),
                'end_date' => $request->query('end_date'),
                'days' => $this->days($request),
                'include_players' => $request->query('include_players', true),
                'include_benchmark_intelligence' => $request->query('include_benchmark_intelligence', true),
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
        $days = (int) $request->query('days', 7);

        return max(1, min(365, $days));
    }
}
