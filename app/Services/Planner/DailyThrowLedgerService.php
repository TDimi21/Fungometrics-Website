<?php

declare(strict_types=1);
namespace App\Services\Planner;
use App\Models\{BullpenPracticeResult,LongTossPractice,WeightBallPractice,DailyPlanProgress};
use Illuminate\Support\Facades\DB;

final class DailyThrowLedgerService
{
    public function build(string $userId, string $date, ?string $teamId=null): array
    {
        $entries=[];
        $links=DB::table('workout_session_links')->where('user_id',$userId)->get()->groupBy('practice_id');
        foreach([[BullpenPracticeResult::class,'pitcher_id','bullpen_pitches'],[LongTossPractice::class,'user_id','long_toss_throws'],[WeightBallPractice::class,'user_id','weighted_ball_throws']] as [$model,$userColumn,$category]) {
            $rows=$model::where($userColumn,$userId)->when($teamId,fn($q)=>$q->where('team_id',$teamId))->where('created_at','>=',$date.' 00:00:00')->where('created_at','<',\Carbon\Carbon::parse($date)->addDay()->format('Y-m-d 00:00:00'))->get();
            foreach($rows as $row) $entries[]=['id'=>$category.':'.$row->id,'category'=>$category,'count'=>1,'source'=>'canonical','session_id'=>$row->practice_id,'weight'=>$row->weight,'intent'=>null,'timestamp'=>$row->getRawOriginal('created_at'),'workout_items'=>collect($links->get($row->practice_id,[]))->map(fn($l)=>['plan_id'=>$l->plan_id,'item_id'=>$l->item_id])->all()];
        }
        $progress=DailyPlanProgress::with('plan')->where('user_id',$userId)->where('updated_at','>=',$date.' 00:00:00')->whereHas('plan',fn($q)=>$q->when($teamId,fn($q)=>$q->where('team_id',$teamId)))->get();
        foreach($progress as $pr) {
            $prescribed=collect($pr->plan->bucketsFor($userId))->flatMap(fn($b)=>array_map(fn($i)=>$i+['program_source'=>$b['program_source']??null],$b['items']??[]))->keyBy('id');
            foreach($pr->items??[] as $id=>$actual) {
                // Canonical sessions always win over any manual count for the same item.
                if(!empty($actual['session_id']) || $links->flatten()->contains(fn($l)=>$l->plan_id===$pr->plan_id && $l->item_id===$id)) continue;
                foreach($actual['quick_throws']??[] as $index=>$throw) {
                    if(substr($throw['timestamp']??'',0,10)!==$date)continue;
                    $item=$prescribed[$id]??[];
                    $entries[]=['id'=>$pr->id.':'.$id.':'.$index,'category'=>$throw['category']??'warmup_throws','count'=>(int)$throw['count'],'source'=>'quick_log','drill'=>$item['name']??null,'ball_weight'=>$throw['ball_weight']??null,'intent'=>$throw['intent']??null,'timestamp'=>$throw['timestamp'],'workout_item_id'=>$id,'plan_id'=>$pr->plan_id,'program_id'=>$item['program_source']['id']??null];
                }
            }
        }
        $totals=array_fill_keys(['total_throws','warmup_throws','catch_play_throws','long_toss_throws','weighted_ball_throws','flat_ground_throws','bullpen_pitches','other_throws','high_intent_throws'],0);
        foreach($entries as $entry){$totals['total_throws']+=$entry['count'];$totals[$entry['category']]+=$entry['count'];if(($entry['intent']??0)>=90)$totals['high_intent_throws']+=$entry['count'];}
        return ['planner_contract_version'=>PlannerContract::VERSION,'date'=>$date,'player_id'=>$userId,'volume_only'=>true,'entries'=>$entries]+$totals;
    }
}
