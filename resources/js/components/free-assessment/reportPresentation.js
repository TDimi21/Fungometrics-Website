export const categories = [
  { name: 'Hitting', symbol: '↗', keys: ['exit_velocity'] },
  { name: 'Pitching', symbol: '◉', keys: ['pitching_velocity'] },
  { name: 'Speed', symbol: '◷', keys: ['sprint_10yd'] },
  { name: 'Agility', symbol: '⇄', keys: ['shuttle_5_10_5'] },
  { name: 'Explosiveness', symbol: '↟', keys: ['broad_jump'] },
  { name: 'Strength', symbol: '↔', keys: ['grip_strength', 'pull_strength', 'pushups', 'pull_ups'] },
]
export const stationOrder = ['exit_velocity', 'pitching_velocity', 'sprint_10yd', 'shuttle_5_10_5', 'grip_strength', 'pull_strength', 'broad_jump', 'pushups', 'pull_ups']
export const ordinal = value => {
  if (value === null || value === undefined || value === '') return '—'
  const n = Math.round(Number(value)), mod = n % 100
  return `${n}${mod >= 11 && mod <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] || 'th')}`
}
export const categoryTiles = report => categories.map(category => {
  const rows = category.keys.map(key => report.results.find(r => r.station === key)).filter(Boolean)
  const result = rows.find(r => r.peer_percentile !== null && r.peer_percentile !== undefined) || rows[0]
  return { ...category, result, percentile: result?.peer_percentile ?? null }
})
export const historicalComparison = (report, station) => {
  const current = report.results.find(r => r.station === station)
  if (!current) return null
  for (const event of report.history || []) {
    const previous = event.results.find(r => r.station === station && (!['pitching_velocity', 'pull_strength'].includes(station) || r.protocol === current.protocol))
    if (!previous) continue
    // Grip comparisons follow the same left-hand basis as report rankings.
    const currentValue = station === 'grip_strength' ? current.summary.left.best : current.summary.best
    const previousValue = station === 'grip_strength' ? previous.summary.left.best : previous.summary.best
    if (currentValue == null || previousValue == null) continue
    const change = Math.round((currentValue - previousValue) * 1000) / 1000
    return { date: event.assessment_date, previous: previousValue, current: currentValue, change, improved: change === 0 ? null : report.stations[station].lower ? change < 0 : change > 0 }
  }
  return null
}
