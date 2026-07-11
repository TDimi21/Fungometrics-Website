<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: delete a Daily Planner plan (soft delete) and drop its assignments.
 */
class DeleteDailyPlan extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        try {
            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

            $plan = DailyPlan::where('id', $id)
                ->whereIn('team_id', $teamIds)
                ->first();

            if ($plan) {
                DailyPlanAssignment::where('plan_id', $plan->id)->delete();
                $plan->delete();
            }

            return response()->json([
                'code'    => '092',
                'message' => 'daily plan deleted',
                'status'  => 'success',
                'data'    => [],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('DeleteDailyPlan: ' . $e->getMessage());

            return response()->json([
                'code'    => '092-E',
                'message' => 'failed to delete daily plan',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
