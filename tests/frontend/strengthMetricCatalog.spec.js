import { describe, expect, it } from 'vitest'
import { ITEM_CATALOG } from '../../resources/js/features/development/lib/assessmentItemCatalog.js'
import { categorizeMetrics, METRICS, positiveMetricNumber } from '../../resources/js/features/development/lib/strengthMetricCatalog.js'

describe('strength metric value validation', () => {
  it('treats recorded zeroes and invalid values as missing data', () => {
    expect(positiveMetricNumber(0)).toBeNull()
    expect(positiveMetricNumber('0')).toBeNull()
    expect(positiveMetricNumber(-1)).toBeNull()
    expect(positiveMetricNumber('')).toBeNull()
    expect(positiveMetricNumber(null)).toBeNull()
    expect(positiveMetricNumber('not-a-number')).toBeNull()
  })

  it('preserves positive recorded measurements', () => {
    expect(positiveMetricNumber('4.5')).toBe(4.5)
    expect(positiveMetricNumber(225)).toBe(225)
  })

  it('includes every assessment item exactly once in the Strength Center catalog', () => {
    const assessmentKeys = ITEM_CATALOG.flatMap((group) => group.items.map((item) => item.key))
    const metricKeys = METRICS.map((metric) => metric.key)
    const representedKeys = METRICS.flatMap((metric) => [metric.key, metric.sourceKey].filter(Boolean))

    expect(new Set(metricKeys).size).toBe(metricKeys.length)
    assessmentKeys.forEach((key) => expect(representedKeys).toContain(key))
  })

  it('places every metric in one selectable category', () => {
    const categorizedKeys = categorizeMetrics().flatMap((group) => group.metrics.map((metric) => metric.key))
    expect(categorizedKeys).toHaveLength(METRICS.length)
    expect(new Set(categorizedKeys).size).toBe(METRICS.length)
  })

  it('uses one cross-session daily metric for each average velocity concept', () => {
    const hitting = METRICS.find((metric) => metric.key === 'average_hitting_velocity')
    const pitching = METRICS.find((metric) => metric.key === 'average_pitching_velocity')

    expect(hitting).toMatchObject({ source: 'daily_velocity', percentileKey: 'average_exit_velocity', category: 'Hitting' })
    expect(pitching).toMatchObject({ source: 'daily_velocity', percentileKey: 'average_fastball_velocity', category: 'Pitching' })
    expect(METRICS.some((metric) => metric.key === 'hitting_data.avg_exit_velo')).toBe(false)
    expect(METRICS.some((metric) => metric.key === 'pitching_data.fastball_velocity')).toBe(false)
  })
})
