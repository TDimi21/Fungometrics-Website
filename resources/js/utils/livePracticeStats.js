// Enrich newly saved API rows with the session roster, without changing stored results.
export function livePracticeRows(rows, players, mode) {
  const idKey = mode === 'P' ? 'pitcher_id' : 'batter_id'
  const roster = new Map(players.map(p => [String(p.id), p]))
  return rows.map(row => {
    const player = roster.get(String(row[idKey]))
    const profile = row.profile || {}
    return { ...row, profile: { ...profile,
      id: profile.id || row[idKey],
      first_name: profile.first_name || player?.name?.first || player?.name?.full || '',
      last_name: profile.last_name || player?.name?.last || '',
      picture: profile.picture || player?.picture || '',
    } }
  })
}
export function groupPracticeRows(rows, mode) {
  return rows.reduce((groups, row) => {
    const id = String(mode === 'P' ? row.pitcher_id : row.batter_id)
    ;(groups[id] ||= []).push(row)
    return groups
  }, {})
}
