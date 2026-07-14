const titleCase = (value) => String(value || '')
  .replace(/[_-]+/g, ' ')
  .replace(/\s+/g, ' ')
  .trim()
  .replace(/\b\w/g, (letter) => letter.toUpperCase())

const labelMaps = {
  planStatus: {
    draft: 'Draft Plan',
    published: 'Published Plan',
    sent: 'Published Plan',
    assigned: 'Assigned to Players',
    in_progress: 'In Progress',
    completed: 'Completed Plan',
    dismissed: 'Dismissed',
    missing: 'No Active Plan',
    unknown: 'Unknown',
  },
  reviewStatus: {
    pending_review: 'Waiting for Coach Review',
    submitted_for_review: 'Submitted for Coach Review',
    approved: 'Approved',
    rejected: 'Rejected',
    correction_requested: 'Needs Correction',
    needs_correction: 'Needs Correction',
    not_required: 'No Review Needed',
  },
  health: {
    elite: 'Elite',
    strong: 'Strong',
    stable: 'Stable',
    needs_attention: 'Needs Attention',
    at_risk: 'At Risk',
    no_data: 'No Data Yet',
  },
  alertSeverity: {
    critical: 'Critical',
    high: 'High',
    medium: 'Medium',
    low: 'Low',
  },
  actionStatus: {
    completed: 'Completed',
    partial: 'Partially Completed',
    skipped: 'Skipped',
    failed: 'Failed',
    navigation_only: 'Open Section',
    confirmation_required: 'Confirmation Required',
  },
  deliveryStatus: {
    prepared: 'Prepared',
    copy_only: 'Copy Only',
    draft_created: 'Draft Created',
    sent: 'Sent',
    partial: 'Partially Sent',
    blocked: 'Blocked for Safety',
    unsupported: 'Not Configured',
    failed: 'Failed',
  },
  benchmarkSource: {
    research_benchmark: 'Research Benchmark',
    research_only: 'Research Benchmark',
    fmtrx_population: 'FMTRX Population',
    population_enabled: 'FMTRX Population',
    composite: 'Research + FMTRX Blend',
    composite_benchmark: 'Research + FMTRX Blend',
    composite_enabled: 'Research + FMTRX Blend',
    needs_review: 'Needs Review',
    disabled: 'Not Used',
    auto: 'Auto Safety Mode',
  },
  comparisonGroup: {
    exact_peer: 'Closest Peer Group',
    athletic_peer: 'Athletic Peer Group',
    age_role: 'Age + Role Group',
    age_only: 'Age Group',
    global_clean: 'Broad FMTRX Population',
    broad_unknown: 'Broad FMTRX Population',
    none: 'Not Enough FMTRX Data',
  },
  metric: {
    average_exit_velocity: 'Average Exit Velocity',
    max_exit_velocity: 'Max Exit Velocity',
    top_exit_velocity: 'Top Exit Velocity',
    hard_hit_percentage: 'Hard-Hit %',
    line_drive_percentage: 'Line-Drive %',
    hitter_swing_miss_percentage: 'Swing/Miss %',
    average_fastball_velocity: 'Average Fastball Velocity',
    max_fastball_velocity: 'Max Fastball Velocity',
    top_pitch_velocity: 'Top Pitch Velocity',
    pitcher_swing_miss_percentage: 'Pitcher Swing/Miss %',
    strike_percentage: 'Strike %',
    long_toss_max_distance: 'Long Toss Max Distance',
    weighted_ball_5oz_velocity: '5 oz Weighted Ball Velocity',
    bench_press: 'Bench Press',
    squat: 'Squat',
    deadlift: 'Deadlift',
    pull_ups: 'Pull-Ups',
    pushups: 'Pushups',
    forty_yard_dash: '40-Yard Dash',
    sixty_yard_dash: '60-Yard Dash',
    broad_jump: 'Broad Jump',
    vertical_jump: 'Vertical Jump',
    mobility_score: 'Mobility Score',
    shoulder_mobility_score: 'Shoulder Mobility',
    hip_mobility_score: 'Hip Mobility',
    t_spine_mobility_score: 'T-Spine Mobility',
    player_context: 'Roster Profile',
    player_benchmark_metrics: 'Benchmark Baseline',
  },
  actionType: {
    open_daily_planner: 'Open Daily Planner',
    generate_suggested_plan: 'Generate Suggested Plan',
    save_suggested_plan_draft: 'Save Draft',
    publish_plan: 'Publish Plan',
    assign_plan: 'Assign Players',
    send_reminder: 'Send Reminder',
    review_submissions: 'Review Submitted Values',
    approve_values: 'Approve Selected',
    approve_selected_values: 'Approve Selected',
    request_corrections: 'Request Correction',
    promote_trusted_data: 'Promote Trusted Data',
    refresh_intelligence: 'Refresh Intelligence',
    collect_baselines: 'Collect Baselines',
    prepare_weekly_report: 'Prepare Weekly Update',
    prepare_parent_update: 'Prepare Parent Update',
    export_report: 'Export Report',
    copy_summary: 'Copy Summary',
    create_draft: 'Create Draft',
    view_alerts: 'View Alerts',
    view_health_score: 'View Health',
    view_benchmark_intelligence: 'View Benchmark Intelligence',
    generate_next_week_plan: 'Generate Next Week Draft',
    open_weekly_calendar: 'Open Weekly Calendar',
  },
  audience: {
    coach: 'Coach Report',
    coaches: 'Coach Report',
    parents: 'Parent Update',
    parent: 'Parent Update',
    players: 'Player Development Summary',
    player: 'Player Development Summary',
    staff: 'Staff Report',
    director: 'Staff Report',
  },
  template: {
    detailed_coach_report: 'Weekly Team Report',
    parent_update: 'Parent Update',
    staff_report: 'Staff Report',
    player_summary: 'Player Development Summary',
    season_review_packet: 'Season Review Packet',
    parent_safe_season_summary: 'Parent-Safe Season Summary',
  },
  generic: {
    source_mix: 'Benchmark Source',
    population_policy: 'Population Learning Policy',
    global_clean: 'Broad FMTRX Population',
    trusted_payload_only: 'Trusted Benchmark Data',
    submitted_payload: 'Submitted Results',
    approved_payload: 'Approved Results',
    promotion_result: 'Trusted Data Update',
    benchmark_profile: 'Benchmark Intelligence',
    bucket_count: 'Comparison Group Size',
    selected_bucket_key: 'Selected Comparison Group',
    attempted_buckets: 'Comparison Groups Checked',
    daily_plan_progress: 'Player Progress',
    review_status: 'Coach Review Status',
    coach_action_practice_plan: 'Suggested Practice Plan',
    metric_key: 'Metric',
    player_id: 'Player',
    team_id: 'Team',
    command_center: 'Coach Command Center',
    coach_command_center: 'Coach Command Center',
    operating_system_home: 'FMTRX Operating System',
    no_data: 'No Data Yet',
    below_average: 'Below Average',
    score_0_100: 'Score',
    data_collection_priority: 'Data Collection Priority',
  },
}

const formatFrom = (map, value, fallback = '—') => {
  const key = String(value ?? '').trim()
  if (!key) return fallback
  return map[key] || labelMaps.generic[key] || titleCase(key) || fallback
}

export const formatLabel = (value, fallback = '—') => formatFrom(labelMaps.generic, value, fallback)
export const formatPlanStatus = (value, fallback = 'Unknown') => formatFrom(labelMaps.planStatus, value, fallback)
export const formatReviewStatus = (value, fallback = 'No Review Needed') => formatFrom(labelMaps.reviewStatus, value, fallback)
export const formatHealthLabel = (value, fallback = 'No Data Yet') => formatFrom(labelMaps.health, value, fallback)
export const formatAlertSeverity = (value, fallback = 'Low') => formatFrom(labelMaps.alertSeverity, value, fallback)
export const formatActionStatus = (value, fallback = 'Unknown') => formatFrom(labelMaps.actionStatus, value, fallback)
export const formatDeliveryStatus = (value, fallback = 'Unknown') => formatFrom(labelMaps.deliveryStatus, value, fallback)
export const formatBenchmarkSource = (value, fallback = 'Research Benchmark') => formatFrom(labelMaps.benchmarkSource, value, fallback)
export const formatComparisonGroup = (value, fallback = 'Comparison Group') => formatFrom(labelMaps.comparisonGroup, value, fallback)
export const formatMetricName = (value, fallback = 'Metric') => formatFrom(labelMaps.metric, value, fallback)
export const formatActionType = (value, fallback = 'Action') => formatFrom(labelMaps.actionType, value, fallback)
export const formatAudience = (value, fallback = 'Audience') => formatFrom(labelMaps.audience, value, fallback)
export const formatTemplateName = (value, fallback = 'Report Template') => formatFrom(labelMaps.template, value, fallback)

export const comparisonGroupExplanation = (value) => ({
  exact_peer: 'Compared with players most similar in age, level, position, body size, height, throwing hand, and batting side.',
  athletic_peer: 'Compared with players similar in age, level, position, and bodyweight.',
  age_role: 'Compared with players in the same age and role group.',
  age_only: 'Compared with players in the same age group.',
  global_clean: 'Compared with all trusted FMTRX values because smaller peer groups were not large enough yet.',
  broad_unknown: 'Compared with trusted FMTRX values because player context is missing or incomplete.',
  none: 'FMTRX needs at least 30 trusted values before player population data can influence this metric.',
}[String(value ?? '').trim()] || 'Comparison group details are not available yet.')

export const benchmarkSourceExplanation = (value) => ({
  research_benchmark: 'FMTRX is using research standards while the player-data sample grows.',
  research_only: 'FMTRX is using research standards while the player-data sample grows.',
  composite: 'FMTRX is blending research standards with trusted player data.',
  composite_benchmark: 'FMTRX is blending research standards with trusted player data.',
  fmtrx_population: 'FMTRX is using trusted player data for this benchmark.',
}[String(value ?? '').trim()] || 'Benchmark source details are not available yet.')
