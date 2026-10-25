<?php
namespace App\Services\Planner;
use App\Models\PlayerDailyReadiness;
final class DailyReadinessSummary {
    public const RATINGS = ['sleep_quality','energy','body_comfort','arm_comfort','shoulder_comfort','elbow_comfort','lower_body_comfort','calmness','motivation'];
    public static function forPlan(\App\Models\DailyPlan $plan, string $playerId): ?array {
        $scheduled = \App\Models\DailyPlanAssignment::where('plan_id',$plan->id)->where('user_id',$playerId)->value('scheduled_date') ?: $plan->date;
        $row = $scheduled ? PlayerDailyReadiness::where('user_id',$playerId)->where('date',\Illuminate\Support\Carbon::parse($scheduled)->toDateString())->first() : null;
        return $row ? self::present($row) : null;
    }
    public static function present(PlayerDailyReadiness $entry): array {
        $answers=$entry->answers;
        $ratings=array_intersect_key($answers,array_flip(self::RATINGS));
        $flags=[];
        foreach ($ratings as $key=>$value) if ($value <= 3) $flags[]=str_replace('_',' ',$key).' rated '.$value.'/10';
        if ($answers['pain_flag'] ?? false) $flags[]='Pain reported';
        return ['id'=>$entry->id,'user_id'=>$entry->user_id,'date'=>$entry->date,'timezone'=>$entry->timezone,'scale_version'=>2,
            'answers'=>$answers,'average'=>round(array_sum($ratings)/max(1,count($ratings)),1),
            'alerts'=>$flags,'needs_attention'=>(bool)$flags,'updated_at'=>$entry->updated_at?->toIso8601String()];
    }
}
