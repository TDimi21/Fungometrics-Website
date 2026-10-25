<?php
namespace Tests\Feature\Api\Training;
use App\Models\{User, Profile, Player, Practice, Team, PlayerTeam, CoachTeam};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class PlayerSessionSharingTest extends TestCase {
    public static function modes(): array { return [['P','HP','bullpen','pitcher_id',['type_throw'=>'FB','pitch_side'=>'MC','miles_per_hour'=>80]], ['B','HP','batting','batter_id',['is_contact'=>true,'quality_of_contact'=>'H','type_of_hit'=>'LD','velocity'=>80]], ['C','HP','cage','user_id',['launch_angle'=>20,'launch_angle_velocity'=>80,'spray_angle'=>0,'distance_travel'=>200,'cage_mark'=>0,'cage_position'=>'0']], ['T','LT','longtoss','user_id',['distance'=>150,'hop'=>0,'set'=>1]], ['T','EV','exitvelocity','user_id',['velocity'=>80,'trajectory'=>'LD','set'=>1]], ['T','WB','weightball','user_id',['velocity'=>80,'weight'=>5,'set'=>1]]]; }
    /** @dataProvider modes */
    public function test_connected_team_coach_can_view_but_only_owner_can_score($type,$mode,$endpoint,$identity,$result): void {
        $player=User::factory()->create(['type'=>'player','subscription_plan'=>'player_pro']);
        Profile::factory()->create(['user_id'=>$player->id]);Player::factory()->create(['user_id'=>$player->id]);
        $coach=User::factory()->create(['type'=>'coach','subscription_plan'=>'coach_pro']);
        $team=Team::factory()->create();CoachTeam::factory()->create(['coach_id'=>$coach->id,'team_id'=>$team->id]);
        $membership=PlayerTeam::factory()->create(['user_id'=>$player->id,'team_id'=>$team->id,'actual'=>true]);
        Sanctum::actingAs($player,['player']);
        $payload=['type'=>$type,'note'=>'My workout','players'=>[['id'=>$player->id,'sort'=>0]],'share_with_team_id'=>$team->id];
        if($type==='T')$payload['modes']=$mode;
        if($type==='C')$payload['cage']=['height'=>['ft'=>14,'inch'=>0],'width'=>['ft'=>14,'inch'=>0],'length'=>['ft'=>65,'inch'=>0]];
        $foreign=Team::factory()->create();
        $this->postJson('/api/training',array_replace($payload,['share_with_team_id'=>$foreign->id]))->assertForbidden();
        $id=$this->postJson('/api/training',$payload)->assertCreated()->assertJsonPath('data.user_id',$player->id)->assertJsonPath('data.is_player_shared',true)->json('data.id');
        $this->assertSame($team->id,Practice::findOrFail($id)->team_id);
        $body=['practice_id'=>$id,$identity=>$player->id]+$result;
        $this->postJson('/api/result/'.$endpoint,$body)->assertCreated();
        $this->postJson('/api/result/'.$endpoint,$body+['team_id'=>$foreign->id])->assertForbidden();
        $this->postJson('/api/result/'.$endpoint,array_replace($body,[$identity=>$coach->id]))->assertForbidden();
        Sanctum::actingAs($coach,['coach']);
        $this->getJson('/api/training/'.$id)->assertOk();
        $stats=$this->getJson('/api/statistics/'.$id.'/'.$endpoint)->assertOk();
        $this->assertStringContainsString($player->id,$stats->getContent());
        $this->assertStringContainsString($team->id,$stats->getContent());
        $this->getJson($type==='T'?'/api/sessions/all/modes?team='.$team->id.'&mode='.$mode:'/api/sessions/all/type?type='.$type)->assertOk()->assertJsonPath('data.0.id',$id);
        $this->postJson('/api/result/'.$endpoint,$body)->assertForbidden();
        $this->putJson('/api/training/'.$id,['is_completed'=>true])->assertForbidden();
        $this->postJson('/api/coach/lineup/'.$id,['player'=>$coach->id,'sort'=>1,'batting'=>true,'pitching'=>false])->assertForbidden();
        $teammate=User::factory()->create(['type'=>'player','subscription_plan'=>'player_pro']);PlayerTeam::factory()->create(['user_id'=>$teammate->id,'team_id'=>$team->id,'actual'=>true]);Sanctum::actingAs($teammate,['player']);
        $this->getJson('/api/training/'.$id)->assertNotFound();
        $list=$this->getJson($type==='T'?'/api/sessions/all/modes?team='.$team->id.'&mode='.$mode:'/api/sessions/type?team_id='.$team->id.'&type='.$type);
        $this->assertStringNotContainsString($id,$list->getContent());
        $this->getJson('/api/statistics/'.$id.'/'.$endpoint)->assertNotFound();
        Sanctum::actingAs($player,['player']);$membership->update(['actual'=>false]);
        $this->postJson('/api/training',$payload)->assertForbidden();
        $this->postJson('/api/result/'.$endpoint,$body)->assertForbidden();
        $this->getJson('/api/training/'.$id)->assertOk();
    }
}
