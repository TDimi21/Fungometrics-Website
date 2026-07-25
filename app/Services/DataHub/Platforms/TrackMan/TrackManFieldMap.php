<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\TrackMan;

final class TrackManFieldMap
{
    /** @var array<string, array<int, string>> */
    public const ALIASES = [
        'batter' => ['Batter', 'BatterName', 'Batter Name', 'Hitter'],
        'batter_id' => ['BatterId', 'BatterID', 'Batter Id', 'PlayerId'],
        'batter_team' => ['BatterTeam', 'Batter Team'],
        'batter_side' => ['BatterSide', 'Batter Side'],
        'pitcher' => ['Pitcher', 'PitcherName', 'Pitcher Name'],
        'pitcher_id' => ['PitcherId', 'PitcherID', 'Pitcher Id'],
        'pitcher_team' => ['PitcherTeam', 'Pitcher Team'],
        'pitcher_throws' => ['PitcherThrows', 'Pitcher Throws'],
        'date' => ['Date', 'GameDate', 'SessionDate'],
        'time' => ['Time', 'PitchTime', 'Timestamp'],
        'stadium' => ['Stadium', 'Venue', 'Facility'],
        'system' => ['System', 'TrackingSystem', 'RadarSystem'],
        'event_id' => ['PitchUID', 'PitchUid', 'PitchNo', 'PitchNumber', 'EventId'],
        'exit_velocity_mph' => ['ExitSpeed', 'ExitVelocity', 'Exit Velo', 'ExitSpeedMPH'],
        'launch_angle_deg' => ['Angle', 'LaunchAngle', 'Launch Angle'],
        'spray_angle_deg' => ['Direction', 'SprayAngle', 'Spray Angle'],
        'distance_ft' => ['Distance', 'CarryDistance', 'HitDistance'],
        'last_tracked_distance_ft' => ['LastTrackedDistance', 'Last Tracked Distance'],
        'hit_spin_rate_rpm' => ['HitSpinRate', 'Hit Spin Rate'],
        'hit_spin_axis_deg' => ['HitSpinAxis', 'Hit Spin Axis'],
        'hang_time_seconds' => ['HangTime', 'Hang Time'],
        'maximum_height_ft' => ['MaxHeight', 'MaximumHeight', 'Max Height'],
        'contact_position_x' => ['ContactPositionX'],
        'contact_position_y' => ['ContactPositionY'],
        'contact_position_z' => ['ContactPositionZ'],
        'tagged_hit_type' => ['TaggedHitType', 'Tagged Hit Type'],
        'automatic_hit_type' => ['AutoHitType', 'AutomaticHitType'],
        'launch_confidence' => ['HitLaunchConfidence'],
        'landing_confidence' => ['HitLandingConfidence'],
        'tagged_pitch_type' => ['TaggedPitchType', 'Tagged Pitch Type'],
        'automatic_pitch_type' => ['AutoPitchType', 'AutomaticPitchType'],
        'pitch_velocity_mph' => ['RelSpeed', 'ReleaseSpeed', 'PitchVelocity'],
        'pitch_spin_rate_rpm' => ['SpinRate', 'PitchSpinRate'],
        'spin_axis_deg' => ['SpinAxis'],
        'induced_vertical_break_in' => ['InducedVertBreak', 'IVB'],
        'horizontal_break_in' => ['HorzBreak', 'HorizontalBreak'],
        'extension_ft' => ['Extension', 'ReleaseExtension'],
        'vertical_release_angle_deg' => ['VertRelAngle'],
        'horizontal_release_angle_deg' => ['HorzRelAngle'],
        'plate_location_height_ft' => ['PlateLocHeight'],
        'plate_location_side_ft' => ['PlateLocSide'],
        'zone_speed_mph' => ['ZoneSpeed'],
        'effective_velocity_mph' => ['EffectiveVelo'],
        'release_height_ft' => ['ReleaseHeight'],
        'release_side_ft' => ['ReleaseSide'],
    ];

    /** @return array<string, string> canonical => actual */
    public function resolve(array $headers): array
    {
        $indexed = [];
        foreach ($headers as $header) {
            $indexed[$this->key((string) $header)] = (string) $header;
        }
        $resolved = [];
        foreach (self::ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($indexed[$this->key($alias)])) {
                    $resolved[$canonical] = $indexed[$this->key($alias)];
                    break;
                }
            }
        }

        return $resolved;
    }

    private function key(string $value): string
    {
        return mb_strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim($value)));
    }
}
