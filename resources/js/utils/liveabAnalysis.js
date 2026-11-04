// Normalize the API relations without inventing locations for unlocated pitches.
export function liveabAnalysisBalls(rows = [], side = 'pitching') {
  return (Array.isArray(rows) ? rows : Object.values(rows || {})).map(row => {
    const p = row.pitching || {}, b = row.batting || {}
    const actor = side === 'pitching' ? p : b
    const id = side === 'pitching' ? p.pitcher_id : b.batter_id
    const name = `${actor.profile?.first_name || ''} ${actor.profile?.last_name || ''}`.trim() || 'Player'
    const rawPitch = String(p.type_throw || 'OTHER').toUpperCase().replace('CB', 'CV')
    const pitch = ['FB','CH','CV','SL'].includes(rawPitch) ? rawPitch : 'OTHER'
    const raw = String(p.trajectory || b.type_of_hit || '').toUpperCase()
    const trajectory = ({ F: 'FOUL', FLY: 'FB', FL: 'FB', TAKE: 'TK', 'SW/MISS': 'SM' })[raw] || raw
    return {...b, playerId: String(id ?? name), playerName: name, pitch,
      trajectory, type_of_hit: trajectory, pitch_mark: p.pitch_mark ?? b.pitch_mark,
      velocity: Number.isFinite(Number(side === 'pitching' ? p.miles_per_hour : b.velocity)) ? Number(side === 'pitching' ? p.miles_per_hour : b.velocity) : 0,
      field_mark: b.field_mark, strike: (p.zone ?? b.zone) === 'S',
      direction: String(b.field_direction || '').toUpperCase(),
    }
  })
}
export const pitchColors = { FB: '#D8232A', CH: '#2160C4', CV: '#16224C', SL: '#E6D08A', OTHER: '#5C6B8A' }
export const trajectoryColors = { FB: '#2160C4', PF: '#E6D08A', LD: '#D8232A', GB: '#16224C', SM: '#5C6B8A', FOUL: '#2E7D8C', TK: '#5C6B8A' }
export function resultColor(ball) {
  return ball.trajectory === 'FOUL' ? '#F1C40F' : ball.trajectory === 'SM' ? '#E74C3C' : ball.trajectory === 'TK' ? '#3498DB' : '#2ECC71'
}
export function analysisValue(balls, key, mode, side) {
  if (key === 'TOTAL') return balls.length
  const group = balls.filter(ball => (side === 'pitching' || mode === 'pitch' ? ball.pitch : ball.trajectory) === key)
  if (key === 'MAX') return balls.length && balls.some(b => b.velocity > 0) ? Math.max(...balls.map(b => b.velocity)).toFixed(1) : '—'
  if (mode === 'velocity') {
    const values = group.map(b => b.velocity).filter(v => v > 0)
    return values.length ? (values.reduce((a, b) => a + b, 0) / values.length).toFixed(1) : '—'
  }
  return mode === 'pitch' && side === 'pitching' ? `${balls.length ? (group.length / balls.length * 100).toFixed(1) : '0.0'}%` : group.length
}
