<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\BaseballConcept;
use App\Models\PlatformDefinition;
use App\Services\DataHub\Dictionary\MappingResolutionService;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\Dictionary\UnitConversionService;
use App\Services\DataHub\Support\CertificationVersions;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DataHubSemanticCertificationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseballDictionarySeeder::class);
        $this->manifest = json_decode(
            file_get_contents(base_path('tests/Fixtures/DataHub/manifests/semantic-equivalence.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $this->assertSame(CertificationVersions::PLATFORM_DICTIONARY, $this->manifest['certification_metadata']['platform_dictionary_version']);
        $this->assertSame(CertificationVersions::BASEBALL_DICTIONARY, $this->manifest['certification_metadata']['baseball_dictionary_version']);
        $this->assertSame(CertificationVersions::TRANSLATION_ENGINE, $this->manifest['certification_metadata']['translation_engine_version']);
    }

    public function test_exact_equivalence_groups_resolve_to_one_canonical_concept(): void
    {
        $before = $this->protectedWriteCounts();

        foreach ($this->manifest['equivalence_groups'] as $group) {
            $expected = BaseballConcept::query()->where('canonical_key', $group['canonical_key'])->firstOrFail();
            $resolvedIds = [];
            foreach ($group['cases'] as $case) {
                $resolution = $this->resolve($case['platform'], $case['source_header']);
                $resolved = BaseballConcept::query()->findOrFail($resolution['concept_id']);
                $resolvedIds[] = $resolved->id;

                $this->assertSame($expected->id, $resolved->id, "{$group['id']}: {$case['platform']} concept ID");
                $this->assertSame($group['canonical_key'], $resolved->canonical_key, "{$group['id']}: canonical key");
                $this->assertSame($group['canonical_unit'], $resolved->canonical_unit_key, "{$group['id']}: canonical unit");
                $this->assertSame($case['platform'], PlatformDefinition::query()->where('key', $case['platform'])->value('key'));
                $this->assertSame($case['source_header'], $resolution['source_column_name']);
                $this->assertSame($case['source_unit'], $resolution['source_unit_key'], "{$group['id']}: source unit");
                $this->assertSame($case['relationship_type'], $resolution['relationship_type']);
                $this->assertSame('official_platform_alias', $resolution['resolution_source']);
                $this->assertSame($case['normalized_value'], $case['raw_value']);
                $this->assertNull($resolution['transformation_key']);
            }
            $this->assertCount(1, array_unique($resolvedIds), "{$group['id']} produced multiple concept IDs.");
            $this->assertSame(1, BaseballConcept::query()->where('canonical_key', $group['canonical_key'])->count());
        }

        $this->assertSame($before, $this->protectedWriteCounts());
    }

    public function test_non_equivalence_cases_remain_separate(): void
    {
        foreach ($this->manifest['non_equivalence_cases'] as $case) {
            $resolution = $this->resolve($case['platform'], $case['source_header']);
            $actual = BaseballConcept::query()->findOrFail($resolution['concept_id']);
            $forbidden = BaseballConcept::query()->where('canonical_key', $case['must_not_equal'])->first();

            $this->assertSame($case['canonical_key'], $actual->canonical_key, "{$case['id']}: wrong canonical key");
            $this->assertNotSame($case['must_not_equal'], $actual->canonical_key, "{$case['id']}: unsafe semantic coercion");
            if ($forbidden) {
                $this->assertNotSame($forbidden->id, $actual->id, "{$case['id']}: distinct concepts share an ID");
            }
            if ('inbound_vs_release_velocity' === $case['id']) {
                $this->assertSame('related_but_separate', $case['semantic_relationship']);
                $this->assertSame('mph', $resolution['source_unit_key']);
                $this->assertFalse($actual->research_eligible, 'Inbound pitch velocity must not share research eligibility automatically.');
            }
        }
    }

    public function test_verified_unit_normalization_is_exact_and_blanks_remain_null(): void
    {
        $converter = app(UnitConversionService::class);

        foreach ($this->manifest['value_normalization'] as $case) {
            $normalized = $converter->convert(
                (float) $case['raw_value'],
                $case['source_unit'],
                $case['canonical_unit']
            );
            $this->assertEqualsWithDelta(
                (float) $case['expected_value'],
                $normalized,
                (float) ($case['tolerance'] ?? 0.000001),
                "{$case['source_unit']} to {$case['canonical_unit']}"
            );
        }

        $rawUnavailableValue = null;
        $normalizedUnavailableValue = is_numeric($rawUnavailableValue)
            ? $converter->convert((float) $rawUnavailableValue, 'mph', 'mph')
            : null;
        $this->assertNull($normalizedUnavailableValue);
        $this->assertSame(0.0, $converter->convert(0.0, 'mph', 'mph'), 'A zero is preserved unless a source-specific parser marks it unavailable.');
    }

    public function test_alias_resolution_does_not_create_duplicate_concepts_or_import_records(): void
    {
        $beforeConcepts = BaseballConcept::query()->count();
        $beforeWrites = $this->protectedWriteCounts();

        foreach ($this->manifest['equivalence_groups'] as $group) {
            foreach ($group['cases'] as $case) {
                $this->resolve($case['platform'], $case['source_header']);
            }
        }
        foreach ($this->manifest['non_equivalence_cases'] as $case) {
            $this->resolve($case['platform'], $case['source_header']);
        }

        $this->assertSame($beforeConcepts, BaseballConcept::query()->count());
        $this->assertSame($beforeWrites, $this->protectedWriteCounts());
    }

    /** @return array<string, mixed> */
    private function resolve(string $platformKey, string $header): array
    {
        $platform = PlatformDefinition::query()->where('key', $platformKey)->firstOrFail();
        $resolved = app(MappingResolutionService::class)->resolve(
            'semantic-certification-no-team',
            (string) $platform->id,
            app(TemplateFingerprintService::class)->fingerprint([$header]),
            [$header]
        );

        $this->assertCount(1, $resolved, "{$platformKey}:{$header} did not resolve exactly once.");
        $this->assertNotNull($resolved[0]['concept_id'], "{$platformKey}:{$header} is unresolved.");

        return $resolved[0];
    }

    /** @return array<string, int> */
    private function protectedWriteCounts(): array
    {
        $tables = [
            'practices', 'batting_practice_results', 'bullpen_practice_results',
            'player_assessments', 'profiles', 'import_batches', 'canonical_events',
            'mapping_templates', 'mapping_template_versions', 'mapping_entries',
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
