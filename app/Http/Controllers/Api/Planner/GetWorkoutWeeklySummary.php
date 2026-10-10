<?php

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\{CoachTeam, DailyPlanAssignment};
use App\Services\Planner\WorkoutWeeklySummary;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GetWorkoutWeeklySummary extends Controller
{
    public function __invoke(Request $request, WorkoutWeeklySummary $summary, ?string $playerId = null)
    {
        $request->validate(['date'=>'nullable|date_format:Y-m-d']);
        $teamIds = null;
        if ($playerId !== null) {
            $teamIds = CoachTeam::where('coach_id', $request->user()->id)->pluck('team_id')->all();
            abort_unless(DailyPlanAssignment::where('user_id', $playerId)->whereHas('plan', fn($q) => $q->whereIn('team_id', $teamIds))->exists(), 404);
        } else $playerId = (string)$request->user()->id;
        return response()->json(['status'=>'success', 'data'=>$summary->build($playerId, Carbon::parse($request->input('date') ?: now()->toDateString()), $teamIds)]);
    }
}
