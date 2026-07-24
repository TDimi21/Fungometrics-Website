<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

final class TrackManImporter
{
    private const ALIASES = [
        'exit_velocity_mph' => ['ExitSpeed', 'Exit Velocity', 'ExitVelocity'],
        'launch_angle_deg' => ['Angle', 'LaunchAngle', 'Launch Angle'],
        'spray_angle_deg' => ['Direction', 'SprayAngle', 'HorizontalAngle'],
        'measured_distance_ft' => ['Distance', 'ProjectedDistance', 'CarryDistance'],
        'last_tracked_distance_ft' => ['LastTrackedDistance'],
        'measured_spin_rpm' => ['HitSpinRate'],
        'measured_spin_axis_deg' => ['HitSpinAxis'],
        'measured_hang_time_seconds' => ['HangTime'],
        'measured_max_height_ft' => ['MaxHeight'],
        'contact_height_ft' => ['ContactPositionZ'],
        'launch_confidence' => ['HitLaunchConfidence'],
        'landing_confidence' => ['HitLandingConfidence'],
        'tagged_hit_type' => ['TaggedHitType'],
        'automatic_hit_type' => ['AutoHitType'],
        'source_row_identifier' => ['PitchNo'],
        'source_event_identifier' => ['PitchUID', 'PlayID'],
        'source_session_identifier' => ['GameID', 'GameUID'],
        'player_name' => ['Batter'],
        'player_external_identifier' => ['BatterId'],
        'player_level' => ['Level'],
        'event_date' => ['Date', 'UTCDate'],
        'facility_name' => ['Stadium'],
        'system' => ['System'],
        'pitch_call' => ['PitchCall'],
    ];

    public function detects(array $headers): bool
    {
        $keys = array_map([$this, 'key'], $headers);

        return in_array('exitspeed', $keys, true)
            && in_array('angle', $keys, true)
            && (in_array('pitchuid', $keys, true) || in_array('gameid', $keys, true));
    }

    /** @return list<array<string,mixed>> */
    public function import(string $path): array
    {
        return $this->inspect($path)['rows'];
    }

    /** @return array<string,mixed> */
    public function inspect(string $path, array $context = []): array
    {
        [$headers, $sourceRows] = $this->csv($path);
        $indexes = $this->indexes($headers);
        $rows = [];
        $exclusions = [];
        $batted = 0;

        foreach ($sourceRows as $offset => $sourceRow) {
            $row = [];
            foreach (self::ALIASES as $target => $aliases) {
                $row[$target] = $this->value($sourceRow, $indexes, $aliases);
            }
            $numeric = [
                'exit_velocity_mph', 'launch_angle_deg', 'spray_angle_deg',
                'measured_distance_ft', 'last_tracked_distance_ft', 'measured_spin_rpm',
                'measured_spin_axis_deg', 'measured_hang_time_seconds',
                'measured_max_height_ft', 'contact_height_ft',
            ];
            foreach ($numeric as $field) {
                $row[$field] = $this->number($row[$field]);
            }

            $hasBattedData = collect([
                $row['exit_velocity_mph'], $row['launch_angle_deg'],
                $row['measured_distance_ft'], $row['last_tracked_distance_ft'],
            ])->contains(fn ($value) => $value !== null);
            if (!$hasBattedData) {
                continue;
            }
            $batted++;

            $reasons = $this->exclusions($row);
            foreach ($reasons as $reason) {
                $exclusions[$reason] = ($exclusions[$reason] ?? 0) + 1;
            }
            $session = (string) ($row['source_session_identifier'] ?: pathinfo($path, PATHINFO_FILENAME));
            $partition = (string) ($context['partition'] ?? BallFlightPartitionService::partition($session));
            $raw = array_combine($headers, array_pad($sourceRow, count($headers), null)) ?: [];
            $raw['_partition_basis'] = $session;
            unset($row['system'], $row['pitch_call']);

            $normalized = array_merge($row, [
                'source_type' => 'trackman',
                'source_name' => 'TrackMan',
                'source_file' => basename($path),
                'facility_id' => $context['facility_id'] ?? null,
                'player_level' => $context['player_level'] ?? $row['player_level'],
                'age_group' => $context['age_group'] ?? null,
                'eligible_for_primary_calibration' => $reasons === [],
                'eligible_for_external_validation' => false,
                'partition' => $partition,
                'exclusion_reason' => $reasons === [] ? null : implode('; ', $reasons),
                'raw_metadata' => $raw,
            ]);
            $normalized['import_hash'] = $this->hash($normalized);
            $rows[] = $normalized;
        }

        return $this->summary($path, $headers, count($sourceRows), $batted, $rows, $exclusions);
    }

    /** @return list<string> */
    private function exclusions(array $row): array
    {
        $reasons = [];
        if ($row['exit_velocity_mph'] === null || $row['launch_angle_deg'] === null || $row['measured_distance_ft'] === null) {
            $reasons[] = 'missing_primary_measurement';
        }
        if ($row['exit_velocity_mph'] !== null && ($row['exit_velocity_mph'] < 20 || $row['exit_velocity_mph'] > 130)) {
            $reasons[] = 'invalid_exit_velocity';
        }
        if ($row['launch_angle_deg'] !== null && ($row['launch_angle_deg'] < -90 || $row['launch_angle_deg'] > 90)) {
            $reasons[] = 'invalid_launch_angle';
        }
        if ($row['measured_distance_ft'] !== null && ($row['measured_distance_ft'] < 0 || $row['measured_distance_ft'] > 600)) {
            $reasons[] = 'invalid_distance';
        }
        if ($row['spray_angle_deg'] !== null && abs($row['spray_angle_deg']) > 90) {
            $reasons[] = 'extreme_or_backward_spray';
        }
        $launchConfidence = mb_strtolower(trim((string) $row['launch_confidence']));
        if (!in_array($launchConfidence, ['high', 'medium'], true)) {
            $reasons[] = 'low_or_missing_launch_confidence';
        }
        $landingConfidence = mb_strtolower(trim((string) $row['landing_confidence']));
        if (!in_array($landingConfidence, ['high', 'medium'], true)) {
            $reasons[] = 'low_or_missing_landing_confidence';
        }
        $description = mb_strtolower(implode(' ', [
            $row['tagged_hit_type'], $row['automatic_hit_type'], $row['pitch_call'],
        ]));
        if (str_contains($description, 'bunt')) {
            $reasons[] = 'bunt';
        }
        if (str_contains($description, 'foul')) {
            $reasons[] = 'foul_contact';
        }

        return array_values(array_unique($reasons));
    }

    private function hash(array $row): string
    {
        return hash('sha256', json_encode([
            'trackman',
            $row['source_event_identifier'],
            $row['source_session_identifier'],
            $row['source_row_identifier'],
            $row['exit_velocity_mph'],
            $row['launch_angle_deg'],
            $row['spray_angle_deg'],
            $row['measured_distance_ft'],
        ], JSON_PRESERVE_ZERO_FRACTION));
    }

    /** @return array<string,mixed> */
    private function summary(string $path, array $headers, int $total, int $batted, array $rows, array $exclusions): array
    {
        return [
            'source' => 'trackman', 'file' => $path, 'file_hash' => hash_file('sha256', $path),
            'headers' => $headers, 'total_rows' => $total, 'batted_ball_rows' => $batted,
            'eligible_calibration_rows' => count(array_filter($rows, fn ($r) => $r['eligible_for_primary_calibration'])),
            'eligible_external_validation_rows' => 0, 'excluded_by_reason' => $exclusions,
            'rows_with_measured_spin' => count(array_filter($rows, fn ($r) => $r['measured_spin_rpm'] !== null)),
            'rows_with_measured_hang_time' => count(array_filter($rows, fn ($r) => $r['measured_hang_time_seconds'] !== null)),
            'rows_with_measured_max_height' => count(array_filter($rows, fn ($r) => $r['measured_max_height_ft'] !== null)),
            'duplicate_rows' => count($rows) - count(array_unique(array_column($rows, 'import_hash'))),
            'rows' => $rows,
        ];
    }

    /** @return array{0:list<string>,1:list<array<int,string|null>>} */
    private function csv(string $path): array
    {
        if (!is_readable($path)) throw new \InvalidArgumentException("CSV is not readable: {$path}");
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new \RuntimeException("Unable to open CSV: {$path}");
        try {
            $headers = fgetcsv($handle);
            if (!is_array($headers)) return [[], []];
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) $rows[] = $row;
            return [array_map(fn ($v) => trim((string) $v), $headers), $rows];
        } finally {
            fclose($handle);
        }
    }

    private function indexes(array $headers): array
    {
        $indexes = [];
        foreach ($headers as $i => $header) $indexes[$this->key($header)] = $i;
        return $indexes;
    }

    private function value(array $row, array $indexes, array $aliases): mixed
    {
        foreach ($aliases as $alias) {
            $i = $indexes[$this->key($alias)] ?? null;
            if ($i !== null && isset($row[$i]) && trim((string) $row[$i]) !== '') return trim((string) $row[$i]);
        }
        return null;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function key(mixed $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $value))) ?? '';
    }
}
