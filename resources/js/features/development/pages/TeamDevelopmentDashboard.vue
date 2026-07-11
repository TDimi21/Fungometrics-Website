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
const benchmarkTaskReviews = ref(null)
const benchmarkTaskPromotions = ref(null)
const benchmarkTaskActionLoading = ref('')
const benchmarkTaskActionError = ref('')
const benchmarkTaskActionMessage = ref('')
const benchmarkReviewActionLoading = ref('')
const benchmarkReviewActionError = ref('')
const benchmarkReviewActionMessage = ref('')
const benchmarkPromotionActionLoading = ref('')
const benchmarkPromotionActionError = ref('')
const benchmarkPromotionActionMessage = ref('')
const selectedPromotionPreview = ref(null)
const benchmarkRefreshLoading = ref(false)
const benchmarkRefreshError = ref('')
const benchmarkRefreshMessage = ref('')

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
  benchmarkTaskReviews.value = null
  benchmarkTaskPromotions.value = null
  selectedPromotionPreview.value = null
  benchmarkTaskActionError.value = ''
  benchmarkTaskActionMessage.value = ''
  benchmarkReviewActionError.value = ''
  benchmarkReviewActionMessage.value = ''
  benchmarkPromotionActionError.value = ''
  benchmarkPromotionActionMessage.value = ''

  const teamId = resolveTeamId.value
  if (!teamId) {
    loadError.value = 'Select a team to load development command center data.'
    return
  }

  loading.value = true
  try {
    const [boardRes, dashRes, perfRes, intelligenceRes, benchmarkTasksRes, benchmarkReviewsRes, benchmarkPromotionsRes] = await Promise.all([
      axiosGet(`coach/teams/${teamId}/player-development-board`).catch(() => null),
      axiosGet(`dashboard/${teamId}`).catch(() => null),
      axiosGet(`coach/performance-overview/${teamId}`).catch(() => null),
      axiosGet(`coach/teams/${teamId}/intelligence`, { days: 365 }).catch(() => null),
      axiosGet(`intelligence/teams/${teamId}/benchmark-tasks`).catch(() => null),
      axiosGet(`intelligence/teams/${teamId}/benchmark-task-reviews`).catch(() => null),
      axiosGet(`intelligence/teams/${teamId}/benchmark-task-promotions`).catch(() => null),
    ])

    board.value = Array.isArray(boardRes?.data?.data) ? boardRes.data.data : []
    dashboard.value = dashRes?.data?.data ?? {}
    perf.value = perfRes?.data?.data ?? {}
    teamIntelligence.value = intelligenceRes?.data?.data || intelligenceRes?.data || null
    const benchmarkTaskPayload = benchmarkTasksRes?.data?.data || benchmarkTasksRes?.data || {}
    savedBenchmarkTasks.value = Array.isArray(benchmarkTaskPayload.tasks) ? benchmarkTaskPayload.tasks : []
    const benchmarkReviewPayload = benchmarkReviewsRes?.data?.data || benchmarkReviewsRes?.data || null
    benchmarkTaskReviews.value = benchmarkReviewPayload || teamIntelligence.value?.benchmark_task_review_summary || null
    const benchmarkPromotionPayload = benchmarkPromotionsRes?.data?.data || benchmarkPromotionsRes?.data || null
    benchmarkTaskPromotions.value = benchmarkPromotionPayload || teamIntelligence.value?.benchmark_task_promotion_status || null

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

const formatDate = (value) => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value).slice(0, 10)
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
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
  throwing: 'Throwing',
  roster: 'Roster Profile',
  trust: 'Data Confidence',
  baseline: 'Benchmark Baseline',
  practice: 'Practice Focus',
  benchmark: 'Benchmark',
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
  research_only: 'Research Only',
  composite_enabled: 'Research + FMTRX Blend',
  population_enabled: 'Population Ready',
  needs_review: 'Needs Review',
  disabled: 'Not Used',
  auto: 'Auto Safety Mode',
  player_context: 'Roster Profile',
  player_benchmark_metrics: 'Benchmark Baseline',
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

const benchmarkRefreshStatus = computed(() => {
  const status = teamIntelligence.value?.benchmark_refresh_status
  return status && typeof status === 'object'
    ? status
    : {
        status: 'unknown',
        last_refreshed_at: null,
        reason: 'Benchmark intelligence is calculated live from current data.',
        changed_signals: [],
        warnings: [],
      }
})

const benchmarkRefreshSignals = computed(() => asArray(benchmarkRefreshStatus.value?.changed_signals).slice(0, 3))
const benchmarkRefreshWarnings = computed(() => asArray(benchmarkRefreshStatus.value?.warnings).slice(0, 3))

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
  low: 'Early Sample',
  medium: 'Growing Sample',
  high: 'Strong Sample',
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
      activeSourceText: 'Benchmark source details are not available yet.',
      guidance: 'Benchmark source details are not available yet.',
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
	      : 'Research benchmarks are active while FMTRX population learning grows.',
	    guidance: averageBucketCount < 30
	      ? 'FMTRX needs at least 30 trusted values before population learning can influence a metric.'
	      : 'FMTRX trust improves as more players complete roster profiles and baseline testing.',
	  }
	})

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
  exact_peer: 'Closest Peer Group',
  athletic_peer: 'Athletic Peer Group',
  age_role: 'Age + Role Group',
  age_only: 'Age Group',
  global_clean: 'Broad FMTRX Population',
  none: 'Not Enough FMTRX Data',
}

const bucketExplanations = {
  exact_peer: 'Compared with players most similar in age, level, position, body size, height, throwing hand, and batting side.',
  athletic_peer: 'Compared against players with similar age, level, position, and bodyweight.',
  age_role: 'Compared against players with similar age, level, and position.',
  age_only: 'Compared against players in the same age group.',
  global_clean: 'Compared with all valid trusted FMTRX values because smaller peer groups were not large enough yet.',
  none: 'FMTRX needs at least 30 trusted values before population learning can influence this metric, so research benchmarks remain active.',
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

const bucketLabel = (level) => bucketLabelOverrides[String(level ?? '').trim()] || 'Comparison group details are not available yet.'
const bucketExplanation = (level) => bucketExplanations[String(level ?? '').trim()] || 'Comparison group details are not available yet.'

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
    return 'FMTRX is using a broad comparison because this player is missing context like age, level, position, height, weight, throws, or bats.'
  }
  return bucketExplanation(level)
}

const metricSourceStatusText = (metric) => {
  const sourceMix = metricSourceMix(metric)
  const populationWeight = n(sourceMix.population_weight) ?? 0
  const bucketCount = metricPopulationBucketCountValue(metric)
  const level = displayBucketLevel(metric)

  if (populationWeight <= 0) {
    return bucketCount < 30
      ? 'FMTRX needs at least 30 trusted values before population learning can influence this metric.'
      : 'Research benchmark remains active. FMTRX player data is not included in this score.'
  }

  if (level === 'broad_unknown' || level === 'global_clean') {
    return 'This is a broad comparison group. Peer-specific confidence improves as more players complete roster profiles and baseline testing.'
  }

  if (level === 'exact_peer') {
    return 'Strong peer match.'
  }

  if (bucketCount >= 300) return 'Strong FMTRX player sample is included in this score.'
  if (bucketCount >= 100) return 'Growing FMTRX player sample is included in this score.'
  if (bucketCount >= 30) return 'Early FMTRX player sample is included in this score.'

  return 'FMTRX player data is included in this score.'
}

const trustStatusDefinitions = {
  research_only: {
    label: 'Research Only',
    meaning: 'FMTRX population data is not used in this score yet. Research standards remain active.',
    badge: 'border-sky-300/30 bg-sky-500/15 text-sky-100',
  },
  composite_enabled: {
    label: 'Research + FMTRX Blend',
    meaning: 'This metric is approved to blend research standards with FMTRX population data when the sample is large enough.',
    badge: 'border-emerald-300/35 bg-emerald-500/15 text-emerald-100',
  },
  population_enabled: {
    label: 'Population Ready',
    meaning: 'This metric has enough trusted FMTRX data to support population-based comparison.',
    badge: 'border-purple-300/35 bg-purple-500/15 text-purple-100',
  },
  needs_review: {
    label: 'Needs Review',
    meaning: 'FMTRX has data for this metric, but it needs more quality review before it can influence scoring.',
    badge: 'border-amber-300/35 bg-amber-500/15 text-amber-100',
  },
  disabled: {
    label: 'Not Used',
    meaning: 'This metric is not currently used for benchmark scoring.',
    badge: 'border-red-300/35 bg-red-500/15 text-red-100',
  },
  auto: {
    label: 'Auto Safety Mode',
    meaning: 'FMTRX is using the safest available benchmark source for this metric.',
    badge: 'border-white/15 bg-white/10 text-slate-100',
  },
}

const normalizeTrustStatus = (status) => {
  const key = String(status ?? '').trim()
  if (!key) return ''

  return {
    research_benchmark: 'research_only',
    fmtrx_population: 'population_enabled',
    population_ready: 'population_enabled',
    composite: 'composite_enabled',
    composite_benchmark: 'composite_enabled',
    blend: 'composite_enabled',
  }[key] || key
}

const boolFromPolicy = (value) => {
  if (value === true || value === 1 || value === '1') return true
  if (value === false || value === 0 || value === '0') return false

  const text = String(value ?? '').trim().toLowerCase()
  if (['true', 'yes', 'enabled', 'allowed'].includes(text)) return true
  if (['false', 'no', 'disabled', 'blocked'].includes(text)) return false

  return null
}

const yesNo = (value) => (value === true ? 'Yes' : value === false ? 'No' : 'Unknown')

const metricTrustPolicy = (metric) =>
  metric?.population_policy && typeof metric.population_policy === 'object'
    ? metric.population_policy
    : {}

const metricTrustStatus = (metric) => {
  const policyStatus = normalizeTrustStatus(metricTrustPolicy(metric).status)
  if (policyStatus) return trustStatusDefinitions[policyStatus] ? policyStatus : 'auto'

  const source = normalizeTrustStatus(metric?.source)
  if (source === 'research_only') return 'research_only'
  if (source === 'composite_enabled') return 'composite_enabled'
  if (source === 'population_enabled') return 'population_enabled'

  if (!metricPopulationUsableValue(metric)) return 'research_only'
  if (bucketLevelValue(metric) === 'none') return 'research_only'

  return 'auto'
}

const metricTrustPopulationAllowed = (metric) => {
  const policy = metricTrustPolicy(metric)
  const explicit = boolFromPolicy(policy.population_allowed ?? policy.population_enabled)
  if (explicit !== null) return explicit

  return ['population_enabled', 'composite_enabled'].includes(metricTrustStatus(metric))
}

const metricTrustCompositeAllowed = (metric) => {
  const policy = metricTrustPolicy(metric)
  const explicit = boolFromPolicy(policy.composite_allowed ?? policy.composite_enabled)
  if (explicit !== null) return explicit

  return metricTrustStatus(metric) === 'composite_enabled'
}

const metricResearchFallbackActive = (metric) => {
  const sourceMix = metricSourceMix(metric)
  const researchWeight = n(sourceMix.research_weight)
  if (researchWeight !== null) return researchWeight > 0

  const source = normalizeTrustStatus(metric?.source)
  if (source === 'research_only' || source === 'composite_enabled') return true
  if (source === 'population_enabled') return false

  return !metricPopulationUsableValue(metric) || metricTrustStatus(metric) !== 'population_enabled'
}

const metricTrustBadge = (metric) => {
  const status = metricTrustStatus(metric)
  const definition = trustStatusDefinitions[status] || trustStatusDefinitions.auto
  return {
    status,
    ...definition,
  }
}

const metricTrustReason = (metric) => {
  const policy = metricTrustPolicy(metric)
  return policy.reason
    || metricTrustBadge(metric).meaning
    || metricSourceStatusText(metric)
}

const metricTrustTooltip = (metric) => {
  const badge = metricTrustBadge(metric)
  return [
    `Trust status: ${badge.label}`,
    `Reason: ${metricTrustReason(metric)}`,
    `FMTRX player data allowed: ${yesNo(metricTrustPopulationAllowed(metric))}`,
    `Blend allowed: ${yesNo(metricTrustCompositeAllowed(metric))}`,
    `Comparison group count: ${metricPopulationBucketCount(metric)}`,
    `FMTRX confidence: ${metricPopulationConfidence(metric)}`,
    `Research benchmark active: ${yesNo(metricResearchFallbackActive(metric))}`,
  ].join('\n')
}

const metricTrustLines = (metric) => [
  { label: 'Trust Status', value: metricTrustBadge(metric).label },
  { label: 'Reason', value: metricTrustReason(metric) },
  { label: 'FMTRX Data Allowed', value: yesNo(metricTrustPopulationAllowed(metric)) },
  { label: 'Blend Allowed', value: yesNo(metricTrustCompositeAllowed(metric)) },
  { label: 'Research Benchmark Active', value: yesNo(metricResearchFallbackActive(metric)) },
]

const benchmarkTrustMetrics = computed(() => {
  const profileMetrics = asArray(benchmarkProfile.value?.metrics)
  return profileMetrics.length ? profileMetrics : allBenchmarkMetricRows.value
})

const benchmarkTrustSummary = computed(() => {
  const counts = Object.fromEntries(Object.keys(trustStatusDefinitions).map((status) => [status, 0]))
  for (const metric of benchmarkTrustMetrics.value) {
    const status = metricTrustStatus(metric)
    counts[status] = (counts[status] || 0) + 1
  }

  return {
    available: benchmarkTrustMetrics.value.length > 0,
    total: benchmarkTrustMetrics.value.length,
    counts,
  }
})

const metricHasPolicy = (metric) => Object.keys(metricTrustPolicy(metric)).length > 0
const metricHasSourceMix = (metric) => Object.keys(metricSourceMix(metric)).length > 0
const metricHasBucketDetails = (metric) => {
  const detail = metricPopulationDetail(metric)
  return Boolean(
    metric?.selected_bucket_level
    || metric?.selected_bucket_key
    || metric?.bucket_count !== undefined
    || metric?.population_bucket_count !== undefined
    || asArray(metric?.attempted_buckets).length
    || detail.selected_bucket_level
    || detail.selected_bucket_key
    || detail.bucket_key
    || detail.bucket_count !== undefined
    || asArray(detail.attempted_buckets).length
  )
}

const benchmarkPayloadQa = computed(() => {
  const metrics = benchmarkTrustMetrics.value
  const metricCount = metrics.length
  const withPolicy = metrics.filter(metricHasPolicy).length
  const withSourceMix = metrics.filter(metricHasSourceMix).length
  const withBucketDetails = metrics.filter(metricHasBucketDetails).length

  return {
    metricCount,
    withPolicy,
    missingPolicy: Math.max(metricCount - withPolicy, 0),
    withSourceMix,
    missingSourceMix: Math.max(metricCount - withSourceMix, 0),
    withBucketDetails,
    missingBucketDetails: Math.max(metricCount - withBucketDetails, 0),
  }
})

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

const metricSourceExplanation = (metric) => {
  const source = metric?.source || (metricPopulationUsableValue(metric) ? 'fmtrx_population' : 'research_benchmark')
  if (source === 'composite' || source === 'composite_benchmark') {
    return 'This score blends research standards with trusted FMTRX player data. Research still protects the score while the FMTRX sample grows.'
  }
  if (source === 'fmtrx_population') {
    return 'This score is based on trusted FMTRX player data from a large enough sample.'
  }
  return 'This score is based on age-adjusted baseball research standards. FMTRX will keep using this until enough trusted FMTRX player data is available.'
}

const metricConfidenceExplanation = (metric) => {
  const confidence = String(metricPopulationConfidenceValue(metric) || '').trim()
  if (confidence === 'high') return 'FMTRX has a large trusted sample for this benchmark.'
  if (confidence === 'medium') return 'FMTRX has a stronger sample and can place more trust in the population comparison.'
  if (confidence === 'low') return 'FMTRX has enough data to learn from this sample, but confidence is still early.'
  return 'FMTRX does not have enough trusted player values to use this comparison yet.'
}

const metricImproveScoreCopy = (metric) => {
  const goodGap = n(metric?.gap_to_good)
  const eliteGap = n(metric?.gap_to_elite)
  if (goodGap !== null && goodGap > 0) return metricGapSentence(metric, 'gap_to_good', 'Good')
  if (eliteGap !== null && eliteGap > 0) return metricGapSentence(metric, 'gap_to_elite', 'Elite')
  return 'Keep collecting quality reps and compare the next session against this benchmark.'
}

const metricImproveConfidenceCopy = (metric) => {
  const level = displayBucketLevel(metric)
  const bucketCount = metricPopulationBucketCountValue(metric)
  if (level === 'broad_unknown') {
    return 'Add roster details like age, level, position, height, weight, throws, and bats to improve peer matching.'
  }
  if (bucketCount < 30) {
    return 'FMTRX needs at least 30 trusted values before population learning can influence this metric.'
  }
  if (level === 'global_clean') {
    return 'Add roster details and collect more baselines so FMTRX can compare this player to a tighter peer group.'
  }
  return 'Continue collecting trusted baseline data to strengthen future comparisons.'
}

const metricWhyMattersCopy = (metric) =>
  `${metric?.display_name || humanizeKey(metric?.metric_key, 'This benchmark')} helps show whether the player is ahead, on track, or needs a targeted development focus.`

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
	    trustBadge: metricTrustBadge(metric),
	    trustTooltip: metricTrustTooltip(metric),
	    trustLines: metricTrustLines(metric),
	    coachExplanations: [
	      { label: 'Why This Score Matters', value: metricWhyMattersCopy(metric) },
	      { label: 'Where This Benchmark Came From', value: metricSourceExplanation(metric) },
	      { label: 'How Confident FMTRX Is', value: metricConfidenceExplanation(metric) },
	      { label: 'What Would Improve The Score', value: metricImproveScoreCopy(metric) },
	      { label: 'What Would Improve Confidence', value: metricImproveConfidenceCopy(metric) },
	    ],
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
	    return 'FMTRX does not have enough trusted values for this comparison yet. Research benchmarks remain active.'
	  }
	  if (detail.sourceKey === 'composite' || detail.sourceKey === 'composite_benchmark') {
	    return 'FMTRX is blending research standards with trusted FMTRX population data for this metric. Research still protects the score while the FMTRX sample grows.'
	  }
	  if (detail.sourceKey === 'fmtrx_population') {
	    return 'This score is based on trusted FMTRX player data from a large enough sample.'
	  }
	  return 'FMTRX is using research standards for this metric because the trusted FMTRX sample is not large enough or not approved yet.'
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

const formatCoachPriority = (priority, fallback = 'Medium') => {
  const text = String(priority ?? '').trim().toLowerCase()
  if (['critical', 'high'].includes(text)) return 'High'
  if (['medium', 'moderate'].includes(text)) return 'Medium'
  if (['low', 'none'].includes(text)) return 'Low'
  return fallback
}

const coachActionPriorityClass = (priority) => ({
  High: 'border-red-300/30 bg-red-500/15 text-red-100',
  Medium: 'border-amber-300/30 bg-amber-500/15 text-amber-100',
  Low: 'border-cyan-300/30 bg-cyan-500/15 text-cyan-100',
}[formatCoachPriority(priority)] || 'border-white/10 bg-white/5 text-slate-200')

const coachActionCategoryClass = (category) => ({
  hitting: 'text-red-200',
  pitching: 'text-sky-200',
  throwing: 'text-cyan-200',
  strength: 'text-amber-200',
  athletic: 'text-purple-200',
  mobility: 'text-emerald-200',
  roster: 'text-orange-200',
  trust: 'text-indigo-200',
}[String(category ?? '').toLowerCase()] || 'text-slate-200')

const coachActionMetricName = (metric) => {
  if (typeof metric === 'string') return coachFriendlyMetricLabel(metric)
  return metric?.display_name || coachFriendlyMetricLabel(metric)
}

const rowMetricKeys = (row) => [
  row?.metric_key,
  ...asArray(row?.metric_keys),
  ...asArray(row?.metrics).map((metric) => typeof metric === 'string' ? metric : metric?.metric_key),
].filter(Boolean)

const rowMatchesMetric = (row, keys) => {
  const keySet = new Set(asArray(keys))
  return rowMetricKeys(row).some((key) => keySet.has(key))
}

const metricRowsForKeys = (keys) => [
  ...allCriticalMissingRows.value,
  ...allSupportingMissingRows.value,
  ...benchmarkMissingMetrics.value,
].filter((row) => rowMatchesMetric(row, keys))

const missingCountForRows = (rows) => {
  const counts = asArray(rows)
    .map((row) => n(row?.missing_count))
    .filter((count) => count !== null)
  return counts.length ? Math.max(...counts) : null
}

const playersForRows = (rows) => {
  const players = new Map()
  asArray(rows).forEach((row) => {
    asArray(row?.players_missing || row?.players).forEach((player) => {
      const id = player?.player_id || player?.id || player?.player_name || player?.name
      if (!id || players.has(id)) return
      players.set(id, player?.player_name || player?.name || id)
    })
  })
  return Array.from(players.values()).slice(0, 6)
}

const metricKeysForRows = (rows) =>
  Array.from(new Set(asArray(rows).flatMap(rowMetricKeys)))
    .map((key) => coachFriendlyMetricLabel(key))
    .filter(Boolean)
    .slice(0, 6)

const actionFromCollectionNextBest = () => {
  const next = benchmarkCollectionNextAction.value
  if (!next) return null

  return {
    title: next.title || 'Complete Benchmark Baselines',
    priority: formatCoachPriority(next.priority || benchmarkCollectionPlan.value?.priority_level),
    category: next.category || 'baseline',
    why: next.why || 'FMTRX found a benchmark collection task that will improve confidence.',
    action: asArray(next.coach_instructions).length
      ? asArray(next.coach_instructions).join(' ')
      : 'Complete the recommended benchmark baseline block.',
    players: asArray(next.players).map((player) => player?.player_name || player?.name || player?.player_id).filter(Boolean).slice(0, 6),
    metrics: asArray(next.metrics).map((metric) => coachActionMetricName(metric)).filter(Boolean).slice(0, 6),
    minutes: n(next.duration_minutes),
    source: 'collection_plan',
  }
}

const coachActionForMetric = (metric, overrides = {}) => {
  const key = String(metric?.metric_key || metric || '').trim()
  const category = String(metric?.category || overrides.category || '').toLowerCase()
  const label = coachActionMetricName(metric)

  if (['average_exit_velocity', 'max_exit_velocity', 'hard_hit_percentage', 'line_drive_percentage'].includes(key) || category === 'hitting') {
    return {
      title: 'Run Exit Velocity Baseline',
      priority: overrides.priority || 'High',
      category: 'hitting',
      why: `${label} is limiting the hitting benchmark picture.`,
      action: 'Run controlled barrel rounds, then max-intent EV rounds. Track average EV, max EV, and line-drive quality.',
      metrics: [label],
      source: overrides.source || 'benchmark_profile',
    }
  }

  if (['average_fastball_velocity', 'max_fastball_velocity', 'strike_percentage'].includes(key) || category === 'pitching') {
    return {
      title: 'Run Bullpen Baseline',
      priority: overrides.priority || 'High',
      category: 'pitching',
      why: `${label} is needed to evaluate command and mound performance.`,
      action: 'Run a tracked bullpen and record average FB velo, max FB velo, and strike percentage.',
      metrics: [label],
      source: overrides.source || 'benchmark_profile',
    }
  }

  if (['long_toss_max_distance', 'weighted_ball_5oz_velocity'].includes(key)) {
    return {
      title: 'Collect Throwing Capacity Baseline',
      priority: overrides.priority || 'Medium',
      category: 'throwing',
      why: 'FMTRX needs long toss and 5 oz velocity to understand throwing capacity and mound transfer.',
      action: 'Record max long toss distance and 5 oz velocity where appropriate.',
      metrics: [label],
      source: overrides.source || 'benchmark_profile',
    }
  }

  if (['bench_press', 'squat', 'deadlift', 'pull_ups', 'pushups'].includes(key) || category === 'strength') {
    return {
      title: 'Complete Strength Baseline',
      priority: overrides.priority || 'Medium',
      category: 'strength',
      why: 'Strength benchmarks are still research-based because FMTRX needs more clean strength data.',
      action: 'Collect bench, squat, deadlift, pull-ups, and pushups during the next testing block.',
      metrics: [label],
      source: overrides.source || 'benchmark_profile',
    }
  }

  if (['forty_yard_dash', 'sixty_yard_dash', 'broad_jump', 'vertical_jump'].includes(key) || category === 'athletic') {
    return {
      title: 'Run Athletic Testing',
      priority: overrides.priority || 'Medium',
      category: 'athletic',
      why: 'Speed and explosiveness data improves player benchmark accuracy.',
      action: 'Collect 40-yard, 60-yard, broad jump, and vertical jump baselines.',
      metrics: [label],
      source: overrides.source || 'benchmark_profile',
    }
  }

  if (key.includes('mobility') || category === 'mobility') {
    return {
      title: 'Run Mobility Screen',
      priority: overrides.priority || 'Medium',
      category: 'mobility',
      why: 'Mobility scores need more review and clean data before FMTRX population learning can influence scoring.',
      action: 'Screen shoulder, hip, and T-spine mobility and record scores.',
      metrics: [label],
      source: overrides.source || 'benchmark_profile',
    }
  }

  return {
    title: `Address ${label}`,
    priority: overrides.priority || 'Medium',
    category: category || 'benchmark',
    why: `${label} is part of the current benchmark picture.`,
    action: 'Use the next testing block to collect a clean baseline and coach the related development need.',
    metrics: [label],
    source: overrides.source || 'benchmark_profile',
  }
}

const selectedMetricCoachAction = computed(() =>
  selectedBenchmarkMetric.value ? coachActionForMetric(selectedBenchmarkMetric.value) : null
)

const coachActionCards = computed(() => {
  const actions = []
  const addAction = (action) => {
    if (!action?.title) return
    const title = String(action.title).trim()
    if (!title || actions.some((existing) => existing.title === title)) return
    actions.push({
      priority: formatCoachPriority(action.priority),
      category: action.category || 'benchmark',
      why: action.why || 'FMTRX found a benchmark signal that needs coach attention.',
      action: action.action || 'Review this benchmark and collect the next clean baseline.',
      players: asArray(action.players).filter(Boolean).slice(0, 6),
      metrics: asArray(action.metrics).filter(Boolean).slice(0, 6),
      minutes: n(action.minutes),
      source: action.source || 'benchmark_profile',
      title,
    })
  }

  const primaryTitle = String(primaryFocusCard.value.title || '').toLowerCase()
  if (primaryTitle.includes('exit velocity') || primaryTitle.includes('power')) {
    const rows = metricRowsForKeys(['average_exit_velocity', 'max_exit_velocity', 'hard_hit_percentage', 'line_drive_percentage'])
    const count = missingCountForRows(rows)
    addAction({
      title: 'Run Exit Velocity Baseline',
      priority: 'High',
      category: 'hitting',
      why: count
        ? `Power output is today’s primary focus and ${fmtCount(count, '0')} players need EV baselines.`
        : 'Power output is today’s primary focus.',
      action: 'Run a 15-minute EV baseline before power rounds. Track average EV, max EV, and line-drive quality.',
      players: playersForRows(rows),
      metrics: ['Exit Velocity Baseline', 'Max Exit Velocity Baseline', 'Line Drive Quality'],
      minutes: 15,
      source: 'decision_brief',
    })
  } else if (primaryTitle.includes('fastball') || primaryTitle.includes('command')) {
    const rows = metricRowsForKeys(['average_fastball_velocity', 'max_fastball_velocity', 'strike_percentage'])
    addAction({
      title: 'Run Bullpen Baseline',
      priority: 'High',
      category: 'pitching',
      why: 'Fastball command is today’s primary focus and FMTRX needs velocity plus strike percentage to evaluate mound performance.',
      action: 'Run a tracked bullpen and record average FB velo, max FB velo, and strike percentage.',
      players: playersForRows(rows),
      metrics: ['Fastball Velocity Baseline', 'Max Fastball Baseline', 'Strike % Baseline'],
      source: 'decision_brief',
    })
  } else if (primaryTitle.includes('long toss') || primaryTitle.includes('mound transfer')) {
    addAction({
      title: 'Collect Throwing Capacity Baseline',
      priority: 'High',
      category: 'throwing',
      why: 'Throwing capacity is today’s primary focus, and long toss plus 5 oz velocity clarify mound transfer.',
      action: 'Record max long toss distance and 5 oz velocity where appropriate.',
      metrics: ['Long Toss Max', '5 oz Velocity'],
      source: 'decision_brief',
    })
  } else if (primaryTitle && !primaryTitle.includes('needs more intelligence')) {
    addAction({
      title: primaryFocusCard.value.title,
      priority: primaryFocusCard.value.confidence === 'high' ? 'High' : 'Medium',
      category: 'practice',
      why: primaryFocusCard.value.why,
      action: primaryFocusCard.value.action,
      source: 'decision_brief',
    })
  }

  addAction(actionFromCollectionNextBest())

  if ((n(rosterCleanupRow.value?.missing_count) ?? rosterCleanupPlayers.value.length) > 0) {
    addAction({
      title: 'Clean Up Roster Profiles',
      priority: 'High',
      category: 'roster',
      why: `${fmtCount(n(rosterCleanupRow.value?.missing_count) ?? rosterCleanupPlayers.value.length, '0')} players are missing DOB or position, which limits peer comparisons.`,
      action: 'Update roster profile details before the next benchmark session.',
      players: rosterCleanupPlayers.value.map((player) => player?.player_name || player?.name || player).filter(Boolean),
      metrics: ['DOB', 'Position', 'Height', 'Weight', 'Throws', 'Bats'],
      source: 'benchmark_profile',
    })
  }

  const missingGroups = [
    {
      keys: ['average_exit_velocity', 'max_exit_velocity'],
      build: (rows) => ({
        title: 'Run Exit Velocity Baseline',
        priority: 'High',
        category: 'hitting',
        why: `${fmtCount(missingCountForRows(rows), '0')} players need EV baselines for better hitting benchmarks.`,
        action: 'Run controlled barrel rounds, then max-intent EV rounds. Track average EV, max EV, and line-drive quality.',
        players: playersForRows(rows),
        metrics: metricKeysForRows(rows),
        source: 'benchmark_profile',
      }),
    },
    {
      keys: ['average_fastball_velocity', 'max_fastball_velocity', 'strike_percentage'],
      build: (rows) => ({
        title: 'Run Bullpen Baseline',
        priority: 'High',
        category: 'pitching',
        why: 'FMTRX needs velocity and strike percentage to evaluate command and mound performance.',
        action: 'Run a tracked bullpen and record average FB velo, max FB velo, and strike percentage.',
        players: playersForRows(rows),
        metrics: metricKeysForRows(rows),
        source: 'benchmark_profile',
      }),
    },
    {
      keys: ['long_toss_max_distance', 'weighted_ball_5oz_velocity'],
      build: (rows) => ({
        title: 'Collect Throwing Capacity Baseline',
        priority: 'Medium',
        category: 'throwing',
        why: 'FMTRX needs long toss and 5 oz velocity to understand throwing capacity and mound transfer.',
        action: 'Record max long toss distance and 5 oz velocity where appropriate.',
        players: playersForRows(rows),
        metrics: metricKeysForRows(rows),
        source: 'benchmark_profile',
      }),
    },
    {
      keys: ['bench_press', 'squat', 'deadlift', 'pull_ups', 'pushups'],
      build: (rows) => ({
        title: 'Complete Strength Baseline',
        priority: 'Medium',
        category: 'strength',
        why: 'Strength benchmarks are still research-based because FMTRX needs more clean strength data.',
        action: 'Collect bench, squat, deadlift, pull-ups, and pushups during the next testing block.',
        players: playersForRows(rows),
        metrics: metricKeysForRows(rows),
        source: 'benchmark_profile',
      }),
    },
    {
      keys: ['forty_yard_dash', 'sixty_yard_dash', 'broad_jump', 'vertical_jump'],
      build: (rows) => ({
        title: 'Run Athletic Testing',
        priority: 'Medium',
        category: 'athletic',
        why: 'Speed and explosiveness data improves player benchmark accuracy.',
        action: 'Collect 40-yard, 60-yard, broad jump, and vertical jump baselines.',
        players: playersForRows(rows),
        metrics: metricKeysForRows(rows),
        source: 'benchmark_profile',
      }),
    },
    {
      keys: ['mobility_score', 'shoulder_mobility_score', 'hip_mobility_score', 't_spine_mobility_score'],
      build: (rows) => ({
        title: 'Run Mobility Screen',
        priority: 'Medium',
        category: 'mobility',
        why: 'Mobility scores need more review and clean data before FMTRX population learning can influence scoring.',
        action: 'Screen shoulder, hip, and T-spine mobility and record scores.',
        players: playersForRows(rows),
        metrics: metricKeysForRows(rows),
        source: 'benchmark_profile',
      }),
    },
  ]

  missingGroups.forEach((group) => {
    const rows = metricRowsForKeys(group.keys)
    if (rows.length) addAction(group.build(rows))
  })

  weakestBenchmarkMetrics.value.slice(0, 2).forEach((metric) => {
    addAction(coachActionForMetric(metric, {
      priority: n(metric?.score_0_100) !== null && n(metric?.score_0_100) < 50 ? 'High' : 'Medium',
      source: 'benchmark_profile',
    }))
  })

  const needsReviewMetric = benchmarkTrustMetrics.value.find((metric) => metricTrustStatus(metric) === 'needs_review')
  if (needsReviewMetric) {
    addAction({
      title: 'Review Data Quality',
      priority: 'Medium',
      category: 'trust',
      why: 'This metric has data, but FMTRX has not approved it for population learning yet.',
      action: 'Check for missing context, outliers, or inconsistent entries before enabling population influence.',
      metrics: [coachActionMetricName(needsReviewMetric)],
      source: 'trust_badge',
    })
  }

  const broadMetric = bucketQualityMetricRows.value.find((metric) =>
    metric.bucketDisplayLevel === 'broad_unknown' || metric.bucketLevel === 'global_clean'
  )
  if (broadMetric) {
    addAction({
      title: 'Improve Peer Matching',
      priority: 'Medium',
      category: 'trust',
      why: 'FMTRX used a broad comparison group for some metrics.',
      action: 'Add height, weight, throws, bats, and level to improve comparison quality.',
      metrics: [coachActionMetricName(broadMetric)],
      source: 'comparison_quality',
    })
  }

  const researchOnlyStrength = benchmarkTrustMetrics.value.find((metric) =>
    metricTrustStatus(metric) === 'research_only' && String(metric?.category || '').toLowerCase() === 'strength'
  )
  if (researchOnlyStrength) {
    addAction({
      title: 'Keep Research Benchmark Active',
      priority: 'Low',
      category: 'trust',
      why: 'FMTRX does not have enough trusted player data for this metric yet.',
      action: 'Continue collecting baselines. FMTRX will blend player data when the sample is ready.',
      metrics: [coachActionMetricName(researchOnlyStrength)],
      source: 'trust_badge',
    })
  }

  return actions.slice(0, 6)
})

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

const savedBenchmarkTaskStatusSummaryRows = computed(() => {
  const counts = benchmarkTaskStatusCounts.value || {}
  return ['draft', 'assigned', 'in_progress', 'completed', 'dismissed']
    .map((status) => ({
      status,
      count: counts[status] || 0,
    }))
    .filter((row) => row.count > 0)
})

const savedBenchmarkTaskPlayerSummaryRows = computed(() => {
  const rows = new Map()

  asArray(savedBenchmarkTasks.value).forEach((task) => {
    const playerId = task?.assigned_to_player_id || 'team'
    const playerName = task?.assigned_to_player_name || 'Team Task'
    const status = task?.status || 'unknown'

    if (!rows.has(playerId)) {
      rows.set(playerId, {
        player_id: playerId,
        player_name: playerName,
        task_count: 0,
        active_count: 0,
        completed_count: 0,
        dismissed_count: 0,
        status_counts: {},
        task_types: new Set(),
      })
    }

    const row = rows.get(playerId)
    row.task_count += 1
    row.status_counts[status] = (row.status_counts[status] || 0) + 1
    if (['assigned', 'in_progress'].includes(status)) row.active_count += 1
    if (status === 'completed') row.completed_count += 1
    if (status === 'dismissed') row.dismissed_count += 1
    if (task?.task_type) row.task_types.add(task.task_type)
  })

  return Array.from(rows.values())
    .map((row) => ({
      ...row,
      task_types: Array.from(row.task_types),
    }))
    .sort((a, b) => b.task_count - a.task_count || String(a.player_name).localeCompare(String(b.player_name)))
    .slice(0, 6)
})

const savedBenchmarkTaskTypeSummaryRows = computed(() => {
  const rows = new Map()

  asArray(savedBenchmarkTasks.value).forEach((task) => {
    const taskType = task?.task_type || 'unknown'
    const status = task?.status || 'unknown'

    if (!rows.has(taskType)) {
      rows.set(taskType, {
        task_type: taskType,
        task_count: 0,
        active_count: 0,
        completed_count: 0,
        dismissed_count: 0,
      })
    }

    const row = rows.get(taskType)
    row.task_count += 1
    if (['assigned', 'in_progress'].includes(status)) row.active_count += 1
    if (status === 'completed') row.completed_count += 1
    if (status === 'dismissed') row.dismissed_count += 1
  })

  return Array.from(rows.values())
    .sort((a, b) => b.task_count - a.task_count || taskTypeLabel(a.task_type).localeCompare(taskTypeLabel(b.task_type)))
    .slice(0, 6)
})

const benchmarkTaskReviewSummary = computed(() =>
  benchmarkTaskReviews.value || teamIntelligence.value?.benchmark_task_review_summary || null
)

const pendingBenchmarkReviewTasks = computed(() =>
  asArray(benchmarkTaskReviewSummary.value?.pending_tasks).slice(0, 8)
)

const pendingBenchmarkReviewCount = computed(() =>
  n(benchmarkTaskReviewSummary.value?.pending_count) ?? pendingBenchmarkReviewTasks.value.length
)

const benchmarkTaskPromotionStatus = computed(() =>
  benchmarkTaskPromotions.value || teamIntelligence.value?.benchmark_task_promotion_status || null
)

const approvedAwaitingPromotionTasks = computed(() =>
  asArray(benchmarkTaskPromotionStatus.value?.approved_awaiting_promotion).slice(0, 8)
)

const promotedBenchmarkTasks = computed(() =>
  asArray(benchmarkTaskPromotionStatus.value?.promoted_tasks).slice(0, 6)
)

const manualPromotionReviewTasks = computed(() =>
  [
    ...asArray(benchmarkTaskPromotionStatus.value?.manual_review_tasks),
    ...asArray(benchmarkTaskPromotionStatus.value?.skipped_tasks),
  ].slice(0, 6)
)

const promotionModeLabel = (mode) => ({
  profile_update: 'Profile Update',
  existing_table_insert: 'Existing Table',
  trusted_payload_only: 'Trusted Payload',
  manual_review: 'Manual Review',
}[mode] || humanizeKey(mode, 'Not Promoted'))

const promotionStatusLabel = (status) => ({
  promoted: 'Promoted',
  partial: 'Partial',
  skipped: 'Skipped',
  failed: 'Failed',
}[status] || 'Awaiting Promotion')

const promotionStatusClass = (status) => ({
  promoted: 'border-emerald-300/30 bg-emerald-500/15 text-emerald-100',
  partial: 'border-amber-300/30 bg-amber-500/15 text-amber-100',
  skipped: 'border-slate-300/20 bg-white/5 text-slate-200',
  failed: 'border-red-300/30 bg-red-500/15 text-red-100',
}[status] || 'border-sky-300/30 bg-sky-500/15 text-sky-100')

const promotionTaskTitle = (task) =>
  `${task?.assigned_to_player_name || 'Player'} · ${task?.title || taskTypeLabel(task?.task_type)}`

const promotionTargetLabel = (task) => {
  const result = task?.promotion_result || {}
  const table = result.target_table || task?.promotion_result?.target_table
  const mode = task?.promotion_mode || result.promotion_mode
  if (table) return `${promotionModeLabel(mode)} · ${table}`
  return promotionModeLabel(mode)
}

const reviewStateLabel = (status) => ({
  not_required: 'No Review Required',
  pending_review: 'Pending Coach Review',
  approved: 'Approved',
  rejected: 'Rejected',
  correction_requested: 'Correction Requested',
}[status] || humanizeKey(status, 'Not Submitted'))

const reviewStateClass = (status) => ({
  pending_review: 'border-amber-300/30 bg-amber-500/15 text-amber-100',
  approved: 'border-emerald-300/30 bg-emerald-500/15 text-emerald-100',
  rejected: 'border-red-300/30 bg-red-500/15 text-red-100',
  correction_requested: 'border-sky-300/30 bg-sky-500/15 text-sky-100',
  not_required: 'border-white/10 bg-white/5 text-white/55',
}[status] || 'border-white/10 bg-white/5 text-white/55')

const submittedValueRows = (task) =>
  asArray(task?.submitted_values_summary).slice(0, 6)

const refreshBenchmarkTaskReviews = async () => {
  const teamId = resolveTeamId.value
  if (!teamId) return

  const response = await axiosGet(`intelligence/teams/${teamId}/benchmark-task-reviews`)
  benchmarkTaskReviews.value = responsePayload(response)
}

const refreshBenchmarkTaskPromotions = async () => {
  const teamId = resolveTeamId.value
  if (!teamId) return

  const response = await axiosGet(`intelligence/teams/${teamId}/benchmark-task-promotions`)
  benchmarkTaskPromotions.value = responsePayload(response)
}

const applyReviewRefreshPayload = (refresh) => {
  if (!refresh || typeof refresh !== 'object') return

  teamIntelligence.value = {
    ...(teamIntelligence.value || {}),
    benchmark_profile: refresh.team_benchmark_profile || teamIntelligence.value?.benchmark_profile || null,
    decision_brief: refresh.decision_brief || teamIntelligence.value?.decision_brief || null,
    benchmark_collection_plan: refresh.collection_plan || teamIntelligence.value?.benchmark_collection_plan || null,
    benchmark_refresh_status: {
      status: refresh.refresh_status || 'unknown',
      last_refreshed_at: refresh.refreshed_at || null,
      reason: refresh.refresh_status === 'completed'
        ? 'Benchmark intelligence was refreshed from approved task data.'
        : 'Benchmark intelligence is calculated live from current data.',
      changed_signals: asArray(refresh.changed_signals),
      warnings: asArray(refresh.warnings),
      evidence: refresh.evidence || {},
    },
  }
}

const reviewBenchmarkTask = async (task, action) => {
  const taskId = task?.id || task?.task_id
  if (!taskId || benchmarkReviewActionLoading.value) return

  let payload = { days: 365 }
  if (action === 'reject') {
    const reason = window.prompt('Why is this benchmark task being rejected?')
    if (!String(reason || '').trim()) return
    payload = { reason: String(reason).trim() }
  }
  if (action === 'request-correction') {
    const message = window.prompt('What should the player correct?')
    if (!String(message || '').trim()) return
    payload = { message: String(message).trim() }
  }

  benchmarkReviewActionLoading.value = `${action}:${taskId}`
  benchmarkReviewActionError.value = ''
  benchmarkReviewActionMessage.value = ''
  try {
    const response = await axiosPost(`intelligence/benchmark-tasks/${taskId}/${action}`, payload)
    const result = responsePayload(response)
    applyReviewRefreshPayload(result.refresh)
    if (result.promotion) {
      selectedPromotionPreview.value = result.promotion
    }
    await Promise.all([
      refreshSavedBenchmarkTasks(),
      refreshBenchmarkTaskReviews(),
      refreshBenchmarkTaskPromotions(),
    ])
    benchmarkReviewActionMessage.value = action === 'approve'
      ? (result.message || 'Benchmark task approved. Trusted data promotion checked.')
      : action === 'reject'
        ? 'Benchmark task rejected and returned to the player.'
        : 'Correction request sent to the player.'
  } catch (error) {
    benchmarkReviewActionError.value = error?.response?.data?.message || `Could not ${humanizeKey(action)} benchmark task.`
  } finally {
    benchmarkReviewActionLoading.value = ''
  }
}

const previewBenchmarkPromotion = async (task) => {
  const taskId = task?.id || task?.task_id
  if (!taskId || benchmarkPromotionActionLoading.value) return

  benchmarkPromotionActionLoading.value = `preview:${taskId}`
  benchmarkPromotionActionError.value = ''
  benchmarkPromotionActionMessage.value = ''
  try {
    const response = await axiosPost(`intelligence/benchmark-tasks/${taskId}/preview-promotion`, {})
    selectedPromotionPreview.value = responsePayload(response)
    benchmarkPromotionActionMessage.value = 'Promotion preview loaded.'
  } catch (error) {
    benchmarkPromotionActionError.value = error?.response?.data?.message || 'Could not preview trusted data promotion.'
  } finally {
    benchmarkPromotionActionLoading.value = ''
  }
}

const promoteBenchmarkTask = async (task) => {
  const taskId = task?.id || task?.task_id
  if (!taskId || benchmarkPromotionActionLoading.value) return

  benchmarkPromotionActionLoading.value = `promote:${taskId}`
  benchmarkPromotionActionError.value = ''
  benchmarkPromotionActionMessage.value = ''
  try {
    const response = await axiosPost(`intelligence/benchmark-tasks/${taskId}/promote`, { days: 365 })
    const result = responsePayload(response)
    selectedPromotionPreview.value = result
    applyReviewRefreshPayload(result.refresh)
    await Promise.all([
      refreshSavedBenchmarkTasks(),
      refreshBenchmarkTaskPromotions(),
      refreshBenchmarkTaskReviews(),
    ])
    benchmarkPromotionActionMessage.value = `Promotion ${promotionStatusLabel(result.promotion_status).toLowerCase()}.`
  } catch (error) {
    benchmarkPromotionActionError.value = error?.response?.data?.message || 'Could not promote approved benchmark task.'
  } finally {
    benchmarkPromotionActionLoading.value = ''
  }
}

const promoteAllApprovedBenchmarkTasks = async () => {
  const teamId = resolveTeamId.value
  if (!teamId || benchmarkPromotionActionLoading.value) return

  benchmarkPromotionActionLoading.value = 'promote-all'
  benchmarkPromotionActionError.value = ''
  benchmarkPromotionActionMessage.value = ''
  try {
    const response = await axiosPost(`intelligence/teams/${teamId}/promote-approved-benchmark-tasks`, { days: 365 })
    const result = responsePayload(response)
    selectedPromotionPreview.value = asArray(result.results)[0] || null
    await Promise.all([
      refreshSavedBenchmarkTasks(),
      refreshBenchmarkTaskPromotions(),
      refreshBenchmarkTaskReviews(),
    ])
    benchmarkPromotionActionMessage.value = `Promoted ${fmtCount(result.promoted_count, '0')} approved tasks. ${fmtCount(result.skipped_count, '0')} skipped.`
  } catch (error) {
    benchmarkPromotionActionError.value = error?.response?.data?.message || 'Could not promote approved benchmark tasks.'
  } finally {
    benchmarkPromotionActionLoading.value = ''
  }
}

const responsePayload = (response) => response?.data?.data || response?.data || {}

const refreshBenchmarkIntelligence = async () => {
  const teamId = resolveTeamId.value
  if (!teamId || benchmarkRefreshLoading.value) return

  benchmarkRefreshLoading.value = true
  benchmarkRefreshError.value = ''
  benchmarkRefreshMessage.value = ''
  try {
    const response = await axiosPost(`intelligence/teams/${teamId}/refresh-benchmarks`, { days: 365 })
    const payload = responsePayload(response)
    teamIntelligence.value = {
      ...(teamIntelligence.value || {}),
      benchmark_profile: payload.team_benchmark_profile || teamIntelligence.value?.benchmark_profile || null,
      decision_brief: payload.decision_brief || teamIntelligence.value?.decision_brief || null,
      benchmark_collection_plan: payload.collection_plan || teamIntelligence.value?.benchmark_collection_plan || null,
      benchmark_refresh_status: {
        status: payload.refresh_status || 'unknown',
        last_refreshed_at: payload.refreshed_at || null,
        reason: payload.refresh_status === 'completed'
          ? 'Benchmark intelligence was refreshed from current data.'
          : 'Benchmark intelligence is calculated live from current data.',
        changed_signals: asArray(payload.changed_signals),
        warnings: asArray(payload.warnings),
        evidence: payload.evidence || {},
      },
    }
    benchmarkRefreshMessage.value = payload.refresh_status === 'completed'
      ? 'Benchmark intelligence refreshed.'
      : 'Benchmark intelligence refreshed with warnings.'
  } catch (error) {
    benchmarkRefreshError.value = error?.response?.data?.message || 'Could not refresh benchmark intelligence.'
  } finally {
    benchmarkRefreshLoading.value = false
  }
}

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

          <div class="mt-3 rounded-lg border border-cyan-300/20 bg-cyan-500/10 p-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="text-[10px] uppercase tracking-widest text-cyan-200/75">Benchmark Refresh Status</p>
                <p class="mt-1 text-sm text-slate-200">
                  {{ benchmarkRefreshStatus.reason || 'Benchmark intelligence is calculated live from current data.' }}
                </p>
                <p v-if="benchmarkRefreshStatus.last_refreshed_at" class="mt-1 text-[10px] uppercase tracking-wider text-white/40">
                  Last refreshed {{ benchmarkRefreshStatus.last_refreshed_at }}
                </p>
              </div>
              <button
                type="button"
                class="rounded-lg border border-cyan-300/30 bg-cyan-500/15 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-cyan-100 disabled:opacity-50"
                :disabled="benchmarkRefreshLoading"
                @click="refreshBenchmarkIntelligence"
              >
                {{ benchmarkRefreshLoading ? 'Refreshing...' : 'Refresh Benchmark Intelligence' }}
              </button>
            </div>
            <p v-if="benchmarkRefreshMessage" class="mt-2 rounded-md border border-emerald-300/20 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-100">
              {{ benchmarkRefreshMessage }}
            </p>
            <p v-if="benchmarkRefreshError" class="mt-2 rounded-md border border-red-300/20 bg-red-500/10 px-3 py-2 text-xs text-red-100">
              {{ benchmarkRefreshError }}
            </p>
            <div v-if="benchmarkRefreshSignals.length" class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-3">
              <div
                v-for="signal in benchmarkRefreshSignals"
                :key="`${signal.type}-${signal.message}`"
                class="rounded-md border border-white/10 bg-slate-950/35 p-2"
              >
                <p class="text-[10px] uppercase tracking-wider text-white/35">{{ humanizeKey(signal.type, 'Signal') }}</p>
                <p class="mt-1 text-xs text-slate-200">{{ signal.message }}</p>
              </div>
            </div>
            <div v-if="benchmarkRefreshWarnings.length" class="mt-2 rounded-md border border-amber-300/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-100">
              <p v-for="warning in benchmarkRefreshWarnings" :key="warning">Warning: {{ warning }}</p>
            </div>
          </div>

	          <div v-if="!hasBenchmarkProfile" class="mt-3 rounded-lg border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
	            Benchmark intelligence will appear after players have roster profiles and baseline data.
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
	                <p class="mt-2 text-xs text-slate-300">
	                  FMTRX compares your players to age-adjusted standards and, when enough trusted data exists, to the FMTRX player population.
	                </p>
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
                    <p class="text-[10px] uppercase tracking-wider text-white/35">FMTRX Data</p>
                    <p class="text-xl font-black text-white">{{ fmtValue(benchmarkSnapshot.populationShare, '%') }}</p>
                  </div>
                </div>
	                <p v-if="benchmarkSnapshot.populationShare === 0" class="mt-3 text-xs text-slate-300">
	                  Research benchmarks are active while FMTRX population learning grows.
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

            <div class="mt-4 rounded-lg border border-red-300/20 bg-red-500/10 p-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="text-[10px] uppercase tracking-widest text-red-200/80">Coach Action Language</p>
                  <h4 class="mt-1 text-lg font-semibold text-white">What To Do Next</h4>
                  <p class="mt-1 text-xs text-slate-300">
                    FMTRX turns benchmark results into simple coach actions for today’s practice plan.
                  </p>
                </div>
                <span class="rounded-full border border-red-300/30 bg-red-500/15 px-3 py-1 text-xs uppercase tracking-wider text-red-100">
                  {{ fmtCount(coachActionCards.length, '0') }} Actions
                </span>
              </div>

              <p v-if="!coachActionCards.length" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-300">
                FMTRX will recommend coach actions after more roster profiles and benchmark baselines are collected.
              </p>

              <div v-else class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-3">
                <div
                  v-for="(action, idx) in coachActionCards"
                  :key="`coach-action-${action.title}`"
                  class="rounded-lg border border-white/10 bg-slate-950/40 p-3"
                >
                  <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <p class="text-[10px] uppercase tracking-widest text-white/35">
                        {{ idx === 0 ? 'Top Action' : action.category === 'trust' ? 'Data Confidence Action' : 'Supporting Action' }}
                      </p>
                      <h5 class="mt-1 text-base font-black text-white">{{ action.title }}</h5>
                    </div>
                    <span
                      class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                      :class="coachActionPriorityClass(action.priority)"
                    >
                      {{ action.priority }}
                    </span>
                  </div>
                  <p class="mt-2 text-[10px] uppercase tracking-widest" :class="coachActionCategoryClass(action.category)">
                    {{ categoryLabel(action.category) }}
                    <span v-if="action.minutes"> · {{ fmtCount(action.minutes, '0') }} min</span>
                  </p>
                  <p class="mt-2 text-xs text-slate-300">
                    <span class="font-black text-white">Why:</span> {{ action.why }}
                  </p>
                  <p class="mt-2 text-xs text-red-100">
                    <span class="font-black text-white">Action:</span> {{ action.action }}
                  </p>
                  <p v-if="action.players.length" class="mt-2 text-[10px] text-slate-300">
                    <span class="font-black text-white">Players:</span> {{ action.players.join(', ') }}
                  </p>
                  <p v-if="action.metrics.length" class="mt-1 text-[10px] text-slate-300">
                    <span class="font-black text-white">Metrics:</span> {{ action.metrics.join(', ') }}
                  </p>
                </div>
              </div>
            </div>

            <div class="mt-4 rounded-lg border border-cyan-300/20 bg-cyan-500/10 p-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
	                  <p class="text-[10px] uppercase tracking-widest text-cyan-200/80">Benchmark Source Mix</p>
	                  <h4 class="mt-1 text-lg font-semibold text-white">Research + FMTRX Population Learning</h4>
	                  <p class="mt-1 text-xs text-slate-300">
	                    This shows how much of the benchmark system is powered by research standards, FMTRX player data, or a blend of both.
	                  </p>
                </div>
                <span
                  class="rounded-full border px-3 py-1 text-xs uppercase tracking-wider"
                  :class="benchmarkSourceMix.populationActive ? 'border-cyan-300/30 bg-cyan-500/15 text-cyan-100' : 'border-white/10 bg-white/5 text-slate-300'"
                >
                  {{ benchmarkSourceMix.populationActive ? 'Blend Active' : 'Research Active' }}
                </span>
              </div>

              <p v-if="!benchmarkSourceMix.available" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-300">
	                Benchmark source details are not available yet.
              </p>

              <template v-else>
                <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
                    <p class="text-[10px] uppercase tracking-wider text-white/35">Research</p>
                    <p class="text-xl font-black text-white">{{ fmtValue(benchmarkSourceMix.researchPercent, '%') }}</p>
                    <p class="mt-1 text-[10px] text-slate-300">{{ fmtCount(benchmarkSourceMix.researchCount, '0') }} research-only metrics</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
	                    <p class="text-[10px] uppercase tracking-wider text-white/35">FMTRX Player Data</p>
	                    <p class="text-xl font-black text-white">{{ fmtValue(benchmarkSourceMix.populationPercent, '%') }}</p>
	                    <p class="mt-1 text-[10px] text-slate-300">{{ fmtCount(benchmarkSourceMix.populationCount, '0') }} player-data metrics</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
	                    <p class="text-[10px] uppercase tracking-wider text-white/35">Blend</p>
                    <p class="text-xl font-black text-white">{{ fmtValue(benchmarkSourceMix.compositePercent, '%') }}</p>
                    <p class="mt-1 text-[10px] text-slate-300">{{ fmtCount(benchmarkSourceMix.compositeCount, '0') }} blended metrics</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
	                    <p class="text-[10px] uppercase tracking-wider text-white/35">Avg Group</p>
	                    <p class="text-xl font-black text-white">{{ fmtCount(benchmarkSourceMix.averageBucketCount, '0') }}</p>
	                    <p class="mt-1 text-[10px] text-slate-300">trusted sample size</p>
                  </div>
                </div>

                <div class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-200">
                  <p><span class="font-black text-white">What:</span> {{ benchmarkSourceMix.activeSourceText }}</p>
	                  <p class="mt-1"><span class="font-black text-white">Why:</span> FMTRX trust improves as players complete roster profiles and baseline testing.</p>
                  <p class="mt-1 text-cyan-100"><span class="font-black text-white">Rule:</span> {{ benchmarkSourceMix.guidance }}</p>
                </div>

                <div class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3">
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-[10px] uppercase tracking-widest text-cyan-200/80">Metric Source Status</p>
	                    <p class="text-[10px] uppercase tracking-widest text-white/35">Percentiles · blend weights · comparison group</p>
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
                          class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                          :class="metricTrustBadge(metric).badge"
                          :title="metricTrustTooltip(metric)"
                        >
                          {{ metricTrustBadge(metric).label }}
                        </span>
                      </div>
                      <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-300 md:grid-cols-3">
                        <p>Research: <span class="font-semibold text-white">{{ metricResearchPercentile(metric) }}</span></p>
	                        <p>FMTRX Data: <span class="font-semibold text-white">{{ metricPopulationPercentile(metric) }}</span></p>
	                        <p>Group: <span class="font-semibold text-white">{{ metricPopulationBucketCount(metric) }} players</span></p>
	                        <p>Trust: <span class="font-semibold text-white">{{ metricPopulationConfidence(metric) }}</span></p>
	                        <p>Used: <span class="font-semibold text-white">{{ metricPopulationUsable(metric) }}</span></p>
                        <p>Blend: <span class="font-semibold text-white">Research {{ metricSourceWeight(metric, 'research_weight') }} / FMTRX {{ metricSourceWeight(metric, 'population_weight') }}</span></p>
                      </div>
                      <p class="mt-2 text-[10px] text-slate-400">{{ metricSourceStatusText(metric) }}</p>
                    </button>
                  </div>
                  <p v-if="!sourceMetricRows.length" class="mt-2 text-sm text-slate-300">
                    No metric-level source details are available yet.
                  </p>
                </div>
              </template>
            </div>

            <div class="mt-4 rounded-lg border border-emerald-300/20 bg-emerald-500/10 p-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
	                <div>
	                  <p class="text-[10px] uppercase tracking-widest text-emerald-200/80">Benchmark Trust Summary</p>
	                  <h4 class="mt-1 text-lg font-semibold text-white">Benchmark Trust by Metric</h4>
	                  <p class="mt-1 text-xs text-slate-300">
	                    These badges show which metrics are ready for FMTRX population learning and which still use research standards.
	                  </p>
	                </div>
                <span
                  class="rounded-full border px-3 py-1 text-xs uppercase tracking-wider"
                  :class="benchmarkTrustSummary.available ? 'border-emerald-300/30 bg-emerald-500/15 text-emerald-100' : 'border-white/10 bg-white/5 text-slate-300'"
                >
	                  {{ benchmarkTrustSummary.available ? `${fmtCount(benchmarkTrustSummary.total, '0')} Metrics` : 'Safe Source' }}
                </span>
              </div>

              <p v-if="!benchmarkTrustSummary.available" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-300">
	                FMTRX is using the safest available benchmark source. No metric trust details are available yet.
              </p>

              <template v-else>
                <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
                  <div
                    v-for="status in ['research_only', 'composite_enabled', 'population_enabled', 'needs_review', 'disabled', 'auto']"
                    :key="`trust-summary-${status}`"
                    class="rounded-md border border-white/10 bg-slate-950/35 p-2"
                  >
                    <p class="text-[10px] uppercase tracking-wider text-white/35">{{ trustStatusDefinitions[status].label }}</p>
                    <p class="mt-1 text-xl font-black text-white">{{ fmtCount(benchmarkTrustSummary.counts[status], '0') }}</p>
                  </div>
                </div>
                <p class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-emerald-100">
	                  FMTRX needs at least 30 trusted values before population learning can influence a metric. Research standards stay active until then.
                </p>
                <details class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3">
                  <summary class="cursor-pointer text-[10px] font-black uppercase tracking-widest text-emerald-200/80">
	                    Benchmark Data QA
                  </summary>
                  <div v-if="benchmarkPayloadQa.metricCount" class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-300 md:grid-cols-4">
                    <p>Metric count: <span class="font-black text-white">{{ fmtCount(benchmarkPayloadQa.metricCount, '0') }}</span></p>
	                    <p>With trust status: <span class="font-black text-white">{{ fmtCount(benchmarkPayloadQa.withPolicy, '0') }}</span></p>
	                    <p>Missing trust status: <span class="font-black text-white">{{ fmtCount(benchmarkPayloadQa.missingPolicy, '0') }}</span></p>
	                    <p>With source details: <span class="font-black text-white">{{ fmtCount(benchmarkPayloadQa.withSourceMix, '0') }}</span></p>
	                    <p>Missing source details: <span class="font-black text-white">{{ fmtCount(benchmarkPayloadQa.missingSourceMix, '0') }}</span></p>
	                    <p>With comparison details: <span class="font-black text-white">{{ fmtCount(benchmarkPayloadQa.withBucketDetails, '0') }}</span></p>
	                    <p>Missing comparison details: <span class="font-black text-white">{{ fmtCount(benchmarkPayloadQa.missingBucketDetails, '0') }}</span></p>
                  </div>
                  <p v-else class="mt-3 text-xs text-slate-300">
                    No metric trust details are available yet.
                  </p>
                </details>
              </template>
            </div>

            <div class="mt-4 rounded-lg border border-indigo-300/20 bg-indigo-500/10 p-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
	                  <p class="text-[10px] uppercase tracking-widest text-indigo-200/80">Benchmark Comparison Quality</p>
	                  <h4 class="mt-1 text-lg font-semibold text-white">FMTRX Peer Group Trust</h4>
	                  <p class="mt-1 text-xs text-slate-300">
	                    This shows whether FMTRX found a strong peer group or had to use a broader comparison group.
	                  </p>
                </div>
                <span
                  class="rounded-full border px-3 py-1 text-xs uppercase tracking-wider"
                  :class="populationBucketQualitySummary.available ? 'border-indigo-300/30 bg-indigo-500/15 text-indigo-100' : 'border-white/10 bg-white/5 text-slate-300'"
                >
	                  {{ populationBucketQualitySummary.available ? 'Comparison Active' : 'Needs Data' }}
                </span>
              </div>

              <p v-if="!populationBucketQualitySummary.available" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-300">
	                Comparison group details are not available yet.
              </p>

              <template v-else>
                <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-7">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
	                    <p class="text-[10px] uppercase tracking-wider text-white/35">Closest Peer</p>
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
	                    <p class="text-[10px] uppercase tracking-wider text-white/35">Broad</p>
                    <p class="text-xl font-black text-white">{{ fmtCount(populationBucketQualitySummary.counts.global_clean, '0') }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
	                    <p class="text-[10px] uppercase tracking-wider text-white/35">Needs Data</p>
                    <p class="text-xl font-black text-white">{{ fmtCount(populationBucketQualitySummary.counts.none, '0') }}</p>
                  </div>
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-2">
	                    <p class="text-[10px] uppercase tracking-wider text-white/35">Avg Group</p>
                    <p class="text-xl font-black text-white">{{ fmtCount(populationBucketQualitySummary.averageBucketCount, '0') }}</p>
                  </div>
                </div>

                <div class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-200">
                  <p><span class="font-black text-white">Confidence:</span> {{ populationBucketQualitySummary.confidenceSummary }}</p>
	                  <p class="mt-1 text-indigo-100"><span class="font-black text-white">Rule:</span> FMTRX needs at least 30 trusted values before population learning can influence a metric.</p>
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
                        class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                        :class="metricTrustBadge(metric).badge"
                        :title="metricTrustTooltip(metric)"
                      >
                        {{ metricTrustBadge(metric).label }}
                      </span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-300 md:grid-cols-4">
                      <p>Group: <span class="font-semibold text-white">{{ fmtCount(metric.bucketCount, '0') }}</span></p>
                      <p>Trust: <span class="font-semibold text-white">{{ confidenceLabel(metric.bucketConfidence) }}</span></p>
                      <p>Research: <span class="font-semibold text-white">{{ metricResearchPercentile(metric) }}</span></p>
                      <p>FMTRX Data: <span class="font-semibold text-white">{{ metricPopulationPercentile(metric) }}</span></p>
                      <p>Source: <span class="font-semibold text-white">{{ metricSource(metric) }}</span></p>
                      <p>Final: <span class="font-semibold text-white">{{ fmtScore(metric.finalScore) }}</span></p>
                    </div>

                    <p class="mt-2 text-xs text-slate-300">{{ metric.bucketExplanation }}</p>
                    <p v-if="metric.bucketLevel === 'global_clean'" class="mt-2 rounded border border-amber-300/20 bg-amber-500/10 px-2 py-1 text-xs text-amber-100">
                      This is a broad comparison group. Peer-specific confidence improves as more players complete roster profiles and baseline testing.
                    </p>
                    <p v-else-if="metric.bucketDisplayLevel === 'exact_peer' && metric.bucketUsable" class="mt-2 rounded border border-emerald-300/20 bg-emerald-500/10 px-2 py-1 text-xs text-emerald-100">
                      Strong peer match.
                    </p>
                    <p v-else-if="!metric.bucketUsable" class="mt-2 rounded border border-white/10 bg-white/5 px-2 py-1 text-xs text-slate-300">
                      FMTRX needs at least 30 trusted values before population learning can influence this metric. Research benchmark remains active.
                    </p>

                    <div v-if="metric.attemptedBuckets.length" class="mt-3 rounded border border-white/10 bg-white/5 p-2">
                      <p class="text-[10px] uppercase tracking-widest text-white/40">Comparison Groups Checked</p>
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
                  No metric-level comparison group details are available yet.
                </p>
              </template>
            </div>

            <div class="mt-4 rounded-lg border border-red-300/20 bg-red-500/10 p-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="text-[10px] uppercase tracking-widest text-red-200/80">Benchmark Data Quality</p>
                  <h4 class="mt-1 text-lg font-semibold text-white">Roster + Baseline Readiness</h4>
                  <p class="mt-1 text-xs text-slate-300">
                    Better roster profiles and baseline testing improve benchmark confidence and peer comparisons.
                  </p>
                </div>
                <span
                  class="rounded-full border px-3 py-1 text-xs uppercase tracking-wider"
                  :class="dataCollectionPriority ? 'border-red-300/30 bg-red-500/15 text-red-100' : 'border-white/10 bg-white/5 text-slate-300'"
                >
                  Priority {{ humanizeKey(benchmarkDataQuality.priority || 'not_available', 'Not Available') }}
                </span>
              </div>

              <p v-if="!dataCollectionPriority" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-300">
                Benchmark data quality will appear after roster profiles and baseline testing are available.
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
                      players need roster profile updates.
                    </p>
                    <p class="mt-2 text-red-100">
                      <span class="font-black text-white">Next action:</span> {{ benchmarkDataQuality.nextAction }}
                    </p>
                    <p v-if="practicePlanHasDataBlock" class="mt-2 rounded border border-red-300/20 bg-red-500/10 px-2 py-1 text-xs font-semibold text-red-100">
                      Baseline collection added to today's plan.
                    </p>
                  </div>

                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-white/40">Baselines Needed</p>
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
                    <p class="text-[10px] uppercase tracking-widest text-red-200/80">Critical Baselines Needed</p>
                    <div class="mt-2 space-y-1 text-xs">
                      <p v-for="row in allCriticalMissingRows.slice(0, 6)" :key="`quality-critical-${row.metric_key}`" class="rounded border border-red-300/15 bg-red-500/10 px-2 py-1 text-red-100">
                        {{ missingRowTitle(row) }} · {{ missingRowCount(row) }}
                      </p>
                      <p v-if="!allCriticalMissingRows.length" class="text-slate-300">No critical benchmark baselines are currently missing.</p>
                    </div>
                  </div>

                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-amber-200/80">Supporting Baselines Needed</p>
                    <div class="mt-2 space-y-1 text-xs">
                      <p v-for="row in allSupportingMissingRows.slice(0, 6)" :key="`quality-supporting-${row.metric_key}`" class="rounded border border-amber-300/15 bg-amber-500/10 px-2 py-1 text-amber-100">
                        {{ missingRowTitle(row) }} · {{ missingRowCount(row) }}
                      </p>
                      <p v-if="!allSupportingMissingRows.length" class="text-slate-300">No supporting benchmark baselines are currently missing.</p>
                    </div>
                  </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                  <div class="rounded-md border border-white/10 bg-slate-950/35 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-white/40">Roster Profile Needs</p>
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
                    <p v-else class="mt-2 text-xs text-slate-300">No roster profile updates are currently attached.</p>
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
                      Research benchmarks are active while FMTRX population learning improves as more players complete roster profiles and baseline testing.
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
                  <p class="mt-1 text-xs text-slate-300">
                    FMTRX turns missing baseline data into a simple collection plan for the coach.
                  </p>
                </div>
                <span
                  class="rounded-full border px-3 py-1 text-xs uppercase tracking-wider"
                  :class="benchmarkCollectionPlan ? 'border-emerald-300/30 bg-emerald-500/15 text-emerald-100' : 'border-white/10 bg-white/5 text-slate-300'"
                >
                  Priority {{ humanizeKey(benchmarkCollectionPlan?.priority_level || 'not_available', 'Not Available') }}
                </span>
              </div>

              <p v-if="!benchmarkCollectionPlan" class="mt-3 rounded-md border border-white/10 bg-slate-950/35 p-3 text-sm text-slate-300">
                Benchmark collection plan will appear after baseline collection needs are identified.
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
                  <p>{{ benchmarkCollectionPlan.summary || 'FMTRX will turn missing baseline data into collection tasks as data becomes available.' }}</p>
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

	                  <div class="mt-3 rounded-md border border-amber-300/20 bg-amber-500/10 p-3">
	                    <div class="flex flex-wrap items-start justify-between gap-3">
	                      <div>
	                        <p class="text-[10px] uppercase tracking-widest text-amber-100/80">Benchmark Task Review Queue</p>
	                        <h5 class="mt-1 text-base font-semibold text-white">Coach Approval</h5>
	                      </div>
	                      <span class="rounded-full border border-amber-300/30 bg-amber-500/15 px-3 py-1 text-xs uppercase tracking-wider text-amber-100">
	                        {{ fmtCount(pendingBenchmarkReviewCount, '0') }} Pending
	                      </span>
	                    </div>

	                    <p v-if="benchmarkReviewActionMessage" class="mt-3 rounded border border-emerald-300/20 bg-emerald-500/10 px-2 py-2 text-xs text-emerald-100">
	                      {{ benchmarkReviewActionMessage }}
	                    </p>
	                    <p v-if="benchmarkReviewActionError" class="mt-3 rounded border border-red-300/20 bg-red-500/10 px-2 py-2 text-xs text-red-100">
	                      {{ benchmarkReviewActionError }}
	                    </p>

	                    <p v-if="!benchmarkTaskReviewSummary" class="mt-3 rounded border border-white/10 bg-slate-950/35 px-2 py-2 text-xs text-slate-300">
	                      Benchmark task reviews are not available yet.
	                    </p>

	                    <div v-else-if="pendingBenchmarkReviewTasks.length" class="mt-3 grid grid-cols-1 gap-2 xl:grid-cols-2">
	                      <div
	                        v-for="task in pendingBenchmarkReviewTasks"
	                        :key="task.id"
	                        class="rounded border border-white/10 bg-slate-950/40 p-3 text-xs text-slate-300"
	                      >
	                        <div class="flex flex-wrap items-start justify-between gap-2">
	                          <div>
	                            <p class="font-black text-white">{{ task.assigned_to_player_name || 'Player' }}</p>
	                            <p class="mt-1 text-slate-100">{{ task.title || taskTypeLabel(task.task_type) }}</p>
	                          </div>
	                          <span class="rounded-full border px-2 py-0.5 text-[10px] uppercase tracking-wider" :class="reviewStateClass(task.review_status)">
	                            {{ reviewStateLabel(task.review_status) }}
	                          </span>
	                        </div>
	                        <p class="mt-2 text-[10px] uppercase tracking-wider text-white/45">
	                          {{ taskTypeLabel(task.task_type) }} · Submitted {{ task.submitted_at ? formatDate(task.submitted_at) : '—' }}
	                        </p>
	                        <div v-if="submittedValueRows(task).length" class="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2">
	                          <p
	                            v-for="value in submittedValueRows(task)"
	                            :key="value.key"
	                            class="rounded border border-white/10 bg-white/5 px-2 py-1"
	                          >
	                            <span class="font-black text-white">{{ value.label }}</span>
	                            <span class="text-slate-200">: {{ value.value ?? '—' }}</span>
	                          </p>
	                        </div>
	                        <p v-else class="mt-2 rounded border border-white/10 bg-white/5 px-2 py-1 text-slate-400">
	                          No submitted values were attached to this task.
	                        </p>
	                        <div class="mt-3 flex flex-wrap gap-2">
	                          <button
	                            type="button"
	                            class="rounded border border-emerald-300/30 bg-emerald-500/15 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-emerald-100 disabled:opacity-50"
	                            :disabled="!!benchmarkReviewActionLoading"
	                            @click="reviewBenchmarkTask(task, 'approve')"
	                          >
	                            {{ benchmarkReviewActionLoading === `approve:${task.id}` ? 'Approving...' : 'Approve' }}
	                          </button>
	                          <button
	                            type="button"
	                            class="rounded border border-sky-300/30 bg-sky-500/15 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-sky-100 disabled:opacity-50"
	                            :disabled="!!benchmarkReviewActionLoading"
	                            @click="reviewBenchmarkTask(task, 'request-correction')"
	                          >
	                            {{ benchmarkReviewActionLoading === `request-correction:${task.id}` ? 'Sending...' : 'Correction' }}
	                          </button>
	                          <button
	                            type="button"
	                            class="rounded border border-red-300/30 bg-red-500/15 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-red-100 disabled:opacity-50"
	                            :disabled="!!benchmarkReviewActionLoading"
	                            @click="reviewBenchmarkTask(task, 'reject')"
	                          >
	                            {{ benchmarkReviewActionLoading === `reject:${task.id}` ? 'Rejecting...' : 'Reject' }}
	                          </button>
	                        </div>
	                      </div>
	                    </div>

	                    <p v-else class="mt-3 rounded border border-white/10 bg-slate-950/35 px-2 py-2 text-xs text-slate-300">
	                      No benchmark tasks are waiting for coach review.
	                    </p>
	                  </div>

	                  <div class="mt-3 rounded-md border border-emerald-300/20 bg-emerald-500/10 p-3">
	                    <div class="flex flex-wrap items-start justify-between gap-3">
	                      <div>
	                        <p class="text-[10px] uppercase tracking-widest text-emerald-100/80">Trusted Data Promotion</p>
	                        <h5 class="mt-1 text-base font-semibold text-white">Approved Data Routing</h5>
	                      </div>
	                      <div class="flex flex-wrap gap-2 text-[10px] uppercase tracking-wider">
	                        <span class="rounded-full border border-sky-300/30 bg-sky-500/15 px-3 py-1 text-sky-100">
	                          {{ fmtCount(benchmarkTaskPromotionStatus?.awaiting_promotion_count, '0') }} Awaiting
	                        </span>
	                        <span class="rounded-full border border-emerald-300/30 bg-emerald-500/15 px-3 py-1 text-emerald-100">
	                          {{ fmtCount(benchmarkTaskPromotionStatus?.promoted_count, '0') }} Promoted
	                        </span>
	                      </div>
	                    </div>

	                    <p v-if="benchmarkPromotionActionMessage" class="mt-3 rounded border border-emerald-300/20 bg-emerald-500/10 px-2 py-2 text-xs text-emerald-100">
	                      {{ benchmarkPromotionActionMessage }}
	                    </p>
	                    <p v-if="benchmarkPromotionActionError" class="mt-3 rounded border border-red-300/20 bg-red-500/10 px-2 py-2 text-xs text-red-100">
	                      {{ benchmarkPromotionActionError }}
	                    </p>

	                    <p v-if="!benchmarkTaskPromotionStatus" class="mt-3 rounded border border-white/10 bg-slate-950/35 px-2 py-2 text-xs text-slate-300">
	                      Trusted data promotion status is not available yet.
	                    </p>

	                    <template v-else>
	                      <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4">
	                        <div class="rounded border border-white/10 bg-slate-950/35 p-2">
	                          <p class="text-[10px] uppercase tracking-wider text-white/35">Approved</p>
	                          <p class="text-lg font-black text-white">{{ fmtCount(benchmarkTaskPromotionStatus.approved_count, '0') }}</p>
	                        </div>
	                        <div class="rounded border border-white/10 bg-slate-950/35 p-2">
	                          <p class="text-[10px] uppercase tracking-wider text-white/35">Awaiting</p>
	                          <p class="text-lg font-black text-sky-100">{{ fmtCount(benchmarkTaskPromotionStatus.awaiting_promotion_count, '0') }}</p>
	                        </div>
	                        <div class="rounded border border-white/10 bg-slate-950/35 p-2">
	                          <p class="text-[10px] uppercase tracking-wider text-white/35">Promoted</p>
	                          <p class="text-lg font-black text-emerald-100">{{ fmtCount(benchmarkTaskPromotionStatus.promoted_count, '0') }}</p>
	                        </div>
	                        <div class="rounded border border-white/10 bg-slate-950/35 p-2">
	                          <p class="text-[10px] uppercase tracking-wider text-white/35">Manual Review</p>
	                          <p class="text-lg font-black text-amber-100">{{ fmtCount(benchmarkTaskPromotionStatus.manual_review_count, '0') }}</p>
	                        </div>
	                      </div>

	                      <div class="mt-3 flex flex-wrap gap-2">
	                        <button
	                          type="button"
	                          class="rounded border border-emerald-300/30 bg-emerald-500/15 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
	                          :disabled="!!benchmarkPromotionActionLoading || !approvedAwaitingPromotionTasks.length"
	                          @click="promoteAllApprovedBenchmarkTasks"
	                        >
	                          {{ benchmarkPromotionActionLoading === 'promote-all' ? 'Promoting...' : 'Promote All Approved' }}
	                        </button>
	                      </div>

	                      <div v-if="approvedAwaitingPromotionTasks.length" class="mt-3 grid grid-cols-1 gap-2 xl:grid-cols-2">
	                        <div
	                          v-for="task in approvedAwaitingPromotionTasks"
	                          :key="task.id"
	                          class="rounded border border-white/10 bg-slate-950/40 p-3 text-xs text-slate-300"
	                        >
	                          <div class="flex flex-wrap items-start justify-between gap-2">
	                            <div>
	                              <p class="font-black text-white">{{ promotionTaskTitle(task) }}</p>
	                              <p class="mt-1 text-slate-100">{{ taskTypeLabel(task.task_type) }}</p>
	                            </div>
	                            <span class="rounded-full border px-2 py-0.5 text-[10px] uppercase tracking-wider" :class="promotionStatusClass(task.promotion_status)">
	                              {{ promotionStatusLabel(task.promotion_status) }}
	                            </span>
	                          </div>
	                          <p class="mt-2 text-[10px] uppercase tracking-wider text-white/45">
	                            Reviewed {{ task.reviewed_at ? formatDate(task.reviewed_at) : '—' }} · {{ promotionTargetLabel(task) }}
	                          </p>
	                          <div class="mt-3 flex flex-wrap gap-2">
	                            <button
	                              type="button"
	                              class="rounded border border-sky-300/30 bg-sky-500/15 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-sky-100 disabled:opacity-50"
	                              :disabled="!!benchmarkPromotionActionLoading"
	                              @click="previewBenchmarkPromotion(task)"
	                            >
	                              {{ benchmarkPromotionActionLoading === `preview:${task.id}` ? 'Previewing...' : 'Preview Promotion' }}
	                            </button>
	                            <button
	                              type="button"
	                              class="rounded border border-emerald-300/30 bg-emerald-500/15 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-emerald-100 disabled:opacity-50"
	                              :disabled="!!benchmarkPromotionActionLoading"
	                              @click="promoteBenchmarkTask(task)"
	                            >
	                              {{ benchmarkPromotionActionLoading === `promote:${task.id}` ? 'Promoting...' : 'Promote Task' }}
	                            </button>
	                          </div>
	                        </div>
	                      </div>

	                      <p v-else class="mt-3 rounded border border-white/10 bg-slate-950/35 px-2 py-2 text-xs text-slate-300">
	                        No approved benchmark tasks awaiting promotion.
	                      </p>

	                      <div
	                        v-if="selectedPromotionPreview"
	                        class="mt-3 rounded border border-white/10 bg-slate-950/45 p-3 text-xs text-slate-300"
	                      >
	                        <div class="flex flex-wrap items-start justify-between gap-2">
	                          <div>
	                            <p class="text-[10px] uppercase tracking-widest text-emerald-100/80">Promotion Preview / Result</p>
	                            <p class="mt-1 font-black text-white">
	                              {{ taskTypeLabel(selectedPromotionPreview.task_type) }} · {{ promotionModeLabel(selectedPromotionPreview.promotion_mode) }}
	                            </p>
	                          </div>
	                          <span class="rounded-full border px-2 py-0.5 text-[10px] uppercase tracking-wider" :class="promotionStatusClass(selectedPromotionPreview.promotion_status)">
	                            {{ promotionStatusLabel(selectedPromotionPreview.promotion_status) }}
	                          </span>
	                        </div>
	                        <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3">
	                          <p class="rounded border border-white/10 bg-white/5 px-2 py-1">
	                            <span class="block text-[10px] uppercase tracking-wider text-white/35">Target</span>
	                            <span class="font-black text-white">{{ selectedPromotionPreview.target_table || 'Trusted Payload' }}</span>
	                          </p>
	                          <p class="rounded border border-white/10 bg-white/5 px-2 py-1">
	                            <span class="block text-[10px] uppercase tracking-wider text-white/35">Fields</span>
	                            <span class="font-black text-white">{{ fmtCount(asArray(selectedPromotionPreview.promoted_fields).length, '0') }}</span>
	                          </p>
	                          <p class="rounded border border-white/10 bg-white/5 px-2 py-1">
	                            <span class="block text-[10px] uppercase tracking-wider text-white/35">Refresh</span>
	                            <span class="font-black text-white">{{ humanizeKey(selectedPromotionPreview.refresh?.refresh_status, '—') }}</span>
	                          </p>
	                        </div>
	                        <div v-if="asArray(selectedPromotionPreview.promoted_fields).length" class="mt-2 flex flex-wrap gap-1">
	                          <span
	                            v-for="field in asArray(selectedPromotionPreview.promoted_fields).slice(0, 8)"
	                            :key="`${field.field}-${field.target}`"
	                            class="rounded-full border border-emerald-300/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] text-emerald-100"
	                          >
	                            {{ humanizeKey(field.field) }}
	                          </span>
	                        </div>
	                        <p v-if="asArray(selectedPromotionPreview.warnings).length" class="mt-2 rounded border border-amber-300/20 bg-amber-500/10 px-2 py-2 text-xs text-amber-100">
	                          {{ asArray(selectedPromotionPreview.warnings).join(' ') }}
	                        </p>
	                      </div>

	                      <div v-if="promotedBenchmarkTasks.length || manualPromotionReviewTasks.length" class="mt-3 grid grid-cols-1 gap-2 xl:grid-cols-2">
	                        <div class="rounded border border-white/10 bg-slate-950/35 p-3">
	                          <p class="text-[10px] uppercase tracking-widest text-emerald-100/80">Recently Promoted</p>
	                          <div class="mt-2 space-y-1">
	                            <p
	                              v-for="task in promotedBenchmarkTasks"
	                              :key="`promoted-${task.id}`"
	                              class="rounded border border-white/10 bg-white/5 px-2 py-1 text-xs text-slate-300"
	                            >
	                              <span class="font-black text-white">{{ promotionTaskTitle(task) }}</span>
	                              · {{ promotionTargetLabel(task) }}
	                            </p>
	                            <p v-if="!promotedBenchmarkTasks.length" class="text-xs text-slate-400">No promoted tasks yet.</p>
	                          </div>
	                        </div>
	                        <div class="rounded border border-white/10 bg-slate-950/35 p-3">
	                          <p class="text-[10px] uppercase tracking-widest text-amber-100/80">Skipped / Manual Review</p>
	                          <div class="mt-2 space-y-1">
	                            <p
	                              v-for="task in manualPromotionReviewTasks"
	                              :key="`manual-${task.id}`"
	                              class="rounded border border-white/10 bg-white/5 px-2 py-1 text-xs text-slate-300"
	                            >
	                              <span class="font-black text-white">{{ promotionTaskTitle(task) }}</span>
	                              · {{ promotionTargetLabel(task) }}
	                            </p>
	                            <p v-if="!manualPromotionReviewTasks.length" class="text-xs text-slate-400">No manual review promotion issues.</p>
	                          </div>
	                        </div>
	                      </div>
	                    </template>
	                  </div>

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
                      <div
                        v-if="savedBenchmarkTaskRows.length"
                        class="mt-3 grid grid-cols-1 gap-2 xl:grid-cols-3"
                      >
                        <div class="rounded border border-white/10 bg-white/5 p-2">
                          <p class="text-[10px] uppercase tracking-widest text-white/45">By Status</p>
                          <div class="mt-2 space-y-1">
                            <p
                              v-for="row in savedBenchmarkTaskStatusSummaryRows"
                              :key="row.status"
                              class="flex items-center justify-between gap-2 rounded border border-white/10 bg-slate-950/35 px-2 py-1 text-xs text-slate-300"
                            >
                              <span>{{ humanizeKey(row.status) }}</span>
                              <span class="font-black text-white">{{ fmtCount(row.count, '0') }}</span>
                            </p>
                          </div>
                        </div>

                        <div class="rounded border border-white/10 bg-white/5 p-2">
                          <p class="text-[10px] uppercase tracking-widest text-white/45">By Player</p>
                          <div class="mt-2 space-y-1">
                            <p
                              v-for="row in savedBenchmarkTaskPlayerSummaryRows"
                              :key="row.player_id"
                              class="rounded border border-white/10 bg-slate-950/35 px-2 py-1 text-xs text-slate-300"
                            >
                              <span class="flex items-center justify-between gap-2">
                                <span class="font-black text-white">{{ row.player_name }}</span>
                                <span>{{ fmtCount(row.task_count, '0') }} tasks</span>
                              </span>
                              <span class="mt-0.5 block text-[10px] uppercase tracking-wider text-sky-100/80">
                                Active {{ fmtCount(row.active_count, '0') }}
                                · Done {{ fmtCount(row.completed_count, '0') }}
                                · Types {{ fmtCount(row.task_types.length, '0') }}
                              </span>
                            </p>
                          </div>
                        </div>

                        <div class="rounded border border-white/10 bg-white/5 p-2">
                          <p class="text-[10px] uppercase tracking-widest text-white/45">By Task Type</p>
                          <div class="mt-2 space-y-1">
                            <p
                              v-for="row in savedBenchmarkTaskTypeSummaryRows"
                              :key="row.task_type"
                              class="rounded border border-white/10 bg-slate-950/35 px-2 py-1 text-xs text-slate-300"
                            >
                              <span class="flex items-center justify-between gap-2">
                                <span class="font-black text-white">{{ taskTypeLabel(row.task_type) }}</span>
                                <span>{{ fmtCount(row.task_count, '0') }}</span>
                              </span>
                              <span class="mt-0.5 block text-[10px] uppercase tracking-wider text-sky-100/80">
                                Active {{ fmtCount(row.active_count, '0') }}
                                · Done {{ fmtCount(row.completed_count, '0') }}
                                · Dismissed {{ fmtCount(row.dismissed_count, '0') }}
                              </span>
                            </p>
                          </div>
                        </div>
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
	                        <span
	                          v-if="task.review_status"
	                          class="mt-2 inline-flex rounded-full border px-2 py-0.5 text-[10px] uppercase tracking-wider"
	                          :class="reviewStateClass(task.review_status)"
	                        >
	                          {{ reviewStateLabel(task.review_status) }}
	                        </span>
	                        <p class="mt-1">
	                          {{ taskTypeLabel(task.task_type) }}
                          · {{ humanizeKey(task.priority) }}
                          · {{ humanizeKey(task.completion_mode, 'Manual Confirm') }}
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
                    <div class="flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <p class="text-sm font-semibold text-white">{{ metric.display_name || humanizeKey(metric.metric_key) }}</p>
                        <span
                          class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                          :class="metricTrustBadge(metric).badge"
                          :title="metricTrustTooltip(metric)"
                        >
                          {{ metricTrustBadge(metric).label }}
                        </span>
                      </div>
                      <span class="text-sm font-black" :class="scoreTone(metric.score_0_100)">{{ fmtScore(metric.score_0_100) }}</span>
                    </div>
                    <p class="text-xs text-slate-300">{{ categoryLabel(metric.category) }} · {{ metricPercentile(metric) }} · {{ humanizeKey(metric.label) }}</p>
                    <p class="mt-1 text-[10px] text-cyan-100">{{ metricSource(metric) }} · Group {{ metricPopulationBucketCount(metric) }} · {{ metricPopulationConfidence(metric) }}</p>
                    <p class="mt-1 text-[10px] text-white/45">Research {{ metricResearchPercentile(metric) }} · FMTRX Data {{ metricPopulationPercentile(metric) }} · Used {{ metricPopulationUsable(metric) }}</p>
                    <p class="mt-1 text-[10px] text-white/45">Good gap {{ metricGap(metric, 'gap_to_good') }} · Elite gap {{ metricGap(metric, 'gap_to_elite') }}</p>
                    <p class="mt-1 text-[10px] text-slate-400">{{ metricSourceStatusText(metric) }}</p>
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
                <h4 class="text-sm font-semibold text-white">Baseline Priority</h4>
                <div class="mt-2 space-y-3 text-xs">
                  <div>
                    <p class="font-black uppercase tracking-wider text-red-200">Critical</p>
                    <div class="mt-1 space-y-1">
                      <p v-for="row in criticalMissingRows" :key="`critical-${missingRowTitle(row)}`" class="rounded border border-red-300/15 bg-red-500/10 px-2 py-1 text-red-100">
                        {{ missingRowTitle(row) }} · {{ missingRowCount(row) }}
                      </p>
                      <p v-if="!criticalMissingRows.length" class="text-slate-300">No critical benchmark baselines are currently missing.</p>
                    </div>
                  </div>

                  <div>
                    <p class="font-black uppercase tracking-wider text-amber-200">Supporting</p>
                    <div class="mt-1 space-y-1">
                      <p v-for="row in supportingMissingRows" :key="`supporting-${missingRowTitle(row)}`" class="rounded border border-amber-300/15 bg-amber-500/10 px-2 py-1 text-amber-100">
                        {{ missingRowTitle(row) }} · {{ missingRowCount(row) }}
                      </p>
                      <p v-if="!supportingMissingRows.length" class="text-slate-300">No supporting benchmark baselines are currently missing.</p>
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
                    <p class="font-black uppercase tracking-wider text-amber-200">Benchmark Baselines</p>
                    <p v-for="row in benchmarkMissingMetrics.slice(0, 5)" :key="row.metric_key" class="mt-1 rounded border border-amber-300/15 bg-amber-500/10 px-2 py-1 text-amber-100">
                      {{ missingRowTitle(row) }} · {{ missingRowCount(row) }}
                    </p>
                  </div>

                  <p v-if="!hasMissingDataPriorityRows" class="rounded border border-white/10 bg-slate-950/35 px-2 py-2 text-slate-300">
                    No benchmark baselines are currently flagged.
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
                  <span
                    class="mt-2 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                    :class="selectedMetricDetail.trustBadge.badge"
                    :title="selectedMetricDetail.trustTooltip"
                  >
                    {{ selectedMetricDetail.trustBadge.label }}
                  </span>
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
                    <p class="text-[10px] uppercase tracking-widest text-white/35">Comparison Group</p>
                    <p class="mt-1 text-lg font-black text-white">{{ selectedMetricDetail.bucketLabel }}</p>
                    <p class="mt-1 text-xs text-slate-300">{{ selectedMetricDetail.bucketCount }} players</p>
                  </div>
                </div>

                <div class="mt-3 rounded-lg border border-white/10 bg-white/5 p-3">
                  <p class="text-[10px] uppercase tracking-widest text-white/40">Coach Explanation</p>
                  <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                    <div
                      v-for="line in selectedMetricDetail.coachExplanations"
                      :key="line.label"
                      class="rounded-md border border-white/10 bg-slate-950/35 p-2"
                    >
                      <p class="text-[10px] uppercase tracking-wider text-white/35">{{ line.label }}</p>
                      <p class="mt-1 text-xs text-slate-200">{{ line.value }}</p>
                    </div>
                  </div>
                </div>

                <div v-if="selectedMetricCoachAction" class="mt-3 rounded-lg border border-red-300/20 bg-red-500/10 p-3">
                  <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <p class="text-[10px] uppercase tracking-widest text-red-200/80">Coach Action</p>
                      <h4 class="mt-1 text-base font-black text-white">{{ selectedMetricCoachAction.title }}</h4>
                    </div>
                    <span
                      class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                      :class="coachActionPriorityClass(selectedMetricCoachAction.priority)"
                    >
                      {{ formatCoachPriority(selectedMetricCoachAction.priority) }}
                    </span>
                  </div>
                  <p class="mt-2 text-xs text-slate-300">
                    <span class="font-black text-white">Why:</span> {{ selectedMetricCoachAction.why }}
                  </p>
                  <p class="mt-2 text-xs text-red-100">
                    <span class="font-black text-white">Action:</span> {{ selectedMetricCoachAction.action }}
                  </p>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-2">
                  <div class="rounded-lg border border-cyan-300/20 bg-cyan-500/10 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-cyan-200/80">Benchmark Source</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-slate-200">
                      <p>Research Share: <span class="font-black text-white">{{ selectedMetricDetail.researchWeight }}</span></p>
                      <p>FMTRX Data Share: <span class="font-black text-white">{{ selectedMetricDetail.populationWeight }}</span></p>
                      <p>Research Percentile: <span class="font-black text-white">{{ selectedMetricDetail.researchPercentile }}</span></p>
                      <p>FMTRX Percentile: <span class="font-black text-white">{{ selectedMetricDetail.populationPercentile }}</span></p>
                      <p>Comparison Group Count: <span class="font-black text-white">{{ selectedMetricDetail.populationBucketCount }}</span></p>
                      <p>FMTRX Trust: <span class="font-black text-white">{{ selectedMetricDetail.populationConfidence }}</span></p>
                      <p>FMTRX Data Used: <span class="font-black text-white">{{ selectedMetricDetail.populationUsable }}</span></p>
                    </div>
                    <p class="mt-3 rounded border border-white/10 bg-slate-950/35 p-2 text-xs text-cyan-100">
                      {{ selectedMetricSourceExplanation }}
                    </p>
                    <div class="mt-3 rounded border border-white/10 bg-slate-950/35 p-2">
                      <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[10px] uppercase tracking-widest text-cyan-200/80">Trust Status</p>
                        <span
                          class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                          :class="selectedMetricDetail.trustBadge.badge"
                          :title="selectedMetricDetail.trustTooltip"
                        >
                          {{ selectedMetricDetail.trustBadge.label }}
                        </span>
                      </div>
                      <div class="mt-2 grid grid-cols-1 gap-1 text-xs text-slate-300 md:grid-cols-2">
                        <p
                          v-for="line in selectedMetricDetail.trustLines"
                          :key="`trust-${line.label}`"
                        >
                          <span class="font-black text-white">{{ line.label }}:</span> {{ line.value }}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div class="rounded-lg border border-indigo-300/20 bg-indigo-500/10 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-indigo-200/80">Comparison Group Quality</p>
                    <p class="mt-2 text-sm text-slate-200">
                      <span class="font-black text-white">Selected group:</span> {{ selectedMetricDetail.bucketLabel }}
                    </p>
                    <p class="mt-2 rounded border border-white/10 bg-slate-950/35 p-2 text-xs text-indigo-100">
                      {{ selectedMetricDetail.bucketExplanation }}
                    </p>
                    <div class="mt-3">
                      <p class="text-[10px] uppercase tracking-widest text-white/40">Comparison Groups Checked</p>
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
                      <p v-else class="mt-2 text-xs text-slate-300">No comparison group details are available yet.</p>
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
