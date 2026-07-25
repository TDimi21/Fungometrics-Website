<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\BlastMotion;

use App\Services\DataHub\Contracts\ImportParserContract;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\DTOs\ImportInspectionResult;
use RuntimeException;

final class BlastMotionParser implements ImportParserContract
{
    public function __construct(private readonly BlastMotionFieldMap $fields)
    {
    }

    public function inspect(ImportFileMetadata $file): ImportInspectionResult
    {
        return new ImportInspectionResult($this->report($file));
    }

    public function parse(ImportFileMetadata $file): iterable
    {
        $report = $this->report($file);
        foreach ($report['source_rows'] as $sourceRow) {
            $row = ['_source_row' => $sourceRow['_source_row'], '_raw' => []];
            foreach ($report['field_map'] as $canonical => $header) {
                $row[$canonical] = $sourceRow[$header] ?? '';
                $row['_raw'][$header] = $sourceRow[$header] ?? '';
            }
            $row['batter_side_canonical'] = $this->handedness($row['batter_side'] ?? null);
            yield $row;
        }
    }

    /** @return array<string, mixed> */
    public function report(ImportFileMetadata $file): array
    {
        if ('csv' !== mb_strtolower($file->extension)) {
            throw new RuntimeException('This observed Blast Motion format requires a CSV file.');
        }
        if ( ! $file->path || ! is_readable($file->path)) {
            throw new RuntimeException('The temporary Blast Motion inspection file is unavailable.');
        }
        $handle = fopen($file->path, 'rb');
        if (false === $handle) {
            throw new RuntimeException('The Blast Motion CSV could not be opened.');
        }
        $physicalRows = [];
        try {
            while (($values = fgetcsv($handle)) !== false) {
                $physicalRows[] = array_map(
                    fn ($value): string => preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value)),
                    $values
                );
            }
        } finally {
            fclose($handle);
        }

        $candidate = null;
        foreach ($physicalRows as $index => $values) {
            $normalized = array_map([TemplateFingerprintService::class, 'normalize'], $values);
            $strongCount = count(array_intersect($this->fields->strongSignals(), $normalized));
            $required = ['date', 'equipment', 'handedness', 'swingdetails', 'batspeedmph'];
            if (count(array_intersect($required, $normalized)) === count($required)
                && $strongCount >= 6
                && (null === $candidate || $strongCount > $candidate['strong_count'])) {
                $candidate = ['index' => $index, 'headers' => $values, 'strong_count' => $strongCount];
            }
        }
        if (null === $candidate) {
            throw new RuntimeException('A Blast Motion baseball swing-sensor header row was not found.');
        }

        $headers = array_values($candidate['headers']);
        $fieldMap = $this->fields->resolve($headers);
        $sourceRows = [];
        $blankRows = 0;
        foreach (array_slice($physicalRows, $candidate['index'] + 1, null, true) as $index => $values) {
            if ([] === array_filter($values, fn ($value): bool => '' !== trim((string) $value))) {
                ++$blankRows;
                continue;
            }
            $values = array_pad(array_slice($values, 0, count($headers)), count($headers), '');
            $record = array_combine($headers, $values);
            $record['_source_row'] = $index + 1;
            if ('' !== trim((string) ($record['Date'] ?? ''))) {
                $sourceRows[] = $record;
            }
        }

        $metadata = [];
        foreach (array_slice($physicalRows, 0, $candidate['index']) as $values) {
            $label = rtrim(trim((string) ($values[0] ?? '')), ':');
            $value = trim((string) ($values[1] ?? ''));
            if (in_array($label, ['Academy', 'Report Date', 'Date Range'], true) && '' !== $value) {
                $metadata[$label] = $value;
            }
        }

        return [
            'header_row' => $candidate['index'] + 1,
            'headers' => $headers,
            'header_count' => count($headers),
            'source_rows' => $sourceRows,
            'field_map' => $fieldMap,
            'unknown_headers' => array_values(array_diff($headers, array_values($fieldMap))),
            'metadata' => $metadata,
            'physical_row_count' => count($physicalRows),
            'blank_row_count' => $blankRows + count(array_filter(
                array_slice($physicalRows, 0, $candidate['index']),
                fn (array $row): bool => [] === array_filter($row, fn ($value): bool => '' !== trim((string) $value))
            )),
            'detection_confidence' => min(100, 50 + $candidate['strong_count'] * 5),
        ];
    }

    private function handedness(mixed $value): ?string
    {
        return match (mb_strtolower(trim((string) $value))) {
            'right', 'r' => 'Right',
            'left', 'l' => 'Left',
            default => null,
        };
    }
}
