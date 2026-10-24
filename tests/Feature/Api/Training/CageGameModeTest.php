<?php
namespace Tests\Feature\Api\Training;
use App\Models\{User,Team,CoachTeam,PlayerTeam,CagePracticeResult};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class CageGameModeTest extends TestCase
{
    private function field(): array { return ['levelId'=>'hs','levelLabel'=>'High school','basePathFt'=>90,'pitchingFt'=>60.5,'fences'=>['lineL'=>320,'gapL'=>365,'center'=>390,'gapR'=>365,'lineR'=>320]]; }
    private function setupGame(): array {
        $coach=User::factory()->create(['type'=>'coach','subscription_plan'=>'coach_pro']);
        $player=User::factory()->create(['type'=>'player','subscription_plan'=>'player_pro']);
        $team=Team::factory()->create();CoachTeam::factory()->create(['coach_id'=>$coach->id,'team_id'=>$team->id]);PlayerTeam::factory()->create(['user_id'=>$player->id,'team_id'=>$team->id,'actual'=>true]);
        Sanctum::actingAs($coach,['coach']);
        return [$player,['team'=>$team->id,'type'=>'C','note'=>'Game','players'=>[['id'=>$player->id,'sort'=>0]],'cage'=>['width'=>['ft'=>14,'inch'=>0],'height'=>['ft'=>12,'inch'=>0],'length'=>['ft'=>70,'inch'=>0]],'game_field'=>$this->field()]];
    }
    public function test_field_and_swings_survive_resume_and_statistics_feed_report(): void {
        [$player,$payload]=$this->setupGame();
        $id=$this->postJson('/api/training',$payload)->assertCreated()->json('data.id');
        $this->getJson('/api/training/'.$id)->assertOk()->assertJsonPath('data.game_field',$this->field());
        $this->postJson('/api/result/cage',['practice_id'=>$id,'user_id'=>$player->id,'launch_angle'=>22,'launch_angle_velocity'=>95,'spray_angle'=>5,'distance_travel'=>300,'ground_ball'=>false,'cage_mark'=>0,'cage_position'=>'0'])->assertCreated();
        $this->getJson('/api/statistics/'.$id.'/cage')->assertOk()->assertJsonPath('data.game_field',$this->field())->assertJsonCount(1,'data.ball_x_ball');
        $other=User::factory()->create(['type'=>'player']);
        CagePracticeResult::factory()->create(['practice_id'=>$id,'user_id'=>$other->id]);
        Sanctum::actingAs($player,['player']);
        $this->getJson('/api/statistics/'.$id.'/cage')->assertOk()->assertJsonCount(1,'data.ball_x_ball');
    }
    public function test_invalid_field_or_mixed_modes_do_not_create_a_game(): void {
        [$player,$payload]=$this->setupGame();
        $payload['game_field']['fences']['center']=0;$this->postJson('/api/training',$payload)->assertStatus(422);
        $payload['game_field']=$this->field();$payload['type']='B';$this->postJson('/api/training',$payload)->assertStatus(422);
        $payload['type']='C';$payload['script_plan']=[['drillId'=>'cage1','drillLabel'=>'Drill','skill'=>'Control','objective'=>'Contact','coachingCues'=>'Stay short','swing_count'=>5]];
        $this->postJson('/api/training',$payload)->assertStatus(422);
    }
}
