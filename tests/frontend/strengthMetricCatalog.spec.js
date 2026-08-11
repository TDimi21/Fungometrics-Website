import { describe, expect, it } from 'vitest'
import { positiveMetricNumber } from '../../resources/js/features/development/lib/strengthMetricCatalog.js'

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
})
