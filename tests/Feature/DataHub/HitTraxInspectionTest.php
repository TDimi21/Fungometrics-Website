<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\PlatformDefinition;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Dictionary\MappingResolutionService;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\Platforms\HitTrax\HitTraxInspectionService;
use App\Services\DataHub\Platforms\HitTrax\HitTraxParser;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class HitTraxInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseballDictionarySeeder::class);
    }

    public function test_parser_trims_headers_and_keeps_pitch_and_velo_distinct(): void
    {
        $rows = iterator_to_array(app(HitTraxParser::class)->parse($this->metadata(
            "#, AB, Date, Pitch, Velo, LA, Dist, User\n1, 1, 12/10/2020 13:31:49.763, 30.6, 84.4, 8, 143, Sammy Eisenberg\n"
        )), false);

        $this->assertSame('30.6', $rows[0]['inbound_pitch_velocity_mph']);
        $this->assertSame('84.4', $rows[0]['exit_velocity_mph']);
        $this->assertSame('Sammy Eisenberg', $rows[0]['user']);
    }

    public function test_inspection_counts_tracked_contact_and_defaults_unavailable_sensor_fields(): void
    {
        $team = Team::factory()->create();
        $result = app(HitTraxInspectionService::class)->inspect($this->metadata(
            "#, AB, Date, Pitch, Velo, LA, Dist, Hand Speed, BV, Trigger to Impact, AA, Impact Momentum, User, Opposing Player\n"
            ."1, 1, 12/10/2020 13:31:49.763, 30.6, 0, , , 0, 0, 0, 0, 0, Sammy Eisenberg, \n"
            ."2, 1, 12/10/2020 13:31:56.286, 33.1, 84.4, 8, 143, 0, 0, 0, 0, 0, Sammy Eisenberg, \n"
        ), (string) $team->id);

        $this->assertSame(2, $result['counts']['total_rows']);
        $this->assertSame(1, $result['counts']['tracked_batted_balls']);
        $this->assertSame(1, $result['counts']['players_found']);
        $this->assertSame(2, $result['players'][0]['row_count']);
        $this->assertSame(1, $result['players'][0]['tracked_batted_ball_count']);
        $handSpeed = collect($result['source_columns'])->firstWhere('source_column_name', 'Hand Speed');
        $this->assertTrue($handSpeed['default_not_importing']);
        $this->assertNull($result['sample_rows'][0]['metrics']['exit_velocity_mph'] ?? null);
        $this->assertSame(['Batting Practice', 'Cage'], $result['destination_recommendation']['recommended']);
    }

    public function test_hittrax_aliases_resolve_to_platform_specific_concepts(): void
    {
        $platform = PlatformDefinition::query()->where('key', 'hittrax')->firstOrFail();
        $headers = ['Velo', 'Pitch', 'Dist', 'Res', 'Vertical Distance', 'User'];
        $resolved = app(MappingResolutionService::class)->resolve(
            'unused-team',
            $platform->id,
            app(TemplateFingerprintService::class)->fingerprint($headers),
            $headers
        );
        $keys = collect($resolved)->mapWithKeys(fn (array $entry): array => [
            $entry['source_column_name'] => DB::table('baseball_concepts')
                ->where('id', $entry['concept_id'])
                ->value('canonical_key'),
        ]);

        $this->assertSame('hitting.exit_velocity', $keys['Velo']);
        $this->assertSame('hitting.inbound_pitch_velocity', $keys['Pitch']);
        $this->assertSame('hitting.projected_distance', $keys['Dist']);
        $this->assertSame('game_outcome.simulated_play_result', $keys['Res']);
        $this->assertSame('pitching.location_vertical_distance', $keys['Vertical Distance']);
        $this->assertSame('session_context.player_identity', $keys['User']);
    }

    public function test_protected_inspection_endpoint_accepts_hittrax_and_deletes_temporary_file(): void
    {
        Storage::fake('local');
        $team = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        Sanctum::actingAs($coach, ['coach']);

        $this->post('/api/data-hub/inspect', [
            'platform' => 'hittrax',
            'team_id' => $team->id,
            'session_type' => 'batting_practice',
            'file' => UploadedFile::fake()->createWithContent(
                'hittrax.csv',
                "#, AB, Date, Pitch, Velo, LA, Dist, User\n1, 1, 12/10/2020 13:31:49.763, 30.6, 84.4, 8, 143, Sammy Eisenberg\n"
            ),
        ])->assertOk()
            ->assertJsonPath('data.platform', 'hittrax')
            ->assertJsonPath('data.destination_recommendation.selected', 'Batting Practice');

        Storage::disk('local')->assertDirectoryEmpty('data-hub/tmp');
    }

    private function metadata(string $contents): ImportFileMetadata
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtrx-hittrax-');
        file_put_contents($path, $contents);

        return new ImportFileMetadata('hittrax.csv', mb_strlen($contents), 'csv', 'text/csv', $path);
    }
}
