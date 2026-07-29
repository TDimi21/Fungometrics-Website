<?php

declare(strict_types=1);

return [
    'admin_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('SUBSCRIPTION_ADMIN_EMAILS', 'admin@fungometrics.com'))))),
    'administrative_entitlements' => ['data_hub_import'],
    /*
     * Temporary evaluation access is deliberately server-authoritative so the
     * web and mobile clients receive the same decision from /api/me/access.
     * An explicit expiration is required; an enabled flag without a valid
     * future timestamp grants nothing.
     */
    'temporary_full_access' => [
        'enabled' => (bool) env('FMTRX_TEMPORARY_FULL_ACCESS_ENABLED', false),
        'ends_at' => env('FMTRX_TEMPORARY_FULL_ACCESS_ENDS_AT'),
        'plans' => [
            'coach' => 'coach_pro',
            'player' => 'player_pro',
        ],
        'limits' => ['players' => null, 'coaches' => null, 'teams' => null],
    ],
    'plans' => [
        'free' => ['name' => 'Free', 'audience' => 'coach', 'entitlements' => [
            'create_session', 'record_pitches', 'view_session_history', 'roster_view',
            'invite_players', 'add_coaches', 'notifications', 'recent_sessions',
            'record_assessments',
        ], 'limits' => ['players' => 10, 'coaches' => 5, 'teams' => 1]],
        'coach_basic' => ['name' => 'Coach Basic', 'audience' => 'coach', 'entitlements' => [
            'create_session', 'record_pitches', 'view_session_history', 'roster_view',
            'invite_players', 'add_coaches', 'notifications', 'recent_sessions',
            'record_assessments',
        ], 'limits' => ['players' => 10, 'coaches' => 5, 'teams' => 1]],
        'coach_pro' => ['name' => 'Coach Pro', 'audience' => 'coach', 'entitlements' => [
            'create_session', 'record_pitches', 'view_session_history', 'roster_view',
            'invite_players', 'notifications', 'recent_sessions', 'liveab_sessions',
            'exit_velocity_sessions', 'long_toss_sessions', 'weighted_ball_sessions',
            'practice_sessions', 'view_team_stats', 'view_advanced_stats',
            'performance_overview', 'heat_maps', 'export_stats', 'ai_analytics',
            'view_session_report', 'arm_care', 'liveab_analytics', 'box_score',
            'team_recaps', 'player_recaps', 'add_coaches', 'team_switching',
            'edit_team', 'edit_player', 'add_team',
            'sms_results', 'view_player_cards',
            'scripted_bp', 'scripted_bullpen', 'planner_create', 'plan_builder',
            'assign_workouts', 'view_workout_progress', 'manage_player_groups',
            'record_assessments', 'view_assessment_reports',
            'view_assessment_comparisons', 'view_assessment_recommendations',
            'data_hub_import',
        ], 'limits' => ['players' => null, 'coaches' => null, 'teams' => null]],
        'player_basic' => ['name' => 'Player Basic', 'audience' => 'player', 'entitlements' => [
            'view_own_profile', 'view_own_sessions', 'personal_stats', 'arm_care',
            'notifications', 'recent_sessions',
        ], 'limits' => ['players' => null, 'coaches' => null, 'teams' => null]],
        'player_pro' => ['name' => 'Player Pro', 'audience' => 'player', 'entitlements' => [
            'view_own_profile', 'view_own_sessions', 'personal_stats', 'arm_care',
            'notifications', 'recent_sessions', 'liveab_sessions', 'exit_velocity_sessions',
            'long_toss_sessions', 'weighted_ball_sessions', 'view_own_stats',
            'view_advanced_stats', 'heat_maps', 'development_graphs',
            'ai_recommendations', 'view_session_report', 'export_stats', 'box_score',
            'player_recaps',
            'view_assessment_reports', 'view_assessment_comparisons',
            'view_assessment_recommendations',
        ], 'limits' => ['players' => null, 'coaches' => null, 'teams' => null]],
    ],
    'audience_baselines' => [
        'coach' => [
            'entitlements' => [
                'create_session', 'record_pitches', 'view_session_history',
                'record_assessments', 'roster_view', 'invite_players',
                'add_coaches', 'notifications', 'recent_sessions',
            ],
            'limits' => ['players' => 10, 'coaches' => 5, 'teams' => 1],
        ],
        'player' => [
            'entitlements' => ['view_own_profile', 'view_own_sessions', 'notifications', 'recent_sessions'],
            'limits' => ['players' => null, 'coaches' => null, 'teams' => null],
        ],
    ],
    'plan_priority' => ['free', 'coach_basic', 'player_basic', 'coach_pro', 'player_pro'],
];
