<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

final class StatcastImporter
{
    public function detects(array $headers): bool
    {
        $keys = array_map([$this, 'key'], $headers);

        return (in_array('launchspeed', $keys, true) || in_array('exitvelocitymph', $keys, true))
            && (in_array('hitdistancesc', $keys, true) || in_array('distanceft', $keys, true));
    }

    /** @return list<array<string,mixed>> */
    public function import(string $path): array
    {
        return $this->inspect($path)['rows'];
    }

    /** @return array<string,mixed> */
    public function inspect(string $path, array $context = []): array
    {
        if (!is_readable($path)) throw new \InvalidArgumentException("CSV is not readable: {$path}");
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new \RuntimeException("Unable to open CSV: {$path}");
        try {
            $headers = fgetcsv($handle);
            if (!is_array($headers)) return ['source' => 'statcast', 'headers' => [], 'rows' => []];
            $headers = array_map(fn ($v) => trim((string) $v), $headers);
            $indexes = [];
            foreach ($headers as $i => $header) $indexes[$this->key($header)] = $i;
            $rows = [];
            $total = 0;
            $batted = 0;
            $exclusions = [];
            while (($sourceRow = fgetcsv($handle)) !== false) {
                $total++;
                $ev = $this->number($sourceRow, $indexes, ['launch_speed', 'exit_velocity_mph']);
                $la = $this->number($sourceRow, $indexes, ['launch_angle', 'launch_angle_deg']);
                $distance = $this->number($sourceRow, $indexes, ['hit_distance_sc', 'distance_ft']);
                $spray = $this->number($sourceRow, $indexes, ['spray_angle_deg', 'spray_angle']);
                if ($ev === null && $la === null && $distance === null) continue;
                $batted++;

                $event = $this->text($sourceRow, $indexes, ['events', 'event']);
                $description = $this->text($sourceRow, $indexes, ['description']);
                $reasons = [];
                if ($ev === null || $la === null || $distance === null) $reasons[] = 'missing_primary_measurement';
                if ($ev !== null && ($ev < 20 || $ev > 130)) $reasons[] = 'invalid_exit_velocity';
                if ($la !== null && ($la < -90 || $la > 90)) $reasons[] = 'invalid_launch_angle';
                if ($distance !== null && ($distance < 0 || $distance > 600)) $reasons[] = 'invalid_distance';
                if ($spray !== null && abs($spray) > 90) $reasons[] = 'extreme_or_backward_spray';
                if (str_contains(mb_strtolower("{$event} {$description}"), 'bunt')) $reasons[] = 'bunt';
                foreach ($reasons as $reason) $exclusions[$reason] = ($exclusions[$reason] ?? 0) + 1;

                $raw = array_combine($headers, array_pad($sourceRow, count($headers), null)) ?: [];
                $sprayDerived = mb_strtolower((string) $this->text($sourceRow, $indexes, ['spray_angle_is_derived'])) === 'true';
                $raw['_spray_angle_source'] = $sprayDerived ? 'derived_from_hit_coordinates' : 'provided';
                $identifier = $this->text($sourceRow, $indexes, ['sample_id', 'swing_id']);
                $normalized = [
                    'source_type' => 'statcast', 'source_name' => 'MLB Statcast',
                    'source_file' => basename($path), 'source_row_identifier' => $identifier,
                    'source_event_identifier' => $this->text($sourceRow, $indexes, ['game_pk']),
                    'source_session_identifier' => $this->text($sourceRow, $indexes, ['game_pk']),
                    'player_name' => $this->text($sourceRow, $indexes, ['player_name', 'batter_name']),
                    'player_external_identifier' => $this->text($sourceRow, $indexes, ['batter', 'batter_mlbam_id']),
                    'player_level' => $context['player_level'] ?? 'MLB', 'age_group' => $context['age_group'] ?? null,
                    'event_date' => $this->text($sourceRow, $indexes, ['game_date']),
                    'facility_name' => null, 'facility_id' => $context['facility_id'] ?? null,
                    'exit_velocity_mph' => $ev, 'launch_angle_deg' => $la, 'spray_angle_deg' => $spray,
                    'measured_distance_ft' => $distance, 'last_tracked_distance_ft' => null,
                    'measured_hang_time_seconds' => null, 'measured_max_height_ft' => null,
                    'measured_spin_rpm' => null, 'measured_spin_axis_deg' => null, 'contact_height_ft' => null,
                    'tagged_hit_type' => $this->text($sourceRow, $indexes, ['bb_type']),
                    'automatic_hit_type' => $event, 'launch_confidence' => null, 'landing_confidence' => null,
                    'ball_type' => null, 'temperature_f' => null, 'humidity_percent' => null,
                    'pressure_inhg' => null, 'elevation_ft' => null,
                    'eligible_for_primary_calibration' => false,
                    'eligible_for_external_validation' => $reasons === [],
                    'partition' => 'external_validation',
                    'exclusion_reason' => $reasons === [] ? null : implode('; ', array_unique($reasons)),
                    'raw_metadata' => $raw,
                ];
                $normalized['import_hash'] = hash('sha256', json_encode([
                    'statcast', $identifier, $normalized['source_event_identifier'], $ev, $la, $spray, $distance,
                ], JSON_PRESERVE_ZERO_FRACTION));
                $rows[] = $normalized;
            }
        } finally {
            fclose($handle);
        }

        return [
            'source' => 'statcast', 'file' => $path, 'file_hash' => hash_file('sha256', $path),
            'headers' => $headers, 'total_rows' => $total, 'batted_ball_rows' => $batted,
            'eligible_calibration_rows' => 0,
            'eligible_external_validation_rows' => count(array_filter($rows, fn ($r) => $r['eligible_for_external_validation'])),
            'excluded_by_reason' => $exclusions, 'rows_with_measured_spin' => 0,
            'rows_with_measured_hang_time' => 0, 'rows_with_measured_max_height' => 0,
            'duplicate_rows' => count($rows) - count(array_unique(array_column($rows, 'import_hash'))),
            'rows' => $rows,
        ];
    }

    private function number(array $row, array $indexes, array $aliases): ?float
    {
        $value = $this->text($row, $indexes, $aliases);
        return is_numeric($value) ? (float) $value : null;
    }

    private function text(array $row, array $indexes, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            $i = $indexes[$this->key($alias)] ?? null;
            if ($i !== null && isset($row[$i]) && trim((string) $row[$i]) !== '') return trim((string) $row[$i]);
        }
        return null;
    }

    private function key(mixed $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $value))) ?? '';
    }
}
