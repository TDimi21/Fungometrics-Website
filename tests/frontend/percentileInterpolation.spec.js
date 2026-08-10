import { describe, expect, it } from 'vitest'
import { estimatePercentileFromAnchors } from '../../resources/js/features/development/lib/percentileInterpolation.js'

// Anchors below are the real 15U_16U bench_press ladder returned by
// /api/player/intelligence (verified against ResearchPercentileEngine
// directly): p1:80 p5:95 p10:110 p25:125 p50:155 p75:185 p90:205 p95:225 p99:245
const BENCH_PRESS_ANCHORS_15U_16U = { p1: 80, p5: 95, p10: 110, p25: 125, p50: 155, p75: 185, p90: 205, p95: 225, p99: 245 }

describe('estimatePercentileFromAnchors', () => {
  it('matches the backend exactly at an anchor point', () => {
    expect(estimatePercentileFromAnchors(225, BENCH_PRESS_ANCHORS_15U_16U, true)).toBe(95)
    expect(estimatePercentileFromAnchors(155, BENCH_PRESS_ANCHORS_15U_16U, true)).toBe(50)
  })

  it('interpolates linearly between two anchors', () => {
    // Halfway between p50 (155) and p75 (185) in value should land at the
    // percentile midpoint (50 + (75-50)/2 = 62.5 -> rounds to 63).
    expect(estimatePercentileFromAnchors(170, BENCH_PRESS_ANCHORS_15U_16U, true)).toBe(63)
  })

  it('clamps below the lowest anchor to its percentile, not below it', () => {
    expect(estimatePercentileFromAnchors(40, BENCH_PRESS_ANCHORS_15U_16U, true)).toBe(1)
  })

  it('clamps above the highest anchor to its percentile, not above it', () => {
    expect(estimatePercentileFromAnchors(400, BENCH_PRESS_ANCHORS_15U_16U, true)).toBe(99)
  })

  it('flips direction correctly for lower-is-better metrics (e.g. sprint times)', () => {
    // Faster (lower) sprint time should score a HIGHER percentile.
    const sprintAnchors = { p10: 5.3, p50: 4.9, p90: 4.5 }
    const fast = estimatePercentileFromAnchors(4.5, sprintAnchors, false)
    const slow = estimatePercentileFromAnchors(5.3, sprintAnchors, false)
    expect(fast).toBe(90)
    expect(slow).toBe(10)
  })

  it('returns null when anchors or value are missing', () => {
    expect(estimatePercentileFromAnchors(225, null, true)).toBeNull()
    expect(estimatePercentileFromAnchors(null, BENCH_PRESS_ANCHORS_15U_16U, true)).toBeNull()
    expect(estimatePercentileFromAnchors(NaN, BENCH_PRESS_ANCHORS_15U_16U, true)).toBeNull()
  })
})
