<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\Player;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\Dictionary\MappingApprovalService;
use App\Services\DataHub\Dictionary\MappingResolutionService;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Platforms\Rapsodo\RapsodoParser;
use App\Services\DataHub\Services\PlayerMappingApprovalService;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class RapsodoImportPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $coach;
    private User $player;
    private string $path;
    private string $fingerprint;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(BaseballDictionarySeeder::class);
        $this->team = Team::factory()->create(['name' => 'Rapsodo Team']);
        $this->coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $this->player = User::factory()->create(['type' => 'player']);
        Profile::factory()->create(['user_id' => $this->player->id, 'first_name' => 'Live', 'last_name' => 'Pitcher']);
        Player::factory()->create(['user_id' => $this->player->id, 'throw_side' => 'R']);
        CoachTeam::factory()->create(['coach_id' => $this->coach->id, 'team_id' => $this->team->id]);
        PlayerTeam::factory()->create(['user_id' => $this->player->id, 'team_id' => $this->team->id, 'actual' => true]);
        $this->path = base_path('tests/Fixtures/DataHub/platforms/rapsodo/rapsodo_pitching_report_48.xlsx');
        $this->approveMappings();
        Sanctum::actingAs($this->coach, ['coach']);
    }

    public function test_approved_rapsodo_workbook_is_persisted_reportable_and_duplicate_safe(): void
    {
        $response = $this->post('/api/data-hub/imports/rapsodo', $this->payload());
        $response->assertCreated()
            ->assertJsonPath('message', 'Rapsodo data imported successfully.')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.events', 48)
            ->assertJsonPath('data.player_id', $this->player->id);
        $batchId = $response->json('data.batch_id');
        $snapshotId = $response->json('data.snapshot_id');

        $this->assertDatabaseCount('translation_snapshots', 1);
        $this->assertDatabaseCount('import_batches', 1);
        $this->assertDatabaseCount('external_sessions', 1);
        $this->assertDatabaseCount('canonical_events', 48);
        $this->assertGreaterThan(600, DB::table('canonical_metrics')->count());
        $this->assertDatabaseHas('external_sessions', [
            'import_batch_id' => $batchId, 'player_id' => $this->player->id,
            'destination' => 'bullpen', 'occurred_at' => '2021-02-04 18:28:07',
        ]);
        $this->assertDatabaseHas('canonical_metrics', [
            'original_header' => 'velocity', 'original_value' => '80.3', 'numeric_value' => 80.3,
        ]);
        $this->assertDatabaseHas('canonical_metrics', [
            'original_header' => 'spin_direction', 'original_value' => '06h:56m',
        ]);
        Storage::disk('local')->assertExists("data-hub/imports/{$snapshotId}/Rapsodo 2-4-21-.xlsx");

        $this->getJson("/api/data-hub/imports/{$batchId}/rapsodo-report")
            ->assertOk()
            ->assertJsonPath('data.player.id', $this->player->id)
            ->assertJsonPath('data.player.name', 'Live Pitcher')
            ->assertJsonPath('data.session.date', '2021-02-04')
            ->assertJsonPath('data.session.total_pitches', 48)
            ->assertJsonPath('data.summary.average_velocity', 70)
            ->assertJsonPath('data.summary.maximum_velocity', 80.3)
            ->assertJsonPath('data.summary.strike_percentage', 27.1)
            ->assertJsonCount(5, 'data.pitch_types')
            ->assertJsonCount(48, 'data.movement_points');
        $this->getJson('/api/data-hub/imports?team_id='.$this->team->id)
            ->assertOk()->assertJsonPath('data.0.platform', 'Rapsodo')->assertJsonPath('data.0.event_count', 48);

        $this->post('/api/data-hub/imports/rapsodo', $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'This Rapsodo workbook has already been imported for this team.');
        $this->assertDatabaseCount('canonical_events', 48);
    }

    public function test_import_requires_team_scope_current_player_approved_mapping_and_valid_xlsx(): void
    {
        $outsider = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($outsider, ['coach']);
        $this->post('/api/data-hub/imports/rapsodo', $this->payload())->assertForbidden();

        Sanctum::actingAs($this->coach, ['coach']);
        $otherPlayer = User::factory()->create(['type' => 'player']);
        $payload = $this->payload();
        $payload['player_id'] = $otherPlayer->id;
        $this->post('/api/data-hub/imports/rapsodo', $payload)
            ->assertUnprocessable()->assertSeeText('Choose a current player from this team.');

        $payload = $this->payload();
        $payload['template_fingerprint'] = str_repeat('a', 64);
        $this->post('/api/data-hub/imports/rapsodo', $payload)
            ->assertUnprocessable()->assertJsonPath('message', 'The approved Rapsodo mapping is unavailable. Inspect and approve the file again.');

        $payload = $this->payload();
        $payload['file'] = UploadedFile::fake()->create('rapsodo.xlsx', 10, 'text/plain');
        $this->withHeader('Accept', 'application/json')
            ->post('/api/data-hub/imports/rapsodo', $payload)->assertUnprocessable();

        $this->assertDatabaseCount('translation_snapshots', 0);
        $this->assertDatabaseCount('import_batches', 0);
        $this->assertDatabaseCount('external_sessions', 0);
        $this->assertDatabaseCount('canonical_events', 0);
        $this->assertDatabaseCount('canonical_metrics', 0);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'platform' => 'rapsodo', 'team_id' => $this->team->id, 'player_id' => $this->player->id,
            'destination' => 'bullpen', 'template_fingerprint' => $this->fingerprint,
            'file' => UploadedFile::fake()->createWithContent('Rapsodo 2-4-21-.xlsx', file_get_contents($this->path)),
        ];
    }

    private function approveMappings(): void
    {
        $platform = DB::table('platform_definitions')->where('key', 'rapsodo')->first();
        $metadata = new ImportFileMetadata(
            basename($this->path), filesize($this->path), 'xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $this->path
        );
        $headers = app(RapsodoParser::class)->workbook($metadata)['headers'];
        $this->fingerprint = app(TemplateFingerprintService::class)->fingerprint($headers);
        $resolved = app(MappingResolutionService::class)->resolve($this->team->id, $platform->id, $this->fingerprint, $headers);
        $entries = array_map(function (array $entry): array {
            $concept = DB::table('baseball_concepts')->where('id', $entry['concept_id'])->first();

            return [
                'source_column_name' => $entry['source_column_name'],
                'normalized_source_column' => $entry['normalized_source_column'],
                'baseball_concept_id' => $entry['concept_id'],
                'source_unit_id' => $entry['source_unit_key']
                    ? DB::table('unit_definitions')->where('key', $entry['source_unit_key'])->value('id') : null,
                'canonical_unit_id' => $concept->canonical_unit_key
                    ? DB::table('unit_definitions')->where('key', $concept->canonical_unit_key)->value('id') : null,
                'transformation_key' => $entry['transformation_key'] ?? null,
                'resolution_source' => $entry['resolution_source'], 'confidence' => $entry['confidence'],
                'required_type' => null, 'action' => 'map', 'metadata' => null,
            ];
        }, $resolved);
        app(PlayerMappingApprovalService::class)->approve($this->coach, $this->team->id, $platform->id, [[
            'source_key' => 'rapsodo:session:Rapsodo 2-4-21-', 'source_name' => 'Rapsodo Pitching Session',
            'external_player_id' => null, 'roles' => ['pitcher'], 'fmtrx_player_id' => $this->player->id,
            'not_importing' => false, 'remember_mapping' => false,
        ]], []);
        app(MappingApprovalService::class)->approve(
            $this->coach, $this->team->id, $platform->id, $this->fingerprint, $headers, $entries, true
        );
    }
}
