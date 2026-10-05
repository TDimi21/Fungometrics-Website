import { describe, it, expect } from 'vitest'
import { nextIncomplete, draftKey, percentComplete, resultSummary } from '../../resources/js/pages/free-assessment/workflow'
describe('Free Assessment workflow', () => {
  const players = [{ id: 'a' }, { id: 'b' }, { id: 'c' }]
  it('advances within the selected station and wraps to unfinished players', () => {
    const results = [{ player_id: 'a', station: 'ev' }, { player_id: 'b', station: 'ev' }, { player_id: 'c', station: 'pushups' }]
    expect(nextIncomplete(players, results, 'ev', 'a')).toBe('c')
    expect(nextIncomplete(players, results, 'pushups', 'c')).toBe('a')
  })
  it('stays on the player when the station is finished', () => {
    expect(nextIncomplete(players, players.map(p => ({ player_id: p.id, station: 'ev' })), 'ev', 'b')).toBe('b')
    expect(nextIncomplete([], [], 'ev', null)).toBeNull()
  })
  it('isolates draft identities by assessment, player and station', () => {
    expect(new Set([draftKey('one','a','ev'), draftKey('two','a','ev'), draftKey('one','b','ev'), draftKey('one','a','grip')]).size).toBe(4)
  })
  it('handles empty progress and real zero results', () => {
    expect(percentComplete(0,0)).toBe(0)
    expect(percentComplete(22,28)).toBe(79)
    expect(resultSummary({ station: 'pushups', summary: { best: 0 } }, { pushups: { unit: 'reps', count: 1 } })).toBe('0 reps')
  })
})
