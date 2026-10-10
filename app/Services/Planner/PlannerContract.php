<?php

declare(strict_types=1);
namespace App\Services\Planner;
use App\Models\{DailyPlan,DailyPlanAssignment,DailyPlanProgress};
use Illuminate\Support\Facades\DB;

final class PlannerContract
{
    public const VERSION = '2.0';
    public function plan(DailyPlan $plan, ?string $playerId=null): array
    {
        $plan->loadMissing('assignments');
        $assignment = $playerId ? $plan->assignments->firstWhere('user_id',$playerId) : null;
        if($playerId)$plan->loadMissing('progress');
        $progress = $playerId ? $plan->progress->firstWhere('user_id',$playerId) : null;
        $buckets=app(LinkedSessionRegistry::class)->buckets($playerId?$plan->bucketsFor($playerId):($plan->buckets??[]));
        $wellness=app(PlannerWellnessService::class);
        $links=$playerId ? DB::table('workout_session_links')->where('plan_id',$plan->id)->where('user_id',$playerId)->get()->all() : [];
        $data=$plan->toArray();
        // The canonical session owns completion; both clients receive the same observed state.
        $actuals=$progress?->items??[];
        $sessionIds=array_map(fn($l)=>$l->practice_id,$links);
        $sessions=\App\Models\Practice::whereIn('id',$sessionIds)->get()->keyBy('id');
        foreach($links as $link) if(isset($sessions[$link->practice_id])) {
            $session=$sessions[$link->practice_id];
            $actuals[$link->item_id]=array_merge($actuals[$link->item_id]??[],['session_id'=>$session->id,'session_status'=>$session->is_completed?'completed':'in_progress','done'=>(bool)$session->is_completed]);
        }
        if($progress)$progress->items=$actuals;
        if($playerId) { $data['assigned_player_ids']=[$playerId]; unset($data['assignments']); }
        $updates=null;
        if($playerId)try{$updates=app(DailyPlanPlayerUpdateService::class)->buildPlayerPlanUpdateStatus($plan->id,$playerId);}catch(\Throwable $e){report($e);}
        return array_merge($data,[
            'settings'=>$plan->settings??[],'planner_contract_version'=>self::VERSION,'version'=>(int)$plan->version,'owner'=>$plan->created_by,'team'=>$plan->team_id,
            'scheduled_date'=>($assignment?->scheduled_date ?? $plan->date)?->format('Y-m-d'),
            'source'=>$buckets[0]['program_source'] ?? $buckets[0]['template_source'] ?? ['type'=>'quick_workout'],
            'buckets'=>$buckets,'blocks'=>$buckets,'assignment'=>$assignment,'progress'=>$progress??($actuals?['version'=>0,'items'=>$actuals]:null),
            'completion'=>['completed_at'=>$progress?->completed_at,'completed_items'=>collect($actuals)->filter(fn($i)=>!empty($i['done']))->count()],
            'actual_results'=>$actuals, 'readiness'=>$wellness->readiness($progress?->readiness??[]),
            'post_training'=>$wellness->post($progress?->post_training??[]),'alert_review'=>$progress?->alert_review,
            'schedule_adjustments'=>$assignment?->schedule_adjustments??[],'session_links'=>$links,
            'update_status'=>$updates,
        ]);
    }
}
