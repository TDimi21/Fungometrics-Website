<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use App\Services\Planner\DailyPlanPlayerUpdateService;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Player: a single assigned + published plan with my progress (the step-by-step
 * workout screen).
 */
class GetMyWorkout extends Controller
{
    public function __invoke(string $id, DailyPlanPlayerUpdateService $updateService): JsonResponse
    {
        try {
            $userId = Auth::id();

            $assigned = DailyPlanAssignment::where('plan_id', $id)
                ->where('user_id', $userId)
                ->exists();

            $plan = $assigned
                ? DailyPlan::where('id', $id)->where('status', 'published')->first()
                : null;

            if (! $plan) {
                return response()->json([
                    'code'    => '094-NF',
                    'message' => 'workout not found',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_NOT_FOUND);
            }

            $arr = $plan->toArray();
            $arr['progress'] = DailyPlanProgress::where('plan_id', $id)
                ->where('user_id', $userId)
                ->first();
            $arr['update_status'] = $updateService->buildPlayerPlanUpdateStatus((string) $plan->id, (string) $userId);

            return response()->json([
                'code'    => '094',
                'message' => 'my workout',
                'status'  => 'success',
                'data'    => $arr,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetMyWorkout: ' . $e->getMessage());

            return response()->json([
                'code'    => '094-E',
                'message' => 'failed to fetch workout',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
