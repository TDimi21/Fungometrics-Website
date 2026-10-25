<?php
namespace Tests\Feature\Api\Planner;

use App\Models\{User,Team,DailyPlan,Practice};
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SimpleAssignmentsTest extends TestCase
{
    public function test_quick_task_publish_retry_and_player_completion_reach_coach_results(): void
    {
        config(['access.temporary_full_access.enabled'=>true,'access.temporary_full_access.ends_at'=>null]);
        $coach=User::factory()->create(['type'=>'coach']); $player=User::factory()->create(['type'=>'player']); $team=Team::factory()->create();
        $this->grantTeamAccess($coach,$team);$this->grantTeamAccess($player,$team);
        Sanctum::actingAs($coach,['coach']);
        $id=(string)Str::uuid(); $item=(string)Str::uuid();
        $body=['id'=>$id,'team_id'=>$team->id,'name'=>'Review your goals','date'=>now()->toDateString(),'status'=>'published','settings'=>['assignment_kind'=>'quick','due_time'=>'18:00'],'assigned_player_ids'=>[$player->id],'buckets'=>[['type'=>'education','items'=>[['id'=>$item,'name'=>'Review your goals','required'=>true,'note'=>'Write your focus','metadata'=>['execution_type'=>'COMPLETE']]]]]];
        $this->postJson('/api/coach/daily-plans',$body)->assertOk();
        $this->postJson('/api/coach/daily-plans',$body)->assertOk();
        $this->assertDatabaseCount('daily_plans',1);$this->assertDatabaseCount('daily_plan_assignments',1);
        Sanctum::actingAs($player,['player']);
        $day=$this->getJson('/api/player/planner/day?date='.now()->toDateString())->assertOk()->assertJsonPath('data.0.settings.due_time','18:00')->json('data.0');
        $task=collect($day['blocks'])->flatMap(fn($b)=>$b['items'])->firstWhere('id',$item);
        $this->assertSame('COMPLETE',$task['execution_type']);
        $this->postJson('/api/player/daily-plans/'.$id.'/progress',['version'=>0,'items'=>[$item=>['done'=>true]]])->assertOk();
        Sanctum::actingAs($coach,['coach']);
        $this->getJson('/api/coach/planner/day?team_id='.$team->id.'&date='.now()->toDateString())->assertOk()->assertJsonPath('data.0.actual_results.'.$item.'.done',true);
        $this->assertDatabaseCount('practices',0);
    }

    public function test_all_six_assignment_modes_resolve_to_existing_engines_and_liveab_is_rejected(): void
    {
        config(['access.temporary_full_access.enabled'=>true,'access.temporary_full_access.ends_at'=>null]);
        $coach=User::factory()->create(['type'=>'coach']);$player=User::factory()->create(['type'=>'player']);$team=Team::factory()->create();
        $this->grantTeamAccess($coach,$team);$this->grantTeamAccess($player,$team);
        foreach(['bullpen','batting','cage','weighted_ball','long_toss','exit_velocity'] as $mode) {
            Sanctum::actingAs($coach,['coach']);
            $id=(string)Str::uuid();$item=(string)Str::uuid();
            $body=['id'=>$id,'team_id'=>$team->id,'name'=>$mode,'date'=>now()->toDateString(),'status'=>'published','assigned_player_ids'=>[$player->id],'buckets'=>[['type'=>'throwing','items'=>[['id'=>$item,'name'=>$mode,'metadata'=>['session_type'=>$mode,'execution_type'=>'LAUNCH_SESSION']]]]]];
            $this->postJson('/api/coach/daily-plans',$body)->assertOk();
            Sanctum::actingAs($player,['player']);
            $data=$this->getJson('/api/player/daily-plans/'.$id)->assertOk()->json('data');
            $task=collect($data['blocks'])->flatMap(fn($b)=>$b['items'])->firstWhere('id',$item);
            $this->assertSame('LAUNCH_SESSION',$task['execution_type']);
            $this->assertNotEmpty($task['linked_session']['native_practice_screen']);
            $this->assertNull($data['completion']['completed_at']);
        }
        Sanctum::actingAs($coach,['coach']);
        $body['id']=(string)Str::uuid();$body['buckets'][0]['items'][0]['metadata']['session_type']='live_ab';
        $this->postJson('/api/coach/daily-plans',$body)->assertUnprocessable();
        $this->assertSame(6,DailyPlan::count());$this->assertSame(0,Practice::count());
    }
}
