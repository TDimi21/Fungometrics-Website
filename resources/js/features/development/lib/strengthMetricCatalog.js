import { ITEM_CATALOG } from './assessmentItemCatalog.js'

// Benchmark-backed fitness metrics. Assessment-only measurements are merged
// below so the Strength Center can chart the complete assessment catalog while
// keeping every stored metric key unique.
const BENCHMARK_METRICS = [
  { key: 'body_weight', label: 'Weight', unit: 'lb', lowerBetter: false, color: '#C00000', percentileKey: 'body_weight', category: 'Body' },
  { key: 'bench_press', label: 'Bench Press', unit: 'lb', lowerBetter: false, color: '#3b82f6', percentileKey: 'bench_press', category: 'Maximum Strength' },
  { key: 'front_squat', label: 'Front Squat', unit: 'lb', lowerBetter: false, color: '#f59e0b', percentileKey: 'front_squat', category: 'Maximum Strength' },
  { key: 'back_squat', label: 'Back Squat', unit: 'lb', lowerBetter: false, color: '#10b981', percentileKey: 'back_squat', category: 'Maximum Strength' },
  { key: 'dead_lift', label: 'Deadlift', unit: 'lb', lowerBetter: false, color: '#a855f7', percentileKey: 'deadlift', category: 'Maximum Strength' },
  { key: 'trap_bar_deadlift', label: 'Trap Bar Deadlift', unit: 'lb', lowerBetter: false, color: '#eab308', percentileKey: 'trap_bar_deadlift', category: 'Maximum Strength' },
  { key: 'power_clean', label: 'Power Clean', unit: 'lb', lowerBetter: false, color: '#06b6d4', percentileKey: 'power_clean', category: 'Explosive Strength' },
  { key: 'vertical_jump', label: 'Vertical Jump', unit: 'in', lowerBetter: false, color: '#84cc16', percentileKey: 'vertical_jump', category: 'Explosive Strength' },
  { key: 'broad_jump', label: 'Broad Jump', unit: 'in', lowerBetter: false, color: '#22c55e', percentileKey: 'broad_jump', category: 'Explosive Strength' },
  { key: 'med_ball_rotational_throw', label: 'Med Ball Rot Throw', unit: 'ft', lowerBetter: false, color: '#14b8a6', percentileKey: 'med_ball_rotational_throw', category: 'Explosive Strength' },
  { key: 'pull_ups', label: 'Pull Ups', unit: 'reps', lowerBetter: false, color: '#f97316', percentileKey: 'pull_ups', category: 'Strength Endurance' },
  { key: 'push_ups', label: 'Push Ups', unit: 'reps', lowerBetter: false, color: '#fb923c', percentileKey: 'pushups', category: 'Strength Endurance' },
  { key: 'plank_hold', label: 'Plank Hold', unit: 'sec', lowerBetter: false, color: '#fbbf24', percentileKey: 'plank_hold', category: 'Strength Endurance' },
  { key: 'grip_strength_left', label: 'Left Grip', unit: 'lb', lowerBetter: false, color: '#ec4899', percentileKey: 'grip_strength_left', category: 'Grip' },
  { key: 'grip_strength_right', label: 'Right Grip', unit: 'lb', lowerBetter: false, color: '#f472b6', percentileKey: 'grip_strength_right', category: 'Grip' },
  { key: 'sprint_10yd', label: '10 Yard Sprint', unit: 's', lowerBetter: true, color: '#8b5cf6', percentileKey: 'sprint_10yd', category: 'Speed' },
  { key: 'yd_40_dash', label: '40 Yard Dash', unit: 's', lowerBetter: true, color: '#a78bfa', percentileKey: 'forty_yard_dash', category: 'Speed' },
  { key: 'yd_60_dash', label: '60 Yard Dash', unit: 's', lowerBetter: true, color: '#c084fc', percentileKey: 'sixty_yard_dash', category: 'Speed' },
  { key: 'sleep_hours', label: 'Sleep Hours', unit: 'hrs', lowerBetter: false, color: '#22d3ee', percentileKey: 'sleep_hours', category: 'Recovery' },
  { key: 'recovery_score', label: 'Recovery Score', unit: '/100', lowerBetter: false, color: '#38bdf8', percentileKey: 'recovery_score', category: 'Recovery' },
  { key: 'mobility_score', label: 'Mobility Score', unit: '/100', lowerBetter: false, color: '#4ade80', percentileKey: 'mobility_score', category: 'Recovery' },
]

const ASSESSMENT_CATEGORY_COLORS = {
  Recovery: '#22d3ee',
  Mobility: '#4ade80',
  Hitting: '#f97316',
  Pitching: '#3b82f6',
  'Throwing / Arm Health': '#a855f7',
  Scores: '#f43f5e',
}

const benchmarkByKey = new Map(BENCHMARK_METRICS.map((metric) => [metric.key, metric]))
const assessmentMetrics = ITEM_CATALOG.flatMap((group) => group.items.map((item, index) => {
  const existing = benchmarkByKey.get(item.key)
  if (existing) return { ...existing, source: item.source }
  return {
    key: item.key,
    label: item.label,
    unit: item.unit.trim(),
    lowerBetter: false,
    color: ASSESSMENT_CATEGORY_COLORS[group.category] || ['#38bdf8', '#fb7185', '#c084fc', '#2dd4bf'][index % 4],
    percentileKey: item.key,
    category: group.category,
    source: item.source,
  }
}))

// Assessment items are authoritative for shared keys. The remaining benchmark
// metrics (for example 10 Yard Sprint and Mobility Score) are appended once.
export const METRICS = [
  ...assessmentMetrics,
  ...BENCHMARK_METRICS.filter((metric) => !assessmentMetrics.some((item) => item.key === metric.key)),
]

export const CATEGORY_ORDER = [
  'Body',
  'Maximum Strength',
  'Explosive Strength',
  'Strength Endurance',
  'Grip',
  'Speed',
  'Recovery',
  'Mobility',
  'Hitting',
  'Pitching',
  'Throwing / Arm Health',
  'Scores',
]

export const categorizeMetrics = () => CATEGORY_ORDER
  .map((label) => ({ label, metrics: METRICS.filter((m) => m.category === label) }))
  .filter((group) => group.metrics.length)

// A recorded physical metric must be positive. Legacy/imported zeroes are
// placeholders or bad measurements, never legitimate player results.
export const positiveMetricNumber = (value) => {
  const number = Number(value)
  return Number.isFinite(number) && number > 0 ? number : null
}

// Looks up a metric's governed benchmark entry from a benchmark_profile.metrics array.
export const benchmarkFor = (metricsArray, metric) => {
  if (!Array.isArray(metricsArray) || !metric) return null
  return metricsArray.find((m) => m.metric_key === metric.percentileKey) || null
}
