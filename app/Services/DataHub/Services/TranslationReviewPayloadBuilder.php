<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

use App\Services\DataHub\Exceptions\TranslationContractException;
use App\Services\DataHub\Support\CanonicalPayload;
use InvalidArgumentException;

final class TranslationReviewPayloadBuilder
{
    public function __construct(
        private readonly TranslationWarningPolicy $warningPolicy,
        private readonly TranslationWarningNormalizer $warningNormalizer,
        private readonly TranslationReviewFailureDetector $failureDetector,
    ) {
    }

    /**
     * @param array<string, mixed> $inspection
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function build(array $inspection, array $context): array
    {
        $checksum = mb_strtolower(trim((string) ($context['source_checksum'] ?? '')));
        if (1 !== preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            throw new InvalidArgumentException('Translation Review requires an explicit SHA-256 source checksum.');
        }

        $versions = $this->versions((array) ($context['versions'] ?? []));
        $warnings = $this->warningPolicy->normalize(array_merge(
            $this->warningNormalizer->normalize(
                $inspection,
                (array) ($context['warnings'] ?? []),
                $versions['warning_rules'],
            ),
            $this->failureDetector->detect($inspection, $context, $versions['warning_rules']),
        ));
        foreach ($warnings as $warning) {
            if ($warning['warning_rules_version'] !== $versions['warning_rules']) {
                throw new TranslationContractException(
                    'translation_version_mismatch',
                    'A warning was produced under a different warning-rules version.',
                    [
                        'review_warning_rules_version' => $versions['warning_rules'],
                        'warning_rules_version' => $warning['warning_rules_version'],
                    ],
                );
            }
        }
        $acknowledgments = $this->sortedStrings((array) ($context['warning_acknowledgments'] ?? []));
        $playerMappings = $this->playerMappings((array) ($context['player_mappings'] ?? []));
        $columnMappings = $this->columnMappings((array) ($context['column_mappings'] ?? []));
        $notImportingPlayers = array_values(array_filter(
            $playerMappings,
            static fn (array $mapping): bool => 'not_importing' === $mapping['decision']
        ));
        $notImportingFields = array_values(array_filter(
            $columnMappings,
            static fn (array $mapping): bool => 'map' !== $mapping['action']
        ));
        $exclusions = array_merge(
            (array) ($context['exclusions'] ?? []),
            array_map(static fn (array $mapping): array => [
                'scope' => 'source_player',
                'source_key' => $mapping['source_key'],
                'reason_code' => 'not_importing',
            ], $notImportingPlayers),
            array_map(static fn (array $mapping): array => [
                'scope' => 'source_field',
                'source_column_name' => $mapping['source_column_name'],
                'reason_code' => $mapping['action'],
            ], $notImportingFields),
        );

        $payload = [
            'schema_version' => $versions['translation_review_schema'],
            'source_summary' => [
                'identity' => [
                    'file_name' => basename((string) ($inspection['file']['name'] ?? $context['file_name'] ?? '')),
                    'file_type' => mb_strtolower((string) ($inspection['file']['extension'] ?? $context['file_type'] ?? '')),
                    'size_bytes' => isset($inspection['file']['size_bytes']) ? (int) $inspection['file']['size_bytes'] : null,
                ],
                'checksum_algorithm' => 'sha256',
                'checksum' => $checksum,
                'platform' => (string) ($inspection['platform'] ?? $context['platform'] ?? ''),
                'platform_recognition_evidence' => (array) ($context['platform_recognition_evidence'] ?? $inspection['detected_format'] ?? []),
                'destination' => (string) ($context['destination'] ?? ''),
                'file_structure' => (array) ($context['file_structure'] ?? $this->fileStructure($inspection)),
                'worksheets' => (array) ($context['worksheets'] ?? $inspection['normalized_inspection']['worksheets'] ?? []),
            ],
            'source_players' => $this->sourcePlayers((array) ($inspection['players'] ?? [])),
            'player_mappings' => $playerMappings,
            'not_importing_players' => $notImportingPlayers,
            'source_fields' => $this->sourceFields((array) ($inspection['source_columns'] ?? [])),
            'column_mappings' => $columnMappings,
            'not_importing_fields' => $notImportingFields,
            'controlled_value_mappings' => $this->sortRecords(
                (array) ($context['controlled_value_mappings'] ?? []),
                ['source_field', 'source_value', 'canonical_value']
            ),
            'units' => $this->sortRecords((array) ($context['units'] ?? []), ['source_field', 'source_unit', 'canonical_unit']),
            'conversion_rules' => $this->sortRecords(
                (array) ($context['conversion_rules'] ?? []),
                ['source_field', 'transformation_key']
            ),
            'warnings' => $warnings,
            'warning_acknowledgments' => $acknowledgments,
            'approval_status' => $this->warningPolicy->approvalStatus($warnings, $acknowledgments),
            'exclusions' => $this->sortRecords($exclusions, ['scope', 'source_key', 'source_column_name', 'reason_code']),
            'not_importing_summary' => [
                'players' => count($notImportingPlayers),
                'fields' => count($notImportingFields),
                'records' => (int) ($context['not_importing_record_count'] ?? 0),
            ],
            'normalized_sample_records' => CanonicalPayload::normalize(
                (array) ($context['normalized_sample_records'] ?? $inspection['sample_rows'] ?? [])
            ),
            'versions' => $versions,
        ];
        $payload = CanonicalPayload::normalize($payload);
        $payload['review_content_hash'] = CanonicalPayload::sha256($payload);

        return CanonicalPayload::normalize($payload);
    }

    /** @return array<string, mixed> */
    private function fileStructure(array $inspection): array
    {
        $normalized = (array) ($inspection['normalized_inspection'] ?? []);

        return array_filter([
            'layout' => $normalized['detected_layout'] ?? $inspection['detected_format']['display_type'] ?? $inspection['detected_format']['data_type'] ?? null,
            'selected_worksheet_index' => $normalized['selected_worksheet_index'] ?? null,
            'header_row' => $normalized['header_row'] ?? $inspection['detected_format']['header_row'] ?? null,
            'first_data_row' => $normalized['first_data_row'] ?? null,
            'player_column' => $normalized['player_column'] ?? null,
            'player_id_column' => $normalized['player_id_column'] ?? null,
            'date_column' => $normalized['date_column'] ?? null,
            'ignored_rows' => $normalized['ignored_rows'] ?? [],
            'ignored_columns' => $normalized['ignored_columns'] ?? [],
        ], static fn (mixed $value): bool => null !== $value);
    }

    /** @return array<int, array<string, mixed>> */
    private function sourcePlayers(array $players): array
    {
        $players = array_map(function (array $player): array {
            $roles = $this->sortedStrings((array) ($player['roles'] ?? $player['data_types'] ?? []));

            return CanonicalPayload::normalize([
                'source_key' => (string) ($player['source_key'] ?? ''),
                'source_name' => (string) ($player['source_name'] ?? $player['external_name'] ?? ''),
                'external_player_id' => $player['external_player_id'] ?? $player['external_identifier'] ?? null,
                'roles' => $roles,
                'row_count' => (int) ($player['row_count'] ?? 0),
                'identity_missing' => (bool) ($player['identity_missing'] ?? false),
            ]);
        }, $players);

        return $this->sortRecords($players, ['source_key']);
    }

    /** @return array<int, array<string, mixed>> */
    private function playerMappings(array $mappings): array
    {
        $records = [];
        foreach ($mappings as $sourceKey => $mapping) {
            if ( ! is_array($mapping)) {
                $mapping = [
                    'source_key' => is_string($sourceKey) ? $sourceKey : '',
                    'fmtrx_player_id' => $mapping,
                    'decision' => null === $mapping || '' === $mapping ? 'not_importing' : 'connected',
                ];
            }
            $decision = (string) ($mapping['decision'] ?? (
                ! empty($mapping['not_importing']) || empty($mapping['fmtrx_player_id'])
                    ? 'not_importing'
                    : 'connected'
            ));
            $records[] = CanonicalPayload::normalize([
                'source_key' => (string) ($mapping['source_key'] ?? (is_string($sourceKey) ? $sourceKey : '')),
                'source_name' => (string) ($mapping['source_name'] ?? ''),
                'external_player_id' => $mapping['external_player_id'] ?? null,
                'decision' => $decision,
                'fmtrx_player_id' => 'connected' === $decision ? (string) ($mapping['fmtrx_player_id'] ?? '') : null,
                'resolution_source' => (string) ($mapping['resolution_source'] ?? 'coach_decision'),
            ]);
        }

        return $this->sortRecords($records, ['source_key']);
    }

    /** @return array<int, array<string, mixed>> */
    private function sourceFields(array $fields): array
    {
        return array_map(static fn (array $field): array => CanonicalPayload::normalize([
            'source_column_name' => (string) ($field['source_column_name'] ?? ''),
            'normalized_source_column' => (string) ($field['normalized_source_column'] ?? ''),
            'sample_values' => array_values((array) ($field['sample_values'] ?? [])),
            'inferred_data_type' => $field['details']['inferred_data_type'] ?? null,
            'populated_count' => isset($field['details']['populated_count']) ? (int) $field['details']['populated_count'] : null,
        ]), $fields);
    }

    /** @return array<int, array<string, mixed>> */
    private function columnMappings(array $mappings): array
    {
        $records = array_map(static function (array $mapping): array {
            $action = (string) ($mapping['action'] ?? 'not_importing');

            return CanonicalPayload::normalize([
                'source_column_name' => (string) ($mapping['source_column_name'] ?? ''),
                'normalized_source_column' => (string) ($mapping['normalized_source_column'] ?? ''),
                'action' => $action,
                'canonical_key' => 'map' === $action ? ($mapping['canonical_key'] ?? null) : null,
                'source_unit' => $mapping['source_unit'] ?? $mapping['source_unit_key'] ?? null,
                'canonical_unit' => $mapping['canonical_unit'] ?? $mapping['canonical_unit_key'] ?? null,
                'transformation_key' => $mapping['transformation_key'] ?? null,
                'relationship_type' => $mapping['relationship_type'] ?? null,
                'resolution_source' => $mapping['resolution_source'] ?? null,
                'confidence' => isset($mapping['confidence']) ? (int) $mapping['confidence'] : null,
                'compatibility_level' => $mapping['compatibility_level'] ?? null,
            ]);
        }, $mappings);

        return $this->sortRecords($records, ['source_column_name']);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @param array<int, string> $keys
     *
     * @return array<int, array<string, mixed>>
     */
    private function sortRecords(array $records, array $keys): array
    {
        $records = array_map(static fn (array $record): array => CanonicalPayload::normalize($record), $records);
        usort($records, static function (array $left, array $right) use ($keys): int {
            foreach ($keys as $key) {
                $comparison = (string) ($left[$key] ?? '') <=> (string) ($right[$key] ?? '');
                if (0 !== $comparison) {
                    return $comparison;
                }
            }

            return CanonicalPayload::serialize($left) <=> CanonicalPayload::serialize($right);
        });

        return $records;
    }

    /** @param array<int, mixed> $values */
    private function sortedStrings(array $values): array
    {
        $values = array_values(array_unique(array_map('strval', $values)));
        sort($values, SORT_STRING);

        return $values;
    }

    /**
     * @param array<string, mixed> $versions
     *
     * @return array{
     *   platform_dictionary: string,
     *   baseball_dictionary: string,
     *   translation_engine: string,
     *   translation_review_schema: string,
     *   warning_rules: string
     * }
     */
    private function versions(array $versions): array
    {
        $required = [
            'platform_dictionary',
            'baseball_dictionary',
            'translation_engine',
            'translation_review_schema',
            'warning_rules',
        ];
        foreach ($required as $key) {
            if ('' === trim((string) ($versions[$key] ?? ''))) {
                throw new TranslationContractException(
                    'missing_translation_version',
                    'Translation Review requires every authoritative version.',
                    ['missing_version' => $key],
                );
            }
        }

        return array_intersect_key(
            array_map('strval', $versions),
            array_flip($required),
        );
    }
}
