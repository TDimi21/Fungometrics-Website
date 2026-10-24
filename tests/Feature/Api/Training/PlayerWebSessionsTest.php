<?php
namespace Tests\Feature\Api\Training;
use App\Models\{User, Profile, Player, Practice, Team, PlayerTeam};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class PlayerWebSessionsTest extends TestCase
{
    public static function modes(): array { return [['P', 'HP'], ['B', 'HP'], ['C', 'HP'], ['T', 'LT'], ['T', 'EV'], ['T', 'WB']]; }
    /** @dataProvider modes */
    public function test_player_can_create_resume_and_finish_personal_session(string $type, string $mode): void
    {
        $user=User::factory()->create(['type'=>'player','subscription_plan'=>'player_pro']);
        Profile::factory()->create(['user_id'=>$user->id]); Player::factory()->create(['user_id'=>$user->id]);
        Sanctum::actingAs($user,['player']);
        $payload=['type'=>$type,'note'=>'Personal practice','players'=>[['id'=>$user->id,'sort'=>0]]];
        if($type==='T')$payload['modes']=$mode;
        if($type==='C')$payload['cage']=['height'=>['ft'=>14,'inch'=>0],'width'=>['ft'=>14,'inch'=>0],'length'=>['ft'=>65,'inch'=>0]];
        $other = User::factory()->create(['type'=>'player']);
        $team = Team::factory()->create();
        PlayerTeam::factory()->create(['user_id'=>$user->id,'team_id'=>$team->id,'actual'=>true]);
        $this->postJson('/api/training',array_replace($payload,['players'=>[['id'=>$other->id,'sort'=>0]]]))->assertForbidden();
        $this->postJson('/api/training',$payload+['team'=>$team->id])->assertForbidden();
        $id=$this->postJson('/api/training',$payload)->assertCreated()->json('data.id');
        $this->assertSame($user->id,Practice::findOrFail($id)->user_id);
        $this->assertNull(Practice::findOrFail($id)->team_id);
        $this->getJson('/api/training/'.$id)->assertOk()->assertJsonPath('data.players.0.id',$user->id);
        if($type==='T') {
            Practice::factory()->create(['user_id'=>$user->id,'type'=>'T','modes'=>$mode==='LT'?'EV':'LT']);
            $this->getJson('/api/player/sessions/training?mode='.$mode)->assertOk()->assertJsonCount(1,'data.data')->assertJsonPath('data.data.0.id',$id);
        }
        [$endpoint,$result] = match ($type === 'T' ? $mode : $type) {
            'P' => ['bullpen',['pitcher_id'=>$user->id,'type_throw'=>'FB','pitch_side'=>'MC','pitch_mark'=>1800,'is_strike'=>true,'miles_per_hour'=>80]],
            'B' => ['batting',['batter_id'=>$user->id,'is_contact'=>true,'quality_of_contact'=>'H','type_of_hit'=>'LD','velocity'=>85]],
            'C' => ['cage',['user_id'=>$user->id,'launch_angle'=>20,'launch_angle_velocity'=>85,'spray_angle'=>0,'distance_travel'=>200,'ground_ball'=>false,'cage_mark'=>0,'cage_position'=>'0']],
            'LT' => ['longtoss',['user_id'=>$user->id,'set'=>1,'distance'=>150,'hop'=>0]],
            'EV' => ['exitvelocity',['user_id'=>$user->id,'set'=>1,'velocity'=>85,'trajectory'=>'LD']],
            'WB' => ['weightball',['user_id'=>$user->id,'set'=>1,'velocity'=>80,'weight'=>5]],
        };
        $identity = $type === 'P' ? 'pitcher_id' : ($type === 'B' ? 'batter_id' : 'user_id');
        $this->postJson('/api/result/'.$endpoint,['practice_id'=>$id]+array_replace($result,[$identity=>$other->id]))->assertForbidden();
        $this->postJson('/api/result/'.$endpoint,['practice_id'=>$id,'team_id'=>$team->id]+$result)->assertForbidden();
        $teamSession=Practice::factory()->create(['user_id'=>$user->id,'team_id'=>$team->id,'type'=>$type,'modes'=>$type==='T'?$mode:'HP']);
        $this->postJson('/api/result/'.$endpoint,['practice_id'=>$teamSession->id]+$result)->assertForbidden();
        $this->putJson('/api/training/'.$teamSession->id,['is_completed'=>true])->assertForbidden();
        $this->postJson('/api/result/'.$endpoint,['practice_id'=>$id]+$result)->assertCreated();
        $model = '\\App\\Models\\'.match ($endpoint) {
            'bullpen'=>'BullpenPracticeResult', 'batting'=>'BattingPracticeResult', 'cage'=>'CagePracticeResult',
            'longtoss'=>'LongTossPractice', 'exitvelocity'=>'ExitVelocityPractice', 'weightball'=>'WeightBallPractice',
        };
        $saved=$model::where('practice_id',$id)->firstOrFail();
        $this->putJson('/api/result/'.$endpoint.'/'.$saved->id,[$identity=>$other->id])->assertForbidden();
        $this->putJson('/api/result/'.$endpoint.'/'.$saved->id,['team_id'=>$team->id])->assertForbidden();
        $this->assertSame($user->id,$saved->fresh()->{$identity});
        $this->assertNull($saved->fresh()->team_id);
        $this->getJson('/api/statistics/'.$id.'/'.$endpoint.'?player=1')->assertOk();
        $this->putJson('/api/training/'.$id,['is_completed'=>true,'end_note'=>'Done'])->assertOk();
        $this->assertTrue((bool)Practice::findOrFail($id)->is_completed);
    }
}
