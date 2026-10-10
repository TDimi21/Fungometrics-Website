<?php

declare(strict_types=1);
namespace App\Services\Planner;
use App\Models\{DailyPlan,PlayerFitness};

final class PlannerStrengthMetricsService
{
    public function record(DailyPlan $plan,string $playerId,array $item,array $actual,array $prior): array
    {
        $sets=collect($actual['sets']??[])->filter(fn($s)=>!empty($s['done']) && isset($s['weight'],$s['reps']) && $s['reps']>0)->values();
        if($sets->isEmpty() && empty($prior['strength_fitness_id']))return $actual;
        $top=$sets->sortByDesc('weight')->first();
        $actual['strength_summary']=['volume_lb'=>$sets->sum(fn($s)=>$s['weight']*$s['reps']),'top_set'=>$top??null,'completed_sets'=>$sets->count()];
        // Reuse the existing strength history, preserving exercise sets as observed results.
        $record=!empty($prior['strength_fitness_id']) ? PlayerFitness::where('user_id',$playerId)->findOrFail($prior['strength_fitness_id']) : new PlayerFitness(['user_id'=>$playerId,'fitness_date'=>now()->toDateString()]);
        if($record->exists)abort_unless(($record->strength_test_metadata['plan_id']??null)===$plan->id && ($record->strength_test_metadata['item_id']??null)===$item['id'],409,'Strength result belongs to another workout.');
        $record->strength_test_metadata=['source'=>'daily_plan','plan_id'=>$plan->id,'item_id'=>$item['id'],'exercise'=>$item['name'],'sets'=>$sets->all(),'summary'=>$actual['strength_summary']];
        $metric=$item['metadata']['strength_metric']??null;
        if(in_array($metric,['bench_press','back_squat','front_squat','dead_lift','trap_bar_deadlift','power_clean'],true)) {
            // Only an observed single-repetition result updates a max field. No invented 1RM.
            $singles=$sets->where('reps',1);
            $record->{$metric}=$singles->isNotEmpty()?$singles->max('weight'):null;
        }
        $record->save();$actual['strength_fitness_id']=$record->id;
        return $actual;
    }
}
