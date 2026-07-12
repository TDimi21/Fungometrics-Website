<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\DailyPlan;
use App\Services\Planner\DailyPlanPlayerUpdateService;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetDailyPlanAcknowledgements extends Controller
{
    public function __invoke(string $dailyPlanId, DailyPlanPlayerUpdateService $updateService): JsonResponse
    {
        try {
            $teamIds = CoachTeam::query()
                ->where('coach_id', Auth::id())
                ->pluck('team_id')
                ->all();

            $plan = DailyPlan::query()
                ->where('id', $dailyPlanId)
                ->whereIn('team_id', $teamIds)
                ->first();

            if (! $plan) {
                return response()->json([
                    'code' => '0C2-F',
                    'message' => 'not allowed to view acknowledgement status for this plan',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            return response()->json([
                'code' => '0C2',
                'message' => 'daily plan acknowledgement status',
                'status' => 'success',
                'data' => $updateService->buildTeamAcknowledgementStatus($dailyPlanId),
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetDailyPlanAcknowledgements: '.$e->getMessage());

            return response()->json([
                'code' => '0C2-E',
                'message' => 'failed to fetch acknowledgement status',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
