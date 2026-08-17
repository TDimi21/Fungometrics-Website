const numberOrNull = (value) => {
  if (value === null || value === undefined || value === '') return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

const playerName = (snapshot) => snapshot?.summary?.player?.name
  || [snapshot?.summary?.player?.first_name, snapshot?.summary?.player?.last_name].filter(Boolean).join(' ')
  || 'Player'

export const benchmarkAgeGroupLabel = (value) => ({
  '10U_12U': '10U–12U',
  '13U_14U': '13U–14U',
  '15U_16U': '15U–16U',
  '17U_18U': '17U–18U',
  'COLLEGE_19_PLUS': 'College 19+',
  UNKNOWN: 'Age not set',
}[String(value || '').toUpperCase()] || String(value || 'Age not set').replaceAll('_', ' '))

export const buildTeamPercentileRows = (snapshots) => (Array.isArray(snapshots) ? snapshots : []).flatMap((snapshot) => {
  const player = snapshot?.summary?.player || {}
  const profile = snapshot?.benchmark_profile || {}
  const ageGroup = profile?.comparison_context?.age_group || snapshot?.age_benchmarks?.age_group || 'UNKNOWN'
  const metrics = Array.isArray(profile?.metrics) ? profile.metrics : []
  const seen = new Set()

  return metrics.flatMap((metric) => {
    const metricKey = String(metric?.metric_key || '').trim()
    const percentile = numberOrNull(metric?.percentile)
    const actual = numberOrNull(metric?.raw_value)
    if (!metricKey || percentile === null || actual === null || actual <= 0 || seen.has(metricKey)) return []
    seen.add(metricKey)

    return [{
      playerId: String(snapshot?.player_id || player?.id || ''),
      playerName: playerName(snapshot),
      age: numberOrNull(player?.age),
      ageGroup,
      ageGroupLabel: benchmarkAgeGroupLabel(ageGroup),
      metricKey,
      metricLabel: metric?.display_name || metricKey.replaceAll('_', ' '),
      category: metric?.category || 'Other',
      percentile: Math.max(0, Math.min(100, percentile)),
      actual,
      unit: metric?.unit || '',
      label: metric?.label || 'Benchmark available',
      confidence: metric?.confidence || 'insufficient',
    }]
  })
})

export const teamPercentileMetricOptions = (rows) => {
  const metrics = new Map()
  for (const row of Array.isArray(rows) ? rows : []) {
    const existing = metrics.get(row.metricKey)
    if (existing) {
      existing.playerCount += 1
      continue
    }
    metrics.set(row.metricKey, {
      key: row.metricKey,
      label: row.metricLabel,
      category: row.category,
      unit: row.unit,
      playerCount: 1,
    })
  }
  return [...metrics.values()].sort((left, right) => left.category.localeCompare(right.category) || left.label.localeCompare(right.label))
}

export const rankTeamPercentileRows = (rows, metricKey, limit = 25) => {
  const ranked = (Array.isArray(rows) ? rows : [])
    .filter((row) => row.metricKey === metricKey)
    .sort((left, right) => right.percentile - left.percentile || left.playerName.localeCompare(right.playerName))
    .slice(0, Math.max(1, limit))

  let displayedRank = 0
  let previousPercentile = null
  return ranked.map((row, index) => {
    if (previousPercentile === null || row.percentile !== previousPercentile) displayedRank = index + 1
    previousPercentile = row.percentile
    return { ...row, rank: displayedRank }
  })
}
