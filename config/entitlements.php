<?php

declare(strict_types=1);

$items = [
    // Core Sessions
    'create_session' => ['Create Basic Sessions', 'Create batting, bullpen, and cage sessions.', 'Core Sessions', 'coach'],
    'record_pitches' => ['Record Pitches', 'Record pitch and ball-by-ball data.', 'Core Sessions', 'coach'],
    'view_session_history' => ['Session History', 'View basic session history and ball-by-ball tables.', 'Core Sessions', 'coach'],
    'scripted_bp' => ['Scripted Batting Practice', 'Build and score structured batting-practice rounds.', 'Advanced Sessions', 'coach'],
    'scripted_bullpen' => ['Scripted Bullpen', 'Build and score structured bullpen sessions.', 'Advanced Sessions', 'coach'],
    'liveab_sessions' => ['Live AB Sessions', 'Run Live AB simulation sessions.', 'Advanced Sessions', 'shared'],
    'exit_velocity_sessions' => ['Exit Velocity Sessions', 'Use exit-velocity training sessions.', 'Advanced Sessions', 'shared'],
    'long_toss_sessions' => ['Long Toss Sessions', 'Use long-toss training sessions.', 'Advanced Sessions', 'shared'],
    'weighted_ball_sessions' => ['Weighted Ball Sessions', 'Use weighted-ball training sessions.', 'Advanced Sessions', 'shared'],
    'practice_sessions' => ['Practice Sessions', 'Use structured practice-plan sessions.', 'Advanced Sessions', 'coach'],

    // Statistics and Analytics
    'view_team_stats' => ['Team Statistics', 'View team leaders and statistics.', 'Statistics and Analytics', 'coach'],
    'view_advanced_stats' => ['Advanced Statistics', 'View advanced breakdowns, charts, and grids.', 'Statistics and Analytics', 'shared'],
    'view_own_stats' => ['Own Advanced Statistics', 'View advanced personal statistics.', 'Statistics and Analytics', 'player'],
    'personal_stats' => ['Personal Statistics', 'View personal statistics and metrics.', 'Statistics and Analytics', 'player'],
    'performance_overview' => ['Performance Overview', 'View the team FMTRX performance dashboard.', 'Statistics and Analytics', 'coach'],
    'heat_maps' => ['Heat Maps', 'View performance heat maps.', 'Statistics and Analytics', 'shared'],
    'export_stats' => ['Export Statistics', 'Export statistics for offline analysis.', 'Statistics and Analytics', 'shared'],
    'ai_analytics' => ['AI Analytics', 'Receive coach-facing AI analysis.', 'Communication and AI', 'coach'],
    'ai_recommendations' => ['AI Recommendations', 'Receive player development recommendations.', 'Communication and AI', 'player'],

    // Reports and Recaps
    'view_session_report' => ['Session Reports', 'View interpreted session reports.', 'Reports and Recaps', 'shared'],
    'liveab_analytics' => ['Live AB Analytics', 'View advanced Live AB analytics.', 'Reports and Recaps', 'coach'],
    'box_score' => ['Box Scores', 'View Live AB box scores.', 'Reports and Recaps', 'shared'],
    'team_recaps' => ['Team Recaps', 'View generated team recaps.', 'Reports and Recaps', 'coach'],
    'player_recaps' => ['Player Recaps', 'View generated player recaps.', 'Reports and Recaps', 'shared'],

    // Planner and Workouts
    'planner_create' => ['Create Practice Plans', 'Create daily and practice plans.', 'Planner and Workouts', 'coach'],
    'plan_builder' => ['Plan and Strength Builder', 'Build workouts and strength prescriptions.', 'Planner and Workouts', 'coach'],
    'assign_workouts' => ['Assign Workouts', 'Assign and track workouts for players.', 'Planner and Workouts', 'coach'],
    'view_workout_progress' => ['Workout Progress', 'Review assigned-player workout progress.', 'Planner and Workouts', 'coach'],
    'manage_player_groups' => ['Player Groups', 'Group players for planning and assignments.', 'Planner and Workouts', 'coach'],

    // Assessments
    'record_assessments' => ['Record Assessments', 'Enter mobility and strength assessment results.', 'Assessments', 'coach'],
    'view_assessment_reports' => ['Assessment Reports', 'View interpreted assessment reports.', 'Assessments', 'shared'],
    'view_assessment_comparisons' => ['Assessment Comparisons', 'Compare assessment history and benchmarks.', 'Assessments', 'shared'],
    'view_assessment_recommendations' => ['Assessment Recommendations', 'View development recommendations from assessments.', 'Assessments', 'shared'],

    // Arm Care
    'arm_care' => ['Arm Care', 'Use arm-care routines and history.', 'Arm Care', 'shared'],

    // Team and Roster
    'roster_view' => ['Roster View', 'View team rosters.', 'Team and Roster', 'coach'],
    'invite_players' => ['Invite Players', 'Invite players up to the plan limit.', 'Team and Roster', 'coach'],
    'add_coaches' => ['Add Coaches', 'Add coaches up to the plan limit.', 'Team and Roster', 'coach'],
    'team_switching' => ['Team Switching', 'Switch between managed teams.', 'Team and Roster', 'coach'],
    'edit_team' => ['Edit Teams', 'Edit team details.', 'Team and Roster', 'coach'],
    'edit_player' => ['Edit Players', 'Edit player records.', 'Team and Roster', 'coach'],
    'add_team' => ['Add Teams', 'Create additional teams.', 'Team and Roster', 'coach'],
    'manage_multiple_teams' => ['Manage Multiple Teams', 'Manage more than one team.', 'Team and Roster', 'coach'],
    'view_player_cards' => ['Player Cards', 'View and print player cards.', 'Team and Roster', 'coach'],
    'unlimited_players' => ['Unlimited Players', 'Legacy compatibility capability for unlimited rosters.', 'Team and Roster', 'coach'],

    // Player Development
    'view_own_profile' => ['Own Profile', 'View the player profile.', 'Player Development', 'player'],
    'view_own_sessions' => ['Own Sessions', 'View personal session history.', 'Player Development', 'player'],
    'development_graphs' => ['Development Graphs', 'View development trends and graphs.', 'Player Development', 'player'],
    'shareable_profile' => ['Shareable Profile', 'Share a recruiting profile.', 'Player Development', 'player'],
    'recruiting_profile' => ['Recruiting Profile', 'Use the enhanced recruiting profile.', 'Player Development', 'player'],

    // Communication and shared baseline
    'sms_results' => ['SMS Results', 'Send session results by SMS.', 'Communication and AI', 'coach'],
    'notifications' => ['Notifications', 'Receive account and activity notifications.', 'Communication and AI', 'shared'],
    'recent_sessions' => ['Recent Sessions', 'View recent session activity.', 'Core Sessions', 'shared'],
];

$immutable = [
    'free' => ['create_session', 'record_pitches', 'view_session_history', 'roster_view', 'invite_players', 'add_coaches', 'notifications', 'recent_sessions', 'record_assessments'],
    'coach_basic' => ['create_session', 'record_pitches', 'view_session_history', 'roster_view', 'invite_players', 'add_coaches', 'notifications', 'recent_sessions', 'record_assessments'],
    'coach_pro' => ['create_session', 'record_pitches', 'view_session_history', 'roster_view', 'invite_players', 'add_coaches', 'notifications', 'recent_sessions', 'record_assessments'],
    'player_basic' => ['view_own_profile', 'view_own_sessions', 'notifications', 'recent_sessions'],
    'player_pro' => ['view_own_profile', 'view_own_sessions', 'notifications', 'recent_sessions'],
];

$baseline = array_values(array_unique(array_merge(...array_values($immutable))));
$deprecated = ['unlimited_players', 'manage_multiple_teams'];
$notImplemented = ['shareable_profile', 'recruiting_profile'];

return [
    'items' => collect($items)->map(fn (array $item, string $key): array => [
        'key' => $key,
        'display_name' => $item[0],
        'description' => $item[1],
        'category' => $item[2],
        'audience' => $item[3],
        'toggleable' => ! in_array($key, array_merge($baseline, $deprecated, $notImplemented), true),
        'immutable_reason' => match (true) {
            in_array($key, $baseline, true) => 'Authenticated audience baseline; still subject to ownership, membership, assignment, and numeric limits.',
            'unlimited_players' === $key => 'Deprecated. The numeric player limit is authoritative.',
            'manage_multiple_teams' === $key => 'Deprecated as an editable grant. It is derived from the team limit, add_team, and team_switching.',
            in_array($key, $notImplemented, true) => 'Hidden until a verified server-authoritative workflow exists.',
            default => null,
        },
        'hidden' => in_array($key, $notImplemented, true),
        'dependencies' => [],
        'conflicts' => [],
    ])->all(),
    'immutable_by_plan' => $immutable,
    'system_capabilities' => [
        'login', 'register', 'password_recovery', 'profile_settings', 'claim_profile',
        'purchase', 'restore_purchases', 'complete_assigned_workout', 'complete_readiness_survey',
    ],
    'categories' => [
        'Core Sessions', 'Advanced Sessions', 'Statistics and Analytics', 'Reports and Recaps',
        'Planner and Workouts', 'Assessments', 'Arm Care', 'Team and Roster',
        'Player Development', 'Communication and AI',
    ],
    'limits' => [
        'players' => ['display_name' => 'Player limit', 'min' => 0, 'max' => 100000, 'nullable' => true],
        'coaches' => ['display_name' => 'Coach-seat limit', 'min' => 0, 'max' => 10000, 'nullable' => true],
        'teams' => ['display_name' => 'Team limit', 'min' => 0, 'max' => 10000, 'nullable' => true],
    ],
];
