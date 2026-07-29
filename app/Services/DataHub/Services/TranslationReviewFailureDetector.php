<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

final class TranslationReviewFailureDetector
{
    public function __construct(private readonly TranslationFailureCatalog $failures)
    {
    }

    /**
     * @param array<string, mixed> $inspection
     * @param array<string, mixed> $context
     *
     * @return array<int, array<string, mixed>>
     */
    public function detect(array $inspection, array $context, string $warningRulesVersion): array
    {
        $warnings = [];
        $recognitionCandidates = array_values(array_unique(array_filter(array_map(
            'strval',
            (array) ($context['platform_recognition_candidates'] ?? [])
        ))));
        if (count($recognitionCandidates) > 1 && ! ($context['platform_confirmed'] ?? false)) {
            $warnings[] = $this->failures->warning('ambiguous_platform', [
                'candidates' => $recognitionCandidates,
            ], $warningRulesVersion);
        }

        $columnMappings = (array) ($context['column_mappings'] ?? []);
        foreach ($columnMappings as $mapping) {
            if ( ! is_array($mapping) || 'map' !== ($mapping['action'] ?? 'not_importing')) {
                continue;
            }
            $sourceField = (string) ($mapping['source_column_name'] ?? '');
            if (($mapping['required'] ?? false)
                && empty($mapping['source_unit'])
                && empty($mapping['source_unit_key'])
                && ! ($mapping['unitless'] ?? false)) {
                $warnings[] = $this->failures->warning('unknown_required_unit', [
                    'source_field' => $sourceField,
                    'canonical_key' => $mapping['canonical_key'] ?? null,
                ], $warningRulesVersion);
            }
            if ('incompatible' === ($mapping['compatibility_level'] ?? null)) {
                $warnings[] = $this->failures->warning('destination_incompatibility', [
                    'source_field' => $sourceField,
                    'canonical_key' => $mapping['canonical_key'] ?? null,
                    'destination' => $context['destination'] ?? null,
                ], $warningRulesVersion);
            }
        }

        foreach ((array) ($context['controlled_value_mappings'] ?? []) as $mapping) {
            if ( ! is_array($mapping)
                || ! ($mapping['required'] ?? false)
                || ! empty($mapping['canonical_value'])) {
                continue;
            }
            $warnings[] = $this->failures->warning('unknown_required_controlled_value', [
                'source_field' => $mapping['source_field'] ?? null,
                'source_value' => $mapping['source_value'] ?? null,
            ], $warningRulesVersion);
        }

        $mappedConcepts = [];
        foreach ($columnMappings as $mapping) {
            if ( ! is_array($mapping)
                || 'map' !== ($mapping['action'] ?? 'not_importing')
                || empty($mapping['canonical_key'])
                || ($mapping['allow_duplicate'] ?? false)) {
                continue;
            }
            $mappedConcepts[(string) $mapping['canonical_key']][] = (string) ($mapping['source_column_name'] ?? '');
        }
        foreach ($mappedConcepts as $canonicalKey => $sourceFields) {
            if (count($sourceFields) < 2) {
                continue;
            }
            sort($sourceFields, SORT_STRING);
            $warnings[] = $this->failures->warning('conflicting_mappings', [
                'canonical_key' => $canonicalKey,
                'source_fields' => $sourceFields,
            ], $warningRulesVersion);
        }

        foreach ((array) ($context['player_mappings'] ?? []) as $sourceKey => $mapping) {
            if ( ! is_array($mapping)
                || ! ($mapping['required'] ?? false)
                || (
                    'connected' === ($mapping['decision'] ?? null)
                    && ! empty($mapping['fmtrx_player_id'])
                )) {
                continue;
            }
            $warnings[] = $this->failures->warning('unresolved_required_player', [
                'source_key' => (string) ($mapping['source_key'] ?? (is_string($sourceKey) ? $sourceKey : '')),
                'source_name' => $mapping['source_name'] ?? null,
            ], $warningRulesVersion);
        }

        foreach ((array) ($inspection['certified_warnings'] ?? []) as $warning) {
            if (is_array($warning) && isset($warning['code'])) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }
}
