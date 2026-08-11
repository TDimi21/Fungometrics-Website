import { describe, expect, it } from 'vitest'
import { projectTrend } from '../../resources/js/features/development/lib/trendProjection.js'

const day = (value) => Date.UTC(2026, 0, value)

describe('Strength Center trend projections', () => {
  it('extends an improving recorded trend by three monthly points', () => {
    const projection = projectTrend([
      { x: day(1), y: 100 },
      { x: day(11), y: 110 },
      { x: day(21), y: 120 },
    ], { months: 3, from: day(21) })

    expect(projection.data).toHaveLength(4)
    expect(projection.data[0]).toEqual({ x: day(21), y: 120 })
    expect(projection.projectedValue).toBeGreaterThan(projection.data[1].y)
    expect(projection.sampleCount).toBe(3)
    expect(projection.rSquared).toBe(1)
  })

  it('keeps a flat trend flat and never invents a percentage scale', () => {
    const projection = projectTrend([
      { x: day(1), y: 185 },
      { x: day(11), y: 185 },
    ], { from: day(11) })

    expect(projection.projectedValue).toBe(185)
    expect(projection.data.slice(1).every((point) => point.y === 185)).toBe(true)
  })

  it('requires at least two distinct recorded dates', () => {
    expect(projectTrend([{ x: day(1), y: 100 }])).toBeNull()
    expect(projectTrend([{ x: day(1), y: 100 }, { x: day(1), y: 105 }])).toBeNull()
  })
})
