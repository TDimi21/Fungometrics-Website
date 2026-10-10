<?php

declare(strict_types=1);
namespace App\Services\Planner;

final class PlannerWellnessService
{
    public function readiness(array $entry): array
    {
        $n = fn($key) => isset($entry[$key]) && is_numeric($entry[$key]) ? (float)$entry[$key] : null;
        $clamp = fn($v) => max(0, min(100, $v));
        $five = fn($key, $inverse=false) => $n($key) === null ? null : ($inverse ? 100 - $clamp(($n($key)-1)*25) : $clamp(($n($key)-1)*25));
        $ten = fn($key) => $n($key) === null ? null : 100-$clamp($n($key)*10);
        $avg = function(array $values) { $values=array_filter($values, fn($v)=>$v !== null);return $values ? array_sum($values)/count($values) : null; };
        $parts = [[$avg([$n('sleep_hours') === null ? null : $clamp(100-abs($n('sleep_hours')-8)*18),$five('sleep_quality')]),.25],[$five('energy'),.2],[$avg([$five('overall_soreness',true),$ten('lower_body_soreness')]),.2],[$avg([$ten('arm_soreness'),$ten('shoulder_soreness'),$ten('elbow_soreness')]),.15],[$five('stress',true),.1],[$five('motivation'),.1]];
        $sum=0;$weight=0;
        foreach($parts as [$v,$w]) if($v!==null){$sum+=$v*$w;$weight+=$w;}
        $score=$weight ? (int)round($sum/$weight) : null;
        $flags=[];
        foreach(['arm_soreness'=>3,'elbow_soreness'=>2,'shoulder_soreness'=>3] as $key=>$threshold) if($n($key)>$threshold)$flags[]=str_replace('_',' ',$key).' above '.$threshold;
        if($score!==null && $score<60)$flags[]='Readiness below 60';
        if(($entry['pain_flag']??false)===true || ($entry['pain_flag']??'')==='yes')$flags[]='Pain reported';
        return ['score'=>$score,'alerts'=>$flags,'needs_attention'=>(bool)$flags];
    }
    public function post(array $entry): array
    {
        $flags=[];
        foreach(['arm_fatigue'=>7,'arm_soreness'=>7,'overall_fatigue'=>8] as $key=>$threshold) if(isset($entry[$key]) && $entry[$key]>=$threshold)$flags[]=str_replace('_',' ',$key).' at or above '.$threshold;
        if(($entry['pain']??false)===true)$flags[]='Pain reported';
        return ['answers'=>$entry,'alerts'=>$flags,'needs_attention'=>(bool)$flags];
    }
}
