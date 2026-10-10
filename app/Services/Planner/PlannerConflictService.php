<?php

declare(strict_types=1);
namespace App\Services\Planner;
use App\Models\{DailyPlan,DailyPlanAssignment};

final class PlannerConflictService
{
    public function types(array $buckets): array
    {
        $types=[];
        foreach(app(LinkedSessionRegistry::class)->buckets($buckets) as $bucket) {
            if(str_starts_with($bucket['type']??'','strength'))$types[]='strength';
            foreach($bucket['items']??[] as $item) if($item['linked_session'])$types[]=$item['linked_session']['type'];
        }
        return array_values(array_unique($types));
    }
    public function check(string $teamId, string $playerId, string $date, array $buckets, array $exclude=[]): array
    {
        $types=$this->types($buckets);$conflicts=[];
        $assignments=DailyPlanAssignment::with('plan')->where('user_id',$playerId)->where('schedule_status','active')->whereHas('plan',fn($q)=>$q->where('team_id',$teamId)->where('status','published')->whereNotIn('id',$exclude))->get();
        foreach($assignments as $assignment) {
            if(($assignment->scheduled_date??$assignment->plan->date)?->format('Y-m-d')!==$date)continue;
            $overlap=array_values(array_intersect($types,$this->types($assignment->plan->bucketsFor($playerId))));
            if($overlap)$conflicts[]=['player_id'=>$playerId,'date'=>$date,'plan_id'=>$assignment->plan_id,'name'=>$assignment->plan->name,'version'=>$assignment->plan->version,'assignment_version'=>$assignment->prescription_override['version']??0,'session_types'=>$overlap,'source'=>$assignment->plan->buckets[0]['program_source']??null,'actions'=>['merge','keep_existing','replace','move']];
        }
        return $conflicts;
    }
}
