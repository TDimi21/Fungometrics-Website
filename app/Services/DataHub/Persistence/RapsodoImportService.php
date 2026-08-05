<?php

declare(strict_types=1);

namespace App\Services\DataHub\Persistence;

use App\Models\MappingTemplate;
use App\Models\MappingTemplateVersion;
use App\Models\PlatformDefinition;
use App\Models\User;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\Dictionary\UnitConversionService;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Platforms\Rapsodo\RapsodoParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class RapsodoImportService
{
    public function __construct(
        private readonly RapsodoParser $parser,
        private readonly UnitConversionService $units,
    ) {
    }

    /** @return array<string, mixed> */
    public function import(User $user, string $teamId, string $playerId, string $destination, string $fingerprint, UploadedFile $file): array
    {
        $platform = PlatformDefinition::query()->where('key', 'rapsodo')->where('is_active', true)->firstOrFail();
        $checksum = hash_file('sha256', $file->getRealPath());
        if (DB::table('translation_snapshots')->where('team_id', $teamId)
            ->where('platform_definition_id', $platform->id)->where('source_file_checksum', $checksum)->exists()) {
            throw new RuntimeException('This Rapsodo workbook has already been imported for this team.');
        }

        $template = MappingTemplate::query()->where('team_id', $teamId)
            ->where('platform_definition_id', $platform->id)
            ->where('template_fingerprint', $fingerprint)
            ->firstOrFail();
        $version = MappingTemplateVersion::query()->whereKey($template->current_version_id)
            ->where('status', 'approved')->firstOrFail();
        $entries = DB::table('mapping_entries as entry')
            ->join('baseball_concepts as concept', 'concept.id', '=', 'entry.baseball_concept_id')
            ->leftJoin('unit_definitions as source_unit', 'source_unit.id', '=', 'entry.source_unit_id')
            ->leftJoin('unit_definitions as canonical_unit', 'canonical_unit.id', '=', 'entry.canonical_unit_id')
            ->where('entry.mapping_template_version_id', $version->id)
            ->where('entry.action', 'map')
            ->whereNotNull('entry.baseball_concept_id')
            ->get([
                'entry.source_column_name', 'entry.baseball_concept_id', 'entry.transformation_key', 'entry.metadata',
                'concept.canonical_key', 'concept.canonical_unit_key',
                'source_unit.key as source_unit_key', 'canonical_unit.key as approved_canonical_unit_key',
            ])->keyBy('source_column_name');
        if ($entries->isEmpty()) {
            throw new RuntimeException('The approved mapping has no columns selected for import.');
        }
        if (!$entries->contains(fn ($entry): bool => 'pitching.tagged_pitch_type' === $entry->canonical_key)) {
            throw new RuntimeException('The approved Rapsodo mapping must include Pitch Type before importing.');
        }

        $metadata = $this->metadata($file);
        $workbook = $this->parser->workbook($metadata);
        $actualFingerprint = app(TemplateFingerprintService::class)->fingerprint($workbook['headers']);
        if ($actualFingerprint !== $fingerprint) {
            throw new RuntimeException('The uploaded file no longer matches the approved column mapping. Inspect it again.');
        }
        $rows = iterator_to_array($this->parser->parse($metadata), false);
        if ([] === $rows) {
            throw new RuntimeException('The Rapsodo workbook contains no importable pitch rows.');
        }

        $sessionDate = $this->sessionDate($file->getClientOriginalName(), (string) $workbook['worksheet']);
        $snapshotId = (string) Str::uuid();
        $storageKey = "data-hub/imports/{$snapshotId}/".basename($file->getClientOriginalName());
        Storage::disk('local')->put($storageKey, file_get_contents($file->getRealPath()));

        try {
            return DB::transaction(function () use ($user, $teamId, $playerId, $destination, $file, $platform, $checksum, $snapshotId, $storageKey, $version, $entries, $rows, $workbook, $sessionDate): array {
                $now = now();
                DB::table('translation_snapshots')->insert([
                    'id' => $snapshotId, 'team_id' => $teamId, 'platform_definition_id' => $platform->id,
                    'mapping_template_version_id' => $version->id, 'approved_by' => $user->id, 'player_id' => $playerId,
                    'destination' => $destination, 'source_file_name' => basename($file->getClientOriginalName()),
                    'source_file_checksum' => $checksum, 'source_storage_key' => $storageKey,
                    'source_file_size' => $file->getSize(),
                    'snapshot' => json_encode([
                        'headers' => $workbook['headers'], 'worksheet' => $workbook['worksheet'],
                        'header_row' => $workbook['header_row'], 'mapping_version' => $version->version,
                        'session_date' => $sessionDate,
                    ], JSON_THROW_ON_ERROR),
                    'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $batchId = (string) Str::uuid();
                DB::table('import_batches')->insert([
                    'id' => $batchId, 'translation_snapshot_id' => $snapshotId, 'initiated_by' => $user->id,
                    'status' => 'processing', 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $sessionId = (string) Str::uuid();
                $firstOccurredAt = $this->occurredAt($sessionDate['date'], $rows[0]['event_time_display'] ?? null);
                DB::table('external_sessions')->insert([
                    'id' => $sessionId, 'import_batch_id' => $batchId, 'team_id' => $teamId, 'player_id' => $playerId,
                    'platform_definition_id' => $platform->id, 'destination' => $destination,
                    'label' => 'Rapsodo Pitching Session', 'occurred_at' => $firstOccurredAt,
                    'metadata' => json_encode([
                        'worksheet' => $workbook['worksheet'], 'header_row' => $workbook['header_row'],
                        'session_date' => $sessionDate['date'], 'session_date_source' => $sessionDate['source'],
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                $metricCount = 0;
                foreach ($rows as $index => $row) {
                    $eventId = (string) Str::uuid();
                    $sourceRow = (int) $row['_source_row'];
                    DB::table('canonical_events')->insert([
                        'id' => $eventId, 'external_session_id' => $sessionId, 'player_id' => $playerId,
                        'event_type' => 'pitch_sensor_event', 'event_order' => $index + 1,
                        'occurred_at' => $this->occurredAt($sessionDate['date'], $row['event_time_display'] ?? null),
                        'source_row' => $sourceRow, 'source_record_key' => hash('sha256', "{$checksum}:{$sourceRow}"),
                        'source_context' => json_encode([
                            'worksheet' => $row['_worksheet'], 'source_pitch_number' => $row['pitch_number'] ?? null,
                            'event_time' => $row['event_time_display'] ?? null,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);

                    foreach ($entries as $header => $entry) {
                        $raw = $row['_raw'][$header] ?? null;
                        if (null === $raw || '' === trim((string) $raw) || '-' === trim((string) $raw)) {
                            continue;
                        }
                        $numeric = is_numeric($raw) ? (float) $raw : null;
                        $canonicalUnit = $entry->approved_canonical_unit_key ?: $entry->canonical_unit_key;
                        $canonicalValue = $numeric;
                        if (null !== $numeric && $entry->source_unit_key && $canonicalUnit) {
                            $canonicalValue = $this->units->convert($numeric, $entry->source_unit_key, $canonicalUnit);
                        }
                        DB::table('canonical_metrics')->insert([
                            'id' => (string) Str::uuid(), 'canonical_event_id' => $eventId,
                            'baseball_concept_id' => $entry->baseball_concept_id,
                            'value' => null === $canonicalValue ? (string) $raw : (string) $canonicalValue,
                            'numeric_value' => $canonicalValue, 'canonical_unit_key' => $canonicalUnit,
                            'original_value' => (string) $raw, 'original_unit_key' => $entry->source_unit_key,
                            'original_header' => $header,
                            'measurement_classification' => str_starts_with((string) $entry->canonical_key, 'session_context.')
                                ? 'source_context' : 'source_measurement',
                            'provenance' => json_encode([
                                'snapshot_id' => $snapshotId, 'batch_id' => $batchId, 'platform' => 'rapsodo',
                                'source_file_checksum' => $checksum, 'worksheet' => $row['_worksheet'],
                                'source_row' => $sourceRow, 'mapping_version_id' => $version->id,
                            ], JSON_THROW_ON_ERROR),
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                        ++$metricCount;
                    }
                }

                DB::table('import_batches')->where('id', $batchId)->update([
                    'status' => 'completed', 'session_count' => 1, 'event_count' => count($rows),
                    'metric_count' => $metricCount, 'completed_at' => now(), 'updated_at' => now(),
                ]);

                return [
                    'batch_id' => $batchId, 'snapshot_id' => $snapshotId, 'player_id' => $playerId,
                    'sessions' => 1, 'events' => count($rows), 'metrics' => $metricCount,
                    'status' => 'completed', 'report_path' => "/data-hub/imports/{$batchId}/rapsodo-report?player_id={$playerId}",
                ];
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storageKey);
            throw $exception;
        }
    }

    private function metadata(UploadedFile $file): ImportFileMetadata
    {
        return new ImportFileMetadata(
            $file->getClientOriginalName(), (int) $file->getSize(), 'xlsx',
            (string) $file->getMimeType(), $file->getRealPath()
        );
    }

    /** @return array{date:?string,source:string} */
    private function sessionDate(string $fileName, string $worksheet): array
    {
        foreach ([['value' => $worksheet, 'source' => 'worksheet'], ['value' => $fileName, 'source' => 'filename']] as $candidate) {
            if (1 !== preg_match('/(?<!\d)(\d{1,2})[-_.](\d{1,2})[-_.](\d{2}|\d{4})(?!\d)/', $candidate['value'], $matches)) {
                continue;
            }
            $year = (int) $matches[3];
            $year = $year < 100 ? ($year >= 70 ? 1900 + $year : 2000 + $year) : $year;
            if (checkdate((int) $matches[1], (int) $matches[2], $year)) {
                return ['date' => sprintf('%04d-%02d-%02d', $year, (int) $matches[1], (int) $matches[2]), 'source' => $candidate['source']];
            }
        }

        return ['date' => null, 'source' => 'unavailable'];
    }

    private function occurredAt(?string $date, mixed $time): ?string
    {
        if (null === $date || null === $time || 1 !== preg_match('/^\d{2}:\d{2}:\d{2}$/', (string) $time)) {
            return null;
        }

        return "{$date} {$time}";
    }
}
