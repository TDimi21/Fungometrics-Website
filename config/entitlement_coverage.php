<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Phase 3C.2 entitlement coverage registry
|--------------------------------------------------------------------------
|
| This registry describes proven runtime wiring. It is intentionally
| conservative: an administrator toggle is never labelled fully_wired unless
| Laravel, web, and mobile all enforce it. The registry is audit metadata;
| /api/me/access remains the runtime authority.
|
*/

$route = static fn (string $method, string $uri, string $controller, bool $enforced): array => compact(
    'method',
    'uri',
    'controller',
    'enforced'
);

$entry = static function (
    array $features,
    array $backend = [],
    array $web = [],
    array $mobile = [],
    string $status = 'not_implemented',
    array $gaps = [],
    string $behavior = 'deny',
    string $access = 'mutating',
    array $dependencies = [],
    array $limits = []
): array {
    return [
        'features' => $features,
        'backend' => $backend,
        'web' => $web,
        'mobile' => $mobile,
        'enforcement_behavior' => $behavior,
        'access_type' => $access,
        'dependencies' => $dependencies,
        'numeric_limits' => $limits,
        'implementation_status' => $status,
        'gaps' => $gaps,
    ];
};

return [
    'allowed_statuses' => [
        'fully_wired', 'backend_only', 'client_only', 'missing_route',
        'missing_web_gate', 'missing_mobile_gate',
        'intentionally_always_available', 'not_implemented',
    ],

    'entitlements' => [
        'create_session' => $entry(
            ['Batting Practice', 'Bullpen Practice', 'Cage Regular', 'Cage Game', 'Cage Practice'],
            [$route('POST', 'api/training', 'Training\\AddNewSession', false)],
            ['resources/router/index.js:create.training'],
            ['src/navigations/ModalOptions.js', 'src/services/trainingService.js'],
            'missing_route',
            ['backend_create_not_gated'],
            'create-only; retain historical reads'
        ),
        'record_pitches' => $entry(
            ['Basic ball-by-ball session table', 'Record Pitches'],
            [
                $route('POST', 'api/result/batting', 'Training\\Result\\SaveBattingResultPractice', false),
                $route('POST', 'api/result/bullpen', 'Training\\Result\\SaveBullpenResultPractice', false),
                $route('POST', 'api/result/cage', 'Training\\Result\\SaveCageResultPractice', false),
            ],
            ['resources/js/pages/training/Batting.vue', 'resources/js/pages/training/Bullpen.vue'],
            ['src/screens/ResumeSession'],
            'missing_route',
            ['backend_writes_not_gated'],
            'create/update; retain historical reads'
        ),
        'view_session_history' => $entry(
            ['Session History', 'Basic ball-by-ball session table'],
            [$route('GET', 'api/sessions/*', 'Sessions controllers', false)],
            ['resources/router/index.js:sessions.all'],
            ['src/navigations/TopTabNavigator.js'],
            'missing_route',
            ['shared_history_routes_not_gated'],
            'historical-read'
        ),
        'scripted_bp' => $entry(
            ['Scripted BP'],
            [
                $route('POST', 'api/result/scripted-bp/plan', 'Training\\Result\\SaveScriptedBpPlan', true),
                $route('POST', 'api/result/scripted-bp/swing', 'Training\\Result\\SaveScriptedBpSwing', true),
                $route('GET', 'api/result/scripted-bp/{practice}', 'Sessions\\Results\\GetScriptedBpResults', true),
            ],
            ['resources/js/pages/training/SessionReport.vue'],
            ['src/screens/BattingSession.js', 'src/screens/ResumeSession/ResumeBattingModern.js'],
            'missing_web_gate',
            ['web_entry_and_deep_link_not_gated'],
            'create/view/update'
        ),
        'scripted_bullpen' => $entry(
            ['Scripted Bullpen'],
            [
                $route('POST', 'api/training', 'Training\\AddNewSession', true),
                $route('GET|PUT|DELETE', 'api/training/{practice}', 'Training session controllers', true),
                $route('GET|POST|PUT', 'api/result/bullpen/{practice?}', 'Training\\Result bullpen controllers', true),
                $route('GET', 'api/statistics/{practice}/bullpen', 'Sessions\\Results\\GetBullpenPracticeResults', true),
            ],
            ['resources/js/pages/training/Bullpen.vue', 'resources/js/pages/training/SessionReport.vue'],
            ['src/screens/bullpenScreen.js', 'src/screens/ResumeSession/ResumeBullpenModern.js', 'src/screens/Statics/ScriptedBullpenScorecard.js'],
            'missing_web_gate',
            ['web_scripted_mode_entry_and_deep_link_not_gated'],
            'create/view/update/report/delete'
        ),
        'liveab_sessions' => $entry(
            ['Live AB'],
            [
                $route('POST', 'api/training', 'Training\\AddNewSession', false),
                $route('POST', 'api/result/liveab', 'Training\\Result\\SaveLiveABResultPractice', true),
                $route('GET', 'api/statistics/{practice}/liveab', 'Sessions\\Results\\GetLiveABPracticeResults', true),
            ],
            ['resources/router/index.js:create.ab', 'resources/router/index.js:track.live'],
            ['src/navigations/ModalOptions.js', 'src/navigations/TopTabNavigator.js'],
            'missing_route',
            ['shared_session_create_not_gated', 'web_start_practice_rollout_disabled'],
            'create/view/update'
        ),
        'exit_velocity_sessions' => $entry(
            ['Exit Velocity'],
            [
                $route('POST', 'api/training', 'Training\\AddNewSession', false),
                $route('POST', 'api/result/exitvelocity', 'Training\\Result\\SaveExitVelocityResultPractice', true),
            ],
            ['resources/router/index.js:track.trainingMode'],
            ['src/navigations/ModalOptions.js', 'src/navigations/TopTabNavigator.js'],
            'missing_route',
            ['shared_session_create_not_gated', 'web_start_practice_rollout_disabled'],
            'create/view/update'
        ),
        'long_toss_sessions' => $entry(
            ['Long Toss'],
            [
                $route('POST', 'api/training', 'Training\\AddNewSession', false),
                $route('POST', 'api/result/longtoss', 'Training\\Result\\SaveLongTossResultPractice', true),
            ],
            ['resources/router/index.js:track.trainingMode'],
            ['src/navigations/ModalOptions.js', 'src/navigations/TopTabNavigator.js'],
            'missing_route',
            ['shared_session_create_not_gated', 'web_start_practice_rollout_disabled'],
            'create/view/update'
        ),
        'weighted_ball_sessions' => $entry(
            ['Weighted Ball'],
            [
                $route('POST', 'api/training', 'Training\\AddNewSession', false),
                $route('POST', 'api/result/weightball', 'Training\\Result\\SaveWeightBallResultPractice', true),
            ],
            ['resources/router/index.js:track.trainingMode'],
            ['src/navigations/ModalOptions.js', 'src/navigations/TopTabNavigator.js'],
            'missing_route',
            ['shared_session_create_not_gated', 'web_start_practice_rollout_disabled'],
            'create/view/update'
        ),
        'practice_sessions' => $entry(
            ['Practice Sessions'],
            [],
            ['resources/js/pages/practice/PracticePlanner.vue'],
            ['src/navigations/TopTabNavigator.js', 'src/screens/PracticeSessionScreen.js'],
            'client_only',
            ['no_distinct_backend_operation'],
            'create/view/update'
        ),
        'view_team_stats' => $entry(
            ['Team statistics', 'Leaders and Top 10'],
            [$route('GET', 'api/result/statistics/{team}', 'Training\\Result\\FilterTrainings', true)],
            ['resources/router/index.js:new-statistic'],
            ['src/navigations/Stacks/StatsStack.js'],
            'missing_web_gate',
            ['web_stats_routes_not_gated'],
            'read-only',
            'read-only'
        ),
        'view_advanced_stats' => $entry(
            ['Pitch breakdown', 'Spray charts', 'Trajectory statistics', 'Velocity grids', 'Player Development Board'],
            [$route('GET', 'api/coach/development/*', 'Coach\\GetPlayerDevelopmentDashboard', true)],
            ['resources/router/index.js:development.*', 'resources/router/index.js:new-statistic'],
            ['src/navigations/Stacks/StatsStack.js', 'src/screens/Statics/*'],
            'missing_web_gate',
            ['web_deep_links_not_gated', 'shared_stats_payload_mixes_free_and_paid_data'],
            'read-only',
            'read-only'
        ),
        'view_own_stats' => $entry(
            ['Advanced personal stats', 'Player session-stat buttons'],
            [$route('GET', 'api/player/statistics/{player}', 'ScoresStatisticPlayers', false)],
            ['resources/js/pages/dashboard/Player.vue'],
            ['src/screens/metricsplayer/metricsplayer.js'],
            'missing_route',
            ['own_stats_endpoint_not_entitlement_gated', 'ownership_must_remain_enforced'],
            'read-only',
            'read-only'
        ),
        'personal_stats' => $entry(
            ['Personal stats', 'Metrics and weight-room log'],
            [$route('GET', 'api/player/fitness/{id}', 'Player\\GetFitness', false)],
            ['resources/js/pages/dashboard/Player.vue'],
            ['src/navigations/Stacks/StatsStack.js', 'src/screens/metricsplayer/metricsplayer.js'],
            'missing_route',
            ['backend_personal_stats_reads_not_gated'],
            'read-only',
            'read-only'
        ),
        'performance_overview' => $entry(
            ['Performance Overview'],
            [$route('GET', 'api/coach/performance-overview/{team}', 'Coach\\GetPerformanceOverview', true)],
            ['resources/js/pages/dashboard/Index.vue'],
            ['src/components/TeamStatsPanel/index.js'],
            'missing_web_gate',
            ['web_panel_not_gated'],
            'read-only',
            'read-only'
        ),
        'heat_maps' => $entry(
            ['Heat maps'],
            [],
            ['resources/js/pages/statistics/*'],
            ['src/screens/StatsTablesScreen.js', 'src/screens/Statics/*'],
            'client_only',
            ['heat_map_data_shares_advanced_stats_payload', 'no_isolated_backend_operation'],
            'read-only',
            'read-only'
        ),
        'export_stats' => $entry(
            ['Export statistics'],
            [],
            ['resources/js/pages/statistics/*'],
            ['src/screens/StatsTablesScreen.js'],
            'client_only',
            ['export_operations_not_mapped_to_protected_endpoint'],
            'export',
            'read-only'
        ),
        'ai_analytics' => $entry(
            ['AI analytics'],
            [$route('GET/POST', 'api/intelligence/*', 'IntelligenceController', false)],
            ['resources/js/features/development/*'],
            ['src/screens/Planner/*'],
            'missing_route',
            ['intelligence_group_uses_view_advanced_stats_instead_of_ai_analytics'],
            'read/write'
        ),
        'ai_recommendations' => $entry(
            ['AI recommendations'],
            [],
            ['resources/js/features/development/*'],
            ['src/screens/assessmentReport/AssessmentReportScreen.js'],
            'client_only',
            ['no_isolated_player_recommendation_route'],
            'read-only',
            'read-only'
        ),
        'view_session_report' => $entry(
            ['Session reports', 'Live AB report'],
            [$route('GET', 'api/statistics/{practice}/*', 'Sessions\\Results controllers', false)],
            ['resources/router/index.js:session.report'],
            ['src/navigations/Stacks/StatsStack.js'],
            'missing_route',
            ['report_routes_are_gated_by_session_type_or_open_not_view_session_report'],
            'historical-read',
            'read-only'
        ),
        'liveab_analytics' => $entry(
            ['Live AB report'],
            [],
            ['resources/js/pages/training/LiveABStatistic.vue'],
            ['src/screens/LiveABSessionReportScreen.js'],
            'client_only',
            ['no_isolated_backend_operation'],
            'read-only',
            'read-only',
            ['liveab_sessions']
        ),
        'box_score' => $entry(
            ['Box Score'],
            [],
            ['resources/js/pages/training/LiveABStatistic.vue'],
            ['src/screens/BoxScoreScreen.js'],
            'client_only',
            ['no_isolated_backend_operation'],
            'read-only',
            'read-only',
            ['liveab_sessions']
        ),
        'team_recaps' => $entry(
            ['Team Recaps'],
            [],
            [],
            ['src/screens/TeamSessionRecapScreen.js'],
            'client_only',
            ['no_isolated_backend_operation'],
            'read-only',
            'read-only'
        ),
        'player_recaps' => $entry(
            ['Player Recaps'],
            [],
            [],
            ['src/screens/PlayerRecapScreen.js'],
            'client_only',
            ['no_isolated_backend_operation'],
            'read-only',
            'read-only'
        ),
        'planner_create' => $entry(
            ['Daily Planner', 'Practice Planner creation'],
            [
                $route('GET/POST/DELETE', 'api/coach/daily-plans*', 'Planner\\DailyPlan controllers', true),
                $route('GET/POST/DELETE', 'api/coach/practice-plans*', 'Coach\\PracticePlan controllers', true),
            ],
            ['resources/router/index.js:practice.planner'],
            ['src/screens/Planner/DailyPlannerScreen.js'],
            'missing_route',
            ['additional_planner_endpoints_are_open'],
            'create/view/update/delete'
        ),
        'plan_builder' => $entry(
            ['Plan Builder', 'Strength Set Builder'],
            [$route('GET/POST/DELETE', 'api/coach/drills*', 'Planner\\Drill controllers', true)],
            ['resources/js/pages/practice/PracticePlanner.vue'],
            ['src/screens/Planner/PlanBuilderScreen.js', 'src/screens/Planner/StrengthSetBuilder.js'],
            'missing_web_gate',
            ['web_builder_actions_not_gated'],
            'create/view/update/delete'
        ),
        'assign_workouts' => $entry(
            ['Publish plans', 'Assign workouts', 'Track workout progress'],
            [$route('POST', 'api/coach/*/publish*', 'Planner\\WeeklyPlanPublishController', true)],
            ['resources/js/pages/practice/PracticePlanner.vue'],
            ['src/screens/Planner/DailyPlannerScreen.js'],
            'missing_web_gate',
            ['web_publish_actions_not_gated', 'some_assignment_routes_are_open'],
            'publish/assign'
        ),
        'view_workout_progress' => $entry(
            ['Coach player-progress view'],
            [$route('GET', 'api/coach/daily-plans/{id}/progress', 'Planner\\GetDailyPlanProgress', true)],
            ['resources/js/pages/practice/PracticePlanner.vue'],
            ['src/screens/Planner/CoachWorkoutPlayersScreen.js'],
            'missing_web_gate',
            ['web_progress_actions_not_gated'],
            'read/review'
        ),
        'manage_player_groups' => $entry(
            ['Player Groups'],
            [$route('GET/POST/DELETE', 'api/coach/player-groups*', 'Coach\\PlayerGroup controllers', true)],
            ['resources/js/pages/practice/PracticePlanner.vue'],
            ['src/screens/Planner/PlayerGroupsScreen.js'],
            'missing_web_gate',
            ['web_group_actions_not_gated'],
            'create/view/delete'
        ),
        'record_assessments' => $entry(
            ['Mobility assessment entry', 'Strength assessment entry'],
            [$route('POST', 'api/assessments', 'Coach\\SaveAssessment', false)],
            ['resources/js/features/development/components/AssessmentModal.vue'],
            ['src/screens/More/AssessmentScreen.js'],
            'missing_route',
            ['assessment_write_is_not_entitlement_gated', 'ownership_and_audience_contract_needs_explicit_tests'],
            'create/update'
        ),
        'view_assessment_reports' => $entry(
            ['Assessment reports'],
            [$route('GET', 'api/assessments/player/{player}', 'Coach\\GetPlayerAssessments', true)],
            ['resources/router/index.js:assessment.reports'],
            ['src/screens/assessmentReport/AssessmentReportScreen.js'],
            'missing_web_gate',
            ['mounted_web_page_does_not_react_to_revocation'],
            'read-only',
            'read-only'
        ),
        'view_assessment_comparisons' => $entry(
            ['Assessment comparisons'],
            [],
            ['resources/js/pages/dashboard/AssessmentReports.vue'],
            ['src/screens/assessmentReport/AssessmentReportScreen.js'],
            'client_only',
            ['comparison_data_shares_report_endpoint', 'no_isolated_backend_operation'],
            'read-only',
            'read-only',
            ['view_assessment_reports']
        ),
        'view_assessment_recommendations' => $entry(
            ['Assessment recommendations'],
            [$route('POST', 'api/coach/assessments/{id}/insights', 'Coach\\AssessmentInsightController', true)],
            ['resources/js/features/development/components/AssessmentReportCard.vue'],
            ['src/screens/assessmentReport/AssessmentReportScreen.js'],
            'missing_web_gate',
            ['web_recommendation_action_not_gated', 'mobile_read_data_shares_report_endpoint'],
            'read/update',
            'read-only',
            ['view_assessment_reports']
        ),
        'arm_care' => $entry(
            ['Arm Care routines', 'Guided sessions', 'Arm Care history'],
            [$route('GET/POST', 'api/result/armcare', 'Training\\Result\\ArmCare controllers', true)],
            ['resources/router/index.js:arm.care'],
            ['src/navigations/Stacks/StatsStack.js'],
            'missing_web_gate',
            ['mounted_web_page_does_not_react_to_revocation'],
            'create/read'
        ),
        'roster_view' => $entry(
            ['Roster view'],
            [$route('GET', 'api/coach/teams/{id}', 'Coach\\GetTeamById', false)],
            ['resources/router/index.js:roster'],
            ['src/screens/MoreScreen.js'],
            'missing_route',
            ['roster_reads_not_entitlement_gated'],
            'read-only',
            'read-only'
        ),
        'invite_players' => $entry(
            ['Invite players'],
            [$route('POST', 'api/coach/add/players', 'Coach\\AddPlayers', false)],
            ['resources/js/layout/Layout.vue'],
            ['src/screens/MoreScreen.js'],
            'missing_route',
            ['add_player_route_not_entitlement_gated'],
            'create',
            'mutating',
            [],
            ['players']
        ),
        'add_coaches' => $entry(
            ['Add coaches'],
            [$route('POST', 'api/coach/add/coaches', 'Coach\\AddCoaches', false)],
            ['resources/js/layout/Layout.vue'],
            ['src/screens/MoreScreen.js'],
            'missing_route',
            ['add_coach_route_not_entitlement_gated'],
            'create',
            'mutating',
            [],
            ['coaches']
        ),
        'team_switching' => $entry(
            ['Team switching'],
            [],
            ['resources/js/layout/Layout.vue'],
            ['src/screens/MoreScreen.js'],
            'client_only',
            ['team_list_and_switch_operations_not_mapped'],
            'read/select'
        ),
        'edit_team' => $entry(
            ['Edit team'],
            [$route('POST', 'api/coach/edit/teams/{id}', 'Coach\\EditTeams', false)],
            ['resources/router/index.js:manage.team.update'],
            ['src/screens/MoreScreen.js'],
            'missing_route',
            ['edit_team_route_not_entitlement_gated'],
            'update'
        ),
        'edit_player' => $entry(
            ['Edit player'],
            [$route('POST', 'api/edit/players/{id}', 'Coach\\EditPlayers', false)],
            ['resources/router/index.js:roster.editPlayer'],
            ['src/screens/MoreScreen.js'],
            'missing_route',
            ['edit_player_route_not_entitlement_gated'],
            'update'
        ),
        'add_team' => $entry(
            ['Add team'],
            [$route('POST', 'api/coach/add/teams', 'Coach\\AddTeams', true)],
            ['resources/router/index.js:manage.team'],
            ['src/screens/MoreScreen.js'],
            'missing_web_gate',
            ['web_route_not_gated'],
            'create',
            'mutating',
            [],
            ['teams']
        ),
        'manage_multiple_teams' => $entry(
            ['Multiple teams'],
            [],
            ['resources/js/layout/Layout.vue'],
            ['src/screens/MoreScreen.js'],
            'client_only',
            ['no_isolated_backend_operation', 'overlaps_add_team_and_team_switching'],
            'read/manage',
            'mutating',
            ['add_team', 'team_switching'],
            ['teams']
        ),
        'view_player_cards' => $entry(
            ['Player Cards'],
            [$route('GET', 'api/coach/teams/{id}/player-cards', 'Coach\\GetTeamPlayerCards', true)],
            [],
            ['src/navigations/Stacks/MoreStack.js'],
            'missing_web_gate',
            ['web_feature_not_implemented'],
            'read/print',
            'read-only'
        ),
        'unlimited_players' => $entry(
            ['Unlimited players'],
            [],
            [],
            [],
            'not_implemented',
            ['legacy_compatibility_only', 'numeric_players_limit_is_authoritative'],
            'capacity',
            'mutating',
            [],
            ['players']
        ),
        'view_own_profile' => $entry(
            ['Own profile'],
            [$route('GET', 'api/player/me', 'Player\\GetMe', false)],
            ['resources/router/index.js:profile-player'],
            ['src/screens/homePlayer.js'],
            'missing_route',
            ['own_profile_route_not_entitlement_gated'],
            'read-only',
            'read-only'
        ),
        'view_own_sessions' => $entry(
            ['Own sessions'],
            [$route('GET', 'api/player/sessions/*', 'Player session controllers', false)],
            ['resources/js/pages/dashboard/Player.vue'],
            ['src/navigations/TopTabNavigatorPlayer.js'],
            'missing_route',
            ['own_session_routes_not_entitlement_gated'],
            'historical-read',
            'read-only'
        ),
        'development_graphs' => $entry(
            ['Development graphs'],
            [$route('GET', 'api/player/development/*', 'Coach\\GetPlayerDevelopmentDashboard', true)],
            ['resources/router/index.js:development.player'],
            ['src/screens/homePlayer.js'],
            'missing_web_gate',
            ['route_uses_view_advanced_stats_instead_of_development_graphs', 'web_route_not_gated'],
            'read-only',
            'read-only'
        ),
        'shareable_profile' => $entry(
            ['Shareable profile'],
            [],
            [],
            [],
            'not_implemented',
            ['no_verified_backend_or_client_operation'],
            'share',
            'read-only'
        ),
        'recruiting_profile' => $entry(
            ['Recruiting profile'],
            [],
            [],
            ['src/screens/homePlayer.js'],
            'client_only',
            ['no_verified_backend_operation'],
            'read/share',
            'read-only'
        ),
        'sms_results' => $entry(
            ['SMS results'],
            [$route('POST', 'api/coach/send/results/{practice}', 'Coach\\SendSmsResults', true)],
            ['resources/js/layout/Layout.vue'],
            [],
            'missing_mobile_gate',
            ['mobile_entry_not_mapped'],
            'message'
        ),
        'notifications' => $entry(
            ['Notifications'],
            [],
            [],
            [],
            'not_implemented',
            ['baseline_capability_without_verified_runtime_operation'],
            'read-only',
            'read-only'
        ),
        'recent_sessions' => $entry(
            ['Recent sessions'],
            [$route('GET', 'api/coach/sessions/lasts/{team}', 'Coach\\GetLastSessions', false)],
            ['resources/js/pages/dashboard/*'],
            ['src/components/CardRecentSession/index.js'],
            'missing_route',
            ['recent_session_route_not_entitlement_gated'],
            'read-only',
            'read-only'
        ),
    ],

    'limits' => [
        'players' => [
            'backend' => ['Coach\\AddPlayers', 'Coach\\CoachUtils'],
            'status' => 'backend_only',
            'gaps' => ['web_usage_display_not_standardized', 'mobile_usage_display_not_standardized'],
        ],
        'coaches' => [
            'backend' => ['Coach\\AddCoaches', 'Coach\\CoachUtils'],
            'status' => 'backend_only',
            'gaps' => ['web_usage_display_not_standardized', 'mobile_usage_display_not_standardized'],
        ],
        'teams' => [
            'backend' => ['Coach\\AddTeams'],
            'status' => 'backend_only',
            'gaps' => ['web_usage_display_not_standardized', 'mobile_usage_display_not_standardized'],
        ],
    ],

    'system_capabilities' => [
        'login', 'register', 'password_recovery', 'profile_settings', 'claim_profile',
        'purchase', 'restore_purchases', 'complete_assigned_workout', 'complete_readiness_survey',
    ],
];
