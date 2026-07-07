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
const { axiosGet } = useAxiosAuth()
const { team } = storeToRefs(useTeamStore())

const loading = ref(false)
const loadError = ref('')
const board = ref([])
const dashboard = ref({})
const perf = ref({})

const selectedMetric = ref('average_fastball_velocity')
const selectedRange = ref('30d')
const selectedPlayers = ref([])
const priorityTop10Modal = ref({
  open: false,
  key: null,
  label: '',
  unit: '',
})

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

  const teamId = resolveTeamId.value
  if (!teamId) {
    loadError.value = 'Select a team to load development command center data.'
    return
  }

  loading.value = true
  try {
    const [boardRes, dashRes, perfRes] = await Promise.all([
      axiosGet(`coach/teams/${teamId}/player-development-board`).catch(() => null),
      axiosGet(`dashboard/${teamId}`).catch(() => null),
      axiosGet(`coach/performance-overview/${teamId}`).catch(() => null),
    ])

    board.value = Array.isArray(boardRes?.data?.data) ? boardRes.data.data : []
    dashboard.value = dashRes?.data?.data ?? {}
    perf.value = perfRes?.data?.data ?? {}

    if (!board.value.length) {
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

const playerRows = computed(() => {
  return (board.value || []).map((p) => {
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
    const bullpenScore = n(p?.scores?.bullpen)
    const liveAbScore = n(p?.scores?.batting)
    const exitVelocityScore = n(p?.scores?.ev)

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

    const currentMetric = n(p?.scores?.overall)
    const prevMetric = n(p?.prev_scores?.overall)
    const change = currentMetric !== null && prevMetric !== null ? currentMetric - prevMetric : 0
    const weightedTrend = change
    const projected30 = currentMetric !== null ? round1(currentMetric + weightedTrend * 1.5) : null
    const projected60 = currentMetric !== null ? round1(currentMetric + weightedTrend * 2.5) : null
    const projected90 = currentMetric !== null ? round1(currentMetric + weightedTrend * 3.5) : null
    const confidence = p?.coverage?.total >= 6 ? 'High' : p?.coverage?.total >= 3 ? 'Medium' : 'Low'

    return {
      id: p.id,
      name: p.name,
      status: normalizeStatus(p.status),
      trend: normalizeTrend(p.trend),
      pdi,
      pdiChange,
      bestStrength: best[0],
      biggestNeed: need[0],
      riskScore,
      riskLevel: playerRiskLevel,
      projectionSummary: projected90 !== null ? `${projected90}` : '—',
      projection: { projected30, projected60, projected90, confidence },
      metrics: {
        strike_percentage: null,
        top_pitch_velocity: n(p?.top_ev_mph),
        average_fastball_velocity: n(p?.scores?.bullpen),
        pitcher_swing_miss_percentage: null,
        bullpen_score: bullpenScore,
        long_toss_max_distance: n(p?.coverage?.long_toss ? 180 + (p.coverage.long_toss * 8) : null),
        long_toss_carry_score: longTossScore,
        average_exit_velocity: n(p?.scores?.ev),
        top_exit_velocity: n(p?.top_ev_mph),
        hard_hit_percentage: n(p?.scores?.batting),
        line_drive_percentage: null,
        hitter_swing_miss_percentage: null,
        damage_index: average([n(p?.scores?.batting), n(p?.scores?.ev)]),
      },
      prevMetrics: {
        bullpen_score: n(p?.prev_scores?.bullpen),
        average_exit_velocity: n(p?.prev_scores?.ev),
        top_exit_velocity: n(p?.prev_scores?.ev),
      },
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
  strength: round1(average(playersWithPercentile.value.map((p) => p.metrics?.bullpen_score))),
  mobility: round1(average((board.value || []).map((p) => p?.fitness?.mobility_score))),
  bullpen: round1(average((board.value || []).map((p) => p?.scores?.bullpen))),
  long_toss: round1(n(perf.value?.long_toss?.lts?.lts)),
  live_ab: round1(average((board.value || []).map((p) => p?.scores?.batting))),
  exit_velocity: round1(average((board.value || []).map((p) => p?.scores?.ev))),
}))

const tdi = computed(() => computeTDI(playersWithPercentile.value.map((p) => p.pdi)))
const prevTdi = computed(() => computeTDI(playersWithPercentile.value.map((p) => (p.pdi !== null && p.pdiChange !== null ? p.pdi - p.pdiChange : null))))
const tdiChange = computed(() => (tdi.value !== null && prevTdi.value !== null ? round1(tdi.value - prevTdi.value) : null))
const teamPercentile = computed(() => (tdi.value !== null ? clamp(Math.round((tdi.value / 100) * 100), 1, 99) : null))

const swingMissCreated = computed(() => {
  const sm = dashboard.value?.swing_miss_take_percents || {}
  const keys = ['FB', 'CH', 'CB', 'SL', 'OTHER']
  return round1(keys.reduce((sum, k) => sum + (n(sm?.[k]?.SM) || 0), 0))
})

const hitterSwingMissAgainst = computed(() => n(dashboard.value?.type_hits_batting_percents?.SM?.percent))
const lineDrivePct = computed(() => n(dashboard.value?.type_hits_batting_percents?.LD?.percent))
const hardHitPct = computed(() => n(perf.value?.batting?.compScore))

const priorityMetrics = computed(() => {
  const bullpenNow = n(perf.value?.bullpen?.bps?.bps)
  const bullpenPrev = average((board.value || []).map((p) => p?.prev_scores?.bullpen))
  const avgFbNow = n(dashboard.value?.pitch_velocity_average?.FB)
  const strikeNow = n(dashboard.value?.pitch_throws?.strike_percent)
  const avgEvNow = n(perf.value?.batting?.avgEV)
  const topEvNow = n(perf.value?.batting?.topEV)
  const evNow = average((board.value || []).map((p) => p?.scores?.ev))
  const evPrev = average((board.value || []).map((p) => p?.prev_scores?.ev))
  const ltScore = n(perf.value?.long_toss?.lts?.lts)
  const topPitch = n(perf.value?.bullpen?.bps?.topVelo)

  return [
    { key: 'strike_percentage', value: strikeNow, delta: null, insight: 'Prioritize fastball command if below 65%.' },
    { key: 'top_pitch_velocity', value: topPitch, delta: null, insight: 'Track top-end arm speed gains by week.' },
    { key: 'average_fastball_velocity', value: avgFbNow, delta: null, insight: 'Use long toss-to-mound transfer block when flat.' },
    { key: 'pitcher_swing_miss_percentage', value: swingMissCreated.value, delta: null, insight: 'Higher is better for pitchers.' },
    { key: 'long_toss_max_distance', value: n(perf.value?.long_toss?.distance_avg?.max), delta: null, insight: 'Build arm endurance and intent.' },
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
    if (m.value === null || m.goal === null) return 100
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
  for (const need of needsAttention.value.slice(0, 5)) {
    if (need.severity > 0) {
      alerts.push({
        severity: need.severity > 35 ? 'high' : need.severity > 15 ? 'medium' : 'low',
        title: `${need.label} needs attention`,
        body: `${need.label} is ${need.status.toLowerCase()} (${need.value ?? '—'}${need.unit ? ` ${need.unit}` : ''}).`,
      })
    }
  }
  const risky = playersWithPercentile.value.filter((p) => p.riskScore > 40)
  if (risky.length) {
    alerts.push({
      severity: 'high',
      title: `${risky.length} players above risk threshold`,
      body: 'Recommend recovery + mobility block and workload check.',
    })
  }
  return alerts
})

const roadmap = computed(() => {
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
      reason: `${need.label} is ${need.status.toLowerCase()} (${need.value ?? '—'}${need.unit ? ` ${need.unit}` : ''}).`,
      action: map[need.key] || 'Target this metric with focused team session design this week.',
    }
  })
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
  if (d === null || d === 0) return { text: '→ Stable', cls: 'text-slate-300' }
  if (d > 0) return { text: `↑ +${round1(d)}`, cls: 'text-emerald-300' }
  return { text: `↓ ${round1(d)}`, cls: 'text-red-300' }
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

      <template v-if="!loading && playersWithPercentile.length">
        <!-- A + G: TDI + Needs Attention -->
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 xl:col-span-2">
            <p class="text-xs uppercase tracking-widest text-white/40">Team Development Index</p>
            <div class="mt-2 flex flex-wrap items-end gap-4">
              <p class="text-5xl font-black" :class="toCardBand(tdi).tone">{{ tdi ?? '—' }}</p>
              <div class="space-y-1 text-sm text-slate-300">
                <p>Grade: <span class="font-semibold text-white">{{ toCardBand(tdi).label }}</span></p>
                <p>Team Percentile: <span class="font-semibold text-white">{{ teamPercentile ? `${teamPercentile}th` : '—' }}</span></p>
                <p>Trend: <span :class="trendChip(tdiChange).cls" class="font-semibold">{{ trendChip(tdiChange).text }}</span></p>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 md:grid-cols-3">
              <div v-for="(v, k) in teamComponentScores" :key="k" class="rounded-lg border border-white/10 bg-white/5 p-2">
                <p class="text-[10px] uppercase tracking-wider text-white/40">{{ String(k).replace('_', ' ') }}</p>
                <p class="text-lg font-black text-white">{{ v ?? '—' }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <h3 class="text-lg font-semibold text-white">Team Needs Attention</h3>
            <div class="mt-3 space-y-2 text-sm">
              <div v-for="m in needsAttention" :key="m.key" class="flex items-center justify-between rounded-md border border-white/10 bg-white/5 px-2 py-1.5">
                <span class="text-slate-200">{{ m.label }}</span>
                <span class="font-semibold" :class="m.severity > 30 ? 'text-red-300' : m.severity > 10 ? 'text-yellow-300' : 'text-emerald-300'">
                  {{ m.value ?? '—' }}{{ m.unit ? ` ${m.unit}` : '' }}
                </span>
              </div>
            </div>
          </div>
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
              <p class="mt-1 text-2xl font-black" :class="m.tone">{{ m.value ?? '—' }}<span class="text-sm font-semibold">{{ m.unit ? ` ${m.unit}` : '' }}</span></p>
              <p class="mt-1 text-xs" :class="trendChip(m.delta).cls">{{ trendChip(m.delta).text }}</p>
              <p class="mt-1 text-xs text-slate-300">{{ m.status }}<span v-if="m.goal !== null"> · Goal {{ m.goal }}{{ m.unit ? ` ${m.unit}` : '' }}</span></p>
              <p class="mt-1 text-[11px] text-white/50">{{ m.insight }}</p>
              <p class="mt-2 text-[10px] uppercase tracking-wider text-red-200/80">Tap to view Top 10 players</p>
            </button>
          </div>
        </div>

        <!-- C + D -->
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <h3 class="text-lg font-semibold text-white">Pitching Development Board</h3>
            <div class="mt-3 space-y-2">
              <div v-for="r in pitchingBoardRows" :key="r.key" class="rounded-md border border-white/10 bg-white/5 p-2">
                <div class="flex items-center justify-between">
                  <p class="text-sm text-white">{{ r.label }}</p>
                  <p class="font-semibold" :class="r.tone">{{ r.value ?? '—' }}{{ r.unit ? ` ${r.unit}` : '' }}</p>
                </div>
                <p class="text-xs text-slate-400">{{ trendChip(r.delta).text }} · {{ r.improving }} improving / {{ r.declining }} declining</p>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <h3 class="text-lg font-semibold text-white">Hitting Development Board</h3>
            <div class="mt-3 space-y-2">
              <div v-for="r in hittingBoardRows" :key="r.key" class="rounded-md border border-white/10 bg-white/5 p-2">
                <div class="flex items-center justify-between">
                  <p class="text-sm text-white">{{ r.label }}</p>
                  <p class="font-semibold" :class="r.tone">{{ r.value ?? '—' }}{{ r.unit ? ` ${r.unit}` : '' }}</p>
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
              <p class="mt-1 text-xs text-slate-300">Current {{ metricMeta[selectedMetric]?.label }}: <span class="font-semibold text-white">{{ p.current ?? '—' }}</span></p>
              <svg class="mt-2 h-14 w-full" viewBox="0 0 100 40" preserveAspectRatio="none">
                <line x1="0" y1="20" x2="100" y2="20" stroke="rgba(148,163,184,0.25)" stroke-dasharray="2 2" />
                <polyline :points="sparklinePoints([p.previous, p.current, p.projected30, p.projected60, p.projected90])" fill="none" stroke="#f43f5e" stroke-width="2" />
              </svg>
              <div class="mt-1 grid grid-cols-3 gap-2 text-xs">
                <p class="text-slate-300">30d: <span class="font-semibold text-white">{{ p.projected30 ?? '—' }}</span></p>
                <p class="text-slate-300">60d: <span class="font-semibold text-white">{{ p.projected60 ?? '—' }}</span></p>
                <p class="text-slate-300">90d: <span class="font-semibold text-white">{{ p.projected90 ?? '—' }}</span></p>
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
                  <td class="py-2 pr-4">{{ p.pdi ?? '—' }}</td>
                  <td class="py-2 pr-4">{{ p.percentile ? `${p.percentile}th` : '—' }}</td>
                  <td class="py-2 pr-4">{{ p.trend }}</td>
                  <td class="py-2 pr-4">{{ p.bestStrength }}</td>
                  <td class="py-2 pr-4">{{ p.biggestNeed }}</td>
                  <td class="py-2 pr-4">{{ p.riskScore }} ({{ p.riskLevel }})</td>
                  <td class="py-2 pr-4">{{ p.projection.projected90 ?? '—' }}</td>
                  <td class="py-2">{{ p.riskScore > 60 ? 'Needs Attention' : p.riskScore > 40 ? 'Watch' : 'Stable' }}</td>
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
              <li v-for="p in leaderboardMostImproved" :key="`imp-${p.id}`">{{ p.name }} · {{ p.pdiChange ?? '—' }}</li>
            </ol>
            <p class="mt-3 text-xs uppercase tracking-wider text-white/40">Needs Attention</p>
            <ol class="mt-1 list-decimal space-y-1 pl-5 text-sm text-slate-300">
              <li v-for="p in leaderboardNeedsAttention" :key="`risk-${p.id}`">{{ p.name }} · Risk {{ p.riskScore }}</li>
            </ol>
          </div>

          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <h3 class="text-lg font-semibold text-white">Team Alerts</h3>
            <div class="mt-2 space-y-2 text-sm">
              <div v-for="(a, idx) in teamAlerts" :key="idx" class="rounded-md border p-2"
                :class="a.severity === 'high' ? 'border-red-500/40 bg-red-500/10 text-red-200' : a.severity === 'medium' ? 'border-yellow-500/40 bg-yellow-500/10 text-yellow-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'">
                <p class="font-semibold">{{ a.title }}</p>
                <p class="text-xs opacity-90">{{ a.body }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <h3 class="text-lg font-semibold text-white">Development Roadmap</h3>
            <div class="mt-2 space-y-2">
              <div v-for="r in roadmap" :key="r.priority" class="rounded-md border border-white/10 bg-white/5 p-2">
                <p class="text-sm font-semibold text-white">{{ r.priority }}. {{ r.title }}</p>
                <p class="text-xs text-slate-300">{{ r.reason }}</p>
                <p class="mt-1 text-xs text-red-200">{{ r.action }}</p>
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
                      {{ row.value }}{{ priorityTop10Modal.unit ? ` ${priorityTop10Modal.unit}` : '' }}
                    </div>
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
