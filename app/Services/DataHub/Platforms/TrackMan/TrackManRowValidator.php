<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\TrackMan;

final class TrackManRowValidator
{
    /** @return array<int, string> */
    public function warnings(array $row, string $dataType): array
    {
        $fields = 'pitching' === $dataType
            ? ['pitch_velocity_mph', 'pitch_spin_rate_rpm', 'induced_vertical_break_in', 'horizontal_break_in']
            : ['exit_velocity_mph', 'launch_angle_deg', 'spray_angle_deg', 'distance_ft'];
        $warnings = [];
        foreach ($fields as $field) {
            if (isset($row[$field]) && '' !== trim((string) $row[$field]) && ! is_numeric($row[$field])) {
                $warnings[] = "{$field} is not numeric";
            }
        }

        return $warnings;
    }
}
