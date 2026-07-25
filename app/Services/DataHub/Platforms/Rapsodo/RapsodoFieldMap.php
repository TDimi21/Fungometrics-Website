<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\Rapsodo;

use App\Services\DataHub\Dictionary\TemplateFingerprintService;

final class RapsodoFieldMap
{
    private const FIELDS = [
        'no' => 'pitch_number',
        'time' => 'event_time',
        'pitchtype' => 'pitch_type',
        'velocity' => 'pitch_velocity_mph',
        'spinrate' => 'total_spin_rate_rpm',
        'truespin' => 'true_spin_rate_rpm',
        'spineff' => 'spin_efficiency_percent',
        'spindirection' => 'spin_direction_clock',
        'horzbreak' => 'horizontal_break_in',
        'vertbreak' => 'vertical_break_in',
        'strike' => 'strike',
        'relht' => 'release_height_ft',
        'relside' => 'release_side_ft',
        'rangle' => 'release_angle_deg',
        'hangle' => 'horizontal_release_angle_deg',
        'gyro' => 'gyro_degree',
    ];

    /** @return array<string, string> */
    public function resolve(array $headers): array
    {
        $resolved = [];
        foreach ($headers as $header) {
            $normalized = TemplateFingerprintService::normalize((string) $header);
            if (isset(self::FIELDS[$normalized])) {
                $resolved[self::FIELDS[$normalized]] = (string) $header;
            }
        }

        return $resolved;
    }

    /** @return array<int, string> */
    public function strongSignals(): array
    {
        return ['spinrate', 'truespin', 'spineff', 'spindirection', 'horzbreak', 'vertbreak', 'relht', 'relside', 'gyro'];
    }
}
