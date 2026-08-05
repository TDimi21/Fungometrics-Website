import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { buildPlayerDevelopmentDashboard, clamp } from '../../resources/js/features/development/lib/playerDevelopmentDashboardAdapter.js'

const root = path.resolve(__dirname, '../..')
const source = (relative) => fs.readFileSync(path.join(root, relative), 'utf8')
const page = source('resources/js/features/development/pages/PlayerDevelopmentDashboard.vue')
const identity = source('resources/js/features/development/components/PlayerIdentityCard.vue')
const percentile = source('resources/js/features/development/components/PercentileMetricRow.vue')
const percentilePanel = source('resources/js/features/development/components/PercentileRankingsPanel.vue')
const actions = source('resources/js/features/development/components/CoachActionPlanCard.vue')
const correlation = source('resources/js/features/development/components/CorrelationInsightsCard.vue')

const live = {
  player: { id: 'p1', name: 'Test Player', role: 'two-way', level: 'high school' },
  current: { max_exit_velocity: 99, avg_exit_velocity: 83.3, avg_fb_velocity: 86.1, recovery_score: null },
  scores: { current_development_score: 64, performance_score: 71, strength_score: null, mobility_score: 52, recovery_score: null, trend_score: 68 },
  data_gaps: { recovery: true },
}
const intelligence = {
  generated_at: '2026-08-05T12:00:00Z',
  recommendations: [{ id: 'one', title: 'Use Current Plan', why: 'Observed limiter', action: 'Run the governed drill', category: 'hitting', confidence: 'medium' }],
  benchmark_profile: {
    comparison_bucket_key: 'high-school-hitter',
    benchmark_confidence: { overall: 'medium' },
    metrics: [{ metric_key: 'max_exit_velocity', display_name: 'Max EV', category: 'hitting', raw_value: 99, unit: 'mph', percentile: 93, label: 'elite', confidence: 'high', source: 'composite' }],
  },
  summary: {
    assessment: {
      assessment_date: '2026-08-01',
      squat: 315,
      metric_percentiles: { squat: 82, bat_speed: 76 },
      shoulder_mobility_score: 4,
      ankle_mobility_score: 3,
      t_spine_mobility_score: 5,
    },
    physical: { front_squat: 315, back_squat: 275, bat_speed: 72 },
  },
}

describe('Player Development Dashboard redesign', () => {
  it('adapts live identity, quick metrics, baseline, scores, percentiles, and governed priorities', () => {
    const model = buildPlayerDevelopmentDashboard(live, intelligence, { canAddToPlanner: true })
    expect(model.player.name).toBe('Test Player')
    expect(model.quickMetrics.some((metric) => metric.key === 'avg_exit_velocity')).toBe(true)
    expect(model.baseline.some((metric) => metric.key === 'avg_fb_velocity')).toBe(true)
    expect(model.scores.map((score) => score.label)).toContain('Player Development Index')
    expect(model.percentileGroups.flatMap((group) => group.metrics).find((metric) => metric.key === 'max_exit_velocity').percentile).toBe(93)
    expect(model.priorities[0].title).toBe('Use Current Plan')
  })

  it('keeps missing data null and displays Needs Data instead of a fabricated zero', () => {
    const model = buildPlayerDevelopmentDashboard(live, intelligence)
    const strength = model.scores.find((score) => score.key === 'strength')
    const recovery = model.scores.find((score) => score.key === 'recovery')
    expect(strength.score).toBeNull()
    expect(strength.status).toBe('Needs Data')
    expect(recovery.score).toBeNull()
    expect(recovery.status).toBe('Needs Data')
  })

  it('maps the latest player assessment mobility categories onto the assessment five-point scale', () => {
    const model = buildPlayerDevelopmentDashboard(live, intelligence)
    expect(model.mobility.map((metric) => metric.display_value)).toEqual(['4/5', '3/5', '5/5'])
    expect(model.mobility.map((metric) => metric.bar_value)).toEqual([80, 60, 100])
    expect(model.mobility.every((metric) => metric.available)).toBe(true)
    expect(source('resources/js/features/development/components/MobilityAssessmentCard.vue')).toContain('metric.bar_value ?? metric.value ?? 0')
  })

  it('clamps percentile marker positions and provides dashed accessible missing states', () => {
    expect(clamp(-12)).toBe(0)
    expect(clamp(114)).toBe(100)
    const missing = buildPlayerDevelopmentDashboard(live, intelligence).percentileGroups
      .flatMap((group) => group.metrics)
      .find((metric) => metric.key === 'avg_exit_velocity')
    expect(missing.percentile).toBeNull()
    expect(missing.status_label).toBe('Benchmark Not Configured')
    expect(percentile).toContain('Math.max(0, Math.min(100')
    expect(percentile).toContain("{ dashed: !metric.available }")
    expect(percentile).toContain(':aria-label="aria"')
  })

  it('restores saved assessment percentiles without applying a shared benchmark to the wrong raw value', () => {
    const withStrength = {
      ...live,
      current: { ...live.current, front_squat: 315, back_squat: 275, bat_speed: 72 },
    }
    const rows = buildPlayerDevelopmentDashboard(withStrength, intelligence).percentileGroups.flatMap((group) => group.metrics)
    expect(rows.find((metric) => metric.key === 'front_squat').percentile).toBe(82)
    expect(rows.find((metric) => metric.key === 'front_squat').source).toBe('saved_assessment_percentile')
    expect(rows.find((metric) => metric.key === 'back_squat').percentile).toBeNull()
    expect(rows.find((metric) => metric.key === 'back_squat').status_label).toBe('Benchmark Not Configured')
    expect(rows.find((metric) => metric.key === 'bat_speed').percentile).toBe(76)
  })

  it('renders goal, gap, trend, status text, and grouped scale independently from color', () => {
    for (const field of ['metric.goal_display', 'metric.gap_display', 'metric.status_label', 'metric.trend']) expect(percentile).toContain(field)
    for (const tick of ['>0<', '>25<', '>50<', '>75<', '>100<']) expect(source('resources/js/features/development/components/PercentileScaleLegend.vue')).toContain(tick)
    expect(percentilePanel).toContain('PercentileCategorySection')
    expect(percentilePanel).toContain('font-size:16px')
    expect(percentile).toContain('.metric-name{font-size:12px')
  })

  it('renders strength v1 absolute relative peer confidence and descriptive body-weight states', () => {
    const strengthIntelligence = {
      ...intelligence,
      benchmark_profile: {
        ...intelligence.benchmark_profile,
        metrics: [
          ...intelligence.benchmark_profile.metrics,
          { metric_key: 'front_squat', display_name: 'Front Squat', category: 'maximum_strength', raw_value: 225, relative_value: 1.25, unit: 'lb', percentile: 72, label: 'Average', confidence: 'medium', source: 'composite', peer_group: ['15U_16U', '170_189', 'high_school'], goal: 240, gap: 15 },
          { metric_key: 'body_weight', display_name: 'Body Weight', category: 'body_context', raw_value: 180, unit: 'lb', percentile: 38, label: 'Descriptive', confidence: 'medium', source: 'fmtrx_population', peer_group: ['15U_16U', 'high_school'] },
        ],
      },
    }
    const rows = buildPlayerDevelopmentDashboard(live, strengthIntelligence).percentileGroups.flatMap((group) => group.metrics)
    const squat = rows.find((metric) => metric.key === 'front_squat')
    const weight = rows.find((metric) => metric.key === 'body_weight')
    expect(squat.display_value).toBe('225 lb')
    expect(squat.relative_display).toBe('1.25× BW')
    expect(squat.peer_group).toBe('15U_16U · 170_189 · high_school')
    expect(squat.confidence).toBe('medium')
    expect(squat.goal_display).toBe('240 lb')
    expect(squat.gap_display).toBe('15 lb')
    expect(weight.status_label).toBe('Descriptive')
    expect(percentile).toContain('relative value')
    expect(percentile).toContain('peer group')
    expect(percentile).toContain('confidence')
  })

  it('uses a fallback silhouette, protected coach actions, and read-only player mode', () => {
    expect(identity).toContain('player-photo-fallback')
    expect(actions).toContain('canAddToPlanner && !readOnly')
    expect(page).toContain(':read-only="dashboard.permissions.readOnly"')
    expect(page).toContain("access.canAccess('planner_create')")
    expect(page).toContain('canManageSubscriptions')
  })

  it('uses non-causal correlation language and supports all resilient page states', () => {
    expect(correlation).toContain('Observed associations')
    expect(correlation).toContain('do not establish causation')
    expect(correlation).toContain('Not enough connected data yet')
    for (const stateName of ['loading-state', 'error-state', 'partial-data-state', 'unauthorized-state', 'empty-player-state']) expect(page).toContain(stateName)
  })

  it('contains desktop, tablet, mobile, support-card, and print behavior', () => {
    expect(page).toContain('minmax(250px,.9fr) minmax(320px,1.15fr) minmax(620px,2.2fr)')
    expect(page).toContain('@media(max-width:1260px)')
    expect(page).toContain('@media(max-width:820px)')
    expect(page).toContain('overflow-x:hidden')
    for (const card of ['RecoverySleepCard', 'StrengthMetricsCard', 'MobilityAssessmentCard']) expect(page).toContain(card)
    expect(page).toContain('@media print')
    expect(page).toContain('print-dashboard')
  })
})
