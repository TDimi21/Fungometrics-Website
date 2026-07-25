<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\TrackMan;

use App\Models\PlatformDefinition;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\Services\PlayerMatchingService;

final class TrackManInspectionService
{
    public function __construct(
        private readonly TrackManParser $parser,
        private readonly TrackManNormalizer $normalizer,
        private readonly TrackManRowValidator $validator,
        private readonly PlayerMatchingService $matching,
        private readonly TemplateFingerprintService $fingerprints,
        private readonly TrackManPlayerExtractor $playerExtractor,
    ) {
    }

    /** @return array<string, mixed> */
    public function inspect(ImportFileMetadata $file, string $teamId, string $sessionType): array
    {
        $rows = iterator_to_array($this->parser->parse($file), false);
        $dataType = (string) ($rows[0]['_data_type'] ?? 'unsupported');
        $dates = $this->unique($rows, 'date');
        $invalid = 0;
        $metrics = [];
        foreach ($rows as $row) {
            $rowType = 'pitching' === $dataType ? 'pitching' : 'hitting';
            $hasPlayer = '' !== trim((string) ($row['batter'] ?? ''))
                || '' !== trim((string) ($row['pitcher'] ?? ''));
            if ([] !== $this->validator->warnings($row, $rowType) || ! $hasPlayer) {
                ++$invalid;
            }
            foreach (array_keys($row) as $field) {
                if (str_ends_with($field, '_mph') || str_ends_with($field, '_deg') || str_ends_with($field, '_ft') || str_ends_with($field, '_rpm') || str_ends_with($field, '_in') || str_ends_with($field, '_seconds')) {
                    if (isset($row[$field]) && '' !== trim((string) $row[$field])) {
                        $metrics[] = $field;
                    }
                }
            }
        }
        $players = $this->playerExtractor->extract($rows);
        $platformId = (string) PlatformDefinition::query()->where('key', 'trackman')->value('id');
        foreach ($players as &$player) {
            $suggestions = $this->matching->suggestions(
                $teamId,
                $player['source_name'],
                $player['external_player_id'],
                $platformId,
            );
            $player['suggested_matches'] = $suggestions;
            $player['suggested_player'] = $suggestions[0] ?? null;
            $player['mapping_status'] = ($suggestions[0]['auto_select'] ?? false) ? 'matched' : 'suggested';
            // Transitional aliases for the existing review DTO.
            $player['external_name'] = $player['source_name'];
            $player['external_identifier'] = $player['external_player_id'];
            $player['data_types'] = $player['roles'];
        }
        unset($player);
        $normalized = $this->normalizer->normalize($rows, [], $sessionType)->records;
        $warnings = [];
        if ('mixed' === $dataType) {
            $warnings[] = 'Mixed TrackMan files require separate destinations and are not supported in Phase 2A.';
        }
        if ($invalid > 0) {
            $warnings[] = "{$invalid} row(s) contain missing players or invalid numeric values.";
        }

        $sourceColumns = $this->parser->inspectSourceColumns($file);

        return [
            'platform' => 'trackman',
            'file' => ['name' => $file->name, 'size_bytes' => $file->sizeBytes, 'extension' => $file->extension],
            'detected_format' => [
                'provider' => 'TrackMan', 'data_type' => $dataType,
                'confidence' => 'unsupported' === $dataType ? 0 : 100, 'header_version' => null,
            ],
            'session' => [
                'dates' => $dates, 'primary_date' => $dates[0] ?? null,
                'facility' => $this->first($rows, 'stadium'), 'system' => $this->first($rows, 'system'),
                'detected_session_count' => count($dates) ?: (count($rows) > 0 ? 1 : 0),
            ],
            'counts' => [
                'total_rows' => count($rows), 'usable_rows' => count($rows) - $invalid,
                'invalid_rows' => $invalid, 'players_found' => count($players),
                'sessions_found' => count($dates) ?: (count($rows) > 0 ? 1 : 0),
            ],
            'players' => array_values($players),
            'metrics_detected' => array_values(array_unique($metrics)),
            'warnings' => $warnings,
            'sample_rows' => array_slice($normalized, 0, 10),
            'source_columns' => $sourceColumns,
            'template_fingerprint' => $this->fingerprints->fingerprint(array_column($sourceColumns, 'source_column_name')),
        ];
    }

    private function first(array $rows, string $key): ?string
    {
        foreach ($rows as $row) {
            if (isset($row[$key]) && '' !== trim((string) $row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function unique(array $rows, string $key): array
    {
        return array_values(array_unique(array_filter(array_map(fn (array $row): ?string => isset($row[$key]) && '' !== trim((string) $row[$key]) ? trim((string) $row[$key]) : null, $rows))));
    }
}
