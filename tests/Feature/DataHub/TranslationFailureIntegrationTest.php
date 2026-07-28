<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Exceptions\TranslationFailureException;
use App\Services\DataHub\Generic\UniversalSpreadsheetInspector;
use App\Services\DataHub\Services\TranslationReviewPayloadBuilder;
use App\Services\DataHub\Services\TranslationWarningPolicy;
use App\Services\DataHub\Support\CertificationVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TranslationFailureIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_certified_failures_are_emitted_by_real_translation_paths_twice_without_writes(): void
    {
        Queue::fake();
        Event::fake();
        Storage::fake('local');
        $before = $this->protectedWriteCounts();
        $cases = [
            'malformed_csv' => fn (): array => $this->inspectionFailure(
                $this->fixtureMetadata('generic/invalid/malformed.csv', 'csv', 'text/csv')
            ),
            'missing_header' => fn (): array => $this->missingHeaderFailure(),
            'unsupported_file_type' => fn (): array => $this->inspectionFailure(
                $this->fixtureMetadata('generic/invalid/unsupported.txt', 'txt', 'text/plain')
            ),
            'corrupted_spreadsheet' => fn (): array => $this->inspectionFailure(
                $this->fixtureMetadata(
                    'generic/invalid/malformed.xlsx',
                    'xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                )
            ),
            'ambiguous_platform' => fn (): array => $this->reviewFailure([
                'platform_recognition_candidates' => ['trackman', 'hittrax'],
                'platform_confirmed' => false,
            ]),
            'unknown_required_unit' => fn (): array => $this->reviewFailure([
                'column_mappings' => [[
                    'source_column_name' => 'Velocity',
                    'action' => 'map',
                    'canonical_key' => 'pitching.release_velocity',
                    'required' => true,
                    'source_unit' => null,
                    'compatibility_level' => 'compatible',
                ]],
            ]),
            'destination_incompatibility' => fn (): array => $this->reviewFailure([
                'column_mappings' => [[
                    'source_column_name' => 'Bench Press',
                    'action' => 'map',
                    'canonical_key' => 'strength.bench_press',
                    'source_unit' => 'lbs',
                    'compatibility_level' => 'incompatible',
                ]],
            ]),
            'unknown_required_controlled_value' => fn (): array => $this->reviewFailure([
                'controlled_value_mappings' => [[
                    'source_field' => 'Throw Type',
                    'source_value' => 'Mystery Throw',
                    'canonical_value' => null,
                    'required' => true,
                ]],
            ]),
            'conflicting_mappings' => fn (): array => $this->reviewFailure([
                'column_mappings' => [
                    [
                        'source_column_name' => 'ExitSpeed',
                        'action' => 'map',
                        'canonical_key' => 'hitting.exit_velocity',
                        'source_unit' => 'mph',
                        'compatibility_level' => 'compatible',
                    ],
                    [
                        'source_column_name' => 'Velo',
                        'action' => 'map',
                        'canonical_key' => 'hitting.exit_velocity',
                        'source_unit' => 'mph',
                        'compatibility_level' => 'compatible',
                    ],
                ],
            ]),
            'unresolved_required_player' => fn (): array => $this->reviewFailure([
                'player_mappings' => [[
                    'source_key' => 'player:unresolved',
                    'source_name' => 'Unresolved Player',
                    'decision' => 'not_importing',
                    'required' => true,
                ]],
            ]),
        ];

        foreach ($cases as $expectedCode => $path) {
            $first = $path();
            $second = $path();
            $this->assertSame($first, $second, "{$expectedCode} changed between real pathway runs.");
            $this->assertSame($expectedCode, $first['code']);
            $this->assertNotSame([], $first['exclusions']);
            $this->assertFalse(
                app(TranslationWarningPolicy::class)->approvalStatus([$first], [])['approval_allowed'],
                "{$expectedCode} did not enforce its certified approval behavior.",
            );
        }

        $manifest = json_decode(
            file_get_contents(base_path('tests/Fixtures/DataHub/manifests/import-certification.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertEquals(
            array_fill_keys(array_keys($cases), 'integrated'),
            $manifest['failure_integration'],
        );
        $this->assertSame($before, $this->protectedWriteCounts());
        Queue::assertNothingPushed();
        Event::assertNothingDispatched();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /** @param array<string, mixed> $overrides */
    private function reviewFailure(array $overrides): array
    {
        $inspection = [
            'platform' => 'generic-csv',
            'file' => ['name' => 'certification.csv', 'extension' => 'csv', 'size_bytes' => 20],
            'players' => [],
            'source_columns' => [],
            'sample_rows' => [],
            'warnings' => [],
        ];
        $context = array_replace_recursive([
            'source_checksum' => str_repeat('a', 64),
            'destination' => 'assessment',
            'player_mappings' => [],
            'column_mappings' => [],
            'controlled_value_mappings' => [],
            'versions' => $this->reviewVersions(),
        ], $overrides);
        $review = app(TranslationReviewPayloadBuilder::class)->build($inspection, $context);
        $warnings = array_values(array_filter(
            $review['warnings'],
            static fn (array $warning): bool => 'inspection_warning' !== $warning['code'],
        ));

        $this->assertCount(1, $warnings);

        return $warnings[0];
    }

    private function inspectionFailure(ImportFileMetadata $metadata): array
    {
        try {
            app(UniversalSpreadsheetInspector::class)->inspect($metadata);
            $this->fail('The invalid source unexpectedly completed inspection.');
        } catch (TranslationFailureException $exception) {
            return $exception->warning();
        }
    }

    private function missingHeaderFailure(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtrx-missing-header-');
        file_put_contents($path, "single\nvalue\n");
        try {
            return $this->inspectionFailure(new ImportFileMetadata(
                'missing-header.csv',
                filesize($path),
                'csv',
                'text/csv',
                $path,
            ));
        } finally {
            @unlink($path);
        }
    }

    private function fixtureMetadata(string $relativePath, string $extension, string $mime): ImportFileMetadata
    {
        $path = base_path('tests/Fixtures/DataHub/'.$relativePath);

        return new ImportFileMetadata(basename($path), filesize($path), $extension, $mime, $path);
    }

    /** @return array<string, string> */
    private function reviewVersions(): array
    {
        return [
            'platform_dictionary' => CertificationVersions::PLATFORM_DICTIONARY,
            'baseball_dictionary' => CertificationVersions::BASEBALL_DICTIONARY,
            'translation_engine' => CertificationVersions::TRANSLATION_ENGINE,
            'translation_review_schema' => CertificationVersions::TRANSLATION_REVIEW_SCHEMA,
            'warning_rules' => CertificationVersions::WARNING_RULES,
        ];
    }

    /** @return array<string, int> */
    private function protectedWriteCounts(): array
    {
        $tables = [
            'practices',
            'batting_practice_results',
            'bullpen_practice_results',
            'player_assessments',
            'profiles',
            'import_batches',
            'external_sessions',
            'canonical_events',
            'canonical_metrics',
            'player_statistics',
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
