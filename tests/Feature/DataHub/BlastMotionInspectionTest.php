<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\PlatformDefinition;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\Dictionary\MappingResolutionService;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Platforms\BlastMotion\BlastMotionInspectionService;
use App\Services\DataHub\Platforms\BlastMotion\BlastMotionParser;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class BlastMotionInspectionTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'Date', 'Equipment', 'Handedness', 'Swing Details', 'Plane Score', 'Connection Score',
        'Rotation Score', 'Bat Speed (mph)', 'Rotational Acceleration (g)', 'On Plane Efficiency (%)',
        'Attack Angle (deg)', 'Early Connection (deg)', 'Connection at Impact (deg)',
        'Vertical Bat Angle (deg)', 'Power (kW)', 'Time to Contact (sec)',
        'Peak Hand Speed (mph)', 'Exit Velocity (mph)', 'Launch Angle (deg)',
        'Estimated Distance (feet)',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseballDictionarySeeder::class);
    }

    public function test_detects_row_eight_metadata_twenty_headers_and_forty_eight_swings(): void
    {
        $file = $this->metadata($this->csv(48));
        $report = app(BlastMotionParser::class)->report($file);
        $result = app(BlastMotionInspectionService::class)->inspect($file);

        $this->assertSame(8, $report['header_row']);
        $this->assertSame(20, $report['header_count']);
        $this->assertSame(48, count($report['source_rows']));
        $this->assertSame(1, $report['blank_row_count']);
        $this->assertSame('Blast Baseball Academy', $report['metadata']['Academy']);
        $this->assertArrayNotHasKey('E-mail', $report['metadata']);
        $this->assertSame(100, $report['detection_confidence']);
        $this->assertSame('Baseball Swing Sensor Session', $result['detected_format']['display_type']);
        $this->assertSame(['Batting Practice'], $result['destination_recommendation']['recommended']);
    }

    public function test_session_assignment_preserves_controlled_and_zero_values(): void
    {
        $result = app(BlastMotionInspectionService::class)->inspect($this->metadata($this->csv(1, '0')));
        $player = $result['players'][0];
        $sample = $result['sample_rows'][0];

        $this->assertTrue($player['identity_missing']);
        $this->assertFalse($player['remember_mapping']);
        $this->assertSame('Blast Motion Swing Session', $player['source_name']);
        $this->assertSame('Right', $sample['controlled_values']['handedness']['canonical_preview']);
        $this->assertSame('HYPERWHIP ADULT BBCOR', $sample['controlled_values']['equipment']['raw']);
        $this->assertSame('General Practice', $sample['controlled_values']['swing_details']['raw']);
        $this->assertSame(0.0, $sample['metrics']['attack_angle_deg']);
        $this->assertArrayNotHasKey('player', $report = $result['report']);
        $this->assertFalse($report['has_player_identity']);
    }

    public function test_aliases_units_separation_and_idempotency_are_exact(): void
    {
        $before = [
            'domains' => DB::table('baseball_domains')->count(),
            'concepts' => DB::table('baseball_concepts')->count(),
            'aliases' => DB::table('baseball_concept_aliases')->count(),
            'units' => DB::table('unit_definitions')->count(),
            'conversions' => DB::table('unit_conversions')->count(),
        ];
        $this->assertSame(['domains' => 14, 'concepts' => 114, 'aliases' => 127, 'units' => 19, 'conversions' => 8], $before);
        $this->seed(BaseballDictionarySeeder::class);
        $this->assertSame($before['concepts'], DB::table('baseball_concepts')->count());
        $this->assertSame($before['aliases'], DB::table('baseball_concept_aliases')->count());

        $platform = PlatformDefinition::query()->where('key', 'blast-motion')->firstOrFail();
        $resolved = app(MappingResolutionService::class)->resolve(
            'unused-team',
            $platform->id,
            app(TemplateFingerprintService::class)->fingerprint(self::HEADERS),
            self::HEADERS
        );
        $keys = collect($resolved)->mapWithKeys(fn (array $entry): array => [
            $entry['source_column_name'] => DB::table('baseball_concepts')->where('id', $entry['concept_id'])->value('canonical_key'),
        ]);
        $this->assertCount(20, $keys);
        $this->assertSame('hitting.bat_speed', $keys['Bat Speed (mph)']);
        $this->assertSame('hitting.exit_velocity', $keys['Exit Velocity (mph)']);
        $this->assertSame('hitting.peak_hand_speed', $keys['Peak Hand Speed (mph)']);
        $this->assertSame('hitting.attack_angle', $keys['Attack Angle (deg)']);
        $this->assertSame('hitting.launch_angle', $keys['Launch Angle (deg)']);
        $this->assertSame('hitting.blast_plane_score', $keys['Plane Score']);
        $this->assertSame('hitting.on_plane_efficiency', $keys['On Plane Efficiency (%)']);
        $this->assertSame('hitting.projected_distance', $keys['Estimated Distance (feet)']);
    }

    public function test_blank_ball_flight_columns_default_to_not_importing(): void
    {
        $result = app(BlastMotionInspectionService::class)->inspect($this->metadata($this->csv(2)));
        $columns = collect($result['source_columns'])->keyBy('source_column_name');
        foreach (['Exit Velocity (mph)', 'Launch Angle (deg)', 'Estimated Distance (feet)'] as $header) {
            $this->assertTrue($columns[$header]['default_not_importing']);
            $this->assertSame(0, $columns[$header]['details']['populated_count']);
            $this->assertNotEmpty($columns[$header]['warnings']);
        }
        $this->assertSame('g_force', $columns['Rotational Acceleration (g)']['suggested_source_unit_key']);
        $this->assertSame('kw', $columns['Power (kW)']['suggested_source_unit_key']);
        $this->assertTrue($columns['Plane Score']['source_specific']);
    }

    public function test_endpoint_is_entitled_team_scoped_cleans_up_and_writes_nothing(): void
    {
        Storage::fake('local');
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $contents = file_get_contents($this->csv(1));
        $before = [
            'practices' => DB::table('practices')->count(),
            'batting' => DB::table('batting_practice_results')->count(),
            'profiles' => DB::table('profiles')->count(),
        ];

        $this->post('/api/data-hub/inspect')->assertUnauthorized();
        Sanctum::actingAs($coach, ['coach']);
        $payload = fn (string $teamId): array => [
            'platform' => 'blast-motion', 'team_id' => $teamId, 'session_type' => 'batting_practice',
            'file' => UploadedFile::fake()->createWithContent('blast.csv', $contents),
        ];
        $this->post('/api/data-hub/inspect', $payload($team->id))->assertOk()
            ->assertJsonPath('data.platform', 'blast-motion')
            ->assertJsonPath('data.detected_format.header_row', 8)
            ->assertJsonPath('data.counts.populated_swing_rows', 1);
        $this->post('/api/data-hub/inspect', $payload($other->id))->assertForbidden();

        Storage::disk('local')->assertDirectoryEmpty('data-hub/tmp');
        $this->assertSame($before['practices'], DB::table('practices')->count());
        $this->assertSame($before['batting'], DB::table('batting_practice_results')->count());
        $this->assertSame($before['profiles'], DB::table('profiles')->count());
    }

    private function metadata(string $path): ImportFileMetadata
    {
        return new ImportFileMetadata('blast.csv', filesize($path), 'csv', 'text/csv', $path);
    }

    private function csv(int $rows, string $attackAngle = '12'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtrx-blast-');
        $handle = fopen($path, 'wb');
        fputcsv($handle, ['© Blast Motion. All rights reserved.']);
        fputcsv($handle, ['NOTE: terms']);
        fputcsv($handle, ['E-mail:', 'private@example.com']);
        fputcsv($handle, ['Academy:', 'Blast Baseball Academy']);
        fputcsv($handle, ['Report Date:', '12/15/2020 03:23 PM']);
        fputcsv($handle, ['Date Range:', '12/01/2020 - 12/15/2020']);
        fputcsv($handle, []);
        fputcsv($handle, self::HEADERS);
        for ($index = 0; $index < $rows; ++$index) {
            fputcsv($handle, [
                'December 15, 2020 / 2:30:05 PM', 'HYPERWHIP ADULT BBCOR', 'Right',
                'General Practice', '46', '44', '49', '78.1', '9.5', '61', $attackAngle,
                '116', '96', '-9', '5.66', '0.14', '21.9', '', '', '',
            ]);
        }
        fclose($handle);

        return $path;
    }
}
