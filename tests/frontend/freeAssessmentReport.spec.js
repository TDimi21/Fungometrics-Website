import { describe, it, expect } from 'vitest'
import { categoryTiles, historicalComparison, ordinal } from '../../resources/js/components/free-assessment/reportPresentation'

describe('Free Assessment report presentation', () => {
  it('distinguishes missing percentile from a genuine zero and formats ordinals', () => {
    expect(ordinal(null)).toBe('—')
    expect(ordinal(undefined)).toBe('—')
    expect(ordinal(0)).toBe('0th')
    expect([1,2,3,11,12,13,21,82].map(ordinal)).toEqual(['1st','2nd','3rd','11th','12th','13th','21st','82nd'])
  })
  it('shows a named recorded test rather than inventing category scores', () => {
    const tiles = categoryTiles({ results: [{ station: 'pushups', peer_percentile: 0 }] })
    expect(tiles).toHaveLength(6)
    expect(tiles[0].percentile).toBeNull()
    expect(tiles[5].percentile).toBe(0)
    expect(tiles[5].result.station).toBe('pushups')
    expect(tiles[5].score).toBeUndefined()
  })
  it('recognizes faster times and preserves zero repetition baselines', () => {
    const report = { stations: { sprint_10yd: { lower: true }, pushups: {} }, results: [{ station: 'sprint_10yd', summary: { best: 1.8 } }, { station: 'pushups', summary: { best: 4 } }], history: [{ assessment_date: '2026-09-01', results: [{ station: 'sprint_10yd', summary: { best: 2 } }, { station: 'pushups', summary: { best: 0 } }] }] }
    expect(historicalComparison(report, 'sprint_10yd')).toMatchObject({ change: -0.2, improved: true })
    expect(historicalComparison(report, 'pushups')).toMatchObject({ previous: 0, change: 4, improved: true })
    expect(historicalComparison(report, 'pull_ups')).toBeNull()
  })
  it('skips incompatible pitching protocols in history', () => {
    const report = { stations: { pitching_velocity: {} }, results: [{ station: 'pitching_velocity', protocol: 'fastball', summary: { best: 80 } }], history: [{ assessment_date: '2026-09-01', results: [{ station: 'pitching_velocity', protocol: 'mixed', summary: { best: 60 } }] }, { assessment_date: '2026-08-01', results: [{ station: 'pitching_velocity', protocol: 'fastball', summary: { best: 75 } }] }] }
    expect(historicalComparison(report, 'pitching_velocity')).toMatchObject({ previous: 75, date: '2026-08-01', change: 5 })
  })
})
