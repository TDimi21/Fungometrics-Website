<?php
namespace App\Services\LongToss;

final class GuidanceEngine
{
    // Product estimates supplied for FMTRX, not a validated velocity/stress model.
    public const VELOCITY = [55=>125,60=>150,65=>175,70=>200,75=>235,80=>265,85=>300,90=>335,95=>375,100=>415];
    public function estimate(?float $mph): ?float {
        if ($mph === null || $mph < 55 || $mph > 100) return null;
        $lo = (int) (floor($mph / 5) * 5); $hi = min(100, $lo + 5);
        return round(self::VELOCITY[$lo] + ($mph-$lo)/5*(self::VELOCITY[$hi]-self::VELOCITY[$lo]), 1);
    }
    public function build(int $readiness, bool $pain, ?float $baseline, ?array $plan, string $date, ?int $age): array {
        $out = ['version'=>1,'status'=>'needs_plan','target_ft'=>null,'range_ft'=>null,'stages'=>[],
            'message'=>'Ask your coach to set a throwing workload for your age, history, and season phase before starting guided throws.'];
        if ($pain || $readiness <= 2) return array_merge($out, ['status'=>'rest','target_ft'=>0,
            'message'=>$pain ? 'Do not throw with arm pain. Stop and tell your coach or parent; seek assessment from a qualified healthcare professional.' : 'Rest today. Do not start a throwing session.']);
        if (!$plan || !$baseline) return array_merge($out, ['message'=>!$baseline ? 'No reliable distance baseline yet. Ask your coach to record a measured maximum; velocity estimates are only a starting reference.' : $out['message']]);
        if ($age === null || $age < $plan['min_age'] || $age > $plan['max_age']) return array_merge($out, ['message'=>'Your age is missing or outside this coach plan’s age range. Ask your coach to review the plan.']);
        $high = $readiness >= 8 && $plan['day_type'] === 'high' && ($plan['scheduled_date'] ?? null) === $date;
        // An unscheduled high-readiness day stays moderate. Recovery remains recovery.
        [$low,$upper,$fraction,$label] = $readiness <= 4 || $plan['day_type'] === 'recovery'
            ? [.5,.6,.5,'Light recovery'] : ($high ? [.97,1,.97,'Scheduled high readiness'] : [.75,.9,.8,'Moderate extension']);
        $ceiling = min(500, $plan['distance_cap_ft'], floor($baseline * $upper));
        $target = min($ceiling, floor($baseline * $fraction));
        if ($target < $plan['start_ft']) return array_merge($out, ['message'=>'Today’s distance ceiling is below this plan’s starting distance. Ask your coach to adjust the plan.']);
        if (($plan['version']??1) >= 2) {
            $outSets=(int)$plan['out_sets']; $inSets=(int)$plan['in_sets']; $sets=$outSets+$inSets;
            $limit=$plan['max_throws']??null;
            if ($limit!==null && $limit<$sets) return array_merge($out,['message'=>'The total throw limit must allow at least one throw per set. Reduce the sets or increase the limit.']);
            $steps=[];
            for($i=0;$i<$sets;$i++) {
                $outward=$i<$outSets;
                $distance=$outward ? 30+($target-30)*$i/($outSets-1) : $target-($target-30)*($i-$outSets+1)/$inSets;
                $throws=($plan['version']??2)===2 && $limit!==null ? intdiv((int)$limit,$sets)+($i<((int)$limit%$sets)?1:0) : (int)$plan[$outward?'out_throws':'in_throws'];
                $steps[]=['phase'=>$outward?'Going Out':'Coming In','set_number'=>$i+1,'direction_set'=>$outward?$i+1:$i-$outSets+1,'distance_ft'=>(int)round($distance),'throws'=>$throws];
            }
            if(($plan['version']??2)>=3 && $limit!==null && array_sum(array_column($steps,'throws'))>$limit) return array_merge($out,['message'=>'Planned throws exceed the total throw limit. Reduce sets or throws per set, or change the limit.']);
            if(array_sum(array_column($steps,'throws'))>120) return array_merge($out,['message'=>'This session exceeds 120 throws. Reduce sets or throws per set.']);
            $out['version']=$plan['version'];
        } else {
        $steps = [['phase'=>'Warm-Up','distance_ft'=>$plan['start_ft'],'throws'=>$plan['warmup_throws']]];
        for ($distance = min($target,$plan['start_ft']+$plan['step_ft']); $distance <= $target && $distance > $plan['start_ft']; $distance = min($target, $distance+$plan['step_ft'])) {
            $steps[] = ['phase'=>'Extension','distance_ft'=>$distance,'throws'=>$plan['throws_per_step']];
            if ($distance === $target) break;
        }
        $steps[] = ['phase'=>'Finish','distance_ft'=>$plan['start_ft'],'throws'=>$plan['finish_throws']];
        if (array_sum(array_column($steps,'throws')) > $plan['max_throws']) return array_merge($out, ['message'=>'This progression exceeds the coach’s throw limit. Ask your coach to adjust the plan.']);
        }
        foreach ($steps as &$step) { $step['rest_seconds']=$plan['rest_seconds']; $step['instruction']="Make {$step['throws']} controlled throws at {$step['distance_ft']} feet. Stop for pain, increasing fatigue, or loss of control."; }
        return array_merge($out, ['status'=>'ready','day_type'=>$label,'target_ft'=>$target,
            'range_ft'=>[min($target, floor($baseline*$low)), $ceiling], 'stages'=>$steps,
            'message'=>$high ? 'Follow your scheduled coach plan. A readiness score is not clearance for maximum effort.' : 'Controlled throws only. No pulldowns or automatic maximum effort.']);
    }
    public function review(float $distance, ?float $expected, int $before, int $after, bool $pain, string $dayType): array {
        $delta = $expected && $expected > 0 ? round(($distance-$expected)/$expected*100,1) : null;
        $outlier = $distance > 0 && $delta !== null && ($delta > 25 || ($dayType === 'Scheduled high readiness' && $delta < -25));
        $fatigue = $pain || ($after <= $before-3 && $distance > 0 && $expected && $distance < .8*$expected);
        return ['distance_ft'=>$distance,'expected_ft'=>$expected,'difference_percent'=>$delta,'flagged'=>$outlier,
            'fatigue_attention'=>(bool)$fatigue,'message'=>$fatigue ? 'Arm feel declined or pain was reported. Stop throwing and review recovery with your coach. This is not a diagnosis.' : ($outlier ? 'This distance differs from your reference. Confirm the distance and measurement method. Your result and PR remain recorded.' : 'Session recorded. Keep measuring consistently; do not chase a distance target.')];
    }
}
