<script setup>
import { ref, onMounted, computed } from 'vue'
import { storeToRefs } from 'pinia'
import Layout from "../../layout/Layout.vue"
import { useUserStore } from "../../store/user";
import { usePlayerStore } from "../../store/players";
import { useTeamStore } from "../../store/team";
import { IndicatorChart } from '@/components/dashboard'
import DashboardSprayChart from '@/components/dashboard/DashboardSprayChart.vue'
import VelocityZoneChart from '@/components/dashboard/VelocityZoneChart.vue'
import PitchHeatmapChart from '@/components/dashboard/PitchHeatmapChart.vue'
import PitchTypeStatsCard from '@/components/dashboard/PitchTypeStatsCard.vue'
import PlayerCompare from '@/components/dashboard/PlayerCompare.vue'
import useChart from '@/composables/useChart.js'
import useChartOptions from '@/composables/useChartOptions.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const { axiosPost } = useAxiosAuth()
const user = useUserStore()
const { team } = storeToRefs(useTeamStore())

// ── Recent sessions ───────────────────────────────────────────────────────────
const apiBaseUrl = process.env.API_ENDPOINT ?? import.meta.env.VITE_API_URL ?? ''
const recentSessions = ref([])
const recentLoading = ref(false)

const sessionTypeColor = {
  batting:      { bg: 'bg-sky-500/20',     border: 'border-sky-500/50',     text: 'text-sky-300',     label: 'BATTING' },
  bullpen:      { bg: 'bg-violet-500/20',  border: 'border-violet-500/50',  text: 'text-violet-300',  label: 'BULLPEN' },
  cage:         { bg: 'bg-emerald-500/20', border: 'border-emerald-500/50', text: 'text-emerald-300', label: 'CAGE' },
  live:         { bg: 'bg-orange-500/20',  border: 'border-orange-500/50',  text: 'text-orange-300',  label: 'LIVE AB' },
  long_toss:    { bg: 'bg-pink-500/20',    border: 'border-pink-500/50',    text: 'text-pink-300',    label: 'LONG TOSS' },
  weight_ball:  { bg: 'bg-yellow-500/20',  border: 'border-yellow-500/50',  text: 'text-yellow-300',  label: 'WEIGHT BALL' },
  exit_velocity:{ bg: 'bg-red-500/20',     border: 'border-red-500/50',     text: 'text-red-300',     label: 'EXIT VEL' },
}
const sessionReportTypeMap = {
  batting:       'batting',
  bullpen:       'bullpen',
  cage:          'cage',
  long_toss:     'long_toss',
  weight_ball:   'weight_ball',
  exit_velocity: 'exit_velocity',
}
const openSessionReport = (session) => {
  const type = sessionReportTypeMap[session._type]
  if (!type) return
  const note = session.end_note || session.notes || null
  const date = session.updated_at ?? session.created_at ?? null
  router.push({
    name: 'session.report',
    params: { id: session.id, type },
    query: { date, note },
  })
}

const getRecentSessions = async () => {
  if (!team.value?.id) return
  recentLoading.value = true
  try {
    const authRaw = localStorage.getItem('auth')
    const token = authRaw ? JSON.parse(authRaw).token : null
    const { data } = await axios.get(apiBaseUrl + 'coach/sessions/lasts/' + team.value.id, {
      headers: token ? { Authorization: `Bearer ${token}` } : {}
    })
    const d = data?.data ?? {}
    const all = []
    for (const [type, items] of Object.entries(d)) {
      if (Array.isArray(items)) items.forEach(item => all.push({ ...item, _type: type }))
    }
    all.sort((a, b) => new Date(b.updated_at ?? b.created_at) - new Date(a.updated_at ?? a.created_at))
    recentSessions.value = all.slice(0, 8)
  } catch (e) { console.warn('getRecentSessions', e) }
  finally { recentLoading.value = false }
}

const formatDate = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

// ── Top 10 ────────────────────────────────────────────────────────────────────
const top10Tab = ref(1)
const top10Range = ref(0)
const top10Data = ref([])
const top10Loading = ref(false)

const top10Tabs = [
  { label: 'Top Hitter',     value: 1, key: 'velocity', suffix: ' mph' },
  { label: 'Top Pitcher',    value: 4, key: 'velocity', suffix: ' mph' },
  { label: 'Exit Velocity',  value: 2, key: 'avg',      suffix: ' mph' },
  { label: 'Top Pitch Velo', value: 5, key: 'avg',      suffix: ' mph' },
  { label: 'Total Swings',   value: 3, key: 'count',    suffix: '' },
]

const activeTop10Tab = computed(() => top10Tabs.find(t => t.value === top10Tab.value) ?? top10Tabs[0])

const getTop10 = async () => {
  if (!team.value?.id) return
  top10Loading.value = true
  try {
    const { data } = await axiosPost('table/' + team.value.id, { option: top10Tab.value, range: top10Range.value })
    top10Data.value = data?.data?.all ?? []
  } catch (e) { console.warn('getTop10', e) }
  finally { top10Loading.value = false }
}

const switchTop10Tab = (val) => { top10Tab.value = val; getTop10() }

// ── Charts ────────────────────────────────────────────────────────────────────
const {
  ballStrike, isloading, directional,
  typeHitsBatting, pitchThrows, pitchVelocityAverage,
  typeHitsPitching, launchAngleAverage, contactSpray,
  getStaticChartData, loadOnMounted,
} = useChart()
const { barChartOptions, radiaChartOptions } = useChartOptions()

// ── Performance Overview — mirrors TeamStatsPanel/index.js (mobile app) ──────
const perfLoading = ref(false)
const perf = ref({ batting: null, bullpen: null, cage: null, ev: null, lt: null, wb: null })
const perfDetail = ref({ batting: null, bullpen: null, cage: null, ev: null, lt: null })

/** Same colour scale as the mobile app's scoreColor() helper */
function scoreColor(s) {
  if (s === null || s === undefined || isNaN(s)) return '#64748B'
  if (s >= 90) return '#2ECC71'
  if (s >= 80) return '#27AE60'
  if (s >= 70) return '#F39C12'
  if (s >= 60) return '#E67E22'
  return '#E74C3C'
}

function scoreGrade(s) {
  if (s === null || s === undefined || isNaN(s)) return null
  if (s >= 90) return 'Elite'
  if (s >= 80) return 'Winning'
  if (s >= 70) return 'Competitive'
  if (s >= 60) return 'Development'
  return 'Needs Work'
}

/** Extract cage FCS from backend pre-computed score */
function computeCageFCS(cage) {
  const fcs = cage?.fcs
  if (!fcs || fcs.total < 3) return { score: null, detail: null }
  return { score: fcs.fcs, detail: fcs }
}

/** Extract EVS from backend pre-computed score */
function computeEVS(ev) {
  const evs = ev?.evs
  if (!evs || evs.total < 1) return { score: null, detail: null }
  return { score: evs.evs, detail: evs }
}

/** Extract LTS from backend pre-computed score */
function computeLTS(lt) {
  const lts = lt?.lts
  if (!lts || lts.total < 1) return { score: null, detail: null }
  return { score: lts.lts, detail: lts }
}

/** Extract WBS from backend pre-computed score */
function computeWBS(wb) {
  const wbs = wb?.wbs
  if (!wbs || wbs.total < 1) return { score: null, detail: null }
  return { score: wbs.wbs, detail: wbs }
}

const fetchPerformanceOverview = async () => {
  if (!team.value?.id) return
  perfLoading.value = true
  try {
    const authRaw = localStorage.getItem('auth')
    const token = authRaw ? JSON.parse(authRaw).token : null
    const { data } = await axios.get(apiBaseUrl + 'coach/performance-overview/' + team.value.id, {
      headers: token ? { Authorization: `Bearer ${token}` } : {}
    })
    const d = data?.data ?? {}

    // Batting FPS — direct from backend
    const bat = Array.isArray(d.batting) ? {} : (d.batting ?? {})
    if (bat.fps != null) {
      perf.value.batting = parseFloat(bat.fps)
      perfDetail.value.batting = {
        total: bat.total, avgEV: bat.avgEV, topEV: bat.topEV,
        contactScore: bat.contactScore, evScore: bat.evScore,
        launchScore: bat.launchScore, compScore: bat.compScore,
        missScore: bat.missScore,
      }
    }

    // Bullpen BPS — d.bullpen.bps.bps
    const bull = Array.isArray(d.bullpen) ? {} : (d.bullpen ?? {})
    const bpsObj = Array.isArray(bull.bps) ? {} : (bull.bps ?? {})
    if (bpsObj.bps != null) {
      perf.value.bullpen = parseFloat(bpsObj.bps)
      perfDetail.value.bullpen = {
        total: bpsObj.total, strikeRate: bpsObj.strikeRate, avgVelo: bpsObj.avgVelo,
        topVelo: bpsObj.topVelo, veloScore: bpsObj.veloScore, mixScore: bpsObj.mixScore,
        typesUsed: bpsObj.typesUsed, fpScore: bpsObj.fpScore,
      }
    }

    // Cage FCS — from backend
    const { score: cageScore, detail: cageDet } = computeCageFCS(Array.isArray(d.cage) ? {} : d.cage)
    if (cageScore !== null) { perf.value.cage = cageScore; perfDetail.value.cage = cageDet }

    // EVS — from backend
    const { score: evScore, detail: evDet } = computeEVS(Array.isArray(d.exit_velocity) ? {} : d.exit_velocity)
    if (evScore !== null) { perf.value.ev = evScore; perfDetail.value.ev = evDet }

    // LTS — from backend
    const { score: ltScore, detail: ltDet } = computeLTS(Array.isArray(d.long_toss) ? {} : d.long_toss)
    if (ltScore !== null) { perf.value.lt = ltScore; perfDetail.value.lt = ltDet }

    // WBS — from backend
    const { score: wbScore, detail: wbDet } = computeWBS(Array.isArray(d.weight_ball) ? {} : d.weight_ball)
    if (wbScore !== null) { perf.value.wb = wbScore; perfDetail.value.wb = wbDet }

  } catch (e) { console.warn('fetchPerformanceOverview', e) }
  finally { perfLoading.value = false }
}

// The 6 overview rows — same labels/order as the app
const perfRows = computed(() => [
  {
    key: 'batting', label: 'Batting', abbr: 'FPS', score: perf.value.batting,
    dot: '#38BDF8', route: '/training/batting',
    detail: perfDetail.value.batting
      ? `${perfDetail.value.batting.total ?? '—'} swings · AvgEV ${perfDetail.value.batting.avgEV ?? '—'} · TopEV ${perfDetail.value.batting.topEV ?? '—'}`
      : null,
  },
  {
    key: 'bullpen', label: 'Bullpen', abbr: 'BPS', score: perf.value.bullpen,
    dot: '#A78BFA', route: '/training/bullpen',
    detail: perfDetail.value.bullpen
      ? `${perfDetail.value.bullpen.total ?? '—'} pitches · Strike ${perfDetail.value.bullpen.strikeRate ?? '—'}% · Avg ${perfDetail.value.bullpen.avgVelo ?? '—'} mph`
      : null,
  },
  {
    key: 'cage', label: 'Cage', abbr: 'FCS', score: perf.value.cage,
    dot: '#34D399', route: '/training/cage',
    detail: perfDetail.value.cage
      ? `${perfDetail.value.cage.total ?? '—'} swings · AvgEV ${perfDetail.value.cage.avgEV ?? '—'} · Sweet ${perfDetail.value.cage.sweetSpotPct ?? '—'}%`
      : null,
  },
  {
    key: 'ev', label: 'Exit Velocity', abbr: 'EVS', score: perf.value.ev,
    dot: '#F87171', route: '/training/training-mode',
    detail: perfDetail.value.ev
      ? `${perfDetail.value.ev.total ?? '—'} swings · Avg ${perfDetail.value.ev.avgEV ?? '—'} mph · Hard Hit ${perfDetail.value.ev.hhPct ?? '—'}%`
      : null,
  },
  {
    key: 'lt', label: 'Long Toss', abbr: 'LTS', score: perf.value.lt,
    dot: '#F472B6', route: '/training/training-mode',
    detail: perfDetail.value.lt
      ? `${perfDetail.value.lt.total ?? '—'} throws · Avg max ${perfDetail.value.lt.avgMaxDist ?? '—'} ft · Zero-hop ${perfDetail.value.lt.zeroHopRate ?? '—'}%`
      : null,
  },
  {
    key: 'wb', label: 'Weighted Ball', abbr: 'WBS', score: perf.value.wb,
    dot: '#FBBF24', route: '/training/training-mode',
    detail: perfDetail.value.wb
      ? `${perfDetail.value.wb.total ?? '—'} throws · Avg ${perfDetail.value.wb.avgVelo ?? '—'} mph · Top ${perfDetail.value.wb.topVelo ?? '—'} mph`
      : null,
  },
])

/** 3-level colour for component sub-scores (same as app's inline logic) */
function compScoreColor(s) {
  if (s === null || s === undefined || isNaN(Number(s))) return '#64748B'
  const n = Number(s)
  if (n >= 80) return '#2ECC71'
  if (n >= 60) return '#F39C12'
  return '#E74C3C'
}

// Breakdown modal state
const breakdownModal = ref({ visible: false, title: '', subtitle: '', score: null, components: [] })

function openBreakdown(row) {
  if (!row.score) return
  const d = perfDetail.value[row.key]
  let components = []

  if (row.key === 'batting' && d) {
    components = [
      { dotColor: '#2ECC71', label: 'Contact Quality',    weight: '30%', score: d.contactScore, detail: `Avg across ${d.total ?? '—'} swings` },
      { dotColor: '#3B82F6', label: 'Exit Velocity',      weight: '25%', score: d.evScore,      detail: `Avg ${d.avgEV ?? '—'} mph · Top ${d.topEV ?? '—'} mph` },
      { dotColor: '#F59E0B', label: 'Launch Profile',     weight: '20%', score: d.launchScore,  detail: 'LD=100, FB=80, PF=60, GB=50' },
      { dotColor: '#A855F7', label: 'Competitive Swings', weight: '15%', score: d.compScore,    detail: `${d.compScore != null ? Number(d.compScore).toFixed(1) : '—'} score` },
      { dotColor: '#EF4444', label: 'Miss Control',       weight: '10%', score: d.missScore,    detail: `${d.missScore != null ? Number(d.missScore).toFixed(1) : '—'} score` },
    ]
    breakdownModal.value = {
      visible: true,
      title: 'Batting Score',
      subtitle: `${d.total ?? '—'} swings analyzed`,
      score: row.score,
      components,
    }
  } else if (row.key === 'bullpen' && d) {
    const strikes = (d.strikeRate != null && d.total) ? Math.round(d.strikeRate / 100 * d.total) : null
    components = [
      { emoji: '🎯', label: 'Strike Rate',        weight: '35%', score: d.strikeRate, detail: strikes != null ? `${strikes}/${d.total} strikes (${Number(d.strikeRate).toFixed(1)}%)` : `${Number(d.strikeRate ?? 0).toFixed(1)}% strike rate` },
      { emoji: '⚾', label: 'First-Pitch Strike', weight: '15%', score: d.fpScore,    detail: `${Number(d.fpScore ?? 0).toFixed(1)}% first-pitch strikes` },
      { emoji: '📊', label: 'Velocity',           weight: '30%', score: d.veloScore,  detail: `Avg ${d.avgVelo ?? '—'} mph · Top ${d.topVelo ?? '—'} mph` },
      { emoji: '💪', label: 'Pitch Mix',          weight: '20%', score: d.mixScore,   detail: `${d.typesUsed ?? '—'} off-speed types used` },
    ]
    breakdownModal.value = {
      visible: true,
      title: 'Bullpen Score',
      subtitle: `${d.total ?? '—'} pitches analyzed`,
      score: row.score,
      components,
    }
  } else if (row.key === 'cage' && d) {
    components = [
      { emoji: '💥', label: 'Power Score',   weight: '45%', score: d.powerScore,    detail: `AvgEV ${d.avgEV ?? '—'} mph · Max ${d.maxEV ?? '—'} mph · Avg dist ${d.avgDist ?? '—'} ft` },
      { emoji: '📐', label: 'Launch Profile', weight: '40%', score: d.launchScore,   detail: `Sweet spot ${d.sweetSpotPct ?? '—'}% · LD ${d.ldPct ?? '—'}%` },
      { emoji: '🎯', label: 'Approach',       weight: '15%', score: d.approachScore, detail: `Pull ${d.pullPct ?? '—'}% · Middle ${d.middlePct ?? '—'}% · Oppo ${d.oppoPct ?? '—'}%` },
    ]
    breakdownModal.value = {
      visible: true,
      title: 'Cage Score',
      subtitle: `${d.total ?? '—'} swings · Reliability ${d.reliability != null ? (d.reliability * 100).toFixed(0) : '—'}%`,
      score: row.score,
      components,
    }
  } else if (row.key === 'ev' && d) {
    components = [
      { emoji: '🔥', label: 'EV Power',    weight: '60%', score: d.evPowerScore,   detail: `Avg ${d.avgEV ?? '—'} mph · Top ${d.topEV ?? '—'} mph` },
      { emoji: '📊', label: 'Trajectory',  weight: '25%', score: d.trajectoryScore, detail: `LD ${d.ldPct ?? '—'}% · FB ${d.fbPct ?? '—'}% · GB ${d.gbPct ?? '—'}%` },
      { emoji: '💪', label: 'Hard Hit',    weight: '15%', score: d.hardHitScore,    detail: `${d.hardHitCount ?? '—'} hard-hit balls (${d.hhPct ?? '—'}% ≥90 mph)` },
    ]
    breakdownModal.value = {
      visible: true,
      title: 'Exit Velocity Score',
      subtitle: `${d.total ?? '—'} swings analyzed`,
      score: row.score,
      components,
    }
  } else if (row.key === 'lt' && d) {
    components = [
      { emoji: '📏', label: 'Extension',   weight: '25 pts', score: d.extensionScore,   detail: `Avg max dist ${d.avgMaxDist ?? '—'} ft (target 250 ft)` },
      { emoji: '🏹', label: 'Carry',       weight: '25 pts', score: d.carryScore,       detail: `Zero-hop rate ${d.zeroHopRate ?? '—'}% · Avg carry ${d.avgCarryScore ?? '—'}` },
      { emoji: '🎯', label: 'Consistency', weight: '20 pts', score: d.consistencyScore, detail: `CV of distances per player` },
      { emoji: '📈', label: 'Progression', weight: '20 pts', score: d.progressionScore, detail: `Oldest vs newest session distance trend` },
      { emoji: '📅', label: 'Availability', weight: '10 pts', score: d.availabilityScore, detail: `${d.sessionCount ?? '—'} sessions (target 8)` },
    ]
    breakdownModal.value = {
      visible: true,
      title: 'Long Toss Score',
      subtitle: `${d.total ?? '—'} throws · ${d.totalPlayers ?? '—'} players`,
      score: row.score,
      components,
    }
  } else if (row.key === 'wb' && d) {
    components = [
      { emoji: '💨', label: 'Velocity',        weight: '30 pts', score: d.velocityScore,        detail: `Avg ${d.avgVelo ?? '—'} mph · Top ${d.topVelo ?? '—'} mph` },
      { emoji: '⚾', label: 'Ball Progression', weight: '20 pts', score: d.ballProgressionScore, detail: `${d.uniqueWeightsUsed?.length ?? '—'} different weights used` },
      { emoji: '🎯', label: 'Consistency',      weight: '20 pts', score: d.consistencyScore,     detail: `Velocity consistency per player` },
      { emoji: '📈', label: 'Progress',         weight: '20 pts', score: d.progressScore,        detail: `${d.progressPct != null ? (d.progressPct > 0 ? '+' : '') + d.progressPct : '—'}% velocity trend` },
      { emoji: '📅', label: 'Availability',     weight: '10 pts', score: d.availabilityScore,    detail: `${d.sessionCount ?? '—'} sessions (target 8)` },
    ]
    breakdownModal.value = {
      visible: true,
      title: 'Weighted Ball Score',
      subtitle: `${d.total ?? '—'} throws · ${d.totalPlayers ?? '—'} players`,
      score: row.score,
      components,
    }
  } else {
    // For rows without data yet, navigate instead
    router.push(row.route)
  }
}

function closeBreakdown() {
  breakdownModal.value.visible = false
}

if (user.userData.type !== 'player') getStaticChartData()

onMounted(() => {
  loadOnMounted()
  getRecentSessions()
  getTop10()
  fetchPerformanceOverview()
})
</script>

<template>
  <Layout>
    <div class="min-h-screen bg-[#001440] text-white">
      <div class="px-4 py-6 lg:px-6 lg:py-8 pb-28 md:pb-12 max-w-[1600px] mx-auto">

        <!-- Page title -->
        <div class="flex items-center gap-3 mb-7">
          <div class="w-1 h-7 bg-[#C00000] rounded-full" />
          <h1 class="text-2xl font-black tracking-wide text-white">Dashboard</h1>
          <span class="text-white/30 text-sm ml-auto hidden md:block">{{ team?.name }}</span>
        </div>

        <!-- 3-Column grid -->
        <div class="grid grid-cols-1 xl:grid-cols-[1fr_1fr_340px] gap-5">

          <!-- COL 1: Performance Overview + Charts -->
          <div class="flex flex-col gap-5">

            <!-- Performance Overview — real FMTRX scores from backend -->
            <div class="rounded-2xl border border-white/10 bg-[#001a40]/80 backdrop-blur-xl p-5 shadow-xl">
              <div class="flex items-center justify-between mb-1">
                <h2 class="text-base font-black uppercase tracking-widest text-white">Performance Overview</h2>
                <span class="text-white/30 text-xs">Last 10 sessions</span>
              </div>
              <p class="text-white/25 text-[11px] mb-4">FMTRX score 0–100 · same algorithm as the app</p>

              <!-- Loading skeleton -->
              <div v-if="perfLoading" class="flex flex-col gap-3">
                <div v-for="i in 6" :key="i" class="flex items-center gap-3 px-2 py-2 animate-pulse">
                  <div class="w-32 h-3 bg-white/10 rounded-full"></div>
                  <div class="flex-1 h-2 bg-white/10 rounded-full"></div>
                  <div class="w-10 h-3 bg-white/10 rounded-full"></div>
                </div>
              </div>

              <!-- Score rows -->
              <div v-else class="flex flex-col gap-1">
                <div
                  v-for="row in perfRows" :key="row.key"
                  class="group flex flex-col px-2 py-2 rounded-xl hover:bg-white/5 transition cursor-pointer"
                  @click="openBreakdown(row)"
                >
                  <div class="flex items-center gap-3">
                    <div class="w-32 shrink-0 flex items-center gap-1.5">
                      <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: row.dot }"></span>
                      <span class="text-sm font-bold text-white/80">{{ row.label }}</span>
                      <span class="text-[10px] font-black text-white/30 ml-0.5">{{ row.abbr }}</span>
                      <svg v-if="row.score" class="w-3 h-3 text-white/25 group-hover:text-white/60 transition ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                      </svg>
                    </div>
                    <div class="flex-1 h-2 bg-white/10 rounded-full overflow-hidden">
                      <div
                        class="h-full rounded-full transition-all duration-700"
                        :style="{
                          width: row.score ? Math.min(row.score, 100) + '%' : '0%',
                          backgroundColor: scoreColor(row.score),
                        }"
                      ></div>
                    </div>
                    <div class="w-16 text-right shrink-0">
                      <span v-if="row.score !== null" class="text-sm font-black tabular-nums" :style="{ color: scoreColor(row.score) }">{{ row.score }}</span>
                      <span v-else class="text-sm font-black text-white/20">--</span>
                    </div>
                  </div>
                  <div v-if="row.detail" class="mt-0.5 pl-[34px] text-[10px] text-white/30 truncate">
                    {{ row.detail }}
                    <span class="ml-1.5 px-1.5 py-0.5 rounded text-[9px] font-black" :style="{ backgroundColor: scoreColor(row.score) + '22', color: scoreColor(row.score) }">
                      {{ scoreGrade(row.score) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

                        <!-- Quick Stats bar -->
            <div class="grid grid-cols-3 gap-3">
              <div class="rounded-xl border border-white/10 bg-[#001a40]/80 p-3 text-center">
                <div class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Strike %</div>
                <div class="text-2xl font-black text-white">
                  {{ ballStrike?.strikes?.percent ?? '--' }}<span class="text-sm text-white/40">%</span>
                </div>
              </div>
              <div class="rounded-xl border border-white/10 bg-[#001a40]/80 p-3 text-center">
                <div class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Avg Pitch Velo</div>
                <div class="text-2xl font-black text-white">{{ pitchVelocityAverage?.FB ?? '--' }}</div>
              </div>
              <div class="rounded-xl border border-white/10 bg-[#001a40]/80 p-3 text-center">
                <div class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Total Pitches</div>
                <div class="text-2xl font-black text-white">{{ pitchThrows?.totals ?? '--' }}</div>
              </div>
            </div>

            <!-- Charts grid (deep-dive) -->
            <div v-if="!isloading" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="rounded-2xl border border-white/10 bg-[#001a40]/80 p-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-3">Contact Zone</h3>
                <DashboardSprayChart :contactSpray="contactSpray" :ballStrike="ballStrike" />
              </div>

              <div class="rounded-2xl border border-white/10 bg-[#001a40]/80 p-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-3">Directional</h3>
                <apexchart v-if="directional?.RIGHT" width="100%" type="radialBar" height="220"
                  :options="radiaChartOptions"
                  :series="[directional.RIGHT.percent, directional.MIDDLE.percent, directional.LEFT.percent]"/>
                <div v-else class="h-[220px] flex items-center justify-center text-white/20 text-sm">No data</div>
              </div>

              <div class="rounded-2xl border border-white/10 bg-[#001a40]/80 p-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-3">Batting Contact</h3>
                <div class="flex flex-col gap-2 mt-1">
                  <indicator-chart :labelTitle="`GB ${typeHitsBatting?.GB?.count ?? 0}`" :labelValue="typeHitsBatting?.GB?.percent ?? 0" color="#F8A488"/>
                  <indicator-chart :labelTitle="`LD ${typeHitsBatting?.LD?.count ?? 0}`" :labelValue="typeHitsBatting?.LD?.percent ?? 0" color="#ADE8F4"/>
                  <indicator-chart :labelTitle="`FLY ${typeHitsBatting?.FLY?.count ?? 0}`" :labelValue="typeHitsBatting?.FLY?.percent ?? 0" color="#8676FF"/>
                  <indicator-chart :labelTitle="`SM/F ${typeHitsBatting?.['SM/F']?.count ?? 0}`" :labelValue="typeHitsBatting?.['SM/F']?.percent ?? 0" color="#FFB457"/>
                  <indicator-chart :labelTitle="`TAKE ${typeHitsBatting?.TAKE?.count ?? 0}`" :labelValue="typeHitsBatting?.TAKE?.percent ?? 0" color="#03F1E3"/>
                </div>
              </div>

              <div class="rounded-2xl border border-white/10 bg-[#001a40]/80 p-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-3">Pitching Contact</h3>
                <div class="flex flex-col gap-2 mt-1">
                  <indicator-chart :labelTitle="`GB ${typeHitsPitching?.GB?.count ?? 0}`" :labelValue="typeHitsPitching?.GB?.percent ?? 0" color="#F8A488"/>
                  <indicator-chart :labelTitle="`FLY ${typeHitsPitching?.FLY?.count ?? 0}`" :labelValue="typeHitsPitching?.FLY?.percent ?? 0" color="#8676FF"/>
                  <indicator-chart :labelTitle="`LD ${typeHitsPitching?.LD?.count ?? 0}`" :labelValue="typeHitsPitching?.LD?.percent ?? 0" color="#ADE8F4"/>
                  <indicator-chart :labelTitle="`SM/F ${typeHitsPitching?.['SM']?.count ?? 0}`" :labelValue="typeHitsPitching?.['SM']?.percent ?? 0" color="#FFB457"/>
                </div>
              </div>

              <div class="rounded-2xl border border-white/10 bg-[#001a40]/80 p-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-3">Velocity Zones</h3>
                <VelocityZoneChart />
              </div>

              <div class="rounded-2xl border border-white/10 bg-[#001a40]/80 p-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-3">Pitch Heatmap</h3>
                <PitchHeatmapChart />
              </div>

              <div class="rounded-2xl border border-white/10 bg-[#001a40]/80 p-4 sm:col-span-2">
                <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-3">Player Comparison</h3>
                <PlayerCompare />
              </div>
            </div>
            <div v-else class="rounded-2xl border border-white/10 bg-[#001a40]/80 p-10 text-center text-white/30">Loading charts…</div>

          </div>

          <!-- COL 2: Top 10 Metrics & Performers -->
          <div class="flex flex-col gap-5">

            <div class="rounded-2xl border border-white/10 bg-[#001a40]/80 backdrop-blur-xl p-5 shadow-xl">
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-black uppercase tracking-widest text-white">Top 10 Metrics & Performers</h2>
              </div>

              <!-- Tab pills -->
              <div class="flex flex-wrap gap-1.5 mb-5">
                <button
                  v-for="tab in top10Tabs" :key="tab.value"
                  @click="switchTop10Tab(tab.value)"
                  class="px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wide transition-all border"
                  :class="top10Tab === tab.value
                    ? 'bg-[#C00000] border-[#C00000] text-white shadow-lg shadow-red-900/30'
                    : 'bg-transparent border-white/15 text-white/40 hover:border-white/40 hover:text-white'"
                >{{ tab.label }}</button>
              </div>

              <!-- Range filter -->
              <div class="flex items-center gap-2 mb-4">
                <span class="text-white/30 text-[10px] uppercase tracking-widest">Period</span>
                <div class="flex gap-1">
                  <button
                    v-for="r in [{ l: 'All', v: 0 }, { l: '1Y', v: 12 }, { l: '1M', v: 6 }, { l: '1W', v: 3 }]"
                    :key="r.v"
                    @click="top10Range = r.v; getTop10()"
                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition border"
                    :class="top10Range === r.v
                      ? 'bg-white/15 border-white/30 text-white'
                      : 'bg-transparent border-white/10 text-white/35 hover:text-white/60'"
                  >{{ r.l }}</button>
                </div>
              </div>

              <!-- Loading -->
              <div v-if="top10Loading" class="flex justify-center py-8">
                <svg class="animate-spin w-6 h-6 text-[#C00000]" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
              </div>

              <!-- Empty -->
              <div v-else-if="!top10Data.length" class="text-white/25 text-sm text-center py-8">No data for this period</div>

              <!-- Rows -->
              <div v-else class="flex flex-col gap-1.5">
                <div
                  v-for="(item, idx) in top10Data.slice(0, 10)" :key="idx"
                  class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-white/5 hover:bg-white/5 hover:border-white/15 transition cursor-pointer group"
                >
                  <span class="w-5 text-center text-sm font-black shrink-0"
                    :class="idx === 0 ? 'text-yellow-400' : idx === 1 ? 'text-slate-300' : idx === 2 ? 'text-orange-400' : 'text-white/30'">
                    {{ idx + 1 }}
                  </span>
                  <span class="flex-1 text-sm font-bold text-white truncate">{{ item.name ?? '—' }}</span>
                  <span class="text-xs font-black text-green-400 bg-green-500/10 border border-green-500/20 px-2.5 py-1 rounded-full group-hover:bg-green-500/20 transition whitespace-nowrap">
                    {{ item[activeTop10Tab.key] ?? '—' }}{{ item[activeTop10Tab.key] ? activeTop10Tab.suffix : '' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Pitch Type Breakdown -->
            <div class="rounded-2xl border border-white/10 bg-[#001a40]/80 p-5">
              <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-3">Pitch Type Breakdown</h3>
              <PitchTypeStatsCard />
            </div>

            <!-- Pitches Thrown chart -->
            <div v-if="!isloading" class="rounded-2xl border border-white/10 bg-[#001a40]/80 p-5">
              <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-3">Pitches Thrown</h3>
              <apexchart v-if="pitchThrows" width="100%" type="bar" height="220"
                :options="barChartOptions(pitchThrows.totals)"
                :series="[{ name: 'Thrown', data: [pitchThrows.totals, pitchThrows.FB, pitchThrows.CH, pitchThrows.CB, pitchThrows.SL, pitchThrows.OTHER] }]"/>
              <div v-else class="h-[220px] flex items-center justify-center text-white/20 text-sm">No data</div>
            </div>

          </div>

          <!-- COL 3: Recent Sessions -->
          <div class="flex flex-col gap-5">

            <div class="rounded-2xl border border-white/10 bg-[#001a40]/80 backdrop-blur-xl shadow-xl overflow-hidden">
              <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
                <h2 class="text-base font-black uppercase tracking-widest text-white">Recent Sessions</h2>
                <button @click="router.push({ name: 'sessions.all' })"
                  class="text-[#C00000] text-xs font-black hover:text-red-400 transition flex items-center gap-1">
                  View All
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </button>
              </div>

              <!-- Loading -->
              <div v-if="recentLoading" class="flex justify-center py-10">
                <svg class="animate-spin w-6 h-6 text-[#C00000]" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
              </div>

              <!-- Empty -->
              <div v-else-if="!recentSessions.length" class="text-white/25 text-sm text-center py-10 px-5">No sessions yet</div>

              <!-- Session list -->
              <div v-else class="divide-y divide-white/5">
                <div
                  v-for="session in recentSessions" :key="session.id"
                  class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/5 transition cursor-pointer group"
                  @click="openSessionReport(session)"
                >
                  <!-- Type badge -->
                  <span
                    class="shrink-0 text-[10px] font-black uppercase tracking-wider px-2 py-1 rounded-lg border"
                    :class="[
                      sessionTypeColor[session._type]?.bg,
                      sessionTypeColor[session._type]?.border,
                      sessionTypeColor[session._type]?.text
                    ]"
                  >{{ sessionTypeColor[session._type]?.label ?? session._type }}</span>

                  <!-- Date + player -->
                  <div class="flex-1 min-w-0">
                    <p class="text-white/80 text-sm font-bold truncate">{{ formatDate(session.updated_at ?? session.created_at) }}</p>
                    <p v-if="session.lineup?.length" class="text-white/35 text-xs truncate">
                      {{ session.lineup[0]?.name?.full ?? session.lineup[0]?.user?.profile?.first_name ?? '' }}
                      <span v-if="session.lineup.length > 1" class="text-white/25">+{{ session.lineup.length - 1 }}</span>
                    </p>
                  </div>

                  <!-- Completed / arrow -->
                  <div class="shrink-0 flex items-center gap-1.5">
                    <svg v-if="session.is_completed" class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <svg class="w-4 h-4 text-white/20 group-hover:text-white/50 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                  </div>
                </div>
              </div>

              <!-- Footer link -->
              <div class="px-5 py-4 border-t border-white/10">
                <button
                  @click="router.push({ name: 'sessions.all' })"
                  class="w-full text-center text-[#C00000] hover:text-red-400 text-sm font-black transition flex items-center justify-center gap-1.5"
                >
                  View All Sessions
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Avg Pitch Velocity chart -->
            <div v-if="!isloading" class="rounded-2xl border border-white/10 bg-[#001a40]/80 p-5">
              <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-3">Avg Pitch Velocity</h3>
              <apexchart v-if="pitchVelocityAverage" width="100%" type="bar" height="200"
                :options="barChartOptions(pitchVelocityAverage.totals / 5, 2, 2)"
                :series="[{ name: 'Average', data: [pitchVelocityAverage.FB, pitchVelocityAverage.CH, pitchVelocityAverage.CB, pitchVelocityAverage.SL, pitchVelocityAverage.OTHER] }]"/>
              <div v-else class="h-[200px] flex items-center justify-center text-white/20 text-sm">No data</div>
            </div>

          </div>

        </div>
      </div>
    </div>
    <!-- Score Breakdown Modal — matches app ScoreBreakdownModal -->
    <Teleport to="body">
      <Transition name="sheet">
        <div
          v-if="breakdownModal.visible"
          class="fixed inset-0 z-50 flex items-end justify-center"
          style="background: rgba(0,0,0,0.65)"
          @click.self="closeBreakdown"
        >
          <div class="w-full max-w-lg bg-[#0d1b33] rounded-t-3xl pt-6 pb-8 px-6 shadow-2xl border-t border-white/10">
            <!-- Handle bar -->
            <div class="w-10 h-1 bg-white/20 rounded-full mx-auto mb-5"></div>

            <!-- Header: title + big score -->
            <div class="flex items-start justify-between mb-3">
              <div>
                <h2 class="text-xl font-black text-white">{{ breakdownModal.title }}</h2>
                <p v-if="breakdownModal.subtitle" class="text-white/50 text-sm mt-0.5">{{ breakdownModal.subtitle }}</p>
              </div>
              <span class="text-4xl font-black tabular-nums leading-none" :style="{ color: scoreColor(breakdownModal.score) }">
                {{ breakdownModal.score }}
              </span>
            </div>

            <!-- Master progress bar -->
            <div class="h-2.5 bg-white/10 rounded-full mb-6 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700"
                :style="{ width: Math.min(100, breakdownModal.score) + '%', backgroundColor: scoreColor(breakdownModal.score) }"
              ></div>
            </div>

            <!-- Component rows -->
            <div v-for="comp in breakdownModal.components" :key="comp.label" class="mb-5">
              <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                  <span v-if="comp.emoji" class="text-base leading-none">{{ comp.emoji }}</span>
                  <span v-else class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: comp.dotColor }"></span>
                  <span class="text-sm font-black text-white truncate">{{ comp.label }}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0 ml-2">
                  <span class="text-xs text-white/40">{{ comp.weight }}</span>
                  <span
                    class="text-base font-black tabular-nums min-w-[44px] text-right"
                    :style="{ color: compScoreColor(comp.score) }"
                  >{{ comp.score != null ? Number(comp.score).toFixed(1) : '--' }}</span>
                </div>
              </div>
              <div class="h-1 bg-white/10 rounded-full overflow-hidden mb-1.5">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :style="{ width: comp.score ? Math.min(100, Number(comp.score)) + '%' : '0%', backgroundColor: compScoreColor(comp.score) }"
                ></div>
              </div>
              <p class="text-[11px] text-white/40">{{ comp.detail }}</p>
            </div>

            <!-- Close button -->
            <button
              class="w-full mt-2 py-3.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-black text-sm transition"
              @click="closeBreakdown"
            >Close</button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </Layout>
</template>

<style scoped>
.sheet-enter-active, .sheet-leave-active { transition: opacity 0.25s ease; }
.sheet-enter-active > div, .sheet-leave-active > div { transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1); }
.sheet-enter-from { opacity: 0; }
.sheet-enter-from > div { transform: translateY(100%); }
.sheet-leave-to { opacity: 0; }
.sheet-leave-to > div { transform: translateY(100%); }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-thumb { background: #C00000; border-radius: 5px; }
::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 4px; }
</style>
