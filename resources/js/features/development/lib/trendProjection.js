const DAY_MS = 86400000

const addUtcMonths = (timestamp, months) => {
  const date = new Date(timestamp)
  date.setUTCMonth(date.getUTCMonth() + months)
  return date.getTime()
}

// Least-squares projection using the recorded points already visible in the
// selected chart range. Returned data starts at the final real observation so
// the UI can draw a continuous dashed forecast without presenting it as fact.
export const projectTrend = (points, { months = 3, from = Date.now() } = {}) => {
  const rows = (points || [])
    .map((point) => ({ x: Number(point?.x), y: Number(point?.y) }))
    .filter((point) => Number.isFinite(point.x) && Number.isFinite(point.y) && point.y > 0)
    .sort((a, b) => a.x - b.x)

  if (rows.length < 2 || new Set(rows.map((point) => point.x)).size < 2) return null

  const origin = rows[0].x
  const samples = rows.map((point) => ({ x: (point.x - origin) / DAY_MS, y: point.y }))
  const meanX = samples.reduce((total, point) => total + point.x, 0) / samples.length
  const meanY = samples.reduce((total, point) => total + point.y, 0) / samples.length
  const denominator = samples.reduce((total, point) => total + ((point.x - meanX) ** 2), 0)
  if (denominator === 0) return null

  const slopePerDay = samples.reduce((total, point) => total + ((point.x - meanX) * (point.y - meanY)), 0) / denominator
  const intercept = meanY - (slopePerDay * meanX)
  const predict = (timestamp) => Math.max(0.01, intercept + (slopePerDay * ((timestamp - origin) / DAY_MS)))
  const last = rows[rows.length - 1]
  const projectionStart = Math.max(last.x, Number(from) || last.x)
  const future = Array.from({ length: Math.max(1, months) }, (_, index) => {
    const x = addUtcMonths(projectionStart, index + 1)
    return { x, y: Number(predict(x).toFixed(2)) }
  })

  const totalVariance = samples.reduce((total, point) => total + ((point.y - meanY) ** 2), 0)
  const residualVariance = samples.reduce((total, point) => total + ((point.y - (intercept + slopePerDay * point.x)) ** 2), 0)
  const rSquared = totalVariance === 0 ? 1 : Math.max(0, Math.min(1, 1 - (residualVariance / totalVariance)))

  return {
    data: [last, ...future],
    projectedValue: future[future.length - 1].y,
    slopePerDay,
    rSquared: Number(rSquared.toFixed(3)),
    sampleCount: rows.length,
  }
}
