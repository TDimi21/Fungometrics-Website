<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

use App\Services\DataHub\DTOs\TranslationSnapshotPayload;
use App\Services\DataHub\Exceptions\TranslationContractException;
use App\Services\DataHub\Support\CanonicalPayload;
use DateTimeImmutable;
use DateTimeZone;

final class TranslationSnapshotPayloadFactory
{
    public function __construct(private readonly TranslationWarningPolicy $warningPolicy)
    {
    }

    /**
     * @param array<string, mixed> $review
     * @param array<int, string> $warningAcknowledgments
     */
    public function create(
        array $review,
        string $approvingUserId,
        string $approvalTimestamp,
        array $warningAcknowledgments,
        string $translationSnapshotSchemaVersion,
    ): TranslationSnapshotPayload {
        if ('' === trim($approvingUserId)) {
            throw new TranslationContractException(
                'invalid_translation_approver',
                'Translation Snapshot approval requires an approving user identifier.',
            );
        }
        $approvalTimestamp = $this->approvalTimestamp($approvalTimestamp);
        if ('' === trim($translationSnapshotSchemaVersion)) {
            throw new TranslationContractException(
                'missing_translation_version',
                'Translation Snapshot requires an explicit schema version.',
                ['missing_version' => 'translation_snapshot_schema'],
            );
        }

        $suppliedReviewHash = (string) ($review['review_content_hash'] ?? '');
        if ('' === $suppliedReviewHash) {
            throw new TranslationContractException(
                'missing_translation_review_hash',
                'Translation Snapshot requires a hashed approved Translation Review.',
            );
        }
        $reviewHashInput = $review;
        unset($reviewHashInput['review_content_hash']);
        $recomputedReviewHash = CanonicalPayload::sha256($reviewHashInput);
        if ( ! hash_equals($suppliedReviewHash, $recomputedReviewHash)) {
            throw new TranslationContractException(
                'translation_review_hash_mismatch',
                'Translation Review content changed after its approval hash was produced.',
                [
                    'supplied_hash' => $suppliedReviewHash,
                    'recomputed_hash' => $recomputedReviewHash,
                ],
            );
        }

        $reviewVersions = $this->reviewVersions($review, $translationSnapshotSchemaVersion);
        $warnings = $this->warningPolicy->normalize((array) ($review['warnings'] ?? []));
        foreach ($warnings as $warning) {
            if ($warning['warning_rules_version'] !== $reviewVersions['warning_rules']) {
                throw new TranslationContractException(
                    'translation_version_mismatch',
                    'A Translation Review warning was produced under a different warning-rules version.',
                    [
                        'review_warning_rules_version' => $reviewVersions['warning_rules'],
                        'warning_rules_version' => $warning['warning_rules_version'],
                        'warning_code' => $warning['code'],
                    ],
                );
            }
        }
        $reviewAcknowledgments = $this->warningPolicy->validateAcknowledgments(
            $warnings,
            (array) ($review['warning_acknowledgments'] ?? []),
        );
        $warningAcknowledgments = $this->warningPolicy->validateAcknowledgments($warnings, $warningAcknowledgments);
        if ($reviewAcknowledgments !== $warningAcknowledgments) {
            throw new TranslationContractException(
                'translation_review_approval_mismatch',
                'Snapshot warning acknowledgments must exactly match the approved Translation Review.',
            );
        }
        $approvalStatus = $this->warningPolicy->approvalStatus($warnings, $warningAcknowledgments);
        if ( ! $approvalStatus['approval_allowed']) {
            throw new TranslationContractException(
                'translation_approval_blocked',
                'Translation Snapshot creation is blocked by unresolved warnings.',
                $approvalStatus,
            );
        }

        $snapshot = [
            'schema_version' => $translationSnapshotSchemaVersion,
            'translation_review_content_hash' => $suppliedReviewHash,
            'source_identity' => $review['source_summary']['identity'] ?? [],
            'source_checksum' => $review['source_summary']['checksum'] ?? null,
            'checksum_algorithm' => $review['source_summary']['checksum_algorithm'] ?? 'sha256',
            'platform' => $review['source_summary']['platform'] ?? null,
            'platform_recognition_evidence' => $review['source_summary']['platform_recognition_evidence'] ?? [],
            'destination' => $review['source_summary']['destination'] ?? null,
            'file_structure' => $review['source_summary']['file_structure'] ?? [],
            'source_players' => $review['source_players'] ?? [],
            'player_mappings' => $review['player_mappings'] ?? [],
            'not_importing_players' => $review['not_importing_players'] ?? [],
            'source_fields' => $review['source_fields'] ?? [],
            'column_mappings' => $review['column_mappings'] ?? [],
            'not_importing_fields' => $review['not_importing_fields'] ?? [],
            'controlled_value_mappings' => $review['controlled_value_mappings'] ?? [],
            'units' => $review['units'] ?? [],
            'conversion_rules' => $review['conversion_rules'] ?? [],
            'exclusions' => $review['exclusions'] ?? [],
            'warnings' => $warnings,
            'warning_acknowledgments' => $warningAcknowledgments,
            'normalized_interpretation' => $review['normalized_sample_records'] ?? [],
            'versions' => [
                'platform_dictionary' => (string) $reviewVersions['platform_dictionary'],
                'baseball_dictionary' => (string) $reviewVersions['baseball_dictionary'],
                'translation_engine' => (string) $reviewVersions['translation_engine'],
                'translation_review_schema' => (string) $reviewVersions['translation_review_schema'],
                'translation_snapshot_schema' => $translationSnapshotSchemaVersion,
                'warning_rules' => (string) $reviewVersions['warning_rules'],
            ],
            'approval' => [
                'approving_user_id' => $approvingUserId,
                'approved_at' => $approvalTimestamp,
            ],
        ];
        $snapshot = CanonicalPayload::normalize($snapshot);
        $snapshot['content_hash_algorithm'] = 'sha256';
        $snapshot['content_hash'] = CanonicalPayload::sha256($snapshot);

        return new TranslationSnapshotPayload(CanonicalPayload::normalize($snapshot));
    }

    /**
     * @param array<string, mixed> $review
     *
     * @return array<string, mixed>
     */
    private function reviewVersions(array $review, string $translationSnapshotSchemaVersion): array
    {
        $reviewVersions = (array) ($review['versions'] ?? []);
        foreach ([
            'platform_dictionary',
            'baseball_dictionary',
            'translation_engine',
            'translation_review_schema',
            'warning_rules',
        ] as $requiredVersion) {
            if ('' === trim((string) ($reviewVersions[$requiredVersion] ?? ''))) {
                throw new TranslationContractException(
                    'missing_translation_version',
                    'The approved Translation Review is missing an authoritative version.',
                    ['missing_version' => $requiredVersion],
                );
            }
        }

        $reviewSchemaVersion = (string) ($review['schema_version'] ?? '');
        if ($reviewSchemaVersion !== (string) $reviewVersions['translation_review_schema']) {
            throw new TranslationContractException(
                'translation_version_mismatch',
                'The Translation Review schema fields contradict each other.',
                [
                    'review_schema_version' => $reviewSchemaVersion,
                    'recorded_review_schema_version' => $reviewVersions['translation_review_schema'],
                ],
            );
        }

        $reviewSnapshotSchemaVersion = $reviewVersions['translation_snapshot_schema'] ?? null;
        if (null !== $reviewSnapshotSchemaVersion
            && (string) $reviewSnapshotSchemaVersion !== $translationSnapshotSchemaVersion) {
            throw new TranslationContractException(
                'translation_version_mismatch',
                'The Translation Snapshot schema fields contradict each other.',
                [
                    'snapshot_schema_version' => $translationSnapshotSchemaVersion,
                    'review_snapshot_schema_version' => $reviewSnapshotSchemaVersion,
                ],
            );
        }

        return $reviewVersions;
    }

    private function approvalTimestamp(string $timestamp): string
    {
        $timestamp = trim($timestamp);
        if (1 !== preg_match(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/',
            $timestamp,
        )) {
            throw new TranslationContractException(
                'invalid_translation_approval_timestamp',
                'Translation Snapshot approval requires an RFC 3339 timestamp.',
            );
        }

        $normalizedInput = str_ends_with($timestamp, 'Z')
            ? mb_substr($timestamp, 0, -1).'+00:00'
            : $timestamp;
        $format = str_contains($normalizedInput, '.') ? '!Y-m-d\TH:i:s.uP' : '!Y-m-d\TH:i:sP';
        $parsed = DateTimeImmutable::createFromFormat($format, $normalizedInput);
        $errors = DateTimeImmutable::getLastErrors();
        if (false === $parsed || (is_array($errors) && (0 !== $errors['warning_count'] || 0 !== $errors['error_count']))) {
            throw new TranslationContractException(
                'invalid_translation_approval_timestamp',
                'Translation Snapshot approval requires a valid RFC 3339 timestamp.',
            );
        }

        return $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
