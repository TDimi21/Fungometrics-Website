<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\Rapsodo;

use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;

final class RapsodoInspectionService
{
    private const UNITS = [
        'velocity' => 'mph',
        'spinrate' => 'rpm',
        'truespin' => 'rpm',
        'spineff' => 'percent',
        'horzbreak' => 'in',
        'vertbreak' => 'in',
        'relht' => 'ft',
        'relside' => 'ft',
        'rangle' => 'deg',
        'hangle' => 'deg',
        'gyro' => 'deg',
    ];

    public function __construct(
        private readonly RapsodoParser $parser,
        private readonly TemplateFingerprintService $fingerprints,
    ) {
    }

    /** @return array<string, mixed> */
    public function inspect(ImportFileMetadata $file): array
    {
        $workbook = $this->parser->workbook($file);
        $rows = iterator_to_array($this->parser->parse($file), false);
        $warnings = $workbook['workbook_warnings'];
        $invalidRows = 0;
        foreach ($rows as &$row) {
            $row['_warnings'] = $this->warnings($row);
            if ([] !== $row['_warnings']) {
                ++$invalidRows;
            }
        }
        unset($row);
        if ($invalidRows > 0) {
            $warnings[] = "{$invalidRows} pitch row(s) contain missing, invalid, or uncertain values.";
        }
        $warnings[] = 'Workbook headers do not declare units. Suggested Rapsodo source units require coach confirmation.';
        $columns = $this->sourceColumns($workbook['headers'], $workbook['source_rows']);
        $sessionName = 'Rapsodo Pitching Session';

        return [
            'platform' => 'rapsodo',
            'file' => ['name' => $file->name, 'size_bytes' => $file->sizeBytes, 'extension' => $file->extension],
            'detected_format' => [
                'provider' => 'Rapsodo',
                'data_type' => 'pitching',
                'display_type' => 'Pitching Session',
                'confidence' => $workbook['detection_confidence'],
                'worksheet' => $workbook['worksheet'],
                'header_row' => $workbook['header_row'],
                'header_count' => $workbook['header_count'],
            ],
            'session' => [
                'dates' => [],
                'primary_date' => null,
                'facility' => null,
                'system' => 'Rapsodo',
                'detected_session_count' => count($rows) > 0 ? 1 : 0,
                'worksheet' => $workbook['worksheet'],
            ],
            'counts' => [
                'total_rows' => count($rows),
                'usable_rows' => count($rows) - $invalidRows,
                'invalid_rows' => $invalidRows,
                'populated_pitch_rows' => count($rows),
                'players_found' => 1,
                'sessions_found' => count($rows) > 0 ? 1 : 0,
                'columns_found' => count($columns),
            ],
            'players' => [[
                'source_key' => 'rapsodo:session:'.$workbook['worksheet'],
                'source_name' => $sessionName,
                'normalized_name' => 'rapsodo pitching session',
                'external_player_id' => null,
                'roles' => ['pitcher'],
                'row_count' => count($rows),
                'pitcher_row_count' => count($rows),
                'source_team_names' => [],
                'suggested_matches' => [],
                'suggested_player' => null,
                'mapping_status' => 'session_assignment_required',
                'external_name' => $sessionName,
                'external_identifier' => null,
                'data_types' => ['pitcher'],
                'identity_missing' => true,
                'remember_mapping' => false,
                'assignment_help' => 'No player name was found in the workbook. Connect this session to one selected-team roster player.',
            ]],
            'metrics_detected' => array_values($workbook['field_map']),
            'unknown_headers' => $workbook['unknown_headers'],
            'warnings' => array_values(array_unique($warnings)),
            'sample_rows' => array_slice(array_map(fn (array $row): array => [
                'player_external_name' => $sessionName,
                'occurred_at' => $row['event_time_display'],
                'metrics' => array_filter([
                    'pitch_number' => $this->number($row['pitch_number'] ?? null),
                    'pitch_velocity_mph' => $this->number($row['pitch_velocity_mph'] ?? null),
                    'total_spin_rate_rpm' => $this->number($row['total_spin_rate_rpm'] ?? null),
                    'true_spin_rate_rpm' => $this->number($row['true_spin_rate_rpm'] ?? null),
                    'spin_efficiency_percent' => $this->number($row['spin_efficiency_percent'] ?? null),
                    'horizontal_break_in' => $this->number($row['horizontal_break_in'] ?? null),
                    'vertical_break_in' => $this->number($row['vertical_break_in'] ?? null),
                ], fn ($value): bool => null !== $value),
                'controlled_values' => [
                    'pitch_type' => ['raw' => $row['pitch_type'] ?? null, 'canonical_preview' => $row['pitch_type_canonical']],
                    'strike' => ['raw' => $row['strike'] ?? null, 'canonical_preview' => $row['strike_boolean']],
                    'spin_direction' => ['raw' => $row['spin_direction_clock'] ?? null, 'canonical_preview' => null],
                    'time' => ['raw' => $row['event_time'] ?? null, 'canonical_preview' => $row['event_time_display']],
                ],
                'raw_source_values' => $row['_raw'],
                'validation' => ['valid' => [] === $row['_warnings'], 'warnings' => $row['_warnings']],
                'source' => ['platform' => 'rapsodo', 'worksheet' => $row['_worksheet'], 'source_row' => $row['_source_row']],
            ], $rows), 0, 10),
            'source_columns' => $columns,
            'template_fingerprint' => $this->fingerprints->fingerprint(array_column($columns, 'source_column_name')),
            'destination_recommendation' => [
                'detected_data_type' => 'pitching',
                'recommended' => ['Bullpen'],
                'compatible' => ['Pitching Practice'],
                'warning' => ['Assessment'],
                'incompatible' => ['Batting Practice', 'Cage', 'Live AB'],
                'selected' => null,
                'advisory_only' => true,
            ],
            'workbook' => [
                'worksheet_names' => $workbook['worksheet_names'],
                'selected_worksheet' => $workbook['worksheet'],
                'header_row' => $workbook['header_row'],
                'has_player_identity' => false,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sourceColumns(array $headers, array $rows): array
    {
        return array_map(function (string $header) use ($rows): array {
            $values = array_values(array_filter(
                array_map(fn (array $row): string => trim((string) ($row[$header] ?? '')), $rows),
                fn (string $value): bool => '' !== $value
            ));
            $numeric = array_values(array_filter($values, 'is_numeric'));
            $isNumeric = count($values) > 0 && count($numeric) === count($values);
            $normalized = TemplateFingerprintService::normalize($header);
            $transformations = match ($normalized) {
                'strike' => ['Y → true', 'N → false'],
                'pitchtype' => ['FB → Fastball', '2FB → Two-Seam Fastball', 'CV → Curveball', 'SL → Slider', 'KN → Knuckleball'],
                'time' => ['Excel time serial → displayed event time'],
                default => [],
            };
            $warnings = [];
            if (isset(self::UNITS[$normalized])) {
                $warnings[] = 'Source unit is suggested from the Rapsodo field convention and requires confirmation.';
            }
            if ('rangle' === $normalized && in_array('-', $values, true)) {
                $warnings[] = 'At least one release-angle value is unavailable (-).';
            }

            return [
                'source_column_name' => $header,
                'normalized_source_column' => $normalized,
                'sample_values' => array_slice(array_values(array_unique($values)), 0, 5),
                'suggested_source_unit_key' => self::UNITS[$normalized] ?? null,
                'controlled_value_transformations' => $transformations,
                'warnings' => $warnings,
                'details' => [
                    'inferred_data_type' => $isNumeric ? 'numeric' : 'text',
                    'minimum' => $isNumeric ? min(array_map('floatval', $numeric)) : null,
                    'maximum' => $isNumeric ? max(array_map('floatval', $numeric)) : null,
                    'average' => $isNumeric ? array_sum(array_map('floatval', $numeric)) / count($numeric) : null,
                    'unique_value_count' => count(array_unique($values)),
                    'populated_count' => count($values),
                ],
            ];
        }, $headers);
    }

    /** @return array<int, string> */
    private function warnings(array $row): array
    {
        $warnings = [];
        $velocity = $this->number($row['pitch_velocity_mph'] ?? null);
        if (null === $velocity || $velocity <= 0 || $velocity > 110) {
            $warnings[] = 'Pitch velocity is missing or implausible.';
        }
        foreach (['total_spin_rate_rpm', 'true_spin_rate_rpm'] as $spin) {
            if (null === $this->number($row[$spin] ?? null) || (float) $row[$spin] < 0) {
                $warnings[] = "{$spin} is missing or invalid.";
            }
        }
        $efficiency = $this->number($row['spin_efficiency_percent'] ?? null);
        if (null === $efficiency || $efficiency < 0 || $efficiency > 100) {
            $warnings[] = 'Spin efficiency is outside 0–100.';
        }
        if (1 !== preg_match('/^(?:0\\d|1[0-2])h:[0-5]\\dm$/', trim((string) ($row['spin_direction_clock'] ?? '')))) {
            $warnings[] = 'Spin direction is not a valid clock-face value.';
        }
        if (null === $row['strike_boolean']) {
            $warnings[] = 'Strike value is not a supported boolean form.';
        }
        if ('' === trim((string) ($row['pitch_type'] ?? ''))) {
            $warnings[] = 'Pitch type is missing.';
        }
        if ('-' === trim((string) ($row['release_angle_deg'] ?? ''))) {
            $warnings[] = 'Release angle is unavailable.';
        }

        return $warnings;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
