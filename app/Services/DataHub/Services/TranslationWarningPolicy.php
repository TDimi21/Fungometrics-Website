<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

use App\Services\DataHub\Enums\TranslationWarningSeverity;
use App\Services\DataHub\Exceptions\TranslationContractException;
use App\Services\DataHub\Support\CanonicalPayload;
use App\Services\DataHub\Support\CertificationVersions;
use InvalidArgumentException;

final class TranslationWarningPolicy
{
    /**
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $exclusions
     *
     * @return array<string, mixed>
     */
    public function warning(
        string $code,
        TranslationWarningSeverity $severity,
        string $message,
        array $context = [],
        array $exclusions = [],
        ?string $warningRulesVersion = null,
    ): array {
        if (1 !== preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
            throw new InvalidArgumentException('Translation warning codes must use stable snake_case identifiers.');
        }

        $warningRulesVersion ??= CertificationVersions::WARNING_RULES;
        if ('' === trim($warningRulesVersion)) {
            throw new InvalidArgumentException('Translation warnings require an explicit warning-rules version.');
        }

        $context = CanonicalPayload::normalize($context);
        $exclusions = CanonicalPayload::normalize($exclusions);
        $acknowledgmentRequired = $severity->acknowledgmentRequired();
        $approvalBlocked = $severity->approvalBlocked();
        $identity = [
            'code' => $code,
            'severity' => $severity->value,
            'context' => $context,
            'exclusions' => $exclusions,
            'acknowledgment_required' => $acknowledgmentRequired,
            'approval_blocked' => $approvalBlocked,
            'warning_rules_version' => $warningRulesVersion,
        ];

        return [
            'warning_id' => CanonicalPayload::sha256($identity),
            'code' => $code,
            'severity' => $severity->value,
            'message' => $message,
            'context' => $context,
            'exclusions' => $exclusions,
            'acknowledgment_required' => $acknowledgmentRequired,
            'approval_blocked' => $approvalBlocked,
            'warning_rules_version' => $warningRulesVersion,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $warnings
     *
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $warnings): array
    {
        $normalized = array_map(function (array $warning): array {
            $severity = TranslationWarningSeverity::tryFrom((string) ($warning['severity'] ?? ''));
            if (null === $severity) {
                throw new InvalidArgumentException('Every translation warning must use a supported severity.');
            }

            return $this->warning(
                (string) ($warning['code'] ?? ''),
                $severity,
                (string) ($warning['message'] ?? ''),
                (array) ($warning['context'] ?? []),
                (array) ($warning['exclusions'] ?? []),
                (string) ($warning['warning_rules_version'] ?? ''),
            );
        }, $warnings);

        usort($normalized, static function (array $left, array $right): int {
            $severity = TranslationWarningSeverity::from($left['severity'])->sortOrder()
                <=> TranslationWarningSeverity::from($right['severity'])->sortOrder();

            return 0 !== $severity
                ? $severity
                : [$left['code'], $left['warning_id']] <=> [$right['code'], $right['warning_id']];
        });

        $unique = [];
        foreach ($normalized as $warning) {
            $unique[$warning['warning_id']] = $warning;
        }

        return array_values($unique);
    }

    /**
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, string> $acknowledgments
     *
     * @return array{approval_allowed: bool, blocking_warning_ids: array<int, string>, missing_acknowledgment_ids: array<int, string>}
     */
    public function approvalStatus(array $warnings, array $acknowledgments): array
    {
        $warnings = $this->normalize($warnings);
        $acknowledgments = $this->validateAcknowledgments($warnings, $acknowledgments);
        $blocking = [];
        $missing = [];

        foreach ($warnings as $warning) {
            if ($warning['approval_blocked']) {
                $blocking[] = $warning['warning_id'];
            }
            if ($warning['acknowledgment_required'] && ! in_array($warning['warning_id'], $acknowledgments, true)) {
                $missing[] = $warning['warning_id'];
            }
        }

        return [
            'approval_allowed' => [] === $blocking && [] === $missing,
            'blocking_warning_ids' => $blocking,
            'missing_acknowledgment_ids' => $missing,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, string> $acknowledgments
     *
     * @return array<int, string>
     */
    public function validateAcknowledgments(array $warnings, array $acknowledgments): array
    {
        $acknowledgments = array_values(array_unique(array_map('strval', $acknowledgments)));
        sort($acknowledgments, SORT_STRING);
        $warningsById = [];
        foreach ($warnings as $warning) {
            $warningsById[(string) $warning['warning_id']] = $warning;
        }

        foreach ($acknowledgments as $warningId) {
            $warning = $warningsById[$warningId] ?? null;
            if (null === $warning) {
                throw new TranslationContractException(
                    'unknown_warning_acknowledgment',
                    'A warning acknowledgment does not reference an existing warning instance.',
                    ['warning_id' => $warningId],
                );
            }
            if ( ! ($warning['acknowledgment_required'] ?? false)) {
                throw new TranslationContractException(
                    'warning_acknowledgment_not_allowed',
                    'Only acknowledgment-required warnings may be acknowledged.',
                    ['warning_id' => $warningId, 'warning_code' => $warning['code'] ?? null],
                );
            }
        }

        return $acknowledgments;
    }
}
