<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\DailyPlan;
use App\Models\DailyPlanProgress;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: mark a player's workout reviewed / leave feedback. Stored on the player's
 * progress row (coach_review) so the player sees the feedback on their completed
 * workout. Only a coach on the plan's team can review.
 */
class SaveCoachWorkoutReview extends Controller
{
    public function __invoke(Request $request, string $planId, string $playerId): JsonResponse
    {
        try {
            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

            $plan = DailyPlan::where('id', $planId)->whereIn('team_id', $teamIds)->first();
            if (! $plan) {
                return response()->json([
                    'code'    => '0C1-F',
                    'message' => 'not allowed to review this workout',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            $validated = $request->validate([
                'reviewed'    => ['nullable', 'boolean'],
                'feedback'    => ['nullable', 'string', 'max:4000'],
                'reviewed_at' => ['nullable', 'date'],
            ]);

            $progress = DailyPlanProgress::firstOrNew(['plan_id' => $planId, 'user_id' => $playerId]);
            $progress->coach_review = [
                'reviewed'    => $validated['reviewed'] ?? true,
                'reviewed_at' => $validated['reviewed_at'] ?? now()->toIso8601String(),
                'reviewed_by' => (string) Auth::id(),
                'feedback'    => $validated['feedback'] ?? '',
            ];
            $progress->save();

            return response()->json([
                'code'    => '0C1',
                'message' => 'review saved',
                'status'  => 'success',
                'data'    => $progress,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('SaveCoachWorkoutReview: ' . $e->getMessage());

            return response()->json([
                'code'    => '0C1-E',
                'message' => 'failed to save review',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
