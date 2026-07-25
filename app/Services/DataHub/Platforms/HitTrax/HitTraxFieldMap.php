<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\HitTrax;

final class HitTraxFieldMap
{
    private const FIELDS = [
        '#' => 'event_number',
        'AB' => 'plate_appearance_number',
        'Date' => 'event_timestamp',
        'Time Stamp' => 'elapsed_time',
        'Pitch' => 'inbound_pitch_velocity_mph',
        'Strike Zone' => 'strike_zone_number',
        'P. Type' => 'inbound_pitch_type',
        'Velo' => 'exit_velocity_mph',
        'LA' => 'launch_angle_deg',
        'Dist' => 'projected_distance_ft',
        'Res' => 'simulated_play_result',
        'Type' => 'automatic_trajectory',
        'Horiz. Angle' => 'spray_angle_deg',
        'Pts' => 'hittrax_points',
        'Hand Speed' => 'hand_speed_mph',
        'BV' => 'bat_velocity_mph',
        'Trigger to Impact' => 'trigger_to_impact_seconds',
        'AA' => 'attack_angle_deg',
        'Impact Momentum' => 'impact_momentum',
        'Strike Zone Bottom' => 'strike_zone_bottom_in',
        'Strike Zone Top' => 'strike_zone_top_in',
        'Strike Zone Width' => 'strike_zone_width_in',
        'Vertical Distance' => 'location_vertical_distance_in',
        'Horizontal Distance' => 'location_horizontal_distance_in',
        'POI X' => 'point_of_impact_x',
        'POI Y' => 'point_of_impact_y',
        'POI Z' => 'point_of_impact_z',
        'Bat Material' => 'bat_material',
        'User' => 'user',
        'Pitch Angle' => 'inbound_pitch_angle_deg',
        'Batting' => 'batter_side',
        'Level' => 'competition_level',
        'Opposing Player' => 'opposing_player',
        'Tag' => 'event_note',
    ];

    /** @return array<string, string> */
    public function resolve(array $headers): array
    {
        $actual = [];
        foreach ($headers as $header) {
            $actual[trim((string) $header)] = (string) $header;
        }

        $resolved = [];
        foreach (self::FIELDS as $source => $canonical) {
            if (isset($actual[$source])) {
                $resolved[$canonical] = $actual[$source];
            }
        }

        return $resolved;
    }
}
