const DAY_MS = 86400000

const median = (values) => {
  const sorted = values.filter(Number.isFinite).slice().sort((a, b) => a - b)
  if (!sorted.length) return null
  const middle = Math.floor(sorted.length / 2)
  return sorted.length % 2 ? sorted[middle] : (sorted[middle - 1] + sorted[middle]) / 2
}

const cleanPoints = (points) => (points || [])
  .map((point) => ({ x: Number(point?.x), y: Number(point?.y) }))
  .filter((point) => Number.isFinite(point.x) && Number.isFinite(point.y) && point.y > 0)
  .sort((a, b) => a.x - b.x)

const robustRegression = (rows) => {
  const origin = rows[0].x
  const samples = rows.map((point) => ({ x: (point.x - origin) / DAY_MS, y: point.y }))
  const slopes = []
  samples.forEach((left, leftIndex) => {
    samples.slice(leftIndex + 1).forEach((right) => {
      if (right.x !== left.x) slopes.push((right.y - left.y) / (right.x - left.x))
    })
  })
  const slopePerDay = median(slopes)
  if (!Number.isFinite(slopePerDay)) return null
  const intercept = median(samples.map((point) => point.y - slopePerDay * point.x))
  const predict = (timestamp) => intercept + slopePerDay * ((timestamp - origin) / DAY_MS)
  const residuals = rows.map((point) => point.y - predict(point.x))
  const residualMedian = median(residuals) ?? 0
  const residualMad = median(residuals.map((value) => Math.abs(value - residualMedian))) ?? 0
  const meanY = rows.reduce((total, point) => total + point.y, 0) / rows.length
  const totalVariance = rows.reduce((total, point) => total + ((point.y - meanY) ** 2), 0)
  const residualVariance = residuals.reduce((total, value) => total + (value ** 2), 0)
  const fitScore = totalVariance === 0 ? 1 : Math.max(0, Math.min(1, 1 - (residualVariance / totalVariance)))

  return { slopePerDay, predict, residualMad, fitScore }
}

const rollingTrend = (rows, method) => {
  const data = rows.map((point, index) => {
    const window = rows.slice(Math.max(0, index - 2), index + 1).map((row) => row.y)
    const y = method === 'rolling_median'
      ? median(window)
      : window.reduce((total, value) => total + value, 0) / window.length
    return { x: point.x, y: Number(y.toFixed(2)) }
  })
  const span = (data[data.length - 1].x - data[0].x) / DAY_MS
  const slopePerDay = span > 0 ? (data[data.length - 1].y - data[0].y) / span : 0
  return { data, slopePerDay, residualMad: 0, fitScore: 1 }
}

const confidenceFor = (rows, fitScore) => {
  const spanDays = (rows[rows.length - 1].x - rows[0].x) / DAY_MS
  if (rows.length < 5 || spanDays < 28 || fitScore < 0.4) return 'Low'
  if (rows.length < 8 || spanDays < 90 || fitScore < 0.75) return 'Moderate'
  return 'Strong'
}

const directionFor = (slopePerDay, current, behavior) => {
  if (behavior.direction === 'target_range') return slopePerDay > 0 ? 'Increasing' : slopePerDay < 0 ? 'Decreasing' : 'Stable'
  const thirtyDayChange = slopePerDay * 30
  if (Math.abs(thirtyDayChange) < Math.max(current * 0.005, 0.01)) return 'Stable'
  const improving = behavior.direction === 'lower' ? slopePerDay < 0 : slopePerDay > 0
  return improving ? 'Improving' : 'Declining'
}

export const buildTrendModel = (points, behavior, { from = null } = {}) => {
  const rows = cleanPoints(points)
  if (rows.length < (behavior?.minTrendPoints ?? 3) || new Set(rows.map((point) => point.x)).size < 3) {
    return { trend: null, projection: null, reason: 'At least 3 recorded dates are needed for a trend.' }
  }

  const robust = robustRegression(rows)
  if (!robust) return { trend: null, projection: null, reason: 'The recorded dates cannot form a trend.' }
  const trendResult = behavior.trendMethod === 'robust_regression'
    ? {
        data: rows.map((point) => ({ x: point.x, y: Number(Math.max(0.01, robust.predict(point.x)).toFixed(2)) })),
        slopePerDay: robust.slopePerDay,
        residualMad: robust.residualMad,
        fitScore: robust.fitScore,
      }
    : rollingTrend(rows, behavior.trendMethod)
  const confidence = confidenceFor(rows, trendResult.fitScore)
  const last = rows[rows.length - 1]
  const trend = {
    data: trendResult.data,
    slopePerDay: trendResult.slopePerDay,
    direction: directionFor(trendResult.slopePerDay, last.y, behavior),
    confidence,
    sampleCount: rows.length,
  }

  if (!behavior.projection) return { trend, projection: null, reason: behavior.projectionReason }
  const spanDays = (last.x - rows[0].x) / DAY_MS
  if (rows.length < behavior.minProjectionPoints || spanDays < behavior.minProjectionSpanDays) {
    return { trend, projection: null, reason: `Projection needs at least ${behavior.minProjectionPoints} tests across ${behavior.minProjectionSpanDays} days.` }
  }
  if (confidence === 'Low') return { trend, projection: null, reason: 'Trend confidence is too low for a responsible projection.' }

  const horizonDays = behavior.projectionDays || 30
  const requestedStart = Number(from)
  const projectionStart = Number.isFinite(requestedStart) ? Math.max(last.x, requestedStart) : last.x
  const uncertainty = Math.max(trendResult.residualMad * 1.4826, last.y * 0.01)
  const confidenceMultiplier = confidence === 'Strong' ? 0.9 : 1.5
  const future = [10, 20, horizonDays].map((days) => {
    const decay = 1 - (0.4 * (days / horizonDays))
    const rawChange = trendResult.slopePerDay * days * decay
    const maximumChange = last.y * behavior.maxChangeRatio * (days / horizonDays)
    const change = Math.max(-maximumChange, Math.min(maximumChange, rawChange))
    const y = Math.max(0.01, last.y + change)
    const spread = uncertainty * confidenceMultiplier * Math.sqrt(days / horizonDays)
    return {
      x: projectionStart + days * DAY_MS,
      y: Number(y.toFixed(2)),
      low: Number(Math.max(0.01, y - spread).toFixed(2)),
      high: Number((y + spread).toFixed(2)),
    }
  })

  return {
    trend,
    reason: null,
    projection: {
      data: [last, ...future.map(({ x, y }) => ({ x, y }))],
      rangeData: [{ x: last.x, y: [last.y, last.y] }, ...future.map(({ x, low, high }) => ({ x, y: [low, high] }))],
      outlookLow: future[future.length - 1].low,
      outlookHigh: future[future.length - 1].high,
      projectedValue: future[future.length - 1].y,
      horizonDays,
      confidence,
      sampleCount: rows.length,
    },
  }
}
