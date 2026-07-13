<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\SeasonArchiveExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SeasonArchiveExportController extends Controller
{
    public function team(string $teamId, Request $request, SeasonArchiveExportService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'SAE-F',
                'message' => 'not allowed to export season archive for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'SAE',
            'message' => 'season archive export',
            'status' => 'success',
            'data' => $service->buildExport($teamId, [
                ...$this->filters($request),
                'current_user_id' => (string) $request->user()->id,
            ]),
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
            'format' => ['nullable', Rule::in(['summary', 'text', 'html', 'pdf'])],
            'audience' => ['nullable', Rule::in(['coach', 'staff', 'director', 'players', 'parents'])],
            'include_player_rows' => ['nullable'],
            'include_benchmark_progress' => ['nullable'],
            'include_planner_progress' => ['nullable'],
            'include_communication_summary' => ['nullable'],
            'include_weekly_timeline' => ['nullable'],
            'include_next_steps' => ['nullable'],
            'include_private_notes' => ['nullable'],
            'include_internal_qa' => ['nullable'],
        ]);

        $validated['weeks'] = max(1, min(52, (int) ($validated['weeks'] ?? 12)));
        $validated['format'] = (string) ($validated['format'] ?? 'summary');
        $validated['audience'] = (string) ($validated['audience'] ?? 'staff');

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
