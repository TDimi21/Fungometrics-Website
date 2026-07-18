export const PLANS = {
  FREE: 'free',
  COACH_BASIC: 'coach_basic',
  COACH_PRO: 'coach_pro',
  PLAYER_BASIC: 'player_basic',
  PLAYER_PRO: 'player_pro',
}

export const PLAN_LABELS = {
  free: 'Free',
  coach_basic: 'Coach Basic',
  coach_pro: 'Coach Pro',
  player_basic: 'Player Basic',
  player_pro: 'Player Pro',
}

export const PLAN_PLAYER_LIMIT = {
  free: 10,
  coach_basic: 10,
  coach_pro: Infinity,
  player_basic: 0,
  player_pro: 0,
}

export const PLAN_FEATURES = {
  free: [
    'create_session',
    'record_pitches',
    'view_session_history',
    'roster_view',
    'invite_players',
    'notifications',
    'recent_sessions',
    'record_assessments',
  ],
  coach_basic: [
    'create_session',
    'record_pitches',
    'view_session_history',
    'roster_view',
    'invite_players',
    'notifications',
    'recent_sessions',
    'record_assessments',
  ],
  coach_pro: [
    'create_session',
    'record_pitches',
    'view_session_history',
    'roster_view',
    'invite_players',
    'notifications',
    'recent_sessions',
    'liveab_sessions',
    'exit_velocity_sessions',
    'long_toss_sessions',
    'weighted_ball_sessions',
    'view_team_stats',
    'development_graphs',
    'view_session_report',
    'liveab_analytics',
    'box_score',
    'add_coaches',
    'edit_team',
    'edit_player',
    'add_team',
    'scripted_bp',
    'scripted_bullpen',
    'planner_create',
    'plan_builder',
    'assign_workouts',
    'view_workout_progress',
    'manage_player_groups',
    'record_assessments',
    'view_assessment_reports',
    'view_assessment_comparisons',
    'view_assessment_recommendations',
    'view_player_cards',
    'sms_results',
  ],
  player_basic: [
    'view_own_profile',
    'view_own_sessions',
    'notifications',
    'recent_sessions',
  ],
  player_pro: [
    'view_own_profile',
    'view_own_sessions',
    'notifications',
    'recent_sessions',
    'liveab_sessions',
    'exit_velocity_sessions',
    'long_toss_sessions',
    'weighted_ball_sessions',
    'development_graphs',
    'view_session_report',
    'box_score',
    'view_assessment_reports',
    'view_assessment_comparisons',
    'view_assessment_recommendations',
  ],
}

export const FEATURE_META = {
  create_session:           { label: 'Create Sessions',           category: 'Sessions' },
  record_pitches:           { label: 'Record Pitches',            category: 'Sessions' },
  liveab_sessions:          { label: 'Live AB Sessions',          category: 'Sessions' },
  exit_velocity_sessions:   { label: 'Exit Velocity Sessions',    category: 'Sessions' },
  long_toss_sessions:       { label: 'Long Toss Sessions',        category: 'Sessions' },
  weighted_ball_sessions:   { label: 'Weighted Ball Sessions',    category: 'Sessions' },
  practice_sessions:        { label: 'Practice Sessions',         category: 'Sessions' },
  view_session_history:     { label: 'Session History',           category: 'Stats & Analytics' },
  view_team_stats:          { label: 'Team Stats',                category: 'Stats & Analytics' },
  view_advanced_stats:      { label: 'Advanced Stats',            category: 'Stats & Analytics' },
  view_own_stats:           { label: 'Own Stats',                 category: 'Stats & Analytics' },
  personal_stats:           { label: 'Personal Stats',            category: 'Stats & Analytics' },
  performance_overview:     { label: 'Performance Overview',      category: 'Stats & Analytics' },
  heat_maps:                { label: 'Heat Maps',                 category: 'Stats & Analytics' },
  export_stats:             { label: 'Export Stats',              category: 'Stats & Analytics' },
  ai_analytics:             { label: 'AI Analytics',              category: 'Stats & Analytics' },
  ai_recommendations:       { label: 'AI Recommendations',       category: 'Stats & Analytics' },
  development_graphs:       { label: 'Development Graphs',        category: 'Stats & Analytics' },
  view_session_report:      { label: 'Session Reports',           category: 'Reports' },
  liveab_analytics:         { label: 'Live AB Analytics',         category: 'Reports' },
  box_score:                { label: 'Box Score',                 category: 'Reports' },
  team_recaps:              { label: 'Team Recaps',               category: 'Reports' },
  player_recaps:            { label: 'Player Recaps',             category: 'Reports' },
  roster_view:              { label: 'Roster View',               category: 'Team Management' },
  invite_players:           { label: 'Invite Players',            category: 'Team Management' },
  add_coaches:              { label: 'Add Coaches',               category: 'Team Management' },
  team_switching:           { label: 'Team Switching',            category: 'Team Management' },
  edit_team:                { label: 'Edit Team',                 category: 'Team Management' },
  edit_player:              { label: 'Edit Players',              category: 'Team Management' },
  add_team:                 { label: 'Add Teams',                 category: 'Team Management' },
  manage_multiple_teams:    { label: 'Multiple Teams',            category: 'Team Management' },
  unlimited_players:        { label: 'Unlimited Players',         category: 'Team Management' },
  view_player_cards:        { label: 'Player Cards',              category: 'Team Management' },
  view_own_profile:         { label: 'Own Profile',               category: 'Profile' },
  view_own_sessions:        { label: 'Own Sessions',              category: 'Profile' },
  shareable_profile:        { label: 'Shareable Profile',         category: 'Profile' },
  recruiting_profile:       { label: 'Recruiting Profile',        category: 'Profile' },
  notifications:            { label: 'Notifications',             category: 'Other' },
  recent_sessions:          { label: 'Recent Sessions',           category: 'Other' },
  sms_results:              { label: 'SMS Results',               category: 'Other' },
}

// Display fallback only. Runtime authorization always comes from /api/me/access;
// administrators edit Laravel's plan_entitlements through protected APIs.
export function getActivePlanFeatures(plan) {
  return PLAN_FEATURES[plan] ?? []
}

// Full list of every known feature key (for admin to build the matrix).
export function getAllFeatureKeys() {
  return Object.keys(FEATURE_META)
}

export function hasFeature(plan, feature) {
  return getActivePlanFeatures(plan).includes(feature)
}

export function getPlanLabel(plan) {
  return PLAN_LABELS[plan] ?? 'Free'
}

export function isCoachPlan(plan) {
  return plan === PLANS.COACH_BASIC || plan === PLANS.COACH_PRO
}

export function isPlayerPlan(plan) {
  return plan === PLANS.PLAYER_BASIC || plan === PLANS.PLAYER_PRO
}

export function groupFeatures(plan) {
  const features = getActivePlanFeatures(plan)
  const groups = {}
  features.forEach((key) => {
    const meta = FEATURE_META[key] || { label: key, category: 'Other' }
    if (!groups[meta.category]) groups[meta.category] = []
    groups[meta.category].push({ key, label: meta.label })
  })
  return groups
}
