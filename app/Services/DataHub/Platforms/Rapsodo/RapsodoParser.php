<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\Rapsodo;

use App\Services\DataHub\Contracts\ImportParserContract;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\DTOs\ImportInspectionResult;
use App\Services\DataHub\Support\SecureXlsxReader;
use RuntimeException;

final class RapsodoParser implements ImportParserContract
{
    public function __construct(
        private readonly SecureXlsxReader $xlsx,
        private readonly RapsodoFieldMap $fields,
    ) {
    }

    public function inspect(ImportFileMetadata $file): ImportInspectionResult
    {
        return new ImportInspectionResult($this->workbook($file));
    }

    public function parse(ImportFileMetadata $file): iterable
    {
        $workbook = $this->workbook($file);
        foreach ($workbook['source_rows'] as $sourceRow) {
            $row = ['_worksheet' => $workbook['worksheet'], '_source_row' => $sourceRow['_source_row']];
            foreach ($workbook['field_map'] as $canonical => $header) {
                $row[$canonical] = $sourceRow[$header] ?? '';
                $row['_raw'][$header] = $sourceRow[$header] ?? '';
            }
            $row['event_time_display'] = $this->excelTime($row['event_time'] ?? null);
            $row['strike_boolean'] = $this->strike($row['strike'] ?? null);
            $row['pitch_type_canonical'] = $this->pitchType($row['pitch_type'] ?? null);
            yield $row;
        }
    }

    /** @return array<string, mixed> */
    public function workbook(ImportFileMetadata $file): array
    {
        if ('xlsx' !== mb_strtolower($file->extension)) {
            throw new RuntimeException('This observed Rapsodo format requires an XLSX workbook.');
        }
        $result = $this->xlsx->read((string) $file->path);
        $candidate = null;
        foreach ($result['sheets'] as $sheet) {
            foreach ($sheet['rows'] as $rowNumber => $values) {
                $headers = array_values($values);
                $normalized = array_map([TemplateFingerprintService::class, 'normalize'], $headers);
                $strongCount = count(array_intersect($this->fields->strongSignals(), $normalized));
                if ($strongCount >= 6 && (null === $candidate || $strongCount > $candidate['strongCount'])) {
                    $candidate = compact('sheet', 'rowNumber', 'headers', 'strongCount');
                }
                if ($rowNumber >= 50) {
                    break;
                }
            }
        }
        if (null === $candidate) {
            throw new RuntimeException('A Rapsodo pitching header row was not found.');
        }
        $headers = array_map(fn (string $header): string => trim(str_replace("\u{00A0}", ' ', $header)), $candidate['headers']);
        $columns = array_keys($candidate['sheet']['rows'][$candidate['rowNumber']]);
        $headerByColumn = array_combine($columns, $headers);
        $sourceRows = [];
        foreach ($candidate['sheet']['rows'] as $rowNumber => $values) {
            if ($rowNumber <= $candidate['rowNumber']) {
                continue;
            }
            $record = ['_source_row' => $rowNumber];
            foreach ($headerByColumn as $column => $header) {
                $record[$header] = trim((string) ($values[$column] ?? ''));
            }
            if ('' === trim((string) ($record['no'] ?? ''))
                || [] === array_filter(array_diff_key($record, ['_source_row' => true]), fn (string $value): bool => '' !== $value)) {
                continue;
            }
            $sourceRows[] = $record;
        }
        $fieldMap = $this->fields->resolve($headers);
        $unknownHeaders = array_values(array_diff($headers, array_values($fieldMap)));

        return [
            'worksheet' => $candidate['sheet']['name'],
            'worksheet_names' => array_column($result['sheets'], 'name'),
            'header_row' => $candidate['rowNumber'],
            'headers' => $headers,
            'header_count' => count($headers),
            'source_rows' => $sourceRows,
            'field_map' => $fieldMap,
            'unknown_headers' => $unknownHeaders,
            'detection_confidence' => min(100, 55 + $candidate['strongCount'] * 5),
            'workbook_warnings' => array_values(array_unique(array_merge(
                $result['warnings'],
                $candidate['sheet']['formulas'] > 0 ? ['Formula cells were not executed; only cached cell values were inspected.'] : [],
                [] !== $candidate['sheet']['merged_ranges'] ? ['Merged cells exist on the detected worksheet and were not expanded.'] : [],
            ))),
        ];
    }

    private function excelTime(mixed $value): ?string
    {
        if ( ! is_numeric($value)) {
            return null;
        }
        $seconds = (int) round(fmod((float) $value, 1.0) * 86400);

        return gmdate('H:i:s', $seconds);
    }

    private function strike(mixed $value): ?bool
    {
        return match (mb_strtolower(trim((string) $value))) {
            'y', 'yes', 'true', '1' => true,
            'n', 'no', 'false', '0' => false,
            default => null,
        };
    }

    private function pitchType(mixed $value): ?string
    {
        return match (mb_strtoupper(trim((string) $value))) {
            'FB' => 'Fastball',
            '2FB' => 'Two-Seam Fastball',
            'CV' => 'Curveball',
            'SL' => 'Slider',
            'KN' => 'Knuckleball',
            default => null,
        };
    }
}
