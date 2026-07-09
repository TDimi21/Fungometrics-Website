<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team'
import { useUserStore } from '@/store/user'

import PlayerSnapshotCard from '../components/PlayerSnapshotCard.vue'
import DevelopmentScoreCard from '../components/DevelopmentScoreCard.vue'
import WhereAreWeCard from '../components/WhereAreWeCard.vue'
import WhereAreWeGoingCard from '../components/WhereAreWeGoingCard.vue'
import HowWeGetThereCard from '../components/HowWeGetThereCard.vue'
import PercentileRankingsTable from '../components/PercentileRankingsTable.vue'
import CorrelationInsightsCard from '../components/CorrelationInsightsCard.vue'
import CoachActionPlanCard from '../components/CoachActionPlanCard.vue'
import RecoverySleepCard from '../components/RecoverySleepCard.vue'
import StrengthMetricsCard from '../components/StrengthMetricsCard.vue'
import MobilityAssessmentCard from '../components/MobilityAssessmentCard.vue'

import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.png'
import { buildPlayerDevelopmentModel } from '../lib/playerDevelopmentScore'
import { getAgeGroup, getMetricPercentile, getPercentileLabel } from '../lib/percentileEngine'
import { buildCorrelationInsights } from '../lib/correlationEngine'
import { buildRecommendations } from '../lib/recommendationEngine'

const route = useRoute()
const { axiosGet } = useAxiosAuth()
const { team } = storeToRefs(useTeamStore())
const user = useUserStore()

const routePlayerId = computed(() =>
  route.params?.playerId || String(route.query?.playerId || '').trim() || null
)

const isPlayerUser = computed(() => String(user.userData?.type || '').toLowerCase() === 'player')
const selfPlayerId = computed(() =>
  user.userData?.id ||
  user.userData?.user?.id ||
  user.userData?.player?.id ||
  user.userData?.user?.player?.id ||
  null
)

const resolvedTeamId = computed(() =>
  team.value?.id ||
  team.value?.id_team ||
  user.userData?.team?.id ||
  user.userData?.team?.id_team ||
  String(route.query?.teamId || '') ||
  null
)

const resolvedPlayerId = computed(() =>
  isPlayerUser.value
    ? (selfPlayerId.value || routePlayerId.value || null)
    : (routePlayerId.value || null)
)

const backRoute = computed(() => (isPlayerUser.value ? '/player-dashboard' : '/dashboard?tab=development'))
const playerTabRoute = computed(() => {
  const pid = resolvedPlayerId.value
  if (!pid) return '/development'

  const query = {}
  if (resolvedTeamId.value) query.teamId = resolvedTeamId.value
  if (selectedPlayerName.value) query.playerName = selectedPlayerName.value

  return {
    path: `/development/player/${pid}`,
    query,
  }
})

const sourceData = ref(null)
const intelligence = ref(null)
const loading = ref(false)
const loadError = ref('')
const selectedScoreKey = ref(null)
const selectedPlayerName = computed(() => String(route.query?.playerName || route.query?.name || '').trim())

const loadIntelligence = async (teamId, playerId) => {
  intelligence.value = null
  if (!teamId || !playerId || isPlayerUser.value) return

  try {
    const { data } = await axiosGet(`coach/teams/${teamId}/players/${playerId}/intelligence`, { days: 365 })
    intelligence.value = data?.data || data || null
  } catch (error) {
    intelligence.value = null
  }
}

const loadLiveData = async () => {
  loadError.value = ''
  sourceData.value = null
  intelligence.value = null

  const playerId = resolvedPlayerId.value
  const teamId = resolvedTeamId.value

  if (!playerId) {
    loadError.value = 'No player selected. Navigate here from the Team Development board.'
    return
  }
  if (!teamId) {
    loadError.value = 'No team selected. Please select a team from the header, then return here.'
    return
  }

  loading.value = true
  try {
    const endpoint = isPlayerUser.value
      ? `player/development/teams/${teamId}/players/${playerId}`
      : `coach/development/teams/${teamId}/players/${playerId}`

    const { data } = await axiosGet(endpoint, { days: 365 })
    const payload = data?.data

    if (payload?.player && payload?.current) {
      sourceData.value = {
        player: payload.player,
        current: payload.current,
        history: Array.isArray(payload.history) ? payload.history : [],
        coachNotes: payload?.coach_notes || 'Live data mode: recommendations are generated from available session + fitness data.',
      }
      await loadIntelligence(teamId, playerId)
      return
    }

    loadError.value = 'No development data found for this player. Make sure they have logged session results.'
  } catch (error) {
    const status = error?.response?.status
    if (status === 404) {
      loadError.value = 'Player not found. They may need to log sessions before development data is available.'
    } else if (status === 403) {
      loadError.value = 'Access denied. Your plan may not include player development dashboards.'
    } else {
      loadError.value = `Could not load development data (${status ?? 'network error'}). Check that the server is running.`
    }
  } finally {
    loading.value = false
  }
}

watch(
  () => [route.params?.playerId, route.query?.teamId, team.value?.id, team.value?.id_team, user.userData?.id],
  () => { loadLiveData() },
  { immediate: true }
)

const current = computed(() => sourceData.value?.current || {})
const history = computed(() => sourceData.value?.history || [])
const player = computed(() => sourceData.value?.player || {
  id: route.params?.playerId,
  name: selectedPlayerName.value || 'Selected Player',
  age: null,
  grade: '',
  position: '',
  positions: [],
  jersey: null,
  throws: '',
  bats: '',
  level: '',
  role: 'two-way',
  height: '',
  weight: null,
  graduation_year: null,
})
const model = computed(() => buildPlayerDevelopmentModel(current.value, history.value, player.value.role))
const ageGroup = computed(() => getAgeGroup(player.value))

const asNumeric = (v) => (Number.isFinite(Number(v)) ? Number(v) : null)
const parseInches = (raw) => {
  const direct = asNumeric(raw)
  if (direct !== null) return direct
  const text = String(raw ?? '').trim()
  if (!text) return null

  // Supports: 7'3", 7 ft 3 in, 87 in
  const ftIn = text.match(/^(\d+(?:\.\d+)?)\s*(?:'|ft|feet)\s*(\d+(?:\.\d+)?)?\s*(?:"|in|inch|inches)?$/i)
  if (ftIn) {
    const feet = Number(ftIn[1] || 0)
    const inches = Number(ftIn[2] || 0)
    return feet * 12 + inches
  }

  const inchesOnly = text.match(/^(\d+(?:\.\d+)?)\s*(?:"|in|inch|inches)$/i)
  if (inchesOnly) return Number(inchesOnly[1])

  return null
}

const firstParsedValue = (obj, keys = [], parser = asNumeric) => {
  for (const k of keys) {
    const parsed = parser(obj?.[k])
    if (parsed !== null) return parsed
  }
  return null
}

const firstPositiveParsedValue = (obj, keys = [], parser = asNumeric) => {
  for (const k of keys) {
    const parsed = parser(obj?.[k])
    if (parsed !== null && parsed > 0) return parsed
  }
  return null
}

// Patch current with FB velocity fallbacks so all consumers get a value
const effectiveCurrent = computed(() => ({
  ...current.value,
  avg_fb_velocity: current.value?.avg_fb_velocity ?? current.value?.avg_pitch_velocity ?? null,
  max_fb_velocity: current.value?.max_fb_velocity ?? current.value?.max_pitch_velocity ?? null,
  body_weight: firstPositiveParsedValue(current.value, ['body_weight', 'bodyWeight'], asNumeric),
  front_squat: firstPositiveParsedValue(current.value, ['front_squat', 'frontSquat'], asNumeric),
  bench_press: firstPositiveParsedValue(current.value, ['bench_press', 'benchPress'], asNumeric),
  dead_lift: firstPositiveParsedValue(current.value, ['dead_lift', 'trap_bar_deadlift', 'trapBarDeadlift'], asNumeric),
  back_squat: firstPositiveParsedValue(current.value, ['back_squat', 'backSquat'], asNumeric),
  power_clean: firstPositiveParsedValue(current.value, ['power_clean', 'powerClean'], asNumeric),
  hand_strength: firstPositiveParsedValue(current.value, ['hand_strength', 'handStrength'], asNumeric),
  vertical_jump: firstPositiveParsedValue(current.value, ['vertical_jump', 'vertical_jump_inches', 'verticalJump', 'verticalJumpInches'], parseInches),
  broad_jump: firstPositiveParsedValue(current.value, ['broad_jump', 'broad_jump_inches', 'broadJump', 'broadJumpInches'], parseInches),
  med_ball_rotational_throw: firstPositiveParsedValue(current.value, ['med_ball_rotational_throw', 'medBallRotationalThrow'], asNumeric),
  exit_velo: firstPositiveParsedValue(current.value, ['exit_velo', 'exitVelo'], asNumeric),
  bat_speed: firstPositiveParsedValue(current.value, ['bat_speed', 'batSpeed'], asNumeric),
}))

const percentileRows = computed(() => {
  const levelForBenchmarks = String(player.value?.level || 'travel').toLowerCase()

  const row = (metric, key, suffix = '', opts = {}) => {
    const value = effectiveCurrent.value?.[key]
    const benchmarkKeyMap = {
      max_fb_velocity: 'max_pitch_velocity',
      avg_fb_velocity: 'avg_pitch_velocity',
    }
    const benchmarkKey = opts.benchmarkKey === null ? null : (opts.benchmarkKey || benchmarkKeyMap[key] || key)
    const hasValue = value !== null && value !== undefined
    const hasUsableValue = hasValue && Number(value) !== 0
    const percentile = opts.scale100 && hasUsableValue
      ? Math.max(0, Math.min(100, Math.round(Number(value))))
      : (benchmarkKey && hasUsableValue
        ? getMetricPercentile(benchmarkKey, value, ageGroup.value, levelForBenchmarks)
        : null)

    let label = 'No benchmark'
    if (!hasUsableValue) label = ''
    else if (opts.scale100) label = getPercentileLabel(percentile)
    else if (benchmarkKey === null) label = 'N/A'
    else if (percentile !== null) label = getPercentileLabel(percentile)

    const numericValue = hasUsableValue ? n(value) : null
    const goalDeltaMap = {
      max_exit_velocity: 3,
      avg_exit_velocity: 3,
      max_fb_velocity: 2,
      avg_fb_velocity: 2,
      bp_score: 5,
      bullpen_score: 5,
      recovery_score: 5,
      vertical_jump: 2,
      broad_jump: 3,
      exit_velo: 3,
      bat_speed: 2,
      sleep_hours: 8,
    }
    const goalRaw = numericValue === null
      ? null
      : (key === 'sleep_hours'
        ? Math.max(numericValue, goalDeltaMap[key])
        : Math.min(opts.scale100 ? 100 : Number.POSITIVE_INFINITY, numericValue + (goalDeltaMap[key] || 0)))
    const gapRaw = numericValue !== null && goalRaw !== null ? round1(goalRaw - numericValue) : null
    const trendKeyMap = {
      max_fb_velocity: 'bullpen_avg_velocity',
      avg_fb_velocity: 'bullpen_avg_velocity',
      avg_exit_velocity: 'exit_velocity_avg',
      max_exit_velocity: 'exit_velocity_avg',
    }
    const trend = trendForMetric(trendKeyMap[key] || key)

    return {
      metric,
      value: hasUsableValue ? `${value}${suffix}` : '—',
      percentile,
      label,
      goal: goalRaw !== null ? `${round1(goalRaw)}${suffix}` : 'Benchmark',
      gap: gapRaw !== null ? `${gapRaw}${suffix}` : 'Needs Data',
      trend: trendSymbol(trend),
    }
  }

  return [
    row('Max EV', 'max_exit_velocity', ' mph'),
    row('Avg EV', 'avg_exit_velocity', ' mph'),
    row('Max FB Velo', 'max_fb_velocity', ' mph'),
    row('Avg FB Velo', 'avg_fb_velocity', ' mph'),
    row('Body Weight', 'body_weight', ' lbs'),
    row('Front Squat', 'front_squat', ' lbs'),
    row('Bench Press', 'bench_press', ' lbs'),
    row('Deadlift', 'dead_lift', ' lbs'),
    row('Back Squat', 'back_squat', ' lbs'),
    row('Power Clean', 'power_clean', ' lbs'),
    row('Hand Strength', 'hand_strength', ' lbs'),
    row('BP Score', 'bp_score', '', { scale100: true }),
    row('Bullpen Score', 'bullpen_score', '', { scale100: true }),
    row('Vertical Jump', 'vertical_jump', ' in'),
    row('Broad Jump', 'broad_jump', ' in'),
    row('Med Ball Rot Throw', 'med_ball_rotational_throw', ' mph'),
    row('Exit Velo', 'exit_velo', ' mph'),
    row('Bat Speed', 'bat_speed', ' mph'),
    row('Sleep Hours', 'sleep_hours', ' hrs'),
    row('Recovery Score', 'recovery_score'),
  ]
})

const insights = computed(() => buildCorrelationInsights(model.value.trend))
const recommendations = computed(() => buildRecommendations({
  scores: model.value,
  trend: model.value.trend,
  mobility: model.value.mobility,
}))

const n = (v) => (Number.isFinite(Number(v)) ? Number(v) : null)
const round1 = (v) => (Number.isFinite(Number(v)) ? Math.round(Number(v) * 10) / 10 : null)
const cleanText = (v) => {
  const text = String(v ?? '').trim()
  return text || null
}
const avg = (arr = []) => {
  const vals = arr.filter((v) => v !== null)
  if (!vals.length) return null
  return vals.reduce((a, b) => a + b, 0) / vals.length
}
const toText = (v, suffix = '') => (v === null || v === undefined ? '—' : `${v}${suffix}`)

const titleize = (v) => {
  const text = cleanText(v)
  if (!text) return null
  return text
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .replace(/\b\w/g, (m) => m.toUpperCase())
}

const scoreStatus = (score) => {
  const value = n(score)
  if (value === null) return 'Needs Data'
  if (value >= 85) return 'Elite'
  if (value >= 70) return 'Solid'
  if (value >= 55) return 'Developing'
  return 'Needs Attention'
}

const scoreTone = (score) => {
  const value = n(score)
  if (value === null) return 'info'
  if (value >= 85) return 'good'
  if (value >= 70) return 'caution'
  return 'risk'
}

const intelligenceSnapshot = computed(() => intelligence.value || {})
const intelligenceDataGaps = computed(() => {
  const gaps = intelligenceSnapshot.value?.data_gaps
  return Array.isArray(gaps) ? gaps : []
})

const normalizeRecommendation = (item, idx = 0) => ({
  id: cleanText(item?.id) || `recommendation-${idx}`,
  category: titleize(item?.category) || 'Development',
  priority: cleanText(item?.priority) || (idx === 0 ? 'high' : 'medium'),
  title: cleanText(item?.title) || 'Collect More Data',
  why: cleanText(item?.why) || cleanText(item?.recommendation) || 'More paired session data will improve the development plan.',
  action: cleanText(item?.action) || cleanText(item?.recommendation) || 'Score the next relevant session and review the updated dashboard.',
  expected_gain: cleanText(item?.expected_gain) || null,
  confidence: cleanText(item?.confidence) || 'low',
  evidence: Array.isArray(item?.evidence) ? item.evidence : [],
})

const actionRecommendations = computed(() => {
  const recs = Array.isArray(intelligenceSnapshot.value?.recommendations)
    ? intelligenceSnapshot.value.recommendations
    : []
  const source = recs.length ? recs : recommendations.value
  return source.slice(0, 3).map(normalizeRecommendation)
})

const primaryLimiter = computed(() => {
  const limiters = Array.isArray(intelligenceSnapshot.value?.limiters)
    ? intelligenceSnapshot.value.limiters
    : []
  const first = limiters[0]
  if (!first) return null
  return {
    label: titleize(first?.limiter || first?.title || first?.category || first?.name) || 'Development Limiter',
    confidence: cleanText(first?.confidence) || 'medium',
    evidence: Array.isArray(first?.evidence) ? first.evidence : [],
  }
})

const projectionMetric = (key) => intelligenceSnapshot.value?.projections?.[key] || null
const projectionValue = (key, horizon = 'projected_90_day') => n(projectionMetric(key)?.[horizon])
const projectionRows = computed(() => ([
  {
    label: 'Avg FB',
    current: n(effectiveCurrent.value.avg_fb_velocity) ?? n(projectionMetric('bullpen_avg_velocity')?.current),
    day30: projectionValue('bullpen_avg_velocity', 'projected_30_day'),
    day60: projectionValue('bullpen_avg_velocity', 'projected_60_day'),
    day90: projectionValue('bullpen_avg_velocity', 'projected_90_day'),
    suffix: ' mph',
  },
  {
    label: 'Avg EV',
    current: n(current.value.avg_exit_velocity) ?? n(projectionMetric('exit_velocity_avg')?.current),
    day30: projectionValue('exit_velocity_avg', 'projected_30_day'),
    day60: projectionValue('exit_velocity_avg', 'projected_60_day'),
    day90: projectionValue('exit_velocity_avg', 'projected_90_day'),
    suffix: ' mph',
  },
  {
    label: 'Strike %',
    current: n(current.value.strike_percentage) ?? n(projectionMetric('strike_percentage')?.current),
    day30: projectionValue('strike_percentage', 'projected_30_day'),
    day60: projectionValue('strike_percentage', 'projected_60_day'),
    day90: projectionValue('strike_percentage', 'projected_90_day'),
    suffix: '%',
  },
  {
    label: 'Long Toss',
    current: n(current.value.long_toss_max_distance) ?? n(current.value.max_long_toss_distance) ?? n(projectionMetric('long_toss_avg_distance')?.current),
    day30: projectionValue('long_toss_avg_distance', 'projected_30_day'),
    day60: projectionValue('long_toss_avg_distance', 'projected_60_day'),
    day90: projectionValue('long_toss_avg_distance', 'projected_90_day'),
    suffix: ' ft',
  },
  {
    label: 'PDI',
    current: n(model.value.developmentIndex),
    day30: null,
    day60: null,
    day90: null,
    suffix: '',
  },
]))

const trendForMetric = (key) => {
  const trend = intelligenceSnapshot.value?.trend_blocks?.[key] || model.value.trend?.changes?.[key] || null
  const direction = cleanText(trend?.direction) || (n(trend?.delta) > 0 ? 'improving' : n(trend?.delta) < 0 ? 'declining' : null)
  return direction || 'no_data'
}

const trendSymbol = (direction) => {
  if (['improving', 'up'].includes(direction)) return '↑'
  if (['declining', 'down'].includes(direction)) return '↓'
  if (['stable', 'flat'].includes(direction)) return '→'
  return '—'
}

const dnaSummary = computed(() => {
  const dna = intelligenceSnapshot.value?.dna || {}
  const labels = Array.isArray(dna?.player_type_labels) && dna.player_type_labels.length
    ? dna.player_type_labels
    : (Array.isArray(intelligenceSnapshot.value?.profile_labels) ? intelligenceSnapshot.value.profile_labels : [])
  const primaryType = titleize(labels[0]) || null
  const primaryStrength = titleize(dna?.primary_strength) || titleize(model.value.strengthScore >= model.value.performanceScore ? 'strength' : 'performance')
  const limiter = primaryLimiter.value?.label || null
  const projectedCeiling = projectionValue('bullpen_avg_velocity', 'projected_90_day')
    ? `${round1(projectionValue('bullpen_avg_velocity', 'projected_90_day'))} MPH`
    : null
  const trend = titleize(model.value.trend?.status) || titleize(trendForMetric('bullpen_avg_velocity'))

  return {
    hasData: Boolean(primaryType || labels.length || primaryLimiter.value || intelligence.value),
    playerType: primaryType,
    developmentStage: scoreStatus(model.value.developmentIndex),
    primaryStrength,
    primaryLimiter: limiter,
    projectedCeiling,
    trendStatus: trend,
  }
})

const snapshotRows = computed(() => ([
  ['Avg FB Velocity', toText(effectiveCurrent.value.avg_fb_velocity, ' mph')],
  ['Top FB Velocity', toText(effectiveCurrent.value.max_fb_velocity, ' mph')],
  ['Avg Exit Velocity', toText(current.value.avg_exit_velocity, ' mph')],
  ['Strike %', toText(current.value.strike_percentage, '%')],
  ['Long Toss Max', toText(current.value.long_toss_max_distance ?? current.value.max_long_toss_distance, ' ft')],
  ['Mobility', toText(model.value.mobilityScore)],
  ['Strength', toText(model.value.strengthScore)],
]))

const dataGapInstruction = computed(() => {
  const firstGap = intelligenceDataGaps.value[0]
  if (!firstGap) return null
  return cleanText(firstGap?.recommendation) || cleanText(firstGap?.message) || `Needs Data: ${titleize(firstGap?.source || firstGap)}`
})

const correlationSummary = computed(() => {
  const limiter = primaryLimiter.value
  const topContributors = [
    trendForMetric('long_toss_avg_distance') === 'improving' ? 'Long Toss Carry' : null,
    model.value.strengthScore >= 75 ? 'Strength Base' : null,
    model.value.mobilityScore >= 75 ? 'Mobility Quality' : null,
    n(current.value.strike_percentage) >= 65 ? 'Command Stability' : null,
    n(current.value.avg_exit_velocity) ? 'Exit Velocity Production' : null,
  ].filter(Boolean).slice(0, 3)

  return {
    topContributors,
    limiter: limiter?.label || 'Needs More Paired Data',
    confidence: limiter?.confidence || (topContributors.length >= 2 ? 'medium' : 'low'),
    evidence: limiter?.evidence?.length ? limiter.evidence : insights.value.slice(0, 2),
    fallback: !limiter && !topContributors.length,
  }
})

const scoreDetailByKey = computed(() => {
  const curr = current.value || {}
  const m = model.value || {}

  const perfHitterInputs = [
    ['Avg Exit Velocity', n(curr.avg_exit_velocity)],
    ['Max Exit Velocity', n(curr.max_exit_velocity)],
    ['Hard Contact %', n(curr.hard_contact_percentage)],
    ['Line Drive %', n(curr.line_drive_percentage)],
    ['BP Score', n(curr.bp_score)],
    ['Cage Score', n(curr.cage_score)],
    ['Live AB Score', n(curr.live_ab_score)],
    ['Contact Control (100 - Swing/Miss %)', n(curr.swing_miss_percentage) !== null ? 100 - n(curr.swing_miss_percentage) : null],
  ]

  const perfPitcherInputs = [
    ['Avg FB Velocity', n(curr.avg_fb_velocity)],
    ['Avg Pitch Velocity', n(curr.avg_pitch_velocity)],
    ['Max Pitch Velocity', n(curr.max_pitch_velocity)],
    ['Bullpen Score', n(curr.bullpen_score)],
    ['Command Score', n(curr.command_score)],
    ['Competitive Pitch %', n(curr.competitive_pitch_percentage)],
    ['Strike %', n(curr.strike_percentage)],
    ['Pitch Quality Score', n(curr.pitch_quality_score)],
  ]

  const hitterAvg = round1(avg(perfHitterInputs.map((x) => x[1])))
  const pitcherAvg = round1(avg(perfPitcherInputs.map((x) => x[1])))

  const upCount = Object.values(m.trend?.changes || {}).filter((x) => x?.direction === 'up').length
  const downCount = Object.values(m.trend?.changes || {}).filter((x) => x?.direction === 'down').length
  const flatCount = Object.values(m.trend?.changes || {}).filter((x) => x?.direction === 'flat').length
  const tally = upCount - downCount

  return {
    developmentIndex: {
      title: 'Player Development Index',
      formula: 'Performance×0.40 + Strength×0.20 + Mobility×0.15 + Recovery×0.15 + Trend×0.10',
      rows: [
        [`Performance (40%)`, `${m.performanceScore} × 0.40 = ${round1((m.performanceScore || 0) * 0.4)}`],
        [`Strength (20%)`, `${m.strengthScore} × 0.20 = ${round1((m.strengthScore || 0) * 0.2)}`],
        [`Mobility (15%)`, `${m.mobilityScore} × 0.15 = ${round1((m.mobilityScore || 0) * 0.15)}`],
        [`Recovery (15%)`, `${m.recoveryScore} × 0.15 = ${round1((m.recoveryScore || 0) * 0.15)}`],
        [`Trend (10%)`, `${m.trendScore} × 0.10 = ${round1((m.trendScore || 0) * 0.1)}`],
      ],
    },
    performance: {
      title: 'Performance Score',
      formula: 'Role-based average of current baseball performance metrics',
      rows: [
        ['Player role', player.value?.role || 'two-way'],
        ['Hitter average', toText(hitterAvg)],
        ['Pitcher average', toText(pitcherAvg)],
        ['Final performance score', `${m.performanceScore}`],
      ],
      inputs: {
        hitter: perfHitterInputs,
        pitcher: perfPitcherInputs,
      },
    },
    strength: {
      title: 'Strength Score',
      formula: 'Power×0.45 + Strength×0.30 + Speed×0.20 + Relative Strength×0.05',
      rows: [
        ['Power part', toText(m.strength?.parts?.power)],
        ['Strength part', toText(m.strength?.parts?.strength)],
        ['Speed part', toText(m.strength?.parts?.speed)],
        ['Relative Strength part', toText(m.strength?.parts?.pwo)],
        ['Final strength score', `${m.strengthScore}`],
      ],
    },
    mobility: {
      title: 'Mobility Score',
      formula: 'Mobility composite minus asymmetry penalties',
      rows: [
        ['Shoulder IR asymmetry', toText(m.mobility?.asymmetries?.shoulder_ir_diff)],
        ['Hip IR asymmetry', toText(m.mobility?.asymmetries?.hip_ir_diff)],
        ['Ankle asymmetry', toText(m.mobility?.asymmetries?.ankle_diff)],
        ['Thoracic asymmetry', toText(m.mobility?.asymmetries?.thoracic_diff)],
        ['Shoulder penalty', toText(round1(m.mobility?.penalties?.shoulderIR))],
        ['Hip penalty', toText(round1(m.mobility?.penalties?.hipIR))],
        ['Ankle penalty', toText(round1(m.mobility?.penalties?.ankle))],
        ['Thoracic penalty', toText(round1(m.mobility?.penalties?.thoracic))],
        ['Final mobility score', `${m.mobilityScore}`],
      ],
    },
    recovery: {
      title: 'Recovery Score',
      formula: 'Sleep×0.40 + SleepQuality×0.20 + Energy×0.15 + SorenessInv×0.10 + Hydration×0.10 + StressInv×0.05',
      rows: [
        ['Sleep score', toText(m.recovery?.parts?.sleep)],
        ['Sleep quality score', toText(m.recovery?.parts?.sleepQuality)],
        ['Energy score', toText(m.recovery?.parts?.energy)],
        ['Soreness (inverted)', toText(m.recovery?.parts?.sorenessInverted)],
        ['Hydration score', toText(m.recovery?.parts?.hydration)],
        ['Stress (inverted)', toText(m.recovery?.parts?.stressInverted)],
        ['Final recovery score', `${m.recoveryScore}`],
      ],
    },
    trend: {
      title: 'Trend Score',
      formula: '50 + (up-metrics - down-metrics) × 6, clamped to 0–100',
      rows: [
        ['Metrics trending up', `${upCount}`],
        ['Metrics trending down', `${downCount}`],
        ['Metrics flat/no change', `${flatCount}`],
        ['Directional tally', `${tally}`],
        ['Trend status', m.trend?.status || '—'],
        ['Final trend score', `${m.trendScore}`],
      ],
    },
  }
})

const selectedScoreDetail = computed(() => {
  if (!selectedScoreKey.value) return null
  return scoreDetailByKey.value[selectedScoreKey.value] || null
})

const scoreCards = computed(() => ([
  {
    key: 'developmentIndex',
    title: 'Player Development Index',
    score: model.value.developmentIndex,
    subtitle: model.value.status,
    insight: primaryLimiter.value ? `${primaryLimiter.value.label} is the current limiter.` : 'Overall development blend from performance, strength, mobility, recovery, and trend.',
    driver: dnaSummary.value.primaryStrength ? `Driver: ${dnaSummary.value.primaryStrength}` : 'Driver: Needs Data',
    next: actionRecommendations.value[0]?.action || dataGapInstruction.value || 'Score the next session to sharpen the plan.',
  },
  {
    key: 'performance',
    title: 'Performance',
    score: model.value.performanceScore,
    subtitle: '40% weight',
    insight: n(effectiveCurrent.value.avg_fb_velocity) || n(current.value.avg_exit_velocity) ? 'Baseball outputs are driving the current performance read.' : 'Needs recent baseball session data.',
    driver: `FB ${toText(effectiveCurrent.value.avg_fb_velocity, ' mph')} · EV ${toText(current.value.avg_exit_velocity, ' mph')}`,
    next: actionRecommendations.value.find((r) => /command|mound|hitter|barrel|velocity/i.test(`${r.title} ${r.category}`))?.action || 'Add a bullpen, BP, cage, or exit velocity session.',
  },
  {
    key: 'strength',
    title: 'Strength',
    score: model.value.strengthScore,
    subtitle: '20% weight',
    insight: scoreStatus(model.value.strengthScore) === 'Needs Data' ? 'Strength profile needs assessment inputs.' : 'Strength helps explain power and throwing force production.',
    driver: `Status: ${scoreStatus(model.value.strengthScore)}`,
    next: 'Maintain strength work while checking transfer to baseball outputs.',
  },
  {
    key: 'mobility',
    title: 'Mobility',
    score: model.value.mobilityScore,
    subtitle: '15% weight',
    insight: primaryLimiter.value?.label?.toLowerCase().includes('mobility') ? 'Mobility is showing up as a development limiter.' : 'Mobility supports cleaner transfer into velocity and contact.',
    driver: `Status: ${scoreStatus(model.value.mobilityScore)}`,
    next: actionRecommendations.value.find((r) => /mobility/i.test(`${r.title} ${r.category}`))?.action || 'Log a mobility screen and pair it with throwing results.',
  },
  {
    key: 'recovery',
    title: 'Recovery',
    score: model.value.recoveryScore,
    subtitle: '15% weight',
    insight: scoreStatus(model.value.recoveryScore) === 'Needs Attention' ? 'Recovery may be limiting high-intent work.' : 'Recovery status informs how hard the next session should be.',
    driver: `Sleep ${toText(current.value.sleep_hours, ' hrs')}`,
    next: actionRecommendations.value.find((r) => /recovery|workload/i.test(`${r.title} ${r.category}`))?.action || 'Keep logging sleep, soreness, and readiness daily.',
  },
  {
    key: 'trend',
    title: 'Trend',
    score: model.value.trendScore,
    subtitle: '10% weight',
    insight: `Velocity ${trendSymbol(trendForMetric('bullpen_avg_velocity'))} · Strike ${trendSymbol(trendForMetric('strike_percentage'))} · EV ${trendSymbol(trendForMetric('exit_velocity_avg'))}`,
    driver: `Status: ${titleize(model.value.trend?.status) || 'Needs Data'}`,
    next: 'Repeat comparable sessions so changes are tied to the same workload.',
  },
]).map((card) => ({ ...card, status: scoreStatus(card.score), tone: scoreTone(card.score) })))
</script>

<template>
  <Layout>
    <div class="mx-auto w-full max-w-[1600px] px-3 py-4">

      <!-- Top nav bar -->
      <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-white/10 bg-slate-900/70 px-4 py-2">
        <RouterLink :to="backRoute" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">← Back</RouterLink>
        <RouterLink :to="playerTabRoute" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Player</RouterLink>
        <RouterLink v-if="!isPlayerUser" to="/development/team" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Team</RouterLink>
        <RouterLink v-if="!isPlayerUser" to="/development/coach" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Coach</RouterLink>
        <RouterLink v-if="!isPlayerUser" to="/development/admin/benchmarks" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Admin</RouterLink>
      </div>

      <!-- Status messages -->
      <div v-if="loading" class="mb-4 rounded-xl border border-white/10 bg-slate-900/70 p-3 text-sm text-slate-300">
        Loading live development data{{ selectedPlayerName ? ` for ${selectedPlayerName}` : '' }}...
      </div>
      <div v-if="loadError" class="mb-4 rounded-xl border border-amber-400/20 bg-amber-500/10 p-3 text-sm text-amber-200">
        {{ loadError }}
      </div>

      <template v-if="sourceData">
        <!-- ═══════════════════════════════════════════════════════════
             SAVANT-STYLE 3-COLUMN LAYOUT
             Col 1 (wide): hero card + where/going/how
             Col 2 (narrow): 6 score cards stacked
             Col 3 (wide): rankings, insights, detail cards
        ════════════════════════════════════════════════════════════ -->
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[300px_1fr_1fr]">

          <!-- ── COLUMN 1 : Hero + narrative ── -->
          <div class="flex flex-col gap-4">

            <!-- Hero card — ModalPlayer style -->
            <div class="relative overflow-hidden rounded-2xl border border-white/10 shadow-2xl" style="min-height:340px">
              <!-- blurred bg -->
              <div class="absolute inset-0 bg-cover bg-center scale-110"
                :style="`background-image:url('${player.picture || updatedLogo}')`"></div>
              <div class="absolute inset-0 bg-gradient-to-b from-[#060b14]/50 via-[#060b14]/75 to-[#060b14]/97 backdrop-blur-[3px]"></div>

              <div class="relative z-10 flex flex-col items-center px-5 pb-6 pt-5 text-center">
                <!-- Avatar -->
                <div class="mb-3 h-[110px] w-[110px] overflow-hidden rounded-full ring-4 ring-[#C00000] shadow-2xl bg-slate-800 flex-shrink-0">
                  <img :src="player.picture || updatedLogo" :alt="player.name" class="h-full w-full object-cover object-top" />
                </div>

                <!-- Name + jersey -->
                <h2 class="text-xl font-black uppercase tracking-wide text-white drop-shadow leading-tight">
                  {{ player.name || 'Player' }}
                  <span v-if="player.jersey" class="ml-1 text-[#C00000]">#{{ player.jersey }}</span>
                </h2>

                <!-- Role badge -->
                <p class="mt-1 text-[10px] font-black uppercase tracking-widest text-white/50">
                  {{ player.role === 'two-way' ? 'TWO-WAY' : player.role === 'pitcher' ? 'PITCHER' : 'HITTER' }}
                </p>

                <!-- Divider -->
                <div class="my-3 w-full border-t border-white/10"></div>

                <!-- Info rows -->
                <div class="w-full flex flex-col gap-2 text-xs">
                  <div v-if="player.positions?.length" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Position</span>
                    <span class="font-black text-white">{{ player.positions.join(' · ') }}</span>
                  </div>
                  <div v-if="player.throws" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Throws</span>
                    <span class="font-black text-white">{{ player.throws }}</span>
                  </div>
                  <div v-if="player.bats" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Bats</span>
                    <span class="font-black text-white">{{ player.bats }}</span>
                  </div>
                  <div v-if="player.height" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Height</span>
                    <span class="font-black text-white">{{ player.height }}</span>
                  </div>
                  <div v-if="player.age" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Age</span>
                    <span class="font-black text-white">{{ player.age }}</span>
                  </div>
                  <div v-if="player.weight" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Weight</span>
                    <span class="font-black text-white">{{ player.weight }} lbs</span>
                  </div>
                  <div v-if="player.level" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Level</span>
                    <span class="font-black text-white capitalize">{{ player.level }}</span>
                  </div>
                </div>

                <!-- Divider -->
                <div class="my-3 w-full border-t border-white/10"></div>

                <!-- Quick stat pills -->
                <div class="flex flex-wrap justify-center gap-2">
                  <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black text-white">
                    EV {{ current.avg_exit_velocity ?? '—' }} mph
                  </span>
                  <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black text-white">
                    FB {{ effectiveCurrent.avg_fb_velocity ?? '—' }} mph
                  </span>
                  <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black text-white">
                    BP {{ current.bp_score ?? '—' }}
                  </span>
                  <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black text-white">
                    Trend <span :class="model.trend?.status === 'improving' ? 'text-green-300' : model.trend?.status === 'declining' ? 'text-red-300' : 'text-white'">{{ model.trend?.status || '—' }}</span>
                  </span>
                </div>

                <!-- Player DNA -->
                <div class="mt-4 w-full rounded-xl border border-cyan-300/15 bg-cyan-950/15 p-3 text-left">
                  <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-cyan-200/70">Player DNA</p>
                  <div v-if="dnaSummary.hasData" class="space-y-1.5 text-xs">
                    <div class="flex justify-between gap-3">
                      <span class="text-white/45">Player Type</span>
                      <span class="text-right font-black text-white">{{ dnaSummary.playerType || 'Needs Data' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                      <span class="text-white/45">Development Stage</span>
                      <span class="text-right font-black text-white">{{ dnaSummary.developmentStage }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                      <span class="text-white/45">Primary Strength</span>
                      <span class="text-right font-black text-emerald-300">{{ dnaSummary.primaryStrength || '—' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                      <span class="text-white/45">Primary Limiter</span>
                      <span class="text-right font-black text-amber-300">{{ dnaSummary.primaryLimiter || 'Needs Data' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                      <span class="text-white/45">Projected Ceiling</span>
                      <span class="text-right font-black text-cyan-200">{{ dnaSummary.projectedCeiling || '—' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                      <span class="text-white/45">Trend</span>
                      <span class="text-right font-black text-white">{{ dnaSummary.trendStatus || '—' }}</span>
                    </div>
                  </div>
                  <p v-else class="text-xs leading-relaxed text-white/55">
                    Collect more session data to unlock Player DNA.
                  </p>
                </div>
              </div>
            </div>

            <!-- WHERE ARE WE? -->
            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-4">
              <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-white/40">Where Are We?</p>
              <p class="mb-3 text-[11px] text-white/40">Current baseline from latest data points.</p>
              <div class="space-y-2 text-xs">
                <div v-for="([label, value]) in snapshotRows" :key="label" class="flex justify-between border-b border-white/5 pb-1.5 last:border-0 last:pb-0">
                  <span class="text-white/55">{{ label }}</span>
                  <span class="font-black text-white">{{ value }}</span>
                </div>
              </div>
              <p v-if="dataGapInstruction" class="mt-3 rounded-lg border border-amber-300/15 bg-amber-500/10 p-2 text-[11px] text-amber-100">
                {{ dataGapInstruction }}
              </p>
            </div>

            <!-- WHERE ARE WE GOING? -->
            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-4">
              <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-white/40">Where Are We Going?</p>
              <p class="mb-1 text-[11px] text-white/40">30 / 60 / 90-day outlook from trend data when available.</p>
              <p class="mb-3 text-xs font-black" :class="model.trend?.status === 'improving' ? 'text-green-300' : model.trend?.status === 'declining' ? 'text-red-300' : 'text-yellow-300'">
                {{ model.trend?.status || '—' }}
              </p>
              <div class="space-y-2 text-xs">
                <div v-for="row in projectionRows" :key="row.label" class="grid grid-cols-[1fr_repeat(3,minmax(42px,auto))] items-center gap-2 border-b border-white/5 pb-1.5 last:border-0 last:pb-0">
                  <span class="text-white/55">{{ row.label }}</span>
                  <span class="text-right font-black text-cyan-200">{{ row.day30 !== null ? `${row.day30}${row.suffix}` : '—' }}</span>
                  <span class="text-right font-black text-blue-200">{{ row.day60 !== null ? `${row.day60}${row.suffix}` : '—' }}</span>
                  <span class="text-right font-black text-emerald-200">{{ row.day90 !== null ? `${row.day90}${row.suffix}` : '—' }}</span>
                </div>
              </div>
              <div class="mt-2 grid grid-cols-[1fr_repeat(3,minmax(42px,auto))] gap-2 text-[10px] font-black uppercase tracking-widest text-white/30">
                <span></span><span class="text-right">30</span><span class="text-right">60</span><span class="text-right">90</span>
              </div>
            </div>

            <!-- HOW DO WE GET THERE? -->
            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-4">
              <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-white/40">How Do We Get There?</p>
              <p class="mb-3 text-[11px] text-white/40">Top action items from weakest areas.</p>
              <div v-if="actionRecommendations.length" class="space-y-3">
                <div v-for="(r, idx) in actionRecommendations" :key="r.id" class="rounded-xl border border-white/8 bg-white/5 p-3">
                  <p class="mb-1 text-[10px] font-black uppercase tracking-widest"
                    :class="r.priority === 'high' ? 'text-red-400' : r.priority === 'medium' ? 'text-yellow-400' : 'text-slate-400'">
                    Priority {{ idx + 1 }} · {{ r.priority }}
                  </p>
                  <p class="text-xs font-bold text-white">{{ r.title }}</p>
                  <p class="mt-1 text-[11px] text-white/55 leading-relaxed">{{ r.action }}</p>
                  <p v-if="r.expected_gain" class="mt-1 text-[10px] font-black text-emerald-300">{{ r.expected_gain }}</p>
                </div>
              </div>
              <p v-else class="text-xs text-slate-500">Score a bullpen, long toss, exit velocity, or assessment session to unlock action plans.</p>
            </div>

          </div><!-- /col 1 -->

          <!-- ── COLUMN 2 : Score cards + deep-dive ── -->
          <div class="flex flex-col gap-4">
            <!-- 6 score cards in a 2-col mini-grid -->
            <div>
              <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-white/30 px-1">Development Scores</p>
              <div class="flex flex-col gap-3">
                <button
                  v-for="(card, idx) in scoreCards"
                  :key="idx"
                  type="button"
                  class="w-full rounded-xl border p-4 text-left transition hover:brightness-110"
                  :class="card.tone === 'good' ? 'border-emerald-400/30 bg-emerald-950/40' : card.tone === 'caution' ? 'border-yellow-400/30 bg-yellow-950/40' : card.tone === 'risk' ? 'border-red-400/30 bg-red-950/40' : 'border-cyan-400/30 bg-cyan-950/30'"
                  @click="selectedScoreKey = card.key"
                >
                  <p class="text-[10px] font-black uppercase tracking-widest"
                    :class="card.tone === 'good' ? 'text-emerald-400/70' : card.tone === 'caution' ? 'text-yellow-400/70' : card.tone === 'risk' ? 'text-red-400/70' : 'text-cyan-300/70'">
                    {{ card.title }}
                  </p>
                  <p class="mt-1.5 text-4xl font-black leading-none"
                    :class="card.tone === 'good' ? 'text-emerald-300' : card.tone === 'caution' ? 'text-yellow-300' : card.tone === 'risk' ? 'text-red-300' : 'text-cyan-200'">
                    {{ card.score ?? '—' }}
                  </p>
                  <p class="mt-1 text-[10px] font-black uppercase tracking-widest text-white/45">{{ card.status }} · {{ card.subtitle }}</p>
                  <p class="mt-2 text-[11px] leading-relaxed text-white/65">{{ card.insight }}</p>
                  <p class="mt-2 text-[10px] font-black uppercase tracking-widest text-white/35">{{ card.driver }}</p>
                  <p class="mt-1 text-[11px] leading-relaxed text-white/55">Next: {{ card.next }}</p>
                  <p class="mt-0.5 text-[10px] text-white/25">click for details</p>
                </button>
              </div>
            </div>
            <CoachActionPlanCard :recommendations="actionRecommendations" :coach-notes="sourceData.coachNotes || ''" />
          </div><!-- /col 2 -->

          <!-- ── COLUMN 3 : Percentile Rankings + detail cards ── -->
          <div class="flex flex-col gap-4">
            <PercentileRankingsTable :rows="percentileRows" :age-label="player.age || ageGroup" />
            <CorrelationInsightsCard :insights="insights" :summary="correlationSummary" />
            <!-- Detail cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <RecoverySleepCard :recovery="model.recovery" :current="current" />
              <StrengthMetricsCard :strength="model.strength" />
              <MobilityAssessmentCard :mobility="model.mobility" />
            </div>
          </div><!-- /col 3 -->

        </div><!-- /3-col grid -->

      </template>

      <!-- No data state -->
      <div v-else-if="!loading" class="rounded-xl border border-white/10 bg-slate-900/70 p-4 text-sm text-slate-300">
        No development snapshot available for this player yet.
      </div>
    </div>

    <!-- Score detail modal (unchanged) -->
    <div v-if="selectedScoreDetail" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 px-4 pt-6" @click.self="selectedScoreKey = null">
      <div class="w-full max-w-3xl rounded-xl border border-white/15 bg-slate-900 p-4">
        <div class="mb-3 flex items-start justify-between gap-3">
          <div>
            <p class="text-[11px] uppercase tracking-wider text-slate-400">Score Build Details</p>
            <p class="text-xl font-bold text-white">{{ selectedScoreDetail.title }}</p>
            <p class="mt-1 text-xs text-slate-300">{{ selectedScoreDetail.formula }}</p>
          </div>
          <button class="rounded-md border border-white/20 px-3 py-1 text-sm text-slate-200 hover:bg-slate-800" @click="selectedScoreKey = null">Close</button>
        </div>
        <div class="rounded-lg border border-white/10 bg-slate-950/60 p-3">
          <div class="space-y-2 text-sm">
            <div v-for="(row, i) in selectedScoreDetail.rows" :key="i" class="flex items-start justify-between gap-3 border-b border-white/5 pb-2 last:border-b-0 last:pb-0">
              <p class="text-slate-300">{{ row[0] }}</p>
              <p class="text-right font-semibold text-white">{{ row[1] }}</p>
            </div>
          </div>
          <div v-if="selectedScoreKey === 'performance'" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
            <div class="rounded-md border border-white/10 p-3">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Hitter inputs</p>
              <div class="space-y-1 text-xs">
                <div v-for="(row, i) in selectedScoreDetail.inputs?.hitter || []" :key="`h-${i}`" class="flex justify-between gap-2">
                  <span class="text-slate-300">{{ row[0] }}</span>
                  <span class="font-medium text-white">{{ row[1] ?? '—' }}</span>
                </div>
              </div>
            </div>
            <div class="rounded-md border border-white/10 p-3">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Pitcher inputs</p>
              <div class="space-y-1 text-xs">
                <div v-for="(row, i) in selectedScoreDetail.inputs?.pitcher || []" :key="`p-${i}`" class="flex justify-between gap-2">
                  <span class="text-slate-300">{{ row[0] }}</span>
                  <span class="font-medium text-white">{{ row[1] ?? '—' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </Layout>
</template>
