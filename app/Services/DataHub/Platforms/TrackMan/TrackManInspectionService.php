<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\TrackMan;

use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Services\PlayerMatchingService;

final class TrackManInspectionService
{
    public function __construct(
        private readonly TrackManParser $parser,
        private readonly TrackManNormalizer $normalizer,
        private readonly TrackManRowValidator $validator,
        private readonly PlayerMatchingService $matching,
    ) {
    }

    /** @return array<string, mixed> */
    public function inspect(ImportFileMetadata $file, string $teamId, string $sessionType): array
    {
        $rows = iterator_to_array($this->parser->parse($file), false);
        $dataType = (string) ($rows[0]['_data_type'] ?? 'unsupported');
        $dates = $this->unique($rows, 'date');
        $players = [];
        $invalid = 0;
        $metrics = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['batter'] ?? $row['pitcher'] ?? ''));
            if ('' !== $name) {
                $key = $this->matching->normalize($name);
                $players[$key] ??= [
                    'external_name' => $name,
                    'external_identifier' => $row['batter_id'] ?? $row['pitcher_id'] ?? null,
                    'row_count' => 0,
                    'data_types' => [],
                ];
                ++$players[$key]['row_count'];
                $players[$key]['data_types'][] = 'mixed' === $dataType
                    ? (isset($row['batter']) && '' !== trim((string) $row['batter']) ? 'hitting' : 'pitching')
                    : $dataType;
            }
            $rowType = 'pitching' === $dataType ? 'pitching' : 'hitting';
            if ([] !== $this->validator->warnings($row, $rowType) || '' === $name) {
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
        foreach ($players as &$player) {
            $player['data_types'] = array_values(array_unique($player['data_types']));
            $player['suggested_matches'] = $this->matching->suggestions($teamId, $player['external_name']);
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
