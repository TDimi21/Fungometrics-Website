<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\BlastMotion;

use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\DTOs\ImportFileMetadata;

final class BlastMotionInspectionService
{
    private const UNITS = [
        'batspeedmph' => 'mph',
        'rotationalaccelerationg' => 'g_force',
        'onplaneefficiency' => 'percent',
        'attackangledeg' => 'deg',
        'earlyconnectiondeg' => 'deg',
        'connectionatimpactdeg' => 'deg',
        'verticalbatangledeg' => 'deg',
        'powerkw' => 'kw',
        'timetocontactsec' => 'sec',
        'peakhandspeedmph' => 'mph',
        'exitvelocitymph' => 'mph',
        'launchangledeg' => 'deg',
        'estimateddistancefeet' => 'ft',
    ];

    public function __construct(
        private readonly BlastMotionParser $parser,
        private readonly TemplateFingerprintService $fingerprints,
    ) {
    }

    /** @return array<string, mixed> */
    public function inspect(ImportFileMetadata $file): array
    {
        $report = $this->parser->report($file);
        $rows = iterator_to_array($this->parser->parse($file), false);
        $columns = $this->sourceColumns($report['headers'], $report['source_rows']);
        $invalidRows = 0;
        foreach ($rows as &$row) {
            $row['_warnings'] = $this->warnings($row);
            if ([] !== $row['_warnings']) {
                ++$invalidRows;
            }
        }
        unset($row);
        $unavailable = count(array_filter($columns, fn (array $column): bool => $column['default_not_importing'] ?? false));
        $warnings = $unavailable > 0
            ? ["{$unavailable} all-blank ball-flight columns default to Not Importing."]
            : [];
        if ($invalidRows > 0) {
            $warnings[] = "{$invalidRows} swing row(s) contain values requiring review.";
        }
        $sessionName = 'Blast Motion Swing Session';

        return [
            'platform' => 'blast-motion',
            'file' => ['name' => $file->name, 'size_bytes' => $file->sizeBytes, 'extension' => $file->extension],
            'detected_format' => [
                'provider' => 'Blast Motion', 'data_type' => 'hitting',
                'display_type' => 'Baseball Swing Sensor Session',
                'confidence' => $report['detection_confidence'],
                'header_row' => $report['header_row'], 'header_count' => $report['header_count'],
            ],
            'session' => [
                'dates' => [], 'primary_date' => $rows[0]['event_timestamp'] ?? null,
                'facility' => $report['metadata']['Academy'] ?? null, 'system' => 'Blast Motion',
                'detected_session_count' => count($rows) > 0 ? 1 : 0,
                'metadata_summary' => $report['metadata'],
            ],
            'counts' => [
                'total_rows' => count($rows), 'usable_rows' => count($rows) - $invalidRows,
                'invalid_rows' => $invalidRows, 'populated_swing_rows' => count($rows),
                'players_found' => 1, 'sessions_found' => count($rows) > 0 ? 1 : 0,
                'columns_found' => count($columns), 'unavailable_columns' => $unavailable,
                'blank_rows' => $report['blank_row_count'],
            ],
            'players' => [[
                'source_key' => 'blast-motion:session', 'source_name' => $sessionName,
                'normalized_name' => 'blast motion swing session', 'external_player_id' => null,
                'roles' => ['batter'], 'row_count' => count($rows), 'batter_row_count' => count($rows),
                'source_team_names' => [], 'suggested_matches' => [], 'suggested_player' => null,
                'mapping_status' => 'session_assignment_required', 'external_name' => $sessionName,
                'external_identifier' => null, 'data_types' => ['batter'], 'identity_missing' => true,
                'remember_mapping' => false,
                'assignment_help' => 'No player name was found in this report. Connect this session to one selected-team roster player.',
            ]],
            'metrics_detected' => array_values($report['field_map']),
            'unknown_headers' => $report['unknown_headers'],
            'warnings' => $warnings,
            'sample_rows' => array_slice(array_map(fn (array $row): array => [
                'player_external_name' => $sessionName,
                'occurred_at' => $row['event_timestamp'] ?? null,
                'metrics' => array_filter([
                    'bat_speed_mph' => $this->number($row['bat_speed_mph'] ?? null),
                    'rotational_acceleration_g' => $this->number($row['rotational_acceleration_g'] ?? null),
                    'attack_angle_deg' => $this->number($row['attack_angle_deg'] ?? null),
                    'peak_hand_speed_mph' => $this->number($row['peak_hand_speed_mph'] ?? null),
                ], fn ($value): bool => null !== $value),
                'controlled_values' => [
                    'handedness' => ['raw' => $row['batter_side'] ?? null, 'canonical_preview' => $row['batter_side_canonical']],
                    'equipment' => ['raw' => $row['bat_equipment'] ?? null, 'canonical_preview' => $row['bat_equipment'] ?? null],
                    'swing_details' => ['raw' => $row['swing_details'] ?? null, 'canonical_preview' => $row['swing_details'] ?? null],
                ],
                'raw_source_values' => $row['_raw'],
                'validation' => ['valid' => [] === $row['_warnings'], 'warnings' => $row['_warnings']],
                'source' => ['platform' => 'blast-motion', 'source_row' => $row['_source_row']],
            ], $rows), 0, 10),
            'source_columns' => $columns,
            'template_fingerprint' => $this->fingerprints->fingerprint(array_column($columns, 'source_column_name')),
            'destination_recommendation' => [
                'detected_data_type' => 'hitting', 'recommended' => ['Batting Practice'],
                'compatible' => ['Cage', 'Assessment'],
                'incompatible' => ['Live AB', 'Bullpen', 'Long Toss', 'Weighted Balls', 'Strength', 'Mobility', 'Recovery'],
                'selected' => null, 'advisory_only' => true,
            ],
            'report' => [
                'header_row' => $report['header_row'], 'physical_rows' => $report['physical_row_count'],
                'metadata_summary' => $report['metadata'], 'has_player_identity' => false,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sourceColumns(array $headers, array $rows): array
    {
        return array_map(function (string $header) use ($rows): array {
            $normalized = TemplateFingerprintService::normalize($header);
            $values = array_map(fn (array $row): string => trim((string) ($row[$header] ?? '')), $rows);
            $populated = array_values(array_filter($values, fn (string $value): bool => '' !== $value));
            $numeric = array_values(array_filter($populated, 'is_numeric'));
            $isNumeric = count($populated) > 0 && count($numeric) === count($populated);
            $unavailable = in_array($normalized, ['exitvelocitymph', 'launchangledeg', 'estimateddistancefeet'], true)
                && [] === $populated;

            return [
                'source_column_name' => $header,
                'normalized_source_column' => $normalized,
                'sample_values' => array_slice(array_values(array_unique($populated)), 0, 5),
                'suggested_source_unit_key' => self::UNITS[$normalized] ?? null,
                'default_not_importing' => $unavailable,
                'source_specific' => in_array($normalized, ['planescore', 'connectionscore', 'rotationscore', 'powerkw'], true),
                'controlled_value_transformations' => 'handedness' === $normalized ? ['Right/R → Right', 'Left/L → Left'] : [],
                'warnings' => $unavailable ? ['This Blast report does not contain values for this metric.'] : [],
                'details' => [
                    'inferred_data_type' => $isNumeric ? 'numeric' : ('date' === $normalized ? 'datetime' : 'text'),
                    'minimum' => $isNumeric ? min(array_map('floatval', $numeric)) : null,
                    'maximum' => $isNumeric ? max(array_map('floatval', $numeric)) : null,
                    'average' => $isNumeric ? array_sum(array_map('floatval', $numeric)) / count($numeric) : null,
                    'unique_value_count' => count(array_unique($populated)),
                    'populated_count' => count($populated), 'blank_count' => count($values) - count($populated),
                    'zero_count' => count(array_filter($numeric, fn ($value): bool => 0.0 === (float) $value)),
                ],
            ];
        }, $headers);
    }

    /** @return array<int, string> */
    private function warnings(array $row): array
    {
        $warnings = [];
        foreach (['bat_speed_mph' => 'Bat Speed', 'peak_hand_speed_mph' => 'Peak Hand Speed', 'blast_swing_power_kw' => 'Power', 'time_to_contact_seconds' => 'Time to Contact'] as $key => $label) {
            if (is_numeric($row[$key] ?? null) && (float) $row[$key] < 0) {
                $warnings[] = "{$label} cannot be negative.";
            }
        }
        $efficiency = $this->number($row['on_plane_efficiency_percent'] ?? null);
        if (null !== $efficiency && ($efficiency < 0 || $efficiency > 100)) {
            $warnings[] = 'On-Plane Efficiency is outside 0–100.';
        }
        if (null === $row['batter_side_canonical']) {
            $warnings[] = 'Handedness is not a supported Right/Left value.';
        }
        if (false === strtotime((string) ($row['event_timestamp'] ?? ''))) {
            $warnings[] = 'Date is malformed.';
        }

        return $warnings;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
