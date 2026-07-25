<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Generic\UniversalSpreadsheetInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

final class UniversalSpreadsheetInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_players_in_rows_with_metadata_and_manual_header_selection(): void
    {
        $path = $this->delimited([
            ['Assessment Export'], ['Generated', 'Today'], [],
            ['Player Name', 'Bench Press', 'Back Squat', 'Vertical Jump'],
            ['Tom', '185', '275', '28'], ['Jake', '165', '245', '25'],
        ]);
        $result = $this->inspect($path, 'csv');

        $this->assertSame(4, $result['normalized_inspection']['header_row']);
        $this->assertSame('players_in_rows', $result['normalized_inspection']['detected_layout']);
        $this->assertSame(['Tom', 'Jake'], array_column($result['players'], 'source_name'));
        $this->assertSame(['Bench Press', 'Back Squat', 'Vertical Jump'], array_column($result['source_columns'], 'source_column_name'));

        $manual = $this->inspect($path, 'csv', ['header_row' => 4, 'first_data_row' => 6, 'player_column' => 'Player Name', 'layout' => 'players_in_rows']);
        $this->assertSame(1, $manual['counts']['total_rows']);
        $this->assertSame('Jake', $manual['players'][0]['source_name']);
    }

    public function test_csv_players_in_columns_is_logically_transposed(): void
    {
        $result = $this->inspect($this->delimited([
            ['Metric', 'Tom', 'Jake', 'Carter'],
            ['Bench Press', '185', '165', '205'],
            ['Back Squat', '275', '245', '315'],
            ['Vertical Jump', '28', '25', '30'],
        ]), 'csv');

        $this->assertSame('players_in_columns', $result['normalized_inspection']['detected_layout']);
        $this->assertSame(['Tom', 'Jake', 'Carter'], array_column($result['players'], 'source_name'));
        $this->assertSame(['Bench Press', 'Back Squat', 'Vertical Jump'], array_column($result['source_columns'], 'source_column_name'));
    }

    public function test_csv_events_in_rows_and_tsv_are_supported(): void
    {
        $rows = [
            ['Player', 'Date', 'Throw Number', 'Ball Weight', 'Velocity'],
            ['Tom', '2026-01-01', '1', '5 oz', '82'],
            ['Tom', '2026-01-01', '2', '5 oz', '84'],
        ];
        $csv = $this->inspect($this->delimited($rows), 'csv');
        $tsv = $this->inspect($this->delimited($rows, "\t"), 'tsv');

        $this->assertSame('events_in_rows', $csv['normalized_inspection']['detected_layout']);
        $this->assertSame(2, $csv['players'][0]['row_count']);
        $this->assertSame('tsv', $tsv['normalized_inspection']['file_type']);
        $this->assertSame(5, $tsv['counts']['columns_found'] + 1);
    }

    public function test_xlsx_multiple_worksheets_suggests_worksheet_per_player(): void
    {
        $result = $this->inspect($this->xlsx(), 'xlsx');

        $this->assertSame('worksheet_per_player', $result['normalized_inspection']['detected_layout']);
        $this->assertSame(['Tom Dimitroff', 'Jake Smith'], array_column($result['players'], 'source_name'));
        $this->assertTrue($result['normalized_inspection']['requires_structure_confirmation']);
        $this->assertCount(2, $result['normalized_inspection']['worksheets']);
    }

    public function test_no_identity_uses_single_player_session_and_ignored_columns_are_removed(): void
    {
        $path = $this->delimited([
            ['Pitch Number', 'Velocity', 'Spin Rate', 'IVB'],
            ['1', '88.4', '2240', '16.8'],
        ]);
        $result = $this->inspect($path, 'csv', ['ignored_columns' => ['IVB']]);

        $this->assertSame('single_player_session', $result['normalized_inspection']['detected_layout']);
        $this->assertTrue($result['players'][0]['identity_missing']);
        $this->assertSame('Spreadsheet Session', $result['players'][0]['source_name']);
        $this->assertNotContains('IVB', array_column($result['source_columns'], 'source_column_name'));
    }

    public function test_endpoint_is_authorized_cleans_up_and_rejects_unsupported_files_without_writes(): void
    {
        Storage::fake('local');
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $contents = file_get_contents($this->delimited([
            ['Player', 'Bench Press'], ['Tom', '185'],
        ], "\t"));
        $before = [
            'practices' => DB::table('practices')->count(),
            'profiles' => DB::table('profiles')->count(),
        ];

        $this->post('/api/data-hub/inspect')->assertUnauthorized();
        Sanctum::actingAs($coach, ['coach']);
        $payload = fn (string $teamId): array => [
            'platform' => 'generic-csv', 'team_id' => $teamId, 'session_type' => 'strength',
            'file' => UploadedFile::fake()->createWithContent('testing.tsv', $contents),
        ];
        $this->post('/api/data-hub/inspect', $payload($team->id))->assertOk()
            ->assertJsonPath('data.normalized_inspection.file_type', 'tsv')
            ->assertJsonPath('data.normalized_inspection.detected_layout', 'players_in_rows');
        $this->post('/api/data-hub/inspect', $payload($other->id))->assertForbidden();
        $this->post('/api/data-hub/inspect', [
            'platform' => 'generic-csv', 'team_id' => $team->id, 'session_type' => 'strength',
            'file' => UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable();

        Storage::disk('local')->assertDirectoryEmpty('data-hub/tmp');
        $this->assertSame($before['practices'], DB::table('practices')->count());
        $this->assertSame($before['profiles'], DB::table('profiles')->count());
    }

    private function inspect(string $path, string $extension, array $override = []): array
    {
        $file = new ImportFileMetadata("generic.{$extension}", filesize($path), $extension, 'text/plain', $path);

        return app(UniversalSpreadsheetInspector::class)->inspect($file, $override);
    }

    private function delimited(array $rows, string $delimiter = ','): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtrx-generic-');
        $handle = fopen($path, 'wb');
        foreach ($rows as $row) {
            fputcsv($handle, $row, $delimiter);
        }
        fclose($handle);

        return $path;
    }

    private function xlsx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtrx-generic-xlsx-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Tom Dimitroff" sheetId="1" r:id="rId1"/><sheet name="Jake Smith" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
        $sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Metric</t></is></c><c r="B1" t="inlineStr"><is><t>Value</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Velocity</t></is></c><c r="B2" t="inlineStr"><is><t>88</t></is></c></row></sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->addFromString('xl/worksheets/sheet2.xml', $sheet);
        $zip->close();

        return $path;
    }
}
