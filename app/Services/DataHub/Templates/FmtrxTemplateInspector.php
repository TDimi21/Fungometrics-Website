<?php

declare(strict_types=1);

namespace App\Services\DataHub\Templates;

use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Services\PlayerMatchingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class FmtrxTemplateInspector
{
    public function __construct(
        private FmtrxTemplateCatalog $catalog,
        private PlayerMatchingService $matching,
    ) {
    }

    /** @return array<string, mixed> */
    public function inspect(ImportFileMetadata $file, string $teamId): array
    {
        if ( ! $file->path || ! is_readable($file->path)) {
            throw new RuntimeException('The FMTRX template file is not readable.');
        }
        $handle = fopen($file->path, 'r');
        $metadata = fgetcsv($handle) ?: [];
        $metadata[0] = ltrim((string) ($metadata[0] ?? ''), "\xEF\xBB\xBF");
        if ('FMTRX_TEMPLATE' !== ($metadata[0] ?? null)) {
            fclose($handle);
            throw new RuntimeException('This Generic CSV is not a versioned FMTRX template.');
        }
        $templateKey = (string) ($metadata[1] ?? '');
        $version = (string) ($metadata[3] ?? '');
        $template = $this->catalog->get($templateKey);
        if ($version !== $template['version']) {
            fclose($handle);
            throw new RuntimeException("FMTRX template version {$version} is not supported. Download the current template.");
        }
        $canonicalKeys = fgetcsv($handle) ?: [];
        $labels = fgetcsv($handle) ?: [];
        $expected = array_column($template['fields'], 'key');
        if ($canonicalKeys !== $expected) {
            fclose($handle);
            throw new RuntimeException('The FMTRX template columns were changed or are incomplete. Download a fresh template.');
        }
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (0 === count(array_filter($values, fn ($value): bool => '' !== trim((string) $value)))) {
                continue;
            }
            $rows[] = array_combine($canonicalKeys, array_pad(array_slice($values, 0, count($canonicalKeys)), count($canonicalKeys), ''));
        }
        fclose($handle);

        $roster = collect($this->matching->roster($teamId))->keyBy('id');
        $players = [];
        $invalidRows = 0;
        $warnings = [];
        foreach ($rows as $index => $row) {
            $playerId = trim((string) ($row['fmtrx_player_id'] ?? ''));
            $name = trim((string) ($row['player_name'] ?? ''));
            $validPlayer = '' !== $playerId && $roster->has($playerId);
            $rowWarnings = [];
            if ( ! $validPlayer) {
                $rowWarnings[] = '' === $playerId ? 'Missing FMTRX Player ID; review player mapping.' : 'FMTRX Player ID is not active on the selected team.';
            }
            if ('' === trim((string) ($row['record_date'] ?? ''))) {
                $rowWarnings[] = 'Record Date is required.';
            }
            foreach ($template['fields'] as $field) {
                $value = trim((string) ($row[$field['key']] ?? ''));
                if ('' === $value) {
                    continue;
                }
                if ((null !== $field['min'] || null !== $field['max']) && ! is_numeric($value)) {
                    $rowWarnings[] = "{$field['label']} must be numeric.";
                } elseif (is_numeric($value) && ((null !== $field['min'] && (float) $value < $field['min']) || (null !== $field['max'] && (float) $value > $field['max']))) {
                    $rowWarnings[] = "{$field['label']} is outside its valid range.";
                }
                if ($field['values'] && ! in_array($value, $field['values'], true)) {
                    $rowWarnings[] = "{$field['label']} is not an allowed value.";
                }
            }
            if ($rowWarnings) {
                $invalidRows++;
                $warnings[] = 'Row '.($index + 4).': '.implode(' ', $rowWarnings);
            }
            $sourceKey = $playerId ?: 'name:'.mb_strtolower($name);
            if ( ! isset($players[$sourceKey])) {
                $players[$sourceKey] = [
                    'source_key' => $sourceKey,
                    'source_name' => $name ?: 'Unnamed Player',
                    'external_player_id' => $playerId ?: null,
                    'roles' => ['player'],
                    'row_count' => 0,
                    'suggested_matches' => $validPlayer ? [[
                        'player_id' => $playerId,
                        'display_name' => $roster[$playerId]['name'],
                        'match_type' => 'fmtrx_player_id',
                        'resolution_source' => 'fmtrx_player_id',
                        'confidence' => 100,
                        'auto_select' => true,
                    ]] : [],
                ];
            }
            $players[$sourceKey]['row_count']++;
            $rows[$index]['_validation'] = ['valid' => ! $rowWarnings, 'warnings' => $rowWarnings];
        }

        $conceptMap = $this->conceptIds();
        $sourceColumns = array_map(function (array $field, int $index) use ($rows, $labels, $conceptMap): array {
            $samples = array_values(array_unique(array_filter(array_map(fn (array $row): string => trim((string) ($row[$field['key']] ?? '')), $rows))));

            return [
                'source_column_name' => $field['key'],
                'display_label' => $labels[$index] ?? $field['label'],
                'normalized_source_column' => $field['key'],
                'sample_values' => array_slice($samples, 0, 5),
                'canonical_concept_id' => $conceptMap[$field['key']] ?? null,
                'source_unit_key' => $this->unitKey($field['unit']),
                'details' => ['inferred_data_type' => null !== $field['min'] || null !== $field['max'] ? 'numeric' : 'text'],
            ];
        }, $template['fields'], array_keys($template['fields']));

        return [
            'platform' => 'fmtrx-template',
            'template' => ['type' => $templateKey, 'version' => $version, 'label' => $template['label']],
            'file' => ['name' => $file->name, 'size_bytes' => $file->sizeBytes, 'extension' => $file->extension],
            'detected_format' => ['provider' => 'FMTRX', 'data_type' => $templateKey, 'confidence' => 100, 'header_version' => $version],
            'session' => ['dates' => [], 'primary_date' => null, 'facility' => null, 'system' => 'FMTRX Template', 'detected_session_count' => count($rows) ? 1 : 0],
            'counts' => ['total_rows' => count($rows), 'usable_rows' => count($rows) - $invalidRows, 'invalid_rows' => $invalidRows, 'players_found' => count($players), 'sessions_found' => count($rows) ? 1 : 0],
            'players' => array_values($players),
            'metrics_detected' => array_values(array_filter(array_column($sourceColumns, 'canonical_concept_id'))),
            'warnings' => array_slice($warnings, 0, 50),
            'sample_rows' => array_slice(array_map(fn (array $row): array => ['player_external_name' => $row['player_name'] ?? null, 'source' => ['player_external_name' => $row['player_name'] ?? null], 'metrics' => $row, 'validation' => $row['_validation']], $rows), 0, 10),
            'source_columns' => $sourceColumns,
            'template_fingerprint' => hash('sha256', implode('|', $canonicalKeys)),
        ];
    }

    /** @return array<string, string> */
    private function conceptIds(): array
    {
        $keys = [
            'fmtrx_player_id' => 'session_context.player_identity', 'player_name' => 'session_context.player_identity',
            'record_date' => 'session_context.event_date', 'exit_velocity_mph' => 'hitting.exit_velocity',
            'max_exit_velo' => 'hitting.exit_velocity', 'avg_exit_velo' => 'hitting.exit_velocity',
            'launch_angle' => 'hitting.launch_angle', 'spray_angle' => 'hitting.spray_angle',
            'distance' => 'hitting.projected_distance', 'velocity' => 'pitching.release_velocity',
            'pitch_velocity' => 'pitching.release_velocity', 'fastball_velocity' => 'pitching.release_velocity',
            'bench_press_lbs' => 'strength.bench_press', 'front_squat_lbs' => 'strength.front_squat',
            'back_squat_lbs' => 'strength.back_squat', 'dead_lift_lbs' => 'strength.deadlift',
            'trap_bar_deadlift_lbs' => 'strength.trap_bar_deadlift',
            'power_clean_lbs' => 'strength.power_clean', 'vertical_jump_inches' => 'strength.vertical_jump',
            'broad_jump_inches' => 'strength.broad_jump', 'sprint_10yd_sec' => 'speed_agility.sprint_10yd',
            'grip_strength_left' => 'strength.grip_left', 'grip_strength_right' => 'strength.grip_right',
            'med_ball_rotational_throw_ft' => 'strength.med_ball_rotational_throw',
            'plank_hold_sec' => 'strength.plank_hold',
            'med_ball_weight_lbs' => 'strength.med_ball_weight', 'med_ball_protocol' => 'strength.med_ball_protocol',
            'plank_protocol' => 'strength.plank_protocol', 'push_up_protocol' => 'strength.push_up_protocol',
            'grip_device' => 'strength.grip_device', 'grip_protocol' => 'strength.grip_protocol',
            'yd_40_dash_sec' => 'speed_agility.sprint_40yd', 'yd_60_dash_sec' => 'speed_agility.sprint_60yd',
            'body_weight_lbs' => 'body_composition.body_weight', 'sleep_hours' => 'recovery.sleep_duration',
            'sleep_quality_1_to_5' => 'recovery.sleep_quality', 'hip_mobility' => 'mobility.hip',
            'shoulder_mobility' => 'mobility.shoulder', 'ankle_mobility' => 'mobility.ankle',
            'ball_weight' => 'throwing.ball_weight', 'hop_count' => 'throwing.long_toss_hops',
        ];
        $ids = DB::table('baseball_concepts')->whereIn('canonical_key', array_values($keys))->pluck('id', 'canonical_key');

        return array_filter(array_map(fn (string $key): ?string => $ids[$key] ?? null, $keys));
    }

    private function unitKey(?string $unit): ?string
    {
        return ['degrees' => 'deg', 'feet' => 'ft', 'inches' => 'in', 'seconds' => 'sec', 'lbs' => 'lbs', 'hours' => 'hours', 'mph' => 'mph'][$unit] ?? null;
    }
}
