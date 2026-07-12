<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\NextWeekPlanGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetNextWeekCalendarDraft extends Controller
{
    public function __invoke(string $teamId, Request $request, NextWeekPlanGeneratorService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'NWCD-F',
                'message' => 'not allowed to generate next week calendar draft for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'NWCD',
            'message' => 'next week calendar draft',
            'status' => 'success',
            'data' => $service->buildCalendarDraft($teamId, $this->options($request)),
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

    private function options(Request $request): array
    {
        return [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'next_week_start_date' => $request->query('next_week_start_date'),
            'days' => max(1, min(365, (int) $request->query('days', 7))),
            'plan_days' => max(1, min(7, (int) $request->query('plan_days', 5))),
            'max_minutes_per_day' => max(30, min(180, (int) $request->query('max_minutes_per_day', 90))),
            'include_player_assignments' => $request->query('include_player_assignments', true),
            'include_benchmark_collection' => $request->query('include_benchmark_collection', true),
            'include_recovery_day' => $request->query('include_recovery_day', true),
        ];
    }
}
