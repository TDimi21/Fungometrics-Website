<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\CoachWeeklyReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetCoachWeeklyReportExport extends Controller
{
    public function __invoke(string $teamId, Request $request, CoachWeeklyReportExportService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'CWRE-F',
                'message' => 'not allowed to export weekly report for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'CWRE',
            'message' => 'coach weekly report export',
            'status' => 'success',
            'data' => $service->buildExport($teamId, [
                'start_date' => $request->query('start_date'),
                'end_date' => $request->query('end_date'),
                'days' => $this->days($request),
                'format' => $request->query('format', 'summary'),
                'audience' => $request->query('audience', 'coach'),
                'include_player_rows' => $request->query('include_player_rows', true),
                'include_benchmark_details' => $request->query('include_benchmark_details', true),
                'include_pending_reviews' => $request->query('include_pending_reviews', true),
                'include_next_week_priorities' => $request->query('include_next_week_priorities', true),
                'include_private_notes' => $request->query('include_private_notes', false),
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
