import { describe, expect, it } from 'vitest'
import { extractSessionRows, normalizeSessionRows } from '../../resources/js/utils/sessionReportRows.js'

describe('session report row extraction', () => {
  const row = { id: 'row-1' }

  it.each([
    'batting',
    'bullpen',
    'cage',
    'exit_velocity',
    'long_toss',
    'weight_ball',
    'live_ab',
  ])('reads the canonical ball_x_ball payload for %s', type => {
    expect(extractSessionRows({ ball_x_ball: [row] }, type)).toEqual([row])
  })

  it('unwraps axios and API data envelopes', () => {
    expect(extractSessionRows({ data: { data: { ball_x_ball: [row] } } }, 'cage')).toEqual([row])
  })

  it('supports legacy type-specific wrappers and aliases', () => {
    expect(extractSessionRows({ bullpen: { pitches: [row] } }, 'bullpen')).toEqual([row])
    expect(extractSessionRows({ weight_balls: [row] }, 'weight_ball')).toEqual([row])
    expect(extractSessionRows({ throws: [row] }, 'long_toss')).toEqual([row])
  })

  it('normalizes keyed Laravel collections and JSON strings', () => {
    expect(normalizeSessionRows({ 4: row })).toEqual([row])
    expect(normalizeSessionRows(JSON.stringify([row]))).toEqual([row])
  })
})
