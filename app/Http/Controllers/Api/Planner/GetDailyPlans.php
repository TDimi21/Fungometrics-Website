<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\DailyPlan;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: list every Daily Planner plan (drafts, published, templates) for the
 * coach's teams. Each plan carries its assigned_player_ids.
 */
class GetDailyPlans extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

            $plans = DailyPlan::whereIn('team_id', $teamIds)
                ->with('assignments')
                ->orderByDesc('updated_at')
                ->get();

            return response()->json([
                'code'    => '090',
                'message' => 'list of daily plans',
                'status'  => 'success',
                'data'    => $plans,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetDailyPlans: ' . $e->getMessage());

            return response()->json([
                'code'    => '090-E',
                'message' => 'failed to fetch daily plans',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
