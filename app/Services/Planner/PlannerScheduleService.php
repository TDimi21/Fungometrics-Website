<?php

declare(strict_types=1);
namespace App\Services\Planner;
use App\Models\{DailyPlan,DailyPlanAssignment,DailyPlanProgress};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

final class PlannerScheduleService
{
    public function adjust(DailyPlan $plan, string $playerId, string $date, string $behavior, string $actor, string $requestId): array
    {
        return DB::transaction(function()use($plan,$playerId,$date,$behavior,$actor,$requestId){
            \App\Models\User::whereKey($playerId)->lockForUpdate()->firstOrFail();
            $assignment=DailyPlanAssignment::where('plan_id',$plan->id)->where('user_id',$playerId)->where('schedule_status','active')->lockForUpdate()->firstOrFail();
            foreach($assignment->schedule_adjustments??[] as $change)if(($change['request_id']??'')===$requestId)return $change;
            abort_if(DailyPlanProgress::where('plan_id',$plan->id)->where('user_id',$playerId)->whereNotNull('completed_at')->exists(),409,'Completed workouts cannot be rescheduled.');
            $original=($assignment->scheduled_date??$plan->date)->format('Y-m-d');
            abort_if($date<=$original,422,'Choose a later training date.');
            $source=$plan->buckets[0]['program_source']['id']??null;
            $others=DailyPlanAssignment::with('plan')->where('user_id',$playerId)->where('schedule_status','active')->whereHas('plan',fn($q)=>$q->where('team_id',$plan->team_id)->where('status','published'))->lockForUpdate()->get();
            $shift=Carbon::parse($original)->diffInDays(Carbon::parse($date));
            $change=['request_id'=>$requestId,'behavior'=>$behavior,'from'=>$original,'to'=>$date,'actor'=>$actor,'at'=>now()->toIso8601String(),'changes'=>[]];
            foreach($others as $row){
                $current=($row->scheduled_date??$row->plan->date)->format('Y-m-d');
                if(DailyPlanProgress::where('plan_id',$row->plan_id)->where('user_id',$playerId)->whereNotNull('completed_at')->exists())continue;
                $sameProgram=$source && ($row->plan->buckets[0]['program_source']['id']??null)===$source;
                if($row->id===$assignment->id || ($behavior==='shift' && $sameProgram && $current>=$original)) {
                    $next=$row->id===$assignment->id ? $date : Carbon::parse($current)->addDays($shift)->toDateString();
                    $change['changes'][]=['plan_id'=>$row->plan_id,'from'=>$current,'to'=>$next];
                    $row->update(['scheduled_date'=>$next,'schedule_adjustments'=>array_merge($row->schedule_adjustments??[],[['request_id'=>$requestId,'behavior'=>$behavior,'from'=>$current,'to'=>$next,'actor'=>$actor,'at'=>now()->toIso8601String()]])]);
                } elseif($behavior==='replace' && $sameProgram && $current===$date) {
                    $row->update(['schedule_status'=>'skipped','schedule_adjustments'=>array_merge($row->schedule_adjustments??[],[['request_id'=>$requestId,'behavior'=>'replaced','actor'=>$actor,'at'=>now()->toIso8601String()]])]);
                }
            }
            foreach($change['changes'] as $moved){
                $movedPlan=DailyPlan::findOrFail($moved['plan_id']);
                $conflicts=app(PlannerConflictService::class)->check($plan->team_id,$playerId,$moved['to'],$movedPlan->buckets,[$movedPlan->id]);
                if($conflicts)throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message'=>'SESSION CONFLICT — choose another date or ask your coach to resolve it.','conflicts'=>$conflicts],409));
            }
            $program=$source?\App\Models\WorkoutProgram::find($source):null;
            if($program){$end=Carbon::parse($program->start_date)->addDays($program->weeks*7-1)->toDateString();$change['extends_program']=collect($change['changes'])->contains(fn($c)=>$c['to']>$end);$change['original_program_end']=$end;}
            $assignment->refresh();$history=$assignment->schedule_adjustments??[];$history=array_values(array_filter($history,fn($c)=>($c['request_id']??'')!==$requestId));$history[]=$change;$assignment->update(['schedule_adjustments'=>$history]);
            return $change;
        });
    }
}
