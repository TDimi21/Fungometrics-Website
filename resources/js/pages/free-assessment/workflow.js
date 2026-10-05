export const draftKey = (event, player, station) => `${event}:${player}:${station}`
export const nextIncomplete = (players, results, station, current) => {
  if (!players.length) return null
  const completed = new Set(results.filter(r => r.station === station).map(r => r.player_id))
  const start = Math.max(0, players.findIndex(p => p.id === current))
  for (let offset = 1; offset <= players.length; offset++) {
    const player = players[(start + offset) % players.length]
    if (!completed.has(player.id)) return player.id
  }
  return current
}
export const resultSummary = (result, definitions) => {
  if (!result) return 'Not recorded'
  const def = definitions[result.station]
  const s = result.summary
  if (result.station === 'grip_strength') return `L ${s.left.best} / R ${s.right.best} ${def.unit}`
  return `${def.count === 1 ? '' : def.lower ? 'Best ' : 'Max '}${s.best} ${def.unit}`
}
export const percentComplete = (completed, total) => total ? Math.round(completed / total * 100) : 0
