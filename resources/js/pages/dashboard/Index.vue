<script setup>
import { ref, computed, watch } from 'vue'
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
import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.png'
import useChart from '@/composables/useChart.js'
import useChartOptions from '@/composables/useChartOptions.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useRoute, useRouter } from 'vue-router'

const router = useRouter()
const route = useRoute()
const { axiosPost, axiosGet } = useAxiosAuth()
const user = useUserStore()
const dashTab = ref('overview')
const teamStore = useTeamStore()
const { team } = storeToRefs(teamStore)
const resolveTeamId = (teamLike) => teamLike?.id_team ?? teamLike?.id ?? null
const activeTeamId = computed(() => resolveTeamId(team.value))
const getTeamIdCandidates = (teamLike) => {
  const ids = [teamLike?.id_team, teamLike?.id]
    .filter(Boolean)
    .map((v) => String(v))
  return [...new Set(ids)]
}
const getActiveTeamIdCandidates = () => getTeamIdCandidates(team.value)
const withTeamIdFallbackGet = async (buildPath, teamLike = team.value) => {
  const candidates = getTeamIdCandidates(teamLike)
  let lastError
  for (const id of candidates) {
    try {
      const response = await axiosGet(buildPath(id))
      return { ...response, resolvedTeamId: id }
    } catch (e) {
      lastError = e
      const status = e?.response?.status
      if (status !== 404 && status !== 403) throw e
    }
  }
  throw lastError
}
const withTeamIdFallbackPost = async (buildPath, bodyFactory, teamLike = team.value) => {
  const candidates = getTeamIdCandidates(teamLike)
  let lastError
  for (const id of candidates) {
    try {
      const response = await axiosPost(buildPath(id), bodyFactory(id))
      return { ...response, resolvedTeamId: id }
    } catch (e) {
      lastError = e
      const status = e?.response?.status
      if (status !== 404 && status !== 403) throw e
    }
  }
  throw lastError
}
const DASHBOARD_CACHE_TTL_MS = 2 * 60 * 1000

const getDashboardCacheKey = () => {
  if (!activeTeamId.value) return null
  return `dashboard-cache:v2:${activeTeamId.value}`
}

const getTeamSessionCount = async (teamLike) => {
  const candidates = getTeamIdCandidates(teamLike)
  if (!candidates.length) return 0
  try {
    const { data } = await withTeamIdFallbackGet((id) => 'coach/sessions/lasts/' + id, teamLike)
    const d = data?.data ?? {}
    return [
      d.batting,
      d.bullpen,
      d.cage,
      d.live,
      d.weight_ball,
      d.long_toss,
      d.exit_velocity,
    ].reduce((sum, arr) => sum + (Array.isArray(arr) ? arr.length : 0), 0)
  } catch {
    return 0
  }
}

const ensureActiveTeam = async () => {
  try {
    const { data } = await axiosGet('coach/teams')

    const teamsList = Array.isArray(data?.data) ? data.data : []
    if (!teamsList.length) return

    const activeIds = getActiveTeamIdCandidates()
    const currentExists = activeIds.length
      ? teamsList.some((t) => getTeamIdCandidates(t).some((id) => activeIds.includes(id)))
      : false

    if (!currentExists) {
      teamStore.setTeam(teamsList[0])
      if (typeof teamStore.setTeams === 'function') {
        teamStore.setTeams(teamsList)
      }
      return
    }

    // If current team has no recent sessions, auto-pick the first team that does.
    const currentCount = await getTeamSessionCount(team.value)
    if (currentCount > 0) return

    for (const candidate of teamsList) {
      const candidateCount = await getTeamSessionCount(candidate)
      if (candidateCount > 0) {
        teamStore.setTeam(candidate)
        if (typeof teamStore.setTeams === 'function') {
          teamStore.setTeams(teamsList)
        }
        return
      }
    }
  } catch (e) {
    console.warn('ensureActiveTeam', e)
  }
}

const readDashboardCache = () => {
  const key = getDashboardCacheKey()
  if (!key) return null

  try {
    const raw = sessionStorage.getItem(key)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (!parsed?.savedAt) return null
    if ((Date.now() - parsed.savedAt) > DASHBOARD_CACHE_TTL_MS) {
      sessionStorage.removeItem(key)
      return null
    }
    return parsed
  } catch {
    return null
  }
}

const writeDashboardCache = (partial) => {
  const key = getDashboardCacheKey()
  if (!key) return

  try {
    const current = readDashboardCache() ?? {}
    sessionStorage.setItem(key, JSON.stringify({
      ...current,
      ...partial,
      savedAt: Date.now(),
    }))
  } catch {
    // ignore cache failures
  }
}

// ── Recent sessions ───────────────────────────────────────────────────────────
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

const getRecentSessions = async (force = false) => {
  if (!force && recentSessions.value.length) return
  if (!getActiveTeamIdCandidates().length) return
  recentLoading.value = true
  try {
    const { data } = await withTeamIdFallbackGet((id) => 'coach/sessions/lasts/' + id)
    const d = data?.data ?? {}
    const all = []
    for (const [type, items] of Object.entries(d)) {
      if (Array.isArray(items)) items.forEach(item => all.push({ ...item, _type: type }))
    }
    all.sort((a, b) => new Date(b.updated_at ?? b.created_at) - new Date(a.updated_at ?? a.created_at))
    recentSessions.value = all.slice(0, 8)
    writeDashboardCache({ recentSessions: recentSessions.value })
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
const top10Mode = ref('players') // 'players' | 'team'

const top10Tabs = [
  { label: 'Top Hitter',     value: 1, key: 'velocity', suffix: ' mph' },
  { label: 'Top Pitcher',    value: 4, key: 'velocity', suffix: ' mph' },
  { label: 'Top Avg EV',     value: 2, key: 'avg',      suffix: ' mph' },
  { label: 'Top Avg Velo',   value: 5, key: 'avg',      suffix: ' mph' },
  { label: 'Total Swings',   value: 3, key: 'count',    suffix: '' },
  { label: 'Top Strength Score', value: 12, key: 'score', suffix: '' },
]

const activeTop10Tab = computed(() => top10Tabs.find(t => t.value === top10Tab.value) ?? top10Tabs[0])

const toNumeric = (v) => {
  const n = Number(v)
  return Number.isFinite(n) ? n : null
}

const normalizePlayerName = (name) => String(name ?? '').trim().toLowerCase().replace(/\s+/g, ' ')

const playerCardsByName = computed(() => {
  const map = new Map()
  for (const card of (teamPlayerCards.value ?? [])) {
    const full = card?.profile?.full_name ?? `${card?.profile?.first_name ?? ''} ${card?.profile?.last_name ?? ''}`.trim()
    const key = normalizePlayerName(full)
    if (key) map.set(key, card)
  }
  return map
})

const top10PlayerName = (item) => item?.name ?? '—'

const top10PlayerInitials = (item) => {
  const name = top10PlayerName(item)
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map(part => part[0]?.toUpperCase())
    .join('') || '—'
}

const top10PlayerAvatar = (item) => {
  const direct = item?.profile?.picture ?? item?.picture ?? item?.avatar ?? null
  if (direct) return direct

  const key = normalizePlayerName(top10PlayerName(item))
  const card = playerCardsByName.value.get(key)
  return card?.profile?.picture ?? null
}

const top10FallbackAvatar = updatedLogo

const topStrengthRows = computed(() => {
  return (teamPlayerCards.value ?? [])
    .map((card) => ({
      name:
        card?.profile?.full_name
        ?? card?.name
        ?? card?.profile?.first_name
        ?? '—',
      avatar: card?.profile?.picture ?? null,
      score: toNumeric(card?.fmtrxx_strength_score),
    }))
    .filter(row => row.score !== null)
    .sort((a, b) => b.score - a.score)
    .slice(0, 10)
})

const top10Rows = computed(() => {
  if (top10Tab.value === 12) return topStrengthRows.value
  return (top10Data.value ?? []).slice(0, 10)
})

// ── Player Development Board ──────────────────────────────────────────────────
const devBoard = ref([])
const devBoardLoading = ref(false)
const devBoardExpanded = ref(null) // player id expanded
const devBoardShowAll = ref(false)
const devBoardDisplayLimit = 5

const visibleDevBoard = computed(() => {
  if (devBoardShowAll.value) return devBoard.value
  return devBoard.value.slice(0, devBoardDisplayLimit)
})

const devBoardHasMore = computed(() => devBoard.value.length > devBoardDisplayLimit)

const statusConfig = {
  hot:        { label: '🔥 Hot',         color: 'text-orange-400',  bg: 'bg-orange-500/10',  border: 'border-orange-500/30' },
  improving:  { label: '🟢 Improving',   color: 'text-green-400',   bg: 'bg-green-500/10',   border: 'border-green-500/30' },
  steady:     { label: '🟡 Steady',      color: 'text-yellow-400',  bg: 'bg-yellow-500/10',  border: 'border-yellow-500/30' },
  needs_work: { label: '🔴 Needs Work',  color: 'text-red-400',     bg: 'bg-red-500/10',     border: 'border-red-500/30' },
  no_data:    { label: '⚪ No Data',     color: 'text-white/30',    bg: 'bg-white/5',        border: 'border-white/10' },
}

const trendIcon = (t) => t === 'up' ? '↑' : t === 'down' ? '↓' : '→'
const trendColor = (t) => t === 'up' ? 'text-green-400' : t === 'down' ? 'text-red-400' : 'text-white/30'

const sessionTypes = [
  { key: 'batting',       label: 'BP' },
  { key: 'bullpen',       label: 'Bull' },
  { key: 'cage',          label: 'Cage' },
  { key: 'exit_velocity', label: 'EV' },
  { key: 'long_toss',     label: 'LT' },
  { key: 'weight_ball',   label: 'WB' },
]

const fetchDevBoard = async (force = false) => {
  if (!force && devBoard.value.length) return
  if (!getActiveTeamIdCandidates().length) return
  devBoardLoading.value = true
  try {
    const { data } = await withTeamIdFallbackGet((id) => 'coach/teams/' + id + '/player-development-board')
    devBoard.value = data?.data ?? []
    devBoardExpanded.value = null
    devBoardShowAll.value = false
    writeDashboardCache({ devBoard: devBoard.value })
  } catch (e) { console.warn('fetchDevBoard', e) }
  finally { devBoardLoading.value = false }
}

// Team leaders computed from perf data already loaded
const teamLeaders = computed(() => [
  { label: 'Team Batting Score (FPS)',   value: perf.value.batting  != null ? perf.value.batting  : null, suffix: '' },
  { label: 'Team Bullpen Score (BPS)',   value: perf.value.bullpen  != null ? perf.value.bullpen  : null, suffix: '' },
  { label: 'Team Cage Score (FCS)',      value: perf.value.cage     != null ? perf.value.cage     : null, suffix: '' },
  { label: 'Team EV Score (EVS)',        value: perf.value.ev       != null ? perf.value.ev       : null, suffix: '' },
  { label: 'Team Long Toss Score (LTS)', value: perf.value.lt       != null ? perf.value.lt       : null, suffix: '' },
  { label: 'Avg Pitch Velo',             value: pitchVelocityAverage.value?.FB ?? null, suffix: ' mph' },
  { label: 'Strike %',                   value: pitchThrows.value?.strike_percent ?? null, suffix: '%' },
  { label: 'Total Pitches',              value: pitchThrows.value?.totals ?? null, suffix: '' },
].filter(r => r.value != null))

const getTop10 = async (force = false) => {
  if (top10Tab.value === 12) {
    await ensureTeamPlayerCards()
    return
  }

  if (!force && top10Data.value.length) return
  if (!getActiveTeamIdCandidates().length) return
  top10Loading.value = true
  try {
    const { data } = await withTeamIdFallbackPost(
      (id) => 'table/' + id,
      () => ({ option: top10Tab.value, range: top10Range.value })
    )
    top10Data.value = data?.data?.all ?? []
    writeDashboardCache({
      top10Data: top10Data.value,
      top10Tab: top10Tab.value,
      top10Range: top10Range.value,
    })
  } catch (e) { console.warn('getTop10', e) }
  finally { top10Loading.value = false }
}

const switchTop10Tab = (val) => {
  top10Tab.value = val

  if (val === 12) {
    if (!teamPlayerCards.value.length) {
      ensureTeamPlayerCards().catch(e => console.warn('ensureTeamPlayerCards strength tab error:', e?.message ?? e))
    }
    return
  }

  getTop10(true)
}

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
const perfLastFetch = ref(null)
const perf = ref({ batting: null, bullpen: null, cage: null, ev: null, lt: null, wb: null })
const perfDetail = ref({ batting: null, bullpen: null, cage: null, ev: null, lt: null })
const perfUnavailableTeams = ref({})

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

const fetchPerformanceOverview = async (force = false) => {
  if (!force && Object.values(perf.value).some(v => v !== null) && (Date.now() - (perfLastFetch.value ?? 0)) < DASHBOARD_CACHE_TTL_MS) return
  const teamIds = getActiveTeamIdCandidates()
  const teamId = teamIds[0]
  if (!teamId) return
  if (!force && perfUnavailableTeams.value[teamId]) return
  perfLoading.value = true
  try {
    const response = await withTeamIdFallbackGet((id) => 'coach/performance-overview/' + id)
    const { data } = response
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

    writeDashboardCache({ perf: perf.value, perfDetail: perfDetail.value })
    perfLastFetch.value = Date.now()
    if (perfUnavailableTeams.value[teamId]) {
      const next = { ...perfUnavailableTeams.value }
      delete next[teamId]
      perfUnavailableTeams.value = next
    }
  } catch (e) {
    const status = e?.response?.status
    if (status === 500) {
      perfUnavailableTeams.value = {
        ...perfUnavailableTeams.value,
        [teamId]: true,
      }
    } else {
      console.warn('fetchPerformanceOverview', e)
    }
  }
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
    const eliteDetail = d.eliteThrowerRate != null
      ? ` · Elite-for-age ${Number(d.eliteThrowerRate).toFixed(1)}%`
      : ''
    components = [
      { emoji: '🎯', label: 'Strike Rate',        weight: '35%', score: d.strikeRate, detail: strikes != null ? `${strikes}/${d.total} strikes (${Number(d.strikeRate).toFixed(1)}%)` : `${Number(d.strikeRate ?? 0).toFixed(1)}% strike rate` },
      { emoji: '⚾', label: 'First-Pitch Strike', weight: '15%', score: d.fpScore,    detail: `${Number(d.fpScore ?? 0).toFixed(1)}% first-pitch strikes` },
      { emoji: '📊', label: 'Velocity',           weight: '30%', score: d.veloScore,  detail: `Avg ${d.avgVelo ?? '—'} mph · Top ${d.topVelo ?? '—'} mph${eliteDetail}` },
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
    const thresholdHint = d.avgHardHitThreshold != null
      ? `≥${d.avgHardHitThreshold} mph hard-hit`
      : 'age-adjusted hard-hit'
    components = [
      { emoji: '🔥', label: 'EV Power',    weight: '60%', score: d.evPowerScore,   detail: `Avg ${d.avgEV ?? '—'} mph · Top ${d.topEV ?? '—'} mph` },
      { emoji: '📊', label: 'Trajectory',  weight: '25%', score: d.trajectoryScore, detail: `LD ${d.ldPct ?? '—'}% · FB ${d.fbPct ?? '—'}% · GB ${d.gbPct ?? '—'}%` },
      { emoji: '💪', label: 'Hard Hit',    weight: '15%', score: d.hardHitScore,    detail: `${d.hardHitCount ?? '—'} hard-hit balls (${d.hhPct ?? '—'}% ${thresholdHint})` },
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
      { emoji: '📏', label: 'Extension',   weight: '25 pts', score: d.extensionScore,   detail: `No-hop ${d.avgPeakNoHopDist ?? d.avgMaxDist ?? '—'} ft · Est. peak ${d.avgEstimatedPeakVelo ?? '—'} mph` },
      { emoji: '🏹', label: 'Carry',       weight: '25 pts', score: d.carryScore,       detail: `Intensity ${d.avgIntensityPct ?? '—'}% · Zero-hop ${d.zeroHopRate ?? '—'}%` },
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

function openOverallDevelopmentBreakdown() {
  const p = selectedDevPlayer.value
  if (!p) return

  const scoreEntries = [
    { key: 'batting', label: 'Batting (FPS)', value: toNum(p.scores?.batting), detail: 'Scripted BP quality and contact execution profile.' },
    { key: 'bullpen', label: 'Bullpen (BPS)', value: toNum(p.scores?.bullpen), detail: 'Command + velocity quality from bullpen sessions.' },
    { key: 'cage', label: 'Cage (FCS)', value: toNum(p.scores?.cage), detail: 'Cage contact quality, launch profile, and approach mix.' },
    { key: 'ev', label: 'Exit Velocity (EVS)', value: toNum(p.scores?.ev), detail: 'Raw batted-ball power and hard-hit trajectory profile.' },
  ].filter((x) => x.value != null)

  if (!scoreEntries.length) return

  const equalWeight = Math.round((100 / scoreEntries.length) * 10) / 10
  const components = scoreEntries.map((x) => ({
    label: x.label,
    score: x.value,
    weight: `${equalWeight}%`,
    dotColor: '#60A5FA',
    detail: x.detail,
  }))

  const weighted = scoreEntries.reduce((sum, x) => sum + x.value, 0) / scoreEntries.length

  breakdownModal.value = {
    visible: true,
    title: 'Overall Development Score',
    subtitle: `Composite from ${scoreEntries.length} available FMTRX pillars`,
    score: Math.round(weighted * 10) / 10,
    components,
  }
}

const insightModal = ref({ visible: false, title: '', body: '', bullets: [] })

const insightCopy = {
  snapshot: {
    title: 'Player Snapshot',
    body: 'Quick identity and context for this athlete so decisions are made with age, side, level, and role in view.',
    bullets: [
      'Use this to confirm you are evaluating the correct player profile.',
      'Bats/Throws and level help contextualize score expectations.',
      'Status reflects current development direction, not long-term potential.'
    ],
  },
  overall: {
    title: 'Overall Development Score',
    body: 'Single composite score from available FMTRX pillar scores for this player in the current data window.',
    bullets: [
      'Click the score card to open full component breakdown.',
      'Green delta means improving vs prior baseline; red means declining.',
      'Use this as a direction signal, then coach from the underlying pillars.'
    ],
  },
  best: {
    title: 'Best Area',
    body: 'Highest performing pillar right now.',
    bullets: [
      'Protect this strength with maintenance reps.',
      'Leverage this area for confidence during training blocks.'
    ],
  },
  needs: {
    title: 'Needs Work',
    body: 'Lowest performing pillar right now.',
    bullets: [
      'This is your biggest short-term coaching opportunity.',
      'Recommended next session is mapped from this weak pillar.'
    ],
  },
  trend: {
    title: 'Last 7/30 Day Trend',
    body: 'Short-term momentum view of key pillars.',
    bullets: [
      'Up arrow indicates measurable improvement.',
      'Flat means stable output; down means regression to address.'
    ],
  },
  hitting: {
    title: 'Hitting Overview',
    body: 'Summary of batting and cage execution.',
    bullets: [
      'BP score reflects scripted batting execution quality.',
      'Cage score reflects contact quality, launch, and approach.'
    ],
  },
  pitching: {
    title: 'Pitching Overview',
    body: 'Snapshot of bullpen score and top fastball output.',
    bullets: [
      'Use with trend panel to confirm command/velo direction.',
      'Top FB is peak output; score reflects repeatable execution.'
    ],
  },
  armcare: {
    title: 'Arm Care / Throwing',
    body: 'Throwing capacity and intent metrics from long toss/weighted work.',
    bullets: [
      'Long Toss Max indicates extension/carry ceiling.',
      'Weighted Ball Max indicates intent and arm-speed expression.'
    ],
  },
  strength: {
    title: 'Strength Metrics',
    body: 'Latest tracked force and body metrics with team-relative standing.',
    bullets: [
      'Ranked fields compare player vs current team distribution.',
      'Unranked means data is missing or insufficient.'
    ],
  },
  scorecard: {
    title: 'Scripted BP Scorecard',
    body: 'Compact breakdown of batting quality indicators.',
    bullets: [
      'Use this to explain the batting score in coach/player language.',
      'Feedback line translates metrics into an actionable coaching cue.'
    ],
  },
  timeline: {
    title: 'Development Timeline',
    body: 'Recent sessions linked to this player in chronological order.',
    bullets: [
      'Use this to validate recency before making training decisions.',
      'Look for session density and type balance week-to-week.'
    ],
  },
  takeaway: {
    title: 'Coach Takeaway',
    body: 'Auto-generated summary that combines score, trend, strengths, and next action.',
    bullets: [
      'Ideal for quick staff handoff and player communication.',
      'Always confirm with underlying score components when planning.'
    ],
  },
}

function openInsight(key) {
  const item = insightCopy[key]
  if (!item) return
  insightModal.value = {
    visible: true,
    title: item.title,
    body: item.body,
    bullets: item.bullets,
  }
}

function closeInsight() {
  insightModal.value.visible = false
}

// ── Player Development Detail Modal ──────────────────────────────────────────
const devDetailModal = ref({ visible: false, loading: false })
const selectedDevPlayer = ref(null)
const selectedDevCard = ref(null)
const selectedDevStats = ref(null)
const playerCardsLoaded = ref(false)
const teamPlayerCards = ref([])

const toNum = (v) => {
  const n = Number(v)
  return Number.isFinite(n) ? n : null
}

const playerStatusLabel = computed(() => {
  const st = selectedDevPlayer.value?.status
  return statusConfig[st]?.label ?? 'No Data'
})

const playerOverallDelta = computed(() => {
  const now = toNum(selectedDevPlayer.value?.scores?.overall)
  const prev = toNum(selectedDevPlayer.value?.prev_scores?.overall)
  if (now == null || prev == null) return null
  return Math.round((now - prev) * 10) / 10
})

const playerBestAndNeeds = computed(() => {
  const s = selectedDevPlayer.value?.scores ?? {}
  const entries = [
    ['batting', 'Batting FPS', toNum(s.batting)],
    ['bullpen', 'Bullpen BPS', toNum(s.bullpen)],
    ['cage', 'Cage FCS', toNum(s.cage)],
    ['ev', 'Exit Velo EVS', toNum(s.ev)],
  ].filter(([, , v]) => v != null)

  if (!entries.length) {
    return {
      bestTrait: 'No recent metric data',
      needsWork: 'Collect more sessions',
      recommended: 'Run baseline session this week',
    }
  }

  const best = [...entries].sort((a, b) => b[2] - a[2])[0]
  const low = [...entries].sort((a, b) => a[2] - b[2])[0]
  const recMap = {
    batting: 'Scripted BP: Two-Strike Compete',
    bullpen: 'Bullpen: Fastball Command Ladder',
    cage: 'Cage: Contact Quality Round',
    ev: 'EV: Hard-Hit Progression Round',
  }
  return {
    bestTrait: `${best[1]} (${Math.round(best[2])})`,
    needsWork: `${low[1]} (${Math.round(low[2])})`,
    recommended: recMap[low[0]] ?? 'High-quality fundamentals session',
  }
})

const playerRecentSessions = computed(() => {
  const pid = selectedDevPlayer.value?.id
  if (!pid) return []
  return (recentSessions.value ?? []).filter((s) => {
    const lineup = Array.isArray(s.lineup) ? s.lineup : []
    return lineup.some((l) =>
      l?.user?.id === pid || l?.user_id === pid || l?.id === pid
    )
  }).slice(0, 10)
})

const playerTrendRows = computed(() => {
  const p = selectedDevPlayer.value
  if (!p) return []
  const pairs = [
    ['Exit Velo', toNum(p.scores?.ev), toNum(p.prev_scores?.ev)],
    ['Batting', toNum(p.scores?.batting), toNum(p.prev_scores?.batting)],
    ['Bullpen', toNum(p.scores?.bullpen), toNum(p.prev_scores?.bullpen)],
    ['Cage', toNum(p.scores?.cage), toNum(p.prev_scores?.cage)],
  ]

  return pairs.map(([label, now, prev]) => {
    if (now == null || prev == null) return { label, text: '→ No baseline', color: 'text-white/40' }
    const d = Math.round((now - prev) * 10) / 10
    if (d >= 1) return { label, text: `↑ +${d}`, color: 'text-green-400' }
    if (d <= -1) return { label, text: `↓ ${d}`, color: 'text-red-400' }
    return { label, text: '→ Flat', color: 'text-yellow-300' }
  })
})

const coachTakeaway = computed(() => {
  const p = selectedDevPlayer.value
  if (!p) return ''
  const delta = playerOverallDelta.value
  const deltaText = delta == null ? 'stable' : (delta > 0 ? `up ${delta}` : delta < 0 ? `down ${Math.abs(delta)}` : 'stable')
  const best = playerBestAndNeeds.value.bestTrait
  const need = playerBestAndNeeds.value.needsWork
  const rec = playerBestAndNeeds.value.recommended
  return `${p.name} is trending ${deltaText} overall. Best area: ${best}. Needs work: ${need}. Recommended next session: ${rec}.`
})

const fitnessStanding = (metric) => {
  const r = selectedDevPlayer.value?.fitness_rank?.[metric]
  if (!r || !r.rank || !r.total) return 'Unranked'
  return `#${r.rank}/${r.total}`
}

const ensureTeamPlayerCards = async () => {
  if (playerCardsLoaded.value || !getActiveTeamIdCandidates().length) return
  const { data } = await withTeamIdFallbackGet((id) => 'coach/teams/' + id + '/player-cards')
  teamPlayerCards.value = data?.data ?? []
  playerCardsLoaded.value = true
}

const openDevPlayerDetail = async (player) => {
  selectedDevPlayer.value = player
  selectedDevCard.value = null
  selectedDevStats.value = null
  devDetailModal.value = { visible: true, loading: true }
  try {
    await ensureTeamPlayerCards()
    selectedDevCard.value = (teamPlayerCards.value ?? []).find((c) => c.id === player.id) ?? null

    const { data } = await axiosGet('coach/statistics/' + player.id)
    selectedDevStats.value = data?.data ?? null
  } catch (e) {
    console.warn('openDevPlayerDetail', e)
  } finally {
    devDetailModal.value.loading = false
  }
}

const closeDevPlayerDetail = () => {
  devDetailModal.value.visible = false
}

const quickStatsLoaded = ref(false)

// ── Mobility Assessment (Quick Stats tab replacement) ────────────────────────
const bmsNum = (v) => {
  const n = Number(v)
  return Number.isFinite(n) ? n : null
}

const bmsGrade = (score) => {
  if (score >= 90) return 'Elite'
  if (score >= 80) return 'Excellent'
  if (score >= 70) return 'Good'
  if (score >= 60) return 'Average'
  if (score >= 50) return 'Poor'
  return 'High Risk'
}

const scoreApleyScratch = (gapInches) => {
  const g = bmsNum(gapInches)
  if (g === null) return 0
  if (g <= 0) return 10
  if (g < 2) return 8
  if (g <= 4) return 6
  if (g <= 8) return 3
  return 0
}

const scoreShoulderER = (deg) => {
  const d = bmsNum(deg)
  if (d === null) return 0
  if (d >= 120) return 10
  if (d >= 110) return 8
  if (d >= 100) return 6
  if (d >= 90) return 3
  return 0
}

const scoreThoracic = (leftDeg, rightDeg) => {
  const l = bmsNum(leftDeg)
  const r = bmsNum(rightDeg)
  if (l === null || r === null) return 0
  const minSide = Math.min(l, r)
  if (minSide >= 55) return 15
  if (minSide >= 45) return 12
  if (minSide >= 35) return 8
  if (minSide >= 25) return 4
  return 0
}

const scoreHip9090 = (rating) => {
  if (rating === 'full') return 10
  if (rating === 'mild') return 7
  if (rating === 'significant') return 3
  return 0
}

const scoreHipIR = (deg) => {
  const d = bmsNum(deg)
  if (d === null) return 0
  if (d >= 40) return 10
  if (d >= 30) return 8
  if (d >= 20) return 5
  return 0
}

const scoreHipFlexion = (rating) => {
  if (rating === 'above') return 5
  if (rating === 'chest') return 4
  if (rating === 'below') return 2
  return 0
}

const scoreAnkle = (inches) => {
  const d = bmsNum(inches)
  if (d === null) return 0
  if (d >= 5) return 15
  if (d >= 4) return 12
  if (d >= 3) return 8
  if (d >= 2) return 4
  return 0
}

const scoreDeadBug = (seconds) => {
  const s = bmsNum(seconds)
  if (s === null) return 0
  if (s >= 60) return 10
  if (s >= 45) return 8
  if (s >= 30) return 5
  if (s >= 15) return 2
  return 0
}

const scoreBalance = (leftSec, rightSec) => {
  const l = bmsNum(leftSec)
  const r = bmsNum(rightSec)
  if (l === null || r === null) return 0
  const minSide = Math.min(l, r)
  if (minSide >= 30) return 15
  if (minSide >= 20) return 12
  if (minSide >= 15) return 8
  if (minSide >= 10) return 4
  return 0
}

const computeBms = (input = {}) => {
  const shoulder = scoreApleyScratch(input.apley_gap_inches) + scoreShoulderER(input.shoulder_er_throwing_deg)
  const thoracic = scoreThoracic(input.thoracic_rotation_left_deg, input.thoracic_rotation_right_deg)
  const hip = scoreHip9090(input.hip_9090_rating) + scoreHipIR(input.hip_internal_rotation_deg) + scoreHipFlexion(input.hip_flexion_rating)
  const ankle = scoreAnkle(input.ankle_knee_to_wall_inches)
  const core = scoreDeadBug(input.dead_bug_hold_sec)
  const balance = scoreBalance(input.single_leg_balance_left_sec, input.single_leg_balance_right_sec)
  const total = shoulder + thoracic + hip + ankle + core + balance

  return {
    score: total,
    grade: bmsGrade(total),
    parts: {
      shoulder,
      thoracic,
      hip,
      ankle,
      core,
      balance,
    },
  }
}

const mobilityPlayers = ref([])
const mobilityPlayersLoading = ref(false)
const mobilitySaving = ref(false)
const mobilityHistoryLoading = ref(false)
const mobilityHistory = ref([])
const mobilityAssessmentType = ref('first_time') // first_time | reassessment
const mobilitySelectedPlayerId = ref('')
const mobilityMessage = ref({ type: '', text: '' })
const mobilityHelpOpen = ref('')

const toggleMobilityHelp = (section) => {
  mobilityHelpOpen.value = mobilityHelpOpen.value === section ? '' : section
}

const mobilityForm = ref({
  fitness_date: new Date().toISOString().slice(0, 10),
  apley_gap_inches: '',
  shoulder_er_throwing_deg: '',
  thoracic_rotation_left_deg: '',
  thoracic_rotation_right_deg: '',
  hip_9090_rating: '',
  hip_internal_rotation_deg: '',
  hip_flexion_rating: '',
  ankle_knee_to_wall_inches: '',
  dead_bug_hold_sec: '',
  single_leg_balance_left_sec: '',
  single_leg_balance_right_sec: '',
})

const selectedMobilityPlayer = computed(() => {
  return mobilityPlayers.value.find((p) => String(p.id) === String(mobilitySelectedPlayerId.value)) ?? null
})

const latestMobilityRecord = computed(() => {
  return Array.isArray(mobilityHistory.value) && mobilityHistory.value.length ? mobilityHistory.value[0] : null
})

const computedMobility = computed(() => {
  return computeBms(mobilityForm.value)
})

const mobilityFormComplete = computed(() => {
  const f = mobilityForm.value
  return (
    f.fitness_date &&
    f.apley_gap_inches !== '' &&
    f.shoulder_er_throwing_deg !== '' &&
    f.thoracic_rotation_left_deg !== '' &&
    f.thoracic_rotation_right_deg !== '' &&
    f.hip_9090_rating &&
    f.hip_internal_rotation_deg !== '' &&
    f.hip_flexion_rating &&
    f.ankle_knee_to_wall_inches !== '' &&
    f.dead_bug_hold_sec !== '' &&
    f.single_leg_balance_left_sec !== '' &&
    f.single_leg_balance_right_sec !== ''
  )
})

const latestMobilityScore = computed(() => {
  const s = Number(latestMobilityRecord.value?.mobility_score)
  return Number.isFinite(s) ? s : null
})

const mobilityDelta = computed(() => {
  if (latestMobilityScore.value == null) return null
  return computedMobility.value.score - latestMobilityScore.value
})

const fetchMobilityPlayers = async () => {
  mobilityPlayersLoading.value = true
  try {
    const res = await axiosGet('coach/roster/players')
    mobilityPlayers.value = (res?.data?.data ?? []).map((p) => ({
      id: p.id,
      name: p?.name?.full || `${p?.name?.first || ''} ${p?.name?.last || ''}`.trim() || `Player #${p.id}`,
    }))
  } catch {
    mobilityPlayers.value = []
  } finally {
    mobilityPlayersLoading.value = false
  }
}

const fetchMobilityHistory = async () => {
  mobilityMessage.value = { type: '', text: '' }
  if (!mobilitySelectedPlayerId.value) {
    mobilityHistory.value = []
    return
  }
  mobilityHistoryLoading.value = true
  try {
    const res = await axiosGet(`player/fitness/${mobilitySelectedPlayerId.value}`)
    mobilityHistory.value = Array.isArray(res?.data?.data) ? res.data.data : []
  } catch {
    mobilityHistory.value = []
  } finally {
    mobilityHistoryLoading.value = false
  }
}

watch(() => mobilitySelectedPlayerId.value, () => {
  fetchMobilityHistory()
})

const submitMobilityAssessment = async () => {
  mobilityMessage.value = { type: '', text: '' }
  if (!mobilitySelectedPlayerId.value) {
    mobilityMessage.value = { type: 'error', text: 'Select a player first.' }
    return
  }

  if (!mobilityFormComplete.value) {
    mobilityMessage.value = { type: 'error', text: 'Complete all mobility tests before saving.' }
    return
  }

  if (mobilityAssessmentType.value === 'first_time' && latestMobilityScore.value != null) {
    mobilityMessage.value = {
      type: 'error',
      text: 'This player already has a mobility baseline. Use Reassessment.',
    }
    return
  }

  if (mobilityAssessmentType.value === 'reassessment' && latestMobilityScore.value == null) {
    mobilityMessage.value = {
      type: 'error',
      text: 'No baseline found yet. Choose First-time Assessment.',
    }
    return
  }

  mobilitySaving.value = true
  try {
    await axiosPost('player/fitness', {
      user_id: mobilitySelectedPlayerId.value,
      fitness_date: mobilityForm.value.fitness_date,
      mobility_score: computedMobility.value.score,
    })

    const modeLabel = mobilityAssessmentType.value === 'first_time' ? 'baseline assessment' : 'reassessment'
    mobilityMessage.value = {
      type: 'success',
      text: `Saved ${modeLabel} for ${selectedMobilityPlayer.value?.name || 'player'} · BMS ${computedMobility.value.score} (${computedMobility.value.grade}).`,
    }
    await fetchMobilityHistory()
    await fetchDevBoard(true)
  } catch {
    mobilityMessage.value = { type: 'error', text: 'Could not save mobility assessment.' }
  } finally {
    mobilitySaving.value = false
  }
}

const ensureQuickStatsLoaded = async () => {
  if (quickStatsLoaded.value) return

  if (user.userData.type !== 'player') await fetchMobilityPlayers()

  quickStatsLoaded.value = true
}

const allowedDashboardTabs = ['overview', 'development', 'quickstats']

const setDashTab = (tab) => {
  const nextTab = allowedDashboardTabs.includes(tab) ? tab : 'overview'
  dashTab.value = nextTab
  router.replace({
    query: {
      ...route.query,
      tab: nextTab === 'overview' ? undefined : nextTab,
    },
  })
}

watch(
  () => route.query?.tab,
  (tab) => {
    const nextTab = typeof tab === 'string' && allowedDashboardTabs.includes(tab) ? tab : 'overview'
    if (dashTab.value !== nextTab) {
      dashTab.value = nextTab
    }
  },
  { immediate: true }
)

watch(
  () => dashTab.value,
  (tab) => {
    if (tab === 'quickstats') {
      ensureQuickStatsLoaded().catch(e => console.warn('ensureQuickStatsLoaded error:', e?.message ?? e))
    }
  },
  { immediate: true }
)

const hydrateDashboardFromCache = () => {
  const cache = readDashboardCache()
  if (!cache) return false

  if (Array.isArray(cache.recentSessions) && cache.recentSessions.length) {
    recentSessions.value = cache.recentSessions
  }

  if (Array.isArray(cache.top10Data) && cache.top10Data.length) {
    top10Data.value = cache.top10Data
  }

  if (typeof cache.top10Tab === 'number') {
    top10Tab.value = cache.top10Tab
  }

  if (typeof cache.top10Range === 'number') {
    top10Range.value = cache.top10Range
  }

  if (cache.perf) {
    perf.value = cache.perf
  }

  if (cache.perfDetail) {
    perfDetail.value = cache.perfDetail
  }

  if (Array.isArray(cache.devBoard) && cache.devBoard.length) {
    devBoard.value = cache.devBoard
  }

  return true
}

watch(
  () => activeTeamId.value,
  async (teamId) => {
    if (!teamId) {
      await ensureActiveTeam()
      if (!activeTeamId.value) return
    } else {
      await ensureActiveTeam()
    }

    hydrateDashboardFromCache()

    // Priority 1 — fast/cached, render immediately
    getRecentSessions()
    getTop10()

    // Priority 2 — heavier, defer until after first paint
    setTimeout(() => {
      fetchPerformanceOverview()
      fetchDevBoard()
      ensureTeamPlayerCards().catch(e => console.warn('ensureTeamPlayerCards preload error:', e?.message ?? e))
    }, 800)
  },
  { immediate: true }
)
</script>

<template>
  <Layout>
    <div class="min-h-screen bg-[#060b14] text-white">
      <div class="w-full px-4 py-6 lg:px-8 lg:py-8 pb-28 md:pb-12">

        <!-- Page title -->
        <div class="flex items-center gap-3 mb-5">
          <div class="w-1 h-7 bg-[#C00000] rounded-full" />
          <h1 class="text-2xl font-black tracking-wide text-white">Dashboard</h1>
          <span class="text-white/30 text-sm ml-auto hidden md:block">{{ team?.name }}</span>
        </div>

        <!-- Tab switcher -->
        <div class="flex gap-1 mb-6 bg-[#0a1020]/60 border border-white/10 rounded-xl p-1 w-fit">
          <button
            @click="setDashTab('overview')"
            class="px-5 py-2 rounded-lg text-sm font-black uppercase tracking-wide transition-all"
            :class="dashTab === 'overview' ? 'bg-[#C00000] text-white shadow-lg shadow-red-900/30' : 'text-white/40 hover:text-white'"
          >Overview</button>
          <button
            @click="setDashTab('development')"
            class="px-5 py-2 rounded-lg text-sm font-black uppercase tracking-wide transition-all"
            :class="dashTab === 'development' ? 'bg-[#C00000] text-white shadow-lg shadow-red-900/30' : 'text-white/40 hover:text-white'"
          >Player Development</button>
        </div>

        <!-- OVERVIEW TAB -->
        <div v-if="dashTab === 'overview'">

          <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">

          <!-- ── Player Development Board ── -->
          <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 backdrop-blur-xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-base font-black uppercase tracking-widest text-white">Player Development Board</h2>
              <span class="text-white/30 text-[11px] uppercase tracking-widest">Last 5 sessions · FMTRX score</span>
            </div>

            <!-- Loading skeleton -->
            <div v-if="devBoardLoading" class="flex flex-col gap-2">
              <div v-for="i in 5" :key="i" class="flex items-center gap-3 px-3 py-3 animate-pulse rounded-xl bg-white/5">
                <div class="w-24 h-3 bg-white/10 rounded-full"></div>
                <div class="flex-1 h-2 bg-white/10 rounded-full"></div>
                <div class="w-16 h-3 bg-white/10 rounded-full"></div>
              </div>
            </div>

            <!-- Empty state -->
            <div v-else-if="!devBoard.length" class="text-white/25 text-sm text-center py-6">
              No player data available
            </div>

            <!-- Board rows -->
            <div v-else>
              <!-- Summary strip -->
              <div class="flex gap-3 mb-4 flex-wrap">
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-500/10 border border-orange-500/20">
                  <span class="text-orange-400 text-xs font-black">🔥 Hot</span>
                  <span class="text-orange-300 text-xs font-bold ml-1">{{ devBoard.filter(p => p.status === 'hot').length }}</span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-500/10 border border-green-500/20">
                  <span class="text-green-400 text-xs font-black">🟢 Improving</span>
                  <span class="text-green-300 text-xs font-bold ml-1">{{ devBoard.filter(p => p.status === 'improving').length }}</span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/20">
                  <span class="text-red-400 text-xs font-black">🔴 Needs Work</span>
                  <span class="text-red-300 text-xs font-bold ml-1">{{ devBoard.filter(p => p.status === 'needs_work').length }}</span>
                </div>
              </div>

              <!-- Column headers -->
              <div class="hidden md:grid grid-cols-[1fr_80px_60px_60px_auto] gap-2 px-3 mb-1">
                <span class="text-white/25 text-[10px] uppercase tracking-widest">Player</span>
                <span class="text-white/25 text-[10px] uppercase tracking-widest text-center">Status</span>
                <span class="text-white/25 text-[10px] uppercase tracking-widest text-right">Score</span>
                <span class="text-white/25 text-[10px] uppercase tracking-widest text-right">Trend</span>
                <span class="text-white/25 text-[10px] uppercase tracking-widest text-right">Sessions (30d)</span>
              </div>

              <div class="flex flex-col gap-1.5">
                <div
                  v-for="player in visibleDevBoard" :key="player.id"
                  class="rounded-xl border transition cursor-pointer"
                  :class="[statusConfig[player.status]?.border ?? 'border-white/10',
                           statusConfig[player.status]?.bg ?? 'bg-white/5',
                           devBoardExpanded === player.id ? 'border-opacity-60' : 'hover:border-opacity-50']"
                  @click="devBoardExpanded = devBoardExpanded === player.id ? null : player.id"
                >
                  <!-- Main row -->
                  <div class="grid grid-cols-[1fr_auto] md:grid-cols-[1fr_90px_70px_60px_auto] items-center gap-2 px-4 py-3">
                    <!-- Name + jersey -->
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="text-white/30 text-xs font-bold w-6 text-center shrink-0">#{{ player.jersey ?? '—' }}</span>
                      <button
                        class="text-sm font-black text-sky-300 hover:text-sky-200 truncate text-left"
                        @click.stop="openDevPlayerDetail(player)"
                      >{{ player.name }}</button>
                    </div>
                    <!-- Status badge -->
                    <div class="flex justify-center">
                      <span class="text-xs font-black px-2 py-0.5 rounded-full whitespace-nowrap"
                        :class="[statusConfig[player.status]?.color ?? 'text-white/40',
                                 statusConfig[player.status]?.bg ?? 'bg-white/5']">
                        {{ statusConfig[player.status]?.label ?? player.status }}
                      </span>
                    </div>
                    <!-- Score -->
                    <div class="flex justify-end">
                      <span class="text-sm font-black px-2.5 py-0.5 rounded-full"
                        :style="player.scores?.overall != null ? { backgroundColor: scoreColor(player.scores.overall) + '22', color: scoreColor(player.scores.overall) } : {}">
                        {{ player.scores?.overall != null ? Math.round(player.scores.overall) : '—' }}
                      </span>
                    </div>
                    <!-- Trend -->
                    <div class="flex justify-end">
                      <span class="text-lg" :class="trendColor(player.trend)">{{ trendIcon(player.trend) }}</span>
                    </div>
                    <!-- Session coverage pills -->
                    <div class="flex gap-1 justify-end flex-wrap col-span-2 md:col-span-1 mt-1 md:mt-0">
                      <span
                        v-for="st in sessionTypes" :key="st.key"
                        class="text-[10px] font-black px-1.5 py-0.5 rounded border whitespace-nowrap"
                        :class="(player.coverage?.[st.key] ?? 0) > 0
                          ? 'text-white/70 bg-white/10 border-white/20'
                          : 'text-white/15 bg-transparent border-white/5'"
                      >{{ st.label }} {{ player.coverage?.[st.key] ?? 0 }}</span>
                    </div>
                  </div>

                  <!-- Expanded detail row -->
                  <div v-if="devBoardExpanded === player.id" class="border-t border-white/10 px-4 py-3">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                      <div v-if="player.scores?.batting != null" class="text-center">
                        <div class="text-[10px] text-white/30 uppercase tracking-widest mb-1">Batting FPS</div>
                        <div class="text-xl font-black" :style="{ color: scoreColor(player.scores.batting) }">{{ Math.round(player.scores.batting) }}</div>
                        <div class="text-[10px] text-white/30 mt-0.5">
                          prev: {{ player.prev_scores?.batting != null ? Math.round(player.prev_scores.batting) : '—' }}
                        </div>
                      </div>
                      <div v-if="player.scores?.bullpen != null" class="text-center">
                        <div class="text-[10px] text-white/30 uppercase tracking-widest mb-1">Bullpen BPS</div>
                        <div class="text-xl font-black" :style="{ color: scoreColor(player.scores.bullpen) }">{{ Math.round(player.scores.bullpen) }}</div>
                        <div class="text-[10px] text-white/30 mt-0.5">
                          prev: {{ player.prev_scores?.bullpen != null ? Math.round(player.prev_scores.bullpen) : '—' }}
                        </div>
                      </div>
                      <div v-if="player.scores?.cage != null" class="text-center">
                        <div class="text-[10px] text-white/30 uppercase tracking-widest mb-1">Cage FCS</div>
                        <div class="text-xl font-black" :style="{ color: scoreColor(player.scores.cage) }">{{ Math.round(player.scores.cage) }}</div>
                        <div class="text-[10px] text-white/30 mt-0.5">
                          prev: {{ player.prev_scores?.cage != null ? Math.round(player.prev_scores.cage) : '—' }}
                        </div>
                      </div>
                      <div v-if="player.scores?.ev != null" class="text-center">
                        <div class="text-[10px] text-white/30 uppercase tracking-widest mb-1">Exit Velo EVS</div>
                        <div class="text-xl font-black" :style="{ color: scoreColor(player.scores.ev) }">{{ Math.round(player.scores.ev) }}</div>
                        <div class="text-[10px] text-white/30 mt-0.5">
                          prev: {{ player.prev_scores?.ev != null ? Math.round(player.prev_scores.ev) : '—' }}
                        </div>
                      </div>
                    </div>

                    <div class="mt-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                      <div class="text-[10px] uppercase tracking-widest text-white/35 mb-1.5">Weight Room Standing</div>
                      <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-[11px]">
                        <div class="text-white/70">Wt: <span class="text-white font-bold">{{ player.fitness?.body_weight ?? '—' }}</span> <span class="text-[#FCA5A5]">{{ player.fitness_rank?.body_weight?.rank ? `#${player.fitness_rank.body_weight.rank}/${player.fitness_rank.body_weight.total}` : '—' }}</span></div>
                        <div class="text-white/70">Bench: <span class="text-white font-bold">{{ player.fitness?.bench_press ?? '—' }}</span> <span class="text-[#FCA5A5]">{{ player.fitness_rank?.bench_press?.rank ? `#${player.fitness_rank.bench_press.rank}/${player.fitness_rank.bench_press.total}` : '—' }}</span></div>
                        <div class="text-white/70">FSQ: <span class="text-white font-bold">{{ player.fitness?.front_squat ?? '—' }}</span> <span class="text-[#FCA5A5]">{{ player.fitness_rank?.front_squat?.rank ? `#${player.fitness_rank.front_squat.rank}/${player.fitness_rank.front_squat.total}` : '—' }}</span></div>
                        <div class="text-white/70">PC: <span class="text-white font-bold">{{ player.fitness?.power_clean ?? '—' }}</span> <span class="text-[#FCA5A5]">{{ player.fitness_rank?.power_clean?.rank ? `#${player.fitness_rank.power_clean.rank}/${player.fitness_rank.power_clean.total}` : '—' }}</span></div>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="devBoardHasMore" class="pt-2 flex justify-center">
                  <button
                    class="px-4 py-1.5 rounded-lg text-xs font-black uppercase tracking-wide transition-all border border-white/20 text-white/70 hover:text-white hover:border-white/40"
                    @click="devBoardShowAll = !devBoardShowAll; if (!devBoardShowAll) devBoardExpanded = null"
                  >
                    {{ devBoardShowAll ? 'Show First 5' : `Show All (${devBoard.length})` }}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Performance Overview — real FMTRX scores from backend -->
          <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 backdrop-blur-xl p-5 shadow-xl">
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

          </div>

        <!-- 2-Column grid -->
        <div class="grid grid-cols-1 xl:grid-cols-[1fr_340px] gap-5">

          <!-- COL 2: Top 10 Metrics & Performers -->
          <div class="flex flex-col gap-5">

            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 backdrop-blur-xl p-5 shadow-xl">
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-black uppercase tracking-widest text-white">Top 10 Metrics & Performers</h2>
              </div>

              <!-- Player / Team toggle -->
              <div class="flex gap-1 mb-4 bg-white/5 rounded-lg p-0.5 w-fit">
                <button
                  @click="top10Mode = 'players'"
                  class="px-4 py-1.5 rounded-md text-xs font-black uppercase tracking-wide transition-all"
                  :class="top10Mode === 'players' ? 'bg-[#C00000] text-white' : 'text-white/40 hover:text-white'"
                >Player Leaders</button>
                <button
                  @click="top10Mode = 'team'"
                  class="px-4 py-1.5 rounded-md text-xs font-black uppercase tracking-wide transition-all"
                  :class="top10Mode === 'team' ? 'bg-[#C00000] text-white' : 'text-white/40 hover:text-white'"
                >Team Leaders</button>
              </div>

              <!-- Player Leaders view -->
              <template v-if="top10Mode === 'players'">
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
                      @click="top10Range = r.v; top10Tab === 12 ? ensureTeamPlayerCards() : getTop10()"
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
                <div v-else-if="!top10Rows.length" class="text-white/25 text-sm text-center py-8">No data for this period</div>

                <!-- Rows -->
                <div v-else class="flex flex-col gap-1.5">
                  <div
                    v-for="(item, idx) in top10Rows" :key="idx"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-white/5 hover:bg-white/5 hover:border-white/15 transition cursor-pointer group"
                  >
                    <span class="w-5 text-center text-sm font-black shrink-0"
                      :class="idx === 0 ? 'text-yellow-400' : idx === 1 ? 'text-slate-300' : idx === 2 ? 'text-orange-400' : 'text-white/30'">
                      {{ idx + 1 }}
                    </span>
                    <div class="flex-1 min-w-0 flex items-center gap-2">
                      <div class="w-7 h-7 rounded-full overflow-hidden ring-1 ring-white/20 bg-[#0f172a] shrink-0 flex items-center justify-center">
                        <img
                          :src="top10PlayerAvatar(item) || top10FallbackAvatar"
                          alt="player avatar"
                          class="w-full h-full object-cover"
                        />
                      </div>
                      <span class="text-sm font-bold text-white truncate">{{ top10PlayerName(item) }}</span>
                    </div>
                    <span class="text-xs font-black text-green-400 bg-green-500/10 border border-green-500/20 px-2.5 py-1 rounded-full group-hover:bg-green-500/20 transition whitespace-nowrap">
                      {{ top10Tab === 12 ? (item.score ?? '—') : (item[activeTop10Tab.key] ?? '—') }}{{ top10Tab !== 12 && item[activeTop10Tab.key] ? activeTop10Tab.suffix : '' }}
                    </span>
                  </div>
                </div>
              </template>

              <!-- Team Leaders view -->
              <template v-else>
                <div v-if="perfLoading" class="flex justify-center py-8">
                  <svg class="animate-spin w-6 h-6 text-[#C00000]" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                  </svg>
                </div>
                <div v-else-if="!teamLeaders.length" class="text-white/25 text-sm text-center py-8">No team data yet</div>
                <div v-else class="flex flex-col gap-1.5">
                  <div
                    v-for="(row, idx) in teamLeaders" :key="row.label"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-white/5"
                  >
                    <span class="w-5 text-center text-sm font-black shrink-0"
                      :class="idx === 0 ? 'text-yellow-400' : idx === 1 ? 'text-slate-300' : idx === 2 ? 'text-orange-400' : 'text-white/30'">
                      {{ idx + 1 }}
                    </span>
                    <span class="flex-1 text-sm font-bold text-white/70 truncate">{{ row.label }}</span>
                    <span class="text-xs font-black px-2.5 py-1 rounded-full whitespace-nowrap"
                      :style="{ backgroundColor: scoreColor(row.value) + '22', color: scoreColor(row.value) }">
                      {{ row.value }}{{ row.suffix }}
                    </span>
                  </div>
                </div>
              </template>
            </div>

          </div>

          <!-- COL 3: Recent Sessions -->
          <div class="flex flex-col gap-5">

            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 backdrop-blur-xl shadow-xl overflow-hidden">
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


          </div>

        </div>
        </div><!-- end overview tab -->

        <!-- PLAYER DEVELOPMENT TAB -->
        <div v-if="dashTab === 'development'" class="flex flex-col gap-5">

          <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 backdrop-blur-xl p-5 shadow-xl">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <h2 class="text-base font-black uppercase tracking-widest text-white">Development Dashboards</h2>
                <p class="text-white/40 text-xs mt-1">Jump between Player, Team, Coach, and Admin development views.</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button class="px-3 py-1.5 rounded-lg text-xs font-black uppercase tracking-wide border border-white/20 text-white/80 hover:text-white hover:border-white/40" @click="router.push('/development')">Player</button>
                <button class="px-3 py-1.5 rounded-lg text-xs font-black uppercase tracking-wide border border-white/20 text-white/80 hover:text-white hover:border-white/40" @click="router.push('/development/team')">Team</button>
                <button class="px-3 py-1.5 rounded-lg text-xs font-black uppercase tracking-wide border border-white/20 text-white/80 hover:text-white hover:border-white/40" @click="router.push('/development/coach')">Coach</button>
                <button class="px-3 py-1.5 rounded-lg text-xs font-black uppercase tracking-wide border border-white/20 text-white/80 hover:text-white hover:border-white/40" @click="router.push('/development/admin/benchmarks')">Admin</button>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 backdrop-blur-xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-base font-black uppercase tracking-widest text-white">Team Development Snapshot</h2>
              <button
                class="text-[#C00000] text-xs font-black hover:text-red-400 transition"
                @click="router.push('/development/team')"
              >Open Full Team Dashboard</button>
            </div>

            <div v-if="devBoardLoading" class="text-white/40 text-sm">Loading development board...</div>
            <div v-else-if="!devBoard.length" class="text-white/25 text-sm">No player development data available</div>
            <div v-else class="flex flex-col gap-2">
              <div
                v-for="player in visibleDevBoard"
                :key="player.id"
                class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 flex items-center gap-2"
              >
                <button class="text-sm font-black text-sky-300 hover:text-sky-200 truncate" @click="router.push(`/development/player/${player.id}`)">
                  {{ player.name }}
                </button>
                <span class="text-[11px] font-black px-2 py-0.5 rounded-full bg-cyan-500/15 text-cyan-300 border border-cyan-400/30">
                  MOB {{ player.fitness?.mobility_score ?? '—' }}
                </span>
                <span class="ml-auto text-xs font-black px-2 py-0.5 rounded-full"
                  :style="player.scores?.overall != null ? { backgroundColor: scoreColor(player.scores.overall) + '22', color: scoreColor(player.scores.overall) } : {}">
                  {{ player.scores?.overall != null ? Math.round(player.scores.overall) : '—' }}
                </span>
              </div>
            </div>
          </div>

        </div><!-- end development tab -->

        <!-- QUICK STATS TAB (sidebar access) -->
        <div v-if="dashTab === 'quickstats'" class="mobility-assessment flex flex-col gap-5">
          <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-5">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
              <div>
                <h2 class="text-base font-black uppercase tracking-widest text-white">Mobility Assessment</h2>
                <p class="text-xs text-white/45 mt-1">Record a baseline or reassessment mobility test and save the score into player development.</p>
              </div>
              <div class="text-xs text-white/50">Step 1: assessment type · Step 2: player · Step 3: test inputs · Step 4: save score</div>
            </div>
          </div>

          <div class="grid grid-cols-1 xl:grid-cols-[1.5fr_1fr] gap-5">
            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-5">
              <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-4">Assessment Form</h3>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                <div class="md:col-span-2">
                  <label class="block text-[11px] uppercase tracking-widest text-white/45 mb-1">Assessment Type</label>
                  <div class="flex gap-2">
                    <button
                      type="button"
                      class="px-3 py-2 rounded-lg border text-xs font-black uppercase tracking-wide"
                      :class="mobilityAssessmentType === 'first_time' ? 'bg-[#C00000]/20 border-[#C00000]/50 text-white' : 'bg-white/5 border-white/15 text-white/60'"
                      @click="mobilityAssessmentType = 'first_time'"
                    >First-time Assessment</button>
                    <button
                      type="button"
                      class="px-3 py-2 rounded-lg border text-xs font-black uppercase tracking-wide"
                      :class="mobilityAssessmentType === 'reassessment' ? 'bg-[#C00000]/20 border-[#C00000]/50 text-white' : 'bg-white/5 border-white/15 text-white/60'"
                      @click="mobilityAssessmentType = 'reassessment'"
                    >Reassessment</button>
                  </div>
                </div>
                <div>
                  <label class="block text-[11px] uppercase tracking-widest text-white/45 mb-1">Assessment Date</label>
                  <input v-model="mobilityForm.fitness_date" type="date" class="w-full rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-sm text-white outline-none focus:border-red-400/60" />
                </div>
              </div>

              <div class="mb-4">
                <label class="block text-[11px] uppercase tracking-widest text-white/45 mb-1">Player</label>
                <select
                  v-model="mobilitySelectedPlayerId"
                  class="mob-select w-full rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-sm text-white outline-none focus:border-red-400/60"
                  :disabled="mobilityPlayersLoading"
                >
                  <option value="">Select player</option>
                  <option v-for="p in mobilityPlayers" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                </select>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                  <button type="button" class="mob-test-title" @click="toggleMobilityHelp('shoulder')">
                    <span>Shoulder (20 pts)</span>
                    <span class="mob-test-help-cta">{{ mobilityHelpOpen === 'shoulder' ? 'Hide how to assess' : 'Click for how to assess' }}</span>
                  </button>
                  <div v-if="mobilityHelpOpen === 'shoulder'" class="mob-test-help">
                    Apley Scratch: measure gap between hands in inches (0 = hands touch). Throwing Arm ER: athlete supine, shoulder abducted 90°, measure external rotation in degrees.
                  </div>
                  <div class="grid grid-cols-2 gap-2">
                    <input v-model="mobilityForm.apley_gap_inches" type="number" step="0.1" placeholder="Apley gap (in) · 0 = touches" class="mob-input col-span-2" />
                    <input v-model="mobilityForm.shoulder_er_throwing_deg" type="number" step="0.1" placeholder="Throwing arm ER (deg)" class="mob-input col-span-2" />
                  </div>
                </div>

                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                  <button type="button" class="mob-test-title" @click="toggleMobilityHelp('thoracic')">
                    <span>Thoracic Rotation (15 pts)</span>
                    <span class="mob-test-help-cta">{{ mobilityHelpOpen === 'thoracic' ? 'Hide how to assess' : 'Click for how to assess' }}</span>
                  </button>
                  <div v-if="mobilityHelpOpen === 'thoracic'" class="mob-test-help">
                    Athlete in half-kneeling or quadruped. Stabilize hips and record active thoracic rotation left and right in degrees. Use the lower side as the limiter.
                  </div>
                  <div class="grid grid-cols-2 gap-2">
                    <input v-model="mobilityForm.thoracic_rotation_left_deg" type="number" step="0.1" placeholder="Left rotation (deg)" class="mob-input" />
                    <input v-model="mobilityForm.thoracic_rotation_right_deg" type="number" step="0.1" placeholder="Right rotation (deg)" class="mob-input" />
                  </div>
                </div>

                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                  <button type="button" class="mob-test-title" @click="toggleMobilityHelp('hip')">
                    <span>Hip Mobility (25 pts)</span>
                    <span class="mob-test-help-cta">{{ mobilityHelpOpen === 'hip' ? 'Hide how to assess' : 'Click for how to assess' }}</span>
                  </button>
                  <div v-if="mobilityHelpOpen === 'hip'" class="mob-test-help">
                    90/90 Test: classify full, mild restriction, significant restriction, or unable. Internal Rotation: measure hip IR in degrees. Hip Flexion: classify above chest, chest level, below chest, or restricted.
                  </div>
                  <div class="grid grid-cols-2 gap-2">
                    <select v-model="mobilityForm.hip_9090_rating" class="mob-input mob-select col-span-2">
                      <option value="">90/90 test result</option>
                      <option value="full">Full 90/90 both sides</option>
                      <option value="mild">Mild restriction</option>
                      <option value="significant">Significant restriction</option>
                      <option value="unable">Unable</option>
                    </select>
                    <input v-model="mobilityForm.hip_internal_rotation_deg" type="number" step="0.1" placeholder="Hip internal rotation (deg)" class="mob-input col-span-2" />
                    <select v-model="mobilityForm.hip_flexion_rating" class="mob-input mob-select col-span-2">
                      <option value="">Hip flexion result</option>
                      <option value="above">Above chest</option>
                      <option value="chest">Chest level</option>
                      <option value="below">Below chest</option>
                      <option value="restricted">Significant restriction</option>
                    </select>
                  </div>
                </div>

                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                  <button type="button" class="mob-test-title" @click="toggleMobilityHelp('ankle_core_balance')">
                    <span>Ankle + Core + Balance (40 pts)</span>
                    <span class="mob-test-help-cta">{{ mobilityHelpOpen === 'ankle_core_balance' ? 'Hide how to assess' : 'Click for how to assess' }}</span>
                  </button>
                  <div v-if="mobilityHelpOpen === 'ankle_core_balance'" class="mob-test-help">
                    Ankle Knee-to-Wall: heel flat, record max distance in inches. Dead Bug: timed hold in seconds with neutral trunk. Single-Leg Balance: timed hold each side in seconds and score by weaker side.
                  </div>
                  <div class="grid grid-cols-2 gap-2">
                    <input v-model="mobilityForm.ankle_knee_to_wall_inches" type="number" step="0.1" placeholder="Knee-to-wall distance (in)" class="mob-input col-span-2" />
                    <input v-model="mobilityForm.dead_bug_hold_sec" type="number" step="0.1" placeholder="Dead bug hold (sec)" class="mob-input col-span-2" />
                    <input v-model="mobilityForm.single_leg_balance_left_sec" type="number" step="0.1" placeholder="Single-leg balance left (sec)" class="mob-input" />
                    <input v-model="mobilityForm.single_leg_balance_right_sec" type="number" step="0.1" placeholder="Single-leg balance right (sec)" class="mob-input" />
                  </div>
                </div>
              </div>

              <div class="mt-4 flex flex-wrap items-center gap-3">
                <button
                  type="button"
                  class="px-4 py-2 rounded-lg bg-[#C00000] hover:bg-red-700 text-sm font-black uppercase tracking-wide disabled:opacity-60"
                  :disabled="mobilitySaving || !mobilitySelectedPlayerId || !mobilityFormComplete"
                  @click="submitMobilityAssessment"
                >
                  {{ mobilitySaving ? 'Saving...' : 'Save Mobility Assessment' }}
                </button>
                <span v-if="mobilityMessage.text" class="text-sm" :class="mobilityMessage.type === 'success' ? 'text-green-300' : 'text-red-300'">{{ mobilityMessage.text }}</span>
              </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-5">
              <h3 class="text-xs font-black uppercase tracking-widest text-white/60 mb-3">Score + Baseline</h3>

              <div class="rounded-xl border border-white/10 bg-white/5 p-3 mb-3">
                <p class="text-[11px] uppercase tracking-widest text-white/45">Computed Baseball Mobility Score (BMS)</p>
                <p class="mt-1 text-4xl font-black" :style="{ color: scoreColor(computedMobility.score) }">{{ computedMobility.score }}</p>
                <p class="mt-1 text-sm font-bold text-white/80">Grade: {{ computedMobility.grade }}</p>
                <p class="text-xs text-white/45 mt-1">0–100 FMTRX BMS. Saved value enters player development baseline/trend.</p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-white/70">
                  <div>Shoulder: <strong>{{ computedMobility.parts.shoulder }}</strong>/20</div>
                  <div>Thoracic: <strong>{{ computedMobility.parts.thoracic }}</strong>/15</div>
                  <div>Hip: <strong>{{ computedMobility.parts.hip }}</strong>/25</div>
                  <div>Ankle: <strong>{{ computedMobility.parts.ankle }}</strong>/15</div>
                  <div>Core: <strong>{{ computedMobility.parts.core }}</strong>/10</div>
                  <div>Balance: <strong>{{ computedMobility.parts.balance }}</strong>/15</div>
                </div>
              </div>

              <div class="rounded-xl border border-white/10 bg-white/5 p-3 mb-3">
                <p class="text-[11px] uppercase tracking-widest text-white/45">Latest Baseline</p>
                <div v-if="mobilityHistoryLoading" class="text-sm text-white/40">Loading baseline...</div>
                <div v-else-if="latestMobilityRecord">
                  <p class="text-lg font-black text-white">{{ latestMobilityScore }}</p>
                  <p class="text-xs text-white/50">{{ formatDate(latestMobilityRecord.fitness_date || latestMobilityRecord.created_at) }}</p>
                  <p v-if="mobilityDelta != null" class="text-xs mt-1" :class="mobilityDelta >= 0 ? 'text-green-300' : 'text-red-300'">
                    {{ mobilityDelta >= 0 ? '+' : '' }}{{ mobilityDelta }} vs latest
                  </p>
                </div>
                <p v-else class="text-sm text-white/35">No prior mobility baseline found for this player.</p>
              </div>

              <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <p class="text-[11px] uppercase tracking-widest text-white/45 mb-2">Guide</p>
                <ul class="text-xs text-white/65 space-y-1 list-disc pl-4">
                  <li>Use First-time Assessment when no baseline exists.</li>
                  <li>Use Reassessment for follow-up checks to compare progress.</li>
                  <li>BMS uses 6 categories: Shoulder, Thoracic, Hip, Ankle, Core, Balance.</li>
                  <li>Record values consistently (same protocol) for valid trend comparison.</li>
                  <li>Saved score feeds player development tracking automatically.</li>
                </ul>
              </div>
            </div>
          </div>

        </div><!-- end quickstats tab -->

      </div>
    </div>
    <!-- Player Development Detail Modal -->
    <Teleport to="body">
      <Transition name="sheet">
        <div
          v-if="devDetailModal.visible"
          class="fixed inset-0 z-50"
          style="background: rgba(2, 8, 20, 0.88)"
          @click.self="closeDevPlayerDetail"
        >
          <div class="h-full w-full overflow-y-auto p-4 md:p-6 lg:p-8">
            <div class="dev-detail-modal max-w-6xl mx-auto rounded-2xl border border-white/20 bg-[#081226] shadow-2xl overflow-hidden">
              <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
                <div>
                  <h2 class="text-xl font-black text-white">Player Development Profile</h2>
                  <p class="text-white/70 text-sm mt-0.5">What this player is doing, trend direction, and next coaching action</p>
                </div>
                <button
                  class="px-3 py-1.5 rounded-lg bg-[#C00000]/20 hover:bg-[#C00000]/35 text-white text-xs font-black border border-[#C00000]/40"
                  @click="closeDevPlayerDetail"
                >Close</button>
              </div>

              <div v-if="devDetailModal.loading" class="p-8 text-center text-white/60">
                Loading player profile...
              </div>

              <div v-else-if="selectedDevPlayer" class="p-5 md:p-6 flex flex-col gap-5">
                <!-- Snapshot + score strip -->
                <div class="grid grid-cols-1 xl:grid-cols-[1.4fr_1fr] gap-5">
                  <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center justify-between mb-2">
                      <div class="text-[10px] uppercase tracking-widest text-white/40">Player Snapshot</div>
                      <button class="insight-btn" @click="openInsight('snapshot')">?</button>
                    </div>
                    <h3 class="text-2xl font-black text-white">{{ selectedDevPlayer.name }}</h3>
                    <p class="text-white/50 text-sm mt-1">
                      #{{ selectedDevPlayer.jersey ?? '—' }}
                      <span v-if="selectedDevCard?.physical?.hit_side"> • {{ selectedDevCard.physical.hit_side }}</span>
                      <span v-if="selectedDevCard?.physical?.throw_side"> / {{ selectedDevCard.physical.throw_side }}</span>
                      <span v-if="selectedDevCard?.profile?.level"> • {{ selectedDevCard.profile.level }}</span>
                      <span v-if="selectedDevCard?.physical?.born_date"> • DOB {{ formatDate(selectedDevCard.physical.born_date) }}</span>
                    </p>
                    <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                      <div class="rounded-lg bg-white/5 px-2.5 py-2">
                        <div class="text-white/35">Team</div>
                        <div class="text-white/80 font-bold truncate">{{ team?.name ?? '—' }}</div>
                      </div>
                      <div class="rounded-lg bg-white/5 px-2.5 py-2">
                        <div class="text-white/35">Height</div>
                        <div class="text-white/80 font-bold">{{ selectedDevCard?.physical?.height_ft ?? '—' }}'{{ selectedDevCard?.physical?.height_in ?? '—' }}"</div>
                      </div>
                      <div class="rounded-lg bg-white/5 px-2.5 py-2">
                        <div class="text-white/35">Bats/Throws</div>
                        <div class="text-white/80 font-bold">{{ selectedDevCard?.physical?.hit_side ?? '—' }}/{{ selectedDevCard?.physical?.throw_side ?? '—' }}</div>
                      </div>
                      <div class="rounded-lg bg-white/5 px-2.5 py-2">
                        <div class="text-white/35">Status</div>
                        <div class="text-white/80 font-bold">{{ playerStatusLabel }}</div>
                      </div>
                    </div>
                  </div>

                  <button
                    class="rounded-xl border border-white/10 bg-white/5 p-4 text-left hover:border-white/30 transition"
                    @click="openOverallDevelopmentBreakdown"
                  >
                    <div class="flex items-center justify-between mb-2">
                      <div class="text-[10px] uppercase tracking-widest text-white/40">Overall Development Score</div>
                      <span class="text-[11px] font-black text-[#FCA5A5]">View Breakdown</span>
                    </div>
                    <div class="flex items-end gap-3">
                      <div class="text-5xl font-black" :style="{ color: scoreColor(selectedDevPlayer.scores?.overall) }">
                        {{ selectedDevPlayer.scores?.overall ?? '—' }}
                      </div>
                      <div class="pb-2 text-sm font-bold" :class="playerOverallDelta != null && playerOverallDelta > 0 ? 'text-green-400' : playerOverallDelta != null && playerOverallDelta < 0 ? 'text-red-400' : 'text-white/40'">
                        {{ playerOverallDelta == null ? 'No previous baseline' : (playerOverallDelta > 0 ? `↑ +${playerOverallDelta}` : playerOverallDelta < 0 ? `↓ ${playerOverallDelta}` : '→ Flat') }}
                      </div>
                    </div>
                    <div class="text-white/50 text-xs mt-2">Current status: {{ playerStatusLabel }}</div>
                    <div class="mt-3 text-xs text-white/70">Recommended Next Session:</div>
                    <div class="mt-1 text-sm font-bold text-[#FCA5A5]">{{ playerBestAndNeeds.recommended }}</div>
                  </button>
                </div>

                <!-- Best / needs / trends -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                  <div class="rounded-xl border border-green-500/20 bg-green-500/10 p-4">
                    <div class="flex items-center justify-between">
                      <div class="text-[10px] uppercase tracking-widest text-green-300/70">Best Area</div>
                      <button class="insight-btn" @click="openInsight('best')">?</button>
                    </div>
                    <div class="text-sm font-black text-green-200 mt-1">{{ playerBestAndNeeds.bestTrait }}</div>
                  </div>
                  <div class="rounded-xl border border-red-500/20 bg-red-500/10 p-4">
                    <div class="flex items-center justify-between">
                      <div class="text-[10px] uppercase tracking-widest text-red-300/70">Needs Work</div>
                      <button class="insight-btn" @click="openInsight('needs')">?</button>
                    </div>
                    <div class="text-sm font-black text-red-200 mt-1">{{ playerBestAndNeeds.needsWork }}</div>
                  </div>
                  <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center justify-between">
                      <div class="text-[10px] uppercase tracking-widest text-white/40">Last 7/30 Day Trend</div>
                      <button class="insight-btn" @click="openInsight('trend')">?</button>
                    </div>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-1 mt-2">
                      <div v-for="tr in playerTrendRows" :key="tr.label" class="flex items-center justify-between text-xs">
                        <span class="text-white/60">{{ tr.label }}</span>
                        <span :class="tr.color" class="font-bold">{{ tr.text }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Performance summary cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                  <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center justify-between mb-2">
                      <div class="text-[10px] uppercase tracking-widest text-white/40">Hitting Overview</div>
                      <button class="insight-btn" @click="openInsight('hitting')">?</button>
                    </div>
                    <div class="text-sm text-white/80">BP {{ selectedDevPlayer.scores?.batting ?? '—' }} · Cage {{ selectedDevPlayer.scores?.cage ?? '—' }}</div>
                    <div class="text-xs text-white/45 mt-1">Max EV: {{ selectedDevStats?.top?.max_exit_velocity?.[0] ?? '—' }}</div>
                  </div>
                  <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center justify-between mb-2">
                      <div class="text-[10px] uppercase tracking-widest text-white/40">Pitching Overview</div>
                      <button class="insight-btn" @click="openInsight('pitching')">?</button>
                    </div>
                    <div class="text-sm text-white/80">Bullpen {{ selectedDevPlayer.scores?.bullpen ?? '—' }}</div>
                    <div class="text-xs text-white/45 mt-1">Top FB: {{ selectedDevStats?.top?.max_fast_ball?.[0] ?? '—' }} mph</div>
                  </div>
                  <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center justify-between mb-2">
                      <div class="text-[10px] uppercase tracking-widest text-white/40">Arm Care / Throwing</div>
                      <button class="insight-btn" @click="openInsight('armcare')">?</button>
                    </div>
                    <div class="text-xs text-white/70">Long Toss Max: {{ selectedDevStats?.top?.max_long_toss?.[0] ?? '—' }} ft</div>
                    <div class="text-xs text-white/70 mt-1">Weighted Ball Max: {{ selectedDevStats?.top?.max_weight_ball?.[0] ?? '—' }} mph</div>
                  </div>
                  <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center justify-between mb-2">
                      <div class="text-[10px] uppercase tracking-widest text-white/40">Strength Metrics</div>
                      <button class="insight-btn" @click="openInsight('strength')">?</button>
                    </div>
                    <div class="text-xs text-white/70 flex items-center justify-between">
                      <span>Weight: {{ selectedDevPlayer?.fitness?.body_weight ?? selectedDevCard?.fitness?.body_weight ?? '—' }}</span>
                      <span class="text-[#FCA5A5] font-bold">{{ fitnessStanding('body_weight') }}</span>
                    </div>
                    <div class="text-xs text-white/70 mt-1 flex items-center justify-between">
                      <span>Bench: {{ selectedDevPlayer?.fitness?.bench_press ?? selectedDevCard?.fitness?.bench_press ?? '—' }}</span>
                      <span class="text-[#FCA5A5] font-bold">{{ fitnessStanding('bench_press') }}</span>
                    </div>
                    <div class="text-xs text-white/70 mt-1 flex items-center justify-between">
                      <span>Front Squat: {{ selectedDevPlayer?.fitness?.front_squat ?? selectedDevCard?.fitness?.front_squat ?? '—' }}</span>
                      <span class="text-[#FCA5A5] font-bold">{{ fitnessStanding('front_squat') }}</span>
                    </div>
                    <div class="text-xs text-white/70 mt-1 flex items-center justify-between">
                      <span>Power Clean: {{ selectedDevPlayer?.fitness?.power_clean ?? selectedDevCard?.fitness?.power_clean ?? '—' }}</span>
                      <span class="text-[#FCA5A5] font-bold">{{ fitnessStanding('power_clean') }}</span>
                    </div>
                    <div class="text-xs text-white/45 mt-2">
                      Last update: {{ selectedDevPlayer?.fitness?.date ? formatDate(selectedDevPlayer.fitness.date) : (selectedDevCard?.fitness?.date ? formatDate(selectedDevCard.fitness.date) : '—') }}
                    </div>
                  </div>
                </div>

                <!-- Scripted BP scorecard + recent sessions -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                  <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center justify-between mb-2">
                      <div class="text-[10px] uppercase tracking-widest text-white/40">Scripted BP Scorecard</div>
                      <button class="insight-btn" @click="openInsight('scorecard')">?</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                      <div class="rounded-lg bg-white/5 p-2">BP Quality Score: <span class="font-bold text-white">{{ selectedDevPlayer.scores?.batting ?? '—' }}</span></div>
                      <div class="rounded-lg bg-white/5 p-2">Grade: <span class="font-bold text-white">{{ scoreGrade(selectedDevPlayer.scores?.batting) ?? '—' }}</span></div>
                      <div class="rounded-lg bg-white/5 p-2">Hard Contact %: <span class="font-bold text-white">{{ selectedDevStats?.top?.max_exit_velocity?.length ? 'Tracked' : '—' }}</span></div>
                      <div class="rounded-lg bg-white/5 p-2">Swing/Miss %: <span class="font-bold text-white">—</span></div>
                      <div class="rounded-lg bg-white/5 p-2">Avg EV: <span class="font-bold text-white">{{ selectedDevStats?.avg?.avg_exit_velocity ?? '—' }}</span></div>
                      <div class="rounded-lg bg-white/5 p-2">Max EV: <span class="font-bold text-white">{{ selectedDevStats?.top?.max_exit_velocity?.[0] ?? '—' }}</span></div>
                    </div>
                    <p class="text-xs text-white/45 mt-3">Feedback: Good progression indicators. Keep reinforcing {{ playerBestAndNeeds.recommended }}.</p>
                  </div>

                  <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center justify-between mb-2">
                      <div class="text-[10px] uppercase tracking-widest text-white/40">Development Timeline (Recent Sessions)</div>
                      <button class="insight-btn" @click="openInsight('timeline')">?</button>
                    </div>
                    <div v-if="!playerRecentSessions.length" class="text-xs text-white/40 py-6 text-center">No recent sessions linked to this player in the latest dashboard feed.</div>
                    <div v-else class="flex flex-col gap-1.5 max-h-52 overflow-y-auto pr-1">
                      <div v-for="s in playerRecentSessions" :key="s.id" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                        <div class="text-xs font-bold text-white/85">{{ sessionTypeColor[s._type]?.label ?? s._type }}</div>
                        <div class="text-[11px] text-white/45">{{ formatDate(s.updated_at ?? s.created_at) }}</div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Coach takeaway -->
                <div class="rounded-xl border border-[#C00000]/30 bg-[#C00000]/10 p-4">
                  <div class="flex items-center justify-between mb-1">
                    <div class="text-[10px] uppercase tracking-widest text-[#FCA5A5]">Coach Takeaway</div>
                    <button class="insight-btn" @click="openInsight('takeaway')">?</button>
                  </div>
                  <p class="text-sm text-white/90 leading-relaxed">{{ coachTakeaway }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Section Insight Modal -->
    <Teleport to="body">
      <Transition name="sheet">
        <div
          v-if="insightModal.visible"
          class="fixed inset-0 z-50 flex items-end justify-center"
          style="background: rgba(0,0,0,0.65)"
          @click.self="closeInsight"
        >
          <div class="w-full max-w-lg bg-[#0d1b33] rounded-t-3xl pt-6 pb-8 px-6 shadow-2xl border-t border-white/10">
            <div class="w-10 h-1 bg-white/20 rounded-full mx-auto mb-5"></div>
            <h2 class="text-xl font-black text-white">{{ insightModal.title }}</h2>
            <p class="text-white/70 text-sm mt-2">{{ insightModal.body }}</p>
            <ul class="mt-4 space-y-2 text-sm text-white/85">
              <li v-for="point in insightModal.bullets" :key="point" class="flex gap-2">
                <span class="text-[#FCA5A5]">•</span>
                <span>{{ point }}</span>
              </li>
            </ul>
            <button
              class="w-full mt-5 py-3.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-black text-sm transition"
              @click="closeInsight"
            >Got it</button>
          </div>
        </div>
      </Transition>
    </Teleport>

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

/* Player detail modal readability */
.dev-detail-modal {
  color: rgba(248, 250, 252, 0.98);
}

.dev-detail-modal .bg-white\/5 {
  background-color: rgba(30, 41, 59, 0.78) !important;
}

.dev-detail-modal .border-white\/10 {
  border-color: rgba(255, 255, 255, 0.22) !important;
}

.dev-detail-modal .text-white\/35,
.dev-detail-modal .text-white\/40,
.dev-detail-modal .text-white\/45,
.dev-detail-modal .text-white\/50,
.dev-detail-modal .text-white\/60,
.dev-detail-modal .text-white\/70 {
  color: rgba(226, 232, 240, 0.9) !important;
}

.dev-detail-modal .text-xs {
  font-size: 0.82rem;
}

.insight-btn {
  width: 22px;
  height: 22px;
  border-radius: 9999px;
  border: 1px solid rgba(255, 255, 255, 0.25);
  background: rgba(255, 255, 255, 0.06);
  color: #e2e8f0;
  font-size: 12px;
  font-weight: 800;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.insight-btn:hover {
  border-color: rgba(252, 165, 165, 0.7);
  color: #fecaca;
  background: rgba(192, 0, 0, 0.15);
}

.mob-input {
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.04);
  color: rgba(248, 250, 252, 0.95);
  padding: 0.45rem 0.6rem;
  font-size: 0.85rem;
  outline: none;
}

.mob-input:focus {
  border-color: rgba(192, 0, 0, 0.65);
}

.mob-input::placeholder {
  color: rgba(226, 232, 240, 0.45);
}

.mob-select {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  padding-right: 2rem;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5L10 12.5L15 7.5' stroke='%23E2E8F0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.65rem center;
  background-size: 12px;
}

.mob-select::-ms-expand {
  display: none;
}

.mobility-assessment p,
.mobility-assessment span,
.mobility-assessment label,
.mobility-assessment li,
.mobility-assessment button,
.mobility-assessment h2,
.mobility-assessment h3,
.mobility-assessment input,
.mobility-assessment select,
.mobility-assessment option {
  font-weight: 700;
}

.mob-test-title {
  width: 100%;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-size: 11px;
  color: rgba(248, 250, 252, 0.88);
}

.mob-test-help-cta {
  color: rgba(252, 165, 165, 0.95);
  font-size: 10px;
}

.mob-test-help {
  margin-bottom: 0.65rem;
  border: 1px solid rgba(192, 0, 0, 0.35);
  background: rgba(192, 0, 0, 0.12);
  border-radius: 0.5rem;
  padding: 0.45rem 0.6rem;
  font-size: 0.74rem;
  color: rgba(254, 226, 226, 0.98);
  line-height: 1.35;
}

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-thumb { background: #C00000; border-radius: 5px; }
::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 4px; }
</style>
