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
    public function test_attention_queue_and_coach_feedback_complete_the_player_loop(): void
    {
        $plan=$this->useTemplate();
        $plan->update(['status'=>'published','buckets'=>[['type'=>'hitting','items'=>[['id'=>'drill','name'=>'Tee work']]]]]);
        $url='/api/coach/workout-attention?team_id='.$this->team->id.'&date=2026-10-09';
        $this->travelTo(\Carbon\Carbon::parse('2026-10-10 12:00:00'));
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.categories.0','not_started');
        $readiness=['sleep_hours'=>8,'sleep_quality'=>5,'energy'=>5,'overall_soreness'=>1,'arm_soreness'=>0,'shoulder_soreness'=>0,'elbow_soreness'=>0,'lower_body_soreness'=>0,'stress'=>1,'motivation'=>5,'pain_flag'=>false];
        Sanctum::actingAs($this->player,['player']);
        $this->postJson('/api/player/daily-plans/'.$plan->id.'/progress',[
            'started_at'=>now()->toIso8601String(),'readiness'=>$readiness,
            'items'=>['drill'=>['done'=>true]],'reflection'=>['workout_rating'=>5,'session_rpe'=>5],
            'completed_at'=>now()->toIso8601String(),
        ])->assertOk();
        Sanctum::actingAs($this->coach,['coach']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.categories',['review']);
        $this->postJson('/api/coach/daily-plans/'.$plan->id.'/players/'.$this->player->id.'/review',[
            'reviewed'=>true,'feedback'=>'Good work completing every drill.',
        ])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data',[]);
        Sanctum::actingAs($this->player,['player']);
        $my=$this->getJson('/api/player/daily-plans')->assertOk()->json('data');
        $this->assertSame('Good work completing every drill.',collect($my)->firstWhere('id',$plan->id)['progress']['coach_review']['feedback']);
        // Review is not resolution of a reported concern or unfinished drill.
        $pr=DailyPlanProgress::where('plan_id',$plan->id)->first();
        $pr->update(['items'=>['drill'=>['done'=>false]],'post_training'=>['pain'=>true],'reflection'=>[]]);
        Sanctum::actingAs($this->coach,['coach']);
        $row=$this->getJson($url)->assertOk()->json('data.0');
        $this->assertContains('reported',$row['categories']);
        $this->assertContains('unfinished',$row['categories']);
        $this->assertContains('check_in',$row['categories']);
        $this->assertNotContains('review',$row['categories']);
        $this->assertSame('reviewed',$row['summary']['review_status']);
        $this->assertContains('Pain reported',$row['reasons']);
        $this->travelBack();
    }

    public function test_attention_uses_active_scheduled_assignments_and_team_authorization(): void
    {
        $plan=$this->useTemplate(); $plan->update(['status'=>'published']);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-10 12:00:00'));
        $url='/api/coach/workout-attention?team_id='.$this->team->id.'&date=';
        DailyPlanAssignment::where('plan_id',$plan->id)->update(['scheduled_date'=>'2026-10-11']);
        $this->getJson($url.'2026-10-09')->assertOk()->assertJsonPath('data',[]);
        // Future workouts are not treated as missed starts.
        $this->getJson($url.'2026-10-11')->assertOk()->assertJsonPath('data',[]);
        DailyPlanAssignment::where('plan_id',$plan->id)->update(['scheduled_date'=>'2026-10-10']);
        $this->getJson($url.'2026-10-10')->assertOk()->assertJsonCount(1,'data');
        DailyPlanAssignment::where('plan_id',$plan->id)->update(['schedule_status'=>'skipped']);
        $this->getJson($url.'2026-10-10')->assertOk()->assertJsonPath('data',[]);
        Sanctum::actingAs(User::factory()->create(['type'=>'coach']),['coach']);
        $this->getJson($url.'2026-10-10')->assertForbidden();
        $this->travelBack();
    }

    public function test_weekly_results_compare_scheduled_weeks_without_inventing_missing_results(): void
    {
        $plan = $this->useTemplate();
        $plan->update(['status'=>'published', 'date'=>'2026-09-01', 'buckets'=>[
            ['type'=>'hitting','items'=>[['id'=>'h1','name'=>'Tee'],['id'=>'h2','name'=>'BP'],['id'=>'h3','name'=>'Unrecorded contacts']]],
            ['type'=>'strength_primary','items'=>[['id'=>'s1','name'=>'Squat']]],
            ['type'=>'defense','items'=>[['id'=>'d1','name'=>'Ground balls']]],
        ]]);
        DailyPlanAssignment::where('plan_id',$plan->id)->update(['scheduled_date'=>'2026-10-09']);
        DailyPlanProgress::create(['plan_id'=>$plan->id,'user_id'=>$this->player->id,'completed_at'=>now(),'items'=>[
            'h1'=>['done'=>true,'performance'=>['swings'=>10,'contacts'=>5]],
            'h2'=>['done'=>true,'performance'=>['swings'=>90,'contacts'=>90]],
            'h3'=>['done'=>false,'performance'=>['swings'=>50]],
            's1'=>['strength_summary'=>['volume_lb'=>1050,'completed_sets'=>2]],
            'd1'=>['performance'=>['attempts'=>10,'successful_reps'=>8,'errors'=>1]],
        ]]);
        $prior = $plan->replicate(); $prior->id=(string)Str::uuid(); $prior->date='2026-10-02'; $prior->save();
        DailyPlanAssignment::create(['plan_id'=>$prior->id,'user_id'=>$this->player->id,'scheduled_date'=>null,'schedule_status'=>'active']);
        DailyPlanProgress::create(['plan_id'=>$prior->id,'user_id'=>$this->player->id,'items'=>[
            'h1'=>['session_id'=>'session-1'], 'h2'=>['session_id'=>'session-1'],
        ]]);
        Sanctum::actingAs($this->player,['player']);
        $url='/api/player/workout-weekly-summary?date=2026-10-10';
        $data=$this->getJson($url)->assertOk()->json('data');
        $this->assertSame('2026-10-05',$data['week_start']);
        $this->assertSame(1,$data['current']['assigned']);
        $this->assertSame(1,$data['current']['submitted']);
        $this->assertSame(0,$data['current']['fully_completed']);
        $this->assertSame(2,$data['current']['completed_drills']);
        $this->assertSame(150,$data['current']['results']['swings']);
        $this->assertEquals(95,$data['current']['results']['contact_pct']);
        $this->assertSame([95,100], collect($data['metrics'])->firstWhere('key','contact_pct')['current_sample']);
        $this->assertEquals(80,$data['current']['results']['defensive_success_pct']);
        $this->assertSame(1050,$data['current']['results']['volume_lb']);
        $this->assertSame(1,$data['previous']['assigned']);
        $this->assertSame(1,$data['previous']['linked_sessions']);
        $this->assertNull(collect($data['metrics'])->firstWhere('key','contacts')['previous']);
        // Repeat reads cannot duplicate volume; both clients consume the same server calculation.
        $this->assertSame($data,$this->getJson($url)->assertOk()->json('data'));
        Sanctum::actingAs($this->coach,['coach']);
        $coachUrl='/api/coach/players/'.$this->player->id.'/workout-weekly-summary?date=2026-10-10';
        $this->assertSame($data,$this->getJson($coachUrl)->assertOk()->json('data'));
        DailyPlanAssignment::where('plan_id',$plan->id)->update(['schedule_status'=>'skipped']);
        $this->getJson($coachUrl)->assertOk()->assertJsonPath('data.current.assigned',0);
    }

    public function test_weekly_summary_is_scoped_to_authorized_players_and_teams(): void
    {
        $plan=$this->useTemplate(); $plan->update(['status'=>'published']);
        $other=User::factory()->create(['type'=>'coach']);
        Sanctum::actingAs($other,['coach']);
        $this->getJson('/api/coach/players/'.$this->player->id.'/workout-weekly-summary')->assertNotFound();
        Sanctum::actingAs($this->player,['player']);
        $this->getJson('/api/player/workout-weekly-summary?date=bad')->assertUnprocessable();
        $unassigned=User::factory()->create(['type'=>'player']);
        Sanctum::actingAs($unassigned,['player']);
        $this->getJson('/api/player/workout-weekly-summary?date=2026-10-10')->assertOk()->assertJsonPath('data.current.assigned',0);
    }

    public function test_every_saved_workout_includes_surveys_without_duplicates(): void
    {
        $plan = $this->useTemplate();
        $this->assertCount(1, collect($plan->buckets)->where('type', 'daily_readiness'));
        $this->assertSame('player_reflection', collect($plan->buckets)->last()['type']);
        $plan->buckets = [['type'=>'throwing','items'=>[]]];
        $plan->save();
        $plan->save();
        $buckets = $plan->fresh()->buckets;
        $this->assertCount(1, collect($buckets)->where('type', 'daily_readiness'));
        $this->assertCount(1, collect($buckets)->where('type', 'player_reflection'));
        $this->assertSame('player_reflection', collect($buckets)->last()['type']);
        $buckets[] = ['type'=>'recovery','items'=>[]];
        $plan->buckets = $buckets;
        $plan->save();
        $this->assertSame('player_reflection', collect($plan->fresh()->buckets)->last()['type']);
    }

    public function test_hitting_presets_preserve_source_and_quick_load_with_surveys(): void
    {
        $this->seed(\Database\Seeders\FmtrxHittingWorkoutTemplateSeeder::class);
        $before = WorkoutTemplate::where('category', 'hitting')->pluck('id', 'slug')->all();
        $this->seed(\Database\Seeders\FmtrxHittingWorkoutTemplateSeeder::class);
        $this->assertSame($before, WorkoutTemplate::where('category', 'hitting')->pluck('id', 'slug')->all());
        $this->assertCount(5, $before);
        $this->assertDatabaseCount('workout_templates', 10);
        $this->assertDatabaseCount('workout_template_exercises', 144);
        $visible = $this->getJson('/api/coach/workout-templates')->assertOk()->json('data');
        $this->assertCount(5, array_filter($visible, fn ($t) => $t['category'] === 'hitting' && $t['is_premade']));
        $source = json_decode(file_get_contents(database_path('data/fmtrx-hitting-workouts.json')), true);
        foreach ($source['templates'] as $preset) {
            $template = WorkoutTemplate::with('sections.exercises')->where('slug', $preset['slug'])->firstOrFail();
            $this->assertSame($preset['name'], $template->name);
            foreach ($preset['sections'] as $index => $section) {
                foreach ($section['exercises'] as $n => $exercise) {
                    $saved = $template->sections[$index]->exercises[$n];
                    $this->assertSame($exercise['exercise_name'], $saved->exercise_name);
                    $this->assertSame($exercise['prescription_text'], $saved->prescription_text);
                    $this->assertEquals($exercise['sets'], $saved->sets_min);
                    $this->assertSame($exercise['track_metric'] ?? null, $saved->metadata['track_metric'] ?? null);
                }
            }
            $id = (string) Str::uuid();
            $this->postJson('/api/coach/workout-templates/'.$template->id.'/use', [
                'id' => $id, 'team_id' => $this->team->id, 'date' => '2026-10-10', 'player_ids' => [$this->player->id],
            ])->assertCreated();
            $buckets = collect(DailyPlan::findOrFail($id)->buckets);
            $this->assertCount(1, $buckets->where('type', 'daily_readiness'));
            $this->assertSame('player_reflection', $buckets->last()['type']);
            $this->assertCount(5, $buckets->firstWhere('type', 'hitting')['items']);
        }
    }

    public function test_program_retains_training_days_before_workouts_are_selected(): void
    {
        $payload = ['id'=>(string) Str::uuid(), 'version'=>0, 'team_id'=>$this->team->id,
            'name'=>'Three days per week', 'start_date'=>'2026-10-10', 'weeks'=>4,
            'training_settings'=>['sessions_per_week'=>3, 'weekdays'=>[1,3,5]], 'schedule'=>[]];
        $saved = $this->postJson('/api/coach/workout-programs', $payload)->assertOk()
            ->assertJsonPath('data.training_settings.weekdays', [1,3,5])->json('data');
        $this->assertSame([1,3,5], WorkoutProgram::findOrFail($payload['id'])->training_settings['weekdays']);
        $payload['version'] = $saved['version'];
        $payload['training_settings']['sessions_per_week'] = 2;
        $this->postJson('/api/coach/workout-programs', $payload)->assertStatus(422);
    }

    public function test_player_submission_and_coach_feedback_share_summary_endpoints(): void
    {
        $plan = $this->useTemplate();
        $plan->update(['status'=>'published']);
        Sanctum::actingAs($this->player, ['player']);
        $response = $this->postJson('/api/player/daily-plans/'.$plan->id.'/progress', [
            'items'=>[], 'reflection'=>['workout_rating'=>2, 'session_rpe'=>9],
            'completed_at'=>now()->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.feedback_summary.submission_status', 'submitted')
          ->assertJsonPath('data.feedback_summary.completion_pct', 0)
          ->assertJsonPath('data.feedback_summary.checks.readiness.status', 'missing')
          ->assertJsonPath('data.feedback_summary.checks.reflection.status', 'received');
        $this->assertContains('Submitted with unfinished drills', $response->json('data.feedback_summary.attention_reasons'));
        Sanctum::actingAs($this->coach, ['coach']);
        $this->getJson('/api/coach/daily-plans/'.$plan->id.'/progress')->assertOk()
            ->assertJsonPath('data.players.0.progress.feedback_summary.submission_status', 'submitted');
        $this->postJson('/api/coach/daily-plans/'.$plan->id.'/players/'.$this->player->id.'/review', [
            'reviewed'=>true, 'feedback'=>'We will review your remaining drills together.',
        ])->assertOk()->assertJsonPath('data.feedback_summary.review_status', 'reviewed');
        $other = User::factory()->create(['type'=>'player']);
        $this->postJson('/api/coach/daily-plans/'.$plan->id.'/players/'.$other->id.'/review', ['feedback'=>'Wrong player'])->assertNotFound();
        Sanctum::actingAs($this->player, ['player']);
        $this->getJson('/api/player/daily-plans')->assertOk()
            ->assertJsonPath('data.0.progress.feedback_summary.coach_feedback', 'We will review your remaining drills together.');
    }

    public function test_skill_results_round_trip_and_retries_do_not_create_sessions(): void
    {
        $plan = $this->useTemplate();
        $itemId = (string) Str::uuid();
        $plan->update(['status'=>'published', 'buckets'=>[['type'=>'hitting','items'=>[['id'=>$itemId,'name'=>'Tee work']]]]]);
        Sanctum::actingAs($this->player, ['player']);
        $payload = ['items'=>[$itemId=>['done'=>true,'performance'=>['swings'=>20,'contacts'=>15,'hard_contacts'=>6,'exit_velocity_mph'=>82.5,'measurement_source'=>'Radar']]]];
        $url = '/api/player/daily-plans/'.$plan->id.'/progress';
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.items.'.$itemId.'.performance.swings',20)
            ->assertJsonPath('data.feedback_summary.skill_results.0.contact_pct',75)
            ->assertJsonPath('data.feedback_summary.skill_results.0.hard_contact_pct',40);
        $this->postJson($url, $payload)->assertOk();
        $this->assertDatabaseCount('daily_plan_progress',1);
        $this->assertDatabaseCount('practices',0);
        $payload['items'][$itemId]['performance']['contacts'] = 21;
        $this->postJson($url, $payload)->assertStatus(422);
        $payload['items'][$itemId]['performance']['contacts'] = 15;
        $payload['items'][$itemId]['performance']['measurement_source'] = '';
        $this->postJson($url, $payload)->assertStatus(422);
        Sanctum::actingAs($this->coach, ['coach']);
        $this->getJson('/api/coach/daily-plans/'.$plan->id.'/progress')->assertOk()
            ->assertJsonPath('data.players.0.progress.feedback_summary.skill_results.0.recorded.exit_velocity_mph',82.5);
    }

    public function test_hitting_links_existing_ev_session_without_copying_it(): void
    {
        $plan = $this->useTemplate();
        $itemId = (string) Str::uuid();
        $plan->update(['status'=>'published', 'buckets'=>[['type'=>'hitting','items'=>[['id'=>$itemId,'name'=>'EV work']]]]]);
        $session = Practice::create(['team_id'=>$this->team->id,'user_id'=>$this->player->id,'type'=>'T','modes'=>'EV','started'=>now(),'is_completed'=>true]);
        Sanctum::actingAs($this->player, ['player']);
        $url = '/api/player/daily-plans/'.$plan->id.'/progress';
        $payload = ['items'=>[$itemId=>['session_id'=>$session->id]]];
        $this->postJson($url,$payload)->assertOk()->assertJsonPath('data.feedback_summary.skill_results.0.result_status','linked_session');
        $this->postJson($url,$payload)->assertOk();
        $this->assertDatabaseCount('practices',1);
        $this->assertDatabaseCount('workout_session_links',1);
        $payload['items'][$itemId]['performance'] = ['swings'=>20];
        $this->postJson($url,$payload)->assertStatus(422);
    }

    public function test_pitching_skill_results_validate_counts_and_return_strike_rate(): void
    {
        $plan = $this->useTemplate();
        $itemId = (string) Str::uuid();
        $plan->update(['status'=>'published', 'buckets'=>[['type'=>'pitching','items'=>[['id'=>$itemId,'name'=>'Command work']]]]]);
        Sanctum::actingAs($this->player, ['player']);
        $url = '/api/player/daily-plans/'.$plan->id.'/progress';
        $payload = ['items'=>[$itemId=>['performance'=>['throws'=>30,'pitches'=>20,'strikes'=>12,'velocity_mph'=>80,'measurement_source'=>'Radar']]]];
        $this->postJson($url,$payload)->assertOk()->assertJsonPath('data.feedback_summary.skill_results.0.strike_pct',60);
        $payload['items'][$itemId]['performance']['strikes'] = 21;
        $this->postJson($url,$payload)->assertStatus(422);
        $this->assertDatabaseCount('practices',0);
    }

    public function test_defensive_results_and_strength_sets_are_shared_without_duplicate_history(): void
    {
        $plan = $this->useTemplate();
        $defense = (string) Str::uuid(); $strength = (string) Str::uuid();
        $plan->update(['status'=>'published','buckets'=>[
            ['type'=>'defense','items'=>[['id'=>$defense,'name'=>'Ground balls']]],
            ['type'=>'strength_primary','items'=>[['id'=>$strength,'name'=>'Squat']]],
        ]]);
        Sanctum::actingAs($this->player, ['player']);
        $url = '/api/player/daily-plans/'.$plan->id.'/progress';
        $payload = ['items'=>[
            $defense=>['performance'=>['attempts'=>10,'successful_reps'=>8,'errors'=>2]],
            $strength=>['actualSets'=>[['weight'=>100,'reps'=>5,'done'=>true],['weight'=>100,'reps'=>5,'done'=>false]]],
        ]];
        $this->postJson($url,$payload)->assertOk()
            ->assertJsonPath('data.items.'.$strength.'.strength_summary.volume_lb',500)
            ->assertJsonPath('data.feedback_summary.skill_results.0.defensive_success_pct',80);
        $payload['items'][$strength] = ['sets'=>[['weight'=>100,'reps'=>5,'done'=>true],['weight'=>110,'reps'=>5,'done'=>true]]];
        $this->postJson($url,$payload)->assertOk()->assertJsonPath('data.items.'.$strength.'.strength_summary.volume_lb',1050);
        $this->postJson($url,$payload)->assertOk();
        $this->assertSame(1, \App\Models\PlayerFitness::where('user_id',$this->player->id)->count());
        $payload['items'][$defense]['performance']['errors'] = 3;
        $this->postJson($url,$payload)->assertStatus(422);
        Sanctum::actingAs($this->coach, ['coach']);
        $this->getJson('/api/coach/daily-plans/'.$plan->id.'/progress')->assertOk()
            ->assertJsonPath('data.players.0.progress.feedback_summary.strength_results.0.summary.completed_sets',2);
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
