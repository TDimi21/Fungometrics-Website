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
use App\Services\DataHub\Platforms\Rapsodo\RapsodoInspectionService;
use App\Services\DataHub\Platforms\Rapsodo\RapsodoParser;
use App\Services\DataHub\Support\SecureXlsxReader;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

final class RapsodoInspectionTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['no', 'time', 'pitch_type', 'velocity', 'spin_rate', 'true_spin', 'spin_eff', 'spin_direction', 'horz_break', 'vert_break', 'strike', 'rel_ht', 'rel_side', 'r_angle', 'h_angle', 'gyro'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseballDictionarySeeder::class);
    }

    public function test_detects_exact_worksheet_header_and_populated_rows(): void
    {
        $file = $this->metadata($this->workbook([
            ['1', '0.7695254629', 'FB', '78', '1622', '1598', '98.6', '01h:26m', '13.1', '13.9', 'Y', '5.9', '2.7', '-1.5', '-3.9', '-9.7'],
            [],
            ['2', '0.7696643518', 'CV', '66.5', '2159', '1258', '58.3', '07h:02m', '-8.4', '-11.5', 'N', '6', '2.6', '2.1', '-2', '54.4'],
        ]));
        $workbook = app(RapsodoParser::class)->workbook($file);
        $result = app(RapsodoInspectionService::class)->inspect($file);

        $this->assertSame(['Rapsodo 2-4-21-', 'Sheet1'], $workbook['worksheet_names']);
        $this->assertSame('Rapsodo 2-4-21-', $workbook['worksheet']);
        $this->assertSame(1, $workbook['header_row']);
        $this->assertSame(self::HEADERS, $workbook['headers']);
        $this->assertSame(16, $workbook['header_count']);
        $this->assertSame(100, $workbook['detection_confidence']);
        $this->assertSame(2, $result['counts']['populated_pitch_rows']);
        $this->assertSame(['Bullpen'], $result['destination_recommendation']['recommended']);
        $this->assertSame('Pitching Session', $result['detected_format']['display_type']);
    }

    public function test_session_assignment_and_controlled_values_preserve_raw_values(): void
    {
        $result = app(RapsodoInspectionService::class)->inspect($this->metadata($this->workbook([
            ['1', '0.7695254629', '2FB', '78', '1622', '1598', '98.6', '01h:26m', '13.1', '13.9', 'Y', '5.9', '2.7', '-', '-3.9', '-9.7'],
        ])));

        $this->assertTrue($result['players'][0]['identity_missing']);
        $this->assertFalse($result['players'][0]['remember_mapping']);
        $this->assertSame('Rapsodo Pitching Session', $result['players'][0]['source_name']);
        $this->assertSame('2FB', $result['sample_rows'][0]['controlled_values']['pitch_type']['raw']);
        $this->assertSame('Two-Seam Fastball', $result['sample_rows'][0]['controlled_values']['pitch_type']['canonical_preview']);
        $this->assertSame('Y', $result['sample_rows'][0]['controlled_values']['strike']['raw']);
        $this->assertTrue($result['sample_rows'][0]['controlled_values']['strike']['canonical_preview']);
        $this->assertSame('01h:26m', $result['sample_rows'][0]['controlled_values']['spin_direction']['raw']);
        $this->assertArrayHasKey('raw_source_values', $result['sample_rows'][0]);
        $this->assertNotEmpty($result['sample_rows'][0]['validation']['warnings']);
    }

    public function test_platform_aliases_keep_distinct_pitching_concepts(): void
    {
        $before = [
            'domains' => DB::table('baseball_domains')->count(),
            'concepts' => DB::table('baseball_concepts')->count(),
            'aliases' => DB::table('baseball_concept_aliases')->count(),
            'units' => DB::table('unit_definitions')->count(),
            'conversions' => DB::table('unit_conversions')->count(),
        ];
        $this->assertSame([
            'domains' => 14,
            'concepts' => 103,
            'aliases' => 127,
            'units' => 19,
            'conversions' => 8,
        ], $before);
        $this->seed(BaseballDictionarySeeder::class);
        $this->assertSame($before['domains'], DB::table('baseball_domains')->count());
        $this->assertSame($before['concepts'], DB::table('baseball_concepts')->count());
        $this->assertSame($before['aliases'], DB::table('baseball_concept_aliases')->count());
        $this->assertSame($before['units'], DB::table('unit_definitions')->count());
        $this->assertSame($before['conversions'], DB::table('unit_conversions')->count());

        $platform = PlatformDefinition::query()->where('key', 'rapsodo')->firstOrFail();
        $resolved = app(MappingResolutionService::class)->resolve(
            'unused-team',
            $platform->id,
            app(TemplateFingerprintService::class)->fingerprint(self::HEADERS),
            self::HEADERS
        );
        $keys = collect($resolved)->mapWithKeys(fn (array $entry): array => [
            $entry['source_column_name'] => DB::table('baseball_concepts')->where('id', $entry['concept_id'])->value('canonical_key'),
        ]);

        $this->assertCount(16, $keys);
        $this->assertSame('pitching.release_velocity', $keys['velocity']);
        $this->assertSame('pitching.spin_rate', $keys['spin_rate']);
        $this->assertSame('pitching.true_spin_rate', $keys['true_spin']);
        $this->assertSame('pitching.spin_efficiency', $keys['spin_eff']);
        $this->assertSame('pitching.spin_direction_clock', $keys['spin_direction']);
        $this->assertSame('pitching.vertical_break', $keys['vert_break']);
        $this->assertNotSame('pitching.induced_vertical_break', $keys['vert_break']);
        $this->assertSame('pitching.release_angle', $keys['r_angle']);
        $this->assertSame('pitching.horizontal_release_angle', $keys['h_angle']);
        $this->assertSame('pitching.gyro_degree', $keys['gyro']);
    }

    public function test_secure_reader_rejects_macro_payloads(): void
    {
        $path = $this->workbook([['1', '0.5', 'FB', '78', '1622', '1598', '98', '01h:26m', '1', '2', 'Y', '5', '2', '1', '2', '3']], true);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('macros or external workbook links');
        app(SecureXlsxReader::class)->read($path);
    }

    public function test_endpoint_is_entitled_team_scoped_and_makes_no_data_writes(): void
    {
        Storage::fake('local');
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $contents = file_get_contents($this->workbook([['1', '0.5', 'FB', '78', '1622', '1598', '98', '01h:26m', '1', '2', 'Y', '5', '2', '1', '2', '3']]));
        $before = [
            'practices' => DB::table('practices')->count(),
            'bullpen' => DB::table('bullpen_practice_results')->count(),
            'profiles' => DB::table('profiles')->count(),
        ];

        $this->post('/api/data-hub/inspect', ['platform' => 'rapsodo', 'team_id' => $team->id, 'session_type' => 'bullpen'])->assertUnauthorized();
        Sanctum::actingAs($coach, ['coach']);
        $this->post('/api/data-hub/inspect', [
            'platform' => 'rapsodo',
            'team_id' => $team->id,
            'session_type' => 'bullpen',
            'file' => UploadedFile::fake()->createWithContent('rapsodo.xlsx', $contents),
        ])->assertOk()
            ->assertJsonPath('data.platform', 'rapsodo')
            ->assertJsonPath('data.destination_recommendation.selected', 'Bullpen')
            ->assertJsonPath('data.counts.populated_pitch_rows', 1);
        $this->post('/api/data-hub/inspect', [
            'platform' => 'rapsodo',
            'team_id' => $other->id,
            'session_type' => 'bullpen',
            'file' => UploadedFile::fake()->createWithContent('rapsodo.xlsx', $contents),
        ])->assertForbidden();

        Storage::disk('local')->assertDirectoryEmpty('data-hub/tmp');
        $this->assertSame($before['practices'], DB::table('practices')->count());
        $this->assertSame($before['bullpen'], DB::table('bullpen_practice_results')->count());
        $this->assertSame($before['profiles'], DB::table('profiles')->count());
    }

    public function test_endpoint_rejects_an_xlsx_name_with_an_invalid_mime_type(): void
    {
        $team = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        Sanctum::actingAs($coach, ['coach']);

        $this->post('/api/data-hub/inspect', [
            'platform' => 'rapsodo',
            'team_id' => $team->id,
            'session_type' => 'bullpen',
            'file' => UploadedFile::fake()->create('rapsodo.xlsx', 10, 'text/plain'),
        ])->assertUnprocessable();
    }

    private function metadata(string $path): ImportFileMetadata
    {
        return new ImportFileMetadata('rapsodo.xlsx', filesize($path), 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $path);
    }

    /** @param array<int, array<int, string>> $rows */
    private function workbook(array $rows, bool $macro = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtrx-rapsodo-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rapsodo 2-4-21-" sheetId="1" r:id="rId1"/><sheet name="Sheet1" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
        $allRows = array_merge([self::HEADERS], $rows);
        $sheetRows = [];
        foreach ($allRows as $rowIndex => $values) {
            if ([] === $values) {
                continue;
            }
            $cells = [];
            foreach ($values as $columnIndex => $value) {
                $column = chr(65 + $columnIndex);
                $escaped = htmlspecialchars((string) $value, ENT_XML1);
                $cells[] = "<c r=\"{$column}".($rowIndex + 1)."\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
            $sheetRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>');
        $zip->addFromString('xl/worksheets/sheet2.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Report Layout</t></is></c></row></sheetData></worksheet>');
        if ($macro) {
            $zip->addFromString('xl/vbaProject.bin', 'not executable in test');
        }
        $zip->close();

        return $path;
    }
}
