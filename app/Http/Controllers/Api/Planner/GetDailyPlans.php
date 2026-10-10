<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\DailyPlan;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: list every Daily Planner plan (drafts, published, templates) for the
 * coach's teams. Each plan carries its assigned_player_ids.
 */
class GetDailyPlans extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'team_id' => ['sometimes', 'required', 'string', 'max:191'],
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ]);
        try {
            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

            $plans = DailyPlan::whereIn('team_id', $teamIds)
                ->when(isset($filters['team_id']), fn ($query) => $query->where('team_id', $filters['team_id']))
                ->when(isset($filters['date']), fn ($query) => $query->where('date', $filters['date']))
                ->with('assignments')
                ->orderByDesc('updated_at')
                ->get();

            return response()->json([
                'code'    => '090',
                'message' => 'list of daily plans',
                'status'  => 'success',
                'planner_contract_version'=>'2.0',
                'data' => $plans->map(fn($plan)=>app(\App\Services\Planner\PlannerContract::class)->plan($plan)),
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
