// Faithful client-side port of ResearchPercentileEngine::estimatePercentile()
// (app/Services/Intelligence/ResearchPercentileEngine.php) — same piecewise
// linear interpolation over the same governed anchor ladder (p1/p5/p10/p25/
// p50/p75/p90/p95/p99), so a historical test point plotted in "Percentile"
// view lands on the same 0-100 scale the backend computed for the current one.
export const estimatePercentileFromAnchors = (value, anchorMap, higherIsBetter) => {
  if (!anchorMap || value == null || !Number.isFinite(value)) return null
  let points = Object.keys(anchorMap)
    .filter((tier) => anchorMap[tier] != null)
    .map((tier) => ({ value: Number(anchorMap[tier]), percentile: Number(tier.replace('p', '')) }))
  if (!points.length) return null

  let v = value
  if (!higherIsBetter) {
    points = points.map((p) => ({ ...p, value: -p.value }))
    v = -v
  }
  points.sort((a, b) => a.value - b.value)

  if (v <= points[0].value) return points[0].percentile
  const last = points[points.length - 1]
  if (v >= last.value) return last.percentile

  for (let i = 1; i < points.length; i++) {
    const left = points[i - 1]
    const right = points[i]
    if (v <= right.value) {
      const span = Math.max(0.0001, right.value - left.value)
      const progress = (v - left.value) / span
      return Math.round(Math.max(1, Math.min(99, left.percentile + progress * (right.percentile - left.percentile))))
    }
  }
  return 50
}
