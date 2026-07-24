<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\TrackMan;

use App\Services\DataHub\Contracts\ImportParserContract;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\DTOs\ImportInspectionResult;
use RuntimeException;

final class TrackManParser implements ImportParserContract
{
    public function __construct(private readonly TrackManFieldMap $fields)
    {
    }

    public function inspect(ImportFileMetadata $file): ImportInspectionResult
    {
        $rows = iterator_to_array($this->parse($file), false);

        return new ImportInspectionResult(['rows' => $rows]);
    }

    public function parse(ImportFileMetadata $file): iterable
    {
        if ('csv' !== mb_strtolower($file->extension)) {
            throw new RuntimeException('XLSX inspection requires an approved server-side spreadsheet reader.');
        }
        if ( ! $file->path || ! is_readable($file->path)) {
            throw new RuntimeException('The temporary inspection file is unavailable.');
        }
        $handle = fopen($file->path, 'rb');
        if (false === $handle) {
            throw new RuntimeException('The TrackMan CSV could not be opened.');
        }
        try {
            $headers = fgetcsv($handle);
            if (false === $headers) {
                throw new RuntimeException('The TrackMan CSV is empty.');
            }
            $headers = array_map(fn ($header): string => preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header)), $headers);
            $map = $this->fields->resolve($headers);
            $hitting = isset($map['batter']) && count(array_intersect(['exit_velocity_mph', 'launch_angle_deg', 'distance_ft'], array_keys($map))) > 0;
            $pitching = isset($map['pitcher']) && count(array_intersect(['pitch_velocity_mph', 'pitch_spin_rate_rpm'], array_keys($map))) > 0;
            if ( ! $hitting && ! $pitching) {
                throw new RuntimeException('Required TrackMan player and metric headers were not found.');
            }
            $dataType = $hitting && $pitching ? 'mixed' : ($hitting ? 'hitting' : 'pitching');
            while (($values = fgetcsv($handle)) !== false) {
                if ([] === array_filter($values, fn ($value): bool => '' !== trim((string) $value))) {
                    continue;
                }
                $values = array_pad(array_slice($values, 0, count($headers)), count($headers), null);
                $raw = array_combine($headers, $values);
                $row = ['_data_type' => $dataType];
                foreach ($map as $canonical => $actual) {
                    $row[$canonical] = $raw[$actual] ?? null;
                }
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }
}
