<?php
namespace Tests\Feature\Api\Player;
use App\Models\{User,Team,CoachTeam,PlayerTeam,PlayerDailyReadiness};
use App\Services\Planner\DailyReadinessSummary;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class DailyReadinessTest extends TestCase {
    private function payload(): array {return ['date'=>now('America/New_York')->toDateString(),'timezone'=>'America/New_York','answers'=>array_fill_keys(DailyReadinessSummary::RATINGS,8)+['sleep_hours'=>7.5,'pain_flag'=>false,'notes'=>'Ready today']];}
    private function query(): string {return '?'.http_build_query(array_intersect_key($this->payload(),array_flip(['date','timezone'])));}
    public function test_daily_save_is_shared_between_clients_and_idempotent_and_private(): void {
        $p=User::factory()->create(['type'=>'player']);$other=User::factory()->create(['type'=>'player']);
        Sanctum::actingAs($p,['player']);
        $this->getJson('/api/player/readiness'.$this->query())->assertOk()->assertJsonPath('data.today',null);
        $id=$this->putJson('/api/player/readiness',$this->payload())->assertOk()->assertJsonPath('data.average',8)->json('data.id');
        $payload=$this->payload();$payload['answers']['arm_comfort']=2;$payload['answers']['pain_flag']=true;
        $this->putJson('/api/player/readiness',$this->payload())->assertOk()->assertJsonPath('data.id',$id);
        $this->putJson('/api/player/readiness',$payload)->assertStatus(409)->assertJsonPath('data.id',$id)->assertJsonPath('data.needs_attention',false);
        $this->assertSame(1,PlayerDailyReadiness::where('user_id',$p->id)->count());
        $this->getJson('/api/player/readiness'.$this->query())->assertOk()->assertJsonPath('data.today.answers.arm_comfort',8)->assertJsonCount(1,'data.history');
        Sanctum::actingAs($other,['player']);
        $this->getJson('/api/players/'.$p->id.'/readiness'.$this->query())->assertNotFound();
        $this->getJson('/api/player/readiness'.$this->query())->assertOk()->assertJsonPath('data.today',null);
    }
    public function test_only_current_team_coaches_can_read_and_cannot_write_for_player(): void {
        $p=User::factory()->create(['type'=>'player']);$coach=User::factory()->create(['type'=>'coach']);$stranger=User::factory()->create(['type'=>'coach']);$team=Team::factory()->create();
        CoachTeam::factory()->create(['coach_id'=>$coach->id,'team_id'=>$team->id]);
        PlayerTeam::factory()->create(['user_id'=>$p->id,'team_id'=>$team->id,'actual'=>true]);
        Sanctum::actingAs($p,['player']);$this->putJson('/api/player/readiness',$this->payload())->assertOk();
        $this->getJson('/api/coach/teams/'.$team->id.'/readiness'.$this->query())->assertForbidden();
        Sanctum::actingAs($coach,['coach']);
        $this->getJson('/api/players/'.$p->id.'/readiness'.$this->query())->assertOk()->assertJsonPath('data.today.answers.notes','Ready today');
        $this->getJson('/api/coach/teams/'.$team->id.'/readiness'.$this->query())->assertOk()->assertJsonPath('data.players.0.user_id',$p->id);
        $this->putJson('/api/player/readiness',$this->payload()+['user_id'=>$p->id])->assertForbidden();
        Sanctum::actingAs($stranger,['coach']);$this->getJson('/api/coach/teams/'.$team->id.'/readiness'.$this->query())->assertNotFound();
        Sanctum::actingAs($coach,['coach']);PlayerTeam::where('user_id',$p->id)->update(['actual'=>false]);
        $this->getJson('/api/players/'.$p->id.'/readiness'.$this->query())->assertNotFound();
    }
    public function test_requires_all_ratings_in_range_and_current_local_date(): void {
        Sanctum::actingAs(User::factory()->create(['type'=>'player']),['player']);
        $p=$this->payload();$p['answers']['energy']=0;$this->putJson('/api/player/readiness',$p)->assertUnprocessable();
        $p['answers']['energy']=11;$this->putJson('/api/player/readiness',$p)->assertUnprocessable();
        unset($p['answers']['energy']);$this->putJson('/api/player/readiness',$p)->assertUnprocessable();
        $p=$this->payload();$p['date']='2000-01-01';$this->putJson('/api/player/readiness',$p)->assertUnprocessable();
        $p=$this->payload();$p['timezone']='invalid';$this->putJson('/api/player/readiness',$p)->assertUnprocessable();
        $this->assertSame(0,PlayerDailyReadiness::count());
    }

    public function test_daily_readiness_is_used_in_workout_feedback_without_rewriting_legacy_answers(): void {
        $player=User::factory()->create(['type'=>'player']);
        Sanctum::actingAs($player,['player']);
        $payload=$this->payload();$payload['answers']['pain_flag']=true;
        $this->putJson('/api/player/readiness',$payload)->assertOk();
        $plan=new \App\Models\DailyPlan(['id'=>(string)\Illuminate\Support\Str::uuid(),'date'=>$payload['date'],'buckets'=>[]]);
        $progress=new \App\Models\DailyPlanProgress(['user_id'=>$player->id,'readiness'=>['energy'=>2]]);
        $summary=app(\App\Services\Planner\WorkoutFeedbackSummary::class)->build($plan,$progress);
        $this->assertSame('received',$summary['checks']['readiness']['status']);
        $this->assertSame(2,$summary['checks']['readiness']['scale_version']);
        $this->assertContains('Pain reported',$summary['attention_reasons']);
        $this->assertSame(['energy'=>2],$progress->readiness);
    }

    public function test_local_day_is_used_across_the_utc_midnight_boundary(): void {
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-10-26T00:30:00Z'));
        try {
            Sanctum::actingAs(User::factory()->create(['type'=>'player']),['player']);
            $payload=$this->payload();
            $this->assertSame('2026-10-25',$payload['date']);
            $this->putJson('/api/player/readiness',$payload)->assertOk();
            $payload['date']='2026-10-26';
            $this->putJson('/api/player/readiness',$payload)->assertUnprocessable();
        } finally { \Illuminate\Support\Carbon::setTestNow(); }
    }
    public function test_a_new_day_allows_a_new_entry_without_changing_yesterday(): void {
        Sanctum::actingAs(User::factory()->create(['type'=>'player']),['player']);
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-10-25T16:00:00Z'));
        try {
            $first=$this->putJson('/api/player/readiness',$this->payload())->assertOk()->json('data.id');
            \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-10-26T16:00:00Z'));
            $this->getJson('/api/player/readiness'.$this->query())->assertOk()->assertJsonPath('data.today',null);
            $second=$this->putJson('/api/player/readiness',$this->payload())->assertOk()->json('data.id');
            $this->assertNotSame($first,$second);
            $this->assertSame(2,PlayerDailyReadiness::count());
        } finally { \Illuminate\Support\Carbon::setTestNow(); }
    }
}
