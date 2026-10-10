<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\{CoachTeam,PlayerTeam,User,Profile,DailyPlanProgress,Practice};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GetRosterActivity extends Controller
{
    public function __invoke(Request $request, string $id)
    {
        abort_unless(CoachTeam::where('team_id', $id)->where('coach_id', $request->user()->id)->exists(), 404);
        $ids = PlayerTeam::where('team_id', $id)->pluck('user_id')->unique();
        $profiles = Profile::whereIn('user_id', $ids)->get()->keyBy('user_id');
        $workouts = DailyPlanProgress::whereIn('user_id', $ids)->whereHas('plan', fn($q) => $q->where('team_id', $id))
            ->whereNotNull('completed_at')->selectRaw('user_id, MAX(completed_at) as latest')->groupBy('user_id')->pluck('latest', 'user_id');
        $owned = Practice::where('team_id', $id)->whereIn('user_id', $ids)->where('is_completed', true)
            ->selectRaw('user_id, MAX(finished) as latest')->groupBy('user_id')->pluck('latest', 'user_id');
        $lineups = DB::table('practice_line_ups as l')->join('practices as p', 'p.id', '=', 'l.practice_id')
            ->where('p.team_id', $id)->where('p.is_completed', true)->whereNull('p.deleted_at')->whereNull('l.deleted_at')
            ->whereIn('l.user_id', $ids)->selectRaw('l.user_id, MAX(p.finished) as latest')->groupBy('l.user_id')->pluck('latest', 'user_id');
        $iso = fn($value) => $value ? Carbon::parse($value)->toIso8601String() : null;
        $rows = User::whereIn('id', $ids)->get(['id', 'last_login_at'])->map(function ($user) use ($profiles, $workouts, $owned, $lineups, $iso) {
            $profile = $profiles->get($user->id);
            $session = max($owned->get($user->id) ?? '', $lineups->get($user->id) ?? '');
            return ['id'=>$user->id, 'last_login_at'=>$iso($user->last_login_at),
                'profile_updated_at'=>$profile && $profile->updated_at > $profile->created_at ? $iso($profile->updated_at) : null,
                'workout_completed_at'=>$iso($workouts->get($user->id)), 'session_completed_at'=>$iso($session)];
        });
        return response()->json(['data'=>$rows])->header('Cache-Control', 'no-store');
    }
}
