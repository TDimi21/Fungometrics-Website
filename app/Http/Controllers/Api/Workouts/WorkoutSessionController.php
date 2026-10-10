<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workouts;

use App\Http\Controllers\Controller;
use App\Models\{DailyPlanAssignment,DailyPlan,Practice};
use Illuminate\Http\Request;

class WorkoutSessionController extends Controller
{
    public function index(Request $r, string $id)
    {
        abort_unless(DailyPlanAssignment::where('plan_id', $id)->where('user_id', $r->user()->id)->exists(), 404);
        $plan = DailyPlan::where('status', 'published')->findOrFail($id);
        $uid = $r->user()->id;
        $sessions = Practice::where('team_id', $plan->team_id)->whereIn('type',['P','T','C','L'])->where(fn ($q) => $q->where('user_id', $uid)->orWhereHas('lineup', fn ($l) => $l->where('user_id', $uid)))->latest()->limit(100)->get(['id','type','modes','started','is_completed']);
        return response()->json(['planner_contract_version'=>'2.0','data' => $sessions]);
    }
}
