<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Services\Planner\DailyPlanPlayerUpdateService;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetDailyPlanUpdateStatus extends Controller
{
    public function __invoke(string $dailyPlanId, DailyPlanPlayerUpdateService $updateService): JsonResponse
    {
        try {
            $playerId = (string) Auth::id();
            $assigned = DailyPlanAssignment::query()
                ->where('plan_id', $dailyPlanId)
                ->where('user_id', $playerId)
                ->exists();

            $planExists = DailyPlan::query()
                ->where('id', $dailyPlanId)
                ->where('status', 'published')
                ->exists();

            if (! $assigned || ! $planExists) {
                return response()->json([
                    'code' => '097-NF',
                    'message' => 'workout update not found',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_NOT_FOUND);
            }

            return response()->json([
                'code' => '097',
                'message' => 'workout update status',
                'status' => 'success',
                'data' => $updateService->buildPlayerPlanUpdateStatus($dailyPlanId, $playerId),
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetDailyPlanUpdateStatus: '.$e->getMessage());

            return response()->json([
                'code' => '097-E',
                'message' => 'failed to fetch workout update status',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
