// Use participant identities, never the ID of a lineup/result row or session owner.
export function sessionParticipants(session = {}) {
  const found = new Map()
  for (const key of ['players', 'pitchers', 'batters', 'lineup', 'pitching_practice_lineups', 'practice_line_ups', 'results']) {
    for (const row of Array.isArray(session[key]) ? session[key] : []) {
      const player = row.player || row.pitcher || row.batter || row.user || row
      const id = row.player_id ?? row.pitcher_id ?? row.batter_id ?? row.user_id ?? (player !== row || ['players', 'pitchers', 'batters'].includes(key) ? player.id : null)
      if (id == null || id === '') continue
      const name = typeof player.name === 'string' ? player.name : player.name?.full
      const existing = found.get(String(id))
      found.set(String(id), {id: String(id), name: name || existing?.name || ''})
    }
  }
  return [...found.values()]
}
