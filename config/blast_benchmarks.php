<?php

declare(strict_types=1);

$metrics = static fn (array $values): array => [
    'bat_speed' => ['min' => $values[0], 'max' => $values[1], 'unit' => 'mph', 'mode' => 'higher_is_better'],
    'attack_angle' => ['min' => $values[2], 'max' => $values[3], 'unit' => 'deg', 'mode' => 'target_range'],
    'vertical_bat_angle' => ['min' => -40.0, 'max' => -10.0, 'unit' => 'deg', 'mode' => 'target_range'],
    'time_to_contact' => ['min' => $values[4], 'max' => $values[5], 'unit' => 'sec', 'mode' => 'lower_is_better'],
    'peak_hand_speed' => ['min' => $values[6], 'max' => $values[7], 'unit' => 'mph', 'mode' => 'higher_is_better'],
    'power' => ['min' => $values[8], 'max' => $values[9], 'unit' => 'kW', 'mode' => 'higher_is_better'],
];

return [
    'version' => '1.0.0',
    'source' => 'Blast Motion Suggested Ranges supplied 2026-08-06',
    'levels' => [
        'pro' => ['label' => 'Pro', 'metrics' => $metrics([66.0, 78.0, 5.0, 15.0, 0.13, 0.17, 23.0, 29.0, 3.65, 5.65])],
        'milb' => ['label' => 'MiLB', 'metrics' => $metrics([63.0, 75.0, 3.0, 15.0, 0.13, 0.17, 22.0, 28.0, 3.20, 5.20])],
        'college' => ['label' => 'College', 'metrics' => $metrics([66.0, 75.0, 2.0, 15.0, 0.14, 0.16, 21.0, 24.0, 3.83, 5.08])],
        'high_school_varsity' => ['label' => 'High School Varsity', 'metrics' => $metrics([60.0, 70.0, 2.0, 13.0, 0.15, 0.18, 19.0, 22.0, 2.81, 4.09])],
        'high_school_jv' => ['label' => 'High School JV', 'metrics' => $metrics([53.0, 67.0, 0.0, 15.0, 0.15, 0.20, 19.0, 25.0, 1.75, 3.75])],
        'middle_school' => ['label' => 'Middle School', 'metrics' => $metrics([46.0, 62.0, 0.0, 15.0, 0.16, 0.21, 18.0, 24.0, 1.40, 3.20])],
        'youth' => ['label' => 'Youth', 'metrics' => $metrics([40.0, 56.0, 0.0, 15.0, 0.17, 0.23, 17.0, 23.0, 0.90, 2.50])],
    ],
    'scouting_scale' => [
        ['min' => 80.0, 'max' => null, 'key' => 'elite', 'label' => 'Elite'],
        ['min' => 70.0, 'max' => 79.999, 'key' => 'well_above_average', 'label' => 'Well above average'],
        ['min' => 60.0, 'max' => 69.999, 'key' => 'above_average', 'label' => 'Above average'],
        ['min' => 50.0, 'max' => 59.999, 'key' => 'average', 'label' => 'Average'],
        ['min' => 40.0, 'max' => 49.999, 'key' => 'below_average', 'label' => 'Below average'],
        ['min' => 30.0, 'max' => 39.999, 'key' => 'well_below_average', 'label' => 'Well below average'],
        ['min' => 20.0, 'max' => 29.999, 'key' => 'poor', 'label' => 'Poor'],
    ],
    'mlb_references' => [
        'on_plane_efficiency' => ['value' => 66.6, 'unit' => '%', 'label' => 'Blast MLB average'],
        'rotational_acceleration' => ['value' => 15.1, 'unit' => 'g', 'label' => 'Blast MLB average'],
        'bat_speed' => ['value' => 71.6, 'unit' => 'mph', 'label' => 'Blast MLB average'],
        'attack_angle' => ['value' => 8.7, 'unit' => 'deg', 'label' => 'Blast MLB average'],
        'early_connection' => ['value' => 95.3, 'unit' => 'deg', 'label' => 'Blast MLB average'],
        'connection_at_impact' => ['value' => 81.6, 'unit' => 'deg', 'label' => 'Blast MLB average'],
        'time_to_contact' => ['value' => 0.149, 'unit' => 'sec', 'label' => 'Blast MLB average'],
        'peak_hand_speed' => ['value' => 22.3, 'unit' => 'mph', 'label' => 'Blast MLB average'],
        'power' => ['value' => 4.5, 'unit' => 'kW', 'label' => 'Blast MLB average'],
    ],
];
