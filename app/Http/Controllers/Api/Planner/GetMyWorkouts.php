<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Player: the published plans assigned to me ("My Workouts"), each with my own
 * progress attached.
 */
class GetMyWorkouts extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $userId = Auth::id();

            $planIds = DailyPlanAssignment::where('user_id', $userId)->pluck('plan_id')->all();

            $plans = DailyPlan::whereIn('id', $planIds)
                ->where('status', 'published')
                ->orderByDesc('date')
                ->get();

            $progress = DailyPlanProgress::where('user_id', $userId)
                ->whereIn('plan_id', $plans->pluck('id'))
                ->get()
                ->keyBy('plan_id');

            $data = $plans->map(function (DailyPlan $plan) use ($progress) {
                $arr = $plan->toArray();
                $arr['progress'] = $progress->get($plan->id);

                return $arr;
            });

            return response()->json([
                'code'    => '093',
                'message' => 'my workouts',
                'status'  => 'success',
                'data'    => $data,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetMyWorkouts: ' . $e->getMessage());

            return response()->json([
                'code'    => '093-E',
                'message' => 'failed to fetch my workouts',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
