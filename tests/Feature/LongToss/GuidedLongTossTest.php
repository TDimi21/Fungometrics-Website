<?php
namespace Tests\Feature\LongToss;
use App\Models\{User, Player, Team, Practice, PracticeLineUp, LongTossPractice, LongTossGuide};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class GuidedLongTossTest extends TestCase {
    private function setupGuide(): array {
        $coach=User::factory()->create(['type'=>'coach']);$player=User::factory()->create(['type'=>'player']);
        Player::factory()->create(['user_id'=>$player->id,'born_date'=>'2005-01-01']);
        $team=Team::factory()->create();$this->grantTeamAccess($coach,$team);$this->grantTeamAccess($player,$team);
        $practice=Practice::factory()->create(['type'=>'T','modes'=>'LT','user_id'=>$coach->id,'team_id'=>$team->id]);
        PracticeLineUp::factory()->create(['practice_id'=>$practice->id,'user_id'=>$player->id]);
        $url="/api/long-toss-guidance/$practice->id/$player->id";
        Sanctum::actingAs($coach,['coach']);
        $this->putJson($url.'/plan',['min_age'=>18,'max_age'=>25,'season_phase'=>'inseason','throwing_history'=>'Established throwing program',
            'day_type'=>'moderate','scheduled_date'=>now()->toDateString(),'measured_max_ft'=>250,'start_ft'=>45,'step_ft'=>45,'distance_cap_ft'=>250,
            'warmup_throws'=>2,'throws_per_step'=>2,'finish_throws'=>2,'rest_seconds'=>30,'max_throws'=>30])->assertOk();
        Sanctum::actingAs($player,['player']);
        return [$coach,$player,$practice,$url];
    }
    public function test_checkin_preview_is_sane_and_retries_do_not_reset_pain(): void {
        [$coach,$player,$practice,$url]=$this->setupGuide();
        $this->postJson($url.'/preview',['readiness'=>7,'pain'=>false,'timezone'=>'America/New_York'])->assertOk()->assertJsonPath('data.target_ft',200);
        $this->postJson($url.'/check-in',['readiness'=>7,'pain'=>true,'timezone'=>'America/New_York'])->assertOk()->assertJsonPath('data.status','stopped');
        $this->postJson($url.'/check-in',['readiness'=>10,'pain'=>false,'timezone'=>'UTC'])->assertOk()->assertJsonPath('data.status','stopped');
        $this->assertDatabaseCount('long_toss_guides',1);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\LongToss\GuidedThrowGate::class)->check($practice->id,$player->id);
    }
    public function test_checkpoints_use_saved_throws_and_finish_preserves_pr(): void {
        [$coach,$player,$practice,$url]=$this->setupGuide();
        $this->postJson($url.'/check-in',['readiness'=>7,'pain'=>false,'timezone'=>'UTC'])->assertOk();
        $this->postJson($url.'/checkpoint',['stage'=>0,'pain'=>false,'fatigue'=>false])->assertUnprocessable();
        foreach([45,45]as$distance)LongTossPractice::factory()->create(['practice_id'=>$practice->id,'user_id'=>$player->id,'team_id'=>$practice->team_id,'hop'=>0,'distance'=>$distance]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.guide.can_record',false);
        $this->postJson($url.'/checkpoint',['stage'=>0,'pain'=>false,'fatigue'=>false])->assertOk()->assertJsonPath('data.stage',1);
        $this->postJson($url.'/checkpoint',['stage'=>0,'pain'=>false,'fatigue'=>false])->assertOk()->assertJsonPath('data.stage',1);
        LongTossPractice::factory()->create(['practice_id'=>$practice->id,'user_id'=>$player->id,'team_id'=>$practice->team_id,'hop'=>0,'distance'=>310]);
        $this->postJson($url.'/finish',['readiness_after'=>6,'pain_after'=>false,'measurement'=>'tape','confirmed'=>true])->assertOk()->assertJsonPath('data.review.distance_ft',310);
        $this->assertEquals(310,LongTossPractice::where('practice_id',$practice->id)->max('distance'));
        $this->postJson($url.'/finish',['readiness_after'=>10,'pain_after'=>false,'measurement'=>'tape','confirmed'=>true])->assertOk()->assertJsonPath('data.readiness_after',6);
    }
    public function test_players_cannot_set_plans_or_read_other_players(): void {
        [$coach,$player,$practice,$url]=$this->setupGuide();
        $this->putJson($url.'/plan',[])->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['type'=>'player']),['player']);$this->getJson($url)->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['type'=>'coach']),['coach']);$this->getJson($url)->assertForbidden();
    }
    public function test_repeated_flags_notify_only_the_players_current_coach(): void {
        [$coach,$player,$practice,$url]=$this->setupGuide();
        for($i=0;$i<5;$i++) {
            $session=Practice::factory()->create(['type'=>'T','modes'=>'LT','user_id'=>$coach->id,'team_id'=>$practice->team_id]);
            LongTossGuide::create(['practice_id'=>$session->id,'player_id'=>$player->id,'team_id'=>$practice->team_id,
                'readiness'=>7,'pain'=>false,'snapshot'=>[],'status'=>'complete','finished_at'=>now()->subMinutes($i+1),
                'review'=>['flagged'=>$i<3,'fatigue_attention'=>false]]);
        }
        $this->getJson($url)->assertOk()->assertJsonPath('data.coach_review_needed',true);
        $alerts="/api/long-toss-guidance-team/$practice->team_id/alerts";
        Sanctum::actingAs($coach,['coach']);
        $this->getJson($alerts)->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.flagged_sessions',3);
        Sanctum::actingAs(User::factory()->create(['type'=>'coach']),['coach']);
        $this->getJson($alerts)->assertForbidden();
    }
    public function test_fatigue_stop_survives_post_session_checkin(): void {
        [$coach,$player,$practice,$url]=$this->setupGuide();
        $this->postJson($url.'/check-in',['readiness'=>7,'pain'=>false,'timezone'=>'UTC'])->assertOk();
        $this->postJson($url.'/checkpoint',['stage'=>0,'pain'=>false,'fatigue'=>true])->assertOk()->assertJsonPath('data.can_record',false);
        $this->postJson($url.'/finish',['readiness_after'=>7,'pain_after'=>false,'measurement'=>'estimate','confirmed'=>false])
            ->assertOk()->assertJsonPath('data.review.fatigue_attention',true)->assertJsonPath('data.status','stopped');
    }

    private function sessionSetup($player): array {
        return ['player_id'=>$player->id,'readiness'=>7,'pain'=>false,'timezone'=>'UTC','maximum_ft'=>250,
            'day_type'=>'moderate','season_phase'=>'inseason','throwing_history'=>'Established program',
            'out_sets'=>5,'in_sets'=>3,'out_throws'=>4,'in_throws'=>2,'rest_seconds'=>30,'max_throws'=>null];
    }
    public function test_player_can_create_regular_or_guided_before_first_throw_and_resume_mode(): void {
        [$coach,$player,$practice,$url]=$this->setupGuide();
        $payload=['type'=>'T','modes'=>'LT','note'=>'Session setup','players'=>[['id'=>$player->id,'sort'=>0]]];
        $regular=$this->postJson('/api/training',$payload)->assertCreated()->assertJsonPath('data.long_toss_mode','regular')->json('data.id');
        $this->assertFalse(LongTossGuide::where('practice_id',$regular)->exists());
        $guided=$this->postJson('/api/training',$payload+['long_toss_setup'=>[$this->sessionSetup($player)]])
            ->assertCreated()->assertJsonPath('data.long_toss_mode','guided')->json('data.id');
        $this->getJson("/api/training/$guided")->assertOk()->assertJsonPath('data.long_toss_mode','guided');
        $this->getJson("/api/long-toss-guidance/$guided/$player->id")->assertOk()
            ->assertJsonPath('data.guide.snapshot.target_ft',200)->assertJsonPath('data.guide.current_stage.phase','Going Out')->assertJsonPath('data.guide.current_stage.distance_ft',30)->assertJsonPath('data.guide.snapshot.plan.min_age',21);
    }
    public function test_invalid_guided_setup_creates_neither_session_nor_guide(): void {
        [$coach,$player,$practice,$url]=$this->setupGuide();
        $before=Practice::count();$setup=$this->sessionSetup($player);$setup['max_throws']=3;
        $this->postJson('/api/training',['type'=>'T','modes'=>'LT','note'=>'Invalid setup','players'=>[['id'=>$player->id,'sort'=>0]],'long_toss_setup'=>[$setup]])->assertUnprocessable();
        $this->assertEquals($before,Practice::count());$this->assertDatabaseCount('long_toss_guides',0);
        $setup['player_id']=$coach->id;
        $this->postJson('/api/training',['type'=>'T','modes'=>'LT','note'=>'Mismatch','players'=>[['id'=>$player->id,'sort'=>0]],'long_toss_setup'=>[$setup]])->assertUnprocessable();
    }
    public function test_coach_creates_separate_guides_for_each_player(): void {
        [$coach,$player,$practice,$url]=$this->setupGuide();
        $other=User::factory()->create(['type'=>'player']);Player::factory()->create(['user_id'=>$other->id,'born_date'=>'2005-01-01']);
        $this->grantTeamAccess($other,Team::find($practice->team_id));
        Sanctum::actingAs($coach,['coach']);$rest=$this->sessionSetup($other);$rest['pain']=true;
        $id=$this->postJson('/api/training',['type'=>'T','modes'=>'LT','team'=>$practice->team_id,'note'=>'Two players',
            'players'=>[['id'=>$player->id,'sort'=>0],['id'=>$other->id,'sort'=>1]],'long_toss_setup'=>[$this->sessionSetup($player),$rest]])
            ->assertCreated()->assertJsonPath('data.long_toss_mode','guided')->json('data.id');
        $this->assertEquals(2,LongTossGuide::where('practice_id',$id)->count());
        $this->assertEquals('stopped',LongTossGuide::where('practice_id',$id)->where('player_id',$other->id)->first()->status);
    }

    public function test_setup_reads_profile_age_and_preserves_workload_below_total_cap(): void {
        [$coach,$player,$practice,$url]=$this->setupGuide();
        $setup=$this->sessionSetup($player);$setup['age']=90;$setup['start_ft']=90;
        $setup['max_throws']=29;
        $id=$this->postJson('/api/training',['type'=>'T','modes'=>'LT','note'=>'Total allocation','players'=>[['id'=>$player->id,'sort'=>0]],'long_toss_setup'=>[$setup]])->assertCreated()->json('data.id');
        $guide=LongTossGuide::where('practice_id',$id)->first();
        $this->assertEquals(21,$guide->snapshot['plan']['min_age']);
        $this->assertEquals(30,$guide->snapshot['stages'][0]['distance_ft']);
        $this->assertEquals([4,4,4,4,4,2,2,2],array_column($guide->snapshot['stages'],'throws'));
        $this->postJson('/api/result/longtoss',['practice_id'=>$id,'user_id'=>$player->id,'distance'=>30,'hop'=>0,'set'=>99])->assertCreated()->assertJsonPath('data.result.set',1);
        $player->player->update(['born_date'=>null]);
        $this->postJson('/api/training',['type'=>'T','modes'=>'LT','note'=>'Missing profile age','players'=>[['id'=>$player->id,'sort'=>0]],'long_toss_setup'=>[$setup]])->assertUnprocessable();
    }

    public function test_distance_limit_and_units_apply_to_create_and_edit(): void {
        [$coach,$player,$practice,$url]=$this->setupGuide();Sanctum::actingAs($coach,['coach']);
        $payload=['practice_id'=>$practice->id,'user_id'=>$player->id,'team_id'=>$practice->team_id,'set'=>1,'hop'=>0];
        $result=$this->postJson('/api/result/longtoss',$payload+['distance'=>100,'distance_unit'=>'yd'])->assertCreated()->assertJsonPath('data.result.distance',300)->json('data.result.id');
        foreach([[501,'ft'],[167,'yd'],[166.667,'yd']] as [$distance,$unit]) {
            $this->postJson('/api/result/longtoss',$payload+['distance'=>$distance,'distance_unit'=>$unit])->assertUnprocessable();
            $this->putJson('/api/result/longtoss/'.$result,['distance'=>$distance,'distance_unit'=>$unit])->assertUnprocessable();
        }
        $this->putJson('/api/result/longtoss/'.$result,['distance'=>100.5,'distance_unit'=>'yd'])->assertOk()->assertJsonPath('data.distance',302);
        $this->postJson('/api/result/longtoss',$payload+['distance'=>500])->assertCreated()->assertJsonPath('data.result.distance',500);
    }
    public function test_setup_maximums_are_in_feet_and_scoped_to_authorized_history(): void {
        [$coach,$player,$practice,$url]=$this->setupGuide();
        LongTossPractice::factory()->create(['practice_id'=>$practice->id,'team_id'=>$practice->team_id,'user_id'=>$player->id,'distance'=>250]);
        LongTossPractice::factory()->create(['team_id'=>Team::factory()->create()->id,'user_id'=>$player->id,'distance'=>350]);
        LongTossPractice::factory()->create(['team_id'=>$practice->team_id,'user_id'=>$player->id,'distance'=>700]);
        $endpoint='/api/long-toss-setup/players?player_ids[]='.$player->id;
        $this->getJson($endpoint)->assertOk()->assertJsonPath('data.0.maximum_ft',350);
        Sanctum::actingAs($coach,['coach']);$this->getJson($endpoint)->assertOk()->assertJsonPath('data.0.maximum_ft',250);
        Sanctum::actingAs(User::factory()->create(['type'=>'coach']),['coach']);$this->getJson($endpoint)->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['type'=>'player']),['player']);$this->getJson($endpoint)->assertForbidden();
    }

}
