<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Statistics\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * GET /api/coach/leaderboard/{team}
 *
 * The Hall of Fame Wall feed: every leaderboard category for the team in one
 * cached response. Auth is coach-of-team (or admin); server-computed and cached
 * so the client's 5s rotation is pure client-side, not per-category requests.
 */
class GetTeamLeaderboard extends Controller
{
    public function __invoke(Request $request, string $team, LeaderboardService $service): JsonResponse
    {
        try {
            $user = $request->user();
            $isAdmin = in_array((string) ($user->type ?? ''), ['admin', 'super_admin'], true);
            if (! $isAdmin && ! CoachTeam::where('team_id', $team)->where('coach_id', (string) ($user->id ?? ''))->exists()) {
                return response()->json([
                    'code' => 'LB-F', 'status' => 'error', 'message' => 'not allowed to view this team', 'data' => ['categories' => []],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            $data = Cache::remember("leaderboard_v1_{$team}", 300, fn () => $service->forTeam($team));

            return response()->json(['code' => 'LB', 'status' => 'success', 'message' => '', 'data' => $data], HttpCodes::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('GetTeamLeaderboard: ' . $e->getMessage());

            // Degrade gracefully so the dashboard falls back to its client-side data.
            return response()->json(['code' => 'LB-E', 'status' => 'error', 'message' => 'leaderboard unavailable', 'data' => ['categories' => []]], HttpCodes::HTTP_OK);
        }
    }
}
