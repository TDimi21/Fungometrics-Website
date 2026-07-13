<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\SeasonCommunicationRhythmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SeasonCommunicationRhythmController extends Controller
{
    public function team(string $teamId, Request $request, SeasonCommunicationRhythmService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden();
        }

        return response()->json([
            'code' => 'SCR-T',
            'message' => 'season communication rhythm',
            'status' => 'success',
            'data' => $service->buildTeamRhythm($teamId, $this->filters($request)),
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
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'include_staff_packets' => ['nullable'],
            'include_parent_summaries' => ['nullable'],
            'include_player_summaries' => ['nullable'],
            'include_internal_qa' => ['nullable'],
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
            'code' => 'SCR-F',
            'message' => 'not allowed to view season communication rhythm for this team',
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
