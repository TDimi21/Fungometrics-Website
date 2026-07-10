<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team'
import {
  DASHBOARD_METRICS,
  average,
  round1,
  computePDI,
  computeTDI,
  projectMetric,
  computeRiskIndex,
  riskLevel,
  rankToPercentile,
} from '../lib/teamDevelopmentCommandCenter'

const router = useRouter()
const { axiosGet, axiosPost } = useAxiosAuth()
const { team } = storeToRefs(useTeamStore())

const loading = ref(false)
const loadError = ref('')
const board = ref([])
const dashboard = ref({})
const perf = ref({})
const teamIntelligence = ref(null)
const savedBenchmarkTasks = ref([])
const benchmarkTaskActionLoading = ref('')
const benchmarkTaskActionError = ref('')
const benchmarkTaskActionMessage = ref('')

const selectedMetric = ref('average_fastball_velocity')
const selectedRange = ref('30d')
const selectedPlayers = ref([])
const priorityTop10Modal = ref({
  open: false,
  key: null,
  label: '',
  unit: '',
})
const selectedBenchmarkMetric = ref(null)

const n = (v) => (Number.isFinite(Number(v)) ? Number(v) : null)
const clamp = (x, min = 0, max = 100) => Math.max(min, Math.min(max, x))
const pct = (rank, total) => {
  if (!rank || !total) return null
  return Math.round(((total - rank + 1) / total) * 100)
}

const normalizeStatus = (status) => ({
  hot: 'Hot', improving: 'Improving', steady: 'Steady', needs_work: 'Needs Work', no_data: 'No Data',
}[status] || status || '—')

const normalizeTrend = (trend) => ({ up: 'Improving', down: 'Declining', steady: 'Steady' }[trend] || trend || '—')

const toCardBand = (score) => {
  const v = n(score) ?? 0
  if (v >= 90) return { label: 'Elite', tone: 'text-emerald-300', chip: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' }
  if (v >= 80) return { label: 'Strong', tone: 'text-emerald-300', chip: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' }
  if (v >= 70) return { label: 'Solid', tone: 'text-yellow-300', chip: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/40' }
  if (v >= 60) return { label: 'Needs Work', tone: 'text-orange-300', chip: 'bg-orange-500/20 text-orange-300 border-orange-500/40' }
  return { label: 'Critical', tone: 'text-red-300', chip: 'bg-red-500/20 text-red-300 border-red-500/40' }
}

const projectionCapByMetric = {
  average_fastball_velocity: 5,
  top_pitch_velocity: 5,
  average_exit_velocity: 8,
  top_exit_velocity: 8,
  strike_percentage: 12,
  long_toss_max_distance: 60,
  pitcher_swing_miss_percentage: 10,
  hitter_swing_miss_percentage: 10,
}

const metricMeta = {
  strike_percentage: { label: 'Strike %', unit: '%', goal: 65 },
  top_pitch_velocity: { label: 'Top Pitch Velo', unit: 'mph', goal: 90 },
  average_fastball_velocity: { label: 'Avg FB Velo', unit: 'mph', goal: 82 },
  pitcher_swing_miss_percentage: { label: 'Pitcher Swing/Miss %', unit: '%', goal: 22 },
  bullpen_score: { label: 'Bullpen Score', unit: '', goal: 75 },
  long_toss_max_distance: { label: 'Long Toss Max', unit: 'ft', goal: 240 },
  long_toss_carry_score: { label: 'Long Toss Carry', unit: '', goal: 75 },
  average_exit_velocity: { label: 'Avg Exit Velo', unit: 'mph', goal: 84 },
  top_exit_velocity: { label: 'Top Exit Velo', unit: 'mph', goal: 95 },
  exit_velocity_growth: { label: 'Exit Velo Growth', unit: 'mph', goal: 2 },
  hard_hit_percentage: { label: 'Hard Hit %', unit: '%', goal: 35 },
  line_drive_percentage: { label: 'Line Drive %', unit: '%', goal: 28 },
  hitter_swing_miss_percentage: { label: 'Hitter Swing/Miss %', unit: '%', goal: 15, lowerBetter: true },
  damage_index: { label: 'Damage Index', unit: '', goal: 75 },
}

const resolveTeamId = computed(() => team.value?.id || team.value?.id_team || null)

const loadTeamCommandCenter = async () => {
  loadError.value = ''
  board.value = []
  dashboard.value = {}
  perf.value = {}
  teamIntelligence.value = null
  savedBenchmarkTasks.value = []
  benchmarkTaskActionError.value = ''
  benchmarkTaskActionMessage.value = ''

  const teamId = resolveTeamId.value
  if (!teamId) {
    loadError.value = 'Select a team to load development command center data.'
    return
  }

  loading.value = true
  try {
    const [boardRes, dashRes, perfRes, intelligenceRes, benchmarkTasksRes] = await Promise.all([
      axiosGet(`coach/teams/${teamId}/player-development-board`).catch(() => null),
      axiosGet(`dashboard/${teamId}`).catch(() => null),
      axiosGet(`coach/performance-overview/${teamId}`).catch(() => null),
      axiosGet(`coach/teams/${teamId}/intelligence`, { days: 365 }).catch(() => null),
      axiosGet(`intelligence/teams/${teamId}/benchmark-tasks`).catch(() => null),
    ])

    board.value = Array.isArray(boardRes?.data?.data) ? boardRes.data.data : []
    dashboard.value = dashRes?.data?.data ?? {}
    perf.value = perfRes?.data?.data ?? {}
    teamIntelligence.value = intelligenceRes?.data?.data || intelligenceRes?.data || null
    const benchmarkTaskPayload = benchmarkTasksRes?.data?.data || benchmarkTasksRes?.data || {}
    savedBenchmarkTasks.value = Array.isArray(benchmarkTaskPayload.tasks) ? benchmarkTaskPayload.tasks : []

    const hasIntelligenceData = Array.isArray(teamIntelligence.value?.players) && teamIntelligence.value.players.length
    const benchmarkMetricCount = n(teamIntelligence.value?.benchmark_profile?.metric_count) || 0

    if (!board.value.length && !hasIntelligenceData && benchmarkMetricCount === 0) {
      loadError.value = 'No development records found. Log bullpen, batting, long toss, and fitness sessions to unlock this dashboard.'
    }
  } catch {
    loadError.value = 'Could not load team command center data.'
  } finally {
    loading.value = false
  }
}

watch(() => resolveTeamId.value, () => { loadTeamCommandCenter() }, { immediate: true })

const rankPercent = (rankObj) => rankToPercentile(rankObj?.rank, rankObj?.total)

const safeText = (value, fallback = 'Needs Data') => {
  const text = String(value ?? '').trim()
  return text || fallback
}

const firstNumber = (...values) => {
  for (const value of values) {
    const parsed = n(value)
    if (parsed !== null) return parsed
  }
  return null
}

const fmt1 = (value, fallback = '—') => {
  const parsed = n(value)
  return parsed === null ? fallback : parsed.toFixed(1)
}

const fmtCount = (value, fallback = '—') => {
  const parsed = n(value)
  return parsed === null ? fallback : String(Math.round(parsed))
}

const fmtValue = (value, unit = '', fallback = '—') => {
  const display = fmt1(value, fallback)
  return display === fallback ? fallback : `${display}${unit ? ` ${unit}` : ''}`
}

const hasPositiveSample = (...values) => values.some((value) => (n(value) ?? 0) > 0)

const valueWithSample = (value, sampleValues = [], { zeroIsMissing = true } = {}) => {
  const parsed = n(value)
  if (parsed === null) return null
  if (sampleValues.length && !hasPositiveSample(...sampleValues)) return null
  if (zeroIsMissing && parsed === 0) return null
  return parsed
}

const firstMeaningfulNumber = (...values) => {
  for (const value of values) {
    const parsed = n(value)
    if (parsed !== null && parsed !== 0) return parsed
  }
  return null
}

const fmtRank = (value, fallback = '—') => {
  const parsed = n(value)
  return parsed === null ? fallback : `${parsed.toFixed(1)}th`
}

const teamPlayersIntelligence = computed(() =>
  Array.isArray(teamIntelligence.value?.players) ? teamIntelligence.value.players : []
)

const intelligenceByPlayerId = computed(() => {
  const map = new Map()
  for (const snapshot of teamPlayersIntelligence.value) {
    const id = snapshot?.player_id || snapshot?.summary?.player?.id
    if (id) map.set(String(id), snapshot)
  }
  return map
})

const playerSnapshotFor = (playerId) => intelligenceByPlayerId.value.get(String(playerId)) || null

const projectionCurrent = (snapshot, key) => n(snapshot?.projections?.[key]?.current)
const projection90 = (snapshot, key) => n(snapshot?.projections?.[key]?.projected_90_day)

const categoryLabel = (key) => ({
  athletic_performance: 'Athletic Performance',
  athletic: 'Athletic',
  strength: 'Strength',
  recovery: 'Recovery',
  mobility: 'Mobility',
  batting: 'Hitting',
  hitting: 'Hitting',
  bullpen: 'Pitching',
  pitching: 'Pitching',
  cage: 'Cage',
  exit_velocity: 'Exit Velocity',
}[key] || String(key || '').replaceAll('_', ' '))

const teamRecommendations = computed(() =>
  Array.isArray(teamIntelligence.value?.recommendations) ? teamIntelligence.value.recommendations : []
)

const asArray = (value) => (Array.isArray(value) ? value : [])

const readableLabelOverrides = {
  below_average: 'Below Average',
  score_0_100: 'Score',
  data_collection_priority: 'Data Collection Priority',
  research_benchmark: 'Research Benchmark',
  fmtrx_population: 'FMTRX Population',
  composite: 'Research + FMTRX Blend',
  composite_benchmark: 'Research + FMTRX Blend',
  insufficient: 'Not Enough Data',
}

const humanizeKey = (value, fallback = 'Needs Data') => {
  const text = String(value ?? '').trim()
  if (!text) return fallback
  if (readableLabelOverrides[text]) return readableLabelOverrides[text]

  return text
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

const benchmarkProfile = computed(() => {
  const profile = teamIntelligence.value?.benchmark_profile
  return profile && typeof profile === 'object' ? profile : null
})

const hasBenchmarkProfile = computed(() => {
  const profile = benchmarkProfile.value
  if (!profile) return false
  return Boolean(
    n(profile.metric_count) ||
    asArray(profile.category_scores).length ||
    asArray(profile.missing_metrics).length
  )
})

const decisionBrief = computed(() => {
  const brief = teamIntelligence.value?.decision_brief
  return brief && typeof brief === 'object' ? brief : {}
})

const sourceShare = (value) => {
  const parsed = n(value)
  if (parsed === null) return null
  return parsed <= 1 ? parsed * 100 : parsed
}

const benchmarkSnapshot = computed(() => {
  const profile = benchmarkProfile.value || {}
  const sourceMix = profile.source_mix || {}

  return {
    confidence: profile.benchmark_confidence || 'low',
    researchShare: sourceShare(sourceMix.research_share),
    populationShare: sourceShare(sourceMix.population_share),
    metricCount: n(profile.metric_count),
    playerCount: n(profile.player_count),
  }
})

const sourceLabelOverrides = {
  research_benchmark: 'Research Benchmark',
  fmtrx_population: 'FMTRX Population',
  composite: 'Research + FMTRX Blend',
  composite_benchmark: 'Research + FMTRX Blend',
}

const confidenceLabelOverrides = {
  insufficient: 'Not Enough Data',
  low: 'Low Confidence',
  medium: 'Medium Confidence',
  high: 'High Confidence',
}

const sourceLabel = (source) => sourceLabelOverrides[String(source ?? '').trim()] || humanizeKey(source, 'Research Benchmark')
const confidenceLabel = (confidence) => confidenceLabelOverrides[String(confidence ?? '').trim()] || humanizeKey(confidence, 'Not Enough Data')

const sourceMixPercent = (sourceMix, percentKey, shareKey) => {
  const percent = n(sourceMix?.[percentKey])
  if (percent !== null) return percent

  return sourceShare(sourceMix?.[shareKey])
}

const sourceMixCount = (sourceMix, key, legacyKey) => {
  const direct = n(sourceMix?.[key])
  if (direct !== null) return direct

  return n(sourceMix?.counts?.[legacyKey])
}

const sourceMixBucketCount = (sourceMix) =>
  n(sourceMix?.average_population_bucket_count)
  ?? n(sourceMix?.population_bucket_count)
  ?? 0

const benchmarkSourceMix = computed(() => {
  const sourceMix = benchmarkProfile.value?.source_mix

  if (!sourceMix || typeof sourceMix !== 'object') {
    return {
      available: false,
      researchPercent: null,
      populationPercent: null,
      compositePercent: null,
      averageBucketCount: 0,
      researchCount: null,
      populationCount: null,
      compositeCount: null,
      populationCompositeCount: null,
      activeSourceText: 'Benchmark source mix is not available yet.',
      guidance: 'Benchmark source mix is not available yet.',
    }
  }

  const researchPercent = sourceMixPercent(sourceMix, 'percent_research', 'research_share')
  const populationPercent = sourceMixPercent(sourceMix, 'percent_population', 'population_share')
  const compositePercent = sourceMixPercent(sourceMix, 'percent_composite', 'composite_share')
  const researchCount = sourceMixCount(sourceMix, 'research_count', 'research')
  const populationCount = sourceMixCount(sourceMix, 'population_count', 'population')
  const compositeCount = sourceMixCount(sourceMix, 'composite_count', 'composite')
  const populationCompositeCount = (populationCount ?? 0) + (compositeCount ?? 0)
  const averageBucketCount = sourceMixBucketCount(sourceMix)
  const populationActive = populationCompositeCount > 0 || (populationPercent ?? 0) > 0 || (compositePercent ?? 0) > 0

  return {
    available: true,
    researchPercent,
    populationPercent,
    compositePercent,
    averageBucketCount,
    researchCount,
    populationCount,
    compositeCount,
    populationCompositeCount,
    populationActive,
    activeSourceText: populationActive
      ? 'FMTRX population learning is active for some metrics.'
      : 'Research benchmarks are still the primary source.',
    guidance: averageBucketCount < 30
      ? 'Population samples under 30 remain research-only.'
      : 'Population benchmark confidence improves as more players are added.',
  }
})

const sourceMixStatusText = (sourceMix = {}) => {
  const populationWeight = n(sourceMix.population_weight) ?? 0
  const bucketCount = n(sourceMix.population_bucket_count) ?? 0
  const selectedBucketLevel = sourceMix.selected_bucket_level || null

  if (populationWeight <= 0) {
    return bucketCount < 30
      ? 'Population sample below 30. Research benchmark remains active.'
      : 'Research benchmark active. FMTRX sample is not included in this score.'
  }

  if (selectedBucketLevel === 'global_clean') {
    return 'This is a broad comparison group. Peer-specific confidence improves as more players collect data.'
  }

  if (selectedBucketLevel === 'exact_peer') {
    return 'Strong peer match.'
  }

  if (bucketCount >= 300) return 'High-confidence FMTRX population blend.'
  if (bucketCount >= 100) return 'Medium-confidence FMTRX population blend.'
  if (bucketCount >= 30) return 'Low-confidence FMTRX population blend.'

  return 'FMTRX population learning is included in this score.'
}

const playerBenchmarkMetricRows = computed(() =>
  teamPlayersIntelligence.value.flatMap((snapshot) => {
    const playerName = snapshot?.summary?.player?.name || snapshot?.summary?.player?.first_name || 'Player'
    return asArray(snapshot?.benchmark_profile?.metrics).map((metric) => ({
      ...metric,
      player_name: playerName,
      player_id: snapshot?.player_id,
    }))
  })
)

const allBenchmarkMetricRows = computed(() => {
  const metricMap = new Map()
  const metricSources = [
    ...asArray(benchmarkProfile.value?.metrics),
    ...asArray(benchmarkProfile.value?.weakest_metrics),
    ...asArray(benchmarkProfile.value?.strongest_metrics),
    ...playerBenchmarkMetricRows.value,
  ]

  for (const metric of metricSources) {
    const key = metric?.metric_key || metric?.display_name
    if (!key) continue

    const existing = metricMap.get(key)
    if (!existing || metricBucketDetailScore(metric) > metricBucketDetailScore(existing)) {
      metricMap.set(key, metric)
    }
  }

  return [...metricMap.values()]
})

const sourceMetricRows = computed(() => allBenchmarkMetricRows.value.slice(0, 6))

const metricSourceMix = (metric) => metric?.source_mix && typeof metric.source_mix === 'object' ? metric.source_mix : {}
const metricPopulationDetail = (metric) => {
  const detail = metric?.population_percentile_detail
  if (detail && typeof detail === 'object') return detail

  const population = metric?.population_percentile
  return population && typeof population === 'object' ? population : {}
}
const metricSource = (metric) => {
  if (metric?.source) return sourceLabel(metric.source)

  const sourceMix = metricSourceMix(metric)
  const populationWeight = n(sourceMix.population_weight) ?? 0
  const researchWeight = n(sourceMix.research_weight) ?? 0

  if (populationWeight > 0 && researchWeight > 0) return sourceLabel('composite')
  if (populationWeight > 0) return sourceLabel('fmtrx_population')

  return sourceLabel('research_benchmark')
}
const metricResearchPercentile = (metric) => {
  const value = n(metric?.research_percentile)
  return value === null ? '—' : fmtRank(value)
}
const metricPopulationPercentile = (metric) => {
  const value = n(
    typeof metric?.population_percentile === 'object'
      ? metric?.population_percentile?.percentile
      : metric?.population_percentile
  )
  return value === null ? '—' : fmtRank(value)
}
const metricPopulationBucketCountValue = (metric) => {
  const sourceMix = metricSourceMix(metric)
  const detail = metricPopulationDetail(metric)
  return n(metric?.bucket_count)
    ?? n(metric?.population_bucket_count)
    ?? n(sourceMix.population_bucket_count)
    ?? n(detail.bucket_count)
    ?? 0
}
const metricPopulationBucketCount = (metric) => fmtCount(metricPopulationBucketCountValue(metric), '0')
const metricPopulationConfidenceValue = (metric) => {
  const sourceMix = metricSourceMix(metric)
  const detail = metricPopulationDetail(metric)
  return metric?.population_confidence
    || sourceMix.population_confidence
    || detail.confidence
    || 'insufficient'
}
const metricPopulationConfidence = (metric) => confidenceLabel(metricPopulationConfidenceValue(metric))
const metricPopulationUsableValue = (metric) => {
  const sourceMix = metricSourceMix(metric)
  const detail = metricPopulationDetail(metric)
  return metric?.population_usable === true
    || sourceMix.population_usable === true
    || detail.usable === true
}
const metricPopulationUsable = (metric) => metricPopulationUsableValue(metric) ? 'Yes' : 'No'

const bucketLabelOverrides = {
  exact_peer: 'Exact Peer Group',
  athletic_peer: 'Athletic Peer Group',
  age_role: 'Age + Role Group',
  age_only: 'Age Group',
  global_clean: 'Global FMTRX Population',
  none: 'Not Enough Population Data',
}

const bucketExplanations = {
  exact_peer: 'Compared against players with similar age, level, position, body size, height, throws, and bats.',
  athletic_peer: 'Compared against players with similar age, level, position, and bodyweight.',
  age_role: 'Compared against players with similar age, level, and position.',
  age_only: 'Compared against players in the same age group.',
  global_clean: 'Compared against all valid guarded FMTRX values because smaller peer buckets were too small.',
  none: 'Population sample is below 30, so research benchmark remains active.',
}

const bucketLevelValue = (metric) => {
  const sourceMix = metricSourceMix(metric)
  const detail = metricPopulationDetail(metric)
  return metric?.selected_bucket_level
    || metric?.population_bucket_level
    || sourceMix.selected_bucket_level
    || detail.selected_bucket_level
    || (metricPopulationUsableValue(metric) ? 'global_clean' : 'none')
}

const bucketKeyValue = (metric) => {
  const sourceMix = metricSourceMix(metric)
  const detail = metricPopulationDetail(metric)
  return metric?.selected_bucket_key
    || metric?.population_bucket_key
    || sourceMix.selected_bucket_key
    || detail.selected_bucket_key
    || detail.bucket_key
    || null
}

const bucketLabel = (level) => bucketLabelOverrides[String(level ?? '').trim()] || 'Bucket quality not available yet.'
const bucketExplanation = (level) => bucketExplanations[String(level ?? '').trim()] || 'Bucket quality not available yet.'

const metricAttemptedBuckets = (metric) => {
  const detail = metricPopulationDetail(metric)
  const attempts = metric?.attempted_buckets || metric?.population_attempted_buckets || detail.attempted_buckets || []
  return asArray(attempts).slice(0, 5)
}

const metricAttemptedBucketLabel = (attempt) => {
  const count = fmtCount(attempt?.count ?? 0, '0')
  const label = isUnknownExactBucketKey(attempt?.level, attempt?.bucket_key)
    ? 'Broad FMTRX Population'
    : bucketLabel(attempt?.level)
  return `${label}: ${count} players${attempt?.usable ? ', selected' : ''}`
}

const isUnknownExactBucketKey = (level, key) => {
  if (level !== 'exact_peer') return false
  const text = String(key ?? '').toLowerCase()
  return [
    'age:unknown',
    'level:unknown',
    'position:unknown',
    'body:unknown',
    'height:unknown',
    'throws:unknown',
    'bats:unknown',
  ].every((part) => text.includes(part))
}

const displayBucketLevel = (metric) => {
  const level = bucketLevelValue(metric) || 'none'
  const key = bucketKeyValue(metric)
  if (isUnknownExactBucketKey(level, key)) return 'broad_unknown'
  return level
}

const displayBucketLabel = (metric) => {
  const level = displayBucketLevel(metric)
  if (level === 'broad_unknown') return 'Broad FMTRX Population'
  return bucketLabel(level)
}

const displayBucketExplanation = (metric) => {
  const level = displayBucketLevel(metric)
  if (level === 'broad_unknown') {
    return 'Compared against guarded FMTRX values because player context is missing or incomplete.'
  }
  return bucketExplanation(level)
}

const metricBucketDetailScore = (metric) => {
  let score = 0
  if (bucketLevelValue(metric) && bucketLevelValue(metric) !== 'none') score += 3
  if (metricAttemptedBuckets(metric).length) score += 3
  if (metricPopulationBucketCountValue(metric) > 0) score += 2
  if (metricPopulationUsableValue(metric)) score += 2
  if (metric?.source_mix) score += 1
  return score
}

const bucketQualityMetricRows = computed(() =>
  allBenchmarkMetricRows.value
    .filter((metric) => metric?.metric_key || metric?.display_name)
    .map((metric) => {
      const level = bucketLevelValue(metric) || 'none'
      const displayLevel = displayBucketLevel(metric)
      const bucketCount = metricPopulationBucketCountValue(metric)
      return {
        ...metric,
        bucketLevel: level,
        bucketDisplayLevel: displayLevel,
        bucketLabel: displayBucketLabel(metric),
        bucketExplanation: displayBucketExplanation(metric),
        bucketKey: bucketKeyValue(metric),
        bucketCount,
        bucketConfidence: metricPopulationConfidenceValue(metric),
        bucketUsable: metricPopulationUsableValue(metric),
        attemptedBuckets: metricAttemptedBuckets(metric),
        finalScore: n(metric?.score_0_100 ?? metric?.percentile_estimate ?? metric?.percentile),
      }
    })
    .slice(0, 8)
)

const populationBucketQualitySummary = computed(() => {
  const rows = bucketQualityMetricRows.value
  const levels = ['exact_peer', 'athletic_peer', 'age_role', 'age_only', 'global_clean', 'none']
  const counts = Object.fromEntries(levels.map((level) => [level, 0]))
  const confidences = { insufficient: 0, low: 0, medium: 0, high: 0 }
  const bucketCounts = []

  for (const row of rows) {
    const displayLevel = row.bucketDisplayLevel === 'broad_unknown' ? 'global_clean' : row.bucketDisplayLevel
    const level = row.bucketUsable ? displayLevel : 'none'
    counts[level] = (counts[level] || 0) + 1
    const confidence = String(row.bucketConfidence || 'insufficient')
    confidences[confidence] = (confidences[confidence] || 0) + 1
    bucketCounts.push(row.bucketCount || 0)
  }

  const averageBucketCount = bucketCounts.length
    ? bucketCounts.reduce((sum, value) => sum + value, 0) / bucketCounts.length
    : null

  return {
    available: rows.length > 0,
    counts,
    confidences,
    averageBucketCount,
    confidenceSummary: Object.entries(confidences)
      .filter(([, count]) => count > 0)
      .map(([confidence, count]) => `${confidenceLabel(confidence)} ${count}`)
      .join(' · ') || 'Not Enough Data',
  }
})

const metricFinalPercentile = (metric) => {
  const value = n(metric?.percentile_estimate ?? metric?.percentile ?? metric?.score_0_100)
  return value === null ? '—' : fmtRank(value)
}

const metricSourceWeight = (metric, key) => {
  const value = n(metricSourceMix(metric)?.[key])
  if (value === null) return '—'
  return value <= 1 ? `${(value * 100).toFixed(1)}%` : `${value.toFixed(1)}%`
}

const metricGapSentence = (metric, key, label) => {
  const gap = n(metric?.[key])
  if (gap === null) return `Gap target is not available for ${label.toLowerCase()} yet.`
  if (gap <= 0) return `Already meets the ${label} benchmark.`
  return `Needs +${fmtValue(gap, metric?.unit || '')} to reach ${label}.`
}

const readableEvidenceLines = (value, prefix = '') => {
  if (value === null || value === undefined || value === '') return []
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return [`${prefix}${String(value)}`]
  }
  if (Array.isArray(value)) {
    return value.flatMap((item) => readableEvidenceLines(item, prefix))
  }
  if (typeof value === 'object') {
    return Object.entries(value).flatMap(([key, item]) => {
      if (item === null || item === undefined || item === '') return []
      const label = humanizeKey(key, key)
      if (typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean') {
        return [`${prefix}${label}: ${String(item)}`]
      }
      if (Array.isArray(item) && item.every((entry) => typeof entry !== 'object')) {
        return [`${prefix}${label}: ${item.join(', ')}`]
      }
      return readableEvidenceLines(item, `${prefix}${label} - `)
    })
  }
  return []
}

const selectedMetricDetail = computed(() => {
  const metric = selectedBenchmarkMetric.value
  if (!metric) return null

  const sourceMix = metricSourceMix(metric)
  const population = metricPopulationDetail(metric)
  const level = displayBucketLevel(metric)
  const source = metric?.source || (metricPopulationUsableValue(metric) ? 'fmtrx_population' : 'research_benchmark')
  const evidence = readableEvidenceLines(metric?.evidence || population?.evidence || []).slice(0, 12)

  return {
    metric,
    displayName: metric.display_name || humanizeKey(metric.metric_key, 'Benchmark Metric'),
    category: categoryLabel(metric.category),
    rawValue: fmtValue(metric.raw_value, metric.unit || ''),
    label: humanizeKey(metric.label || metric.benchmark_label || 'unknown'),
    score: fmtScore(metric.score_0_100 ?? metric.percentile_estimate ?? metric.percentile),
    finalPercentile: metricFinalPercentile(metric),
    confidence: confidenceLabel(metric.confidence || metricPopulationConfidenceValue(metric)),
    source: sourceLabel(source),
    sourceKey: source,
    researchWeight: metricSourceWeight(metric, 'research_weight'),
    populationWeight: metricSourceWeight(metric, 'population_weight'),
    researchPercentile: metricResearchPercentile(metric),
    populationPercentile: metricPopulationPercentile(metric),
    populationBucketCount: metricPopulationBucketCount(metric),
    populationConfidence: metricPopulationConfidence(metric),
    populationUsable: metricPopulationUsable(metric),
    bucketLevel: level,
    bucketLabel: level === 'broad_unknown' ? 'Broad FMTRX Population' : bucketLabel(level),
    bucketKey: bucketKeyValue(metric),
    bucketCount: fmtCount(metricPopulationBucketCountValue(metric), '0'),
    bucketExplanation: displayBucketExplanation(metric),
    attemptedBuckets: metricAttemptedBuckets(metric),
    goodGap: metricGapSentence(metric, 'gap_to_good', 'Good'),
    eliteGap: metricGapSentence(metric, 'gap_to_elite', 'Elite'),
    evidence,
    hasSourceMix: Object.keys(sourceMix).length > 0 || Object.keys(population).length > 0,
  }
})

const selectedMetricSourceExplanation = computed(() => {
  const detail = selectedMetricDetail.value
  if (!detail) return ''
  if (detail.populationUsable === 'No') {
    return 'FMTRX population sample is below the minimum threshold of 30, so the research benchmark remains active.'
  }
  if (detail.sourceKey === 'composite' || detail.sourceKey === 'composite_benchmark') {
    return 'FMTRX is blending research benchmarks with population data because the population bucket has enough guarded values.'
  }
  if (detail.sourceKey === 'fmtrx_population') {
    return 'FMTRX population data is carrying this benchmark because enough trusted sample data exists.'
  }
  return 'Research benchmark remains active for this metric.'
})

const openBenchmarkMetricDetail = (metric) => {
  if (!metric) return
  selectedBenchmarkMetric.value = metric
}

const closeBenchmarkMetricDetail = () => {
  selectedBenchmarkMetric.value = null
}

const benchmarkCategoryKeys = ['pitching', 'hitting', 'strength', 'athletic', 'mobility']

const hasBenchmarkCategoryScores = computed(() =>
  asArray(benchmarkProfile.value?.category_scores).length > 0
)

const benchmarkCategoryRows = computed(() => {
  const rows = new Map()
  for (const row of asArray(benchmarkProfile.value?.category_scores)) {
    if (row?.category) rows.set(row.category, row)
  }

  return benchmarkCategoryKeys.map((key) => {
    const row = rows.get(key) || {}
    const score = n(row.score_0_100)
    return {
      category: key,
      display: categoryLabel(key),
      score,
      label: row.label || (score === null ? 'Needs Data' : toCardBand(score).label),
      confidence: row.confidence || 'low',
      playerCount: n(row.player_count),
      metricCount: n(row.metric_count),
      hasData: score !== null || n(row.metric_count) !== null,
    }
  })
})

const weakestBenchmarkCategories = computed(() =>
  asArray(benchmarkProfile.value?.weakest_categories).slice(0, 5)
)

const weakestBenchmarkMetrics = computed(() =>
  asArray(benchmarkProfile.value?.weakest_metrics).slice(0, 5)
)

const benchmarkPlayersNeedingAttention = computed(() =>
  asArray(benchmarkProfile.value?.players_needing_attention).slice(0, 5)
)

const benchmarkMissingMetrics = computed(() =>
  asArray(benchmarkProfile.value?.missing_metrics).slice(0, 8)
)

const dataCollectionPriority = computed(() => {
  const priority = decisionBrief.value?.data_collection_priority
  return priority && typeof priority === 'object' ? priority : null
})

const criticalMissingRows = computed(() => asArray(dataCollectionPriority.value?.missing_critical).slice(0, 5))
const supportingMissingRows = computed(() => asArray(dataCollectionPriority.value?.missing_supporting).slice(0, 5))
const optionalMissingRows = computed(() => asArray(dataCollectionPriority.value?.missing_optional).slice(0, 3))
const collectionPlanRows = computed(() => asArray(dataCollectionPriority.value?.recommended_collection_plan).slice(0, 3))
const allCriticalMissingRows = computed(() => asArray(dataCollectionPriority.value?.missing_critical))
const allSupportingMissingRows = computed(() => asArray(dataCollectionPriority.value?.missing_supporting))

const hasMissingDataPriorityRows = computed(() =>
  criticalMissingRows.value.length > 0
  || supportingMissingRows.value.length > 0
  || optionalMissingRows.value.length > 0
  || benchmarkMissingMetrics.value.length > 0
)

const primaryFocusCard = computed(() => {
  const focus = decisionBrief.value?.primary_focus || {}
  const topRec = teamRecommendations.value[0] || {}

  return {
    title: focus.title || topRec.title || 'Needs More Intelligence Data',
    why: focus.why || topRec.why || 'FMTRX needs more scored sessions to produce a confident team focus.',
    action: focus.action || topRec.action || 'Collect bullpen, BP, exit velocity, long toss, and fitness baselines.',
    expectedGain: decisionBrief.value?.expected_gain || topRec.expected_gain || null,
    confidence: decisionBrief.value?.confidence || focus.confidence || topRec.confidence || 'low',
    affectedCount: asArray(decisionBrief.value?.players_needing_attention).length,
  }
})

const practicePlanHasDataBlock = computed(() =>
  decisionBrief.value?.recommended_practice_plan?.data_collection_appended === true
)

const coachFriendlyMetricLabels = {
  player_context: 'Roster Cleanup',
  player_benchmark_metrics: 'Benchmark Baseline',
  average_fastball_velocity: 'Fastball Velocity Baseline',
  max_fastball_velocity: 'Max Fastball Baseline',
  strike_percentage: 'Strike % Baseline',
  average_exit_velocity: 'Exit Velocity Baseline',
  max_exit_velocity: 'Max Exit Velocity Baseline',
}

const coachFriendlyMetricLabel = (metric) => {
  const key = String(typeof metric === 'string' ? metric : metric?.metric_key || '').trim()
  if (coachFriendlyMetricLabels[key]) return coachFriendlyMetricLabels[key]
  return typeof metric === 'object' && metric?.display_name
    ? metric.display_name
    : humanizeKey(key || metric?.title || metric?.category, 'Missing data')
}

const missingRowTitle = (row) => coachFriendlyMetricLabel(row)

const missingRowCount = (row) => {
  const missing = n(row?.missing_count)
  const total = n(row?.player_count)
  if (missing !== null && total !== null) return `missing ${fmt1(missing)} of ${fmt1(total)}`
  if (missing !== null) return `missing ${fmt1(missing)}`
  return row?.reason || 'Needs data'
}

const metricPercentile = (metric) => {
  const percentile = n(metric?.percentile_estimate ?? metric?.percentile)
  return percentile === null ? '—' : `${percentile.toFixed(1)}th`
}

const fmtScore = (value, fallback = '—') => fmt1(value, fallback)
const scoreTone = (value) => (n(value) === null ? 'text-slate-400' : toCardBand(value).tone)

const metricGap = (metric, key) => {
  const gap = n(metric?.[key])
  return gap === null ? '—' : fmtValue(gap, metric?.unit || '')
}

const rosterCleanupRow = computed(() =>
  allCriticalMissingRows.value.find((row) => row?.metric_key === 'player_context')
  || benchmarkMissingMetrics.value.find((row) => row?.metric_key === 'player_context')
  || null
)

const rosterCleanupPlayers = computed(() =>
  asArray(rosterCleanupRow.value?.players_missing || rosterCleanupRow.value?.players).slice(0, 6)
)

const collectionPlanTitle = computed(() =>
  collectionPlanRows.value[0]?.title || 'Benchmark data quality is not available yet.'
)

const collectionPlanMetricNames = (plan) =>
  asArray(plan?.metrics)
    .map((metric) => coachFriendlyMetricLabel(metric))
    .filter(Boolean)
    .slice(0, 6)
    .join(', ')

const benchmarkDataQuality = computed(() => {
  const profile = benchmarkProfile.value || {}
  const evidence = profile.evidence || {}

  return {
    confidence: profile.benchmark_confidence || benchmarkSnapshot.value.confidence || 'low',
    priority: dataCollectionPriority.value?.level || null,
    playerCount: n(profile.player_count),
    playersWithData: n(evidence.players_with_benchmark_metrics),
    playersWithoutData: n(evidence.players_without_benchmark_metrics),
    metricCount: n(profile.metric_count),
    criticalCount: allCriticalMissingRows.value.length,
    supportingCount: allSupportingMissingRows.value.length,
    rosterCleanupCount: n(rosterCleanupRow.value?.missing_count),
    nextAction: collectionPlanTitle.value,
    hasDataBlock: practicePlanHasDataBlock.value,
  }
})

const benchmarkCollectionPlan = computed(() => {
  const plan = teamIntelligence.value?.benchmark_collection_plan
  return plan && typeof plan === 'object' ? plan : null
})

const benchmarkCollectionNextAction = computed(() =>
  benchmarkCollectionPlan.value?.next_best_action && typeof benchmarkCollectionPlan.value.next_best_action === 'object'
    ? benchmarkCollectionPlan.value.next_best_action
    : null
)

const benchmarkCollectionSessions = computed(() =>
  asArray(benchmarkCollectionPlan.value?.collection_sessions)
)

const benchmarkCollectionPlayerTasks = computed(() =>
  asArray(benchmarkCollectionPlan.value?.player_tasks).slice(0, 6)
)

const benchmarkCollectionMetricTasks = computed(() =>
  asArray(benchmarkCollectionPlan.value?.metric_tasks).slice(0, 6)
)

const benchmarkCollectionTargets = computed(() => {
  const targets = benchmarkCollectionPlan.value?.completion_targets
  return targets && typeof targets === 'object' ? targets : {}
})

const collectionTaskMetricNames = (metrics) =>
  asArray(metrics)
    .map((metric) => typeof metric === 'string' ? coachFriendlyMetricLabel(metric) : coachFriendlyMetricLabel(metric))
    .filter(Boolean)
    .slice(0, 6)
    .join(', ')

const collectionPlayerNames = (players) =>
  asArray(players)
    .map((player) => player?.player_name || player?.name || player?.player_id)
    .filter(Boolean)
    .slice(0, 6)
    .join(', ')

const benchmarkTaskAssignments = computed(() => {
  const assignments = teamIntelligence.value?.benchmark_task_assignments
  return assignments && typeof assignments === 'object' ? assignments : null
})

const assignableBenchmarkTasks = computed(() =>
  asArray(benchmarkTaskAssignments.value?.assignable_tasks)
)

const draftBenchmarkTasksForSave = computed(() => [
  ...asArray(benchmarkTaskAssignments.value?.team_tasks),
  ...assignableBenchmarkTasks.value,
])

const benchmarkTeamTasks = computed(() =>
  asArray(benchmarkTaskAssignments.value?.team_tasks).slice(0, 6)
)

const benchmarkPlayerTaskGroups = computed(() =>
  asArray(benchmarkTaskAssignments.value?.player_tasks).slice(0, 6)
)

const taskTypeLabel = (type) => ({
  roster_cleanup: 'Roster Cleanup',
  exit_velocity_baseline: 'Exit Velocity Baseline',
  bullpen_baseline: 'Bullpen Baseline',
  long_toss_weighted_ball: 'Long Toss / Weighted Ball',
  strength_baseline: 'Strength Baseline',
  athletic_testing: 'Athletic Testing',
  mobility_screen: 'Mobility Screen',
}[type] || humanizeKey(type, 'Benchmark Task'))

const savedBenchmarkTaskRows = computed(() => asArray(savedBenchmarkTasks.value).slice(0, 20))

const savedDraftBenchmarkTaskIds = computed(() =>
  asArray(savedBenchmarkTasks.value)
    .filter((task) => task?.status === 'draft')
    .map((task) => task?.id)
    .filter(Boolean)
)

const benchmarkTaskStatusCounts = computed(() =>
  asArray(savedBenchmarkTasks.value).reduce((counts, task) => {
    const status = task?.status || 'unknown'
    counts[status] = (counts[status] || 0) + 1
    return counts
  }, {})
)

const responsePayload = (response) => response?.data?.data || response?.data || {}

const refreshSavedBenchmarkTasks = async () => {
  const teamId = resolveTeamId.value
  if (!teamId) return

  const response = await axiosGet(`intelligence/teams/${teamId}/benchmark-tasks`)
  const payload = responsePayload(response)
  savedBenchmarkTasks.value = Array.isArray(payload.tasks) ? payload.tasks : []
}

const generateBenchmarkDraftTasks = async () => {
  const teamId = resolveTeamId.value
  if (!teamId) return

  benchmarkTaskActionLoading.value = 'generate'
  benchmarkTaskActionError.value = ''
  benchmarkTaskActionMessage.value = ''
  try {
    const response = await axiosPost(`intelligence/teams/${teamId}/benchmark-tasks/generate`, { days: 365 })
    const payload = responsePayload(response)
    teamIntelligence.value = {
      ...(teamIntelligence.value || {}),
      benchmark_task_assignments: payload,
    }
    benchmarkTaskActionMessage.value = `Generated ${fmtCount(payload.task_count, '0')} draft task previews.`
  } catch {
    benchmarkTaskActionError.value = 'Could not generate benchmark task previews.'
  } finally {
    benchmarkTaskActionLoading.value = ''
  }
}

const saveBenchmarkDraftTasks = async () => {
  const teamId = resolveTeamId.value
  if (!teamId) return

  benchmarkTaskActionLoading.value = 'save'
  benchmarkTaskActionError.value = ''
  benchmarkTaskActionMessage.value = ''
  try {
    const response = await axiosPost(`intelligence/teams/${teamId}/benchmark-tasks/save-drafts`, {
      days: 365,
      tasks: draftBenchmarkTasksForSave.value,
    })
    const payload = responsePayload(response)
    savedBenchmarkTasks.value = Array.isArray(payload.saved_tasks)
      ? payload.saved_tasks
      : (Array.isArray(payload.tasks) ? payload.tasks : savedBenchmarkTasks.value)
    benchmarkTaskActionMessage.value = `Saved drafts: ${fmtCount(payload.created_count, '0')} created, ${fmtCount(payload.updated_count, '0')} updated, ${fmtCount(payload.skipped_count, '0')} skipped.`
  } catch {
    benchmarkTaskActionError.value = 'Could not save benchmark draft tasks.'
  } finally {
    benchmarkTaskActionLoading.value = ''
  }
}

const assignBenchmarkDraftTasks = async () => {
  const teamId = resolveTeamId.value
  if (!teamId) return

  benchmarkTaskActionLoading.value = 'assign'
  benchmarkTaskActionError.value = ''
  benchmarkTaskActionMessage.value = ''
  try {
    const response = await axiosPost(`intelligence/teams/${teamId}/benchmark-tasks/assign`, {
      task_ids: savedDraftBenchmarkTaskIds.value,
    })
    const payload = responsePayload(response)
    savedBenchmarkTasks.value = Array.isArray(payload.saved_tasks)
      ? payload.saved_tasks
      : (Array.isArray(payload.tasks) ? payload.tasks : savedBenchmarkTasks.value)
    benchmarkTaskActionMessage.value = `Assigned ${fmtCount(payload.assigned_count, '0')} task(s). ${fmtCount(payload.skipped_count, '0')} skipped.`
  } catch {
    benchmarkTaskActionError.value = 'Could not assign benchmark tasks.'
  } finally {
    benchmarkTaskActionLoading.value = ''
  }
}

const playerWeakCategory = (player) => {
  const category = player?.weakest_category
  if (typeof category === 'string') return categoryLabel(category)
  return categoryLabel(category?.category)
}

const playerWeakMetric = (player) => {
  const metric = asArray(player?.weakest_metrics)[0]
  return metric?.display_name || humanizeKey(metric?.metric_key)
}

const teamDataGaps = computed(() =>
  Array.isArray(teamIntelligence.value?.data_gaps) ? teamIntelligence.value.data_gaps : []
)

const teamSignals = computed(() =>
  Array.isArray(teamIntelligence.value?.signals) ? teamIntelligence.value.signals : []
)

const teamScoreValues = computed(() => Object.entries(teamIntelligence.value?.scores || {})
  .map(([key, value]) => ({ key, value: n(value) }))
  .filter((row) => row.value !== null)
)

const teamDevelopmentIndex = computed(() => {
  const scoreAverage = round1(average(teamScoreValues.value.map((row) => row.value)))
  return scoreAverage ?? tdi.value
})

const topTeamScore = computed(() => {
  const sorted = [...teamScoreValues.value].sort((a, b) => b.value - a.value)
  return sorted[0] || null
})

const lowTeamScore = computed(() => {
  const sorted = [...teamScoreValues.value].sort((a, b) => a.value - b.value)
  return sorted[0] || null
})

const playerTypeCounts = computed(() => {
  const counts = new Map()
  for (const snapshot of teamPlayersIntelligence.value) {
    for (const label of snapshot?.dna?.player_type_labels || []) {
      counts.set(label, (counts.get(label) || 0) + 1)
    }
  }
  return [...counts.entries()]
    .sort((a, b) => b[1] - a[1])
    .map(([label, count]) => ({ label, count }))
})

const primaryStrengthCounts = computed(() => {
  const counts = new Map()
  for (const snapshot of teamPlayersIntelligence.value) {
    const strength = snapshot?.dna?.primary_strength
    if (strength) counts.set(strength, (counts.get(strength) || 0) + 1)
  }
  return [...counts.entries()]
    .sort((a, b) => b[1] - a[1])
    .map(([key, count]) => ({ key, label: categoryLabel(key), count }))
})

const teamDna = computed(() => {
  const primary = primaryStrengthCounts.value[0] || null
  const type = playerTypeCounts.value[0] || null
  const need = lowTeamScore.value

  return {
    label: type?.label || primary?.label || 'Needs More Player Data',
    what: type
      ? `${type.count} player(s) currently profile as ${type.label}.`
      : `${teamPlayersIntelligence.value.length || 0} player intelligence snapshot(s) available.`,
    why: primary
      ? `The roster's strongest current signal is ${primary.label}, based on player DNA snapshots.`
      : 'Team DNA improves as player assessments and scored sessions are added.',
    next: need
      ? `Build the next team block around ${categoryLabel(need.key)}.`
      : 'Score bullpen, BP, exit velocity, and assessment sessions to sharpen team identity.',
  }
})

const cardAnswer = (what, why, next) => ({ what, why, next })

const playerRows = computed(() => {
  return (board.value || []).map((p) => {
    const snapshot = playerSnapshotFor(p?.id)
    const dna = snapshot?.dna || {}
    const projections = snapshot?.projections || {}
    const limiters = Array.isArray(snapshot?.limiters) ? snapshot.limiters : []
    const recs = Array.isArray(snapshot?.recommendations) ? snapshot.recommendations : []
    const fit = p?.fitness || {}
    const ranks = p?.fitness_rank || {}

    // Single source of truth: the canonical athletic-index strength_score
    // (mirrored onto the fitness row), matching the player views. Fall back to
    // the rank-based blend only when a player has no scored assessment yet.
    const strengthScore = n(fit.strength_score) ?? average([
      rankPercent(ranks.bench_press),
      rankPercent(ranks.front_squat),
      rankPercent(ranks.back_squat),
      rankPercent(ranks.dead_lift),
      rankPercent(ranks.power_clean),
    ])

    const mobilityScore = n(fit.mobility_score)
    const longTossScore = p?.coverage?.long_toss > 0 ? clamp(55 + p.coverage.long_toss * 7, 0, 95) : null
    const bullpenScore = firstNumber(p?.scores?.bullpen, snapshot?.scores?.bullpen)
    const liveAbScore = n(p?.scores?.batting)
    const exitVelocityScore = firstNumber(p?.scores?.ev, snapshot?.scores?.exit_velocity)

    const weights = [
      { key: 'mobility', value: mobilityScore, weight: 0.15 },
      { key: 'strength', value: strengthScore, weight: 0.15 },
      { key: 'long_toss', value: longTossScore, weight: 0.15 },
      { key: 'bullpen', value: bullpenScore, weight: 0.20 },
      { key: 'live_ab', value: liveAbScore, weight: 0.20 },
      { key: 'exit_velocity', value: exitVelocityScore, weight: 0.15 },
    ]
    const valid = weights.filter((w) => w.value !== null)
    const pdi = computePDI(valid)

    const prevPdi = average([n(p?.prev_scores?.bullpen), n(p?.prev_scores?.batting), n(p?.prev_scores?.ev)])
    const pdiChange = pdi !== null && prevPdi !== null ? round1(pdi - prevPdi) : null

    const componentList = [
      ['Mobility', mobilityScore],
      ['Strength', strengthScore],
      ['Long Toss', longTossScore],
      ['Bullpen', bullpenScore],
      ['Live AB', liveAbScore],
      ['Exit Velo', exitVelocityScore],
    ].filter((x) => x[1] !== null)
    const best = componentList.length ? [...componentList].sort((a, b) => b[1] - a[1])[0] : ['No Data', null]
    const need = componentList.length ? [...componentList].sort((a, b) => a[1] - b[1])[0] : ['No Data', null]

    const riskScore = computeRiskIndex({
      pdiChange,
      status: p?.status,
      mobility: mobilityScore,
      bullpen: bullpenScore,
      exitVelocity: exitVelocityScore,
    })
    const playerRiskLevel = riskLevel(riskScore)

    const currentMetric = firstNumber(p?.scores?.overall, average(Object.values(snapshot?.scores || {})))
    const prevMetric = n(p?.prev_scores?.overall)
    const change = currentMetric !== null && prevMetric !== null ? currentMetric - prevMetric : 0
    const weightedTrend = change
    const projected30 = currentMetric !== null ? round1(currentMetric + weightedTrend * 1.5) : null
    const projected60 = currentMetric !== null ? round1(currentMetric + weightedTrend * 2.5) : null
    const projected90 = currentMetric !== null ? round1(currentMetric + weightedTrend * 3.5) : null
    const confidence = snapshot ? 'Medium' : (p?.coverage?.total >= 6 ? 'High' : p?.coverage?.total >= 3 ? 'Medium' : 'Low')

    const primaryStrength = safeText(dna?.primary_strength_detail?.category || dna?.primary_strength || best[0], best[0])
    const biggestNeed = safeText(dna?.biggest_need_detail?.category || dna?.biggest_need || limiters[0]?.title || need[0], need[0])
    const topRecommendation = recs[0] || null

    return {
      id: p.id,
      name: p.name,
      status: normalizeStatus(p.status),
      trend: normalizeTrend(p.trend),
      pdi,
      pdiChange,
      bestStrength: categoryLabel(primaryStrength),
      biggestNeed: categoryLabel(biggestNeed),
      playerType: (dna?.player_type_labels || [])[0] || 'Needs Data',
      limiterCount: limiters.length,
      recommendationTitle: topRecommendation?.title || null,
      riskScore,
      riskLevel: playerRiskLevel,
      projectionSummary: projected90 !== null ? `${projected90}` : '—',
      projection: { projected30, projected60, projected90, confidence },
      metrics: {
        strike_percentage: projectionCurrent(snapshot, 'strike_percentage'),
        top_pitch_velocity: projectionCurrent(snapshot, 'bullpen_max_velocity'),
        average_fastball_velocity: projectionCurrent(snapshot, 'bullpen_avg_velocity'),
        pitcher_swing_miss_percentage: null,
        bullpen_score: bullpenScore,
        long_toss_max_distance: firstMeaningfulNumber(
          projectionCurrent(snapshot, 'long_toss_max_distance'),
          projectionCurrent(snapshot, 'long_toss_avg_distance'),
        ),
        long_toss_carry_score: longTossScore,
        average_exit_velocity: projectionCurrent(snapshot, 'exit_velocity_avg'),
        top_exit_velocity: firstMeaningfulNumber(projectionCurrent(snapshot, 'exit_velocity_max'), p?.top_ev_mph),
        hard_hit_percentage: null,
        line_drive_percentage: null,
        hitter_swing_miss_percentage: null,
        damage_index: average([n(p?.scores?.batting), n(p?.scores?.ev)]),
      },
      prevMetrics: {
        bullpen_score: n(p?.prev_scores?.bullpen),
        average_exit_velocity: n(p?.prev_scores?.ev),
        top_exit_velocity: n(p?.prev_scores?.ev),
      },
      intelligence: snapshot,
      projections,
      limiters,
      recommendations: recs,
      alerts: [],
    }
  })
})

const playersWithPercentile = computed(() => {
  const sorted = [...playerRows.value].sort((a, b) => (b.pdi ?? -1) - (a.pdi ?? -1))
  return sorted.map((p, idx) => ({
    ...p,
    percentile: p.pdi !== null ? Math.round(((sorted.length - idx) / Math.max(1, sorted.length)) * 100) : null,
  }))
})

const teamComponentScores = computed(() => ({
  strength: round1(firstNumber(teamIntelligence.value?.scores?.strength, average((board.value || []).map((p) => p?.fitness?.strength_score)))),
  mobility: round1(firstNumber(teamIntelligence.value?.scores?.mobility, average((board.value || []).map((p) => p?.fitness?.mobility_score)))),
  bullpen: round1(firstNumber(teamIntelligence.value?.scores?.bullpen, average((board.value || []).map((p) => p?.scores?.bullpen)))),
  long_toss: round1(n(perf.value?.long_toss?.lts?.lts)),
  live_ab: round1(firstNumber(teamIntelligence.value?.scores?.batting, average((board.value || []).map((p) => p?.scores?.batting)))),
  exit_velocity: round1(firstNumber(teamIntelligence.value?.scores?.exit_velocity, average((board.value || []).map((p) => p?.scores?.ev)))),
}))

const tdi = computed(() => computeTDI(playersWithPercentile.value.map((p) => p.pdi)))
const prevTdi = computed(() => computeTDI(playersWithPercentile.value.map((p) => (p.pdi !== null && p.pdiChange !== null ? p.pdi - p.pdiChange : null))))
const tdiChange = computed(() => (tdi.value !== null && prevTdi.value !== null ? round1(tdi.value - prevTdi.value) : null))
const teamPercentile = computed(() => (tdi.value !== null ? clamp(Math.round((tdi.value / 100) * 100), 1, 99) : null))

const swingMissCreated = computed(() => {
  const sm = dashboard.value?.swing_miss_take_percents || {}
  if (!hasPositiveSample(sm?.totals)) return null
  const keys = ['FB', 'CH', 'CB', 'SL', 'OTHER']
  return round1(keys.reduce((sum, k) => sum + (n(sm?.[k]?.SM) || 0), 0))
})

const hitterSwingMissAgainst = computed(() => {
  const hitTypes = dashboard.value?.type_hits_batting_percents || {}
  return valueWithSample(hitTypes?.SM?.percent ?? hitTypes?.['SM/F']?.percent, [hitTypes?.effective, hitTypes?.totals], { zeroIsMissing: false })
})
const lineDrivePct = computed(() => {
  const hitTypes = dashboard.value?.type_hits_batting_percents || {}
  return valueWithSample(hitTypes?.LD?.percent, [hitTypes?.effective, hitTypes?.totals], { zeroIsMissing: false })
})
const hardHitPct = computed(() => valueWithSample(perf.value?.batting?.compScore, [perf.value?.batting?.total], { zeroIsMissing: false }))

const priorityMetrics = computed(() => {
  const bullpenNow = firstNumber(teamIntelligence.value?.scores?.bullpen, perf.value?.bullpen?.bps?.bps)
  const bullpenPrev = average((board.value || []).map((p) => p?.prev_scores?.bullpen))
  const avgFbNow = average(playersWithPercentile.value.map((p) => p.metrics?.average_fastball_velocity))
    ?? valueWithSample(dashboard.value?.pitch_velocity_average?.FB, [dashboard.value?.pitch_velocity_average?.totals])
  const strikeNow = average(playersWithPercentile.value.map((p) => p.metrics?.strike_percentage))
    ?? valueWithSample(dashboard.value?.pitch_throws?.strike_percent, [dashboard.value?.pitch_throws?.totals], { zeroIsMissing: false })
  const avgEvNow = average(playersWithPercentile.value.map((p) => p.metrics?.average_exit_velocity))
    ?? valueWithSample(perf.value?.batting?.avgEV, [perf.value?.batting?.total])
  const topEvNow = average(playersWithPercentile.value.map((p) => p.metrics?.top_exit_velocity))
    ?? valueWithSample(perf.value?.batting?.topEV, [perf.value?.batting?.total])
  const evNow = firstNumber(teamIntelligence.value?.scores?.exit_velocity, average((board.value || []).map((p) => p?.scores?.ev)))
  const evPrev = average((board.value || []).map((p) => p?.prev_scores?.ev))
  const ltScore = valueWithSample(perf.value?.long_toss?.lts?.lts)
  const topPitch = average(playersWithPercentile.value.map((p) => p.metrics?.top_pitch_velocity))
    ?? valueWithSample(perf.value?.bullpen?.bps?.topVelo, [perf.value?.bullpen?.bps?.total])

  return [
    { key: 'strike_percentage', value: strikeNow, delta: null, insight: 'Prioritize fastball command if below 65%.' },
    { key: 'top_pitch_velocity', value: topPitch, delta: null, insight: 'Track top-end arm speed gains by week.' },
    { key: 'average_fastball_velocity', value: avgFbNow, delta: null, insight: 'Use long toss-to-mound transfer block when flat.' },
    { key: 'pitcher_swing_miss_percentage', value: swingMissCreated.value, delta: null, insight: 'Higher is better for pitchers.' },
    { key: 'long_toss_max_distance', value: valueWithSample(perf.value?.long_toss?.distance_avg?.max, [perf.value?.long_toss?.distance_avg?.team_totals?.throws, perf.value?.long_toss?.distance_avg?.throws]), delta: null, insight: 'Build arm endurance and intent.' },
    { key: 'bullpen_score', value: bullpenNow, delta: bullpenNow !== null && bullpenPrev !== null ? round1(bullpenNow - bullpenPrev) : null, insight: 'Blend command + velocity + execution.' },
    { key: 'average_exit_velocity', value: avgEvNow, delta: null, insight: 'Power floor from quality contact.' },
    { key: 'top_exit_velocity', value: topEvNow, delta: null, insight: 'Top-end power ceiling.' },
    { key: 'exit_velocity_growth', value: evNow !== null && evPrev !== null ? round1(evNow - evPrev) : null, delta: null, insight: 'Growth in EV over recent sessions.' },
    { key: 'hard_hit_percentage', value: hardHitPct.value, delta: null, insight: 'Improve barrel quality to raise this.' },
    { key: 'line_drive_percentage', value: lineDrivePct.value, delta: null, insight: 'Stabilize launch profile around LD window.' },
    { key: 'hitter_swing_miss_percentage', value: hitterSwingMissAgainst.value, delta: null, insight: 'Lower is better for hitters.' },
    { key: 'damage_index', value: round1(average([hardHitPct.value, avgEvNow])), delta: null, insight: 'Combines quality and velocity output.' },
    { key: 'long_toss_carry_score', value: ltScore, delta: null, insight: 'Carry and distance drive arm-strength trends.' },
  ]
})

const metricCardData = computed(() => {
  return priorityMetrics.value.map((m) => {
    const meta = metricMeta[m.key] || { label: m.key, unit: '', goal: null }
    const goal = meta.goal
    const lowerBetter = meta.lowerBetter === true
    const value = n(m.value)
    const delta = n(m.delta)
    const meets = goal !== null && value !== null
      ? (lowerBetter ? value <= goal : value >= goal)
      : null
    const tone = value === null ? 'text-slate-300' : (meets ? 'text-emerald-300' : 'text-red-300')
    return {
      ...m,
      label: meta.label,
      unit: meta.unit,
      goal,
      lowerBetter,
      value,
      delta,
      tone,
      status: value === null ? 'No Data' : (meets ? 'On Target' : 'Below Target'),
    }
  })
})

const needsAttention = computed(() => {
  const toSeverity = (m) => {
    if (m.value === null || m.goal === null) return 0
    const diff = m.lowerBetter ? (m.value - m.goal) : (m.goal - m.value)
    return diff <= 0 ? 0 : Math.round(diff * 10)
  }
  return metricCardData.value
    .map((m) => ({ ...m, severity: toSeverity(m) }))
    .sort((a, b) => b.severity - a.severity)
    .slice(0, 6)
})

const pitchingBoardRows = computed(() => {
  const improving = playersWithPercentile.value.filter((p) => p.trend === 'Improving').length
  const declining = playersWithPercentile.value.filter((p) => p.trend === 'Declining').length
  const byKey = (k) => metricCardData.value.find((m) => m.key === k)
  return [
    byKey('average_fastball_velocity'),
    byKey('top_pitch_velocity'),
    byKey('strike_percentage'),
    byKey('pitcher_swing_miss_percentage'),
    byKey('bullpen_score'),
    byKey('long_toss_max_distance'),
    byKey('long_toss_carry_score'),
  ].filter(Boolean).map((r) => ({ ...r, improving, declining }))
})

const hittingBoardRows = computed(() => {
  const byKey = (k) => metricCardData.value.find((m) => m.key === k)
  return [
    byKey('average_exit_velocity'),
    byKey('top_exit_velocity'),
    byKey('exit_velocity_growth'),
    byKey('hard_hit_percentage'),
    byKey('line_drive_percentage'),
    byKey('damage_index'),
    byKey('hitter_swing_miss_percentage'),
  ].filter(Boolean)
})

const leaderboardMostImproved = computed(() => [...playersWithPercentile.value]
  .sort((a, b) => (b.pdiChange ?? -999) - (a.pdiChange ?? -999))
  .slice(0, 5))
const leaderboardNeedsAttention = computed(() => [...playersWithPercentile.value]
  .sort((a, b) => (b.riskScore ?? 0) - (a.riskScore ?? 0))
  .slice(0, 5))

const projectionRows = computed(() => {
  return playersWithPercentile.value.map((p) => {
    const cur = n(p.metrics[selectedMetric.value])
    const prev = n(p.prevMetrics[selectedMetric.value])
    const proj = projectMetric({
      current: cur,
      previous: prev,
      metricKey: selectedMetric.value,
      lowerBetter: metricMeta[selectedMetric.value]?.lowerBetter === true,
    })

    const confidence = p?.projection?.confidence || (p?.riskScore < 25 ? 'High' : p?.riskScore < 45 ? 'Medium' : 'Low')
    return {
      ...p,
      current: proj.current,
      previous: proj.previous,
      projected30: proj.projected30,
      projected60: proj.projected60,
      projected90: proj.projected90,
      confidence,
    }
  })
})

watch(playersWithPercentile, (rows) => {
  if (!rows.length) {
    selectedPlayers.value = []
    return
  }
  if (!selectedPlayers.value.length) {
    selectedPlayers.value = rows.slice(0, 3).map((p) => p.id)
  }
}, { immediate: true })

const selectedProjectionRows = computed(() => {
  const set = new Set(selectedPlayers.value.map(String))
  return projectionRows.value.filter((p) => set.has(String(p.id)))
})

const sparklinePoints = (vals = []) => {
  const clean = vals.map(n).filter((v) => v !== null)
  if (!clean.length) return '0,20 100,20'
  const min = Math.min(...clean)
  const max = Math.max(...clean)
  const span = max - min || 1
  const toY = (v) => 34 - (((v - min) / span) * 28)
  const xStep = 100 / Math.max(1, vals.length - 1)
  return vals.map((v, i) => `${Math.round(i * xStep)},${Math.round(v === null ? 20 : toY(v))}`).join(' ')
}

const teamAlerts = computed(() => {
  const alerts = []
  for (const signal of teamSignals.value.slice(0, 3)) {
    alerts.push({
      severity: signal?.severity === 'warning' ? 'high' : 'medium',
      title: signal?.title || 'Team intelligence signal',
      body: signal?.message || 'Review this team signal before planning the next block.',
      next: 'Open affected player profiles and review evidence.',
    })
  }
  for (const gap of teamDataGaps.value.slice(0, 4)) {
    alerts.push({
      severity: 'medium',
      title: `Data gap: ${String(gap?.missing_field || 'missing data').replaceAll('_', ' ')}`,
      body: gap?.impact || 'This missing data limits team intelligence quality.',
      next: gap?.recommended_collection_action || 'Collect the missing data in the next player workflow.',
    })
  }
  for (const need of needsAttention.value.slice(0, 5)) {
    if (need.severity > 0) {
      alerts.push({
        severity: need.severity > 35 ? 'high' : need.severity > 15 ? 'medium' : 'low',
        title: `${need.label} needs attention`,
        body: `${need.label} is ${need.status.toLowerCase()} (${fmtValue(need.value, need.unit)}).`,
        next: need.insight || 'Build the next training block around this metric.',
      })
    }
  }
  const risky = playersWithPercentile.value.filter((p) => p.riskScore > 40)
  if (risky.length) {
    alerts.push({
      severity: 'high',
      title: `${risky.length} players above risk threshold`,
      body: 'Recommend recovery + mobility block and workload check.',
      next: 'Review player workload, recovery, and recent trend evidence.',
    })
  }
  return alerts.slice(0, 8)
})

const roadmap = computed(() => {
  if (teamRecommendations.value.length) {
    return teamRecommendations.value.slice(0, 5).map((rec, idx) => ({
      priority: idx + 1,
      title: rec?.title || 'Team recommendation',
      reason: rec?.why || 'The intelligence layer flagged this as a team priority.',
      action: rec?.action || 'Review the affected players and plan the next training block.',
      confidence: rec?.confidence || 'medium',
    }))
  }

  return needsAttention.value.slice(0, 4).map((need, idx) => {
    const map = {
      strike_percentage: 'Fastball command bullpen with strike challenge constraints.',
      average_fastball_velocity: 'Long-toss to mound transfer progression.',
      long_toss_max_distance: 'Arm endurance long toss ladder + pulldown integration.',
      hitter_swing_miss_percentage: 'Barrel-control rounds and two-strike contact plan.',
      line_drive_percentage: 'Middle-middle line-drive challenge rounds.',
      hard_hit_percentage: 'Intent + quality-of-contact training blocks.',
      mobility_score: 'Pre-throw shoulder/hip/ankle mobility circuit.',
    }
    return {
      priority: idx + 1,
      title: need.label,
      reason: `${need.label} is ${need.status.toLowerCase()} (${fmtValue(need.value, need.unit)}).`,
      action: map[need.key] || 'Target this metric with focused team session design this week.',
      confidence: 'fallback',
    }
  })
})

const teamIndexAnswer = computed(() => cardAnswer(
  teamDevelopmentIndex.value !== null
    ? `The team development index is ${fmt1(teamDevelopmentIndex.value)}.`
    : 'The team index needs more scored player data.',
  topTeamScore.value
    ? `${categoryLabel(topTeamScore.value.key)} is currently the strongest team signal.`
    : 'The index matters because it combines available player intelligence into one roster health view.',
  lowTeamScore.value
    ? `Prioritize ${categoryLabel(lowTeamScore.value.key)} in the next team block.`
    : 'Score current player sessions to establish a reliable team baseline.'
))

const needsAttentionAnswer = computed(() => {
  const topNeed = needsAttention.value[0]
  return cardAnswer(
    topNeed ? `${topNeed.label} is the top team need.` : 'No major team need has enough data yet.',
    topNeed
      ? `${topNeed.label} affects whether the roster can transfer training into game performance.`
      : 'Needs attention becomes more useful once player snapshots have comparable session data.',
    topNeed?.insight || roadmap.value[0]?.action || 'Review player data gaps and score the next relevant sessions.'
  )
})

const pitchingPulseAnswer = computed(() => {
  const strike = metricCardData.value.find((m) => m.key === 'strike_percentage')
  const velo = metricCardData.value.find((m) => m.key === 'average_fastball_velocity')
  return cardAnswer(
    `Pitching pulse: FB ${fmtValue(velo?.value, velo?.unit)}, Strike ${fmtValue(strike?.value, strike?.unit)}.`,
    'Pitching pulse shows whether arm speed and command are developing together.',
    strike?.value !== null && strike.value < (strike.goal || 65)
      ? 'Run fastball command and edge-location bullpens.'
      : 'Keep tracking bullpen quality, velocity, and strike percentage each week.'
  )
})

const hittingPulseAnswer = computed(() => {
  const ev = metricCardData.value.find((m) => m.key === 'average_exit_velocity')
  const ld = metricCardData.value.find((m) => m.key === 'line_drive_percentage')
  return cardAnswer(
    `Hitting pulse: Avg EV ${fmtValue(ev?.value, ev?.unit)}, LD ${fmtValue(ld?.value, ld?.unit)}.`,
    'Hitting pulse shows whether power is pairing with playable contact quality.',
    ev?.value === null && ld?.value === null
      ? 'Score BP, cage, or exit velocity sessions to build the hitting pulse.'
      : 'Use line-drive constraint rounds before max-intent damage rounds.'
  )
})

const openPlayer = (player) => {
  if (!player?.id) return
  router.push({
    path: `/development/player/${player.id}`,
    query: { playerName: player.name || '', teamId: resolveTeamId.value || '' },
  })
}

const trendChip = (delta) => {
  const d = n(delta)
  if (d === null) return { text: 'No Trend', cls: 'text-slate-300' }
  if (d === 0) return { text: '→ Stable', cls: 'text-slate-300' }
  if (d > 0) return { text: `↑ +${fmt1(d)}`, cls: 'text-emerald-300' }
  return { text: `↓ ${fmt1(d)}`, cls: 'text-red-300' }
}

const normalizePlayerName = (name) => String(name ?? '').trim().toLowerCase().replace(/\s+/g, ' ')

const openPriorityTop10 = (metric) => {
  if (!metric?.key) return
  priorityTop10Modal.value = {
    open: true,
    key: metric.key,
    label: metric.label,
    unit: metric.unit || '',
  }
}

const closePriorityTop10 = () => {
  priorityTop10Modal.value.open = false
}

const priorityTop10Rows = computed(() => {
  const key = priorityTop10Modal.value.key
  if (!key) return []

  const byName = new Map()
  for (const p of playersWithPercentile.value) {
    const playerName = p?.name || 'Player'
    const norm = normalizePlayerName(playerName)
    if (!norm) continue
    const value = n(p?.metrics?.[key])
    if (value === null) continue

    const current = byName.get(norm)
    if (!current || value > current.value) {
      byName.set(norm, {
        id: p?.id,
        name: playerName,
        value,
        trend: p?.trend || '—',
      })
    }
  }

  return [...byName.values()]
    .sort((a, b) => b.value - a.value)
    .slice(0, 10)
})
</script>

<template>
  <Layout>
    <div class="mx-auto w-full max-w-[1700px] space-y-4 px-4 py-6">
      <div class="rounded-xl border border-white/10 bg-slate-900/70 p-3">
        <div class="flex flex-wrap items-center gap-2">
          <RouterLink to="/dashboard?tab=development" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">← Back to Dashboard</RouterLink>
          <RouterLink to="/development" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Player</RouterLink>
          <RouterLink to="/development/team" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Team</RouterLink>
          <RouterLink to="/development/coach" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Coach</RouterLink>
          <RouterLink to="/development/admin/benchmarks" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Admin</RouterLink>
        </div>
      </div>

      <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-4">
        <h1 class="text-2xl font-semibold text-white">FMTRX Team Development Command Center</h1>
        <p class="mt-1 text-sm text-slate-300">Coach decision dashboard: team quality, weaknesses, risk, trends, and next training priorities.</p>
      </div>

      <div v-if="loading" class="rounded-xl border border-white/10 bg-slate-900/70 p-3 text-sm text-slate-300">
        Loading command center metrics...
      </div>
      <div v-if="loadError" class="rounded-xl border border-amber-400/20 bg-amber-500/10 p-3 text-sm text-amber-200">
        {{ loadError }}
      </div>

      <template v-if="!loading && (playersWithPercentile.length || hasBenchmarkProfile)">
        <!-- A + G: TDI + Needs Attention -->
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 xl:col-span-2">
            <p class="text-xs uppercase tracking-widest text-white/40">Team Development Index</p>
            <div class="mt-2 flex flex-wrap items-end gap-4">
              <p class="text-5xl font-black" :class="toCardBand(teamDevelopmentIndex).tone">{{ fmt1(teamDevelopmentIndex) }}</p>
              <div class="space-y-1 text-sm text-slate-300">
                <p>Grade: <span class="font-semibold text-white">{{ toCardBand(teamDevelopmentIndex).label }}</span></p>
                <p>Team Percentile: <span class="font-semibold text-white">{{ fmtRank(teamPercentile) }}</span></p>
                <p>Trend: <span :class="trendChip(tdiChange).cls" class="font-semibold">{{ trendChip(tdiChange).text }}</span></p>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2">
              <div class="rounded-lg border border-cyan-300/15 bg-cyan-500/10 p-3">
                <p class="text-[10px] uppercase tracking-widest text-cyan-200/70">Team DNA</p>
                <p class="mt-1 text-lg font-black text-white">{{ teamDna.label }}</p>
                <p class="mt-1 text-xs text-slate-300"><span class="font-black text-white">What:</span> {{ teamDna.what }}</p>
                <p class="mt-1 text-xs text-slate-300"><span class="font-black text-white">Why:</span> {{ teamDna.why }}</p>
                <p class="mt-1 text-xs text-red-200"><span class="font-black text-white">Next:</span> {{ teamDna.next }}</p>
              </div>
              <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                <p class="text-[10px] uppercase tracking-widest text-white/40">Index Read</p>
                <p class="mt-1 text-xs text-slate-300"><span class="font-black text-white">What:</span> {{ teamIndexAnswer.what }}</p>
                <p class="mt-1 text-xs text-slate-300"><span class="font-black text-white">Why:</span> {{ teamIndexAnswer.why }}</p>
                <p class="mt-1 text-xs text-red-200"><span class="font-black text-white">Next:</span> {{ teamIndexAnswer.next }}</p>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 md:grid-cols-3">
              <div v-for="(v, k) in teamComponentScores" :key="k" class="rounded-lg border border-white/10 bg-white/5 p-2">
                <p class="text-[10px] uppercase tracking-wider text-white/40">{{ String(k).replace('_', ' ') }}</p>
                <p class="text-lg font-black text-white">{{ fmt1(v) }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <h3 class="text-lg font-semibold text-white">Team Needs Attention</h3>
            <div class="mt-2 rounded-lg border border-white/10 bg-white/5 p-2 text-xs">
              <p class="text-slate-300"><span class="font-black text-white">What:</span> {{ needsAttentionAnswer.what }}</p>
              <p class="mt-1 text-slate-300"><span class="font-black text-white">Why:</span> {{ needsAttentionAnswer.why }}</p>
              <p class="mt-1 text-red-200"><span class="font-black text-white">Next:</span> {{ needsAttentionAnswer.next }}</p>
            </div>
            <div class="mt-3 space-y-2 text-sm">
              <div v-for="m in needsAttention" :key="m.key" class="flex items-center justify-between rounded-md border border-white/10 bg-white/5 px-2 py-1.5">
                <span class="text-slate-200">{{ m.label }}</span>
                <span class="font-semibold" :class="m.severity > 30 ? 'text-red-300' : m.severity > 10 ? 'text-yellow-300' : 'text-emerald-300'">
                  {{ fmtValue(m.value, m.unit) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Benchmark Intelligence -->
        <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="text-xs uppercase tracking-widest text-red-300">Benchmark Intelligence</p>
              <h3 class="mt-1 text-xl font-semibold text-white">Team Benchmark Profile</h3>
            </div>
            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-wider text-slate-300">
              Confidence {{ humanizeKey(benchmarkSnapshot.confidence) }}
            </span>
          </div>

          <div v-if="!hasBenchmarkProfile" class="mt-3 rounded-lg border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            Benchmark profile not available yet.
          </div>

          <template v-else>
            <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-3">
              <div class="rounded-lg border border-cyan-300/15 bg-cyan-500/10 p-3">
                <p class="text-[10px] uppercase tracking-widest text-cyan-200/70">Today's Primary Focus</p>
                <p class="mt-1 text-2xl font-black text-white">{{ primaryFocusCard.title }}</p>
                <p class="mt-2 text-xs text-slate-300"><span class="font-black text-white">Why:</span> {{ primaryFocusCard.why }}</p>
                <p class="mt-1 text-xs text-red-200"><span class="font-black text-white">Next:</span> {{ primaryFocusCard.action }}</p>
                <p class="mt-2 text-[10px] uppercase tracking-wider text-white/40">
                  Confidence {{ humanizeKey(primaryFocusCard.confidence) }}
                  <span v-if="primaryFocusCard.expectedGain"> · Gain {{ primaryFocusCard.expectedGain }}</span>
                </p>
              </div>

              <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                <p class="text-[10px] uppercase tracking-widest text-white/40">Team Benchmark Snapshot</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                  <div class="rounded-md bg-slate-950/40 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Players</p>
                    <p class="text-xl font-black text-white">{{ fmt1(benchmarkSnapshot.playerCount) }}</p>
                  </div>
                  <div class="rounded-md bg-slate-950/40 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Metrics</p>
                    <p class="text-xl font-black text-white">{{ fmt1(benchmarkSnapshot.metricCount) }}</p>
                  </div>
                  <div class="rounded-md bg-slate-950/40 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Research</p>
                    <p class="text-xl font-black text-white">{{ fmtValue(benchmarkSnapshot.researchShare, '%') }}</p>
                  </div>
                  <div class="rounded-md bg-slate-950/40 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Population</p>
                    <p class="text-xl font-black text-white">{{ fmtValue(benchmarkSnapshot.populationShare, '%') }}</p>
                  </div>
                </div>
                <p v-if="benchmarkSnapshot.populationShare === 0" class="mt-3 text-xs text-slate-300">
                  Research benchmarks active. FMTRX population learning improves as more data is collected.
                </p>
              </div>

              <div class="rounded-lg border border-amber-300/20 bg-amber-500/10 p-3">
                <p class="text-[10px] uppercase tracking-widest text-amber-200/70">Data Collection Priority</p>
                <p class="mt-1 text-2xl font-black text-white">{{ humanizeKey(dataCollectionPriority?.level || 'none') }}</p>
                <p class="mt-2 text-xs text-slate-300">
                  Critical {{ fmt1(criticalMissingRows.length) }} · Supporting {{ fmt1(supportingMissingRows.length) }} · Optional {{ fmt1(optionalMissingRows.length) }}
                </p>
                <p v-if="practicePlanHasDataBlock" class="mt-2 rounded-md border border-red-300/20 bg-red-500/10 p-2 text-xs font-semibold text-red-200">
                  Baseline collection added to today's plan.
                </p>
                <p v-else class="mt-2 text-xs text-slate-300">No baseline collection block is currently attached to today's plan.</p>
              </div>
            </div>

            <div class="mt-4 rounded-lg border border-cyan-300/20 bg-cyan-500/10 p-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="text-[10px] uppercase tracking-widest text-cyan-200/80">Benchmark Source Mix</p>
                  <h4 class="mt-1 text-lg font-semibold text-white">Research + FMTRX Population Learning</h4>
                </div>
                <span
                  class="rounded-full border px-3 py-1 text-xs uppercase tracking-wider"
                  :class="benchmarkSourceMix.populationActive ? 'border-cyan-300/30 bg-cyan-500/15 text-cyan-100' : 'border-white/10 bg-white/5 text-slate-300'"
                >
                  {{ benchmarkSourceMix.populationActive ? 'Composite Active' : 'Research Active' }}
                </span>
              </div>

              <p v-if="!benchmarkSourceMix.available" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-300">
                Benchmark source mix is not available yet.
              </p>

              <template v-else>
                <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Research</p>
                    <p class="text-xl font-black text-white">{{ fmtValue(benchmarkSourceMix.researchPercent, '%') }}</p>
                    <p class="mt-1 text-[10px] text-slate-300">{{ fmtCount(benchmarkSourceMix.researchCount, '0') }} research-only metrics</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">FMTRX Population</p>
                    <p class="text-xl font-black text-white">{{ fmtValue(benchmarkSourceMix.populationPercent, '%') }}</p>
                    <p class="mt-1 text-[10px] text-slate-300">{{ fmtCount(benchmarkSourceMix.populationCount, '0') }} population metrics</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Composite</p>
                    <p class="text-xl font-black text-white">{{ fmtValue(benchmarkSourceMix.compositePercent, '%') }}</p>
                    <p class="mt-1 text-[10px] text-slate-300">{{ fmtCount(benchmarkSourceMix.compositeCount, '0') }} blended metrics</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Avg Bucket</p>
                    <p class="text-xl font-black text-white">{{ fmtCount(benchmarkSourceMix.averageBucketCount, '0') }}</p>
                    <p class="mt-1 text-[10px] text-slate-300">population sample size</p>
                  </div>
                </div>

                <div class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-200">
                  <p><span class="font-black text-white">What:</span> {{ benchmarkSourceMix.activeSourceText }}</p>
                  <p class="mt-1"><span class="font-black text-white">Why:</span> Population benchmark confidence improves as more players are added.</p>
                  <p class="mt-1 text-cyan-100"><span class="font-black text-white">Rule:</span> {{ benchmarkSourceMix.guidance }}</p>
                </div>

                <div class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3">
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-[10px] uppercase tracking-widest text-cyan-200/80">Metric Source Status</p>
                    <p class="text-[10px] uppercase tracking-widest text-white/35">Research percentile · Population percentile · Bucket</p>
                  </div>
                  <div class="mt-2 grid grid-cols-1 gap-2 lg:grid-cols-2">
                    <button
                      v-for="metric in sourceMetricRows"
                      :key="`source-${metric.metric_key || metric.display_name}`"
                      type="button"
                      class="w-full rounded-md border border-white/10 bg-white/5 p-2 text-left transition hover:border-cyan-300/40 hover:bg-cyan-500/10"
                      @click="openBenchmarkMetricDetail(metric)"
                    >
                      <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                          <p class="text-sm font-semibold text-white">{{ metric.display_name || humanizeKey(metric.metric_key) }}</p>
                          <p class="mt-1 text-xs text-cyan-100">{{ metricSource(metric) }}</p>
                        </div>
                        <span
                          class="rounded-full border px-2 py-0.5 text-[10px] uppercase tracking-wider"
                          :class="metricSourceMix(metric).population_usable ? 'border-cyan-300/30 bg-cyan-500/15 text-cyan-100' : 'border-white/10 bg-white/5 text-slate-300'"
                        >
                          Usable {{ metricPopulationUsable(metric) }}
                        </span>
                      </div>
                      <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-300">
                        <p>Research: <span class="font-semibold text-white">{{ metricResearchPercentile(metric) }}</span></p>
                        <p>Population: <span class="font-semibold text-white">{{ metricPopulationPercentile(metric) }}</span></p>
                        <p>Bucket: <span class="font-semibold text-white">{{ metricPopulationBucketCount(metric) }} players</span></p>
                        <p>Confidence: <span class="font-semibold text-white">{{ metricPopulationConfidence(metric) }}</span></p>
                      </div>
                      <p class="mt-2 text-[10px] text-slate-400">{{ sourceMixStatusText(metricSourceMix(metric)) }}</p>
                    </button>
                  </div>
                  <p v-if="!sourceMetricRows.length" class="mt-2 text-sm text-slate-300">
                    No metric-level source mix is available yet.
                  </p>
                </div>
              </template>
            </div>

            <div class="mt-4 rounded-lg border border-indigo-300/20 bg-indigo-500/10 p-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="text-[10px] uppercase tracking-widest text-indigo-200/80">Population Bucket Quality</p>
                  <h4 class="mt-1 text-lg font-semibold text-white">FMTRX Peer Group Trust</h4>
                </div>
                <span
                  class="rounded-full border px-3 py-1 text-xs uppercase tracking-wider"
                  :class="populationBucketQualitySummary.available ? 'border-indigo-300/30 bg-indigo-500/15 text-indigo-100' : 'border-white/10 bg-white/5 text-slate-300'"
                >
                  {{ populationBucketQualitySummary.available ? 'Bucket Detail Active' : 'Needs Data' }}
                </span>
              </div>

              <p v-if="!populationBucketQualitySummary.available" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-300">
                Population bucket quality is not available yet.
              </p>

              <template v-else>
                <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-7">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Exact Peer</p>
                    <p class="text-xl font-black text-white">{{ fmtCount(populationBucketQualitySummary.counts.exact_peer, '0') }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Athletic Peer</p>
                    <p class="text-xl font-black text-white">{{ fmtCount(populationBucketQualitySummary.counts.athletic_peer, '0') }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Age + Role</p>
                    <p class="text-xl font-black text-white">{{ fmtCount(populationBucketQualitySummary.counts.age_role, '0') }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Age Only</p>
                    <p class="text-xl font-black text-white">{{ fmtCount(populationBucketQualitySummary.counts.age_only, '0') }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Global</p>
                    <p class="text-xl font-black text-white">{{ fmtCount(populationBucketQualitySummary.counts.global_clean, '0') }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Insufficient</p>
                    <p class="text-xl font-black text-white">{{ fmtCount(populationBucketQualitySummary.counts.none, '0') }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Avg Bucket</p>
                    <p class="text-xl font-black text-white">{{ fmtCount(populationBucketQualitySummary.averageBucketCount, '0') }}</p>
                  </div>
                </div>

                <div class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-200">
                  <p><span class="font-black text-white">Confidence:</span> {{ populationBucketQualitySummary.confidenceSummary }}</p>
                  <p class="mt-1 text-indigo-100"><span class="font-black text-white">Rule:</span> Population sample below 30 means research benchmark remains active.</p>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-2">
                  <button
                    v-for="metric in bucketQualityMetricRows"
                    :key="`bucket-quality-${metric.metric_key || metric.display_name}`"
                    type="button"
                    class="w-full rounded-md border border-white/10 bg-slate-950/35 p-3 text-left transition hover:border-indigo-300/40 hover:bg-indigo-500/10"
                    @click="openBenchmarkMetricDetail(metric)"
                  >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <p class="text-sm font-semibold text-white">{{ metric.display_name || humanizeKey(metric.metric_key) }}</p>
                        <p class="mt-1 text-xs text-indigo-100">{{ metric.bucketLabel }}</p>
                      </div>
                      <span
                        class="rounded-full border px-2 py-0.5 text-[10px] uppercase tracking-wider"
                        :class="metric.bucketUsable ? 'border-indigo-300/30 bg-indigo-500/15 text-indigo-100' : 'border-white/10 bg-white/5 text-slate-300'"
                      >
                        {{ metric.bucketUsable ? 'Population Usable' : 'Research Active' }}
                      </span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-300 md:grid-cols-4">
                      <p>Bucket: <span class="font-semibold text-white">{{ fmtCount(metric.bucketCount, '0') }}</span></p>
                      <p>Confidence: <span class="font-semibold text-white">{{ confidenceLabel(metric.bucketConfidence) }}</span></p>
                      <p>Research: <span class="font-semibold text-white">{{ metricResearchPercentile(metric) }}</span></p>
                      <p>Population: <span class="font-semibold text-white">{{ metricPopulationPercentile(metric) }}</span></p>
                      <p>Source: <span class="font-semibold text-white">{{ metricSource(metric) }}</span></p>
                      <p>Final: <span class="font-semibold text-white">{{ fmtScore(metric.finalScore) }}</span></p>
                    </div>

                    <p class="mt-2 text-xs text-slate-300">{{ metric.bucketExplanation }}</p>
                    <p v-if="metric.bucketLevel === 'global_clean'" class="mt-2 rounded border border-amber-300/20 bg-amber-500/10 px-2 py-1 text-xs text-amber-100">
                      This is a broad comparison group. Peer-specific confidence improves as more players collect data.
                    </p>
                    <p v-else-if="metric.bucketDisplayLevel === 'exact_peer' && metric.bucketUsable" class="mt-2 rounded border border-emerald-300/20 bg-emerald-500/10 px-2 py-1 text-xs text-emerald-100">
                      Strong peer match.
                    </p>
                    <p v-else-if="!metric.bucketUsable" class="mt-2 rounded border border-white/10 bg-white/5 px-2 py-1 text-xs text-slate-300">
                      Population sample below 30. Research benchmark remains active.
                    </p>

                    <div v-if="metric.attemptedBuckets.length" class="mt-3 rounded border border-white/10 bg-white/5 p-2">
                      <p class="text-[10px] uppercase tracking-widest text-white/40">Attempted Buckets</p>
                      <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                          v-for="attempt in metric.attemptedBuckets"
                          :key="`${metric.metric_key}-${attempt.level}-${attempt.bucket_key}`"
                          class="rounded-full border px-2 py-1 text-[10px]"
                          :class="attempt.usable ? 'border-indigo-300/30 bg-indigo-500/15 text-indigo-100' : 'border-white/10 bg-slate-950/40 text-slate-300'"
                        >
                          {{ metricAttemptedBucketLabel(attempt) }}
                        </span>
                      </div>
                    </div>
                  </button>
                </div>

                <p v-if="!bucketQualityMetricRows.length" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-300">
                  No metric-level bucket details are available yet.
                </p>
              </template>
            </div>

            <div class="mt-4 rounded-lg border border-red-300/20 bg-red-500/10 p-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="text-[10px] uppercase tracking-widest text-red-200/80">Benchmark Data Quality</p>
                  <h4 class="mt-1 text-lg font-semibold text-white">Roster + Baseline Readiness</h4>
                </div>
                <span
                  class="rounded-full border px-3 py-1 text-xs uppercase tracking-wider"
                  :class="dataCollectionPriority ? 'border-red-300/30 bg-red-500/15 text-red-100' : 'border-white/10 bg-white/5 text-slate-300'"
                >
                  Priority {{ humanizeKey(benchmarkDataQuality.priority || 'not_available', 'Not Available') }}
                </span>
              </div>

              <p v-if="!dataCollectionPriority" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-300">
                Benchmark data quality is not available yet.
              </p>

              <template v-else>
                <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Confidence</p>
                    <p class="text-lg font-black text-white">{{ humanizeKey(benchmarkDataQuality.confidence) }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">With Data</p>
                    <p class="text-lg font-black text-white">{{ fmtCount(benchmarkDataQuality.playersWithData) }} / {{ fmtCount(benchmarkDataQuality.playerCount) }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Need Baseline</p>
                    <p class="text-lg font-black text-white">{{ fmtCount(benchmarkDataQuality.playersWithoutData) }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Metrics Loaded</p>
                    <p class="text-lg font-black text-white">{{ fmtCount(benchmarkDataQuality.metricCount) }}</p>
                  </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-200 lg:col-span-2">
                    <p>
                      <span class="font-black text-white">{{ fmtCount(benchmarkDataQuality.playersWithData) }} of {{ fmtCount(benchmarkDataQuality.playerCount) }}</span>
                      players have benchmark data.
                    </p>
                    <p class="mt-1">
                      <span class="font-black text-white">{{ fmtCount(benchmarkDataQuality.playersWithoutData) }}</span>
                      players need benchmark baselines.
                    </p>
                    <p class="mt-1">
                      <span class="font-black text-white">{{ fmtCount(benchmarkDataQuality.rosterCleanupCount) }}</span>
                      players need roster cleanup.
                    </p>
                    <p class="mt-2 text-red-100">
                      <span class="font-black text-white">Next action:</span> {{ benchmarkDataQuality.nextAction }}
                    </p>
                    <p v-if="practicePlanHasDataBlock" class="mt-2 rounded border border-red-300/20 bg-red-500/10 px-2 py-1 text-xs font-semibold text-red-100">
                      Baseline collection added to today's plan.
                    </p>
                  </div>

                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-white/40">Missing Data</p>
                    <p class="mt-2 text-sm text-slate-200">
                      Critical <span class="font-black text-white">{{ fmtCount(benchmarkDataQuality.criticalCount) }}</span>
                      · Supporting <span class="font-black text-white">{{ fmtCount(benchmarkDataQuality.supportingCount) }}</span>
                    </p>
                    <p class="mt-2 text-xs text-slate-300">
                      {{ benchmarkDataQuality.priority ? `Priority: ${humanizeKey(benchmarkDataQuality.priority)}` : 'Priority not available.' }}
                    </p>
                  </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-red-200/80">Critical Missing Data</p>
                    <div class="mt-2 space-y-1 text-xs">
                      <p v-for="row in allCriticalMissingRows.slice(0, 6)" :key="`quality-critical-${row.metric_key}`" class="rounded border border-red-300/15 bg-red-500/10 px-2 py-1 text-red-100">
                        {{ missingRowTitle(row) }} · {{ missingRowCount(row) }}
                      </p>
                      <p v-if="!allCriticalMissingRows.length" class="text-slate-300">No critical benchmark gaps are currently flagged.</p>
                    </div>
                  </div>

                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-amber-200/80">Supporting Missing Data</p>
                    <div class="mt-2 space-y-1 text-xs">
                      <p v-for="row in allSupportingMissingRows.slice(0, 6)" :key="`quality-supporting-${row.metric_key}`" class="rounded border border-amber-300/15 bg-amber-500/10 px-2 py-1 text-amber-100">
                        {{ missingRowTitle(row) }} · {{ missingRowCount(row) }}
                      </p>
                      <p v-if="!allSupportingMissingRows.length" class="text-slate-300">No supporting benchmark gaps are currently flagged.</p>
                    </div>
                  </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-white/40">Roster Cleanup Needs</p>
                    <div v-if="rosterCleanupPlayers.length" class="mt-2 flex flex-wrap gap-2">
                      <span
                        v-for="player in rosterCleanupPlayers"
                        :key="player.player_id || player.player_name || player.name"
                        class="rounded-full border border-red-300/20 bg-red-500/10 px-2 py-1 text-xs text-red-100"
                      >
                        {{ player.player_name || player.name || 'Player' }}
                        <span v-if="asArray(player.missing_fields).length" class="text-red-100/70">
                          · {{ asArray(player.missing_fields).join(', ') }}
                        </span>
                      </span>
                    </div>
                    <p v-else class="mt-2 text-xs text-slate-300">No roster cleanup names are currently attached.</p>
                  </div>

                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-cyan-200/80">Next Collection Plan</p>
                    <div v-if="collectionPlanRows.length" class="mt-2 space-y-2 text-xs text-cyan-100">
                      <div v-for="plan in collectionPlanRows" :key="plan.title" class="rounded border border-cyan-300/15 bg-cyan-500/10 px-2 py-1">
                        <p class="font-black text-white">{{ plan.title }}</p>
                        <p v-if="collectionPlanMetricNames(plan)" class="mt-1">{{ collectionPlanMetricNames(plan) }}</p>
                      </div>
                    </div>
                    <p v-else class="mt-2 text-xs text-slate-300">No collection plan is attached yet.</p>
                    <p v-if="benchmarkSnapshot.populationShare === 0" class="mt-3 text-xs text-slate-300">
                      Research benchmarks active. FMTRX population learning improves as more data is collected.
                    </p>
                  </div>
                </div>

              </template>
            </div>

            <div class="mt-4 rounded-lg border border-emerald-300/20 bg-emerald-500/10 p-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="text-[10px] uppercase tracking-widest text-emerald-200/80">Benchmark Collection Plan</p>
                  <h4 class="mt-1 text-lg font-semibold text-white">Coach Workflow</h4>
                </div>
                <span
                  class="rounded-full border px-3 py-1 text-xs uppercase tracking-wider"
                  :class="benchmarkCollectionPlan ? 'border-emerald-300/30 bg-emerald-500/15 text-emerald-100' : 'border-white/10 bg-white/5 text-slate-300'"
                >
                  Priority {{ humanizeKey(benchmarkCollectionPlan?.priority_level || 'not_available', 'Not Available') }}
                </span>
              </div>

              <p v-if="!benchmarkCollectionPlan" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-300">
                Benchmark collection plan is not available yet.
              </p>

              <template v-else>
                <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Priority</p>
                    <p class="text-lg font-black text-white">{{ humanizeKey(benchmarkCollectionPlan.priority_level) }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Minutes</p>
                    <p class="text-lg font-black text-white">{{ fmtCount(benchmarkCollectionPlan.estimated_total_minutes, '0') }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Sessions</p>
                    <p class="text-lg font-black text-white">{{ fmtCount(benchmarkCollectionSessions.length, '0') }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Player Tasks</p>
                    <p class="text-lg font-black text-white">{{ fmtCount(benchmarkCollectionPlan.player_tasks?.length, '0') }}</p>
                  </div>
                </div>

                <div class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-200">
                  <p>{{ benchmarkCollectionPlan.summary || 'FMTRX will turn missing benchmark data into collection tasks as data becomes available.' }}</p>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-200/80">Next Best Action</p>
                    <template v-if="benchmarkCollectionNextAction">
                      <p class="mt-1 text-xl font-black text-white">{{ benchmarkCollectionNextAction.title }}</p>
                      <p class="mt-1 text-xs text-slate-300">
                        {{ humanizeKey(benchmarkCollectionNextAction.priority) }} · {{ fmtCount(benchmarkCollectionNextAction.duration_minutes, '0') }} min
                      </p>
                      <p class="mt-2 text-xs text-emerald-100"><span class="font-black text-white">Why:</span> {{ benchmarkCollectionNextAction.why }}</p>
                      <p v-if="collectionPlayerNames(benchmarkCollectionNextAction.players)" class="mt-2 text-xs text-slate-300">
                        <span class="font-black text-white">Players:</span> {{ collectionPlayerNames(benchmarkCollectionNextAction.players) }}
                      </p>
                      <p v-if="collectionTaskMetricNames(benchmarkCollectionNextAction.metrics)" class="mt-1 text-xs text-slate-300">
                        <span class="font-black text-white">Metrics:</span> {{ collectionTaskMetricNames(benchmarkCollectionNextAction.metrics) }}
                      </p>
                    </template>
                    <p v-else class="mt-2 text-xs text-slate-300">No next collection action is attached yet.</p>
                  </div>

                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-200/80">Completion Targets</p>
                    <div class="mt-2 space-y-2 text-xs text-slate-300">
                      <p v-if="benchmarkCollectionTargets.next_session" class="rounded border border-emerald-300/15 bg-emerald-500/10 px-2 py-1">
                        <span class="font-black text-white">Next session:</span> {{ benchmarkCollectionTargets.next_session.target }}
                        <span class="text-emerald-100">({{ fmtCount(benchmarkCollectionTargets.next_session.minutes, '0') }} min)</span>
                      </p>
                      <p v-if="benchmarkCollectionTargets.this_week" class="rounded border border-emerald-300/15 bg-emerald-500/10 px-2 py-1">
                        <span class="font-black text-white">This week:</span> {{ benchmarkCollectionTargets.this_week.target }}
                        <span class="text-emerald-100">({{ fmtCount(benchmarkCollectionTargets.this_week.minutes, '0') }} min)</span>
                      </p>
                      <p v-if="benchmarkCollectionTargets.this_month" class="rounded border border-emerald-300/15 bg-emerald-500/10 px-2 py-1">
                        <span class="font-black text-white">This month:</span> {{ benchmarkCollectionTargets.this_month.target }}
                        <span class="text-emerald-100">({{ fmtCount(benchmarkCollectionTargets.this_month.minutes, '0') }} min)</span>
                      </p>
                      <p v-if="!Object.keys(benchmarkCollectionTargets).length" class="text-slate-300">No completion targets are attached yet.</p>
                    </div>
                  </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-2">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-200/80">Collection Sessions</p>
                    <div class="mt-2 space-y-2">
                      <div
                        v-for="session in benchmarkCollectionSessions"
                        :key="`${session.sequence || session.title}-${session.collection_type}`"
                        class="rounded border border-white/10 bg-white/5 px-2 py-2 text-xs text-slate-300"
                      >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                          <p class="font-black text-white">{{ session.sequence }}. {{ session.title }}</p>
                          <span class="rounded-full border border-emerald-300/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] uppercase tracking-wider text-emerald-100">
                            {{ humanizeKey(session.schedule_window) }} · {{ fmtCount(session.duration_minutes, '0') }} min
                          </span>
                        </div>
                        <p class="mt-1">{{ session.description }}</p>
                        <p class="mt-1 text-emerald-100"><span class="font-black text-white">Why:</span> {{ session.why }}</p>
                        <p v-if="collectionPlayerNames(session.players)" class="mt-1">
                          <span class="font-black text-white">Players:</span> {{ collectionPlayerNames(session.players) }}
                        </p>
                        <p v-if="collectionTaskMetricNames(session.metric_keys || session.metrics)" class="mt-1">
                          <span class="font-black text-white">Metrics:</span> {{ collectionTaskMetricNames(session.metric_keys || session.metrics) }}
                        </p>
                      </div>
                      <p v-if="!benchmarkCollectionSessions.length" class="text-xs text-slate-300">No collection sessions are currently recommended.</p>
                    </div>
                  </div>

                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-200/80">Player Tasks</p>
                    <div class="mt-2 space-y-2">
                      <div
                        v-for="task in benchmarkCollectionPlayerTasks"
                        :key="task.player_id || task.player_name"
                        class="rounded border border-white/10 bg-white/5 px-2 py-2 text-xs text-slate-300"
                      >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                          <p class="font-black text-white">{{ task.player_name || 'Player' }}</p>
                          <span class="text-emerald-100">{{ humanizeKey(task.priority) }}</span>
                        </div>
                        <p v-if="asArray(task.missing_context).length" class="mt-1">
                          <span class="font-black text-white">Roster:</span> {{ asArray(task.missing_context).join(', ') }}
                        </p>
                        <p v-if="collectionTaskMetricNames(task.missing_metrics)" class="mt-1">
                          <span class="font-black text-white">Metrics:</span> {{ collectionTaskMetricNames(task.missing_metrics) }}
                        </p>
                        <p class="mt-1 text-emerald-100"><span class="font-black text-white">Next:</span> {{ task.next_action }}</p>
                      </div>
                      <p v-if="!benchmarkCollectionPlayerTasks.length" class="text-xs text-slate-300">No player collection tasks are currently attached.</p>
                    </div>
                  </div>
                </div>

                <div v-if="benchmarkCollectionMetricTasks.length" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3">
                  <p class="text-[10px] uppercase tracking-widest text-emerald-200/80">Metric Tasks</p>
                  <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                    <p
                      v-for="task in benchmarkCollectionMetricTasks"
                      :key="task.metric_key"
                      class="rounded border border-white/10 bg-white/5 px-2 py-1 text-xs text-slate-300"
                    >
                      <span class="font-black text-white">{{ coachFriendlyMetricLabel(task) }}</span>
                      · {{ humanizeKey(task.priority) }}
                      · missing {{ fmtCount(task.missing_count, '0') }} of {{ fmtCount(task.eligible_count, '0') }}
                      · {{ task.recommended_session }}
                    </p>
                  </div>
                </div>

                <div class="mt-3 rounded-md border border-sky-300/20 bg-sky-500/10 p-3">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p class="text-[10px] uppercase tracking-widest text-sky-200/80">Assignable Benchmark Tasks</p>
                      <h5 class="mt-1 text-base font-semibold text-white">Draft Player Tasks</h5>
                    </div>
                    <span
                      class="rounded-full border px-3 py-1 text-xs uppercase tracking-wider"
                      :class="benchmarkTaskAssignments ? 'border-sky-300/30 bg-sky-500/15 text-sky-100' : 'border-white/10 bg-white/5 text-slate-300'"
                    >
                      {{ benchmarkTaskAssignments ? 'Draft Only' : 'Not Available' }}
                    </span>
                  </div>

                  <p v-if="!benchmarkTaskAssignments" class="mt-3 rounded border border-white/10 bg-slate-950/35 px-2 py-2 text-xs text-slate-300">
                    Assignable benchmark tasks are not available yet.
                  </p>

                  <div class="mt-3 flex flex-wrap gap-2">
                    <button
                      type="button"
                      class="rounded border border-sky-300/30 bg-sky-500/15 px-3 py-2 text-xs font-black uppercase tracking-wider text-sky-100 disabled:cursor-not-allowed disabled:opacity-50"
                      :disabled="!!benchmarkTaskActionLoading"
                      @click="generateBenchmarkDraftTasks"
                    >
                      {{ benchmarkTaskActionLoading === 'generate' ? 'Generating...' : 'Generate Draft Tasks' }}
                    </button>
                    <button
                      type="button"
                      class="rounded border border-emerald-300/30 bg-emerald-500/15 px-3 py-2 text-xs font-black uppercase tracking-wider text-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                      :disabled="!!benchmarkTaskActionLoading || !draftBenchmarkTasksForSave.length"
                      @click="saveBenchmarkDraftTasks"
                    >
                      {{ benchmarkTaskActionLoading === 'save' ? 'Saving...' : 'Save Draft Tasks' }}
                    </button>
                    <button
                      type="button"
                      class="rounded border border-red-300/30 bg-red-500/15 px-3 py-2 text-xs font-black uppercase tracking-wider text-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                      :disabled="!!benchmarkTaskActionLoading || !savedDraftBenchmarkTaskIds.length"
                      @click="assignBenchmarkDraftTasks"
                    >
                      {{ benchmarkTaskActionLoading === 'assign' ? 'Assigning...' : 'Assign Tasks' }}
                    </button>
                  </div>

                  <p v-if="benchmarkTaskActionMessage" class="mt-3 rounded border border-emerald-300/20 bg-emerald-500/10 px-2 py-2 text-xs text-emerald-100">
                    {{ benchmarkTaskActionMessage }}
                  </p>
                  <p v-if="benchmarkTaskActionError" class="mt-3 rounded border border-red-300/20 bg-red-500/10 px-2 py-2 text-xs text-red-100">
                    {{ benchmarkTaskActionError }}
                  </p>

                  <template v-if="benchmarkTaskAssignments">
                    <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4">
                      <div class="rounded border border-white/10 bg-slate-950/35 p-2">
                        <p class="text-[10px] uppercase tracking-wider text-white/35">Tasks</p>
                        <p class="text-lg font-black text-white">{{ fmtCount(benchmarkTaskAssignments.task_count, '0') }}</p>
                      </div>
                      <div class="rounded border border-white/10 bg-slate-950/35 p-2">
                        <p class="text-[10px] uppercase tracking-wider text-white/35">Player Tasks</p>
                        <p class="text-lg font-black text-white">{{ fmtCount(benchmarkTaskAssignments.player_task_count, '0') }}</p>
                      </div>
                      <div class="rounded border border-white/10 bg-slate-950/35 p-2">
                        <p class="text-[10px] uppercase tracking-wider text-white/35">Team Tasks</p>
                        <p class="text-lg font-black text-white">{{ fmtCount(benchmarkTaskAssignments.team_task_count, '0') }}</p>
                      </div>
                      <div class="rounded border border-white/10 bg-slate-950/35 p-2">
                        <p class="text-[10px] uppercase tracking-wider text-white/35">Priority</p>
                        <p class="text-lg font-black text-white">{{ humanizeKey(benchmarkTaskAssignments.priority_level) }}</p>
                      </div>
                    </div>

                    <p class="mt-3 rounded border border-white/10 bg-slate-950/35 px-2 py-2 text-xs text-slate-300">
                      These are draft tasks only. No player assignments are sent or saved yet.
                    </p>

                    <div class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-2">
                      <div class="rounded border border-white/10 bg-slate-950/35 p-3">
                        <p class="text-[10px] uppercase tracking-widest text-sky-200/80">Tasks By Player</p>
                        <div class="mt-2 space-y-2">
                          <div
                            v-for="group in benchmarkPlayerTaskGroups"
                            :key="group.player_id || group.player_name"
                            class="rounded border border-white/10 bg-white/5 px-2 py-2 text-xs text-slate-300"
                          >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                              <p class="font-black text-white">{{ group.player_name || 'Player' }}</p>
                              <span class="text-sky-100">{{ humanizeKey(group.priority) }} · {{ fmtCount(group.task_count, '0') }} tasks</span>
                            </div>
                            <div class="mt-2 space-y-1">
                              <p
                                v-for="task in asArray(group.tasks).slice(0, 5)"
                                :key="task.temporary_key"
                                class="rounded border border-sky-300/15 bg-sky-500/10 px-2 py-1"
                              >
                                <span class="font-black text-white">{{ task.title }}</span>
                                · {{ taskTypeLabel(task.task_type) }}
                                · {{ humanizeKey(task.priority) }}
                                · {{ fmtCount(task.estimated_minutes, '0') }} min
                                · {{ humanizeKey(task.due_window) }}
                                · {{ humanizeKey(task.status) }}
                                <span v-if="collectionTaskMetricNames(task.metrics)" class="block text-sky-100/90">
                                  {{ collectionTaskMetricNames(task.metrics) }}
                                </span>
                                <span v-if="asArray(task.missing_fields).length" class="block text-sky-100/90">
                                  {{ asArray(task.missing_fields).join(', ') }}
                                </span>
                              </p>
                            </div>
                          </div>
                          <p v-if="!benchmarkPlayerTaskGroups.length" class="text-xs text-slate-300">No draft player tasks are currently available.</p>
                        </div>
                      </div>

                      <div class="rounded border border-white/10 bg-slate-950/35 p-3">
                        <p class="text-[10px] uppercase tracking-widest text-sky-200/80">Team Tasks</p>
                        <div class="mt-2 space-y-2">
                          <div
                            v-for="task in benchmarkTeamTasks"
                            :key="task.temporary_key"
                            class="rounded border border-white/10 bg-white/5 px-2 py-2 text-xs text-slate-300"
                          >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                              <p class="font-black text-white">{{ task.title }}</p>
                              <span class="text-sky-100">{{ humanizeKey(task.priority) }} · {{ fmtCount(task.estimated_minutes, '0') }} min</span>
                            </div>
                            <p class="mt-1">{{ task.description }}</p>
                            <p class="mt-1 text-sky-100">
                              {{ taskTypeLabel(task.task_type) }} · {{ humanizeKey(task.due_window) }} · {{ humanizeKey(task.status) }}
                            </p>
                            <p v-if="collectionTaskMetricNames(task.metrics)" class="mt-1">
                              <span class="font-black text-white">Metrics:</span> {{ collectionTaskMetricNames(task.metrics) }}
                            </p>
                          </div>
                          <p v-if="!benchmarkTeamTasks.length" class="text-xs text-slate-300">No draft team tasks are currently available.</p>
                        </div>
                      </div>
                    </div>
                  </template>

                  <div class="mt-3 rounded border border-white/10 bg-slate-950/35 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                      <p class="text-[10px] uppercase tracking-widest text-sky-200/80">Saved Task List</p>
                      <p class="text-[10px] uppercase tracking-wider text-slate-300">
                        Draft {{ fmtCount(benchmarkTaskStatusCounts.draft, '0') }}
                        · Assigned {{ fmtCount(benchmarkTaskStatusCounts.assigned, '0') }}
                        · Completed {{ fmtCount(benchmarkTaskStatusCounts.completed, '0') }}
                      </p>
                    </div>
                    <div v-if="savedBenchmarkTaskRows.length" class="mt-2 grid grid-cols-1 gap-2 xl:grid-cols-2">
                      <div
                        v-for="task in savedBenchmarkTaskRows"
                        :key="task.id"
                        class="rounded border border-white/10 bg-white/5 px-2 py-2 text-xs text-slate-300"
                      >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                          <div>
                            <p class="font-black text-white">{{ task.assigned_to_player_name || 'Team Task' }}</p>
                            <p class="mt-1 text-slate-200">{{ task.title }}</p>
                          </div>
                          <span class="rounded-full border border-white/10 bg-slate-950/50 px-2 py-0.5 text-[10px] uppercase tracking-wider text-sky-100">
                            {{ humanizeKey(task.status) }}
                          </span>
                        </div>
                        <p class="mt-1">
                          {{ taskTypeLabel(task.task_type) }}
                          · {{ humanizeKey(task.priority) }}
                          · {{ humanizeKey(task.due_window) }}
                          · {{ fmtCount(task.estimated_minutes, '0') }} min
                        </p>
                      </div>
                    </div>
                    <p v-else class="mt-2 text-xs text-slate-300">No saved benchmark tasks yet.</p>
                    <p class="mt-3 text-[10px] uppercase tracking-wider text-slate-400">
                      No notifications are sent yet. This only saves and assigns task records.
                    </p>
                  </div>
                </div>
              </template>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-3">
              <div class="rounded-lg border border-white/10 bg-white/5 p-3 xl:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <h4 class="text-sm font-semibold text-white">Category Scores</h4>
                  <span class="text-[10px] uppercase tracking-widest text-white/35">Pitching · Hitting · Strength · Athletic · Mobility</span>
                </div>
                <p v-if="!hasBenchmarkCategoryScores" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-2 text-sm text-slate-300">
                  More player data is needed before category scores are available.
                </p>
                <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-5">
                  <div
                    v-for="row in benchmarkCategoryRows"
                    :key="row.category"
                    class="rounded-md border border-white/10 bg-slate-950/35 p-2"
                  >
                    <p class="text-[10px] uppercase tracking-wider text-white/40">{{ row.display }}</p>
                    <p class="mt-1 text-2xl font-black" :class="row.hasData ? scoreTone(row.score) : 'text-slate-400'">{{ fmtScore(row.score) }}</p>
                    <p class="text-xs text-slate-300">{{ humanizeKey(row.label) }} · {{ humanizeKey(row.confidence) }}</p>
                    <p class="mt-1 text-[10px] text-white/45">Players {{ fmt1(row.playerCount) }} · Metrics {{ fmt1(row.metricCount) }}</p>
                  </div>
                </div>
              </div>

              <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                <h4 class="text-sm font-semibold text-white">Weakest Categories</h4>
                <div class="mt-2 space-y-2">
                  <div v-for="category in weakestBenchmarkCategories" :key="category.category" class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <div class="flex items-center justify-between gap-2">
                      <p class="text-sm font-semibold text-white">{{ categoryLabel(category.category) }}</p>
                      <span class="text-sm font-black" :class="scoreTone(category.score_0_100)">{{ fmtScore(category.score_0_100) }}</span>
                    </div>
                    <p class="text-xs text-slate-300">{{ humanizeKey(category.label) }} · {{ humanizeKey(category.confidence) }}</p>
                  </div>
                  <p v-if="!weakestBenchmarkCategories.length" class="text-sm text-slate-300">More player data is needed.</p>
                </div>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-3">
              <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                <h4 class="text-sm font-semibold text-white">Weakest Metrics</h4>
                <div class="mt-2 space-y-2">
                  <button
                    v-for="metric in weakestBenchmarkMetrics"
                    :key="metric.metric_key"
                    type="button"
                    class="w-full rounded-md border border-white/10 bg-slate-950/35 p-2 text-left transition hover:border-red-300/40 hover:bg-red-500/10"
                    @click="openBenchmarkMetricDetail(metric)"
                  >
                    <div class="flex items-center justify-between gap-2">
                      <p class="text-sm font-semibold text-white">{{ metric.display_name || humanizeKey(metric.metric_key) }}</p>
                      <span class="text-sm font-black" :class="scoreTone(metric.score_0_100)">{{ fmtScore(metric.score_0_100) }}</span>
                    </div>
                    <p class="text-xs text-slate-300">{{ categoryLabel(metric.category) }} · {{ metricPercentile(metric) }} · {{ humanizeKey(metric.label) }}</p>
                    <p class="mt-1 text-[10px] text-cyan-100">{{ metricSource(metric) }} · Bucket {{ metricPopulationBucketCount(metric) }} · {{ metricPopulationConfidence(metric) }}</p>
                    <p class="mt-1 text-[10px] text-white/45">Research {{ metricResearchPercentile(metric) }} · Population {{ metricPopulationPercentile(metric) }} · Usable {{ metricPopulationUsable(metric) }}</p>
                    <p class="mt-1 text-[10px] text-white/45">Good gap {{ metricGap(metric, 'gap_to_good') }} · Elite gap {{ metricGap(metric, 'gap_to_elite') }}</p>
                    <p class="mt-1 text-[10px] text-slate-400">{{ sourceMixStatusText(metricSourceMix(metric)) }}</p>
                  </button>
                  <p v-if="!weakestBenchmarkMetrics.length" class="text-sm text-slate-300">No weakest metrics available yet.</p>
                </div>
              </div>

              <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                <h4 class="text-sm font-semibold text-white">Players Needing Attention</h4>
                <div class="mt-2 space-y-2">
                  <button
                    v-for="player in benchmarkPlayersNeedingAttention"
                    :key="player.player_id || player.name"
                    type="button"
                    class="w-full rounded-md border border-white/10 bg-slate-950/35 p-2 text-left transition hover:border-white/25 hover:bg-white/10"
                    @click="openPlayer({ id: player.player_id, name: player.name })"
                  >
                    <div class="flex items-center justify-between gap-2">
                      <p class="text-sm font-semibold text-white">{{ player.name || 'Player' }}</p>
                      <span class="text-sm font-black" :class="scoreTone(player.average_score)">{{ fmtScore(player.average_score) }}</span>
                    </div>
                    <p class="text-xs text-slate-300">{{ playerWeakCategory(player) }} · {{ playerWeakMetric(player) }}</p>
                    <p class="mt-1 text-[10px] text-white/45">Metrics {{ fmt1(player.metric_count) }} · {{ humanizeKey(player.label) }}</p>
                  </button>
                  <p v-if="!benchmarkPlayersNeedingAttention.length" class="text-sm text-slate-300">No players are flagged by benchmarks yet.</p>
                </div>
              </div>

              <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                <h4 class="text-sm font-semibold text-white">Missing Data Priority</h4>
                <div class="mt-2 space-y-3 text-xs">
                  <div>
                    <p class="font-black uppercase tracking-wider text-red-200">Critical</p>
                    <div class="mt-1 space-y-1">
                      <p v-for="row in criticalMissingRows" :key="`critical-${missingRowTitle(row)}`" class="rounded border border-red-300/15 bg-red-500/10 px-2 py-1 text-red-100">
                        {{ missingRowTitle(row) }} · {{ missingRowCount(row) }}
                      </p>
                      <p v-if="!criticalMissingRows.length" class="text-slate-300">No critical missing data.</p>
                    </div>
                  </div>

                  <div>
                    <p class="font-black uppercase tracking-wider text-amber-200">Supporting</p>
                    <div class="mt-1 space-y-1">
                      <p v-for="row in supportingMissingRows" :key="`supporting-${missingRowTitle(row)}`" class="rounded border border-amber-300/15 bg-amber-500/10 px-2 py-1 text-amber-100">
                        {{ missingRowTitle(row) }} · {{ missingRowCount(row) }}
                      </p>
                      <p v-if="!supportingMissingRows.length" class="text-slate-300">No supporting missing data.</p>
                    </div>
                  </div>

                  <div v-if="optionalMissingRows.length">
                    <p class="font-black uppercase tracking-wider text-slate-300">Optional</p>
                    <div class="mt-1 space-y-1">
                      <p v-for="row in optionalMissingRows" :key="`optional-${missingRowTitle(row)}`" class="rounded border border-white/10 bg-white/5 px-2 py-1 text-slate-200">
                        {{ missingRowTitle(row) }} · {{ missingRowCount(row) }}
                      </p>
                    </div>
                  </div>

                  <div v-if="collectionPlanRows.length">
                    <p class="font-black uppercase tracking-wider text-cyan-200">Collection Plan</p>
                    <p v-for="plan in collectionPlanRows" :key="plan.title" class="mt-1 rounded border border-cyan-300/15 bg-cyan-500/10 px-2 py-1 text-cyan-100">
                      {{ plan.title }}<span v-if="collectionPlanMetricNames(plan)"> · {{ collectionPlanMetricNames(plan) }}</span>
                    </p>
                  </div>

                  <div v-if="!dataCollectionPriority && benchmarkMissingMetrics.length">
                    <p class="font-black uppercase tracking-wider text-amber-200">Benchmark Gaps</p>
                    <p v-for="row in benchmarkMissingMetrics.slice(0, 5)" :key="row.metric_key" class="mt-1 rounded border border-amber-300/15 bg-amber-500/10 px-2 py-1 text-amber-100">
                      {{ missingRowTitle(row) }} · {{ missingRowCount(row) }}
                    </p>
                  </div>

                  <p v-if="!hasMissingDataPriorityRows" class="rounded border border-white/10 bg-slate-950/35 px-2 py-2 text-slate-300">
                    No missing benchmark data is currently flagged.
                  </p>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- B: Priority Metric Cards -->
        <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
          <h3 class="text-lg font-semibold text-white">Priority Metric Cards</h3>
          <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <button
              v-for="m in metricCardData"
              :key="m.key"
              type="button"
              class="rounded-lg border border-white/10 bg-white/5 p-3 text-left transition hover:border-white/25 hover:bg-white/10 cursor-pointer"
              @click="openPriorityTop10(m)"
            >
              <p class="text-[10px] uppercase tracking-wider text-white/40">{{ m.label }}</p>
              <p class="mt-1 text-2xl font-black" :class="m.tone">{{ fmtValue(m.value, m.unit) }}</p>
              <p class="mt-1 text-xs" :class="trendChip(m.delta).cls">{{ trendChip(m.delta).text }}</p>
              <p class="mt-1 text-xs text-slate-300">{{ m.status }}<span v-if="m.goal !== null"> · Goal {{ fmtValue(m.goal, m.unit) }}</span></p>
              <p class="mt-1 text-[11px] text-white/50">{{ m.insight }}</p>
              <p class="mt-2 text-[10px] uppercase tracking-wider text-red-200/80">Tap to view Top 10 players</p>
            </button>
          </div>
        </div>

        <!-- C + D -->
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <h3 class="text-lg font-semibold text-white">Pitching Development Board</h3>
            <div class="mt-2 rounded-lg border border-white/10 bg-white/5 p-2 text-xs">
              <p class="text-slate-300"><span class="font-black text-white">What:</span> {{ pitchingPulseAnswer.what }}</p>
              <p class="mt-1 text-slate-300"><span class="font-black text-white">Why:</span> {{ pitchingPulseAnswer.why }}</p>
              <p class="mt-1 text-red-200"><span class="font-black text-white">Next:</span> {{ pitchingPulseAnswer.next }}</p>
            </div>
            <div class="mt-3 space-y-2">
              <div v-for="r in pitchingBoardRows" :key="r.key" class="rounded-md border border-white/10 bg-white/5 p-2">
                <div class="flex items-center justify-between">
                  <p class="text-sm text-white">{{ r.label }}</p>
                  <p class="font-semibold" :class="r.tone">{{ fmtValue(r.value, r.unit) }}</p>
                </div>
                <p class="text-xs text-slate-400">{{ trendChip(r.delta).text }} · {{ r.improving }} improving / {{ r.declining }} declining</p>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <h3 class="text-lg font-semibold text-white">Hitting Development Board</h3>
            <div class="mt-2 rounded-lg border border-white/10 bg-white/5 p-2 text-xs">
              <p class="text-slate-300"><span class="font-black text-white">What:</span> {{ hittingPulseAnswer.what }}</p>
              <p class="mt-1 text-slate-300"><span class="font-black text-white">Why:</span> {{ hittingPulseAnswer.why }}</p>
              <p class="mt-1 text-red-200"><span class="font-black text-white">Next:</span> {{ hittingPulseAnswer.next }}</p>
            </div>
            <div class="mt-3 space-y-2">
              <div v-for="r in hittingBoardRows" :key="r.key" class="rounded-md border border-white/10 bg-white/5 p-2">
                <div class="flex items-center justify-between">
                  <p class="text-sm text-white">{{ r.label }}</p>
                  <p class="font-semibold" :class="r.tone">{{ fmtValue(r.value, r.unit) }}</p>
                </div>
                <p class="text-xs text-slate-400">{{ trendChip(r.delta).text }} · {{ r.status }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- E + F -->
        <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
          <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-semibold text-white">Growth Charts / Player Comparison</h3>
            <select v-model="selectedMetric" class="rounded-md border border-white/20 bg-slate-800 px-2 py-1 text-sm text-slate-200">
              <option v-for="k in [...DASHBOARD_METRICS.pitching, ...DASHBOARD_METRICS.hitting]" :key="k" :value="k">{{ metricMeta[k]?.label || k }}</option>
            </select>
            <select v-model="selectedRange" class="rounded-md border border-white/20 bg-slate-800 px-2 py-1 text-sm text-slate-200">
              <option value="30d">30 days</option>
              <option value="60d">60 days</option>
              <option value="90d">90 days</option>
            </select>
          </div>

          <div class="mt-3 flex flex-wrap gap-2">
            <button
              v-for="p in playersWithPercentile"
              :key="p.id"
              type="button"
              class="rounded-md border px-2 py-1 text-xs"
              :class="selectedPlayers.includes(p.id) ? 'border-red-400/50 bg-red-500/20 text-red-200' : 'border-white/20 bg-slate-800 text-slate-200'"
              @click="selectedPlayers = selectedPlayers.includes(p.id) ? selectedPlayers.filter((x) => x !== p.id) : [...selectedPlayers, p.id].slice(0, 4)"
            >
              {{ p.name }}
            </button>
          </div>

          <div class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-2">
            <div v-for="p in selectedProjectionRows" :key="p.id" class="rounded-lg border border-white/10 bg-white/5 p-3">
              <div class="flex items-center justify-between">
                <p class="font-semibold text-white">{{ p.name }}</p>
                <span class="rounded border px-2 py-0.5 text-xs" :class="p.confidence === 'High' ? 'border-emerald-400/40 text-emerald-300' : p.confidence === 'Medium' ? 'border-yellow-400/40 text-yellow-300' : 'border-red-400/40 text-red-300'">{{ p.confidence }}</span>
              </div>
              <p class="mt-1 text-xs text-slate-300">Current {{ metricMeta[selectedMetric]?.label }}: <span class="font-semibold text-white">{{ fmt1(p.current) }}</span></p>
              <svg class="mt-2 h-14 w-full" viewBox="0 0 100 40" preserveAspectRatio="none">
                <line x1="0" y1="20" x2="100" y2="20" stroke="rgba(148,163,184,0.25)" stroke-dasharray="2 2" />
                <polyline :points="sparklinePoints([p.previous, p.current, p.projected30, p.projected60, p.projected90])" fill="none" stroke="#f43f5e" stroke-width="2" />
              </svg>
              <div class="mt-1 grid grid-cols-3 gap-2 text-xs">
                <p class="text-slate-300">30d: <span class="font-semibold text-white">{{ fmt1(p.projected30) }}</span></p>
                <p class="text-slate-300">60d: <span class="font-semibold text-white">{{ fmt1(p.projected60) }}</span></p>
                <p class="text-slate-300">90d: <span class="font-semibold text-white">{{ fmt1(p.projected90) }}</span></p>
              </div>
            </div>
          </div>
        </div>

        <!-- H -->
        <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
          <h3 class="text-lg font-semibold text-white">Player Development Board</h3>
          <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-left text-sm text-slate-300">
              <thead>
                <tr class="text-xs uppercase text-slate-400">
                  <th class="py-2 pr-4">Player</th>
                  <th class="py-2 pr-4">PDI</th>
                  <th class="py-2 pr-4">Percentile</th>
                  <th class="py-2 pr-4">Trend</th>
                  <th class="py-2 pr-4">Best</th>
                  <th class="py-2 pr-4">Need</th>
                  <th class="py-2 pr-4">DNA</th>
                  <th class="py-2 pr-4">Risk</th>
                  <th class="py-2 pr-4">Projection</th>
                  <th class="py-2">Alert</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in playersWithPercentile" :key="p.id" class="border-t border-white/10">
                  <td class="py-2 pr-4">
                    <button class="text-left text-white hover:text-red-300" @click="openPlayer(p)">{{ p.name }}</button>
                  </td>
                  <td class="py-2 pr-4">{{ fmt1(p.pdi) }}</td>
                  <td class="py-2 pr-4">{{ fmtRank(p.percentile) }}</td>
                  <td class="py-2 pr-4">{{ p.trend }}</td>
                  <td class="py-2 pr-4">{{ p.bestStrength }}</td>
                  <td class="py-2 pr-4">{{ p.biggestNeed }}</td>
                  <td class="py-2 pr-4">{{ p.playerType }}</td>
                  <td class="py-2 pr-4">{{ fmt1(p.riskScore) }} ({{ p.riskLevel }})</td>
                  <td class="py-2 pr-4">{{ fmt1(p.projection.projected90) }}</td>
                  <td class="py-2">{{ p.limiterCount ? `${p.limiterCount} limiter(s)` : (p.riskScore > 60 ? 'Needs Attention' : p.riskScore > 40 ? 'Watch' : 'Stable') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Leaderboards + Alerts + Roadmap -->
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <h3 class="text-lg font-semibold text-white">Development Leaderboards</h3>
            <p class="mt-2 text-xs uppercase tracking-wider text-white/40">Most Improved</p>
            <ol class="mt-1 list-decimal space-y-1 pl-5 text-sm text-slate-300">
              <li v-for="p in leaderboardMostImproved" :key="`imp-${p.id}`">{{ p.name }} · {{ fmt1(p.pdiChange) }}</li>
            </ol>
            <p class="mt-3 text-xs uppercase tracking-wider text-white/40">Needs Attention</p>
            <ol class="mt-1 list-decimal space-y-1 pl-5 text-sm text-slate-300">
              <li v-for="p in leaderboardNeedsAttention" :key="`risk-${p.id}`">{{ p.name }} · Risk {{ fmt1(p.riskScore) }}</li>
            </ol>
          </div>

          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <h3 class="text-lg font-semibold text-white">Team Alerts / Data Gaps</h3>
            <div class="mt-2 space-y-2 text-sm">
              <div v-for="(a, idx) in teamAlerts" :key="idx" class="rounded-md border p-2"
                :class="a.severity === 'high' ? 'border-red-500/40 bg-red-500/10 text-red-200' : a.severity === 'medium' ? 'border-yellow-500/40 bg-yellow-500/10 text-yellow-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'">
                <p class="font-semibold">{{ a.title }}</p>
                <p class="text-xs opacity-90">{{ a.body }}</p>
                <p v-if="a.next" class="mt-1 text-xs font-semibold opacity-95">Next: {{ a.next }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <h3 class="text-lg font-semibold text-white">Team Recommendations</h3>
            <div class="mt-2 space-y-2">
              <div v-for="r in roadmap" :key="r.priority" class="rounded-md border border-white/10 bg-white/5 p-2">
                <p class="text-sm font-semibold text-white">{{ r.priority }}. {{ r.title }}</p>
                <p class="text-xs text-slate-300"><span class="font-black text-white">Why:</span> {{ r.reason }}</p>
                <p class="mt-1 text-xs text-red-200"><span class="font-black text-white">Next:</span> {{ r.action }}</p>
                <p class="mt-1 text-[10px] uppercase tracking-widest text-white/35">Confidence: {{ r.confidence }}</p>
              </div>
            </div>
          </div>
        </div>
      </template>

      <Teleport to="body">
        <Transition name="fade">
          <div
            v-if="priorityTop10Modal.open"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/70 p-3 sm:p-6"
            @click.self="closePriorityTop10"
          >
            <div class="w-full max-w-2xl rounded-2xl border border-white/15 bg-[#0c1630] shadow-2xl max-h-[92vh] flex flex-col">
              <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                <div>
                  <p class="text-xs uppercase tracking-widest text-white/45">Priority Metric Top 10</p>
                  <h3 class="text-lg font-semibold text-white">{{ priorityTop10Modal.label }}</h3>
                </div>
                <button type="button" class="rounded-md border border-white/20 px-2 py-1 text-xs text-slate-200 hover:bg-white/10" @click="closePriorityTop10">Close</button>
              </div>

              <div class="overflow-y-auto p-4">
                <div v-if="!priorityTop10Rows.length" class="rounded-lg border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                  No player data available for this metric yet.
                </div>

                <div v-else class="space-y-2">
                  <div
                    v-for="(row, idx) in priorityTop10Rows"
                    :key="`${row.name}-${idx}`"
                    class="flex items-center justify-between rounded-lg border border-white/10 bg-white/5 px-3 py-2"
                  >
                    <div class="flex items-center gap-3">
                      <span class="w-6 text-center text-sm font-black" :class="idx === 0 ? 'text-yellow-300' : idx === 1 ? 'text-slate-300' : idx === 2 ? 'text-orange-300' : 'text-slate-500'">{{ idx + 1 }}</span>
                      <span class="text-sm font-semibold text-white">{{ row.name }}</span>
                    </div>
                    <div class="text-sm font-black text-emerald-300">
                      {{ fmtValue(row.value, priorityTop10Modal.unit) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <Teleport to="body">
        <Transition name="fade">
          <div
            v-if="selectedMetricDetail"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/75 p-3 sm:p-6"
            @click.self="closeBenchmarkMetricDetail"
          >
            <div class="w-full max-w-4xl rounded-2xl border border-white/15 bg-[#0c1630] shadow-2xl max-h-[92vh] flex flex-col">
              <div class="flex items-start justify-between gap-3 border-b border-white/10 px-4 py-3">
                <div>
                  <p class="text-xs uppercase tracking-widest text-indigo-200/70">Benchmark Metric Detail</p>
                  <h3 class="mt-1 text-xl font-black text-white">{{ selectedMetricDetail.displayName }}</h3>
                  <p class="mt-1 text-sm text-slate-300">{{ selectedMetricDetail.category }} · {{ selectedMetricDetail.label }}</p>
                </div>
                <button
                  type="button"
                  class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-white/10"
                  @click="closeBenchmarkMetricDetail"
                >
                  Close
                </button>
              </div>

              <div class="overflow-y-auto p-4">
                <div v-if="!selectedMetricDetail.hasSourceMix" class="mb-3 rounded-lg border border-amber-300/20 bg-amber-500/10 p-3 text-sm text-amber-100">
                  Benchmark detail is not available yet.
                </div>

                <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                  <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-white/35">Raw Value</p>
                    <p class="mt-1 text-2xl font-black text-white">{{ selectedMetricDetail.rawValue }}</p>
                  </div>
                  <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-white/35">Final Score</p>
                    <p class="mt-1 text-2xl font-black" :class="scoreTone(selectedMetricDetail.metric.score_0_100)">{{ selectedMetricDetail.score }}</p>
                    <p class="mt-1 text-xs text-slate-300">{{ selectedMetricDetail.finalPercentile }}</p>
                  </div>
                  <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-white/35">Source</p>
                    <p class="mt-1 text-lg font-black text-white">{{ selectedMetricDetail.source }}</p>
                    <p class="mt-1 text-xs text-slate-300">{{ selectedMetricDetail.confidence }}</p>
                  </div>
                  <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-white/35">Selected Bucket</p>
                    <p class="mt-1 text-lg font-black text-white">{{ selectedMetricDetail.bucketLabel }}</p>
                    <p class="mt-1 text-xs text-slate-300">{{ selectedMetricDetail.bucketCount }} players</p>
                  </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-2">
                  <div class="rounded-lg border border-cyan-300/20 bg-cyan-500/10 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-cyan-200/80">Benchmark Source</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-slate-200">
                      <p>Research Weight: <span class="font-black text-white">{{ selectedMetricDetail.researchWeight }}</span></p>
                      <p>Population Weight: <span class="font-black text-white">{{ selectedMetricDetail.populationWeight }}</span></p>
                      <p>Research Percentile: <span class="font-black text-white">{{ selectedMetricDetail.researchPercentile }}</span></p>
                      <p>Population Percentile: <span class="font-black text-white">{{ selectedMetricDetail.populationPercentile }}</span></p>
                      <p>Bucket Count: <span class="font-black text-white">{{ selectedMetricDetail.populationBucketCount }}</span></p>
                      <p>Population Confidence: <span class="font-black text-white">{{ selectedMetricDetail.populationConfidence }}</span></p>
                      <p>Population Usable: <span class="font-black text-white">{{ selectedMetricDetail.populationUsable }}</span></p>
                    </div>
                    <p class="mt-3 rounded border border-white/10 bg-slate-950/35 p-2 text-xs text-cyan-100">
                      {{ selectedMetricSourceExplanation }}
                    </p>
                  </div>

                  <div class="rounded-lg border border-indigo-300/20 bg-indigo-500/10 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-indigo-200/80">Population Bucket Quality</p>
                    <p class="mt-2 text-sm text-slate-200">
                      <span class="font-black text-white">Selected:</span> {{ selectedMetricDetail.bucketLabel }}
                    </p>
                    <p class="mt-1 text-xs text-slate-300 break-all">
                      <span class="font-black text-white">Key:</span> {{ selectedMetricDetail.bucketKey || '—' }}
                    </p>
                    <p class="mt-2 rounded border border-white/10 bg-slate-950/35 p-2 text-xs text-indigo-100">
                      {{ selectedMetricDetail.bucketExplanation }}
                    </p>
                    <div class="mt-3">
                      <p class="text-[10px] uppercase tracking-widest text-white/40">Attempted Buckets</p>
                      <div v-if="selectedMetricDetail.attemptedBuckets.length" class="mt-2 flex flex-wrap gap-1.5">
                        <span
                          v-for="attempt in selectedMetricDetail.attemptedBuckets.slice(0, 5)"
                          :key="`drawer-${attempt.level}-${attempt.bucket_key}`"
                          class="rounded-full border px-2 py-1 text-[10px]"
                          :class="attempt.usable ? 'border-indigo-300/30 bg-indigo-500/15 text-indigo-100' : 'border-white/10 bg-slate-950/40 text-slate-300'"
                        >
                          {{ metricAttemptedBucketLabel(attempt) }}
                        </span>
                      </div>
                      <p v-else class="mt-2 text-xs text-slate-300">No bucket ladder details are available yet.</p>
                    </div>
                  </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-2">
                  <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-white/40">Gap Explanation</p>
                    <p class="mt-2 text-sm text-slate-200">{{ selectedMetricDetail.goodGap }}</p>
                    <p class="mt-1 text-sm text-slate-200">{{ selectedMetricDetail.eliteGap }}</p>
                  </div>

                  <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-white/40">Evidence</p>
                    <ul v-if="selectedMetricDetail.evidence.length" class="mt-2 list-disc space-y-1 pl-5 text-xs text-slate-300">
                      <li v-for="(line, idx) in selectedMetricDetail.evidence" :key="`evidence-${idx}-${line}`">{{ line }}</li>
                    </ul>
                    <p v-else class="mt-2 text-xs text-slate-300">No additional evidence is available yet.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>
    </div>
  </Layout>
</template>
