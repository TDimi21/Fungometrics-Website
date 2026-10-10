<?php

declare(strict_types=1);

namespace Tests\Feature\Workouts;

use Tests\TestCase;
use App\Models\{User,Team,WorkoutTemplate,WorkoutProgram,DailyPlan,DailyPlanProgress,DailyPlanAssignment,WeightBallPractice,Practice,PracticeLineUp};
use Database\Seeders\FlameBangersWorkoutTemplateSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class WorkoutLibraryTest extends TestCase
{
    private $coach;
    private $player;
    private $team;
    protected function setUp(): void
    {
        parent::setUp();
        config(['access.temporary_full_access.enabled' => true]);
        $this->coach = User::factory()->create(['type' => 'coach']);
        $this->player = User::factory()->create(['type' => 'player']);
        $this->team = Team::factory()->create();
        $this->grantTeamAccess($this->coach, $this->team);
        $this->grantTeamAccess($this->player, $this->team);
        $this->seed(FlameBangersWorkoutTemplateSeeder::class);
        Sanctum::actingAs($this->coach, ['coach']);
    }
    private function template($slug = 'velocity-day')
    {
        return WorkoutTemplate::where('slug', 'flamebangers-'.$slug)->with('sections.exercises')->firstOrFail();
    }
    private function useTemplate($slug = 'velocity-day')
    {
        $id = (string)Str::uuid();
        $this->postJson('/api/coach/workout-templates/'.$this->template($slug)->id.'/use', ['id' => $id,'team_id' => $this->team->id,'date' => '2026-10-09','player_ids' => [$this->player->id]])->assertCreated();
        return DailyPlan::findOrFail($id);
    }
    public function test_seed_is_idempotent_and_preserves_all_prescriptions(): void
    {
        $this->seed(FlameBangersWorkoutTemplateSeeder::class);
        $this->assertDatabaseCount('workout_templates', 5);
        $this->assertDatabaseCount('workout_template_exercises', 94);
        $t = $this->template();
        $run = $t->sections->flatMap->exercises->firstWhere('exercise_name', 'Run and Gun Weighted Balls');
        $this->assertStringContainsString('5, 6, 7, 5, 4, and 3 oz', $run->prescription_text);
        $this->assertNull($run->reps_min);
        $this->getJson('/api/coach/workout-templates/'.$t->id)->assertOk()->assertJsonCount(3, 'data.sections');
    }
    public function test_copy_is_editable_but_master_is_protected_and_snapshot_is_unchanged(): void
    {
        $t = $this->template();
        $data = $t->toArray();
        $data['name'] = 'Changed';
        $this->putJson('/api/coach/workout-templates/'.$t->id, $data)->assertForbidden();
        $copy = $this->postJson('/api/coach/workout-templates/'.$t->id.'/duplicate')->assertCreated()->json('data');
        $planId = (string)Str::uuid();
        $this->postJson('/api/coach/workout-templates/'.$copy['id'].'/use', ['id' => $planId,'team_id' => $this->team->id,'date' => '2026-10-09','player_ids' => [$this->player->id]])->assertCreated();
        $plan = DailyPlan::findOrFail($planId);
        $original = $plan->buckets;
        $copy['name'] = 'Custom recovery';
        $copy['sections'][0]['exercises'][0]['prescription_text'] = 'Coach custom instruction';
        $this->putJson('/api/coach/workout-templates/'.$copy['id'], $copy)->assertOk()->assertJsonPath('data.version', 2);
        $this->assertSame('Velocity Day', $t->fresh()->name);
        $this->assertSame($original, $plan->fresh()->buckets);
    }
    public function test_assignment_retries_do_not_duplicate_and_foreign_team_players_are_rejected(): void
    {
        $id = (string)Str::uuid();
        $url = '/api/coach/workout-templates/'.$this->template()->id.'/use';
        $payload = ['id' => $id,'team_id' => $this->team->id,'date' => '2026-10-09','player_ids' => [$this->player->id]];
        $this->postJson($url, $payload)->assertCreated();
        $this->postJson($url, $payload)->assertCreated();
        $this->assertDatabaseCount('daily_plans', 1);
        $this->assertDatabaseCount('daily_plan_assignments', 1);
        $payload['id'] = (string)Str::uuid();
        $payload['player_ids'] = [User::factory()->create(['type' => 'player'])->id];
        $this->postJson($url, $payload)->assertUnprocessable();
    }
    public function test_program_publishes_multiple_athletes_and_individual_overrides_exactly_once(): void
    {
        $other = User::factory()->create(['type' => 'player']);
        $this->grantTeamAccess($other, $this->team);
        $id = (string)Str::uuid();
        $payload = ['id' => $id,'version' => 0,'name' => 'Pitching program','team_id' => $this->team->id,'start_date' => '2026-10-12','weeks' => 4,'schedule' => [
            ['id' => (string)Str::uuid(),'day_offset' => 0,'template_id' => $this->template()->id,'phase' => 'Foundation','player_ids' => [$this->player->id,$other->id]],
            ['id' => (string)Str::uuid(),'day_offset' => 2,'template_id' => $this->template('recovery-day')->id,'phase' => 'Recovery / Reassessment','player_ids' => [$other->id]],
        ]];
        $this->postJson('/api/coach/workout-programs', $payload)->assertOk();
        $url = '/api/coach/workout-programs/'.$id.'/publish';
        $this->postJson($url, ['version' => 1])->assertUnprocessable();
        $this->postJson($url, ['version' => 1,'workload_approved' => true])->assertOk();
        $this->postJson($url, ['version' => 1,'workload_approved' => true])->assertOk();
        $this->assertDatabaseCount('daily_plans', 3); // one immutable player prescription per published entry
        $this->assertDatabaseCount('daily_plan_assignments', 3);
        $this->assertDatabaseHas('daily_plans', ['date' => '2026-10-14','name' => 'Recovery Day']);
        $this->postJson('/api/coach/workout-programs', $payload)->assertStatus(409);
    }
    public function test_player_completion_radar_retries_preserve_canonical_measurements(): void
    {
        $plan = $this->useTemplate();
        $plan->update(['status' => 'published','published_at' => now()]);
        $item = collect($plan->buckets)->flatMap(fn ($b) => $b['items'])->firstWhere('name', 'Run and Gun Weighted Balls');
        Sanctum::actingAs($this->player, ['player']);
        $this->getJson('/api/player/daily-plans/'.$plan->id)->assertOk();
        $data = ['items' => [$item['id'] => ['done' => true,'radar' => [['id' => (string)Str::uuid(),'weight' => 5,'velocity' => 91,'attempt' => 1,'timestamp' => '2026-10-09T15:00:00Z']]]],'started_at' => now()->toIso8601String(),'completed_at' => now()->toIso8601String()];
        $url = '/api/player/daily-plans/'.$plan->id.'/progress';
        $this->postJson($url, $data)->assertOk();
        $this->postJson($url, $data)->assertOk();
        $this->assertDatabaseCount('weight_ball_practices', 1);
        $this->assertDatabaseCount('daily_plan_progress', 1);
        $this->assertDatabaseCount('workout_session_links', 1);
        $this->assertEquals(91, WeightBallPractice::first()->velocity);
        $this->assertNotNull(DailyPlanProgress::first()->completed_at);
        $this->assertEquals($plan->buckets, $plan->fresh()->buckets);
        $data['items'][$item['id']]['radar'][0]['velocity'] = 95;
        $this->postJson($url, $data)->assertStatus(409);
    }
    public function test_bullpen_link_does_not_copy_results_and_rejects_another_players_session(): void
    {
        $plan = $this->useTemplate('bullpen-day');
        $plan->update(['status' => 'published']);
        $item = collect($plan->buckets)->flatMap(fn ($b) => $b['items'])->firstWhere('name', 'Bullpen');
        $practice = Practice::create(['team_id' => $this->team->id,'user_id' => $this->coach->id,'type' => 'P','modes' => 'HP','started' => now()]);
        PracticeLineUp::create(['practice_id' => $practice->id,'user_id' => $this->player->id,'sort' => 1]);
        Sanctum::actingAs($this->player, ['player']);
        $url = '/api/player/daily-plans/'.$plan->id.'/progress';
        $payload = ['items' => [$item['id'] => ['done' => true,'session_id' => $practice->id]]];
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $this->assertDatabaseCount('practices', 1);
        $this->assertDatabaseCount('workout_session_links', 1);
        $this->assertNull(DailyPlanProgress::first()->completed_at);
        $foreign = Practice::create(['team_id' => $this->team->id,'user_id' => $this->coach->id,'type' => 'P','modes' => 'HP','started' => now()]);
        $payload['items'][$item['id']]['session_id'] = $foreign->id;
        $this->postJson($url, $payload)->assertNotFound();
    }
    public function test_drafts_and_cross_player_access_cannot_be_completed(): void
    {
        $plan = $this->useTemplate();
        Sanctum::actingAs($this->player, ['player']);
        $this->postJson('/api/player/daily-plans/'.$plan->id.'/progress', ['items' => []])->assertNotFound();
        $plan->update(['status' => 'published']);
        Sanctum::actingAs(User::factory()->create(['type' => 'player']), ['player']);
        $this->postJson('/api/player/daily-plans/'.$plan->id.'/progress', ['items' => []])->assertForbidden();
        $this->getJson('/api/coach/workout-templates')->assertForbidden();
        $this->assertDatabaseCount('daily_plan_progress', 0);
    }
    public function test_other_coach_cannot_read_private_copy_or_edit_program(): void
    {
        $copy = $this->postJson('/api/coach/workout-templates/'.$this->template()->id.'/duplicate')->json('data');
        Sanctum::actingAs(User::factory()->create(['type' => 'coach']), ['coach']);
        $this->getJson('/api/coach/workout-templates/'.$copy['id'])->assertNotFound();
        $this->postJson('/api/coach/workout-templates/'.$this->template()->id.'/use', ['id' => (string)Str::uuid(),'team_id' => $this->team->id,'date' => '2026-10-09','player_ids' => []])->assertForbidden();
    }
    public function test_started_snapshots_are_frozen_and_partial_updates_preserve_observed_results(): void
    {
        $plan = $this->useTemplate();
        $plan->update(['status' => 'published']);
        $item = collect($plan->buckets)->flatMap(fn ($b) => $b['items'])->firstWhere('name', 'Run and Gun Weighted Balls');
        Sanctum::actingAs($this->player, ['player']);
        $url = '/api/player/daily-plans/'.$plan->id.'/progress';
        $this->postJson($url, ['items' => [$item['id'] => ['done' => true,'actual_reps' => 3,'radar' => [['id' => (string)Str::uuid(),'weight' => 5,'velocity' => 92,'attempt' => 1,'timestamp' => now()->toIso8601String()]]]]])->assertOk();
        $this->postJson($url, ['reflection' => ['comments' => 'Felt good'],'items' => []])->assertOk();
        $this->assertCount(1, DailyPlanProgress::first()->items[$item['id']]['radar']);
        $this->assertNull(DailyPlanProgress::first()->completed_at);
        Sanctum::actingAs($this->coach, ['coach']);
        $this->postJson('/api/coach/daily-plans', ['id' => $plan->id,'team_id' => $this->team->id,'name' => 'Changed','buckets' => []])->assertStatus(409);
        $this->assertSame($plan->buckets, $plan->fresh()->buckets);
    }
    public function test_completion_only_never_creates_a_performance_session(): void
    {
        $plan = $this->useTemplate();
        $plan->update(['status' => 'published']);
        $item = $plan->buckets[0]['items'][0];
        Sanctum::actingAs($this->player, ['player']);
        $this->postJson('/api/player/daily-plans/'.$plan->id.'/progress', ['items' => [$item['id'] => ['done' => true]],'completed_at' => now()->toIso8601String()])->assertOk();
        $this->assertDatabaseCount('practices', 0);
        $this->assertDatabaseCount('weight_ball_practices', 0);
        $this->assertDatabaseCount('workout_session_links', 0);
    }
    public function test_group_assignment_and_foreign_group_boundary(): void
    {
        $group = \App\Models\PlayerGroup::create(['id' => (string)Str::uuid(),'team_id' => $this->team->id,'created_by' => $this->coach->id,'name' => 'Pitchers','member_ids' => [$this->player->id]]);
        $url = '/api/coach/workout-templates/'.$this->template()->id.'/use';
        $payload = ['id' => (string)Str::uuid(),'team_id' => $this->team->id,'date' => '2026-10-09','player_ids' => [],'group_ids' => [$group->id]];
        $this->postJson($url, $payload)->assertCreated();
        $this->assertDatabaseHas('daily_plan_assignments', ['plan_id' => $payload['id'],'user_id' => $this->player->id]);
        $group->update(['team_id' => Team::factory()->create()->id]);
        $payload['id'] = (string)Str::uuid();
        $this->postJson($url, $payload)->assertNotFound();
    }
    public function test_program_rejects_out_of_range_days_and_stale_revisions(): void
    {
        $payload = ['id' => (string)Str::uuid(),'version' => 0,'name' => 'Draft','team_id' => $this->team->id,'start_date' => '2026-10-12','weeks' => 4,'schedule' => [['id' => (string)Str::uuid(),'day_offset' => 28,'template_id' => $this->template()->id,'phase' => 'Foundation','player_ids' => [$this->player->id]]]];
        $this->postJson('/api/coach/workout-programs', $payload)->assertUnprocessable();
        $payload['schedule'][0]['day_offset'] = 0;
        $this->postJson('/api/coach/workout-programs', $payload)->assertOk();
        $this->postJson('/api/coach/workout-programs', $payload)->assertStatus(409);
        Sanctum::actingAs(User::factory()->create(['type' => 'coach']), ['coach']);
        $this->postJson('/api/coach/workout-programs/'.$payload['id'].'/publish', ['version' => 1,'workload_approved' => true])->assertNotFound();
    }

    public function test_linked_existing_weighted_session_is_not_closed_by_workout_completion(): void
    {
        $plan = $this->useTemplate();
        $plan->update(['status' => 'published']);
        $item = collect($plan->buckets)->flatMap(fn ($b) => $b['items'])->firstWhere('name', 'Run and Gun Weighted Balls');
        $practice = Practice::create(['team_id' => $this->team->id,'user_id' => $this->player->id,'type' => 'T','modes' => 'WB','started' => now(),'is_completed' => false]);
        Sanctum::actingAs($this->player, ['player']);
        $url = '/api/player/daily-plans/'.$plan->id.'/progress';
        $this->postJson($url, ['items' => [$item['id'] => ['session_id' => $practice->id]]])->assertOk();
        $this->postJson($url, ['items' => [$item['id'] => ['done' => true,'radar' => [['id' => (string)Str::uuid(),'weight' => 5,'velocity' => 91,'attempt' => 1,'timestamp' => now()->toIso8601String()]]]],'completed_at' => now()->toIso8601String()])->assertOk();
        $this->assertFalse((bool)$practice->fresh()->is_completed);
        $this->assertDatabaseCount('practices', 1);
        $this->assertDatabaseCount('weight_ball_practices', 1);
    }

}
