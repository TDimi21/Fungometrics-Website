const numberOrNull = (value) => {
  if (value === null || value === undefined || value === '') return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

const textOrNull = (value) => {
  const text = String(value ?? '').trim()
  return text || null
}

const titleize = (value) => textOrNull(value)
  ?.replace(/[_-]+/g, ' ')
  .replace(/\s+/g, ' ')
  .replace(/\b\w/g, (letter) => letter.toUpperCase()) || null

const clamp = (value) => Math.max(0, Math.min(100, Number(value)))

const statusForScore = (score, backendLabel = null) => {
  if (textOrNull(backendLabel)) return titleize(backendLabel)
  if (numberOrNull(score) === null) return 'Needs Data'
  if (score >= 90) return 'Elite'
  if (score >= 75) return 'Above Average'
  if (score >= 50) return 'Average'
  if (score >= 25) return 'Below Average'
  return 'Needs Development'
}

const metricAliases = {
  max_exit_velocity: ['max_exit_velocity', 'max_ev', 'exit_velocity_max'],
  avg_exit_velocity: ['avg_exit_velocity', 'average_exit_velocity', 'exit_velocity_avg'],
  max_fb_velocity: ['max_fb_velocity', 'max_pitch_velocity', 'bullpen_max_velocity'],
  avg_fb_velocity: ['avg_fb_velocity', 'avg_pitch_velocity', 'bullpen_avg_velocity'],
  bullpen_score: ['bullpen_score'],
  bp_score: ['bp_score', 'batting_practice_score'],
  body_weight: ['body_weight', 'bodyweight'],
  front_squat: ['front_squat', 'squat'],
  bench_press: ['bench_press'],
  dead_lift: ['dead_lift', 'deadlift'],
  trap_bar_deadlift: ['trap_bar_deadlift'],
  back_squat: ['back_squat', 'squat'],
  power_clean: ['power_clean'],
  hand_strength: ['grip_strength_average', 'hand_strength', 'grip_strength'],
  grip_strength_left: ['grip_strength_left'], grip_strength_right: ['grip_strength_right'],
  pull_ups: ['pull_ups'], pushups: ['pushups', 'push_ups'], plank_hold: ['plank_hold'],
  sprint_10yd: ['sprint_10yd'], forty_yard_dash: ['forty_yard_dash', 'yd_40_dash'], sixty_yard_dash: ['sixty_yard_dash', 'yd_60_dash'],
  vertical_jump: ['vertical_jump', 'vertical_jump_inches'],
  broad_jump: ['broad_jump', 'broad_jump_inches'],
  med_ball_rotational_throw: ['med_ball_rotational_throw', 'rotational_med_ball_throw'],
  bat_speed: ['bat_speed'],
  sleep_hours: ['sleep_hours'],
  recovery_score: ['recovery_score'],
  mobility_score: ['mobility_score'],
  strength_score: ['strength_score'],
}

const valueUnits = {
  max_exit_velocity: 'mph', avg_exit_velocity: 'mph', max_fb_velocity: 'mph', avg_fb_velocity: 'mph',
  body_weight: 'lb', front_squat: 'lb', bench_press: 'lb', dead_lift: 'lb', back_squat: 'lb',
  power_clean: 'lb', hand_strength: 'lb', vertical_jump: 'in', broad_jump: 'in',
  trap_bar_deadlift: 'lb', grip_strength_left: 'lb', grip_strength_right: 'lb',
  pull_ups: 'reps', pushups: 'reps', plank_hold: 'sec', sprint_10yd: 'sec', forty_yard_dash: 'sec', sixty_yard_dash: 'sec',
  med_ball_rotational_throw: 'ft', bat_speed: 'mph', sleep_hours: 'hrs',
}

const metricLabels = {
  max_exit_velocity: 'Max Exit Velocity', avg_exit_velocity: 'Average Exit Velocity',
  max_fb_velocity: 'Max Fastball Velocity', avg_fb_velocity: 'Average Fastball Velocity',
  bullpen_score: 'Bullpen Score', bp_score: 'Batting Practice Score', body_weight: 'Body Weight',
  front_squat: 'Front Squat', bench_press: 'Bench Press', dead_lift: 'Deadlift', back_squat: 'Back Squat',
  power_clean: 'Power Clean', hand_strength: 'Hand Strength', vertical_jump: 'Vertical Jump',
  trap_bar_deadlift: 'Trap-bar Deadlift', grip_strength_left: 'Grip Strength Left', grip_strength_right: 'Grip Strength Right',
  pull_ups: 'Pull-ups', pushups: 'Push-ups', plank_hold: 'Plank Hold', sprint_10yd: '10 Yard Sprint', forty_yard_dash: '40 Yard Dash', sixty_yard_dash: '60 Yard Dash',
  broad_jump: 'Broad Jump', med_ball_rotational_throw: 'Med Ball Rotational Throw', bat_speed: 'Bat Speed',
  sleep_hours: 'Sleep Average', recovery_score: 'Recovery Score', mobility_score: 'Mobility Score',
  strength_score: 'Strength Score', hard_contact_percentage: 'Hard-Hit Percentage', launch_angle: 'Launch Angle',
}

const findBenchmark = (metrics, key) => {
  const aliases = metricAliases[key] || [key]
  return metrics.find((metric) => aliases.includes(String(metric?.metric_key || '').toLowerCase())) || null
}

const savedAssessmentBenchmark = (intelligence, key, value) => {
  const assessment = intelligence?.summary?.assessment || {}
  const physical = intelligence?.summary?.physical || {}
  const storedKey = {
    front_squat: 'squat', back_squat: 'squat', dead_lift: 'deadlift',
    max_exit_velocity: 'exit_velocity', avg_exit_velocity: 'exit_velocity',
  }[key] || key
  const percentile = numberOrNull(assessment?.metric_percentiles?.[storedKey])
  if (percentile === null) return null

  const savedRaw = numberOrNull({
    squat: assessment.squat ?? physical.squat,
    deadlift: assessment.deadlift ?? physical.deadlift,
    bench_press: assessment.bench_press ?? physical.bench_press,
    broad_jump: assessment.broad_jump ?? physical.broad_jump,
    vertical_jump: assessment.vertical_jump ?? physical.vertical_jump,
    med_ball_rotational_throw: physical.med_ball_rotational_throw,
    exit_velocity: assessment.baseline_exit_velocity ?? physical.exit_velocity,
    bat_speed: physical.bat_speed,
  }[storedKey])

  // A stored squat or exit-velocity percentile describes one saved raw
  // measurement. Do not attach it to a different current variant merely
  // because the fields share a family name.
  if (savedRaw !== null && value !== null && Math.abs(savedRaw - value) > 0.11) return null

  return {
    metric_key: storedKey,
    raw_value: savedRaw ?? value,
    percentile,
    score_0_100: percentile,
    label: null,
    source: 'saved_assessment_percentile',
    confidence: 'saved',
    unit: valueUnits[key] || null,
    calculated_at: assessment.assessment_date || intelligence?.generated_at || null,
  }
}

const displayValue = (value, unit = '') => {
  const number = numberOrNull(value)
  if (number === null) return null
  const rounded = Number.isInteger(number) ? number : Math.round(number * 10) / 10
  return `${rounded}${unit ? ` ${unit}` : ''}`
}

const trendDirection = (live, intelligence, key) => {
  const aliases = metricAliases[key] || [key]
  const blocks = intelligence?.trend_blocks || {}
  const block = aliases.map((alias) => blocks?.[alias]).find(Boolean)
  if (block?.direction) return String(block.direction).toLowerCase()

  const change = aliases.map((alias) => live?.growth?.from_previous?.[alias]).find(Boolean)
  return String(change?.direction || change?.trend || 'needs_data').toLowerCase()
}

const currentValue = (current, key) => {
  const aliases = metricAliases[key] || [key]
  for (const alias of aliases) {
    const value = numberOrNull(current?.[alias])
    if (value !== null) return value
  }
  return null
}

const metricRow = (live, intelligence, key, overrides = {}) => {
  const current = live?.current || {}
  const metrics = Array.isArray(intelligence?.benchmark_profile?.metrics)
    ? intelligence.benchmark_profile.metrics
    : []
  const currentRaw = currentValue(current, key)
  let benchmark = findBenchmark(metrics, key)
  if (
    benchmark?.metric_key === 'squat'
    && currentRaw !== null
    && numberOrNull(benchmark?.raw_value) !== null
    && Math.abs(Number(benchmark.raw_value) - currentRaw) > 0.11
  ) {
    benchmark = null
  }
  const value = currentRaw ?? numberOrNull(benchmark?.raw_value)
  benchmark = numberOrNull(benchmark?.percentile) !== null
    ? benchmark
    : (savedAssessmentBenchmark(intelligence, key, value) || benchmark)
  const percentile = numberOrNull(benchmark?.percentile)
  const goal = numberOrNull(benchmark?.goal ?? benchmark?.goal_value)
  const gap = numberOrNull(benchmark?.gap ?? benchmark?.gap_to_goal)
  const unit = textOrNull(benchmark?.unit) || valueUnits[key] || ''
  const relative = numberOrNull(benchmark?.relative_value)

  return {
    key,
    label: overrides.label || textOrNull(benchmark?.display_name) || metricLabels[key] || titleize(key),
    category: overrides.category || textOrNull(benchmark?.category) || 'other',
    percentile: percentile === null ? null : clamp(percentile),
    value,
    display_value: displayValue(value, unit),
    relative_display: relative === null ? null : `${Math.round(relative * 100) / 100}× BW`,
    status: percentile === null ? (value === null ? 'needs_data' : 'benchmark_unavailable') : String(benchmark?.label || 'benchmark_available').toLowerCase(),
    status_label: percentile === null
      ? (value === null ? 'Measurement Needs Data' : (benchmark ? 'Benchmark Needs Context' : 'Benchmark Not Configured'))
      : statusForScore(percentile, benchmark?.label),
    goal_display: displayValue(goal, unit),
    gap_display: displayValue(gap, unit),
    trend: trendDirection(live, intelligence, key),
    available: percentile !== null,
    source: textOrNull(benchmark?.source),
    confidence: textOrNull(benchmark?.confidence),
    calculated_at: textOrNull(benchmark?.calculated_at) || textOrNull(intelligence?.generated_at),
    evidence: benchmark?.evidence || null,
    peer_group: Array.isArray(benchmark?.peer_group) ? benchmark.peer_group.filter(Boolean).join(' · ') : null,
  }
}

const percentileGroups = (live, intelligence) => {
  const keys = [
    ['hitting', 'Hitting', ['max_exit_velocity', 'avg_exit_velocity', 'bp_score', 'bat_speed']],
    ['pitching', 'Pitching', ['max_fb_velocity', 'avg_fb_velocity', 'bullpen_score']],
    ['strength', 'Strength / Body', ['body_weight', 'front_squat', 'back_squat', 'bench_press', 'dead_lift', 'trap_bar_deadlift', 'power_clean', 'pull_ups', 'pushups', 'plank_hold', 'grip_strength_left', 'grip_strength_right']],
    ['athletic', 'Athletic / Mobility', ['vertical_jump', 'broad_jump', 'med_ball_rotational_throw', 'sprint_10yd', 'forty_yard_dash', 'sixty_yard_dash', 'mobility_score']],
    ['recovery', 'Recovery', ['sleep_hours', 'recovery_score']],
  ]

  return keys.map(([key, label, rows]) => ({
    key,
    label,
    metrics: rows.map((metric) => metricRow(live, intelligence, metric, { category: key })),
  })).filter((group) => group.metrics.some((metric) => metric.value !== null || metric.available))
}

const scoreDefinition = (key, label, score, source, summary, improve, focus, backendLabel = null) => ({
  key,
  label,
  score: numberOrNull(score),
  status: statusForScore(numberOrNull(score), backendLabel),
  summary,
  improve,
  focus,
  source,
  available: numberOrNull(score) !== null,
})

const developmentScores = (live, intelligence) => {
  const scores = live?.scores || {}
  const intelligenceScores = intelligence?.scores || {}
  const current = live?.current || {}
  const pdi = numberOrNull(scores.current_development_score)
  const performance = numberOrNull(scores.performance_score)
  const strength = numberOrNull(scores.strength_score ?? intelligenceScores.strength)
  const mobility = numberOrNull(scores.mobility_score ?? intelligenceScores.mobility)
  const recovery = numberOrNull(scores.recovery_score ?? intelligenceScores.recovery)
  const trend = numberOrNull(scores.trend_score)

  return [
    scoreDefinition('pdi', 'Player Development Index', pdi, 'development-dashboard', 'Overall governed development score from available categories.', 'Complete missing category baselines to improve confidence.', 'Use the highest-priority coach action.'),
    scoreDefinition('performance', 'Performance', performance, 'live-sessions', 'Current baseball performance across applicable session data.', 'Repeat comparable sessions to build a reliable baseline.', `FB ${displayValue(currentValue(current, 'avg_fb_velocity'), 'mph') || 'Needs Data'} · EV ${displayValue(currentValue(current, 'avg_exit_velocity'), 'mph') || 'Needs Data'}`),
    scoreDefinition('strength', 'Strength', strength, 'athletic-assessment', 'Current strength assessment score.', 'Complete or repeat the governed strength assessment.', 'Build force while monitoring transfer to baseball outputs.'),
    scoreDefinition('mobility', 'Mobility', mobility, current?.mobility_score_source || 'assessment', 'Current movement-quality assessment score.', 'Complete or repeat the governed mobility assessment.', 'Use movement screening to guide preparation.'),
    scoreDefinition('recovery', 'Recovery', recovery, 'fitness-check-in', 'Current readiness and recovery score.', 'Log sleep and recovery check-ins consistently.', `Sleep ${displayValue(currentValue(current, 'sleep_hours'), 'hrs') || 'Needs Data'}`),
    scoreDefinition('trend', 'Trend', trend, 'live-history', 'Direction across comparable recent measurement windows.', 'Add another comparable session to increase trend confidence.', 'Keep workload and measurement context consistent.'),
  ]
}

const recommendations = (intelligence) => {
  const rows = Array.isArray(intelligence?.recommendations) ? intelligence.recommendations : []
  return rows.slice(0, 3).map((item, index) => ({
    id: textOrNull(item?.id) || `priority-${index + 1}`,
    rank: index + 1,
    priority: textOrNull(item?.priority) || 'medium',
    title: textOrNull(item?.title) || 'Collect More Data',
    description: textOrNull(item?.why) || 'More connected data is needed to sharpen this priority.',
    why: textOrNull(item?.why),
    action: textOrNull(item?.action),
    expected_gain: textOrNull(item?.expected_gain),
    category: titleize(item?.category) || 'Development',
    confidence: textOrNull(item?.confidence) || 'low',
  }))
}

const baselineRows = (live) => {
  const role = String(live?.player?.role || 'two-way').toLowerCase()
  const current = live?.current || {}
  const candidates = [
    ['avg_fb_velocity', 'Average Fastball Velocity', 'mph', role !== 'hitter'],
    ['max_fb_velocity', 'Top Fastball Velocity', 'mph', role !== 'hitter'],
    ['avg_exit_velocity', 'Average Exit Velocity', 'mph', role !== 'pitcher'],
    ['hard_contact_percentage', 'Hard-Hit Percentage', '%', role !== 'pitcher'],
    ['launch_angle', 'Launch Angle', '°', role !== 'pitcher'],
    ['bat_speed', 'Bat Speed', 'mph', role !== 'pitcher'],
    ['strength_score', 'Strength Score', '', true],
  ]

  return candidates.filter(([, , , applicable]) => applicable).map(([key, label, unit]) => ({
    key, label, value: currentValue(current, key), display_value: displayValue(currentValue(current, key), unit),
  }))
}

const quickMetrics = (live, intelligence) => [
  metricRow(live, intelligence, 'avg_exit_velocity', { label: 'Exit Velocity' }),
  metricRow(live, intelligence, 'avg_fb_velocity', { label: 'Fastball Velocity' }),
  metricRow(live, intelligence, 'bullpen_score', { label: 'Bullpen Score' }),
  {
    key: 'trend', label: 'Trend', display_value: statusForScore(live?.scores?.trend_score),
    percentile: null, available: numberOrNull(live?.scores?.trend_score) !== null,
    trend: numberOrNull(live?.scores?.trend_score) === null ? 'needs_data' : (live.scores.trend_score >= 50 ? 'up' : 'down'),
  },
]

const supportMetric = (label, value, description = '') => ({
  label,
  value: numberOrNull(value),
  display_value: displayValue(value),
  description,
  available: numberOrNull(value) !== null,
})

const mobilityAssessmentMetric = (label, value) => {
  const score = numberOrNull(value)
  const rounded = score === null ? null : Math.round(score * 10) / 10

  return {
    label,
    value: score,
    bar_value: score === null ? null : clamp(score * 20),
    display_value: rounded === null ? null : `${rounded}/5`,
    description: 'Latest saved player assessment score.',
    available: score !== null,
  }
}

export function buildPlayerDevelopmentDashboard(livePayload = {}, intelligencePayload = {}, options = {}) {
  const live = livePayload || {}
  const intelligence = intelligencePayload || {}
  const player = live.player || intelligence?.summary?.player || {}
  const priorities = recommendations(intelligence)
  const athletic = live.athletic_performance || {}
  const assessment = intelligence?.summary?.assessment || {}
  const correlation = intelligence?.correlation_insights || intelligence?.observed_associations || null

  return {
    player: {
      ...player,
      name: textOrNull(player.name) || textOrNull(player.full_name) || 'Player',
      team: textOrNull(player.team_name) || textOrNull(intelligence?.summary?.team?.name),
      role: titleize(player.role) || 'Needs Data',
      level: titleize(player.level) || 'Needs Data',
    },
    quickMetrics: quickMetrics(live, intelligence),
    baseline: baselineRows(live),
    goals: percentileGroups(live, intelligence).flatMap((group) => group.metrics)
      .filter((metric) => metric.goal_display)
      .slice(0, 5),
    priorities,
    scores: developmentScores(live, intelligence),
    percentileGroups: percentileGroups(live, intelligence),
    comparison: {
      context: intelligence?.benchmark_profile?.comparison_context || {},
      bucket: textOrNull(intelligence?.benchmark_profile?.comparison_bucket_key),
      confidence: intelligence?.benchmark_profile?.benchmark_confidence || null,
      generatedAt: textOrNull(intelligence?.generated_at),
      sourceMix: intelligence?.benchmark_profile?.source_mix || null,
    },
    actions: priorities,
    coachNotes: textOrNull(live.coach_notes),
    correlation: correlation ? {
      positive: correlation.positive || null,
      negative: correlation.negative || null,
      confidence: textOrNull(correlation.confidence) || 'low',
      available: Boolean(correlation.positive || correlation.negative),
    } : { positive: null, negative: null, confidence: null, available: false },
    recovery: {
      sleep: supportMetric('Sleep Average', live?.current?.sleep_hours, 'Average logged sleep in the selected window.'),
      score: supportMetric('Recovery Score', live?.scores?.recovery_score, 'Readiness from available recovery check-ins.'),
      message: numberOrNull(live?.scores?.recovery_score) === null ? 'Log recovery and sleep to establish readiness.' : 'Use recovery with training load; it is not a medical diagnosis.',
    },
    strength: [
      supportMetric('Lower Body', athletic.lower_body_strength_score, 'Governed lower-body assessment score.'),
      supportMetric('Explosive Power', athletic.power_score, 'Governed explosive-power assessment score.'),
      supportMetric('Power Band', athletic.relative_strength_score, 'Strength relative to the current assessment context.'),
    ],
    mobility: [
      mobilityAssessmentMetric('Shoulder Horizontal Range', assessment.shoulder_mobility_score ?? assessment.shoulder_mobility ?? assessment.shoulder_horizontal_range ?? assessment.shoulder_hr),
      mobilityAssessmentMetric('Ankle Dorsiflexion', assessment.ankle_mobility_score ?? assessment.ankle_mobility ?? assessment.ankle_dorsiflexion),
      mobilityAssessmentMetric('T-Spine', assessment.t_spine_mobility_score ?? assessment.rotational_mobility ?? assessment.t_spine ?? assessment.thoracic_rotation),
    ],
    dataQuality: {
      liveGaps: live.data_gaps || {},
      intelligenceGaps: Array.isArray(intelligence.data_gaps) ? intelligence.data_gaps : [],
      partial: Boolean(Object.values(live.data_gaps || {}).some(Boolean) || (intelligence.data_gaps || []).length),
    },
    permissions: {
      canAddToPlanner: Boolean(options.canAddToPlanner),
      readOnly: Boolean(options.readOnly),
    },
  }
}

export { clamp, displayValue, numberOrNull, statusForScore }
