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

}
