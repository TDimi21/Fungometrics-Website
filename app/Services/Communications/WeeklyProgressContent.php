<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\{DailyPlanAssignment,DailyPlanProgress,Practice,PlayerAssessment,FreeAssessment,PlayerFitness,Team};
use App\Services\Intelligence\PlayerIntelligenceService;
use Carbon\Carbon;

class WeeklyProgressContent
{
    public function buildForCoach(string $teamId, string $week): array
    {
        $start = Carbon::parse($week)->startOfDay();
        $end = $start->copy()->addWeek();
        if($end->isFuture()) {
            return [];
        }
        $sessions = Practice::where('team_id', $teamId)->where('is_completed', true)->where('finished', '>=', $start)->where('finished', '<', $end)->count();
        $submissions = DailyPlanProgress::whereHas('plan', fn ($q) => $q->where('team_id', $teamId)->where('status', 'published'))->where('completed_at', '>=', $start)->where('completed_at', '<', $end)->count();
        $assessments = PlayerAssessment::where('team_id', $teamId)->where('assessment_date', '>=', $start->toDateString())->where('assessment_date', '<', $end->toDateString())->count();
        $assessments += FreeAssessment::where('team_id', $teamId)->where('status', 'completed')->whereNotNull('report_snapshot')->where('assessment_date', '>=', $start->toDateString())->where('assessment_date', '<', $end->toDateString())->count();
        $next = \App\Models\DailyPlan::where('team_id', $teamId)->where('status', 'published')->where('date', '>=', $end->toDateString())->where('date', '<', $end->copy()->addWeek()->toDateString())->whereHas('assignments', fn ($q) => $q->where('schedule_status', 'active'))->get();
        if( ! $sessions && ! $submissions && ! $assessments && ! $next->contains(fn ($p) => $p->created_at >= $start && $p->created_at < $end)) {
            return [];
        }
        $lines = [(Team::find($teamId)?->name ?? 'Your team').' · '.$start->toDateString().' through '.$end->copy()->subDay()->toDateString(),'Completed team sessions: '.$sessions,'Player workout submissions: '.$submissions.' (submitted does not mean every drill was completed).','Saved assessment updates: '.$assessments];
        foreach($next->take(10) as $p) {
            $lines[] = 'Upcoming: '.$p->name.' · '.$p->date->format('Y-m-d');
        }
        $lines[] = 'Open your coach dashboard to review individual results, goals and development trends.';
        return $lines;
    }
    public function build(string $userId, string $teamId, string $week): array
    {
        $start = Carbon::parse($week)->startOfDay();
        $end = $start->copy()->addWeek();
        $previous = $start->copy()->subWeek();
        if ($end->isFuture()) {
            return [];
        }
        $sessions = Practice::where('team_id', $teamId)->where('is_completed', true)->where('finished', '>=', $start)->where('finished', '<', $end)
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhereHas('lineup', fn ($l) => $l->where('user_id', $userId)))->count();
        $submitted = DailyPlanProgress::where('user_id', $userId)->where('completed_at', '>=', $start)->where('completed_at', '<', $end)
            ->whereHas('plan', fn ($q) => $q->where('team_id', $teamId)->where('status', 'published'))->count();
        $assessments = PlayerAssessment::where('user_id', $userId)->where('team_id', $teamId)->where('assessment_date', '>=', $start->toDateString())->where('assessment_date', '<', $end->toDateString())->count();
        $assessments += FreeAssessment::where('team_id', $teamId)->where('status', 'completed')->whereNotNull('report_snapshot')->where('assessment_date', '>=', $start->toDateString())->where('assessment_date', '<', $end->toDateString())->whereHas('results', fn ($q) => $q->where('player_id', $userId))->count();
        $assigned = DailyPlanAssignment::with('plan')->where('user_id', $userId)->where('schedule_status', 'active')->whereHas('plan', fn ($q) => $q->where('team_id', $teamId)->where('status', 'published'))->get();
        $next = $assigned->filter(fn ($a) => ($a->scheduled_date ?? $a->plan->date) >= $end && ($a->scheduled_date ?? $a->plan->date) < $end->copy()->addWeek());
        $fitness = PlayerFitness::where('user_id', $userId)->where('fitness_date', '>=', $start->toDateString())->where('fitness_date', '<', $end->toDateString())->latest('fitness_date')->first();
        $snapshot = app(PlayerIntelligenceService::class)->build($teamId, $userId, min(365, max(14, $previous->diffInDays(now()) + 1)));
        $days = collect($snapshot['daily_velocity_averages'] ?? []);
        $current = $days->filter(fn ($r) => ($r['date'] ?? '') >= $start->toDateString() && ($r['date'] ?? '') < $end->toDateString());
        $prior = $days->filter(fn ($r) => ($r['date'] ?? '') >= $previous->toDateString() && ($r['date'] ?? '') < $start->toDateString());
        // Only explicitly tagged fastballs qualify; general pitching measurements are not relabeled.
        $fastballs = \App\Models\BullpenPracticeResult::with('practice:id,started')->where('pitcher_id', $userId)->where('team_id', $teamId)->where('type_throw', 'FB')->where('miles_per_hour', '>', 0)
            ->where(fn ($q) => $q->whereHas('practice', fn ($p) => $p->where('started', '>=', $previous)->where('started', '<', $end))->orWhere(fn ($q) => $q->where('created_at', '>=', $previous)->where('created_at', '<', $end)))->get();
        $fbCurrent = $fastballs->filter(fn ($r) => Carbon::parse($r->practice?->started ?? $r->created_at) >= $start && Carbon::parse($r->practice?->started ?? $r->created_at) < $end);
        $fbPrevious = $fastballs->filter(fn ($r) => Carbon::parse($r->practice?->started ?? $r->created_at) >= $previous && Carbon::parse($r->practice?->started ?? $r->created_at) < $start);
        $hasMeasurements = $current->contains(fn ($r) => ($r['hitting_sample_count'] ?? 0) > 0 || ($r['pitching_sample_count'] ?? 0) > 0);
        $newUpcoming = $next->contains(fn ($a) => $a->created_at >= $start && $a->created_at < $end);
        if ( ! $sessions && ! $submitted && ! $assessments && ! $fitness && ! $hasMeasurements && $fbCurrent->isEmpty() && ! $newUpcoming) {
            return [];
        }
        $lines = [(Team::find($teamId)?->name ?? 'Your team').' · '.$start->toDateString().' through '.$end->copy()->subDay()->toDateString(),
            'Training sessions completed: '.$sessions,'Workouts submitted: '.$submitted.' (submission does not necessarily mean every drill was completed).'];
        foreach(['pitching' => 'Pitching velocity','hitting' => 'Exit velocity'] as $key => $label) {
            $average = function ($rows) use ($key) {
                $rows = $rows->filter(fn ($r) => is_numeric($r['average_'.$key.'_velocity'] ?? null) && ($r[$key.'_sample_count'] ?? 0) > 0);
                $n = $rows->sum($key.'_sample_count');
                return $n ? [$rows->sum(fn ($r) => $r['average_'.$key.'_velocity'] * $r[$key.'_sample_count']) / $n,$n] : null;
            };
            $a = $average($current);
            $b = $average($prior);
            if ($a) {
                $lines[] = $label.': '.round($a[0], 1).' mph across '.$a[1].' readings'.($b ? ' (previous week '.round($b[0], 1).' mph; change '.sprintf('%+.1f', $a[0] - $b[0]).' mph).' : '. No previous-week comparison available.');
            }
        }
        if($fbCurrent->isNotEmpty()) {
            $lines[] = 'Fastball velocity (tagged bullpen pitches): '.round($fbCurrent->avg('miles_per_hour'), 1).' mph across '.$fbCurrent->count().' readings'.($fbPrevious->isNotEmpty() ? ' (previous week '.round($fbPrevious->avg('miles_per_hour'), 1).' mph; change '.sprintf('%+.1f', $fbCurrent->avg('miles_per_hour') - $fbPrevious->avg('miles_per_hour')).' mph).' : '. No previous-week comparison available.');
        }
        if ($fitness) {
            $priorFitness = PlayerFitness::where('user_id', $userId)->where('fitness_date', '<', $start->toDateString())->latest('fitness_date')->first();
            foreach(['bench_press' => ['Bench press','lb'],'front_squat' => ['Front squat','lb'],'back_squat' => ['Back squat','lb'],'dead_lift' => ['Deadlift','lb'],'vertical_jump' => ['Vertical jump','in'],'broad_jump' => ['Broad jump','in'],'sprint_10yd' => ['10-yard sprint','sec']] as $key => [$label,$unit]) {
                if ( ! is_numeric($fitness->$key)) {
                    continue;
                }
                $value = $fitness->$key;
                $lines[] = $label.': '.$value.' '.$unit.' recorded '.$fitness->fitness_date->format('Y-m-d').($priorFitness && is_numeric($priorFitness->$key) ? ' (previous recorded test '.$priorFitness->$key.' '.$unit.' on '.$priorFitness->fitness_date->format('Y-m-d').').' : '.');
            }
        }
        if ($assessments) {
            $lines[] = 'Assessment updates: '.$assessments.'. Open your dashboard for the authenticated reports.';
        }
        $goals = $assigned->filter(fn ($a) => ($a->scheduled_date ?? $a->plan->date) >= $start && ($a->scheduled_date ?? $a->plan->date) < $end->copy()->addWeek())->map(fn ($a) => $a->plan->primary_goal)->filter()->unique();
        foreach($goals->take(5) as $goal) {
            $lines[] = 'Development goal: '.$goal;
        }
        foreach($next->take(10) as $a) {
            $lines[] = 'Upcoming: '.$a->plan->name.' · '.($a->scheduled_date ?? $a->plan->date)->format('Y-m-d');
        }
        if($next->count() > 10) {
            $lines[] = 'Open FMTRX for all '.$next->count().' upcoming workouts.';
        }
        $metrics = collect(data_get($snapshot, 'benchmark_profile.metrics', []))->filter(fn ($m) => is_numeric($m['raw_value'] ?? null));
        if($metrics->isNotEmpty()) {
            $lines[] = 'Your development dashboard has '.$metrics->count().' available benchmark measurements. Open it for sources and context; no missing scores or projections are estimated in this email.';
        }
        return $lines;
    }
}
