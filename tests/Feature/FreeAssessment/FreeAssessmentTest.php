<?php

declare(strict_types=1);

namespace Tests\Feature\FreeAssessment;

use App\Models\{FreeAssessment, FreeAssessmentAttempt, FreeAssessmentResult, Player, PlayerFitness, Profile, Team, User};
use App\Services\Development\PlayerMetricFreshnessService;
use App\Services\Intelligence\{PopulationMetricRepository, IntelligenceDataAssembler};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use RuntimeException;

class FreeAssessmentTest extends TestCase
{
    private User $coach;
    private User $player;
    private Team $team;
    private FreeAssessment $event;
    protected function setUp(): void
    {
        parent::setUp();
        $this->coach = User::factory()->create(['type' => 'coach']);
        $this->team = Team::factory()->create();
        $this->grantTeamAccess($this->coach, $this->team);
        $this->player = User::factory()->create(['type' => 'player']);
        Profile::factory()->create(['user_id' => $this->player->id, 'first_name' => 'Test', 'last_name' => 'Athlete']);
        Player::factory()->create(['user_id' => $this->player->id, 'born_date' => now()->subYears(15)->toDateString()]);
        $this->grantTeamAccess($this->player, $this->team);
        Sanctum::actingAs($this->coach, ['coach']);
        $id = $this->postJson('/api/free-assessments', ['team_id' => $this->team->id, 'name' => 'Testing', 'location' => 'The Yard', 'assessment_date' => now()->toDateString()])->assertCreated()->json('id');
        $this->event = FreeAssessment::findOrFail($id);
        $this->postJson($this->url('/players'), ['player_id' => $this->player->id])->assertOk();
    }
    private function url(string $suffix = ''): string
    {
        return '/api/free-assessments/'.$this->event->id.$suffix;
    }
    private function save(string $station, array $values, int $revision = 0, array $extra = [])
    {
        return $this->putJson($this->url('/players/'.$this->player->id.'/stations/'.$station), ['values' => $values, 'revision' => $revision] + $extra);
    }
    public function test_retries_edits_and_other_stations_do_not_duplicate_or_overwrite_metrics(): void
    {
        $unrelated = PlayerFitness::create(['user_id' => $this->player->id, 'fitness_date' => now()->toDateString(), 'push_ups' => 12]);
        $this->save('pushups', [32])->assertOk()->assertJsonPath('revision', 1);
        $this->save('pushups', [32])->assertOk()->assertJsonPath('revision', 1);
        $this->save('broad_jump', [70, 80, 75])->assertOk();
        $this->save('pushups', [35], 1)->assertOk()->assertJsonPath('revision', 2);
        $fitness = PlayerFitness::where('free_assessment_id', $this->event->id)->sole();
        $this->assertEquals(35, $fitness->push_ups);
        $this->assertEquals(80, $fitness->broad_jump);
        $this->assertEquals(12, $unrelated->fresh()->push_ups);
        $this->assertEquals('free_assessment', $fitness->strength_test_metadata['source_type']);
        $this->assertSame(5, FreeAssessmentAttempt::count()); // both push-up revisions plus three jumps
        $this->assertSame(2, FreeAssessmentResult::count());
    }
    public function test_two_coaches_can_save_different_stations_but_stale_same_station_edit_is_rejected(): void
    {
        $this->save('pushups', [20])->assertOk();
        $other = User::factory()->create(['type' => 'coach']);
        $this->grantTeamAccess($other, $this->team);
        Sanctum::actingAs($other, ['coach']);
        $this->save('pull_ups', [7])->assertOk();
        $this->save('pushups', [40])->assertStatus(409);
        $f = PlayerFitness::where('free_assessment_id', $this->event->id)->sole();
        $this->assertEquals(20, $f->push_ups);
        $this->assertEquals(7, $f->pull_ups);
        $this->assertSame($other->id, FreeAssessmentResult::where('station', 'pull_ups')->sole()->entered_by);
    }
    public function test_team_and_player_authorization_covers_read_write_export_and_claim(): void
    {
        $outsider = User::factory()->create(['type' => 'coach']);
        Sanctum::actingAs($outsider, ['coach']);
        foreach (['', '/rankings', '/export', '/candidates'] as $path) {
            $this->getJson($this->url($path))->assertNotFound();
        }
        $this->save('pushups', [20])->assertNotFound();
        $this->postJson($this->url('/players/'.$this->player->id.'/claim'))->assertNotFound();
        Sanctum::actingAs($this->player, ['player']);
        $this->getJson($this->url('/export'))->assertForbidden();
    }
    public function test_phone_export_is_explicit_admin_only_and_csv_escapes_formulas(): void
    {
        $this->player->profile->update(['first_name' => '=HYPERLINK("bad")']);
        $this->save('pushups', [0])->assertOk();
        $csv = $this->get($this->url('/export'))->assertOk()->getContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString($this->player->phone, $csv);
        $this->getJson($this->url('/export?include_phone=1'))->assertForbidden();
        config(['access.admin_emails' => [$this->coach->email]]);
        $this->get($this->url('/export?include_phone=1'))->assertOk()->assertSee($this->player->phone);
    }
    public function test_velocities_reach_population_and_intelligence_without_creating_pitches(): void
    {
        $this->save('exit_velocity', array_fill(0, 10, 80))->assertOk();
        $this->save('pitching_velocity', array_fill(0, 10, 75), 0, ['protocol' => 'fastball'])->assertOk();
        $repository = app(PopulationMetricRepository::class);
        $this->assertEquals([80], $repository->valuesForMetric('max_exit_velocity', ['team_id' => $this->team->id]));
        $this->assertEquals([75], $repository->valuesForMetric('average_fastball_velocity', ['team_id' => $this->team->id]));
        $this->save('exit_velocity', array_fill(0, 10, 90), 1)->assertOk();
        $this->assertEquals([90], $repository->valuesForMetric('average_exit_velocity', ['team_id' => $this->team->id]));
        $this->assertDatabaseCount('bullpen_practice_results', 0);
        $data = app(IntelligenceDataAssembler::class)->assembleForPlayer($this->team->id, $this->player->id);
        $this->assertEquals(90, $data['exit_velocity_summary']['max_exit_velocity']);
        $this->assertEquals(75, $data['bullpen_summary']['max_pitch_velocity']);
        $this->assertNull($data['bullpen_summary']['strike_rate']);
    }
    public function test_mixed_pitching_is_not_fastball_population_data(): void
    {
        $this->save('pitching_velocity', array_fill(0, 10, 75), 0, ['protocol' => 'mixed'])->assertOk();
        $this->assertSame([], app(PopulationMetricRepository::class)->valuesForMetric('max_fastball_velocity', ['team_id' => $this->team->id]));
        $this->getJson($this->url('/rankings'))->assertOk()->assertJsonPath('0.peer_percentile', null);
    }
    public function test_recent_entries_return_latest_revision_in_attempt_order_and_server_author(): void
    {
        $this->save('exit_velocity', [70,71,72,73,74,75,76,77,78,79], 0, ['entered_by' => $this->player->id])->assertOk();
        $response = $this->getJson($this->url())->assertOk();
        $this->assertEquals([70,71,72,73,74,75,76,77,78,79], $response->json('results.0.values'));
        $this->assertSame($this->coach->id, FreeAssessmentResult::sole()->entered_by);
    }
    public function test_ranking_ties_and_lower_times_and_zero_reps(): void
    {
        $this->save('sprint_10yd', [1.8, 1.7, 1.9])->assertOk();
        $this->save('pushups', [0])->assertOk();
        $other = User::factory()->create(['type' => 'player']);
        Profile::factory()->create(['user_id' => $other->id]);
        $this->grantTeamAccess($other, $this->team);
        $this->postJson($this->url('/players'), ['player_id' => $other->id])->assertOk();
        $this->putJson($this->url('/players/'.$other->id.'/stations/sprint_10yd'), ['values' => [2,2.1,2.2], 'revision' => 0])->assertOk();
        $rows = collect($this->getJson($this->url('/rankings'))->assertOk()->json());
        $this->assertEquals(1, $rows->first(fn ($r) => 'sprint_10yd' === $r['station'] && $r['player_id'] === $this->player->id)['rank']);
        $this->assertEquals(2, $rows->first(fn ($r) => $r['player_id'] === $other->id)['rank']);
        $this->assertEquals(0, $rows->firstWhere('station', 'pushups')['value']);
    }
    public function test_grip_keeps_both_sides_and_all_attempts(): void
    {
        $this->save('grip_strength', [70,80,75,85,90,95])->assertOk()->assertJsonPath('summary.left.best', 80)->assertJsonPath('summary.right.best', 95);
        $f = PlayerFitness::where('free_assessment_id', $this->event->id)->sole();
        $this->assertEquals(80, $f->grip_strength_left);
        $this->assertEquals(95, $f->grip_strength_right);
        $this->assertEquals([70,80,75,85,90,95], $this->getJson($this->url())->json('results.0.values'));
    }
    public function test_incomplete_results_and_missing_protocols_are_not_finalized(): void
    {
        $this->save('exit_velocity', [80])->assertUnprocessable();
        $this->save('pull_strength', [80,90,100])->assertUnprocessable();
        $this->save('pushups', [-1])->assertUnprocessable();
        $this->save('pushups', [null])->assertUnprocessable();
        $this->assertDatabaseCount('free_assessment_results', 0);
    }
    public function test_completion_blocks_edits_until_reopened(): void
    {
        $this->patchJson($this->url(), ['status' => 'completed'])->assertOk();
        $this->save('pushups', [20])->assertStatus(409);
        $this->patchJson($this->url(), ['status' => 'active'])->assertOk();
        $this->save('pushups', [20])->assertOk();
    }
    public function test_quick_add_creates_real_profile_and_refuses_normalized_duplicate_phone(): void
    {
        $data = ['first_name' => 'New', 'last_name' => 'Player', 'phone' => '404-555-0123', 'born_date' => '2011-03-14', 'grad_year' => 2029, 'height_in_ft' => 5, 'height_in_inch' => 9, 'weight' => 145, 'position' => 'P', 'hit_side' => 'R', 'throw_side' => 'L'];
        $id = $this->postJson($this->url('/quick-add'), $data)->assertCreated()->json('player_id');
        $this->assertDatabaseHas('players', ['user_id' => $id, 'born_date' => '2011-03-14', 'grad_year' => 2029]);
        $this->assertDatabaseHas('free_assessment_players', ['assessment_id' => $this->event->id, 'player_id' => $id]);
        $this->postJson($this->url('/quick-add'), array_replace($data, ['phone' => '(404) 555-0123']))->assertStatus(409);
        $code = $this->postJson($this->url('/players/'.$id.'/claim'))->assertOk()->json('code');
        $this->assertDatabaseHas('account_claims', ['user_id' => $id, 'token_hash' => hash('sha256', $code)]);
    }
    public function test_backdated_events_do_not_appear_in_recent_population_windows(): void
    {
        $this->event->update(['assessment_date' => now()->subYears(2)->toDateString()]);
        $this->save('exit_velocity', array_fill(0, 10, 80))->assertOk();
        $this->save('broad_jump', [80,85,90])->assertOk();
        $repo = app(PopulationMetricRepository::class);
        $this->assertSame([], $repo->valuesForMetric('max_exit_velocity', ['team_id' => $this->team->id], 30));
        $this->assertSame([], $repo->valuesForMetric('broad_jump', ['team_id' => $this->team->id], 30));
    }

    public function test_daily_velocity_history_uses_attempts_once_instead_of_counting_the_maximum_twice(): void
    {
        $this->save('exit_velocity', [70,71,72,73,74,75,76,77,78,79])->assertOk();
        $this->save('exit_velocity', [70,71,72,73,74,75,76,77,78,79])->assertOk();
        $days = app(\App\Services\Intelligence\DailyVelocityAverageService::class)->forPlayer($this->team->id, $this->player->id, now()->subDays(30));
        $this->assertCount(1, $days);
        $this->assertEquals(74.5, $days[0]['average_hitting_velocity']);
        $this->assertSame(10, $days[0]['hitting_sample_count']);
    }
    public function test_dashboard_and_freshness_read_assessment_measurements_without_erasing_old_metrics(): void
    {
        config(['access.temporary_full_access.enabled' => true]);
        $this->coach->update(['subscription_plan' => 'coach_pro']);
        PlayerFitness::create(['user_id' => $this->player->id, 'fitness_date' => now()->subDay()->toDateString(), 'bench_press' => 125]);
        $this->save('exit_velocity', array_fill(0, 10, 85))->assertOk();
        $this->save('shuttle_5_10_5', [4.5,4.7,4.6])->assertOk();
        $this->save('pitching_velocity', array_fill(0, 10, 78), 0, ['protocol' => 'fastball'])->assertOk();
        $fitness = app(PlayerMetricFreshnessService::class)->snapshot($this->player->id, $this->team->id)['fitness'];
        $this->assertEquals(125, $fitness->bench_press);
        $this->assertEquals(4.5, $fitness->shuttle_5_10_5);
        $this->getJson('/api/coach/development/teams/'.$this->team->id.'/players/'.$this->player->id)
            ->assertOk()->assertJsonPath('data.current.max_exit_velocity', 85)->assertJsonPath('data.current.max_fb_velocity', 78)->assertJsonPath('data.current.shuttle_5_10_5', 4.5);
    }
    public function test_failed_canonical_sync_rolls_back_attempts_and_completion(): void
    {
        $this->mock(\App\Services\FreeAssessment\AssessmentMetricSyncService::class, function ($mock): void {
            $mock->shouldReceive('sync')->once()->andThrow(new RuntimeException('Simulated storage failure'));
        });
        $this->save('pushups', [30])->assertStatus(500);
        $this->assertDatabaseCount('free_assessment_results', 0);
        $this->assertDatabaseCount('free_assessment_attempts', 0);
        $this->assertSame(0, PlayerFitness::where('free_assessment_id', $this->event->id)->count());
    }
    public function test_other_team_measurements_do_not_leak_into_event_scoped_metrics(): void
    {
        $this->save('broad_jump', [80,85,90])->assertOk();
        $this->save('exit_velocity', array_fill(0, 10, 85))->assertOk();
        $anotherTeam = Team::factory()->create();
        $this->grantTeamAccess($this->player, $anotherTeam);
        $this->assertNull(app(PlayerMetricFreshnessService::class)->snapshot($this->player->id, $anotherTeam->id)['fitness']);
        $repo = app(PopulationMetricRepository::class);
        $this->assertSame([], $repo->valuesForMetric('broad_jump', ['team_id' => $anotherTeam->id]));
        $this->assertSame([], $repo->valuesForMetric('max_exit_velocity', ['team_id' => $anotherTeam->id]));
    }

    public function test_saved_results_automatically_appear_in_profile_report_list(): void
    {
        $this->getJson('/api/free-assessment-reports?player_id='.$this->player->id)->assertOk()->assertJsonCount(0, 'data');
        $this->save('pushups', [32])->assertOk();
        $this->getJson('/api/free-assessment-reports?team_id='.$this->team->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.player_id', $this->player->id)
            ->assertJsonPath('data.0.completed_stations', 1)->assertJsonPath('data.0.kind', 'free_assessment');
        $this->save('pushups', [35], 1)->assertOk();
        $this->getJson('/api/free-assessment-reports?player_id='.$this->player->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/free-assessment-reports/'.$this->event->id.'/players/'.$this->player->id)
            ->assertOk()->assertJsonPath('data.results.0.summary.best', 35)->assertJsonPath('data.total_stations', 9);
    }

    public function test_player_can_read_own_report_without_exposing_other_participants(): void
    {
        $this->save('pushups', [32])->assertOk();
        $other = User::factory()->create(['type' => 'player']);
        Profile::factory()->create(['user_id' => $other->id, 'first_name' => 'PrivateOtherAthlete']);
        $this->grantTeamAccess($other, $this->team);
        $this->postJson($this->url('/players'), ['player_id' => $other->id])->assertOk();
        $this->putJson($this->url('/players/'.$other->id.'/stations/pushups'), ['values' => [40], 'revision' => 0])->assertOk();
        Sanctum::actingAs($this->player, ['player']);
        $this->getJson('/api/free-assessment-reports')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.player_id', $this->player->id);
        $response = $this->getJson('/api/free-assessment-reports/'.$this->event->id.'/players/'.$this->player->id)
            ->assertOk()->assertJsonCount(1, 'data.results')->assertJsonPath('data.results.0.rank', 2);
        $this->assertStringNotContainsString($other->id, $response->getContent());
        $this->assertStringNotContainsString('PrivateOtherAthlete', $response->getContent());
        $this->assertStringNotContainsString($this->player->phone, $response->getContent());
        $this->getJson('/api/free-assessment-reports/'.$this->event->id.'/players/'.$other->id)->assertNotFound();
        $this->getJson('/api/free-assessment-reports?player_id='.$other->id)->assertForbidden();
    }

    public function test_report_access_is_scoped_for_coaches_and_rejects_claim_only_tokens(): void
    {
        $this->save('pushups', [32])->assertOk();
        $outsider = User::factory()->create(['type' => 'coach']);
        Sanctum::actingAs($outsider, ['coach']);
        $this->getJson('/api/free-assessment-reports')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/free-assessment-reports?team_id='.$this->team->id)->assertNotFound();
        $this->getJson('/api/free-assessment-reports/'.$this->event->id.'/players/'.$this->player->id)->assertNotFound();
        Sanctum::actingAs($this->player, ['profile-claim']);
        $this->getJson('/api/free-assessment-reports')->assertForbidden();
    }
}
