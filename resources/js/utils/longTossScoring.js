export const isLongTossScoringRoute = to =>
  to.path === '/track/training-mode/LT' || (to.path === '/create/mode' && to.query?.mode === 'LT')

export function summarizeThrows(rows) {
  const distances = rows.map(row => Number(row.distance)).filter(n => Number.isFinite(n) && n > 0)
  return { count: rows.length, top: distances.length ? Math.max(...distances) : null,
    average: distances.length ? Math.round(distances.reduce((a, b) => a + b, 0) / distances.length * 10) / 10 : null }
}
export function playerThrows(rows, id) {
  return rows.filter(row => String(row.user_id ?? row.player_id) === String(id))
}
export function validDistance(value) {
  return /^\d+$/.test(String(value)) && Number.isSafeInteger(Number(value)) && Number(value) > 0 && Number(value) <= 2147483647
}
