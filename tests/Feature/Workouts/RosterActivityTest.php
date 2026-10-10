<?php
namespace Tests\Feature\Workouts;
use Tests\TestCase;
use App\Models\{User,Team,Practice,PracticeLineUp,DailyPlan,DailyPlanProgress};
use Laravel\Sanctum\Sanctum;

class RosterActivityTest extends TestCase
{
    public function test_activity_is_scoped_to_the_coachs_team_and_completed_sessions(): void
    {
        config(['access.temporary_full_access.enabled'=>true]);
        $coach=User::factory()->create(['type'=>'coach']);
        $player=User::factory()->create(['type'=>'player','last_login_at'=>'2026-10-10 08:00:00']);
        $team=Team::factory()->create();
        $other=Team::factory()->create();
        $this->grantTeamAccess($coach,$team);
        $this->grantTeamAccess($player,$team);
        Sanctum::actingAs($coach,['coach']);
        $practice=Practice::create(['team_id'=>$team->id,'user_id'=>$coach->id,'started'=>'2026-10-10 08:00:00','finished'=>'2026-10-10 09:00:00','is_completed'=>true]);
        PracticeLineUp::create(['practice_id'=>$practice->id,'user_id'=>$player->id,'sort'=>1]);
        Practice::create(['team_id'=>$other->id,'user_id'=>$player->id,'started'=>'2026-10-11 08:00:00','finished'=>'2026-10-11 09:00:00','is_completed'=>true]);
        Practice::create(['team_id'=>$team->id,'user_id'=>$player->id,'started'=>'2026-10-12 08:00:00','is_completed'=>false]);
        $plan=DailyPlan::create(['id'=>(string)\Illuminate\Support\Str::uuid(),'team_id'=>$team->id,'created_by'=>$coach->id,'name'=>'Workout','date'=>'2026-10-10','status'=>'published','buckets'=>[]]);
        DailyPlanProgress::create(['plan_id'=>$plan->id,'user_id'=>$player->id,'completed_at'=>'2026-10-10 10:00:00']);
        $response=$this->getJson('/api/coach/teams/'.$team->id.'/roster-activity')->assertOk();
        $row=collect($response->json('data'))->firstWhere('id',$player->id);
        $this->assertStringStartsWith('2026-10-10T08:00:00',$row['last_login_at']);
        $this->assertStringStartsWith('2026-10-10T09:00:00',$row['session_completed_at']);
        $this->assertStringStartsWith('2026-10-10T10:00:00',$row['workout_completed_at']);
        $this->getJson('/api/coach/teams/'.$other->id.'/roster-activity')->assertNotFound();
    }
}
