<?php

declare(strict_types=1);

namespace App\Services\DataHub\Templates;

use InvalidArgumentException;

final class FmtrxTemplateCatalog
{
    public const VERSION = '1.0';

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $identity = [
            $this->field('fmtrx_player_id', 'FMTRX Player ID', 'Identity', null, true),
            $this->field('player_name', 'Player Name', 'Identity', null, true),
            $this->field('team_id', 'Team ID', 'Identity', null, true),
            $this->field('record_date', 'Record Date', 'Identity', 'YYYY-MM-DD', true),
        ];
        $notes = $this->field('notes', 'Notes', 'Notes');

        return [
            'assessment' => $this->template('Assessment', 1, array_merge($identity, [
                $this->field('pitch_velo_mph', 'Pitch Velocity', 'Baseball Metrics', 'mph'),
                $this->field('position_velo', 'Position Velocity', 'Baseball Metrics', 'mph'),
                $this->field('exit_velocity_mph', 'Exit Velocity', 'Baseball Metrics', 'mph'),
                $this->field('yd_60_dash_sec', '60 Yard Dash', 'Baseball Metrics', 'seconds'),
                $this->field('sprint_10yd_sec', '10 Yard Sprint', 'Baseball Metrics', 'seconds'),
                $this->field('home_to_first', 'Home to First', 'Baseball Metrics', 'seconds'),
                $this->field('catcher_pop_time', 'Catcher Pop Time', 'Baseball Metrics', 'seconds'),
                $this->field('body_weight_lbs', 'Body Weight', 'Strength Testing', 'lbs'),
                $this->field('front_squat_lbs', 'Front Squat', 'Strength Testing', 'lbs'),
                $this->field('front_squat_repetitions', 'Front Squat Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('back_squat_lbs', 'Back Squat', 'Strength Testing', 'lbs'),
                $this->field('back_squat_repetitions', 'Back Squat Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('bench_press_lbs', 'Bench Press', 'Strength Testing', 'lbs'),
                $this->field('bench_press_repetitions', 'Bench Press Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('dead_lift_lbs', 'Deadlift', 'Strength Testing', 'lbs'),
                $this->field('deadlift_repetitions', 'Deadlift Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('trap_bar_deadlift_lbs', 'Trap-bar Deadlift', 'Strength Testing', 'lbs'),
                $this->field('trap_bar_deadlift_repetitions', 'Trap-bar Deadlift Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('pull_ups', 'Pull-ups', 'Strength Testing', 'repetitions'),
                $this->field('push_ups', 'Push-ups', 'Strength Testing', 'repetitions'),
                $this->field('power_clean_lbs', 'Power Clean', 'Strength Testing', 'lbs'),
                $this->field('power_clean_repetitions', 'Power Clean Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('vertical_jump_inches', 'Vertical Jump', 'Strength Testing', 'inches'),
                $this->field('broad_jump_inches', 'Broad Jump', 'Strength Testing', 'inches'),
                $this->field('med_ball_rotational_throw_ft', 'Medicine-ball Rotational Throw', 'Strength Testing', 'feet'),
                $this->field('plank_hold_sec', 'Plank Hold', 'Strength Testing', 'seconds'),
                $this->score('shoulder_mobility', 'Shoulder Mobility', 'Mobility Testing', 0, 5),
                $this->score('hip_mobility', 'Hip Mobility', 'Mobility Testing', 0, 5),
                $this->score('ankle_mobility', 'Ankle Mobility', 'Mobility Testing', 0, 5),
                $this->score('hamstring_mobility', 'Hamstring Mobility', 'Mobility Testing', 0, 5),
                $this->score('t_spine_rotation', 'T-Spine Rotation', 'Mobility Testing', 0, 5),
                $this->field('primary_throwing_role', 'Primary Throwing Role', 'Throwing / Arm Health'),
                $this->field('throwing_days_per_week', 'Throw Days per Week', 'Throwing / Arm Health', 'days'),
                $this->field('bullpens_per_week', 'Bullpens per Week', 'Throwing / Arm Health', 'sessions'),
                $this->field('long_toss_sessions_per_week', 'Long Toss per Week', 'Throwing / Arm Health', 'sessions'),
                $this->field('weighted_ball_sessions_per_week', 'Weighted Ball per Week', 'Throwing / Arm Health', 'sessions'),
                $this->score('arm_fatigue', 'Arm Fatigue', 'Throwing / Arm Health', 0, 10),
                $this->score('arm_soreness', 'Arm Soreness', 'Throwing / Arm Health', 0, 10),
                $this->field('arm_pain', 'Arm Pain', 'Throwing / Arm Health', null, false, ['Yes', 'No']),
                $this->field('max_exit_velo', 'Maximum Exit Velocity', 'Hitting Assessment', 'mph'),
                $this->field('avg_exit_velo', 'Average Exit Velocity', 'Hitting Assessment', 'mph'),
                $this->field('contact_percentage', 'Contact Percentage', 'Hitting Assessment', 'percent'),
                $this->field('hard_hit_percentage', 'Hard-hit Percentage', 'Hitting Assessment', 'percent'),
                $this->field('fastball_velocity', 'Fastball Velocity', 'Pitching Assessment', 'mph'),
                $this->field('strike_percentage', 'Strike Percentage', 'Pitching Assessment', 'percent'),
                $this->field('command_percentage', 'Command Percentage', 'Pitching Assessment', 'percent'),
                $this->field('sleep_hours', 'Sleep Hours', 'Recovery', 'hours'),
                $this->score('sleep_quality_1_to_5', 'Sleep Quality', 'Recovery', 1, 5),
                $this->score('recovery_score', 'Recovery Score', 'Recovery', 0, 100),
                $notes,
            ])),
            'strength' => $this->template('Strength', 2, array_merge($identity, [
                $this->field('body_weight_lbs', 'Body Weight', 'Strength Testing', 'lbs'),
                $this->field('front_squat_lbs', 'Front Squat', 'Strength Testing', 'lbs'),
                $this->field('front_squat_repetitions', 'Front Squat Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('back_squat_lbs', 'Back Squat', 'Strength Testing', 'lbs'),
                $this->field('back_squat_repetitions', 'Back Squat Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('bench_press_lbs', 'Bench Press', 'Strength Testing', 'lbs'),
                $this->field('bench_press_repetitions', 'Bench Press Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('dead_lift_lbs', 'Deadlift', 'Strength Testing', 'lbs'),
                $this->field('deadlift_repetitions', 'Deadlift Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('trap_bar_deadlift_lbs', 'Trap-bar Deadlift', 'Strength Testing', 'lbs'),
                $this->field('trap_bar_deadlift_repetitions', 'Trap-bar Deadlift Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('power_clean_lbs', 'Power Clean', 'Strength Testing', 'lbs'),
                $this->field('power_clean_repetitions', 'Power Clean Repetitions', 'Strength Testing', 'repetitions'),
                $this->field('pull_ups', 'Pull-ups', 'Strength Testing', 'repetitions'),
                $this->field('push_ups', 'Push-ups', 'Strength Testing', 'repetitions'),
                $this->field('grip_strength_left', 'Grip Strength Left', 'Strength Testing', 'lbs'),
                $this->field('grip_strength_right', 'Grip Strength Right', 'Strength Testing', 'lbs'),
                $this->field('vertical_jump_inches', 'Vertical Jump', 'Power Testing', 'inches'),
                $this->field('broad_jump_inches', 'Broad Jump', 'Power Testing', 'inches'),
                $this->field('med_ball_rotational_throw_ft', 'Medicine-ball Rotational Throw', 'Power Testing', 'feet'),
                $this->field('med_ball_weight_lbs', 'Medicine-ball Weight', 'Power Testing', 'lbs'),
                $this->field('med_ball_protocol', 'Medicine-ball Protocol', 'Power Testing'),
                $this->field('plank_hold_sec', 'Plank Hold', 'Strength Endurance', 'seconds'),
                $this->field('plank_protocol', 'Plank Protocol', 'Strength Endurance'),
                $this->field('push_up_protocol', 'Push-up Protocol', 'Strength Endurance'),
                $this->field('grip_device', 'Grip Device', 'Grip Testing'),
                $this->field('grip_protocol', 'Grip Protocol', 'Grip Testing'),
                $this->field('sprint_10yd_sec', '10 Yard Sprint', 'Speed Testing', 'seconds'),
                $this->field('yd_40_dash_sec', '40 Yard Dash', 'Speed Testing', 'seconds'),
                $this->field('yd_60_dash_sec', '60 Yard Dash', 'Speed Testing', 'seconds'),
                $notes,
            ])),
            'mobility' => $this->template('Mobility', 3, array_merge($identity, [
                $this->score('shoulder_mobility', 'Shoulder Mobility', 'Mobility Testing', 0, 5),
                $this->score('hip_mobility', 'Hip Mobility', 'Mobility Testing', 0, 5),
                $this->score('ankle_mobility', 'Ankle Mobility', 'Mobility Testing', 0, 5),
                $this->score('hamstring_mobility', 'Hamstring Mobility', 'Mobility Testing', 0, 5),
                $this->score('t_spine_rotation', 'T-Spine Rotation', 'Mobility Testing', 0, 5),
                $this->score('overhead_squat', 'Overhead Squat', 'Mobility Testing', 0, 5),
                $this->score('single_leg_balance', 'Single-leg Balance', 'Mobility Testing', 0, 5),
                $notes,
            ])),
            'recovery' => $this->template('Recovery', 4, array_merge($identity, [
                $this->field('sleep_hours', 'Sleep Hours', 'Recovery', 'hours'),
                $this->score('sleep_quality_1_to_5', 'Sleep Quality', 'Recovery', 1, 5),
                $this->score('recovery_score', 'Recovery Score', 'Recovery', 0, 100),
                $this->score('arm_fatigue', 'Arm Fatigue', 'Readiness', 0, 10),
                $this->score('arm_soreness', 'Arm Soreness', 'Readiness', 0, 10),
                $this->field('arm_pain', 'Arm Pain', 'Readiness', null, false, ['Yes', 'No']),
                $notes,
            ])),
            'exit_velocity' => $this->ballByBall('Exit Velocity', 5, ['swing_number', 'exit_velocity_mph', 'launch_angle', 'spray_angle', 'distance', 'hit_type']),
            'long_toss' => $this->ballByBall('Long Toss', 6, ['throw_number', 'distance', 'velocity', 'hop_count']),
            'weighted_balls' => $this->ballByBall('Weighted Balls', 7, ['throw_number', 'ball_weight', 'throw_type', 'velocity']),
            'bullpen' => $this->ballByBall('Bullpen', 8, ['pitch_number', 'pitch_type', 'velocity', 'intended_location', 'actual_location', 'strike', 'result']),
            'batting_practice' => $this->ballByBall('Batting Practice', 9, ['swing_number', 'exit_velocity_mph', 'launch_angle', 'spray_angle', 'distance', 'hit_type']),
            'live_ab' => $this->ballByBall('Live AB', 10, ['event_number', 'pitch_type', 'pitch_velocity', 'exit_velocity_mph', 'launch_angle', 'play_result', 'count', 'outs']),
        ];
    }

    /** @return array<string, mixed> */
    public function get(string $key): array
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException('Unknown FMTRX template type.');
    }

    private function template(string $label, int $priority, array $fields): array
    {
        return ['key' => str_replace(' ', '_', mb_strtolower($label)), 'label' => $label, 'version' => self::VERSION, 'priority' => $priority, 'fields' => $fields];
    }

    private function ballByBall(string $label, int $priority, array $keys): array
    {
        $identity = [
            $this->field('fmtrx_player_id', 'FMTRX Player ID', 'Identity', null, true),
            $this->field('player_name', 'Player Name', 'Identity', null, true),
            $this->field('team_id', 'Team ID', 'Identity', null, true),
            $this->field('record_date', 'Session Date', 'Identity', 'YYYY-MM-DD', true),
        ];
        $labels = ['exit_velocity_mph' => 'Exit Velocity', 'launch_angle' => 'Launch Angle', 'spray_angle' => 'Spray Angle'];
        $events = array_map(fn (string $key): array => $this->field($key, $labels[$key] ?? ucwords(str_replace('_', ' ', $key)), 'Ball by Ball'), $keys);

        return $this->template($label, $priority, array_merge($identity, $events, [$this->field('notes', 'Notes', 'Notes')]));
    }

    private function score(string $key, string $label, string $section, int $min, int $max): array
    {
        return $this->field($key, $label, $section, "score {$min}-{$max}", false, null, $min, $max);
    }

    private function field(string $key, string $label, string $section, ?string $unit = null, bool $required = false, ?array $values = null, ?int $min = null, ?int $max = null): array
    {
        return compact('key', 'label', 'section', 'unit', 'required', 'values', 'min', 'max');
    }
}
