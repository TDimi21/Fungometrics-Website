<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\Templates\FmtrxCsvTemplateService;
use App\Services\DataHub\Templates\FmtrxTemplateCatalog;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FmtrxTemplateTest extends TestCase
{
    public function test_catalog_prioritizes_live_web_form_templates_and_exposes_lower_priority_sessions(): void
    {
        $templates = app(FmtrxTemplateCatalog::class)->all();

        $this->assertSame(
            ['assessment', 'strength', 'mobility', 'recovery'],
            array_slice(array_keys($templates), 0, 4)
        );
        $this->assertSame(
            ['exit_velocity', 'long_toss', 'weighted_balls', 'bullpen', 'batting_practice', 'live_ab'],
            array_slice(array_keys($templates), 4)
        );
        $this->assertSame(['fmtrx_player_id', 'player_name', 'team_id', 'record_date'], array_column(array_slice($templates['assessment']['fields'], 0, 4), 'key'));
        $this->assertContains('sleep_quality_1_to_5', array_column($templates['recovery']['fields'], 'key'));
        $this->assertContains('t_spine_rotation', array_column($templates['mobility']['fields'], 'key'));
        $strengthKeys = array_column($templates['strength']['fields'], 'key');
        $this->assertContains('trap_bar_deadlift_lbs', $strengthKeys);
        $this->assertContains('plank_hold_sec', $strengthKeys);
        $this->assertContains('grip_strength_left', $strengthKeys);
        $this->assertContains('grip_strength_right', $strengthKeys);
        $this->assertContains('med_ball_weight_lbs', $strengthKeys);
    }

    public function test_csv_contains_versioned_metadata_canonical_keys_labels_and_active_roster_rows(): void
    {
        $team = Team::factory()->create(['name' => 'North Oconee']);
        $active = $this->player($team, 'Thomas', 'Dimitroff', true);
        $this->player($team, 'Inactive', 'Player', false);

        $csv = app(FmtrxCsvTemplateService::class)->generate('strength', (string) $team->id, $team->name);

        $this->assertStringContainsString('FMTRX_TEMPLATE,strength,VERSION,1.0', $csv);
        $this->assertStringContainsString('fmtrx_player_id,player_name,team_id,record_date', $csv);
        $this->assertStringContainsString('Bench Press (lbs)', $csv);
        $this->assertStringContainsString((string) $active->id.',"Thomas Dimitroff"', $csv);
        $this->assertStringNotContainsString('Inactive Player', $csv);
    }

    public function test_roster_names_are_protected_from_spreadsheet_formula_execution(): void
    {
        $team = Team::factory()->create();
        $this->player($team, '=HYPERLINK', 'Danger', true);

        $csv = app(FmtrxCsvTemplateService::class)->generate('mobility', (string) $team->id, $team->name);

        $this->assertStringContainsString("\"'=HYPERLINK Danger\"", $csv);
    }

    public function test_download_is_entitled_team_scoped_and_makes_no_import_writes(): void
    {
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $this->player($team, 'Carter', 'Moon', true);
        Sanctum::actingAs($coach, ['coach']);

        $this->getJson('/api/data-hub/templates')->assertOk()->assertJsonCount(10, 'data');
        $this->get('/api/data-hub/templates/download?team_id='.$other->id.'&template=assessment')->assertForbidden();
        $this->get('/api/data-hub/templates/download?team_id='.$team->id.'&template=assessment')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('cache-control', 'no-store, private');

        $this->assertDatabaseCount('player_assessments', 0);
        $this->assertDatabaseCount('mapping_template_versions', 0);
    }

    public function test_generated_template_is_detected_and_ids_and_canonical_keys_are_reviewed_without_writes(): void
    {
        $team = Team::factory()->create(['name' => 'Template Team']);
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $player = $this->player($team, 'Thomas', 'Dimitroff', true);
        Sanctum::actingAs($coach, ['coach']);
        $csv = app(FmtrxCsvTemplateService::class)->generate('strength', (string) $team->id, $team->name);
        $csv = str_replace($player->id.',"Thomas Dimitroff",'.$team->id.',', $player->id.',"Thomas Dimitroff",'.$team->id.',2026-07-25', $csv);

        $this->post('/api/data-hub/inspect', [
            'platform' => 'generic-csv',
            'team_id' => $team->id,
            'session_type' => 'strength',
            'file' => UploadedFile::fake()->createWithContent('fmtrx-strength.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('data.template.type', 'strength')
            ->assertJsonPath('data.template.version', '1.0')
            ->assertJsonPath('data.players.0.suggested_matches.0.player_id', (string) $player->id)
            ->assertJsonPath('data.players.0.suggested_matches.0.auto_select', true);

        $this->assertDatabaseCount('player_assessments', 0);
        $this->assertDatabaseCount('mapping_template_versions', 0);
    }

    private function player(Team $team, string $first, string $last, bool $active): User
    {
        $user = User::factory()->create(['type' => 'player', 'status' => $active]);
        Profile::factory()->create(['user_id' => $user->id, 'first_name' => $first, 'last_name' => $last]);
        PlayerTeam::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

        return $user;
    }
}
