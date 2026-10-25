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
            $done = (bool) $session->is_completed;
            if ($done && ($plan->settings['assignment_kind'] ?? null) === 'scored') {
                $relation = ['batting'=>'batting','bullpen'=>'bullpen','cage'=>'cage','weighted_ball'=>'weightBall','long_toss'=>'longToss','exit_velocity'=>'exitVelocity'][$link->type] ?? null;
                $done = $relation && $session->{$relation}()->exists();
            }
            $actuals[$link->item_id]=array_merge($actuals[$link->item_id]??[],['session_id'=>$session->id,'session_status'=>$session->is_completed?'completed':'in_progress','done'=>$done]);
        }
        $completedAt = $progress?->completed_at;
        $required = collect($buckets)->flatMap(fn($b) => $b['items'] ?? [])->filter(fn($i) => ($i['required'] ?? true) !== false);
        // Observe finalized scored assignments without writing or duplicating workout/session records.
        // Guided workouts keep their explicit submission and reflection requirements.
        if (!$completedAt && ($plan->settings['assignment_kind'] ?? null) === 'scored' && $required->isNotEmpty()) {
            $finished = [];
            $allFinished = $required->every(function ($item) use ($actuals, $links, $sessions, &$finished) {
                if (($item['execution_type'] ?? null) !== 'LAUNCH_SESSION') return false;
                $actual = $actuals[$item['id']] ?? [];
                $link = collect($links)->first(fn($l) => $l->item_id === $item['id'] && $l->practice_id === ($actual['session_id'] ?? null));
                $session = $link ? $sessions->get($link->practice_id) : null;
                if (!$session?->is_completed || empty($actual['done'])) return false;
                $finished[] = $session->finished ?: $session->updated_at;
                return true;
            });
            if ($allFinished) $completedAt = collect($finished)->max();
        }
        $progressData = $progress ? $progress->toArray() : ($actuals ? ['version'=>0] : null);
        if ($progressData !== null) {
            $progressData['items'] = $actuals;
            $progressData['completed_at'] = $completedAt;
        }
        if($playerId) { $data['assigned_player_ids']=[$playerId]; unset($data['assignments']); }
        $updates=null;
        if($playerId)try{$updates=app(DailyPlanPlayerUpdateService::class)->buildPlayerPlanUpdateStatus($plan->id,$playerId);}catch(\Throwable $e){report($e);}
        return array_merge($data,[
            'settings'=>$plan->settings??[],'planner_contract_version'=>self::VERSION,'version'=>(int)$plan->version,'owner'=>$plan->created_by,'team'=>$plan->team_id,
            'scheduled_date'=>($assignment?->scheduled_date ?? $plan->date)?->format('Y-m-d'),
            'source'=>$buckets[0]['program_source'] ?? $buckets[0]['template_source'] ?? ['type'=>'quick_workout'],
            'buckets'=>$buckets,'blocks'=>$buckets,'assignment'=>$assignment,'progress'=>$progressData,
            'completion'=>['completed_at'=>$completedAt,'completed_items'=>collect($actuals)->filter(fn($i)=>!empty($i['done']))->count()],
            'actual_results'=>$actuals, 'readiness'=>$wellness->readiness($progress?->readiness??[]),
            'post_training'=>$wellness->post($progress?->post_training??[]),'alert_review'=>$progress?->alert_review,
            'schedule_adjustments'=>$assignment?->schedule_adjustments??[],'session_links'=>$links,
            'update_status'=>$updates,
        ]);
    }
}
