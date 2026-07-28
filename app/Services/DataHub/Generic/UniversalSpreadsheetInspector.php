<?php

declare(strict_types=1);

namespace App\Services\DataHub\Generic;

use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Exceptions\TranslationFailureException;
use App\Services\DataHub\Services\TranslationFailureCatalog;
use App\Services\DataHub\Support\SecureXlsxReader;
use RuntimeException;

final class UniversalSpreadsheetInspector
{
    private const PLAYER_LABELS = ['player', 'playername', 'athlete', 'athletename', 'name', 'user', 'batter', 'pitcher'];
    private const DATE_LABELS = ['date', 'eventdate', 'recorddate', 'timestamp', 'eventtimestamp'];

    public function __construct(
        private readonly SecureXlsxReader $xlsx,
        private readonly TemplateFingerprintService $fingerprints,
        private readonly TranslationFailureCatalog $failures,
    ) {
    }

    /** @param array<string, mixed> $override */
    public function inspect(ImportFileMetadata $file, array $override = []): array
    {
        $tables = match (mb_strtolower($file->extension)) {
            'csv' => [$this->delimited($file, ',')],
            'tsv' => [$this->delimited($file, "\t")],
            'xlsx' => $this->workbook($file),
            default => throw new TranslationFailureException($this->failures->warning(
                'unsupported_file_type',
                ['extension' => mb_strtolower($file->extension)],
            )),
        };
        if ([] === $tables) {
            throw new TranslationFailureException($this->failures->warning(
                'missing_header',
                ['file_type' => mb_strtolower($file->extension)],
            ));
        }

        $selectedIndex = min(max((int) ($override['worksheet_index'] ?? 0), 0), count($tables) - 1);
        $table = $tables[$selectedIndex];
        $headerRow = (int) ($override['header_row'] ?? $this->detectHeaderRow($table['rows']));
        if ($headerRow < 1 || $headerRow > count($table['rows'])) {
            throw new TranslationFailureException($this->failures->warning(
                'missing_header',
                ['worksheet' => $table['name']],
            ));
        }
        $firstDataRow = (int) ($override['first_data_row'] ?? ($headerRow + 1));
        if ($firstDataRow <= $headerRow || $firstDataRow > count($table['rows']) + 1) {
            throw new RuntimeException('The first data row must follow the header and stay inside the detected table.');
        }

        $headers = $this->headers($table['rows'][$headerRow - 1] ?? []);
        $ignoredRows = array_map('intval', (array) ($override['ignored_rows'] ?? []));
        if ([] !== array_filter($ignoredRows, fn (int $row): bool => $row < 1 || $row > count($table['rows']))) {
            throw new RuntimeException('An ignored row is outside the detected table bounds.');
        }
        $records = $this->records($table['rows'], $headers, $firstDataRow, $ignoredRows);
        $layout = (string) ($override['layout'] ?? $this->detectLayout($headers, $records, $tables));
        $playerColumn = (string) ($override['player_column'] ?? $this->detectPlayerColumn($headers));
        $metricColumn = (string) ($override['metric_column'] ?? ($headers[0] ?? ''));
        foreach (['player_column' => $playerColumn, 'metric_column' => $metricColumn, 'player_id_column' => (string) ($override['player_id_column'] ?? ''), 'date_column' => (string) ($override['date_column'] ?? '')] as $label => $column) {
            if ('' !== $column && ! in_array($column, $headers, true)) {
                throw new RuntimeException(str_replace('_', ' ', ucfirst($label)).' is outside the detected table bounds.');
            }
        }
        $ignoredColumns = array_values(array_intersect($headers, (array) ($override['ignored_columns'] ?? [])));
        $activeHeaders = array_values(array_diff($headers, $ignoredColumns));
        $players = $this->players($layout, $playerColumn, $metricColumn, $activeHeaders, $records, $tables);
        $sourceColumns = $this->sourceColumns($layout, $playerColumn, $metricColumn, $activeHeaders, $records);
        $confidence = $this->layoutConfidence($layout, $playerColumn, $tables);
        $needsConfirmation = 'unknown' === $layout || $confidence < 0.85 || 'generic-csv' === ($override['platform'] ?? 'generic-csv');
        $warnings = $table['warnings'];
        if ($needsConfirmation) {
            $warnings[] = 'Confirm the file structure before Player Mapping.';
        }
        if ('worksheet_per_player' === $layout) {
            $warnings[] = 'Worksheet names are only player-identity candidates and require coach confirmation.';
        }

        $preview = array_slice(array_map(
            fn (array $row, int $index): array => ['row_number' => $firstDataRow + $index, 'values' => $this->sanitizeRow($row, $activeHeaders)],
            $records,
            array_keys($records)
        ), 0, 15);

        return [
            'platform' => 'generic-csv',
            'file' => ['name' => $file->name, 'size_bytes' => $file->sizeBytes, 'extension' => $file->extension],
            'detected_format' => [
                'provider' => 'Generic Spreadsheet', 'data_type' => 'generic',
                'display_type' => 'Structured Spreadsheet', 'confidence' => (int) round($confidence * 100),
                'header_row' => $headerRow, 'header_count' => count($activeHeaders),
            ],
            'normalized_inspection' => [
                'file_type' => mb_strtolower($file->extension),
                'worksheets' => array_map(fn (array $candidate): array => [
                    'name' => $candidate['name'], 'row_count' => count($candidate['rows']),
                    'column_count' => max(array_map('count', $candidate['rows'] ?: [[]])),
                ], $tables),
                'selected_worksheet_index' => $selectedIndex,
                'detected_table_regions' => [['start_row' => $headerRow, 'end_row' => count($table['rows']), 'column_count' => count($headers)]],
                'detected_layout' => $layout, 'layout_confidence' => $confidence,
                'requires_structure_confirmation' => $needsConfirmation,
                'header_row' => $headerRow, 'first_data_row' => $firstDataRow,
                'player_identity_candidates' => array_values(array_filter([$playerColumn])),
                'metric_header_candidates' => $activeHeaders,
                'player_column' => $playerColumn ?: null, 'metric_column' => $metricColumn ?: null,
                'player_id_column' => $override['player_id_column'] ?? null,
                'date_column' => $override['date_column'] ?? null,
                'ignored_rows' => $ignoredRows,
                'ignored_columns' => $ignoredColumns,
                'preview_rows' => $preview,
                'extraction_confidence' => 1.0,
            ],
            'session' => ['dates' => [], 'primary_date' => null, 'facility' => null, 'system' => 'Generic Spreadsheet', 'detected_session_count' => count($records) > 0 ? 1 : 0],
            'counts' => [
                'total_rows' => count($records), 'usable_rows' => count($records), 'invalid_rows' => 0,
                'players_found' => count($players), 'sessions_found' => count($records) > 0 ? 1 : 0,
                'columns_found' => count($sourceColumns), 'blank_rows' => $table['blank_rows'],
            ],
            'players' => $players,
            'metrics_detected' => array_column($sourceColumns, 'source_column_name'),
            'warnings' => array_values(array_unique($warnings)),
            'sample_rows' => array_slice(array_map(fn (array $row): array => [
                'player_external_name' => $playerColumn ? ($row[$playerColumn] ?? null) : ($players[0]['source_name'] ?? null),
                'metrics' => $this->sanitizeRow($row, $activeHeaders),
                'source' => ['platform' => 'generic-csv', 'worksheet' => $table['name']],
                'validation' => ['valid' => true, 'warnings' => []],
            ], $records), 0, 10),
            'source_columns' => $sourceColumns,
            'template_fingerprint' => $this->fingerprints->fingerprint(array_column($sourceColumns, 'source_column_name')),
            'destination_recommendation' => ['detected_data_type' => 'generic', 'recommended' => [], 'selected' => null, 'advisory_only' => true],
        ];
    }

    /** @return array{name: string, rows: array<int, array<int, string>>, blank_rows: int, warnings: array<int, string>} */
    private function delimited(ImportFileMetadata $file, string $delimiter): array
    {
        if ( ! $file->path || ! is_readable($file->path)) {
            throw new RuntimeException('The temporary spreadsheet file is unavailable.');
        }
        $handle = fopen($file->path, 'rb');
        if (false === $handle) {
            throw new RuntimeException('The spreadsheet could not be opened.');
        }
        $contents = file_get_contents($file->path);
        if (false === $contents) {
            fclose($handle);
            throw new RuntimeException('The spreadsheet could not be read.');
        }
        if ($this->hasUnclosedQuotedField($contents)) {
            fclose($handle);
            throw new TranslationFailureException($this->failures->warning(
                'malformed_csv',
                ['file_type' => mb_strtolower($file->extension)],
            ));
        }
        $rows = [];
        $blank = 0;
        try {
            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                $values = array_map(fn ($value): string => preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value)), $values);
                if ([] === array_filter($values, fn (string $value): bool => '' !== $value)) {
                    ++$blank;
                }
                $rows[] = $values;
            }
        } finally {
            fclose($handle);
        }

        return ['name' => 'Spreadsheet', 'rows' => $rows, 'blank_rows' => $blank, 'warnings' => []];
    }

    /** @return array<int, array{name: string, rows: array<int, array<int, string>>, blank_rows: int, warnings: array<int, string>}> */
    private function workbook(ImportFileMetadata $file): array
    {
        $book = $this->xlsx->read((string) $file->path);

        return array_map(function (array $sheet) use ($book): array {
            $rows = [];
            $blank = 0;
            $maxRow = max(array_keys($sheet['rows'] ?: [1 => []]));
            for ($row = 1; $row <= $maxRow; ++$row) {
                $values = array_values($sheet['rows'][$row] ?? []);
                if ([] === array_filter($values, fn ($value): bool => '' !== trim((string) $value))) {
                    ++$blank;
                }
                $rows[] = array_map(fn ($value): string => trim((string) $value), $values);
            }
            $warnings = $book['warnings'];
            if ($sheet['formulas'] > 0) {
                $warnings[] = 'Formula cells were not executed; only cached displayed values were inspected.';
            }
            if ([] !== $sheet['merged_ranges']) {
                $warnings[] = 'Merged cells exist and require review.';
            }

            return ['name' => $sheet['name'], 'rows' => $rows, 'blank_rows' => $blank, 'warnings' => $warnings];
        }, $book['sheets']);
    }

    private function detectHeaderRow(array $rows): int
    {
        $best = ['row' => 0, 'score' => -1];
        foreach (array_slice($rows, 0, 50, true) as $index => $row) {
            $populated = array_values(array_filter($row, fn ($value): bool => '' !== trim((string) $value)));
            $text = array_filter($populated, fn ($value): bool => ! is_numeric($value));
            $normalized = array_map([TemplateFingerprintService::class, 'normalize'], $populated);
            $score = count($populated) + count($text) + 5 * count(array_intersect($normalized, array_merge(self::PLAYER_LABELS, self::DATE_LABELS)));
            if (count($populated) >= 2 && $score > $best['score']) {
                $best = ['row' => $index + 1, 'score' => $score];
            }
        }

        return $best['row'];
    }

    private function hasUnclosedQuotedField(string $contents): bool
    {
        $insideQuotedField = false;
        for ($index = 0; isset($contents[$index]); ++$index) {
            if ('"' !== $contents[$index]) {
                continue;
            }
            if ($insideQuotedField && isset($contents[$index + 1]) && '"' === $contents[$index + 1]) {
                ++$index;
                continue;
            }
            $insideQuotedField = ! $insideQuotedField;
        }

        return $insideQuotedField;
    }

    private function headers(array $values): array
    {
        $headers = [];
        foreach ($values as $index => $value) {
            $label = trim((string) $value);
            $headers[] = '' !== $label ? $label : 'Column '.($index + 1);
        }

        return $headers;
    }

    private function records(array $rows, array $headers, int $firstDataRow, array $ignoredRows): array
    {
        $records = [];
        foreach (array_slice($rows, $firstDataRow - 1, null, true) as $index => $values) {
            $rowNumber = $index + 1;
            if (in_array($rowNumber, $ignoredRows, true) || [] === array_filter($values, fn ($value): bool => '' !== trim((string) $value))) {
                continue;
            }
            $records[] = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), ''));
        }

        return $records;
    }

    private function detectPlayerColumn(array $headers): string
    {
        foreach ($headers as $header) {
            if (in_array(TemplateFingerprintService::normalize($header), self::PLAYER_LABELS, true)) {
                return $header;
            }
        }

        return '';
    }

    private function detectLayout(array $headers, array $records, array $tables): string
    {
        $player = $this->detectPlayerColumn($headers);
        if ('' !== $player) {
            $normalized = array_map([TemplateFingerprintService::class, 'normalize'], $headers);
            $playerValues = array_values(array_filter(array_column($records, $player)));
            $hasRepeatedPlayer = count($playerValues) > count(array_unique($playerValues));
            $hasEventNumber = [] !== array_intersect($normalized, [
                'eventnumber', 'swingnumber', 'thrownumber', 'pitchnumber',
            ]);

            return $hasRepeatedPlayer || $hasEventNumber ? 'events_in_rows' : 'players_in_rows';
        }
        if (count($tables) > 1 && count(array_filter($tables, fn (array $table): bool => count($table['rows']) > 1)) > 1) {
            return 'worksheet_per_player';
        }
        if (count($headers) >= 3 && $records && $this->mostlyText(array_slice($headers, 1)) && $this->mostlyText(array_column($records, $headers[0]))) {
            return 'players_in_columns';
        }
        if (count($records) > 0) {
            return 'single_player_session';
        }

        return 'unknown';
    }

    private function players(string $layout, string $playerColumn, string $metricColumn, array $headers, array $records, array $tables): array
    {
        $names = match ($layout) {
            'players_in_rows', 'events_in_rows' => array_values(array_unique(array_filter(array_column($records, $playerColumn)))),
            'players_in_columns' => array_values(array_diff($headers, [$metricColumn])),
            'worksheet_per_player' => array_column($tables, 'name'),
            'single_player_session' => ['Spreadsheet Session'],
            default => [],
        };

        return array_map(function (string $name) use ($layout, $playerColumn, $records): array {
            $count = in_array($layout, ['players_in_rows', 'events_in_rows'], true)
                ? count(array_filter($records, fn (array $row): bool => $name === ($row[$playerColumn] ?? null)))
                : count($records);

            return [
                'source_key' => 'generic:'.hash('sha256', $layout.'|'.$name), 'source_name' => $name,
                'normalized_name' => mb_strtolower(trim($name)), 'external_player_id' => null,
                'roles' => ['player'], 'row_count' => $count, 'suggested_matches' => [], 'suggested_player' => null,
                'mapping_status' => 'needs_review', 'external_name' => $name, 'external_identifier' => null,
                'data_types' => ['player'], 'identity_missing' => 'single_player_session' === $layout,
                'remember_mapping' => false,
                'assignment_help' => 'single_player_session' === $layout ? 'No player field was found. Connect the entire spreadsheet to one roster player.' : null,
            ];
        }, $names);
    }

    private function sourceColumns(string $layout, string $playerColumn, string $metricColumn, array $headers, array $records): array
    {
        $names = 'players_in_columns' === $layout
            ? array_values(array_unique(array_filter(array_column($records, $metricColumn))))
            : array_values(array_diff($headers, array_filter([$playerColumn])));

        return array_map(function (string $name) use ($layout, $headers, $records): array {
            $values = 'players_in_columns' === $layout
                ? array_values(array_filter(array_map(function (array $row) use ($name, $headers): string {
                    $metric = $row[$headers[0]] ?? '';
                    if ($metric !== $name) {
                        return '';
                    }

                    return implode(' · ', array_filter(array_slice($row, 1)));
                }, $records)))
                : array_values(array_filter(array_column($records, $name), fn ($value): bool => '' !== trim((string) $value)));
            $numeric = array_filter($values, 'is_numeric');

            return [
                'source_column_name' => $name,
                'normalized_source_column' => TemplateFingerprintService::normalize($name),
                'sample_values' => array_slice(array_values(array_unique($values)), 0, 5),
                'warnings' => [], 'controlled_value_transformations' => [],
                'details' => [
                    'inferred_data_type' => count($values) > 0 && count($numeric) === count($values) ? 'numeric' : 'text',
                    'populated_count' => count($values), 'unique_value_count' => count(array_unique($values)),
                ],
            ];
        }, $names);
    }

    private function layoutConfidence(string $layout, string $playerColumn, array $tables): float
    {
        return match ($layout) {
            'players_in_rows', 'events_in_rows' => '' !== $playerColumn ? 0.95 : 0.65,
            'worksheet_per_player' => count($tables) > 1 ? 0.8 : 0.5,
            'players_in_columns' => 0.75,
            'single_player_session' => 0.7,
            default => 0.3,
        };
    }

    private function mostlyText(array $values): bool
    {
        $populated = array_filter($values, fn ($value): bool => '' !== trim((string) $value));

        return count($populated) > 0 && count(array_filter($populated, fn ($value): bool => ! is_numeric($value))) / count($populated) >= 0.7;
    }

    private function sanitizeRow(array $row, array $headers): array
    {
        return array_intersect_key($row, array_flip($headers));
    }
}
