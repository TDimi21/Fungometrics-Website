<?php
namespace Tests\Unit\LongToss;
use App\Services\LongToss\GuidanceEngine;
use PHPUnit\Framework\TestCase;
class GuidanceEngineTest extends TestCase {
    private function plan(array $extra=[]): array { return array_merge(['min_age'=>13,'max_age'=>30,'day_type'=>'moderate','scheduled_date'=>'2026-10-28','distance_cap_ft'=>300,'start_ft'=>45,'step_ft'=>45,'warmup_throws'=>5,'throws_per_step'=>3,'finish_throws'=>4,'rest_seconds'=>30,'max_throws'=>40],$extra); }
    public function test_velocity_estimates_interpolate_without_extrapolation(): void {
        $e=new GuidanceEngine; $this->assertSame(235.0,$e->estimate(75)); $this->assertSame(282.5,$e->estimate(82.5));
        $this->assertNull($e->estimate(40));$this->assertNull($e->estimate(110));
    }
    public function test_pain_and_very_low_readiness_override_all_plans(): void {
        $e=new GuidanceEngine;
        foreach([[10,true],[1,false],[2,false]] as [$rating,$pain]){
            $g=$e->build($rating,$pain,250,$this->plan(['day_type'=>'high']),'2026-10-28',18);
            $this->assertSame('rest',$g['status']);$this->assertSame(0,$g['target_ft']);$this->assertSame([],$g['stages']);
        }
    }
    public function test_readiness_is_not_permission_for_maximum_effort(): void {
        $e=new GuidanceEngine;
        $this->assertSame(200.0,$e->build(10,false,250,$this->plan(),'2026-10-28',18)['target_ft']);
        $this->assertSame(125.0,$e->build(10,false,250,$this->plan(['day_type'=>'recovery']),'2026-10-28',18)['target_ft']);
        $this->assertSame(200.0,$e->build(10,false,250,$this->plan(['day_type'=>'high']),'2026-10-29',18)['target_ft']);
        $this->assertSame(242.0,$e->build(10,false,250,$this->plan(['day_type'=>'high']),'2026-10-28',18)['target_ft']);
    }
    public function test_age_and_workload_require_an_applicable_coach_plan(): void {
        $e=new GuidanceEngine;
        foreach([null,12,40] as $age)$this->assertSame('needs_plan',$e->build(7,false,250,$this->plan(),'2026-10-28',$age)['status']);
        $this->assertSame('needs_plan',$e->build(7,false,250,$this->plan(['max_throws'=>5]),'2026-10-28',18)['status']);
        $g=$e->build(7,false,250,$this->plan(),'2026-10-28',18);
        $this->assertSame(200.0,$g['target_ft']); $this->assertLessThanOrEqual(40,array_sum(array_column($g['stages'],'throws')));
        $this->assertSame('Warm-Up',$g['stages'][0]['phase']);$this->assertSame('Finish',end($g['stages'])['phase']);
    }
    public function test_recovery_is_not_an_outlier_and_fatigue_is_separate(): void {
        $e=new GuidanceEngine;
        $this->assertFalse($e->review(120,235,7,7,false,'Light recovery')['flagged']);
        $this->assertTrue($e->review(310,235,7,7,false,'Moderate extension')['flagged']);
        $r=$e->review(120,235,7,3,false,'Moderate extension'); $this->assertFalse($r['flagged']);$this->assertTrue($r['fatigue_attention']);
    }
    public function test_out_and_back_sets_start_and_finish_at_thirty_and_allocate_exact_total(): void {
        $e=new GuidanceEngine;
        $plan=$this->plan(['version'=>2,'start_ft'=>30,'out_sets'=>5,'in_sets'=>3,'out_throws'=>4,'in_throws'=>2,'max_throws'=>null]);
        $g=$e->build(7,false,250,$plan,'2026-10-28',18);
        $this->assertSame([30,73,115,158,200,143,87,30],array_column($g['stages'],'distance_ft'));
        $this->assertSame([4,4,4,4,4,2,2,2],array_column($g['stages'],'throws'));
        $g=$e->build(7,false,250,array_merge($plan,['max_throws'=>29]),'2026-10-28',18);
        $this->assertSame([4,4,4,4,4,3,3,3],array_column($g['stages'],'throws'));
        $g=$e->build(7,false,250,array_merge($plan,['max_throws'=>3]),'2026-10-28',18);
        $this->assertSame('needs_plan',$g['status']);
    }

    public function test_version_three_limit_is_a_ceiling_not_a_throw_allocation(): void {
        $e=new GuidanceEngine;
        $plan=$this->plan(['version'=>3,'start_ft'=>30,'out_sets'=>5,'in_sets'=>3,'out_throws'=>4,'in_throws'=>2,'max_throws'=>29]);
        $g=$e->build(7,false,250,$plan,'2026-10-28',18);
        $this->assertSame([4,4,4,4,4,2,2,2],array_column($g['stages'],'throws'));
        foreach([null,26] as $limit) $this->assertSame('ready',$e->build(7,false,250,array_merge($plan,['max_throws'=>$limit]),'2026-10-28',18)['status']);
        $this->assertSame('needs_plan',$e->build(7,false,250,array_merge($plan,['max_throws'=>25]),'2026-10-28',18)['status']);
    }

}
