<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\BlastMotion;

use App\Services\DataHub\Dictionary\TemplateFingerprintService;

final class BlastMotionFieldMap
{
    private const FIELDS = [
        'date' => 'event_timestamp',
        'equipment' => 'bat_equipment',
        'handedness' => 'batter_side',
        'swingdetails' => 'swing_details',
        'planescore' => 'blast_plane_score',
        'connectionscore' => 'blast_connection_score',
        'rotationscore' => 'blast_rotation_score',
        'batspeedmph' => 'bat_speed_mph',
        'rotationalaccelerationg' => 'rotational_acceleration_g',
        'onplaneefficiency' => 'on_plane_efficiency_percent',
        'attackangledeg' => 'attack_angle_deg',
        'earlyconnectiondeg' => 'early_connection_deg',
        'connectionatimpactdeg' => 'connection_at_impact_deg',
        'verticalbatangledeg' => 'vertical_bat_angle_deg',
        'powerkw' => 'blast_swing_power_kw',
        'timetocontactsec' => 'time_to_contact_seconds',
        'peakhandspeedmph' => 'peak_hand_speed_mph',
        'exitvelocitymph' => 'exit_velocity_mph',
        'launchangledeg' => 'launch_angle_deg',
        'estimateddistancefeet' => 'projected_distance_ft',
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
        return [
            'planescore', 'connectionscore', 'rotationscore', 'batspeedmph',
            'rotationalaccelerationg', 'onplaneefficiency', 'earlyconnectiondeg',
            'connectionatimpactdeg', 'verticalbatangledeg', 'timetocontactsec',
            'peakhandspeedmph',
        ];
    }
}
