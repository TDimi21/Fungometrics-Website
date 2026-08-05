<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\Player;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Platforms\Rapsodo\RapsodoParser;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class RapsodoPitchingSessionReportTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $coach;
    private User $player;
    private string $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseballDictionarySeeder::class);
        $this->team = Team::factory()->create(['name' => 'Example Team']);
        $this->coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $this->player = User::factory()->create(['type' => 'player', 'subscription_plan' => 'player_pro']);
        Profile::factory()->create(['user_id' => $this->player->id, 'first_name' => 'Mapped', 'last_name' => 'Pitcher']);
        Player::factory()->create(['user_id' => $this->player->id, 'throw_side' => 'R']);
        CoachTeam::factory()->create(['coach_id' => $this->coach->id, 'team_id' => $this->team->id]);
        PlayerTeam::factory()->create(['user_id' => $this->player->id, 'team_id' => $this->team->id, 'actual' => true]);
        $this->batchId = $this->persistFixture($this->player);
    }

    public function test_fixture_summary_pitch_types_and_tilts_are_reported_without_invented_data(): void
    {
        Sanctum::actingAs($this->coach, ['coach']);
        $before = $this->databaseFingerprint();
        $response = $this->getJson("/api/data-hub/imports/{$this->batchId}/rapsodo-report");

        $response->assertOk()
            ->assertJsonPath('data.player.id', $this->player->id)
            ->assertJsonPath('data.player.name', 'Mapped Pitcher')
            ->assertJsonPath('data.player.throws', 'right')
            ->assertJsonPath('data.session.date', '2021-02-04')
            ->assertJsonPath('data.session.total_pitches', 48)
            ->assertJsonPath('data.session.duration_minutes', 48.1)
            ->assertJsonPath('data.summary.pitch_type_count', 5)
            ->assertJsonPath('data.summary.maximum_velocity', 80.3)
            ->assertJsonPath('data.summary.strike_percentage', 27.1)
            ->assertJsonPath('data.availability.pitch_location', false)
            ->assertJsonPath('data.availability.batter_context', false)
            ->assertJsonPath('data.availability.pitch_outcome', false)
            ->assertJsonPath('data.availability.external_benchmark', false)
            ->assertJsonMissing(['email' => $this->player->email]);
        $this->assertEqualsWithDelta(70.0, $response->json('data.summary.average_velocity'), 0.1);
        $this->assertEqualsWithDelta(1492.6, $response->json('data.summary.average_spin_rate'), 0.1);

        $types = collect($response->json('data.pitch_types'))->keyBy('pitch_type');
        $this->assertSame(['FB', '2FB', 'CV', 'SL', 'KN'], $types->keys()->all());
        $expected = [
            'FB' => [12, 25.0, 77.9, 80.3, 1747.0, 1717.0, 98.3, 12.1, 15.5, 25.0, 5.70, 3.02, '1:16'],
            '2FB' => [8, 16.7, 75.1, 79.9, 1642.0, 1586.0, 96.5, 15.4, 11.8, 37.5, 5.50, 3.11, '1:46'],
            'CV' => [10, 20.8, 68.1, 70.5, 2153.0, 1091.0, 50.6, -8.0, -9.3, 40.0, 5.80, 2.80, '7:11'],
            'SL' => [7, 14.6, 67.6, 70.8, 2017.0, 472.0, 24.0, -6.7, 0.1, 14.3, 5.46, 3.00, '8:59'],
            'KN' => [11, 22.9, 60.8, 63.3, 172.0, 114.0, 69.0, -0.2, -1.5, 18.2, 5.27, 2.84, '5:52'],
        ];
        foreach ($expected as $key => $values) {
            $row = $types[$key];
            foreach (['count', 'usage_percentage', 'average_velocity', 'maximum_velocity', 'average_spin_rate', 'average_true_spin', 'average_spin_efficiency', 'average_horizontal_break', 'average_vertical_break', 'strike_percentage', 'average_release_height', 'average_release_side', 'average_tilt'] as $index => $field) {
                if (is_float($values[$index])) {
                    $this->assertEqualsWithDelta($values[$index], $row[$field], 0.11, "{$key} {$field}");
                } else {
                    $this->assertSame($values[$index], $row[$field], "{$key} {$field}");
                }
            }
        }
        $this->assertCount(48, $response->json('data.movement_points'));
        $this->assertCount(48, $response->json('data.release_points'));
        $this->assertCount(3, $response->json('data.insights'));
        $this->assertSame($before, $this->databaseFingerprint(), 'Viewing the report must not mutate persisted data.');
    }

    public function test_player_self_access_coach_scope_and_report_index_are_enforced(): void
    {
        Sanctum::actingAs($this->player, ['player']);
        $this->getJson("/api/data-hub/imports/{$this->batchId}/rapsodo-report")->assertOk();
        $this->getJson('/api/data-hub/rapsodo-reports')->assertOk()
            ->assertJsonPath('data.0.id', $this->batchId)
            ->assertJsonPath('data.0.report_path', "/player/reports/rapsodo/{$this->batchId}?player_id={$this->player->id}");

        $outsider = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($outsider, ['coach']);
        $this->getJson("/api/data-hub/imports/{$this->batchId}/rapsodo-report")
            ->assertForbidden()->assertJsonPath('code', 'unauthorized');
        $this->getJson('/api/data-hub/rapsodo-reports')->assertOk()->assertJsonCount(0, 'data');

        $administrator = User::factory()->create(['type' => 'admin']);
        Sanctum::actingAs($administrator, ['admin']);
        $this->getJson("/api/data-hub/imports/{$this->batchId}/rapsodo-report")->assertOk();
        $this->getJson('/api/data-hub/rapsodo-reports')->assertOk()->assertJsonPath('data.0.id', $this->batchId);
    }

    public function test_genuine_zero_is_preserved_while_blank_metrics_remain_unavailable(): void
    {
        $sessionId = DB::table('external_sessions')->where('import_batch_id', $this->batchId)->value('id');
        $eventId = (string) Str::uuid();
        DB::table('canonical_events')->insert([
            'id' => $eventId, 'external_session_id' => $sessionId, 'player_id' => $this->player->id,
            'event_type' => 'pitch_sensor_event', 'event_order' => 49, 'occurred_at' => '2021-02-04 19:17:00',
            'source_row' => 50, 'source_record_key' => hash('sha256', "{$this->batchId}:zero"),
            'source_context' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([
            ['pitching.tagged_pitch_type', 'ZZ', null, 'pitch_type'],
            ['pitching.horizontal_break', '0', 0.0, 'horz_break'],
        ] as [$canonical, $value, $number, $header]) {
            $concept = DB::table('baseball_concepts')->where('canonical_key', $canonical)->first();
            DB::table('canonical_metrics')->insert([
                'id' => (string) Str::uuid(), 'canonical_event_id' => $eventId, 'baseball_concept_id' => $concept->id,
                'value' => $value, 'numeric_value' => $number, 'canonical_unit_key' => $concept->canonical_unit_key,
                'original_value' => $value, 'original_unit_key' => $concept->canonical_unit_key,
                'original_header' => $header, 'measurement_classification' => 'source_measurement',
                'provenance' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($this->coach, ['coach']);
        $response = $this->getJson("/api/data-hub/imports/{$this->batchId}/rapsodo-report")->assertOk();
        $unknown = collect($response->json('data.pitch_types'))->firstWhere('pitch_type', 'ZZ');
        $this->assertSame(0, $unknown['average_horizontal_break']);
        $this->assertNull($unknown['average_vertical_break']);
        $this->assertNull($unknown['strike_percentage']);
        $this->assertCount(48, $response->json('data.movement_points'), 'A blank vertical-break value must not become a fabricated zero coordinate.');
    }

    public function test_stable_errors_cover_platform_status_mapping_selection_and_empty_pitch_data(): void
    {
        Sanctum::actingAs($this->coach, ['coach']);

        $wrong = $this->emptyBatch('blast-motion', 'completed');
        $this->getJson("/api/data-hub/imports/{$wrong}/rapsodo-report")->assertUnprocessable()->assertJsonPath('code', 'wrong_platform');

        $unfinished = $this->emptyBatch('rapsodo', 'processing');
        $this->getJson("/api/data-hub/imports/{$unfinished}/rapsodo-report")->assertUnprocessable()->assertJsonPath('code', 'import_not_completed');

        $unmapped = $this->emptyBatch('rapsodo', 'completed');
        $this->getJson("/api/data-hub/imports/{$unmapped}/rapsodo-report")->assertUnprocessable()->assertJsonPath('code', 'player_mapping_required');

        $second = User::factory()->create(['type' => 'player']);
        Profile::factory()->create(['user_id' => $second->id]);
        PlayerTeam::factory()->create(['user_id' => $second->id, 'team_id' => $this->team->id, 'actual' => true]);
        $platformId = DB::table('platform_definitions')->where('key', 'rapsodo')->value('id');
        DB::table('external_sessions')->insert([
            'id' => (string) Str::uuid(), 'import_batch_id' => $this->batchId, 'team_id' => $this->team->id,
            'player_id' => $second->id, 'platform_definition_id' => $platformId, 'destination' => 'bullpen',
            'label' => 'Rapsodo Pitching Session', 'occurred_at' => '2021-02-04 18:28:07', 'metadata' => '{}',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->getJson("/api/data-hub/imports/{$this->batchId}/rapsodo-report")
            ->assertUnprocessable()->assertJsonPath('code', 'player_selection_required');

        Sanctum::actingAs($this->player, ['player']);
        $this->getJson("/api/data-hub/imports/{$this->batchId}/rapsodo-report?player_id={$second->id}")
            ->assertForbidden()->assertJsonPath('code', 'unauthorized');

        Sanctum::actingAs($this->coach, ['coach']);
        $this->getJson("/api/data-hub/imports/{$this->batchId}/rapsodo-report?player_id={$this->player->id}")->assertOk();

        $notMapped = User::factory()->create(['type' => 'player']);
        $this->getJson("/api/data-hub/imports/{$this->batchId}/rapsodo-report?player_id={$notMapped->id}")
            ->assertUnprocessable()->assertJsonPath('code', 'player_not_in_batch');
    }

    private function persistFixture(User $player): string
    {
        $batchId = $this->emptyBatch('rapsodo', 'completed', $player->id);
        $platformId = DB::table('platform_definitions')->where('key', 'rapsodo')->value('id');
        $sessionId = (string) Str::uuid();
        DB::table('external_sessions')->insert([
            'id' => $sessionId, 'import_batch_id' => $batchId, 'team_id' => $this->team->id,
            'player_id' => $player->id, 'platform_definition_id' => $platformId, 'destination' => 'bullpen',
            'label' => 'Rapsodo Pitching Session', 'occurred_at' => '2021-02-04 18:28:07',
            'metadata' => json_encode(['worksheet' => 'Rapsodo 2-4-21-']), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $path = base_path('tests/Fixtures/DataHub/platforms/rapsodo/rapsodo_pitching_report_48.xlsx');
        $file = new ImportFileMetadata(basename($path), filesize($path), 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $path);
        $rows = iterator_to_array(app(RapsodoParser::class)->parse($file), false);
        $metricMap = [
            'pitch_type' => 'pitching.tagged_pitch_type', 'pitch_velocity_mph' => 'pitching.release_velocity',
            'total_spin_rate_rpm' => 'pitching.spin_rate', 'true_spin_rate_rpm' => 'pitching.true_spin_rate',
            'spin_efficiency_percent' => 'pitching.spin_efficiency', 'spin_direction_clock' => 'pitching.spin_direction_clock',
            'horizontal_break_in' => 'pitching.horizontal_break', 'vertical_break_in' => 'pitching.vertical_break',
            'strike' => 'pitching.strike_result', 'release_height_ft' => 'pitching.release_height',
            'release_side_ft' => 'pitching.release_side',
        ];
        $metricCount = 0;
        foreach ($rows as $index => $row) {
            $eventId = (string) Str::uuid();
            DB::table('canonical_events')->insert([
                'id' => $eventId, 'external_session_id' => $sessionId, 'player_id' => $player->id,
                'event_type' => 'pitch_sensor_event', 'event_order' => $index + 1,
                'occurred_at' => '2021-02-04 '.$row['event_time_display'], 'source_row' => $row['_source_row'],
                'source_record_key' => hash('sha256', "{$batchId}:{$row['_source_row']}"),
                'source_context' => json_encode(['pitch_type' => $row['pitch_type'], 'spin_direction' => $row['spin_direction_clock'], 'strike' => $row['strike']]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($metricMap as $field => $canonical) {
                $value = $row[$field] ?? null;
                if (null === $value || '' === trim((string) $value)) {
                    continue;
                }
                $concept = DB::table('baseball_concepts')->where('canonical_key', $canonical)->first();
                DB::table('canonical_metrics')->insert([
                    'id' => (string) Str::uuid(), 'canonical_event_id' => $eventId, 'baseball_concept_id' => $concept->id,
                    'value' => (string) $value, 'numeric_value' => is_numeric($value) ? (float) $value : null,
                    'canonical_unit_key' => $concept->canonical_unit_key, 'original_value' => (string) $value,
                    'original_unit_key' => $concept->canonical_unit_key, 'original_header' => $field,
                    'measurement_classification' => 'source_measurement',
                    'provenance' => json_encode(['batch_id' => $batchId, 'platform' => 'rapsodo', 'source_row' => $row['_source_row']]),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                ++$metricCount;
            }
        }
        DB::table('import_batches')->where('id', $batchId)->update([
            'session_count' => 1, 'event_count' => count($rows), 'metric_count' => $metricCount,
            'completed_at' => '2021-02-04 19:16:13', 'updated_at' => now(),
        ]);

        return $batchId;
    }

    private function emptyBatch(string $platformKey, string $status, ?string $playerId = null): string
    {
        $platformId = DB::table('platform_definitions')->where('key', $platformKey)->value('id');
        $templateId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        DB::table('mapping_templates')->insert([
            'id' => $templateId, 'team_id' => $this->team->id, 'platform_definition_id' => $platformId,
            'template_fingerprint' => hash('sha256', $templateId), 'name' => 'Report fixture',
            'current_version_id' => null, 'is_active' => true, 'created_by' => $this->coach->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('mapping_template_versions')->insert([
            'id' => $versionId, 'mapping_template_id' => $templateId, 'version' => 1,
            'header_fingerprint' => hash('sha256', $versionId), 'headers' => '[]', 'status' => 'approved',
            'approved_by' => $this->coach->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('mapping_templates')->where('id', $templateId)->update(['current_version_id' => $versionId]);
        $snapshotId = (string) Str::uuid();
        DB::table('translation_snapshots')->insert([
            'id' => $snapshotId, 'team_id' => $this->team->id, 'platform_definition_id' => $platformId,
            'mapping_template_version_id' => $versionId, 'approved_by' => $this->coach->id,
            'player_id' => $playerId ?? $this->player->id, 'destination' => 'bullpen',
            'source_file_name' => 'source-without-player-identity.xlsx', 'source_file_checksum' => hash('sha256', $snapshotId),
            'source_storage_key' => "data-hub/imports/{$snapshotId}/source.xlsx", 'source_file_size' => 1,
            'snapshot' => json_encode(['approved_player_id' => $playerId]), 'approved_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $batchId = (string) Str::uuid();
        DB::table('import_batches')->insert([
            'id' => $batchId, 'translation_snapshot_id' => $snapshotId, 'initiated_by' => $this->coach->id,
            'status' => $status, 'started_at' => now(), 'completed_at' => 'completed' === $status ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $batchId;
    }

    /** @return array<string, int> */
    private function databaseFingerprint(): array
    {
        return collect(['import_batches', 'translation_snapshots', 'external_sessions', 'canonical_events', 'canonical_metrics', 'users', 'teams', 'subscriptions'])
            ->filter(fn (string $table): bool => DB::getSchemaBuilder()->hasTable($table))
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();
    }
}
