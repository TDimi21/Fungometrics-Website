const PROJECTABLE = {
  bench_press: { maxChangeRatio: 0.08 },
  front_squat: { maxChangeRatio: 0.08 },
  back_squat: { maxChangeRatio: 0.08 },
  dead_lift: { maxChangeRatio: 0.08 },
  trap_bar_deadlift: { maxChangeRatio: 0.08 },
  power_clean: { maxChangeRatio: 0.08 },
  pull_ups: { maxChangeRatio: 0.15 },
  push_ups: { maxChangeRatio: 0.15 },
  plank_hold: { maxChangeRatio: 0.15 },
  grip_strength_left: { maxChangeRatio: 0.08 },
  grip_strength_right: { maxChangeRatio: 0.08 },
  vertical_jump: { maxChangeRatio: 0.08 },
  broad_jump: { maxChangeRatio: 0.08 },
  med_ball_rotational_throw: { maxChangeRatio: 0.08 },
  sprint_10yd: { maxChangeRatio: 0.04 },
  yd_40_dash: { maxChangeRatio: 0.04 },
  yd_60_dash: { maxChangeRatio: 0.04 },
  average_hitting_velocity: { maxChangeRatio: 0.05 },
  average_pitching_velocity: { maxChangeRatio: 0.05 },
  'hitting_data.max_exit_velo': { maxChangeRatio: 0.05 },
}

const TARGET_RANGE_KEYS = new Set([
  'body_weight', 'sleep_hours', 'sleep_quality_1_to_5', 'recovery_score', 'mobility_score',
  'shoulder_mobility', 'hip_mobility', 'ankle_mobility', 'hip_flexor_mobility', 'rotational_mobility',
])

export const trendBehaviorFor = (metric) => {
  const key = metric?.key || ''
  const projectable = PROJECTABLE[key]
  const targetRange = TARGET_RANGE_KEYS.has(key) || metric?.category === 'Recovery' || metric?.category === 'Mobility'

  return {
    trendMethod: key === 'body_weight' ? 'rolling_median' : targetRange ? 'rolling_average' : 'robust_regression',
    direction: targetRange ? 'target_range' : metric?.lowerBetter ? 'lower' : 'higher',
    projection: Boolean(projectable),
    projectionReason: projectable
      ? null
      : key === 'body_weight'
        ? 'Body weight is monitored against goals and rate of change.'
        : 'This metric is shown as a historical trend only.',
    projectionDays: 30,
    minTrendPoints: 3,
    minProjectionPoints: 4,
    minProjectionSpanDays: 28,
    maxChangeRatio: projectable?.maxChangeRatio ?? 0,
  }
}
