<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\Dictionary\MappingApprovalService;
use App\Services\DataHub\Dictionary\MappingResolutionService;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class BlastImportPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_blast_csv_is_persisted_readable_and_duplicate_safe(): void
    {
        Storage::fake('local');
        $this->seed(BaseballDictionarySeeder::class);
        $team = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $player = User::factory()->create(['type' => 'player']);
        Profile::factory()->create(['user_id' => $player->id, 'first_name' => 'Blast', 'last_name' => 'Player']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);
        Sanctum::actingAs($coach, ['coach']);

        $path = base_path('tests/Fixtures/DataHub/platforms/blast/blast_motion_sanitized.csv');
        $headers = $this->headers($path);
        $fingerprint = app(TemplateFingerprintService::class)->fingerprint($headers);
        $platform = DB::table('platform_definitions')->where('key', 'blast-motion')->first();
        $resolved = app(MappingResolutionService::class)->resolve($team->id, $platform->id, $fingerprint, $headers);
        $entries = array_map(fn (array $entry): array => [
            'source_column_name' => $entry['source_column_name'],
            'normalized_source_column' => $entry['normalized_source_column'],
            'baseball_concept_id' => $entry['concept_id'],
            'source_unit_id' => null, 'canonical_unit_id' => null,
            'transformation_key' => $entry['transformation_key'] ?? null,
            'resolution_source' => $entry['resolution_source'], 'confidence' => $entry['confidence'],
            'required_type' => null, 'action' => $entry['concept_id'] ? 'map' : 'ignore', 'metadata' => null,
        ], $resolved);
        app(MappingApprovalService::class)->approve($coach, $team->id, $platform->id, $fingerprint, $headers, $entries, true);

        $payload = fn (): array => [
            'platform' => 'blast-motion', 'team_id' => $team->id, 'player_id' => $player->id,
            'destination' => 'batting_practice', 'template_fingerprint' => $fingerprint,
            'file' => UploadedFile::fake()->createWithContent('blast.csv', file_get_contents($path)),
        ];
        $response = $this->post('/api/data-hub/imports/blast', $payload());
        $response->assertCreated()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.events', 1);
        $batchId = $response->json('data.batch_id');
        $this->assertDatabaseCount('translation_snapshots', 1);
        $this->assertDatabaseCount('import_batches', 1);
        $this->assertDatabaseCount('external_sessions', 1);
        $this->assertDatabaseCount('canonical_events', 1);
        $this->assertGreaterThan(10, DB::table('canonical_metrics')->count());
        $this->assertDatabaseHas('canonical_metrics', ['original_header' => 'Bat Speed (mph)', 'numeric_value' => 78.1]);

        $this->getJson("/api/data-hub/players/{$player->id}/metrics")
            ->assertOk()->assertJsonFragment(['canonical_key' => 'hitting.bat_speed', 'display_name' => 'Bat Speed']);
        $this->getJson('/api/data-hub/imports?team_id='.$team->id)
            ->assertOk()->assertJsonPath('data.0.status', 'completed')->assertJsonPath('data.0.event_count', 1);

        $beforeReport = [DB::table('translation_snapshots')->count(), DB::table('canonical_events')->count(), DB::table('canonical_metrics')->count()];
        $report = $this->getJson("/api/data-hub/imports/{$batchId}/blast-report?benchmark_level=high_school_varsity");
        $report->assertOk()->assertJsonPath('data.session.total_swings', 1)->assertJsonPath('data.ball_flight_available', false);
        $batSpeed = collect($report->json('data.metrics'))->firstWhere('key', 'bat_speed');
        $this->assertSame(78.1, $batSpeed['average']);
        $this->assertSame(78.1, $batSpeed['best_swing']);
        $this->assertSame('above_benchmark', $batSpeed['benchmark']['status']);
        foreach (['exit_velocity', 'launch_angle', 'estimated_distance'] as $key) {
            $metric = collect($report->json('data.metrics'))->firstWhere('key', $key);
            $this->assertFalse($metric['available']);
            $this->assertNull($metric['average']);
        }
        $this->assertSame($beforeReport, [DB::table('translation_snapshots')->count(), DB::table('canonical_events')->count(), DB::table('canonical_metrics')->count()]);
        $this->getJson("/api/data-hub/imports/{$batchId}/blast-report?benchmark_level=made_up")->assertUnprocessable();

        $outsider = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($outsider, ['coach']);
        $this->getJson("/api/data-hub/imports/{$batchId}/blast-report?benchmark_level=high_school_varsity")->assertForbidden();
        Sanctum::actingAs($coach, ['coach']);

        $this->post('/api/data-hub/imports/blast', $payload())
            ->assertStatus(409)->assertJsonPath('message', 'This Blast CSV has already been imported for this team.');
        $this->assertDatabaseCount('canonical_events', 1);
    }

    /** @return array<int, string> */
    private function headers(string $path): array
    {
        $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES));
        return $rows[7];
    }
}
