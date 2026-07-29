<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

use App\Services\DataHub\Enums\TranslationWarningSeverity;
use InvalidArgumentException;

final class TranslationFailureCatalog
{
    /**
     * @var array<string, array{severity: TranslationWarningSeverity, message: string, exclusion_scope: string|null}>
     */
    private const DEFINITIONS = [
        'source_context_available' => [
            'severity' => TranslationWarningSeverity::Informational,
            'message' => 'Source context was detected and remains available for review.',
            'exclusion_scope' => null,
        ],
        'source_quality_concern' => [
            'severity' => TranslationWarningSeverity::Warning,
            'message' => 'Source quality requires coach review.',
            'exclusion_scope' => null,
        ],
        'malformed_csv' => [
            'severity' => TranslationWarningSeverity::Blocking,
            'message' => 'The CSV structure is malformed and cannot be approved.',
            'exclusion_scope' => 'source_file',
        ],
        'missing_header' => [
            'severity' => TranslationWarningSeverity::Blocking,
            'message' => 'A required header row could not be identified.',
            'exclusion_scope' => 'source_file',
        ],
        'ambiguous_platform' => [
            'severity' => TranslationWarningSeverity::Blocking,
            'message' => 'The source platform is ambiguous and must be confirmed.',
            'exclusion_scope' => 'source_file',
        ],
        'unknown_required_unit' => [
            'severity' => TranslationWarningSeverity::Blocking,
            'message' => 'A required source unit is unknown.',
            'exclusion_scope' => 'source_field',
        ],
        'destination_incompatibility' => [
            'severity' => TranslationWarningSeverity::Blocking,
            'message' => 'A required concept is incompatible with the selected destination.',
            'exclusion_scope' => 'source_field',
        ],
        'unknown_required_controlled_value' => [
            'severity' => TranslationWarningSeverity::HighSeverity,
            'message' => 'A required controlled value is unknown and must be acknowledged or excluded.',
            'exclusion_scope' => 'source_value',
        ],
        'conflicting_mappings' => [
            'severity' => TranslationWarningSeverity::Blocking,
            'message' => 'Conflicting mappings must be resolved before approval.',
            'exclusion_scope' => 'mapping',
        ],
        'unresolved_required_player' => [
            'severity' => TranslationWarningSeverity::Blocking,
            'message' => 'A required source player is unresolved.',
            'exclusion_scope' => 'source_player',
        ],
        'unsupported_file_type' => [
            'severity' => TranslationWarningSeverity::Blocking,
            'message' => 'The source file type is not supported.',
            'exclusion_scope' => 'source_file',
        ],
        'corrupted_spreadsheet' => [
            'severity' => TranslationWarningSeverity::Blocking,
            'message' => 'The spreadsheet is corrupted and cannot be inspected safely.',
            'exclusion_scope' => 'source_file',
        ],
    ];

    public function __construct(private readonly TranslationWarningPolicy $warnings)
    {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function warning(string $code, array $context = [], ?string $warningRulesVersion = null): array
    {
        $definition = self::DEFINITIONS[$code] ?? null;
        if (null === $definition) {
            throw new InvalidArgumentException("Unknown Translation Engine failure code: {$code}");
        }

        $exclusions = null === $definition['exclusion_scope']
            ? []
            : [[
                'scope' => $definition['exclusion_scope'],
                'reason_code' => $code,
                'context' => $context,
            ]];

        return $this->warnings->warning(
            $code,
            $definition['severity'],
            $definition['message'],
            $context,
            $exclusions,
            $warningRulesVersion,
        );
    }
}
