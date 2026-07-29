<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

use App\Services\DataHub\Enums\TranslationWarningSeverity;

final class TranslationWarningNormalizer
{
    public function __construct(
        private readonly TranslationWarningPolicy $warnings,
        private readonly TranslationFailureCatalog $failures,
    ) {
    }

    /**
     * @param array<string, mixed> $inspection
     * @param array<int, mixed> $contextWarnings
     *
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $inspection, array $contextWarnings, string $warningRulesVersion): array
    {
        $normalized = [];
        foreach ($contextWarnings as $index => $warning) {
            $normalized[] = $this->normalizeOne(
                $warning,
                ['origin' => 'translation_context', 'ordinal' => $index],
                $warningRulesVersion,
            );
        }
        foreach ((array) ($inspection['warnings'] ?? []) as $index => $warning) {
            $normalized[] = $this->normalizeOne(
                $warning,
                ['origin' => 'inspection', 'ordinal' => $index],
                $warningRulesVersion,
            );
        }
        foreach ((array) ($inspection['source_columns'] ?? []) as $column) {
            foreach ((array) ($column['warnings'] ?? []) as $index => $warning) {
                $normalized[] = $this->normalizeOne(
                    $warning,
                    [
                        'origin' => 'source_field',
                        'source_column_name' => (string) ($column['source_column_name'] ?? ''),
                        'ordinal' => $index,
                    ],
                    $warningRulesVersion,
                );
            }
        }

        return $this->warnings->normalize($normalized);
    }

    /** @param array<string, mixed> $fallbackContext */
    private function normalizeOne(mixed $warning, array $fallbackContext, string $warningRulesVersion): array
    {
        if (is_array($warning) && isset($warning['code'], $warning['severity'])) {
            return $this->warnings->warning(
                (string) $warning['code'],
                TranslationWarningSeverity::from((string) $warning['severity']),
                (string) ($warning['message'] ?? ''),
                (array) ($warning['context'] ?? $fallbackContext),
                (array) ($warning['exclusions'] ?? []),
                (string) ($warning['warning_rules_version'] ?? $warningRulesVersion),
            );
        }

        $message = (string) $warning;
        $lower = mb_strtolower($message);
        if (str_contains($lower, 'destination is incompatible')) {
            return $this->failures->warning(
                'destination_incompatibility',
                $fallbackContext,
                $warningRulesVersion,
            );
        }
        if (str_contains($lower, 'requires controlled-value review')) {
            return $this->failures->warning(
                'unknown_required_controlled_value',
                $fallbackContext,
                $warningRulesVersion,
            );
        }

        return $this->warnings->warning(
            'inspection_warning',
            TranslationWarningSeverity::Warning,
            $message,
            $fallbackContext,
            [],
            $warningRulesVersion,
        );
    }
}
