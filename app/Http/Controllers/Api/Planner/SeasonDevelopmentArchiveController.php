<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\SeasonDevelopmentArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SeasonDevelopmentArchiveController extends Controller
{
    public function team(string $teamId, Request $request, SeasonDevelopmentArchiveService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'SDA-F',
                'message' => 'not allowed to view season development archive for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'SDA',
            'message' => 'season development archive',
            'status' => 'success',
            'data' => $service->buildTeamSeasonArchive($teamId, $this->filters($request)),
        ], HttpCodes::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'season_start_date' => ['nullable', 'date'],
            'season_end_date' => ['nullable', 'date'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'include_player_rows' => ['nullable'],
            'include_benchmark_progress' => ['nullable'],
            'include_report_delivery' => ['nullable'],
            'include_communication_rhythm' => ['nullable'],
            'include_weekly_reports' => ['nullable'],
        ]);

        $validated['weeks'] = max(1, min(52, (int) ($validated['weeks'] ?? 12)));

        return $validated;
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
}
