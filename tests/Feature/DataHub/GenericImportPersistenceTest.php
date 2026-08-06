<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Generic\UniversalSpreadsheetInspector;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class GenericImportPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_spreadsheet_with_two_players_is_split_into_two_sessions(): void
    {
        Storage::fake('local');
        $this->seed(BaseballDictionarySeeder::class);
        $team = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $tom = User::factory()->create(['type' => 'player']);
        $jake = User::factory()->create(['type' => 'player']);
        Profile::factory()->create(['user_id' => $tom->id, 'first_name' => 'Tom', 'last_name' => 'Dimitroff']);
        Profile::factory()->create(['user_id' => $jake->id, 'first_name' => 'Jake', 'last_name' => 'Smith']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        PlayerTeam::factory()->create(['user_id' => $tom->id, 'team_id' => $team->id, 'actual' => true]);
        PlayerTeam::factory()->create(['user_id' => $jake->id, 'team_id' => $team->id, 'actual' => true]);
        Sanctum::actingAs($coach, ['coach']);

        $path = $this->csv([
            ['Player Name', 'Bench Press', 'Back Squat'],
            ['Tom Dimitroff', '185', '275'],
            ['Jake Smith', '165', '245'],
        ]);
        $metadata = new ImportFileMetadata('roster.csv', filesize($path), 'csv', 'text/csv', $path);
        $inspection = app(UniversalSpreadsheetInspector::class)->inspect($metadata);
        $this->assertSame('players_in_rows', $inspection['normalized_inspection']['detected_layout']);
        $this->assertSame(['Tom Dimitroff', 'Jake Smith'], array_column($inspection['players'], 'source_name'));
        $this->assertSame(2, $inspection['counts']['players_found']);

        $this->postJson('/api/data-hub/player-mappings/approve', [
            'team_id' => $team->id, 'platform' => 'generic-csv',
            'mappings' => [
                ['source_key' => $inspection['players'][0]['source_key'], 'source_name' => 'Tom Dimitroff', 'external_player_id' => null, 'roles' => ['player'], 'fmtrx_player_id' => $tom->id, 'not_importing' => false, 'remember_mapping' => false],
                ['source_key' => $inspection['players'][1]['source_key'], 'source_name' => 'Jake Smith', 'external_player_id' => null, 'roles' => ['player'], 'fmtrx_player_id' => $jake->id, 'not_importing' => false, 'remember_mapping' => false],
            ],
        ])->assertOk()->assertJsonPath('data.connected_count', 2);

        $benchPressId = DB::table('baseball_concepts')->where('canonical_key', 'strength.bench_press')->value('id');
        $backSquatId = DB::table('baseball_concepts')->where('canonical_key', 'strength.back_squat')->value('id');
        $this->postJson('/api/data-hub/mappings/approve', [
            'team_id' => $team->id, 'platform' => 'generic-csv', 'template_fingerprint' => $inspection['template_fingerprint'],
            'headers' => array_column($inspection['source_columns'], 'source_column_name'),
            'entries' => [
                ['source_column_name' => 'Bench Press', 'normalized_source_column' => 'benchpress', 'baseball_concept_id' => $benchPressId, 'source_unit_id' => null, 'canonical_unit_id' => null, 'transformation_key' => null, 'resolution_source' => 'manual', 'confidence' => 100, 'required_type' => null, 'action' => 'map', 'compatibility_level' => 'compatible', 'warning_confirmed' => false, 'metadata' => null],
                ['source_column_name' => 'Back Squat', 'normalized_source_column' => 'backsquat', 'baseball_concept_id' => $backSquatId, 'source_unit_id' => null, 'canonical_unit_id' => null, 'transformation_key' => null, 'resolution_source' => 'manual', 'confidence' => 100, 'required_type' => null, 'action' => 'map', 'compatibility_level' => 'compatible', 'warning_confirmed' => false, 'metadata' => null],
            ],
            'destination' => 'strength', 'confirmed_duplicate_concepts' => [], 'remember' => true,
        ])->assertOk()->assertJsonPath('data.approved', true);

        $playerMappings = [
            $inspection['players'][0]['source_key'] => $tom->id,
            $inspection['players'][1]['source_key'] => $jake->id,
        ];
        $payload = fn (): array => [
            'team_id' => $team->id, 'destination' => 'strength', 'template_fingerprint' => $inspection['template_fingerprint'],
            'structure' => json_encode(['header_row' => $inspection['normalized_inspection']['header_row'], 'first_data_row' => $inspection['normalized_inspection']['first_data_row'], 'layout' => 'players_in_rows', 'player_column' => 'Player Name']),
            'player_mappings' => json_encode($playerMappings),
            'file' => UploadedFile::fake()->createWithContent('roster.csv', file_get_contents($path)),
        ];
        $response = $this->post('/api/data-hub/imports/generic', $payload());
        $response->assertCreated()->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.sessions', 2)->assertJsonPath('data.events', 2)->assertJsonPath('data.metrics', 4);

        $this->assertDatabaseCount('translation_snapshots', 1);
        $this->assertDatabaseHas('translation_snapshots', ['team_id' => $team->id, 'player_id' => null]);
        $this->assertDatabaseCount('import_batches', 1);
        $this->assertDatabaseCount('external_sessions', 2);
        $this->assertDatabaseHas('external_sessions', ['player_id' => $tom->id, 'destination' => 'strength']);
        $this->assertDatabaseHas('external_sessions', ['player_id' => $jake->id, 'destination' => 'strength']);
        $this->assertDatabaseCount('canonical_events', 2);
        $this->assertDatabaseCount('canonical_metrics', 4);
        $this->assertDatabaseHas('canonical_metrics', ['baseball_concept_id' => $benchPressId, 'numeric_value' => 185]);
        $this->assertDatabaseHas('canonical_metrics', ['baseball_concept_id' => $benchPressId, 'numeric_value' => 165]);

        $this->post('/api/data-hub/imports/generic', $payload())
            ->assertStatus(409)->assertJsonPath('message', 'This spreadsheet has already been imported for this team.');
        $this->assertDatabaseCount('canonical_events', 2);
    }

    /** @param array<int, array<int, string>> $rows */
    private function csv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtrx-generic-import-');
        $handle = fopen($path, 'wb');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }
}
