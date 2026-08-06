<?php

declare(strict_types=1);

namespace App\Services\DataHub\Persistence;

use App\Models\MappingTemplate;
use App\Models\MappingTemplateVersion;
use App\Models\PlatformDefinition;
use App\Models\User;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Platforms\BlastMotion\BlastMotionParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class BlastImportService
{
    public function __construct(private readonly BlastMotionParser $parser)
    {
    }

    /** @return array<string, mixed> */
    public function import(User $user, string $teamId, string $playerId, string $destination, string $fingerprint, UploadedFile $file): array
    {
        $platform = PlatformDefinition::query()->where('key', 'blast-motion')->where('is_active', true)->firstOrFail();
        $checksum = hash_file('sha256', $file->getRealPath());
        if (DB::table('translation_snapshots')->where('team_id', $teamId)
            ->where('platform_definition_id', $platform->id)->where('source_file_checksum', $checksum)->exists()) {
            throw new RuntimeException('This Blast CSV has already been imported for this team.');
        }

        $template = MappingTemplate::query()->where('team_id', $teamId)
            ->where('platform_definition_id', $platform->id)
            ->where('template_fingerprint', $fingerprint)
            ->firstOrFail();
        $version = MappingTemplateVersion::query()->whereKey($template->current_version_id)
            ->where('status', 'approved')->firstOrFail();
        $entries = DB::table('mapping_entries')->where('mapping_template_version_id', $version->id)
            ->where('action', 'map')->whereNotNull('baseball_concept_id')->get()->keyBy('source_column_name');
        if ($entries->isEmpty()) {
            throw new RuntimeException('The approved mapping has no columns selected for import.');
        }

        $report = $this->parser->report($this->metadata($file));
        $actualFingerprint = app(TemplateFingerprintService::class)->fingerprint($report['headers']);
        if ($actualFingerprint !== $fingerprint) {
            throw new RuntimeException('The uploaded file no longer matches the approved column mapping. Inspect it again.');
        }
        $rows = iterator_to_array($this->parser->parse($this->metadata($file)), false);
        if ([] === $rows) {
            throw new RuntimeException('The Blast CSV contains no importable swing rows.');
        }

        $snapshotId = (string) Str::uuid();
        $storageKey = "data-hub/imports/{$snapshotId}/".basename($file->getClientOriginalName());
        Storage::disk('local')->put($storageKey, file_get_contents($file->getRealPath()));

        try {
            $result = DB::transaction(function () use ($user, $teamId, $playerId, $destination, $file, $platform, $checksum, $snapshotId, $storageKey, $version, $entries, $rows, $report): array {
                $now = now();
                DB::table('translation_snapshots')->insert([
                    'id' => $snapshotId, 'team_id' => $teamId, 'platform_definition_id' => $platform->id,
                    'mapping_template_version_id' => $version->id, 'approved_by' => $user->id, 'player_id' => $playerId,
                    'destination' => $destination, 'source_file_name' => basename($file->getClientOriginalName()),
                    'source_file_checksum' => $checksum, 'source_storage_key' => $storageKey,
                    'source_file_size' => $file->getSize(),
                    'snapshot' => json_encode(['headers' => $report['headers'], 'metadata' => $report['metadata'], 'mapping_version' => $version->version], JSON_THROW_ON_ERROR),
                    'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $batchId = (string) Str::uuid();
                DB::table('import_batches')->insert([
                    'id' => $batchId, 'translation_snapshot_id' => $snapshotId, 'initiated_by' => $user->id,
                    'status' => 'processing', 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $sessionId = (string) Str::uuid();
                $firstDate = $this->date($rows[0]['event_timestamp'] ?? null);
                DB::table('external_sessions')->insert([
                    'id' => $sessionId, 'import_batch_id' => $batchId, 'team_id' => $teamId, 'player_id' => $playerId,
                    'platform_definition_id' => $platform->id, 'destination' => $destination,
                    'label' => 'Blast Motion Swing Session', 'occurred_at' => $firstDate,
                    'metadata' => json_encode($report['metadata'], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now,
                ]);
                $metricCount = 0;
                foreach ($rows as $index => $row) {
                    $eventId = (string) Str::uuid();
                    $sourceRow = (int) $row['_source_row'];
                    $occurredAt = $this->date($row['event_timestamp'] ?? null);
                    DB::table('canonical_events')->insert([
                        'id' => $eventId, 'external_session_id' => $sessionId, 'player_id' => $playerId,
                        'event_type' => 'swing_sensor_event', 'event_order' => $index + 1, 'occurred_at' => $occurredAt,
                        'source_row' => $sourceRow, 'source_record_key' => hash('sha256', "{$checksum}:{$sourceRow}"),
                        'source_context' => json_encode(['handedness' => $row['batter_side_canonical'] ?? null, 'equipment' => $row['bat_equipment'] ?? null, 'swing_details' => $row['swing_details'] ?? null], JSON_THROW_ON_ERROR),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                    foreach ($entries as $header => $entry) {
                        $raw = $row['_raw'][$header] ?? null;
                        if (null === $raw || '' === trim((string) $raw)) {
                            continue;
                        }
                        $concept = DB::table('baseball_concepts')->where('id', $entry->baseball_concept_id)->first();
                        if (null === $concept) {
                            throw new RuntimeException("Approved concept for {$header} is unavailable.");
                        }
                        DB::table('canonical_metrics')->insert([
                            'id' => (string) Str::uuid(), 'canonical_event_id' => $eventId,
                            'baseball_concept_id' => $concept->id, 'value' => (string) $raw,
                            'numeric_value' => is_numeric($raw) ? (float) $raw : null,
                            'canonical_unit_key' => $concept->canonical_unit_key,
                            'original_value' => (string) $raw, 'original_unit_key' => $entry->metadata ? (json_decode($entry->metadata, true)['source_unit_key'] ?? null) : null,
                            'original_header' => $header,
                            'measurement_classification' => str_contains((string) $concept->canonical_key, 'blast_') ? 'source_specific' : 'source_measurement',
                            'provenance' => json_encode(['snapshot_id' => $snapshotId, 'batch_id' => $batchId, 'platform' => 'blast-motion', 'source_file_checksum' => $checksum, 'source_row' => $sourceRow, 'mapping_version_id' => $version->id], JSON_THROW_ON_ERROR),
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                        ++$metricCount;
                    }
                }
                DB::table('import_batches')->where('id', $batchId)->update([
                    'status' => 'completed', 'session_count' => 1, 'event_count' => count($rows),
                    'metric_count' => $metricCount, 'completed_at' => now(), 'updated_at' => now(),
                ]);

                return ['batch_id' => $batchId, 'snapshot_id' => $snapshotId, 'sessions' => 1, 'events' => count($rows), 'metrics' => $metricCount, 'status' => 'completed'];
            });

            $this->forgetDevelopmentDashboardCaches($teamId, $playerId);

            return $result;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storageKey);
            throw $exception;
        }
    }

    private function metadata(UploadedFile $file): ImportFileMetadata
    {
        return new ImportFileMetadata($file->getClientOriginalName(), (int) $file->getSize(), 'csv', (string) $file->getMimeType(), $file->getRealPath());
    }

    private function date(mixed $value): ?string
    {
        // Blast formats timestamps as "July 1, 2026 / 10:00:00 AM". The
        // separator is display punctuation, not a date token understood by PHP.
        $normalized = preg_replace('/\s+\/\s+/', ' ', trim((string) $value));
        $timestamp = strtotime((string) $normalized);

        return false === $timestamp ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function forgetDevelopmentDashboardCaches(string $teamId, string $playerId): void
    {
        foreach ([30, 60, 90, 120, 365, 'all'] as $days) {
            Cache::forget("dev_dashboard_v3_{$teamId}_{$playerId}_{$days}");
        }
    }
}
