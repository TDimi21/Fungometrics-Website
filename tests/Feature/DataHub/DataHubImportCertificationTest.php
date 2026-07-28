<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Generic\UniversalSpreadsheetInspector;
use App\Services\DataHub\Platforms\BlastMotion\BlastMotionInspectionService;
use App\Services\DataHub\Platforms\HitTrax\HitTraxInspectionService;
use App\Services\DataHub\Platforms\Rapsodo\RapsodoInspectionService;
use App\Services\DataHub\Platforms\TrackMan\TrackManInspectionService;
use App\Services\DataHub\Templates\FmtrxCsvTemplateService;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class DataHubImportCertificationTest extends TestCase
{
    use RefreshDatabase;

    private const REQUIRED_MANIFEST_KEYS = [
        'id', 'filename', 'format', 'intended_platform', 'intended_destination',
        'expected_layout', 'expected_worksheet', 'expected_header_row',
        'expected_first_data_row', 'expected_physical_row_count',
        'expected_logical_record_count', 'expected_column_count',
        'expected_unique_source_players', 'expected_eligible_players',
        'expected_not_importing_players', 'expected_metric_headers',
        'expected_mapped_concepts', 'expected_unknown_concepts', 'expected_warnings',
        'expected_transformations', 'expected_detection_confidence_category',
        'expected_extraction_confidence_category',
        'manual_file_structure_confirmation_required',
        'session_level_player_mapping_required',
        'duplicate_player_confirmation_required',
        'duplicate_concept_confirmation_required', 'approval_should_pass',
        'expected_blocked_reason',
    ];

    public function test_manifest_is_complete_and_all_registered_files_exist(): void
    {
        $manifest = $this->manifest();

        $this->assertSame('1.0', $manifest['schema_version']);
        $this->assertCount(30, $manifest['fixtures']);
        $this->assertSame(count($manifest['fixtures']), count(array_unique(array_column($manifest['fixtures'], 'id'))));
        foreach ($manifest['fixtures'] as $fixture) {
            $missing = array_values(array_diff(self::REQUIRED_MANIFEST_KEYS, array_keys($fixture)));
            $this->assertSame([], $missing, "{$fixture['id']} is missing manifest keys: ".implode(', ', $missing));
            $this->assertFileExists($this->fixturePath($fixture), "{$fixture['id']} points to a missing fixture.");
            $this->assertStringNotContainsStringIgnoringCase('private@example.com', file_get_contents($this->fixturePath($fixture)), "{$fixture['id']} contains private test data.");
        }
    }

    public function test_fixture_generator_is_byte_for_byte_repeatable(): void
    {
        $before = $this->fixtureHashes();
        exec('php '.escapeshellarg(base_path('scripts/generate-data-hub-certification-fixtures.php')), $output, $exit);
        $after = $this->fixtureHashes();

        $this->assertSame(0, $exit, implode("\n", $output));
        $this->assertSame($before, $after, 'Rerunning fixture generation changed deterministic fixture bytes.');
    }

    public function test_generic_success_fixtures_match_their_structure_contract(): void
    {
        $fixtures = array_filter(
            $this->manifest()['fixtures'],
            fn (array $fixture): bool =>
            'generic-csv' === $fixture['intended_platform']
            && in_array($fixture['format'], ['csv', 'tsv', 'xlsx'], true)
            && ! str_starts_with($fixture['id'], 'invalid-')
        );

        foreach ($fixtures as $fixture) {
            $result = app(UniversalSpreadsheetInspector::class)->inspect($this->metadata($fixture));
            $inspection = $result['normalized_inspection'];
            $message = $fixture['id'];

            $this->assertSame($fixture['format'], $inspection['file_type'], "{$message}: file type");
            $this->assertSame($fixture['expected_layout'], $inspection['detected_layout'], "{$message}: layout");
            $this->assertSame($fixture['expected_header_row'], $inspection['header_row'], "{$message}: header row");
            $this->assertSame($fixture['expected_first_data_row'], $inspection['first_data_row'], "{$message}: first data row");
            $this->assertSame($fixture['expected_logical_record_count'], $result['counts']['total_rows'], "{$message}: logical records");
            $this->assertSame($fixture['expected_unique_source_players'], $result['counts']['players_found'], "{$message}: players");
            $this->assertSame($fixture['expected_worksheet'], $inspection['worksheets'][$inspection['selected_worksheet_index']]['name'], "{$message}: worksheet");
            $this->assertSame(
                $fixture['expected_metric_headers'],
                array_column($result['source_columns'], 'source_column_name'),
                "{$message}: source metric headers"
            );
            $this->assertSame(
                $fixture['manual_file_structure_confirmation_required'],
                $inspection['requires_structure_confirmation'],
                "{$message}: confirmation requirement"
            );
        }
    }

    public function test_known_platform_fixtures_use_the_real_inspection_services(): void
    {
        $this->seed(BaseballDictionarySeeder::class);
        $fixtures = collect($this->manifest()['fixtures'])->keyBy('id');
        $before = $this->protectedWriteCounts();

        $trackman = app(TrackManInspectionService::class)->inspect(
            $this->metadata($fixtures['platform-trackman']),
            '00000000-0000-4000-8000-000000000000',
            'live_ab'
        );
        $hittrax = app(HitTraxInspectionService::class)->inspect(
            $this->metadata($fixtures['platform-hittrax']),
            '00000000-0000-4000-8000-000000000000'
        );
        $rapsodo = app(RapsodoInspectionService::class)->inspect($this->metadata($fixtures['platform-rapsodo']));
        $blast = app(BlastMotionInspectionService::class)->inspect($this->metadata($fixtures['platform-blast']));

        $this->assertSame('mixed', $trackman['detected_format']['data_type']);
        $this->assertSame(2, $trackman['counts']['total_rows']);
        $this->assertSame('HitTrax', $hittrax['detected_format']['provider']);
        $this->assertSame(2, $hittrax['counts']['tracked_batted_balls']);
        $this->assertSame('Rapsodo', $rapsodo['detected_format']['provider']);
        $this->assertSame(1, $rapsodo['counts']['populated_pitch_rows']);
        $this->assertTrue($rapsodo['players'][0]['identity_missing']);
        $this->assertSame('Blast Motion', $blast['detected_format']['provider']);
        $this->assertSame(8, $blast['detected_format']['header_row']);
        $this->assertSame(1, $blast['counts']['populated_swing_rows']);
        $this->assertTrue($blast['players'][0]['identity_missing']);
        $this->assertSame($before, $this->protectedWriteCounts(), 'Inspection wrote protected import or performance records.');
    }

    public function test_fmtrx_fixture_schema_is_produced_by_the_live_template_generator(): void
    {
        $team = \App\Models\Team::factory()->create(['name' => 'Certification Team']);
        $generated = app(FmtrxCsvTemplateService::class)->generate('strength', (string) $team->id, $team->name);
        $fixture = collect($this->manifest()['fixtures'])->keyBy('id')['fmtrx-generated-template'];

        $generatedRows = $this->csvRows($generated);
        $fixtureRows = $this->csvRows(file_get_contents($this->fixturePath($fixture)));

        $this->assertSame($fixtureRows[0], $generatedRows[0], 'Template marker/version drifted from the live generator.');
        $this->assertSame($fixtureRows[1], $generatedRows[1], 'Canonical key row drifted from the live generator.');
        $this->assertSame($fixtureRows[2], $generatedRows[2], 'Visible labels drifted from the live generator.');
    }

    public function test_invalid_fixtures_fail_safely_or_return_reviewable_warnings(): void
    {
        $fixtures = collect($this->manifest()['fixtures'])->keyBy('id');
        $bad = app(UniversalSpreadsheetInspector::class)->inspect($this->metadata($fixtures['invalid-bad-xlsx']));
        $this->assertContains('Merged cells exist and require review.', $bad['warnings']);
        $this->assertContains('Formula cells were not executed; only cached displayed values were inspected.', $bad['warnings']);

        foreach (['invalid-extension', 'invalid-malformed-xlsx'] as $id) {
            try {
                app(UniversalSpreadsheetInspector::class)->inspect($this->metadata($fixtures[$id]));
                $this->fail("{$id} was expected to be rejected.");
            } catch (RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage(), "{$id} returned an empty error.");
            }
        }
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        return json_decode(
            file_get_contents(base_path('tests/Fixtures/DataHub/manifests/import-certification.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }

    private function fixturePath(array $fixture): string
    {
        return base_path('tests/Fixtures/DataHub/'.$fixture['filename']);
    }

    private function metadata(array $fixture): ImportFileMetadata
    {
        $path = $this->fixturePath($fixture);
        $mime = match ($fixture['format']) {
            'csv' => 'text/csv',
            'tsv' => 'text/tab-separated-values',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/plain',
        };

        return new ImportFileMetadata(basename($path), filesize($path), $fixture['format'], $mime, $path);
    }

    /** @return array<int, array<int, string|null>> */
    private function csvRows(string $contents): array
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $contents);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $row[0] = ltrim((string) ($row[0] ?? ''), "\xEF\xBB\xBF");
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<string, string> */
    private function fixtureHashes(): array
    {
        $files = [];
        foreach ($this->manifest()['fixtures'] as $fixture) {
            $files[$fixture['filename']] = hash_file('sha256', $this->fixturePath($fixture));
        }
        ksort($files);

        return $files;
    }

    /** @return array<string, int> */
    private function protectedWriteCounts(): array
    {
        $tables = [
            'practices', 'batting_practice_results', 'bullpen_practice_results',
            'player_assessments', 'profiles', 'import_batches', 'canonical_events',
            'mapping_template_versions',
        ];
        $counts = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return $counts;
    }
}
