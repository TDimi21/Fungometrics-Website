<?php

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\{DailyPlanAssignment, DailyPlanProgress, Profile};
use App\Services\Planner\WorkoutFeedbackSummary;
use App\Services\Workouts\WorkoutTemplateService;
use Illuminate\Http\Request;

final class GetWorkoutAttention extends Controller
{
    public function __invoke(Request $request, WorkoutTemplateService $teams, WorkoutFeedbackSummary $feedback)
    {
        $v = $request->validate(['team_id'=>'required|string','date'=>'required|date_format:Y-m-d']);
        $teams->team($request->user(), $v['team_id']);
        $assignments = DailyPlanAssignment::with(['plan.assignments','plan.progress'])
            ->where('schedule_status','active')
            ->whereHas('plan', fn($q) => $q->where('team_id',$v['team_id'])->where('status','published'))
            ->where(fn($q) => $q->whereDate('scheduled_date',$v['date'])->orWhere(fn($q) => $q->whereNull('scheduled_date')->whereHas('plan',fn($p) => $p->whereDate('date',$v['date']))))
            ->get()->unique(fn($a) => $a->plan_id.':'.$a->user_id);
        $profiles = Profile::whereIn('user_id',$assignments->pluck('user_id'))->get()->keyBy('user_id');
        $rows = [];
        foreach ($assignments as $assignment) {
            $plan = $assignment->plan;
            $progress = $plan->progress->firstWhere('user_id',$assignment->user_id)
                ?? new DailyPlanProgress(['plan_id'=>$plan->id,'user_id'=>$assignment->user_id]);
            $progress->setRelation('plan',$plan);
            $summary = $feedback->build($plan,$progress);
            $reasons = $summary['attention_reasons'];
            $categories = [];
            // Reported concerns remain visible after review: review does not mean resolved.
            if ($reasons) $categories[] = 'reported';
            if ($summary['submission_status'] === 'submitted') {
                if ($summary['completed_drills'] < $summary['counted_drills']) $categories[] = 'unfinished';
                if ($summary['review_status'] !== 'reviewed') { $categories[] = 'review'; $reasons[] = 'Awaiting coach review'; }
            }
            if ($summary['submission_status'] !== 'not_started') {
                foreach ($summary['checks'] as $key=>$check) {
                    if ($key === 'reflection' && $summary['submission_status'] !== 'submitted') continue;
                    if ($check['status'] !== 'received') { $categories[] = 'check_in'; $reasons[] = ucfirst($key).' '.$check['status']; }
                }
            } elseif ($v['date'] <= now()->toDateString()) {
                $categories[] = 'not_started'; $reasons[] = 'Scheduled workout not started';
            }
            if (!$reasons) continue;
            // Classify wellness independently from completion/check-in reminders.
            $wellness = array_filter($summary['attention_reasons'], fn($reason) => !in_array($reason,['Submitted with unfinished drills','Readiness missing','Readiness partial','Reflection missing','Reflection partial'],true));
            $categories = array_values(array_unique(array_filter($categories,fn($category) => $category !== 'reported' || count($wellness))));
            $profile = $profiles->get($assignment->user_id);
            $rows[] = [
                'plan'=>array_merge($plan->attributesToArray(), ['buckets'=>$plan->bucketsFor((string)$assignment->user_id), 'date'=>$v['date']]), 'player'=>['id'=>(string)$assignment->user_id,'first_name'=>$profile->first_name ?? '', 'last_name'=>$profile->last_name ?? '', 'photo'=>$profile->picture ?? null],
                'progress'=>$progress->exists ? $progress : null,
                'scheduled_date'=>$v['date'], 'summary'=>$summary,
                'reasons'=>array_values(array_unique($reasons)), 'categories'=>$categories,
                'priority'=>count($wellness) ? 0 : (in_array('unfinished',$categories,true) ? 1 : 2),
            ];
        }
        return response()->json(['status'=>'success','data'=>collect($rows)->sortBy('priority')->values()]);
    }
}
