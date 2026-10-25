<?php
namespace Tests\Feature\Api\Training;
use App\Models\{User,Team,CoachTeam,Practice,ScriptedBpSwing,BattingPracticeResult,ScriptedBpPlan};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class ScriptedWebPracticeTest extends TestCase
{
    private function context(): array {
        $coach=User::factory()->create(['type'=>'coach','subscription_plan'=>'coach_pro']);
        $player=User::factory()->create(['type'=>'player','subscription_plan'=>'player_pro']);
        $team=Team::factory()->create();
        CoachTeam::factory()->create(['coach_id'=>$coach->id,'team_id'=>$team->id]);
        \App\Models\PlayerTeam::factory()->create(['user_id'=>$player->id,'team_id'=>$team->id,'actual'=>true]);
        Sanctum::actingAs($coach,['coach']);
        return [$coach,$player,$team];
    }
    private function createScript(string $type, array $plan): array {
        [$coach,$player,$team]=$this->context();
        $data=['team'=>$team->id,'type'=>$type,'note'=>'Scripted test','scripted'=>$type!=='C','script_plan'=>$plan,'players'=>[['id'=>$player->id,'sort'=>0]]];
        if($type==='C')$data['cage']=['width'=>['ft'=>14,'inch'=>0],'height'=>['ft'=>12,'inch'=>0],'length'=>['ft'=>70,'inch'=>0]];
        $response=$this->postJson('/api/training',$data)->assertCreated();
        return [$response->json('data.id'),$player,$coach,$team];
    }
    public function test_plans_persist_and_reload_for_all_three_modes(): void {
        foreach([
            'P'=>[['pitchType'=>'FB','intendedCoord'=>1830]],
            'B'=>[['round_type'=>'BARREL','swing_count'=>2]],
            'C'=>[['drillId'=>'cage1','drillLabel'=>'Short Bat Progression','skill'=>'Barrel Control','objective'=>'Control','coachingCues'=>'Stay short','swing_count'=>10]],
        ] as $type=>$plan){
            [$id]=$this->createScript($type,$plan);
            $this->getJson('/api/training/'.$id)->assertOk()->assertJsonPath('data.script_plan',$plan);
            if($type==='B')$this->assertSame($plan,ScriptedBpPlan::where('practice_id',$id)->first()->rounds);
        }
    }
    public function test_scripted_swing_and_statistics_are_atomic_and_duplicate_progress_is_rejected(): void {
        [$id,$player]=$this->createScript('B',[['round_type'=>'BARREL','swing_count'=>2]]);
        $payload=['practice_id'=>$id,'batter_id'=>$player->id,'round_type'=>'BARREL','round_swing_index'=>1,'contact_type'=>'Hard','trajectory'=>'LineDrive','direction'=>'Middle','exit_velocity'=>90,'web_result'=>['pitch_mark'=>1830,'field_mark'=>3200,'pitch_location'=>'middle','zone'=>'S','field_direction'=>'CF']];
        $this->postJson('/api/result/scripted-bp/swing',$payload)->assertCreated()->assertJsonPath('data.batting_result.velocity',90);
        $this->assertSame(1,ScriptedBpSwing::where('practice_id',$id)->count());
        $this->assertSame(1,BattingPracticeResult::where('practice_id',$id)->count());
        $this->postJson('/api/result/scripted-bp/swing',$payload)->assertStatus(409);
        $this->assertSame(1,BattingPracticeResult::where('practice_id',$id)->count());
        $this->getJson('/api/statistics/'.$id.'/batting')->assertOk();
        $this->postJson('/api/result/scripted-bp/plan',['practice_id'=>$id,'rounds'=>[['round_type'=>'CONTACT','swing_count'=>2]]])->assertStatus(409);
        Practice::find($id)->update(['is_completed'=>true]);
        $payload['round_swing_index']=2;
        $this->postJson('/api/result/scripted-bp/swing',$payload)->assertStatus(422);
    }
    public function test_foreign_coach_and_player_cannot_write_or_read_other_players_scripted_results(): void {
        [$id,$player]=$this->createScript('B',[['round_type'=>'BARREL','swing_count'=>2]]);
        $other=User::factory()->create(['type'=>'coach','subscription_plan'=>'coach_pro']);
        Sanctum::actingAs($other,['coach']);
        $this->postJson('/api/result/scripted-bp/swing',['practice_id'=>$id,'batter_id'=>$player->id])->assertNotFound();
        $this->getJson('/api/result/scripted-bp/'.$id)->assertNotFound();
        Sanctum::actingAs($player,['player']);
        $this->postJson('/api/result/scripted-bp/swing',['practice_id'=>$id,'batter_id'=>$other->id])->assertForbidden();
    }
    public function test_invalid_pitch_plan_and_missing_scripted_flag_are_rejected(): void {
        [$coach,$player,$team]=$this->context();
        $data=['team'=>$team->id,'type'=>'P','note'=>'test','scripted'=>true,'players'=>[['id'=>$player->id,'sort'=>0]],'script_plan'=>[['pitchType'=>'FB','intendedCoord'=>9999]]];
        $this->postJson('/api/training',$data)->assertStatus(422);
        $data['script_plan'][0]['intendedCoord']=1830;$data['scripted']=false;
        $this->postJson('/api/training',$data)->assertStatus(422);
    }
}
