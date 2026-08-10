// Individual assessment items — every field the assessment wizard actually
// captures AND actually persists somewhere. Strength/speed lifts sync into
// PlayerFitness (SaveAssessment::buildFitnessSnapshotData); mobility/scores
// are flat columns on PlayerAssessment; hitting/pitching/throwing-workload
// are the JSON blob columns on PlayerAssessment. Items live wherever the
// backend actually wrote them, not wherever it'd be convenient to pretend.
export const ITEM_CATALOG = [
  { category: 'Strength & Speed', items: [
    { key: 'body_weight', label: 'Body Weight', unit: ' lb', source: 'fitness' },
    { key: 'front_squat', label: 'Front Squat', unit: ' lb', source: 'fitness' },
    { key: 'back_squat', label: 'Back Squat', unit: ' lb', source: 'fitness' },
    { key: 'bench_press', label: 'Bench Press', unit: ' lb', source: 'fitness' },
    { key: 'dead_lift', label: 'Deadlift', unit: ' lb', source: 'fitness' },
    { key: 'trap_bar_deadlift', label: 'Trap-Bar Deadlift', unit: ' lb', source: 'fitness' },
    { key: 'power_clean', label: 'Power Clean', unit: ' lb', source: 'fitness' },
    { key: 'pull_ups', label: 'Pull-Ups', unit: ' reps', source: 'fitness' },
    { key: 'push_ups', label: 'Push-Ups', unit: ' reps', source: 'fitness' },
    { key: 'grip_strength_left', label: 'Grip Strength (Left)', unit: ' lb', source: 'fitness' },
    { key: 'grip_strength_right', label: 'Grip Strength (Right)', unit: ' lb', source: 'fitness' },
    { key: 'vertical_jump', label: 'Vertical Jump', unit: ' in', source: 'fitness' },
    { key: 'broad_jump', label: 'Broad Jump', unit: ' in', source: 'fitness' },
    { key: 'med_ball_rotational_throw', label: 'Med Ball Rotational Throw', unit: ' ft', source: 'fitness' },
    { key: 'plank_hold', label: 'Plank Hold', unit: ' sec', source: 'fitness' },
    { key: 'yd_40_dash', label: '40 Yard Dash', unit: ' sec', source: 'fitness' },
    { key: 'yd_60_dash', label: '60 Yard Dash', unit: ' sec', source: 'fitness' },
  ] },
  { category: 'Recovery', items: [
    { key: 'sleep_hours', label: 'Sleep Hours', unit: ' hrs', source: 'fitness' },
    { key: 'sleep_quality_1_to_5', label: 'Sleep Quality', unit: '/5', source: 'fitness' },
    { key: 'recovery_score', label: 'Recovery Score', unit: '/100', source: 'fitness' },
  ] },
  { category: 'Mobility', items: [
    { key: 'shoulder_mobility', label: 'Shoulder Mobility', unit: '/5', source: 'assessment' },
    { key: 'hip_mobility', label: 'Hip Mobility', unit: '/5', source: 'assessment' },
    { key: 'ankle_mobility', label: 'Ankle Mobility', unit: '/5', source: 'assessment' },
    { key: 'hip_flexor_mobility', label: 'Hip Flexor Mobility', unit: '/5', source: 'assessment' },
    { key: 'rotational_mobility', label: 'T-Spine Rotation', unit: '/5', source: 'assessment' },
  ] },
  { category: 'Hitting', items: [
    { key: 'hitting_data.max_exit_velo', label: 'Max Exit Velocity', unit: ' mph', source: 'assessment' },
    { key: 'hitting_data.avg_exit_velo', label: 'Average Exit Velocity', unit: ' mph', source: 'assessment' },
    { key: 'hitting_data.mechanics.setup', label: 'Setup', unit: '/5', source: 'assessment' },
    { key: 'hitting_data.mechanics.load', label: 'Load', unit: '/5', source: 'assessment' },
    { key: 'hitting_data.mechanics.lower_half', label: 'Lower Half', unit: '/5', source: 'assessment' },
    { key: 'hitting_data.mechanics.rotation', label: 'Rotation', unit: '/5', source: 'assessment' },
    { key: 'hitting_data.mechanics.barrel_path', label: 'Barrel Path', unit: '/5', source: 'assessment' },
    { key: 'hitting_data.mechanics.contact', label: 'Contact', unit: '/5', source: 'assessment' },
    { key: 'hitting_data.mechanics.attack_angle', label: 'Attack Angle', unit: '/5', source: 'assessment' },
    { key: 'hitting_data.mechanics.balance', label: 'Balance', unit: '/5', source: 'assessment' },
    { key: 'hitting_data.mechanics.approach', label: 'Approach', unit: '/5', source: 'assessment' },
  ] },
  { category: 'Pitching', items: [
    { key: 'pitching_data.fastball_velocity', label: 'Fastball Velocity', unit: ' mph', source: 'assessment' },
    { key: 'pitching_data.strike_percentage', label: 'Strike Percentage', unit: '%', source: 'assessment' },
    { key: 'pitching_data.command_percentage', label: 'Command Percentage', unit: '%', source: 'assessment' },
    { key: 'pitching_data.mechanics.posture', label: 'Posture', unit: '/5', source: 'assessment' },
    { key: 'pitching_data.mechanics.tempo', label: 'Tempo', unit: '/5', source: 'assessment' },
    { key: 'pitching_data.mechanics.lower_half', label: 'Lower Half', unit: '/5', source: 'assessment' },
    { key: 'pitching_data.mechanics.front_leg', label: 'Front Leg', unit: '/5', source: 'assessment' },
    { key: 'pitching_data.mechanics.hip_rotation', label: 'Hip Rotation', unit: '/5', source: 'assessment' },
    { key: 'pitching_data.mechanics.core_stability', label: 'Core Stability', unit: '/5', source: 'assessment' },
    { key: 'pitching_data.mechanics.arm_action', label: 'Arm Action', unit: '/5', source: 'assessment' },
    { key: 'pitching_data.mechanics.finish', label: 'Finish', unit: '/5', source: 'assessment' },
  ] },
  { category: 'Throwing / Arm Health', items: [
    { key: 'throwing_workload_data.throwing_days_per_week', label: 'Throw Days / Week', unit: '', source: 'assessment' },
    { key: 'throwing_workload_data.bullpens_per_week', label: 'Bullpens / Week', unit: '', source: 'assessment' },
    { key: 'throwing_workload_data.long_toss_sessions_per_week', label: 'Long Toss / Week', unit: '', source: 'assessment' },
    { key: 'throwing_workload_data.weighted_ball_sessions_per_week', label: 'Weighted Ball / Week', unit: '', source: 'assessment' },
    { key: 'throwing_workload_data.games_per_week', label: 'Games / Week', unit: '', source: 'assessment' },
    { key: 'throwing_workload_data.arm_fatigue', label: 'Arm Fatigue Before Practice', unit: '/5', source: 'assessment' },
    { key: 'throwing_workload_data.arm_soreness', label: 'Arm Soreness (7 Days)', unit: '/10', source: 'assessment' },
    { key: 'throwing_workload_score', label: 'Throwing Workload Score', unit: '', source: 'assessment' },
    { key: 'arm_health_score', label: 'Arm Health Score', unit: '/100', source: 'assessment' },
  ] },
  { category: 'Scores', items: [
    { key: 'strength_overall_score', label: 'Strength Overall', unit: '/100', source: 'assessment' },
    { key: 'mobility_overall_score', label: 'Mobility Overall', unit: '/100', source: 'assessment' },
    { key: 'hitting_score', label: 'Hitting Score', unit: '/100', source: 'assessment' },
    { key: 'pitching_score', label: 'Pitching Score', unit: '/100', source: 'assessment' },
    { key: 'overall_score', label: 'Overall Score', unit: '/100', source: 'assessment' },
  ] },
]

export const getPath = (obj, path) => path.split('.').reduce((acc, key) => (acc && typeof acc === 'object' ? acc[key] : undefined), obj)

// rows: source='fitness' -> PlayerFitness rows (fitness_date); source='assessment' -> PlayerAssessment rows (assessment_date)
export const buildItemRows = (item, { assessmentHistory, fitnessHistory }) => {
  const rows = item.source === 'fitness' ? fitnessHistory : assessmentHistory
  const dateField = item.source === 'fitness' ? 'fitness_date' : 'assessment_date'
  return (rows || [])
    .map((row) => ({ date: row[dateField], value: getPath(row, item.key) }))
    .filter((r) => r.date && r.value !== null && r.value !== undefined && r.value !== '')
    .sort((a, b) => new Date(b.date) - new Date(a.date))
}
