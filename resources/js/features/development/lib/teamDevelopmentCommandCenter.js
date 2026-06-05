export const DASHBOARD_METRICS = {
  pitching: [
    'strike_percentage',
    'top_pitch_velocity',
    'average_fastball_velocity',
    'pitcher_swing_miss_percentage',
    'bullpen_score',
    'long_toss_max_distance',
    'long_toss_carry_score',
  ],
  hitting: [
    'average_exit_velocity',
    'top_exit_velocity',
    'exit_velocity_growth',
    'hard_hit_percentage',
    'line_drive_percentage',
    'hitter_swing_miss_percentage',
    'damage_index',
  ],
  athletic: [
    'mobility_score',
    'strength_score',
    'long_toss_score',
    'weighted_ball_score',
    'athletic_performance_index',
  ],
}

const num = (v) => (Number.isFinite(Number(v)) ? Number(v) : null)
const clamp = (x, min = 0, max = 100) => Math.max(min, Math.min(max, x))

export const average = (arr = []) => {
  const vals = arr.map(num).filter((x) => x !== null)
  if (!vals.length) return null
  return vals.reduce((a, b) => a + b, 0) / vals.length
}

export const round1 = (x) => (Number.isFinite(Number(x)) ? Math.round(Number(x) * 10) / 10 : null)

export const computePDI = (components = []) => {
  const valid = components.filter((c) => num(c?.value) !== null && num(c?.weight) !== null)
  if (!valid.length) return null
  const top = valid.reduce((sum, c) => sum + (num(c.value) * num(c.weight)), 0)
  const weights = valid.reduce((sum, c) => sum + num(c.weight), 0)
  if (!weights) return null
  return round1(top / weights)
}

export const computeTDI = (playerPdis = []) => {
  return round1(average(playerPdis))
}

export const projectionCaps = {
  average_fastball_velocity: 5,
  top_pitch_velocity: 5,
  average_exit_velocity: 8,
  top_exit_velocity: 8,
  strike_percentage: 12,
  long_toss_max_distance: 60,
  pitcher_swing_miss_percentage: 10,
  hitter_swing_miss_percentage: 10,
}

export const projectMetric = ({ current, previous, metricKey, lowerBetter = false }) => {
  const cur = num(current)
  const prev = num(previous)
  if (cur === null) {
    return {
      current: null,
      previous: null,
      weightedTrend: 0,
      projected30: null,
      projected60: null,
      projected90: null,
    }
  }

  const weightedTrend = cur !== null && prev !== null ? (cur - prev) : 0
  const cap = projectionCaps[metricKey] ?? 8
  const minGain = lowerBetter ? -cap : -cap
  const maxGain = cap

  const p30 = clamp(cur + weightedTrend * 1.5, cur + (minGain * 0.4), cur + (maxGain * 0.4))
  const p60 = clamp(cur + weightedTrend * 2.5, cur + (minGain * 0.7), cur + (maxGain * 0.7))
  const p90 = clamp(cur + weightedTrend * 3.5, cur + minGain, cur + maxGain)

  return {
    current: round1(cur),
    previous: round1(prev),
    weightedTrend: round1(weightedTrend),
    projected30: round1(p30),
    projected60: round1(p60),
    projected90: round1(p90),
  }
}

export const computeRiskIndex = ({ pdiChange, status, mobility, bullpen, exitVelocity }) => {
  let risk = 0
  if ((num(mobility) ?? 100) < 65) risk += 20
  if ((num(bullpen) ?? 100) < 60) risk += 20
  if ((num(exitVelocity) ?? 100) < 60) risk += 10
  if ((num(pdiChange) ?? 0) < -3) risk += 20
  if (status === 'down') risk += 15
  if (status === 'needs_work') risk += 15
  return clamp(risk, 0, 100)
}

export const riskLevel = (risk) => {
  const value = num(risk) ?? 0
  if (value > 60) return 'Red'
  if (value > 40) return 'Orange'
  if (value > 20) return 'Yellow'
  return 'Green'
}

export const rankToPercentile = (rank, total) => {
  if (!rank || !total) return null
  return Math.round(((total - rank + 1) / total) * 100)
}
