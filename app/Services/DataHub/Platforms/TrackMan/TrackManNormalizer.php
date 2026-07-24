<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\TrackMan;

use App\Services\DataHub\Contracts\ImportNormalizerContract;
use App\Services\DataHub\DTOs\NormalizedImportResult;
use Carbon\Carbon;
use Throwable;

final class TrackManNormalizer implements ImportNormalizerContract
{
    public function __construct(private readonly TrackManRowValidator $validator)
    {
    }

    public function normalize(iterable $records, array $playerMappings = [], string $sessionType = 'cage'): NormalizedImportResult
    {
        $normalized = [];
        foreach ($records as $row) {
            $type = 'pitching' === ($row['_data_type'] ?? null) ? 'pitching' : 'hitting';
            $name = trim((string) ($row['batter'] ?? $row['pitcher'] ?? ''));
            $metrics = [];
            $metricNames = 'pitching' === $type
                ? ['pitch_velocity_mph', 'pitch_spin_rate_rpm', 'spin_axis_deg', 'induced_vertical_break_in', 'horizontal_break_in', 'extension_ft', 'vertical_release_angle_deg', 'horizontal_release_angle_deg', 'plate_location_height_ft', 'plate_location_side_ft', 'zone_speed_mph', 'effective_velocity_mph', 'release_height_ft', 'release_side_ft']
                : ['exit_velocity_mph', 'launch_angle_deg', 'spray_angle_deg', 'distance_ft', 'last_tracked_distance_ft', 'hit_spin_rate_rpm', 'hit_spin_axis_deg', 'hang_time_seconds', 'maximum_height_ft', 'contact_position_x', 'contact_position_y', 'contact_position_z'];
            foreach ($metricNames as $metric) {
                if (isset($row[$metric]) && '' !== trim((string) $row[$metric]) && is_numeric($row[$metric])) {
                    $metrics[$metric] = (float) $row[$metric];
                }
            }
            $warnings = $this->validator->warnings($row, $type);
            $normalized[] = [
                'external_event_id' => $row['event_id'] ?? null,
                'player_external_name' => $name,
                'player_id' => $playerMappings[$name] ?? null,
                'occurred_at' => $this->occurredAt($row['date'] ?? null, $row['time'] ?? null),
                'session_type' => $sessionType,
                'data_type' => $type,
                'metrics' => $metrics,
                'source' => ['platform' => 'trackman'],
                'validation' => ['valid' => [] === $warnings, 'warnings' => $warnings],
            ];
        }

        return new NormalizedImportResult($normalized);
    }

    private function occurredAt(mixed $date, mixed $time): ?string
    {
        if ( ! $date) {
            return null;
        }
        try {
            return Carbon::parse(trim((string) $date).' '.trim((string) $time))->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
