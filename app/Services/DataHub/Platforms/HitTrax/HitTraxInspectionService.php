<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\HitTrax;

use App\Models\PlatformDefinition;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\Services\PlayerMatchingService;

final class HitTraxInspectionService
{
    private const OPTIONAL_ZERO_FIELDS = [
        'Hand Speed', 'BV', 'Trigger to Impact', 'AA', 'Impact Momentum',
    ];

    public function __construct(
        private readonly HitTraxParser $parser,
        private readonly PlayerMatchingService $matching,
        private readonly TemplateFingerprintService $fingerprints,
    ) {
    }

    /** @return array<string, mixed> */
    public function inspect(ImportFileMetadata $file, string $teamId): array
    {
        $rows = iterator_to_array($this->parser->parse($file), false);
        $columns = $this->parser->inspectSourceColumns($file);
        $tracked = count(array_filter(
            $rows,
            fn (array $row): bool =>
            is_numeric($row['exit_velocity_mph'] ?? null) && (float) $row['exit_velocity_mph'] > 0
        ));
        $players = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['user'] ?? ''));
            if ('' === $name) {
                continue;
            }
            $key = 'hittrax:name:'.hash('sha256', $this->matching->normalize($name));
            $players[$key] ??= [
                'source_key' => $key, 'source_name' => $name, 'normalized_name' => $this->matching->normalize($name),
                'external_player_id' => null, 'roles' => ['batter'], 'row_count' => 0,
                'batter_row_count' => 0, 'tracked_batted_ball_count' => 0, 'source_team_names' => [],
            ];
            ++$players[$key]['row_count'];
            ++$players[$key]['batter_row_count'];
            if (is_numeric($row['exit_velocity_mph'] ?? null) && (float) $row['exit_velocity_mph'] > 0) {
                ++$players[$key]['tracked_batted_ball_count'];
            }
        }
        $platformId = (string) PlatformDefinition::query()->where('key', 'hittrax')->value('id');
        foreach ($players as &$player) {
            $suggestions = $this->matching->suggestions($teamId, $player['source_name'], null, $platformId);
            $player['suggested_matches'] = $suggestions;
            $player['suggested_player'] = $suggestions[0] ?? null;
            $player['mapping_status'] = ($suggestions[0]['auto_select'] ?? false) ? 'matched' : 'suggested';
            $player['external_name'] = $player['source_name'];
            $player['external_identifier'] = null;
            $player['data_types'] = ['batter'];
        }
        unset($player);
        $warnings = [];
        foreach ($columns as &$column) {
            if (in_array($column['source_column_name'], self::OPTIONAL_ZERO_FIELDS, true)
                && ($column['details']['all_zero'] ?? false)) {
                $column['default_not_importing'] = true;
                $column['warnings'][] = 'All populated values are zero; HitTrax likely did not measure this optional sensor field.';
                $warnings[] = "{$column['source_column_name']} contains only zeros and defaults to Not Importing.";
            }
            if ('Velo' === $column['source_column_name']) {
                $column['warnings'][] = 'Zero means no tracked batted-ball measurement and must be treated as missing.';
            }
        }
        unset($column);

        return [
            'platform' => 'hittrax',
            'file' => ['name' => $file->name, 'size_bytes' => $file->sizeBytes, 'extension' => $file->extension],
            'detected_format' => ['provider' => 'HitTrax', 'data_type' => 'hitting', 'confidence' => 100, 'header_version' => null],
            'session' => ['dates' => [], 'primary_date' => $rows[0]['event_timestamp'] ?? null, 'facility' => null, 'system' => 'HitTrax', 'detected_session_count' => count($rows) ? 1 : 0],
            'counts' => ['total_rows' => count($rows), 'usable_rows' => $tracked, 'invalid_rows' => count($rows) - $tracked, 'tracked_batted_balls' => $tracked, 'players_found' => count($players), 'sessions_found' => count($rows) ? 1 : 0],
            'players' => array_values($players),
            'metrics_detected' => array_values(array_filter(array_column($columns, 'source_column_name'))),
            'warnings' => array_values(array_unique($warnings)),
            'sample_rows' => array_slice(array_map(fn (array $row): array => [
                'player_external_name' => $row['user'] ?? null,
                'occurred_at' => $row['event_timestamp'] ?? null,
                'metrics' => array_filter([
                    'exit_velocity_mph' => $this->positive($row['exit_velocity_mph'] ?? null),
                    'launch_angle_deg' => $this->number($row['launch_angle_deg'] ?? null),
                    'spray_angle_deg' => $this->number($row['spray_angle_deg'] ?? null),
                    'projected_distance_ft' => $this->number($row['projected_distance_ft'] ?? null),
                ], fn ($value): bool => null !== $value),
                'source' => ['platform' => 'hittrax'],
            ], $rows), 0, 10),
            'source_columns' => $columns,
            'template_fingerprint' => $this->fingerprints->fingerprint(array_column($columns, 'source_column_name')),
            'destination_recommendation' => ['detected_data_type' => 'hitting', 'recommended' => ['Batting Practice', 'Cage'], 'selected' => null, 'advisory_only' => true],
        ];
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function positive(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }
}
