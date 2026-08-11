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

    expect(new Set(metricKeys).size).toBe(metricKeys.length)
    assessmentKeys.forEach((key) => expect(metricKeys).toContain(key))
  })

  it('places every metric in one selectable category', () => {
    const categorizedKeys = categorizeMetrics().flatMap((group) => group.metrics.map((metric) => metric.key))
    expect(categorizedKeys).toHaveLength(METRICS.length)
    expect(new Set(categorizedKeys).size).toBe(METRICS.length)
  })
})
