<?php

declare(strict_types=1);

namespace App\Services\DataHub\Persistence;

use App\Models\MappingTemplate;
use App\Models\MappingTemplateVersion;
use App\Models\PlatformDefinition;
use App\Models\User;
use App\Services\DataHub\Dictionary\UnitConversionService;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Generic\UniversalSpreadsheetInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class GenericImportService
{
    public function __construct(
        private readonly UniversalSpreadsheetInspector $inspector,
        private readonly UnitConversionService $units,
    ) {
    }

    /**
     * @param array<string, mixed>  $structure      the confirmed file-structure answers from the wizard
     * @param array<string, string> $playerMappings source_key => fmtrx player id
     *
     * @return array<string, mixed>
     */
    public function import(User $user, string $teamId, string $destination, string $fingerprint, array $structure, array $playerMappings, UploadedFile $file): array
    {
        $platform = PlatformDefinition::query()->where('key', 'generic-csv')->where('is_active', true)->firstOrFail();
        $checksum = hash_file('sha256', $file->getRealPath());
        if (DB::table('translation_snapshots')->where('team_id', $teamId)
            ->where('platform_definition_id', $platform->id)->where('source_file_checksum', $checksum)->exists()) {
            throw new RuntimeException('This spreadsheet has already been imported for this team.');
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
                'entry.source_column_name', 'entry.baseball_concept_id',
                'concept.canonical_key', 'concept.canonical_unit_key',
                'source_unit.key as source_unit_key', 'canonical_unit.key as approved_canonical_unit_key',
            ])->keyBy('source_column_name');
        if ($entries->isEmpty()) {
            throw new RuntimeException('The approved mapping has no columns selected for import.');
        }

        $extraction = $this->inspector->extract($this->metadata($file), $structure);
        if ($extraction['template_fingerprint'] !== $fingerprint) {
            throw new RuntimeException('The uploaded file no longer matches the approved column mapping. Inspect it again.');
        }
        if ('worksheet_per_player' === $extraction['layout']) {
            throw new RuntimeException('One-worksheet-per-player files are not yet supported for final import.');
        }
        if ([] === $extraction['records']) {
            throw new RuntimeException('The spreadsheet contains no importable rows.');
        }

        $targetIds = array_values(array_unique(array_filter($playerMappings)));
        if ([] === $targetIds) {
            throw new RuntimeException('Connect at least one player before importing.');
        }
        $authorized = DB::table('player_teams')->where('team_id', $teamId)->whereIn('user_id', $targetIds)
            ->where('actual', true)->whereNull('deleted_at')->pluck('user_id')->map(fn ($id): string => (string) $id)->all();
        if (array_diff($targetIds, $authorized)) {
            throw new RuntimeException('A selected player is not on the chosen FMTRX roster.');
        }

        $rowsByPlayer = $this->groupRowsByPlayer($extraction, $playerMappings);
        if ([] === $rowsByPlayer) {
            throw new RuntimeException('No rows matched a connected player.');
        }

        $snapshotId = (string) Str::uuid();
        $storageKey = "data-hub/imports/{$snapshotId}/".basename($file->getClientOriginalName());
        Storage::disk('local')->put($storageKey, file_get_contents($file->getRealPath()));

        try {
            return DB::transaction(function () use ($user, $teamId, $destination, $file, $platform, $checksum, $snapshotId, $storageKey, $version, $entries, $rowsByPlayer, $extraction): array {
                $now = now();
                DB::table('translation_snapshots')->insert([
                    'id' => $snapshotId, 'team_id' => $teamId, 'platform_definition_id' => $platform->id,
                    'mapping_template_version_id' => $version->id, 'approved_by' => $user->id, 'player_id' => null,
                    'destination' => $destination, 'source_file_name' => basename($file->getClientOriginalName()),
                    'source_file_checksum' => $checksum, 'source_storage_key' => $storageKey,
                    'source_file_size' => $file->getSize(),
                    'snapshot' => json_encode(['headers' => $extraction['headers'], 'layout' => $extraction['layout'], 'mapping_version' => $version->version], JSON_THROW_ON_ERROR),
                    'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $batchId = (string) Str::uuid();
                DB::table('import_batches')->insert([
                    'id' => $batchId, 'translation_snapshot_id' => $snapshotId, 'initiated_by' => $user->id,
                    'status' => 'processing', 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);

                $eventCount = 0;
                $metricCount = 0;
                foreach ($rowsByPlayer as $playerId => $rows) {
                    $sessionId = (string) Str::uuid();
                    DB::table('external_sessions')->insert([
                        'id' => $sessionId, 'import_batch_id' => $batchId, 'team_id' => $teamId, 'player_id' => $playerId,
                        'platform_definition_id' => $platform->id, 'destination' => $destination,
                        'label' => 'Generic Spreadsheet Session', 'occurred_at' => null,
                        'metadata' => json_encode(['layout' => $extraction['layout']], JSON_THROW_ON_ERROR),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                    foreach ($rows as $index => $row) {
                        $eventId = (string) Str::uuid();
                        $sourceRow = (int) $row['row'];
                        DB::table('canonical_events')->insert([
                            'id' => $eventId, 'external_session_id' => $sessionId, 'player_id' => $playerId,
                            'event_type' => 'generic_spreadsheet_row', 'event_order' => $index + 1, 'occurred_at' => null,
                            'source_row' => $sourceRow, 'source_record_key' => hash('sha256', "{$checksum}:{$playerId}:{$sourceRow}"),
                            'source_context' => null, 'created_at' => $now, 'updated_at' => $now,
                        ]);
                        ++$eventCount;
                        foreach ($entries as $header => $entry) {
                            $raw = $row['values'][$header] ?? null;
                            if (null === $raw || '' === trim((string) $raw)) {
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
                                'measurement_classification' => str_starts_with((string) $entry->canonical_key, 'session_context.') ? 'source_context' : 'source_measurement',
                                'provenance' => json_encode(['snapshot_id' => $snapshotId, 'batch_id' => $batchId, 'platform' => 'generic-csv', 'source_file_checksum' => $checksum, 'source_row' => $sourceRow, 'mapping_version_id' => $version->id], JSON_THROW_ON_ERROR),
                                'created_at' => $now, 'updated_at' => $now,
                            ]);
                            ++$metricCount;
                        }
                    }
                }

                DB::table('import_batches')->where('id', $batchId)->update([
                    'status' => 'completed', 'session_count' => count($rowsByPlayer), 'event_count' => $eventCount,
                    'metric_count' => $metricCount, 'completed_at' => now(), 'updated_at' => now(),
                ]);

                return ['batch_id' => $batchId, 'snapshot_id' => $snapshotId, 'sessions' => count($rowsByPlayer), 'events' => $eventCount, 'metrics' => $metricCount, 'status' => 'completed'];
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storageKey);
            throw $exception;
        }
    }

    /**
     * Turns the extracted spreadsheet into per-player row groups. The source_key
     * hashing must match UniversalSpreadsheetInspector::players() exactly, since
     * that's the key the coach's approved player mapping was recorded against.
     *
     * @return array<string, array<int, array{row:int, values: array<string,mixed>}>>
     */
    private function groupRowsByPlayer(array $extraction, array $playerMappings): array
    {
        $layout = $extraction['layout'];
        $grouped = [];
        if (in_array($layout, ['players_in_rows', 'events_in_rows'], true)) {
            $playerColumn = $extraction['player_column'];
            foreach ($extraction['records'] as $record) {
                $name = trim((string) ($record[$playerColumn] ?? ''));
                if ('' === $name) {
                    continue;
                }
                $playerId = $playerMappings['generic:'.hash('sha256', $layout.'|'.$name)] ?? null;
                if ( ! $playerId) {
                    continue;
                }
                $grouped[$playerId][] = ['row' => (int) ($record['_row_number'] ?? 0), 'values' => $record];
            }

            return $grouped;
        }
        if ('players_in_columns' === $layout) {
            $metricColumn = $extraction['metric_column'];
            foreach ($extraction['headers'] as $header) {
                if ($header === $metricColumn) {
                    continue;
                }
                $playerId = $playerMappings['generic:'.hash('sha256', $layout.'|'.$header)] ?? null;
                if ( ! $playerId) {
                    continue;
                }
                $values = [];
                foreach ($extraction['records'] as $record) {
                    $metricLabel = trim((string) ($record[$metricColumn] ?? ''));
                    if ('' !== $metricLabel) {
                        $values[$metricLabel] = $record[$header] ?? '';
                    }
                }
                $grouped[$playerId] = [['row' => (int) ($extraction['records'][0]['_row_number'] ?? 0), 'values' => $values]];
            }

            return $grouped;
        }
        if ('single_player_session' === $layout) {
            $playerId = $playerMappings['generic:'.hash('sha256', $layout.'|Spreadsheet Session')] ?? null;
            if ($playerId) {
                foreach ($extraction['records'] as $record) {
                    $grouped[$playerId][] = ['row' => (int) ($record['_row_number'] ?? 0), 'values' => $record];
                }
            }

            return $grouped;
        }

        return $grouped;
    }

    private function metadata(UploadedFile $file): ImportFileMetadata
    {
        return new ImportFileMetadata(
            $file->getClientOriginalName(),
            (int) $file->getSize(),
            mb_strtolower((string) $file->getClientOriginalExtension()),
            (string) $file->getMimeType(),
            $file->getRealPath()
        );
    }
}
