<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\BaseballConcept;
use App\Models\BaseballConceptAlias;
use App\Models\PlatformDefinition;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Enums\TranslationWarningSeverity;
use App\Services\DataHub\Exceptions\TranslationContractException;
use App\Services\DataHub\Exceptions\TranslationFailureException;
use App\Services\DataHub\Dictionary\ConceptCompatibilityService;
use App\Services\DataHub\Dictionary\MappingResolutionService;
use App\Services\DataHub\Generic\UniversalSpreadsheetInspector;
use App\Services\DataHub\Platforms\TrackMan\TrackManInspectionService;
use App\Services\DataHub\Services\PlayerMatchingService;
use App\Services\DataHub\Services\TranslationFailureCatalog;
use App\Services\DataHub\Services\TranslationReviewPayloadBuilder;
use App\Services\DataHub\Services\TranslationSnapshotPayloadFactory;
use App\Services\DataHub\Services\TranslationWarningPolicy;
use App\Services\DataHub\Support\CanonicalPayload;
use App\Services\DataHub\Support\CertificationVersions;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class TranslationDeterminismCertificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseballDictionarySeeder::class);
        PlatformDefinition::query()->firstOrCreate(
            ['key' => 'generic-csv'],
            ['name' => 'Generic Spreadsheet', 'description' => 'Generic certification source.', 'is_active' => true],
        );
    }

    public function test_all_four_warning_levels_have_versioned_deterministic_approval_behavior(): void
    {
        $policy = app(TranslationWarningPolicy::class);
        $catalog = app(TranslationFailureCatalog::class);
        $informational = $catalog->warning('source_context_available', ['source' => 'certification']);
        $warning = $catalog->warning('source_quality_concern', ['source' => 'certification']);
        $high = $catalog->warning('unknown_required_controlled_value', ['source_value' => 'Mystery Throw']);
        $blocking = $catalog->warning('unknown_required_unit', ['source_field' => 'Velocity']);

        $this->assertSame('informational', $informational['severity']);
        $this->assertSame('warning', $warning['severity']);
        $this->assertSame('high_severity', $high['severity']);
        $this->assertSame('blocking', $blocking['severity']);
        $this->assertTrue($policy->approvalStatus([$informational], [])['approval_allowed']);
        $this->assertTrue($policy->approvalStatus([$warning], [])['approval_allowed']);
        $this->assertFalse($policy->approvalStatus([$high], [])['approval_allowed']);
        $this->assertTrue($policy->approvalStatus([$high], [$high['warning_id']])['approval_allowed']);
        $this->assertFalse($policy->approvalStatus([$blocking], [])['approval_allowed']);
        try {
            $policy->approvalStatus([$blocking], [$blocking['warning_id']]);
            $this->fail('A blocking warning was accepted as acknowledgment-eligible.');
        } catch (TranslationContractException $exception) {
            $this->assertSame('warning_acknowledgment_not_allowed', $exception->errorCode());
        }
        $this->assertSame(CertificationVersions::WARNING_RULES, $blocking['warning_rules_version']);
        $this->assertSame($high, $catalog->warning('unknown_required_controlled_value', ['source_value' => 'Mystery Throw']));
    }

    public function test_warning_identity_and_acknowledgments_preserve_material_semantics(): void
    {
        $policy = app(TranslationWarningPolicy::class);
        $base = $policy->warning(
            'certification_warning',
            TranslationWarningSeverity::Warning,
            'Wording one',
            ['field' => 'Velocity'],
            [['scope' => 'source_field']],
            'rules-1',
        );
        $same = $policy->warning(
            'certification_warning',
            TranslationWarningSeverity::Warning,
            'Different non-authoritative wording',
            ['field' => 'Velocity'],
            [['scope' => 'source_field']],
            'rules-1',
        );
        $variants = [
            $policy->warning('certification_warning', TranslationWarningSeverity::Blocking, 'Blocking', ['field' => 'Velocity'], [['scope' => 'source_field']], 'rules-1'),
            $policy->warning('certification_warning', TranslationWarningSeverity::HighSeverity, 'High', ['field' => 'Velocity'], [['scope' => 'source_field']], 'rules-1'),
            $policy->warning('certification_warning', TranslationWarningSeverity::Warning, 'Exclusion', ['field' => 'Velocity'], [['scope' => 'source_file']], 'rules-1'),
            $policy->warning('certification_warning', TranslationWarningSeverity::Warning, 'Context', ['field' => 'Spin'], [['scope' => 'source_field']], 'rules-1'),
            $policy->warning('certification_warning', TranslationWarningSeverity::Warning, 'Version', ['field' => 'Velocity'], [['scope' => 'source_field']], 'rules-2'),
        ];

        $this->assertSame($base['warning_id'], $same['warning_id']);
        foreach ($variants as $variant) {
            $this->assertNotSame($base['warning_id'], $variant['warning_id']);
        }
        $this->assertCount(1 + count($variants), $policy->normalize(array_merge([$base, $same], $variants)));

        $high = $variants[1];
        $this->assertSame(
            [$high['warning_id']],
            $policy->validateAcknowledgments([$high], [$high['warning_id'], $high['warning_id']]),
        );
        foreach ([
            ['unknown-id', 'unknown_warning_acknowledgment'],
            [$base['warning_id'], 'warning_acknowledgment_not_allowed'],
        ] as [$warningId, $expectedCode]) {
            try {
                $policy->validateAcknowledgments([$base, $high], [$warningId]);
                $this->fail("{$expectedCode} was not enforced.");
            } catch (TranslationContractException $exception) {
                $this->assertSame($expectedCode, $exception->errorCode());
            }
        }
    }

    /**
     * @dataProvider failureConditionProvider
     */
    public function test_failure_conditions_have_stable_codes_severity_exclusions_and_repeat_results(
        string $code,
        string $expectedSeverity,
        bool $approvalBlocked,
        bool $acknowledgmentRequired,
    ): void {
        $before = $this->protectedWriteCounts();
        $catalog = app(TranslationFailureCatalog::class);
        $policy = app(TranslationWarningPolicy::class);
        $context = ['fixture' => "certification:{$code}"];
        $first = $catalog->warning($code, $context);
        $second = $catalog->warning($code, $context);

        $this->assertSame($first, $second, "{$code} changed between identical evaluations.");
        $this->assertSame($code, $first['code']);
        $this->assertSame($expectedSeverity, $first['severity']);
        $this->assertSame($approvalBlocked, $first['approval_blocked']);
        $this->assertSame($acknowledgmentRequired, $first['acknowledgment_required']);
        $this->assertNotSame([], $first['exclusions']);
        $this->assertFalse($policy->approvalStatus([$first], [])['approval_allowed']);
        if ($acknowledgmentRequired && ! $approvalBlocked) {
            $this->assertTrue($policy->approvalStatus([$first], [$first['warning_id']])['approval_allowed']);
        }
        $this->assertSame($before, $this->protectedWriteCounts(), "{$code} wrote protected data.");
    }

    /** @return array<string, array{string, string, bool, bool}> */
    public static function failureConditionProvider(): array
    {
        return [
            'malformed CSV' => ['malformed_csv', 'blocking', true, false],
            'missing header' => ['missing_header', 'blocking', true, false],
            'ambiguous platform' => ['ambiguous_platform', 'blocking', true, false],
            'unknown required unit' => ['unknown_required_unit', 'blocking', true, false],
            'destination incompatibility' => ['destination_incompatibility', 'blocking', true, false],
            'unknown required controlled value' => ['unknown_required_controlled_value', 'high_severity', false, true],
            'conflicting mappings' => ['conflicting_mappings', 'blocking', true, false],
            'unresolved required player' => ['unresolved_required_player', 'blocking', true, false],
            'unsupported file type' => ['unsupported_file_type', 'blocking', true, false],
            'corrupted spreadsheet' => ['corrupted_spreadsheet', 'blocking', true, false],
        ];
    }

    public function test_known_platform_fixture_is_exactly_deterministic_from_inspection_through_snapshot(): void
    {
        $fixture = $this->fixture('platform-trackman');
        $metadata = $this->metadata($fixture);
        $inspectionService = app(TrackManInspectionService::class);
        $before = $this->protectedWriteCounts();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $firstInspection = $inspectionService->inspect($metadata, 'certification-no-team', 'live_ab');
        $secondInspection = $inspectionService->inspect($metadata, 'certification-no-team', 'live_ab');
        $this->assertSame($firstInspection, $secondInspection);

        $context = $this->reviewContext($firstInspection, $metadata, 'live_ab');
        $firstReview = app(TranslationReviewPayloadBuilder::class)->build($firstInspection, $context);
        $secondReview = app(TranslationReviewPayloadBuilder::class)->build($secondInspection, $context);
        $this->assertDeterministicReviewSections($firstReview, $secondReview);

        $firstSnapshot = app(TranslationSnapshotPayloadFactory::class)->create(
            $firstReview,
            'coach-certification',
            '2026-07-27T12:00:00-04:00',
            [],
            CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
        )->toArray();
        $secondSnapshot = app(TranslationSnapshotPayloadFactory::class)->create(
            $secondReview,
            'coach-certification',
            '2026-07-27T12:00:00-04:00',
            [],
            CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
        )->toArray();
        $this->assertSame($firstSnapshot, $secondSnapshot);
        $this->assertSame($firstSnapshot['content_hash'], $secondSnapshot['content_hash']);
        $this->assertNoMutationQueries();
        $this->assertSame($before, $this->protectedWriteCounts());
    }

    public function test_generic_fixture_is_exactly_deterministic_from_inspection_through_snapshot(): void
    {
        $fixture = $this->fixture('generic-assessment-rows');
        $metadata = $this->metadata($fixture);
        $inspector = app(UniversalSpreadsheetInspector::class);
        $before = $this->protectedWriteCounts();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $firstInspection = $inspector->inspect($metadata);
        $secondInspection = $inspector->inspect($metadata);
        $this->assertSame($firstInspection, $secondInspection);

        $context = $this->reviewContext($firstInspection, $metadata, 'assessment');
        $firstReview = app(TranslationReviewPayloadBuilder::class)->build($firstInspection, $context);
        $secondReview = app(TranslationReviewPayloadBuilder::class)->build($secondInspection, $context);
        $this->assertDeterministicReviewSections($firstReview, $secondReview);

        $firstSnapshot = app(TranslationSnapshotPayloadFactory::class)->create(
            $firstReview,
            'coach-certification',
            '2026-07-27T12:00:00-04:00',
            [],
            CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
        )->toArray();
        $secondSnapshot = app(TranslationSnapshotPayloadFactory::class)->create(
            $secondReview,
            'coach-certification',
            '2026-07-27T12:00:00-04:00',
            [],
            CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
        )->toArray();
        $this->assertSame($firstSnapshot, $secondSnapshot);
        $this->assertNoMutationQueries();
        $this->assertSame($before, $this->protectedWriteCounts());
    }

    public function test_failure_fixture_is_rejected_deterministically_by_the_real_inspector_without_writes(): void
    {
        $fixture = $this->fixture('invalid-malformed-csv');
        $metadata = $this->metadata($fixture);
        $inspector = app(UniversalSpreadsheetInspector::class);
        $before = $this->protectedWriteCounts();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $warnings = [];
        for ($run = 0; $run < 2; ++$run) {
            try {
                $inspector->inspect($metadata);
                $this->fail('Malformed CSV completed the real inspection pathway.');
            } catch (TranslationFailureException $exception) {
                $warnings[] = $exception->warning();
            }
        }
        $this->assertSame($warnings[0], $warnings[1]);
        $this->assertSame('malformed_csv', $warnings[0]['code']);
        $this->assertTrue($warnings[0]['approval_blocked']);
        $this->assertNoMutationQueries();
        $this->assertSame($before, $this->protectedWriteCounts());
    }

    public function test_snapshot_rejects_every_stale_material_review_mutation_and_accepts_a_rebuilt_review(): void
    {
        $fixture = $this->fixture('generic-assessment-rows');
        $metadata = $this->metadata($fixture);
        $inspection = app(UniversalSpreadsheetInspector::class)->inspect($metadata);
        $context = $this->reviewContext($inspection, $metadata, 'assessment');
        $review = app(TranslationReviewPayloadBuilder::class)->build($inspection, $context);
        $factory = app(TranslationSnapshotPayloadFactory::class);
        $base = $factory->create(
            $review,
            'coach-one',
            '2026-07-27T12:00:00-04:00',
            [],
            CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
        );
        $baseArray = $base->toArray();

        $mutatedCopy = $base->toArray();
        $mutatedCopy['destination'] = 'cage';
        $this->assertSame($baseArray, $base->toArray(), 'Snapshot value object was mutated through a returned array.');

        $mutations = [
            'destination' => static function (array &$payload): void {
                $payload['source_summary']['destination'] = 'cage';
            },
            'player mapping' => static function (array &$payload): void {
                $payload['player_mappings'][0]['fmtrx_player_id'] = 'different-player';
            },
            'column mapping' => static function (array &$payload): void {
                $payload['column_mappings'][0]['canonical_key'] = 'hitting.launch_angle';
            },
            'warning' => static function (array &$payload): void {
                $payload['warnings'][] = ['code' => 'tampered'];
            },
            'exclusion' => static function (array &$payload): void {
                $payload['exclusions'][] = ['scope' => 'source_file', 'reason_code' => 'tampered'];
            },
            'version' => static function (array &$payload): void {
                $payload['versions']['baseball_dictionary'] = '9.9.9';
            },
        ];
        foreach ($mutations as $label => $mutate) {
            $tampered = $review;
            $mutate($tampered);
            try {
                $factory->create(
                    $tampered,
                    'coach-one',
                    '2026-07-27T12:00:00-04:00',
                    [],
                    CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
                );
                $this->fail("Stale {$label} mutation was accepted.");
            } catch (TranslationContractException $exception) {
                $this->assertSame('translation_review_hash_mismatch', $exception->errorCode(), $label);
            }
        }

        $rebuiltContext = $context;
        $rebuiltContext['destination'] = 'cage';
        $rebuilt = app(TranslationReviewPayloadBuilder::class)->build($inspection, $rebuiltContext);
        $rebuiltSnapshot = $factory->create(
            $rebuilt,
            'coach-one',
            '2026-07-27T12:00:00-04:00',
            [],
            CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
        )->toArray();
        $this->assertNotSame($review['review_content_hash'], $rebuilt['review_content_hash']);
        $this->assertNotSame($baseArray['content_hash'], $rebuiltSnapshot['content_hash']);
    }

    public function test_valid_rebuilt_material_inputs_change_snapshot_hashes_one_factor_at_a_time(): void
    {
        $fixture = $this->fixture('platform-trackman');
        $metadata = $this->metadata($fixture);
        $inspection = app(TrackManInspectionService::class)->inspect($metadata, 'certification-no-team', 'live_ab');
        $baseContext = $this->reviewContext($inspection, $metadata, 'live_ab');
        $base = $this->snapshot($inspection, $baseContext, 'coach-one', '2026-07-27T12:00:00-04:00');

        $contextChanges = [
            'athlete mapping' => static function (array &$context): void {
                $context['player_mappings'][0]['fmtrx_player_id'] = 'different-player';
            },
            'concept mapping' => static function (array &$context): void {
                $context['column_mappings'][0]['canonical_key'] = 'certification.changed_concept';
            },
            'platform dictionary version' => static function (array &$context): void {
                $context['versions']['platform_dictionary'] = '1.0.1';
            },
            'baseball dictionary version' => static function (array &$context): void {
                $context['versions']['baseball_dictionary'] = '1.0.1';
            },
            'translation engine version' => static function (array &$context): void {
                $context['versions']['translation_engine'] = '1.1.1';
            },
            'translation review schema version' => static function (array &$context): void {
                $context['versions']['translation_review_schema'] = '1.1.1';
            },
            'warning rules version' => static function (array &$context): void {
                $context['versions']['warning_rules'] = '1.1.1';
            },
            'destination' => static function (array &$context): void {
                $context['destination'] = 'cage';
            },
            'exclusion' => static function (array &$context): void {
                $context['exclusions'][] = ['scope' => 'source_file', 'reason_code' => 'coach_exclusion'];
            },
        ];
        foreach ($contextChanges as $label => $change) {
            $changedContext = $baseContext;
            $change($changedContext);
            $changed = $this->snapshot(
                $inspection,
                $changedContext,
                'coach-one',
                '2026-07-27T12:00:00-04:00',
            );
            $this->assertNotSame($base['content_hash'], $changed['content_hash'], $label);
        }

        $differentApprover = $this->snapshot(
            $inspection,
            $baseContext,
            'coach-two',
            '2026-07-27T12:00:00-04:00',
        );
        $differentTimestamp = $this->snapshot(
            $inspection,
            $baseContext,
            'coach-one',
            '2026-07-27T12:00:01-04:00',
        );
        $differentSnapshotSchema = $this->snapshot(
            $inspection,
            $baseContext,
            'coach-one',
            '2026-07-27T12:00:00-04:00',
            '1.1.1',
        );
        $this->assertNotSame($base['content_hash'], $differentApprover['content_hash']);
        $this->assertNotSame($base['content_hash'], $differentTimestamp['content_hash']);
        $this->assertNotSame($base['content_hash'], $differentSnapshotSchema['content_hash']);

        $catalog = app(TranslationFailureCatalog::class);
        $high = $catalog->warning(
            'unknown_required_controlled_value',
            ['source_value' => 'Mystery Throw'],
            $baseContext['versions']['warning_rules'],
        );
        $warningContext = $baseContext;
        $warningContext['warnings'] = [$high];
        $warningContext['warning_acknowledgments'] = [$high['warning_id']];
        $withExactAcknowledgment = $this->snapshot(
            $inspection,
            $warningContext,
            'coach-one',
            '2026-07-27T12:00:00-04:00',
        );
        $this->assertNotSame($base['content_hash'], $withExactAcknowledgment['content_hash']);
        $this->assertSame([$high['warning_id']], $withExactAcknowledgment['warning_acknowledgments']);
    }

    public function test_canonical_ordering_scalars_unicode_and_float_policy_are_explicit(): void
    {
        $left = ['z' => ['b' => 2, 'a' => 1], 'name' => 'José ⚾'];
        $right = ['name' => 'José ⚾', 'z' => ['a' => 1, 'b' => 2]];
        $this->assertSame(CanonicalPayload::serialize($left), CanonicalPayload::serialize($right));
        $this->assertSame(CanonicalPayload::sha256($left), CanonicalPayload::sha256($right));

        $this->assertNotSame(
            CanonicalPayload::sha256(['rows' => [['event' => 1], ['event' => 2]]]),
            CanonicalPayload::sha256(['rows' => [['event' => 2], ['event' => 1]]]),
        );
        $this->assertSame('{"empty":[]}', CanonicalPayload::serialize(['empty' => []]));
        $this->assertNotSame(CanonicalPayload::sha256(['value' => 0]), CanonicalPayload::sha256(['value' => null]));
        $this->assertNotSame(CanonicalPayload::sha256(['value' => 0]), CanonicalPayload::sha256(['value' => false]));
        $this->assertNotSame(CanonicalPayload::sha256(['value' => null]), CanonicalPayload::sha256([]));
        $this->assertSame(CanonicalPayload::sha256(['value' => -0.0]), CanonicalPayload::sha256(['value' => 0.0]));
        $this->assertSame('{"value":92.375}', CanonicalPayload::serialize(['value' => 92.375]));

        $policy = app(TranslationWarningPolicy::class);
        $warningA = app(TranslationFailureCatalog::class)->warning('source_quality_concern', ['field' => 'A']);
        $warningB = app(TranslationFailureCatalog::class)->warning('source_quality_concern', ['field' => 'B']);
        $this->assertSame($policy->normalize([$warningA, $warningB]), $policy->normalize([$warningB, $warningA]));

        $inspection = [
            'platform' => 'generic-csv',
            'file' => ['name' => 'ordering.csv', 'extension' => 'csv', 'size_bytes' => 10],
            'players' => [],
            'source_columns' => [],
            'sample_rows' => [['event' => 1], ['event' => 2]],
            'warnings' => [],
        ];
        $context = [
            'source_checksum' => str_repeat('b', 64),
            'destination' => 'assessment',
            'column_mappings' => [
                ['source_column_name' => 'B', 'action' => 'not_importing'],
                ['source_column_name' => 'A', 'action' => 'not_importing'],
            ],
            'versions' => [
                'platform_dictionary' => CertificationVersions::PLATFORM_DICTIONARY,
                'baseball_dictionary' => CertificationVersions::BASEBALL_DICTIONARY,
                'translation_engine' => CertificationVersions::TRANSLATION_ENGINE,
                'translation_review_schema' => CertificationVersions::TRANSLATION_REVIEW_SCHEMA,
                'warning_rules' => CertificationVersions::WARNING_RULES,
            ],
        ];
        $review = app(TranslationReviewPayloadBuilder::class)->build($inspection, $context);
        $reversedContext = $context;
        $reversedContext['column_mappings'] = array_reverse($context['column_mappings']);
        $reversedReview = app(TranslationReviewPayloadBuilder::class)->build($inspection, $reversedContext);
        $this->assertSame([['event' => 1], ['event' => 2]], $review['normalized_sample_records']);
        $this->assertSame($review['column_mappings'], $reversedReview['column_mappings']);

        foreach ([NAN, INF, -INF] as $nonFinite) {
            try {
                CanonicalPayload::serialize(['value' => $nonFinite]);
                $this->fail('A non-finite canonical value was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('non-finite', $exception->getMessage());
            }
        }
    }

    public function test_snapshot_validates_versions_acknowledgments_approver_and_timestamp(): void
    {
        $fixture = $this->fixture('platform-trackman');
        $metadata = $this->metadata($fixture);
        $inspection = app(TrackManInspectionService::class)->inspect($metadata, 'certification-no-team', 'live_ab');
        $context = $this->reviewContext($inspection, $metadata, 'live_ab');
        $builder = app(TranslationReviewPayloadBuilder::class);
        $factory = app(TranslationSnapshotPayloadFactory::class);

        $missingVersion = $context;
        unset($missingVersion['versions']['baseball_dictionary']);
        try {
            $builder->build($inspection, $missingVersion);
            $this->fail('Review finalized without every explicit authoritative version.');
        } catch (TranslationContractException $exception) {
            $this->assertSame('missing_translation_version', $exception->errorCode());
        }

        $review = $builder->build($inspection, $context);
        $snapshot = $factory->create(
            $review,
            ' coach-one ',
            '2026-07-27T12:00:00-04:00',
            [],
            CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
        )->toArray();
        $this->assertSame($review['versions'], array_intersect_key($snapshot['versions'], $review['versions']));
        $this->assertSame($snapshot['schema_version'], $snapshot['versions']['translation_snapshot_schema']);
        $this->assertSame('2026-07-27T16:00:00.000000Z', $snapshot['approval']['approved_at']);

        foreach ([
            ['', '2026-07-27T12:00:00-04:00', CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA, 'invalid_translation_approver'],
            ['coach', '', CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA, 'invalid_translation_approval_timestamp'],
            ['coach', '2026-02-30T12:00:00Z', CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA, 'invalid_translation_approval_timestamp'],
            ['coach', 'not-a-date', CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA, 'invalid_translation_approval_timestamp'],
            ['coach', '2026-07-27T12:00:00Z', '', 'missing_translation_version'],
        ] as [$approver, $timestamp, $schema, $expectedCode]) {
            try {
                $factory->create($review, $approver, $timestamp, [], $schema);
                $this->fail("{$expectedCode} was not enforced.");
            } catch (TranslationContractException $exception) {
                $this->assertSame($expectedCode, $exception->errorCode());
            }
        }

        try {
            $factory->create(
                $review,
                'coach',
                '2026-07-27T12:00:00Z',
                ['unknown-warning'],
                CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
            );
            $this->fail('Unknown acknowledgment was accepted.');
        } catch (TranslationContractException $exception) {
            $this->assertSame('unknown_warning_acknowledgment', $exception->errorCode());
        }
    }

    public function test_snapshot_rejects_correctly_rehashed_internal_version_contradictions(): void
    {
        $fixture = $this->fixture('platform-trackman');
        $metadata = $this->metadata($fixture);
        $inspection = app(TrackManInspectionService::class)->inspect(
            $metadata,
            'certification-no-team',
            'live_ab',
        );
        $review = app(TranslationReviewPayloadBuilder::class)->build(
            $inspection,
            $this->reviewContext($inspection, $metadata, 'live_ab'),
        );
        $policy = app(TranslationWarningPolicy::class);
        $factory = app(TranslationSnapshotPayloadFactory::class);

        $contradictions = [
            'review schema' => static function (array &$payload): void {
                $payload['schema_version'] = '9.9.9';
            },
            'warning rules' => static function (array &$payload) use ($policy): void {
                $payload['warnings'] = [$policy->warning(
                    'version_certification_probe',
                    TranslationWarningSeverity::Informational,
                    'Version certification probe.',
                    ['fixture' => 'platform-trackman'],
                    [],
                    '9.9.9',
                )];
                $payload['approval_status'] = $policy->approvalStatus($payload['warnings'], []);
            },
            'snapshot schema' => static function (array &$payload): void {
                $payload['versions']['translation_snapshot_schema'] = '9.9.9';
            },
        ];

        foreach ($contradictions as $label => $contradict) {
            $contradictoryReview = $review;
            unset($contradictoryReview['review_content_hash']);
            $contradict($contradictoryReview);
            $contradictoryReview['review_content_hash'] = CanonicalPayload::sha256($contradictoryReview);

            try {
                $factory->create(
                    $contradictoryReview,
                    'coach-one',
                    '2026-07-27T12:00:00-04:00',
                    [],
                    CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
                );
                $this->fail("A correctly rehashed {$label} contradiction was accepted.");
            } catch (TranslationContractException $exception) {
                $this->assertSame('translation_version_mismatch', $exception->errorCode(), $label);
            }
        }
    }

    public function test_real_player_and_column_mapping_recommendations_are_deterministic(): void
    {
        $team = Team::factory()->create();
        $this->player($team, 'Thomas', 'Dimitroff');
        $this->player($team, 'Carter', 'Moon');
        $trackman = PlatformDefinition::query()->where('key', 'trackman')->firstOrFail();
        $generic = PlatformDefinition::query()->where('key', 'generic-csv')->firstOrFail();
        $knownFixture = $this->fixture('platform-trackman');
        $knownMetadata = $this->metadata($knownFixture);
        $knownInspection = app(TrackManInspectionService::class)->inspect(
            $knownMetadata,
            (string) $team->id,
            'live_ab',
        );
        $this->assertMappingCertificationIsDeterministic(
            'known platform',
            $knownInspection,
            $team,
            $trackman,
            'live_ab',
            true,
        );

        $genericFixture = $this->fixture('generic-assessment-rows');
        $genericInspection = app(UniversalSpreadsheetInspector::class)->inspect($this->metadata($genericFixture));
        $genericResult = $this->assertMappingCertificationIsDeterministic(
            'generic spreadsheet',
            $genericInspection,
            $team,
            $generic,
            'assessment',
            true,
        );
        $this->assertContains('unresolved', array_column($genericResult['column_recommendations'], 'resolution_source'));

        $exitVelocity = BaseballConcept::query()->where('canonical_key', 'hitting.exit_velocity')->firstOrFail();
        foreach (['ExitSpeed', 'Ball Speed', 'EV'] as $alias) {
            BaseballConceptAlias::query()->updateOrCreate([
                'platform_definition_id' => $generic->id,
                'normalized_alias' => Str::lower(preg_replace('/[^a-z0-9]/i', '', $alias)),
            ], [
                'baseball_concept_id' => $exitVelocity->id,
                'alias' => $alias,
                'relationship_type' => 'exact_equivalent',
                'source_unit_key' => 'mph',
                'confidence' => 100,
                'is_official' => true,
                'status' => 'active',
            ]);
        }
        $conflictFixture = $this->fixture('column-duplicate-concepts');
        $conflictInspection = app(UniversalSpreadsheetInspector::class)->inspect($this->metadata($conflictFixture));
        $conflictResult = $this->assertMappingCertificationIsDeterministic(
            'conflict fixture',
            $conflictInspection,
            $team,
            $generic,
            'cage',
            false,
        );
        $this->assertSame(
            1,
            count(array_unique(array_column($conflictResult['column_recommendations'], 'concept_id'))),
        );
        $this->assertSame(
            [100],
            array_values(array_unique(array_column($conflictResult['column_recommendations'], 'confidence'))),
        );
        $this->assertSame(
            ['hitting.exit_velocity' => ['Ball Speed', 'EV', 'ExitSpeed']],
            $conflictResult['conflicts'],
        );
    }

    /**
     * @param array<string, mixed> $inspection
     *
     * @return array<string, mixed>
     */
    private function assertMappingCertificationIsDeterministic(
        string $label,
        array $inspection,
        Team $team,
        PlatformDefinition $platform,
        string $destination,
        bool $expectsNotImporting,
    ): array {
        $first = $this->mappingCertificationResult($inspection, $team, $platform, $destination);
        $second = $this->mappingCertificationResult($inspection, $team, $platform, $destination);

        $this->assertSame($first, $second, "{$label} mapping services changed between independent runs.");
        $this->assertNotSame([], $first['player_recommendations'], "{$label} did not exercise player matching.");
        $this->assertNotSame([], $first['column_recommendations'], "{$label} did not exercise column mapping.");
        $this->assertSame(
            count($first['column_recommendations']),
            count(array_filter(
                $first['column_recommendations'],
                static fn (array $recommendation): bool => array_key_exists('confidence', $recommendation)
                    && array_key_exists('compatibility', $recommendation),
            )),
            "{$label} did not certify confidence and destination compatibility for every column.",
        );

        $decisions = array_column($first['player_recommendations'], 'decision');
        if ($expectsNotImporting) {
            $this->assertContains('not_importing', $decisions, "{$label} did not certify Not Importing.");
        } else {
            $this->assertNotContains('not_importing', $decisions, "{$label} unexpectedly excluded a roster player.");
        }

        return $first;
    }

    /**
     * @param array<string, mixed> $inspection
     *
     * @return array{
     *   player_recommendations: array<int, array<string, mixed>>,
     *   column_recommendations: array<int, array<string, mixed>>,
     *   conflicts: array<string, array<int, string>>
     * }
     */
    private function mappingCertificationResult(
        array $inspection,
        Team $team,
        PlatformDefinition $platform,
        string $destination,
    ): array {
        $playerMatching = app(PlayerMatchingService::class);
        $playerRecommendations = array_map(
            static function (array $player) use ($playerMatching, $team, $platform): array {
                $suggestions = $playerMatching->suggestions(
                    (string) $team->id,
                    (string) $player['source_name'],
                    $player['external_player_id'] ?? null,
                    (string) $platform->id,
                );
                $selected = $suggestions[0] ?? null;

                return [
                    'source_key' => (string) $player['source_key'],
                    'source_name' => (string) $player['source_name'],
                    'decision' => ($selected['auto_select'] ?? false) ? 'connected' : 'not_importing',
                    'selected_player_id' => ($selected['auto_select'] ?? false)
                        ? $selected['player_id']
                        : null,
                    'suggestions' => $suggestions,
                ];
            },
            (array) ($inspection['players'] ?? []),
        );

        $sourceColumns = array_column((array) ($inspection['source_columns'] ?? []), 'source_column_name');
        $columnRecommendations = app(MappingResolutionService::class)->resolve(
            (string) $team->id,
            (string) $platform->id,
            (string) $inspection['template_fingerprint'],
            $sourceColumns,
        );
        $concepts = BaseballConcept::query()
            ->whereIn('id', array_values(array_filter(array_column($columnRecommendations, 'concept_id'))))
            ->get()
            ->keyBy(fn (BaseballConcept $concept): string => (string) $concept->id);
        $domainKeys = DB::table('baseball_domains')->pluck('key', 'id');
        $compatibility = app(ConceptCompatibilityService::class);
        $columnRecommendations = array_map(
            static function (array $recommendation) use ($compatibility, $concepts, $domainKeys, $destination): array {
                $concept = $concepts->get((string) ($recommendation['concept_id'] ?? ''));
                $recommendation['canonical_key'] = $concept?->canonical_key;
                $recommendation['compatibility'] = $compatibility->classify(
                    $destination,
                    $concept ? ($domainKeys[$concept->domain_id] ?? null) : null,
                );

                return $recommendation;
            },
            $columnRecommendations,
        );

        $mappedFields = [];
        foreach ($columnRecommendations as $recommendation) {
            if (null !== $recommendation['canonical_key']) {
                $mappedFields[$recommendation['canonical_key']][] = $recommendation['source_column_name'];
            }
        }
        $conflicts = array_filter(
            $mappedFields,
            static fn (array $sourceFields): bool => count($sourceFields) > 1,
        );
        foreach ($conflicts as &$sourceFields) {
            sort($sourceFields, SORT_STRING);
        }
        unset($sourceFields);
        ksort($conflicts, SORT_STRING);

        return [
            'player_recommendations' => $playerRecommendations,
            'column_recommendations' => $columnRecommendations,
            'conflicts' => $conflicts,
        ];
    }

    public function testTranslationReviewAndTranslationSnapshotPayloadVectorMatchesTheManifestHashes(): void
    {
        $vector = json_decode(
            file_get_contents(base_path('tests/Fixtures/DataHub/manifests/translation-payload-certification.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $this->assertSame($this->expectedCertificationMetadata(), $vector['certification_metadata']);

        $review = app(TranslationReviewPayloadBuilder::class)->build($vector['inspection'], $vector['context']);
        $snapshot = app(TranslationSnapshotPayloadFactory::class)->create(
            $review,
            $vector['approval']['approving_user_id'],
            $vector['approval']['approved_at'],
            $vector['approval']['warning_acknowledgments'],
            $vector['approval']['translation_snapshot_schema_version'],
        )->toArray();

        $this->assertSame($vector['expected_hashes']['translation_review'], $review['review_content_hash']);
        $this->assertSame($vector['expected_hashes']['translation_snapshot'], $snapshot['content_hash']);
    }

    public function test_certification_manifests_use_the_authoritative_versions(): void
    {
        foreach (['import-certification.json', 'semantic-equivalence.json', 'translation-payload-certification.json'] as $file) {
            $manifest = json_decode(
                file_get_contents(base_path("tests/Fixtures/DataHub/manifests/{$file}")),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $this->assertSame($this->expectedCertificationMetadata(), $manifest['certification_metadata'], $file);
        }
    }

    /** @return array<string, string> */
    private function expectedCertificationMetadata(): array
    {
        return [
            'fixture_generator_version' => CertificationVersions::FIXTURE_GENERATOR,
            'platform_dictionary_version' => CertificationVersions::PLATFORM_DICTIONARY,
            'baseball_dictionary_version' => CertificationVersions::BASEBALL_DICTIONARY,
            'translation_engine_version' => CertificationVersions::TRANSLATION_ENGINE,
            'translation_review_schema_version' => CertificationVersions::TRANSLATION_REVIEW_SCHEMA,
            'translation_snapshot_schema_version' => CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
            'warning_rules_version' => CertificationVersions::WARNING_RULES,
        ];
    }

    /** @param array<string, mixed> $inspection */
    private function reviewContext(array $inspection, ImportFileMetadata $metadata, string $destination): array
    {
        $playerMappings = [];
        foreach ((array) ($inspection['players'] ?? []) as $index => $player) {
            $connected = $index < 2;
            $playerMappings[] = [
                'source_key' => $player['source_key'],
                'source_name' => $player['source_name'],
                'external_player_id' => $player['external_player_id'] ?? null,
                'decision' => $connected ? 'connected' : 'not_importing',
                'fmtrx_player_id' => $connected ? 'certification-player-'.($index + 1) : null,
                'resolution_source' => 'coach_decision',
            ];
        }

        $platformKey = (string) ($inspection['platform'] ?? 'generic-csv');
        $platform = PlatformDefinition::query()->where('key', $platformKey)->firstOrFail();
        $sourceNames = array_column((array) ($inspection['source_columns'] ?? []), 'source_column_name');
        $recommendations = app(MappingResolutionService::class)->resolve(
            'certification-no-team',
            (string) $platform->id,
            (string) ($inspection['template_fingerprint'] ?? hash('sha256', implode('|', $sourceNames))),
            $sourceNames,
        );
        $concepts = BaseballConcept::query()
            ->whereIn('id', array_values(array_filter(array_column($recommendations, 'concept_id'))))
            ->get()
            ->keyBy(fn (BaseballConcept $concept): string => (string) $concept->id);
        $domainKeys = DB::table('baseball_domains')->pluck('key', 'id');
        $compatibility = app(ConceptCompatibilityService::class);
        $columnMappings = [];
        $units = [];
        foreach ($recommendations as $recommendation) {
            $name = (string) $recommendation['source_column_name'];
            $concept = $concepts->get((string) ($recommendation['concept_id'] ?? ''));
            $sourceUnit = $recommendation['source_unit_key'] ?? null;
            $canonicalUnit = $concept?->canonical_unit_key;
            $columnMappings[] = [
                'source_column_name' => $name,
                'normalized_source_column' => $recommendation['normalized_source_column'],
                'action' => $concept ? 'map' : 'not_importing',
                'canonical_key' => $concept?->canonical_key,
                'source_unit' => $sourceUnit,
                'canonical_unit' => $canonicalUnit,
                'relationship_type' => $recommendation['relationship_type'] ?? null,
                'resolution_source' => $recommendation['resolution_source'],
                'confidence' => $recommendation['confidence'],
                'compatibility_level' => $concept
                    ? $compatibility->classify($destination, $domainKeys[$concept->domain_id] ?? null)
                    : 'not_importing',
                'allow_duplicate' => 'session_context.player_identity' === $concept?->canonical_key,
            ];
            if ($concept && (null !== $sourceUnit || null !== $canonicalUnit)) {
                $units[] = [
                    'source_field' => $name,
                    'source_unit' => $sourceUnit,
                    'canonical_unit' => $canonicalUnit,
                ];
            }
        }

        return [
            'source_checksum' => hash_file('sha256', (string) $metadata->path),
            'destination' => $destination,
            'player_mappings' => $playerMappings,
            'column_mappings' => $columnMappings,
            'controlled_value_mappings' => [],
            'units' => $units,
            'conversion_rules' => [],
            'warnings' => [],
            'warning_acknowledgments' => [],
            'exclusions' => [],
            'versions' => [
                'platform_dictionary' => CertificationVersions::PLATFORM_DICTIONARY,
                'baseball_dictionary' => CertificationVersions::BASEBALL_DICTIONARY,
                'translation_engine' => CertificationVersions::TRANSLATION_ENGINE,
                'translation_review_schema' => CertificationVersions::TRANSLATION_REVIEW_SCHEMA,
                'warning_rules' => CertificationVersions::WARNING_RULES,
            ],
            'not_importing_record_count' => count(array_filter(
                $playerMappings,
                static fn (array $mapping): bool => 'not_importing' === $mapping['decision']
            )),
        ];
    }

    /**
     * @param array<string, mixed> $inspection
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function snapshot(
        array $inspection,
        array $context,
        string $approver,
        string $timestamp,
        string $snapshotSchemaVersion = CertificationVersions::TRANSLATION_SNAPSHOT_SCHEMA,
    ): array {
        $review = app(TranslationReviewPayloadBuilder::class)->build($inspection, $context);

        return app(TranslationSnapshotPayloadFactory::class)->create(
            $review,
            $approver,
            $timestamp,
            (array) ($context['warning_acknowledgments'] ?? []),
            $snapshotSchemaVersion,
        )->toArray();
    }

    private function player(Team $team, string $firstName, string $lastName): User
    {
        $user = User::factory()->create(['type' => 'player', 'status' => true]);
        Profile::factory()->create([
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
        PlayerTeam::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

        return $user;
    }

    /** @param array<string, mixed> $first @param array<string, mixed> $second */
    private function assertDeterministicReviewSections(array $first, array $second): void
    {
        foreach ([
            'source_players',
            'source_fields',
            'column_mappings',
            'warnings',
            'exclusions',
            'normalized_sample_records',
        ] as $key) {
            $this->assertSame($first[$key], $second[$key], "{$key} changed between identical runs.");
        }
        $this->assertSame($first, $second, 'Complete Translation Review changed between identical runs.');
        $this->assertSame($first['review_content_hash'], $second['review_content_hash']);
    }

    /** @return array<string, mixed> */
    private function fixture(string $id): array
    {
        $manifest = json_decode(
            file_get_contents(base_path('tests/Fixtures/DataHub/manifests/import-certification.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        return collect($manifest['fixtures'])->firstWhere('id', $id);
    }

    /** @param array<string, mixed> $fixture */
    private function metadata(array $fixture): ImportFileMetadata
    {
        $path = base_path('tests/Fixtures/DataHub/'.$fixture['filename']);
        $mime = match ($fixture['format']) {
            'csv' => 'text/csv',
            'tsv' => 'text/tab-separated-values',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/plain',
        };

        return new ImportFileMetadata(basename($path), filesize($path), $fixture['format'], $mime, $path);
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

    private function assertNoMutationQueries(): void
    {
        $mutations = array_values(array_filter(
            DB::getQueryLog(),
            static fn (array $query): bool => 1 === preg_match(
                '/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i',
                (string) ($query['query'] ?? '')
            )
        ));
        DB::disableQueryLog();

        $this->assertSame([], $mutations, 'Translation certification generated a database mutation query.');
    }
}
