<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Platforms\TrackMan\TrackManInspectionService;
use App\Services\DataHub\Platforms\TrackMan\TrackManNormalizer;
use App\Services\DataHub\Platforms\TrackMan\TrackManParser;
use App\Services\DataHub\Services\PlayerMatchingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class TrackManInspectionTest extends TestCase
{
    public function test_hitting_csv_aliases_are_detected_and_normalized_without_raw_rows(): void
    {
        $team = Team::factory()->create();
        $result = app(TrackManInspectionService::class)->inspect(
            $this->metadata("Batter Name,GameDate,Venue,PitchUID,ExitVelocity,Launch Angle,Spray Angle,CarryDistance,Hang Time\nTom Smith,2026-07-24,North Oconee,p-1,92.4,24.1,-7.2,347.8,4.12\n"),
            (string) $team->id,
            'cage'
        );

        $this->assertSame('hitting', $result['detected_format']['data_type']);
        $this->assertSame(1, $result['counts']['total_rows']);
        $this->assertSame(['Tom Smith'], array_column($result['players'], 'external_name'));
        $this->assertSame(92.4, $result['sample_rows'][0]['metrics']['exit_velocity_mph']);
        $this->assertArrayNotHasKey('original_fields', $result['sample_rows'][0]['source']);
    }

    public function test_pitching_and_mixed_formats_are_detected(): void
    {
        $parser = app(TrackManParser::class);
        $pitching = iterator_to_array($parser->parse($this->metadata("Pitcher,Date,RelSpeed,SpinRate,InducedVertBreak\nJane Doe,2026-07-24,91.8,2380,17.2\n")), false);
        $mixed = iterator_to_array($parser->parse($this->metadata("Batter,Pitcher,Date,ExitSpeed,Angle,RelSpeed,SpinRate\nTom Smith,Jane Doe,2026-07-24,92,24,91,2300\n")), false);

        $this->assertSame('pitching', $pitching[0]['_data_type']);
        $this->assertSame('mixed', $mixed[0]['_data_type']);
    }

    public function test_unsupported_csv_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        iterator_to_array(app(TrackManParser::class)->parse($this->metadata("Name,Score\nNobody,10\n")));
    }

    public function test_invalid_numeric_values_create_preview_warnings(): void
    {
        $rows = iterator_to_array(app(TrackManParser::class)->parse($this->metadata("Batter,ExitSpeed,Angle\nTom Smith,bad,20\n")), false);
        $normalized = app(TrackManNormalizer::class)->normalize($rows)->records;
        $this->assertFalse($normalized[0]['validation']['valid']);
        $this->assertStringContainsString('not numeric', $normalized[0]['validation']['warnings'][0]);
    }

    public function test_player_matching_is_team_scoped_and_fuzzy_is_suggestion_only(): void
    {
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        $tom = $this->player($team, 'Thomas', 'Smith');
        $this->player($other, 'Tom', 'Smith');

        $matches = app(PlayerMatchingService::class)->suggestions((string) $team->id, 'Tom Smith');
        $this->assertSame((string) $tom->id, $matches[0]['player_id']);
        $this->assertSame('normalized', $matches[0]['match_type']);
        $this->assertCount(1, $matches);
    }

    public function test_inspection_endpoint_enforces_auth_entitlement_team_scope_and_deletes_temp_file(): void
    {
        Storage::fake('local');
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $payload = ['platform' => 'trackman', 'team_id' => $team->id, 'session_type' => 'cage'];

        $this->postJson('/api/data-hub/inspect', $payload)->assertUnauthorized();
        Sanctum::actingAs($coach, ['coach']);
        $this->post('/api/data-hub/inspect', $payload + ['file' => UploadedFile::fake()->createWithContent('trackman.csv', "Batter,ExitSpeed,Angle\nTom Smith,92,20\n")])
            ->assertOk()->assertJsonPath('data.detected_format.data_type', 'hitting');
        Storage::disk('local')->assertDirectoryEmpty('data-hub/tmp');
        $this->post('/api/data-hub/inspect', ['platform' => 'trackman', 'team_id' => $other->id, 'session_type' => 'cage', 'file' => UploadedFile::fake()->createWithContent('trackman.csv', "Batter,ExitSpeed,Angle\nTom Smith,92,20\n")])
            ->assertForbidden();
    }

    private function metadata(string $contents): ImportFileMetadata
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtrx-trackman-');
        file_put_contents($path, $contents);

        return new ImportFileMetadata('trackman.csv', mb_strlen($contents), 'csv', 'text/csv', $path);
    }

    private function player(Team $team, string $first, string $last): User
    {
        $user = User::factory()->create(['type' => 'player']);
        Profile::factory()->create(['user_id' => $user->id, 'first_name' => $first, 'last_name' => $last]);
        PlayerTeam::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

        return $user;
    }
}
