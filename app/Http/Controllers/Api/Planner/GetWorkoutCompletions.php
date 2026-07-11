<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\DailyPlan;
use App\Models\DailyPlanProgress;
use App\Models\Profile;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: recently completed workouts across the coach's teams. The coach app polls
 * this, diffs against what it has already seen, and raises an in-app alert for each
 * new completion. Enriched with plan + player names so the alert reads nicely.
 */
class GetWorkoutCompletions extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

            $plans = DailyPlan::whereIn('team_id', $teamIds)->get()->keyBy('id');

            $completions = DailyPlanProgress::whereIn('plan_id', $plans->keys())
                ->whereNotNull('completed_at')
                ->orderByDesc('completed_at')
                ->limit(100)
                ->get();

            $profiles = Profile::whereIn('user_id', $completions->pluck('user_id')->unique()->all())
                ->get()
                ->keyBy('user_id');

            $data = $completions->map(function (DailyPlanProgress $c) use ($plans, $profiles) {
                $profile = $profiles->get($c->user_id);
                $name    = trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? ''));

                return [
                    'id'           => $c->id,
                    'plan_id'      => $c->plan_id,
                    'plan_name'    => $plans->get($c->plan_id)->name ?? null,
                    'user_id'      => $c->user_id,
                    'player_name'  => $name !== '' ? $name : null,
                    'completed_at' => optional($c->completed_at)->toIso8601String(),
                ];
            })->values();

            return response()->json([
                'code'    => '0A0',
                'message' => 'workout completions',
                'status'  => 'success',
                'data'    => $data,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetWorkoutCompletions: ' . $e->getMessage());

            return response()->json([
                'code'    => '0A0-E',
                'message' => 'failed to fetch workout completions',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
