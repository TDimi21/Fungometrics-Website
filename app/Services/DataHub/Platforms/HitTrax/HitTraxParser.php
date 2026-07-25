<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\HitTrax;

use App\Services\DataHub\Contracts\ImportParserContract;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\DTOs\ImportInspectionResult;
use RuntimeException;

final class HitTraxParser implements ImportParserContract
{
    public function __construct(private readonly HitTraxFieldMap $fields)
    {
    }

    public function inspect(ImportFileMetadata $file): ImportInspectionResult
    {
        return new ImportInspectionResult(['rows' => iterator_to_array($this->parse($file), false)]);
    }

    public function parse(ImportFileMetadata $file): iterable
    {
        [$headers, $rows] = $this->read($file);
        $map = $this->fields->resolve($headers);
        if ( ! isset($map['user'], $map['exit_velocity_mph'])) {
            throw new RuntimeException('Required HitTrax User and Velo headers were not found.');
        }
        foreach ($rows as $values) {
            $raw = array_combine($headers, $values);
            $row = [];
            foreach ($map as $canonical => $source) {
                $row[$canonical] = trim((string) ($raw[$source] ?? ''));
            }
            yield $row;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function inspectSourceColumns(ImportFileMetadata $file): array
    {
        [$headers, $rows] = $this->read($file);

        return array_map(function (string $header) use ($headers, $rows): array {
            $index = array_search($header, $headers, true);
            $values = array_values(array_filter(array_map(
                fn (array $row): string => trim((string) ($row[$index] ?? '')),
                $rows
            ), fn (string $value): bool => '' !== $value));
            $samples = array_slice(array_values(array_unique($values)), 0, 5);
            $numeric = array_values(array_filter($values, 'is_numeric'));
            $isNumeric = count($values) > 0 && count($values) === count($numeric);
            $zeroCount = count(array_filter($numeric, fn ($value): bool => 0.0 === (float) $value));

            return [
                'source_column_name' => trim($header),
                'normalized_source_column' => mb_strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim($header))),
                'sample_values' => $samples,
                'details' => [
                    'inferred_data_type' => $isNumeric ? 'numeric' : 'text',
                    'minimum' => $isNumeric ? min(array_map('floatval', $numeric)) : null,
                    'maximum' => $isNumeric ? max(array_map('floatval', $numeric)) : null,
                    'average' => $isNumeric ? array_sum(array_map('floatval', $numeric)) / count($numeric) : null,
                    'unique_value_count' => count(array_unique($values)),
                    'populated_count' => count($values),
                    'zero_count' => $zeroCount,
                    'all_zero' => $isNumeric && count($numeric) > 0 && $zeroCount === count($numeric),
                ],
            ];
        }, $headers);
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, mixed>>} */
    private function read(ImportFileMetadata $file): array
    {
        if ('csv' !== mb_strtolower($file->extension)) {
            throw new RuntimeException('XLSX inspection requires an approved server-side spreadsheet reader.');
        }
        if ( ! $file->path || ! is_readable($file->path)) {
            throw new RuntimeException('The temporary HitTrax inspection file is unavailable.');
        }
        $handle = fopen($file->path, 'rb');
        if (false === $handle) {
            throw new RuntimeException('The HitTrax CSV could not be opened.');
        }
        try {
            $headers = fgetcsv($handle);
            if (false === $headers) {
                throw new RuntimeException('The HitTrax CSV is empty.');
            }
            $headers = array_map(fn ($header): string => preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header)), $headers);
            $rows = [];
            while (($values = fgetcsv($handle)) !== false) {
                if ([] === array_filter($values, fn ($value): bool => '' !== trim((string) $value))) {
                    continue;
                }
                $rows[] = array_pad(array_slice($values, 0, count($headers)), count($headers), null);
            }

            return [$headers, $rows];
        } finally {
            fclose($handle);
        }
    }
}
