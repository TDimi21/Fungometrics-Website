<?php
namespace Tests\Feature\Api\Planner;

use App\Models\{User,Team,Profile,Player,Practice};
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScoredAssignmentRoundTripTest extends TestCase
{
    public static function modes(): array
    {
        return [['bullpen','P','HP','bullpen'],['batting','B','HP','batting'],['cage','C','HP','cage'],['weighted_ball','T','WB','weightball'],['long_toss','T','LT','longtoss'],['exit_velocity','T','EV','exitvelocity']];
    }

    /** @dataProvider modes */
    public function test_coach_assignment_player_scoring_and_coach_review_share_one_session($mode,$type,$code,$endpoint): void
    {
        config(['access.temporary_full_access.enabled'=>true,'access.temporary_full_access.ends_at'=>null]);
        $coach=User::factory()->create(['type'=>'coach']);
        $player=User::factory()->create(['type'=>'player']);
        Profile::factory()->create(['user_id'=>$player->id]);
        Player::factory()->create(['user_id'=>$player->id]);
        $team=Team::factory()->create(); $primary=Team::factory()->create();
        $this->grantTeamAccess($coach,$team); $this->grantTeamAccess($player,$team); $this->grantTeamAccess($player,$primary);
        $plan=(string)Str::uuid(); $item=(string)Str::uuid();
        Sanctum::actingAs($coach,['coach']);
        $this->postJson('/api/coach/daily-plans',[
            'id'=>$plan,'team_id'=>$team->id,'name'=>$mode,'date'=>now()->toDateString(),'status'=>'published',
            'settings'=>['assignment_kind'=>'scored'],'assigned_player_ids'=>[$player->id],
            'buckets'=>[['type'=>'throwing','items'=>[['id'=>$item,'name'=>$mode,'required'=>true,'metadata'=>['session_type'=>$mode,'execution_type'=>'LAUNCH_SESSION']]]]],
        ])->assertOk();
        Sanctum::actingAs($player,['player']);
        $this->getJson('/api/player/daily-plans/'.$plan)->assertOk()->assertJsonPath('data.completion.completed_at',null);
        $body=['note'=>'Assigned practice','type'=>$type,'players'=>[['id'=>$player->id,'sort'=>0]],'share_with_team_ids'=>[$primary->id,$team->id],'planner_plan_id'=>$plan,'planner_item_id'=>$item];
        if($type==='T')$body['modes']=$code;
        if($type==='C')$body['cage']=['height'=>['ft'=>14,'inch'=>0],'width'=>['ft'=>14,'inch'=>0],'length'=>['ft'=>65,'inch'=>0]];
        $creation=$this->postJson('/api/training',$body);
        $this->assertSame(201,$creation->status(),$creation->getContent());
        $id=$creation->json('data.id');
        $retry=$this->postJson('/api/training',$body);
        $retry->assertSuccessful();
        $this->assertSame($id,$retry->json('data.id'));
        // Finalization alone cannot complete an assignment without recorded results.
        Practice::findOrFail($id)->update(['is_completed'=>true]);
        $this->getJson('/api/player/daily-plans/'.$plan)->assertOk()
            ->assertJsonPath('data.completion.completed_at',null)
            ->assertJsonPath('data.actual_results.'.$item.'.done',false);
        Practice::findOrFail($id)->update(['is_completed'=>false]);
        $result=match($endpoint){
            'bullpen'=>['pitcher_id'=>$player->id,'type_throw'=>'FB','pitch_side'=>'MC','pitch_mark'=>1800,'is_strike'=>true,'miles_per_hour'=>80],
            'batting'=>['batter_id'=>$player->id,'is_contact'=>true,'quality_of_contact'=>'H','type_of_hit'=>'LD','velocity'=>85],
            'cage'=>['user_id'=>$player->id,'launch_angle'=>20,'launch_angle_velocity'=>85,'spray_angle'=>0,'distance_travel'=>200,'ground_ball'=>false,'cage_mark'=>0,'cage_position'=>'0'],
            'weightball'=>['user_id'=>$player->id,'set'=>1,'velocity'=>80,'weight'=>5],
            'longtoss'=>['user_id'=>$player->id,'set'=>1,'distance'=>150,'hop'=>0],
            'exitvelocity'=>['user_id'=>$player->id,'set'=>1,'velocity'=>85,'trajectory'=>'LD'],
        };
        $this->postJson('/api/result/'.$endpoint,['practice_id'=>$id]+$result)->assertCreated();
        $this->getJson('/api/player/daily-plans/'.$plan)->assertOk()->assertJsonPath('data.completion.completed_at',null);
        $this->putJson('/api/training/'.$id,['is_completed'=>true,'end_note'=>'Done'])->assertOk();
        $playerView=$this->getJson('/api/player/daily-plans/'.$plan)->assertOk();
        $this->assertNotNull($playerView->json('data.completion.completed_at'));
        $this->getJson('/api/statistics/'.$id.'/'.$endpoint.'?player=1')->assertOk();
        Sanctum::actingAs($coach,['coach']);
        $day=$this->getJson('/api/coach/planner/day?team_id='.$team->id.'&date='.now()->toDateString())->assertOk();
        $this->assertNotNull($day->json('data.0.completion.completed_at'));
        $review=$this->getJson('/api/coach/daily-plans/'.$plan.'/progress')->assertOk()->assertJsonPath('data.players.0.progress.items.'.$item.'.session_id',$id)->assertJsonPath('data.players.0.progress.items.'.$item.'.done',true);
        $this->assertNotNull($review->json('data.players.0.progress.completed_at'));
        $this->getJson('/api/statistics/'.$id.'/'.$endpoint)->assertOk();
        $recent=$this->getJson('/api/coach/sessions/lasts/'.$team->id)->assertOk()->json('data');
        $this->assertTrue(collect($recent)->flatten(1)->contains(fn($s)=>$s['id']===$id));
        $this->assertSame(1,Practice::count());
        $this->assertDatabaseCount('workout_session_links',1);
        $outsider=User::factory()->create(['type'=>'coach']);
        Sanctum::actingAs($outsider,['coach']);
        $this->getJson('/api/statistics/'.$id.'/'.$endpoint)->assertNotFound();
    }
}
