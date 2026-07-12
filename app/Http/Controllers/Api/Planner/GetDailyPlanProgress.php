<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use App\Models\Profile;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: every assigned player's progress for one daily plan. Powers the coach's
 * "View Players" review screen. The plan is what was assigned; each player's
 * progress is what THEY actually did.
 */
class GetDailyPlanProgress extends Controller
{
    public function __invoke(string $planId): JsonResponse
    {
        try {
            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

            $plan = DailyPlan::where('id', $planId)->whereIn('team_id', $teamIds)->first();
            if (! $plan) {
                return response()->json([
                    'code'    => '0C0-F',
                    'message' => 'plan not found for this coach',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_NOT_FOUND);
            }

            $userIds = DailyPlanAssignment::where('plan_id', $planId)->pluck('user_id')->unique()->values()->all();

            $progress = DailyPlanProgress::where('plan_id', $planId)
                ->whereIn('user_id', $userIds)
                ->get()
                ->keyBy('user_id');

            $profiles = Profile::whereIn('user_id', $userIds)->get()->keyBy('user_id');

            $players = collect($userIds)->map(function ($uid) use ($progress, $profiles) {
                $profile = $profiles->get($uid);
                return [
                    'player' => [
                        'id'         => (string) $uid,
                        'first_name' => $profile->first_name ?? '',
                        'last_name'  => $profile->last_name ?? '',
                        'photo'      => $profile->picture ?? null,
                        'position'   => '',
                    ],
                    'progress' => $progress->get($uid), // model (with items/completed_at/coach_review) or null
                ];
            })->values();

            return response()->json([
                'code'    => '0C0',
                'message' => 'daily plan player progress',
                'status'  => 'success',
                'data'    => [
                    'plan'    => $plan,
                    'players' => $players,
                ],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetDailyPlanProgress: ' . $e->getMessage());

            return response()->json([
                'code'    => '0C0-E',
                'message' => 'failed to fetch plan progress',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
