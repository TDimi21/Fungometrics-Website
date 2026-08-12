import { describe, expect, it } from 'vitest'
import { buildTrendModel } from '../../resources/js/features/development/lib/trendProjection.js'
import { trendBehaviorFor } from '../../resources/js/features/development/lib/trendMetricRegistry.js'

const day = (value) => Date.UTC(2026, 0, value)
const points = (values, spacing = 15) => values.map((y, index) => ({ x: day(1) + index * spacing * 86400000, y }))

describe('Strength Center trend engine', () => {
  it('keeps an outlier visible in actual data but out of the robust trend direction', () => {
    const model = buildTrendModel(points([185, 190, 195, 245, 200, 205]), trendBehaviorFor({ key: 'bench_press' }), { from: day(90) })
    expect(model.trend.data[3].y).toBeLessThan(220)
    expect(model.trend.direction).toBe('Improving')
  })

  it('never projects body weight', () => {
    const model = buildTrendModel(points([180, 182, 184, 186, 188, 190]), trendBehaviorFor({ key: 'body_weight' }))
    expect(model.trend).not.toBeNull()
    expect(model.projection).toBeNull()
    expect(model.reason).toContain('Body weight')
  })

  it('shows no trend with fewer than three dates', () => {
    const model = buildTrendModel(points([225, 230]), trendBehaviorFor({ key: 'bench_press' }))
    expect(model.trend).toBeNull()
    expect(model.projection).toBeNull()
  })

  it('creates a capped decaying 30-day outlook range only with adequate history', () => {
    const behavior = trendBehaviorFor({ key: 'bench_press' })
    const model = buildTrendModel(points([200, 204, 208, 212, 216, 220], 20), behavior, { from: day(110) })
    expect(model.projection.horizonDays).toBe(30)
    expect(model.projection.data).toHaveLength(4)
    expect(model.projection.outlookLow).toBeLessThan(model.projection.outlookHigh)
    expect(model.projection.projectedValue).toBeLessThanOrEqual(220 * 1.08)
    expect(['Moderate', 'Strong']).toContain(model.projection.confidence)
  })

  it('does not project a low-confidence or trend-only metric', () => {
    const lowHistory = buildTrendModel(points([200, 220, 205, 240]), trendBehaviorFor({ key: 'bench_press' }))
    const recovery = buildTrendModel(points([70, 72, 69, 74, 73, 75]), trendBehaviorFor({ key: 'recovery_score', category: 'Recovery' }))
    expect(lowHistory.projection).toBeNull()
    expect(recovery.projection).toBeNull()
  })
})
