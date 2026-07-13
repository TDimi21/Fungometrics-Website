<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\DevelopmentProgramHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class DevelopmentProgramHealthController extends Controller
{
    public function team(string $teamId, Request $request, DevelopmentProgramHealthService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden();
        }

        return response()->json([
            'code' => 'DPH-T',
            'message' => 'development program health',
            'status' => 'success',
            'data' => $service->buildTeamHealthScore($teamId, $this->filters($request)),
        ], HttpCodes::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'include_weekly_reports' => ['nullable'],
            'include_season_archive' => ['nullable'],
            'include_population_learning' => ['nullable'],
            'include_decision_brief' => ['nullable'],
        ]);
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

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'code' => 'DPH-F',
            'message' => 'not allowed to view development program health for this team',
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
