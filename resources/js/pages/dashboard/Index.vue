<script setup>
import { ref, computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import Layout from "../../layout/Layout.vue"
import { useUserStore } from "../../store/user";
import { usePlayerStore } from "../../store/players";
import { useTeamStore } from "../../store/team";
import { IndicatorChart } from '@/components/dashboard'
import DashboardSprayChart from '@/components/dashboard/DashboardSprayChart.vue'
import DevelopmentCard from '@/components/dashboard/DevelopmentCard.vue'
import VelocityZoneChart from '@/components/dashboard/VelocityZoneChart.vue'
import PitchHeatmapChart from '@/components/dashboard/PitchHeatmapChart.vue'
import PitchTypeStatsCard from '@/components/dashboard/PitchTypeStatsCard.vue'
import PlayerCompare from '@/components/dashboard/PlayerCompare.vue'
import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.png'
import useChart from '@/composables/useChart.js'
import useChartOptions from '@/composables/useChartOptions.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useRoute, useRouter } from 'vue-router'
import { computeStrengthAssessmentScore } from '@/features/development/lib/strengthAssessmentScore.js'
import { computeFmtrxAssessment, throwsPerDayOptions, pitchCountOptions, intensityOptions } from '@/features/development/lib/fmtrxAssessmentScore.js'
import AssessmentModal from '@/features/development/components/AssessmentModal.vue'
import PlayerAssessmentReport from '@/features/development/components/PlayerAssessmentReport.vue'
import { buildTeamInsight } from '@/features/development/lib/assessmentInsights.js'
import { resolveBornValue, toISODOB, formatDOB } from '@/utils/dob.js'
import StrengthStandardsCard from '@/features/development/components/StrengthStandardsCard.vue'
import CoachAssessmentPanel from '@/features/development/components/CoachAssessmentPanel.vue'

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
  return `dashboard-cache:v3:${activeTeamId.value}`
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

    if (typeof teamStore.setTeams === 'function') {
      teamStore.setTeams(teamsList)
    }

    if (!currentExists) {
      teamStore.setTeam(teamsList[0])
      return
    }

    const freshCurrentTeam = teamsList.find((t) =>
      getTeamIdCandidates(t).some((id) => activeIds.includes(id))
    )

    if (freshCurrentTeam) {
      teamStore.setTeam(freshCurrentTeam)
    }

    // If current team has no recent sessions, auto-pick the first team that does.
    const currentCount = await getTeamSessionCount(freshCurrentTeam ?? team.value)
    if (currentCount > 0) return

    for (const candidate of teamsList) {
      const candidateCount = await getTeamSessionCount(candidate)
      if (candidateCount > 0) {
        teamStore.setTeam(candidate)
        return
      }
    }

    const teamWithRoster = teamsList.find((candidate) => Number(candidate?.num_players ?? 0) > 0)
    if (teamWithRoster) {
      teamStore.setTeam(teamWithRoster)
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

const top10MetricValue = (item, tab) => {
  if (!tab) return null
  if (tab.value === 12) return toNumeric(item?.score)
  return toNumeric(item?.[tab.key])
}

const dedupeTop10Players = (rows, tab) => {
  const map = new Map()
  for (const row of (rows ?? [])) {
    const name = top10PlayerName(row)
    const key = normalizePlayerName(name)
    if (!key) continue

    const metricValue = top10MetricValue(row, tab)
    const current = map.get(key)

    if (!current || (metricValue ?? -Infinity) > (current._metricValue ?? -Infinity)) {
      map.set(key, {
        ...row,
        _metricValue: metricValue,
      })
    }
  }

  return [...map.values()]
    .sort((a, b) => (b?._metricValue ?? -Infinity) - (a?._metricValue ?? -Infinity))
    .slice(0, 10)
}

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
  const rows = (teamPlayerCards.value ?? [])
    .map((card) => ({
      name:
        card?.profile?.full_name
        ?? card?.name
        ?? card?.profile?.first_name
        ?? '—',
      avatar: card?.profile?.picture ?? null,
      score: toNumeric(card?.fmtrxx_strength_score),
    }))

  return dedupeTop10Players(rows, { value: 12, key: 'score' })
})

const top10Rows = computed(() => {
  if (top10Tab.value === 12) return topStrengthRows.value.slice(0, 10)
  return dedupeTop10Players(top10Data.value ?? [], activeTop10Tab.value)
})

const top10Modal = ref({ visible: false, loading: false, tab: null, rows: [] })

const closeTop10Modal = () => {
  top10Modal.value.visible = false
}

const loadTop10RowsForTab = async (tab, force = true) => {
  if (!tab) return []
  if (tab.value === 12) {
    await ensureTeamPlayerCards()
    return topStrengthRows.value.slice(0, 10)
  }

  if (!getActiveTeamIdCandidates().length) return []

  const { data } = await withTeamIdFallbackPost(
    (id) => 'table/' + id,
    () => ({ option: tab.value, range: top10Range.value })
  )

  const rows = dedupeTop10Players(data?.data?.all ?? [], tab)

  if (tab.value === top10Tab.value || force) {
    top10Data.value = rows
    writeDashboardCache({
      top10Data: top10Data.value,
      top10Tab: tab.value,
      top10Range: top10Range.value,
    })
  }

  return rows
}

const openTop10Modal = async (tab) => {
  if (!tab) return
  top10Tab.value = tab.value
  top10Modal.value = { visible: true, loading: true, tab, rows: [] }
  try {
    const rows = await loadTop10RowsForTab(tab, true)
    top10Modal.value = { visible: true, loading: false, tab, rows }
  } catch (e) {
    console.warn('openTop10Modal', e)
    top10Modal.value = { visible: true, loading: false, tab, rows: [] }
  }
}

const formatTop10MetricValue = (item, tab) => {
  const value = top10MetricValue(item, tab)
  if (value === null) return '—'
  return `${value}${tab?.suffix ?? ''}`
}

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

// ── Development Card carousel (scroll through players one card at a time) ──────
const devCarousel = ref(null)
const devCardIndex = ref(0)
const devScrollToIndex = (i) => {
  const el = devCarousel.value
  if (!el) return
  const clamped = Math.max(0, Math.min(i, devBoard.value.length - 1))
  const card = el.children?.[clamped]
  if (card) el.scrollTo({ left: card.offsetLeft - el.offsetLeft, behavior: 'smooth' })
}
const devPrev = () => devScrollToIndex(devCardIndex.value - 1)
const devNext = () => devScrollToIndex(devCardIndex.value + 1)
const onDevScroll = () => {
  const el = devCarousel.value
  if (!el || !el.children.length) return
  // The card whose left edge is closest to the container's left is the active one.
  const cardW = el.children[0].getBoundingClientRect().width + 12 // width + gap
  devCardIndex.value = Math.round(el.scrollLeft / cardW)
}

const statusConfig = {
  hot:        { label: '🔥 Hot',         color: 'text-orange-400',  bg: 'bg-orange-500/10',  border: 'border-orange-500/30' },
  improving:  { label: '🟢 Improving',   color: 'text-green-400',   bg: 'bg-green-500/10',   border: 'border-green-500/30' },
  steady:     { label: '🟡 Steady',      color: 'text-yellow-400',  bg: 'bg-yellow-500/10',  border: 'border-yellow-500/30' },
  needs_work: { label: '🔴 Needs Work',  color: 'text-red-400',     bg: 'bg-red-500/10',     border: 'border-red-500/30' },
  no_data:    { label: '⚪ No Data',     color: 'text-white/30',    bg: 'bg-white/5',        border: 'border-white/10' },
}

const trendIcon = (t) => t === 'up' ? '↑' : t === 'down' ? '↓' : '→'
const trendColor = (t) => t === 'up' ? 'text-green-400' : t === 'down' ? 'text-red-400' : 'text-white/30'

const devBoardPlayerInitials = (player) => {
  const name = player?.name ?? ''
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map(part => part[0]?.toUpperCase())
    .join('') || '—'
}

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
    top10Data.value = await loadTop10RowsForTab(activeTop10Tab.value, force)
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
const selectedDevLive = ref(null)
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
  const pidStr = String(pid)
  return (recentSessions.value ?? []).filter((s) => {
    const lineup = Array.isArray(s.lineup) ? s.lineup : []
    const lineupMatch = lineup.some((l) => {
      const cand = l?.user?.id ?? l?.user_id ?? l?.id ?? null
      return cand != null && String(cand) === pidStr
    })
    if (lineupMatch) return true

    const directCandidates = [
      s?.user_id,
      s?.player_id,
      s?.batter_id,
      s?.pitcher_id,
      s?.created_by,
      s?.profile?.id,
      s?.player?.id,
      s?.user?.id,
    ]

    return directCandidates.some((cand) => cand != null && String(cand) === pidStr)
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

const firstDefined = (...vals) => {
  for (const v of vals) {
    if (v !== undefined && v !== null && v !== '') return v
  }
  return null
}

const firstNumeric = (...vals) => {
  const toNumCandidate = (value) => {
    if (value === undefined || value === null || value === '') return null

    if (Array.isArray(value)) {
      for (const entry of value) {
        const n = toNumCandidate(entry)
        if (n !== null) return n
      }
      return null
    }

    if (typeof value === 'object') {
      const objCandidates = [
        value.value,
        value.max,
        value.avg,
        value.current,
        value.reps,
        value.score,
      ]
      for (const entry of objCandidates) {
        const n = toNumCandidate(entry)
        if (n !== null) return n
      }
      return null
    }

    const n = Number(value)
    return Number.isFinite(n) ? n : null
  }

  for (const v of vals) {
    const n = toNumCandidate(v)
    if (n !== null) return n
  }
  return null
}

const fmtDevReport = (value, suffix = '') => {
  const n = Number(value)
  if (!Number.isFinite(n)) return '—'
  return `${Number.isInteger(n) ? n : n.toFixed(1)}${suffix}`
}

const reportValue = (...vals) => firstNumeric(...vals)

const buildDevReport = ({ title, subtitle, suffix, max, rows, lineSeries, tiles }) => {
  const cleanRows = (rows || []).filter((row) => Number.isFinite(Number(row.value)) && Number(row.value) > 0)
  if (!cleanRows.length && !(tiles || []).some((tile) => tile.value && tile.value !== '—')) return null
  return {
    title,
    subtitle,
    suffix,
    max: Math.max(Number(max || 0), ...cleanRows.map((row) => Number(row.value)), 1),
    rows: cleanRows,
    lineSeries: lineSeries || [{ key: 'value', label: 'Value', color: '#ff2d55' }],
    tiles: tiles || [],
  }
}

const selectedEVHomeReport = computed(() => {
  const stats = selectedDevStats.value ?? {}
  const avgStats = stats.avg ?? {}
  const topStats = stats.top ?? {}
  const live = selectedDevLive.value?.current ?? {}
  const avgEV = reportValue(avgStats.avg_exit_velocity, avgStats.average_exit_velocity, live.avg_exit_velocity)
  const topEV = reportValue(topStats.max_exit_velocity, stats.max?.exit_velo, live.max_exit_velocity, live.exit_velo)
  const ld = reportValue(avgStats.ld_avg_ev, avgStats.line_drive_avg_ev, avgStats.avg_ld_exit_velocity, live.ld_avg_ev)
  const gb = reportValue(avgStats.gb_avg_ev, avgStats.ground_ball_avg_ev, avgStats.avg_gb_exit_velocity, live.gb_avg_ev)
  const fb = reportValue(avgStats.fb_avg_ev, avgStats.fly_ball_avg_ev, avgStats.avg_fb_exit_velocity, live.fb_avg_ev)
  const hasTrajectory = [ld, gb, fb].some((v) => v != null)
  const rows = hasTrajectory
    ? [
      { label: 'LD avg', shortLabel: 'LD', value: ld, color: '#37D67A' },
      { label: 'GB avg', shortLabel: 'GB', value: gb, color: '#34A7FF' },
      { label: 'FB avg', shortLabel: 'FB', value: fb, color: '#F7D774' },
    ]
    : [
      { label: 'Avg EV', shortLabel: 'Avg', value: avgEV, color: '#37D67A' },
      { label: 'Top EV', shortLabel: 'Top', value: topEV, color: '#34A7FF' },
    ]
  return buildDevReport({
    title: hasTrajectory ? 'Exit Velocity by Trajectory' : 'Exit Velocity Report',
    subtitle: 'All player stats',
    suffix: ' mph',
    max: Math.max(Number(topEV || 0), 100),
    rows,
    lineSeries: [{ key: 'value', label: hasTrajectory ? 'Avg EV' : 'EV', color: '#ff2d55' }],
    tiles: [
      { label: 'Avg EV', value: fmtDevReport(avgEV, ' mph'), sub: 'All swings' },
      { label: 'Top EV', value: fmtDevReport(topEV, ' mph'), sub: 'Best recorded' },
      { label: 'EV Score', value: fmtDevReport(selectedDevPlayer.value?.scores?.ev), sub: scoreGrade(selectedDevPlayer.value?.scores?.ev) || '' },
      { label: 'Line Drive EV', value: fmtDevReport(ld, ' mph'), sub: hasTrajectory ? 'Trajectory split' : '' },
    ],
  })
})

const selectedLongTossHomeReport = computed(() => {
  const stats = selectedDevStats.value ?? {}
  const avgStats = stats.avg ?? {}
  const topStats = stats.top ?? {}
  const live = selectedDevLive.value?.current ?? {}
  const topDist = reportValue(topStats.max_long_toss, live.max_long_toss)
  const avgDist = reportValue(avgStats.avg_long_toss, avgStats.average_long_toss, live.avg_long_toss)
  const hop0 = reportValue(avgStats.long_toss_hop0_avg, avgStats.hop0_avg, avgStats.zero_hop_avg, live.long_toss_hop0_avg)
  const hop1 = reportValue(avgStats.long_toss_hop1_avg, avgStats.hop1_avg, avgStats.one_hop_avg, live.long_toss_hop1_avg)
  const hop2 = reportValue(avgStats.long_toss_hop2_avg, avgStats.hop2_avg, avgStats.two_hop_avg, live.long_toss_hop2_avg)
  const hop3 = reportValue(avgStats.long_toss_hop3_avg, avgStats.hop3_avg, avgStats.three_hop_avg, live.long_toss_hop3_avg)
  const hasHops = [hop0, hop1, hop2, hop3].some((v) => v != null)
  const rows = hasHops
    ? [
      { label: '0 hops avg', shortLabel: '0', value: hop0, color: '#37D67A' },
      { label: '1 hop avg', shortLabel: '1', value: hop1, color: '#34A7FF' },
      { label: '2 hops avg', shortLabel: '2', value: hop2, color: '#F7D774' },
      { label: '3 hops avg', shortLabel: '3', value: hop3, color: '#ff2d55' },
    ]
    : [
      { label: 'Avg Distance', shortLabel: 'Avg', value: avgDist, color: '#37D67A' },
      { label: 'Top Distance', shortLabel: 'Top', value: topDist, color: '#34A7FF' },
    ]
  return buildDevReport({
    title: hasHops ? 'Long Toss Distance by Hops' : 'Long Toss Report',
    subtitle: 'All player stats',
    suffix: ' ft',
    max: Math.max(Number(topDist || 0), 300),
    rows,
    lineSeries: [{ key: 'value', label: hasHops ? 'Avg Distance' : 'Distance', color: '#37D67A' }],
    tiles: [
      { label: 'Top Distance', value: fmtDevReport(topDist, ' ft'), sub: 'Best throw' },
      { label: 'Avg Distance', value: fmtDevReport(avgDist, ' ft'), sub: 'All throws' },
      { label: 'LTS', value: fmtDevReport(selectedDevPlayer.value?.scores?.lt), sub: scoreGrade(selectedDevPlayer.value?.scores?.lt) || '' },
      { label: '0 Hop Avg', value: fmtDevReport(hop0, ' ft'), sub: hasHops ? 'Carry split' : '' },
    ],
  })
})

const selectedWeightedHomeReport = computed(() => {
  const stats = selectedDevStats.value ?? {}
  const avgStats = stats.avg ?? {}
  const topStats = stats.top ?? {}
  const live = selectedDevLive.value?.current ?? {}
  const topVelo = reportValue(topStats.max_weight_ball, live.max_weight_ball)
  const avgVelo = reportValue(avgStats.avg_weight_ball, avgStats.average_weight_ball, live.avg_weight_ball)
  const weights = [3, 4, 5, 6, 7]
  const byWeight = weights.map((weight) => ({
    weight,
    avg: reportValue(
      avgStats[`weighted_${weight}oz_avg`],
      avgStats[`weight_${weight}_avg`],
      avgStats[`avg_${weight}oz_weighted_ball`],
      live[`weighted_${weight}oz_avg`]
    ),
    top: reportValue(
      topStats[`weighted_${weight}oz_top`],
      topStats[`weight_${weight}_top`],
      live[`weighted_${weight}oz_top`]
    ),
  }))
  const hasWeights = byWeight.some((row) => row.avg != null)
  const five = byWeight.find((row) => row.weight === 5)
  const base = five?.avg || avgVelo
  const multipliers = { 3: 1.04, 4: 1.02, 5: 1, 6: 0.97, 7: 0.94 }
  const rows = hasWeights
    ? byWeight.map((row) => ({
      label: `${row.weight} oz avg`,
      shortLabel: `${row.weight} oz`,
      value: row.avg,
      topValue: row.top,
      expected: base && multipliers[row.weight] ? base * multipliers[row.weight] : null,
      color: row.weight < 5 ? '#34A7FF' : row.weight === 5 ? '#37D67A' : '#F7D774',
    }))
    : [
      { label: 'Avg Velo', shortLabel: 'Avg', value: avgVelo, color: '#37D67A' },
      { label: 'Top Velo', shortLabel: 'Top', value: topVelo, color: '#34A7FF' },
    ]
  return buildDevReport({
    title: hasWeights ? 'Weighted Ball Velocity Curve' : 'Weighted Ball Report',
    subtitle: 'All player stats',
    suffix: ' mph',
    max: Math.max(Number(topVelo || 0), Number(base || 0) * 1.06, 100),
    rows,
    lineSeries: hasWeights
      ? [
        { key: 'value', label: 'Avg', color: '#37D67A' },
        { key: 'topValue', label: 'Top', color: '#34A7FF' },
        { key: 'expected', label: 'Expected', color: '#FFFFFF', dashed: true },
      ]
      : [{ key: 'value', label: 'Velo', color: '#37D67A' }],
    tiles: [
      { label: 'Top Velo', value: fmtDevReport(topVelo, ' mph'), sub: 'Best recorded' },
      { label: 'Avg Velo', value: fmtDevReport(avgVelo, ' mph'), sub: 'All throws' },
      { label: 'WBS', value: fmtDevReport(selectedDevPlayer.value?.scores?.wb), sub: scoreGrade(selectedDevPlayer.value?.scores?.wb) || '' },
      { label: '5 oz Avg', value: fmtDevReport(five?.avg, ' mph'), sub: hasWeights ? 'Regulation ball' : '' },
    ],
  })
})

const selectedTrainingHomeReports = computed(() => [
  selectedEVHomeReport.value,
  selectedLongTossHomeReport.value,
  selectedWeightedHomeReport.value,
].filter(Boolean))

const homeReportRows = (report) => (report?.rows || []).filter((row) => Number.isFinite(Number(row.value)) && Number(row.value) > 0)
const homeLineRows = (report) => {
  const series = homeLineSeries(report)
  return (report?.rows || []).filter((row) => series.some((item) => Number.isFinite(Number(row[item.key])) && Number(row[item.key]) > 0))
}
const homeLineSeries = (report) => {
  const rows = report?.rows || []
  return (report?.lineSeries || []).filter((item) => rows.some((row) => Number.isFinite(Number(row[item.key])) && Number(row[item.key]) > 0))
}
const homeLineValues = (report) => {
  const rows = report?.rows || []
  return homeLineSeries(report)
    .flatMap((item) => rows.map((row) => Number(row[item.key])))
    .filter((value) => Number.isFinite(value) && value > 0)
}
const homeLineRange = (report) => {
  const values = homeLineValues(report)
  const max = Math.max(Number(report?.max || 0), ...values, 1)
  const rawMin = values.length ? Math.min(...values) : 0
  const min = Math.max(0, rawMin - Math.max(5, (max - rawMin) * 0.2))
  return { min, max, span: Math.max(1, max - min) }
}
const homeLineX = (report, index) => {
  const rows = homeLineRows(report)
  const left = 32
  const right = 14
  const width = 320
  if (rows.length <= 1) return left
  return left + (index / (rows.length - 1)) * (width - left - right)
}
const homeLineY = (report, value) => {
  const top = 14
  const bottom = 28
  const height = 150
  const range = homeLineRange(report)
  return top + (1 - ((Number(value) - range.min) / range.span)) * (height - top - bottom)
}
const homeLinePath = (report, series) => homeLineRows(report)
  .map((row, index) => ({ x: homeLineX(report, index), y: homeLineY(report, row[series.key]), value: Number(row[series.key]) }))
  .filter((point) => Number.isFinite(point.value) && point.value > 0)
  .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(1)} ${point.y.toFixed(1)}`)
  .join(' ')

const strengthChartValues = computed(() => {
  const maxStats = selectedDevStats.value?.max ?? {}
  const liveCurrent = selectedDevLive.value?.current ?? {}
  const playerFitness = selectedDevPlayer.value?.fitness ?? {}
  const cardFitness = selectedDevCard.value?.fitness ?? {}

  const pullUps = firstNumeric(
    maxStats.pull_ups,
    maxStats.pullups,
    maxStats.pull_up,
    maxStats.pullUp,
    liveCurrent.pull_ups,
    liveCurrent.pullups,
    liveCurrent.pull_up,
    liveCurrent.pullUp,
    playerFitness.pull_ups,
    playerFitness.pullups,
    playerFitness.pull_up,
    playerFitness.pullUp,
    cardFitness.pull_ups,
    cardFitness.pullups,
    cardFitness.pull_up,
    cardFitness.pullUp,
  )

  const pushUps = firstNumeric(
    maxStats.push_ups,
    maxStats.pushups,
    maxStats.push_up,
    maxStats.pushUp,
    liveCurrent.push_ups,
    liveCurrent.pushups,
    liveCurrent.push_up,
    liveCurrent.pushUp,
    playerFitness.push_ups,
    playerFitness.pushups,
    playerFitness.push_up,
    playerFitness.pushUp,
    cardFitness.push_ups,
    cardFitness.pushups,
    cardFitness.push_up,
    cardFitness.pushUp,
  )

  return {
    bodyWeight: firstNumeric(
      maxStats.body_weight,
      maxStats.weight,
      liveCurrent.body_weight,
      liveCurrent.weight,
      playerFitness.body_weight,
      playerFitness.weight,
      cardFitness.body_weight,
      cardFitness.weight,
    ),
    benchPress: firstNumeric(
      maxStats.bench_press,
      liveCurrent.bench_press,
      playerFitness.bench_press,
      cardFitness.bench_press,
    ),
    frontSquat: firstNumeric(
      maxStats.front_squat,
      liveCurrent.front_squat,
      playerFitness.front_squat,
      cardFitness.front_squat,
    ),
    pullUps,
    pushUps,
  }
})

const hasAnyValue = (obj) => {
  if (!obj || typeof obj !== 'object') return false
  return Object.values(obj).some((v) => {
    if (v === null || v === undefined || v === '') return false
    if (Array.isArray(v)) return v.length > 0
    if (typeof v === 'object') return hasAnyValue(v)
    return true
  })
}

const mergePreferDefined = (...sources) => {
  const out = {}
  for (const src of sources) {
    if (!src || typeof src !== 'object') continue
    for (const [k, v] of Object.entries(src)) {
      if (v !== undefined && v !== null && v !== '') out[k] = v
    }
  }
  return out
}

const withOneDecimal = (v) => {
  const n = Number(v)
  return Number.isFinite(n) ? Math.round(n * 10) / 10 : null
}

const normalizeDevScoresFromLive = (current = {}) => ({
  batting: firstDefined(current.bp_score, current.batting_score, current.fps),
  bullpen: firstDefined(current.bullpen_score, current.bps),
  cage: firstDefined(current.cage_score, current.fcs),
  ev: firstDefined(current.ev_score, current.evs, current.avg_exit_velocity),
  overall: firstDefined(
    current.development_index,
    current.overall,
    withOneDecimal(avg([current.bp_score, current.bullpen_score, current.cage_score, current.ev_score, current.evs].filter((x) => Number.isFinite(Number(x)))))
  ),
})

const makeCardFromLive = (livePlayer, current) => {
  if (!livePlayer && !current) return null
  const heightFt = Number.isFinite(Number(livePlayer?.height_ft)) ? Number(livePlayer.height_ft) : null
  const heightIn = Number.isFinite(Number(livePlayer?.height_in)) ? Number(livePlayer.height_in) : null
  const bornDate = toISODOB(resolveBornValue(livePlayer)) || null

  return {
    id: livePlayer?.id ?? null,
    profile: {
      first_name: livePlayer?.first_name ?? null,
      last_name: livePlayer?.last_name ?? null,
      full_name: livePlayer?.name ?? null,
      picture: livePlayer?.picture ?? null,
      level: livePlayer?.level ?? null,
    },
    physical: {
      height_ft: heightFt,
      height_in: heightIn,
      born_date: bornDate,
      hit_side: livePlayer?.bats ?? null,
      throw_side: livePlayer?.throws ?? null,
      jersey_number: livePlayer?.jersey ?? null,
    },
    fitness: {
      body_weight: current?.body_weight ?? livePlayer?.weight ?? null,
      bench_press: current?.bench_press ?? null,
      front_squat: current?.front_squat ?? null,
      power_clean: current?.power_clean ?? null,
      hand_strength: current?.hand_strength ?? null,
      push_ups: current?.push_ups ?? null,
      pull_ups: current?.pull_ups ?? null,
      vertical_jump: current?.vertical_jump ?? null,
      broad_jump: current?.broad_jump ?? null,
      med_ball_rotational_throw: current?.med_ball_rotational_throw ?? null,
      sprint_10yd: current?.sprint_10yd ?? null,
      exit_velo: current?.exit_velo ?? null,
      bat_speed: current?.bat_speed ?? null,
      throwing_velo: current?.throwing_velo ?? null,
      pitch_velo: current?.pitch_velo ?? null,
      date: current?.date ?? null,
    },
  }
}

const makeStatsFromLive = (current = {}) => {
  if (!current || Object.keys(current).length === 0) return null
  return {
    top: {
      max_exit_velocity: current.max_exit_velocity != null ? [current.max_exit_velocity] : [],
      max_fast_ball: current.max_fb_velocity != null ? [current.max_fb_velocity] : [],
      max_long_toss: current.max_long_toss != null ? [current.max_long_toss] : [],
      max_weight_ball: current.max_weight_ball != null ? [current.max_weight_ball] : [],
    },
    avg: {
      avg_exit_velocity: current.avg_exit_velocity ?? null,
      avg_pitch_velocity: current.avg_pitch_velocity ?? null,
    },
    max: {
      body_weight: current.body_weight ?? null,
      bench_press: current.bench_press ?? null,
      front_squat: current.front_squat ?? null,
      pull_ups: current.pull_ups ?? null,
      push_ups: current.push_ups ?? null,
      hand_strength: current.hand_strength ?? null,
      vertical_jump: current.vertical_jump ?? null,
      broad_jump: current.broad_jump ?? null,
      med_ball_rotational_throw: current.med_ball_rotational_throw ?? null,
      sprint_10yd: current.sprint_10yd ?? null,
      exit_velo: current.exit_velo ?? null,
      bat_speed: current.bat_speed ?? null,
      throwing_velo: current.throwing_velo ?? null,
      pitch_velo: current.pitch_velo ?? null,
    },
  }
}

const openSharedPlayerDevelopmentProfile = async (player) => {
  const playerId = player?.id
  if (!playerId) return

  await router.push({
    name: 'development.player',
    params: { playerId },
    query: {
      teamId: activeTeamId.value || undefined,
      playerName: player?.name || undefined,
    },
  })
}

const openDevPlayerDetail = async (player) => {
  selectedDevPlayer.value = player
  selectedDevCard.value = null
  selectedDevStats.value = null
  selectedDevLive.value = null
  devDetailModal.value = { visible: true, loading: true }
  try {
    await ensureTeamPlayerCards()
    const playerId = String(player?.id ?? '')
    selectedDevCard.value = (teamPlayerCards.value ?? []).find((c) => String(c?.id) === playerId) ?? null

    const isPlayerUser = String(user.userData?.type || '').toLowerCase() === 'player'
    const teamLikeForDev = getActiveTeamIdCandidates().length
      ? team.value
      : (user.userData?.team || null)

    const devPathBuilder = (id) =>
      isPlayerUser
        ? `player/development/teams/${id}/players/${player.id}?days=60`
        : `coach/development/teams/${id}/players/${player.id}?days=60`

    const [statsRes, devRes] = await Promise.allSettled([
      axiosGet('coach/statistics/' + player.id).catch(() => null),
      teamLikeForDev
        ? withTeamIdFallbackGet(devPathBuilder, teamLikeForDev).catch(() => null)
        : Promise.resolve(null),
    ])

    const statsData = statsRes.status === 'fulfilled' ? (statsRes.value?.data?.data ?? null) : null
    const devData = devRes.status === 'fulfilled' ? (devRes.value?.data?.data ?? null) : null
    selectedDevLive.value = devData

    const livePlayer = devData?.player ?? null
    const liveCurrent = devData?.current ?? null

    if (!selectedDevCard.value) {
      selectedDevCard.value = makeCardFromLive(livePlayer, liveCurrent)
    }

    const liveScoreMap = normalizeDevScoresFromLive(liveCurrent ?? {})
    const liveFitness = {
      body_weight: liveCurrent?.body_weight ?? null,
      bench_press: liveCurrent?.bench_press ?? null,
      front_squat: liveCurrent?.front_squat ?? null,
      power_clean: liveCurrent?.power_clean ?? null,
      hand_strength: liveCurrent?.hand_strength ?? null,
      pull_ups: liveCurrent?.pull_ups ?? null,
      push_ups: liveCurrent?.push_ups ?? null,
      vertical_jump: liveCurrent?.vertical_jump ?? null,
      broad_jump: liveCurrent?.broad_jump ?? null,
      med_ball_rotational_throw: liveCurrent?.med_ball_rotational_throw ?? null,
      sprint_10yd: liveCurrent?.sprint_10yd ?? null,
      exit_velo: liveCurrent?.exit_velo ?? null,
      bat_speed: liveCurrent?.bat_speed ?? null,
      throwing_velo: liveCurrent?.throwing_velo ?? null,
      pitch_velo: liveCurrent?.pitch_velo ?? null,
      date: liveCurrent?.date ?? null,
    }

    selectedDevPlayer.value = {
      ...player,
      id: player?.id ?? livePlayer?.id ?? playerId,
      name: firstDefined(player?.name, livePlayer?.name, selectedDevCard.value?.profile?.full_name, 'Player'),
      jersey: firstDefined(player?.jersey, livePlayer?.jersey, selectedDevCard.value?.physical?.jersey_number),
      scores: mergePreferDefined(player?.scores ?? {}, liveScoreMap),
      prev_scores: {
        ...(player?.prev_scores ?? {}),
      },
      fitness: mergePreferDefined(player?.fitness ?? {}, selectedDevCard.value?.fitness ?? {}, liveFitness),
    }

    selectedDevStats.value = hasAnyValue(statsData) ? statsData : makeStatsFromLive(liveCurrent ?? {})
  } catch (e) {
    console.warn('openDevPlayerDetail', e)
  } finally {
    devDetailModal.value.loading = false
  }
}

const closeDevPlayerDetail = () => {
  if (devOnlyMode.value) {
    router.replace({ name: 'playerDashboard' })
    return
  }
  devDetailModal.value.visible = false
}

const devPlayerQueryId = computed(() => String(route.query?.devPlayerId || '').trim())
const devOnlyMode = computed(() => String(route.query?.devOnly || '') === '1')
const autoOpenedDevPlayerId = ref('')
const legacyDevOnlyRedirected = ref('')

const redirectLegacyDevOnlyProfile = async () => {
  const queryId = devPlayerQueryId.value
  if (!devOnlyMode.value || !queryId) return false
  if (legacyDevOnlyRedirected.value === queryId) return true

  legacyDevOnlyRedirected.value = queryId
  await router.replace({
    name: 'development.player',
    params: { playerId: queryId },
    query: {
      teamId: activeTeamId.value || route.query?.teamId || undefined,
      playerName: String(route.query?.playerName || '').trim() || undefined,
    },
  })
  return true
}

const tryOpenDevPlayerFromQuery = async () => {
  const queryId = devPlayerQueryId.value
  if (!queryId) return
  if (await redirectLegacyDevOnlyProfile()) return
  if (autoOpenedDevPlayerId.value === queryId) return

  const fromBoard = (devBoard.value || []).find((p) => String(p?.id) === queryId)
  const fallbackPlayer = {
    id: queryId,
    name: String(route.query?.playerName || 'Player').trim() || 'Player',
    status: 'no_data',
    scores: {},
    prev_scores: {},
  }

  autoOpenedDevPlayerId.value = queryId
  if (!devOnlyMode.value && dashTab.value !== 'overview') {
    dashTab.value = 'overview'
  }
  await openDevPlayerDetail(fromBoard || fallbackPlayer)
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
    const payload = res?.data?.data
    const rawPlayers = Array.isArray(payload)
      ? payload
      : Array.isArray(payload?.data)
        ? payload.data
        : []

    mobilityPlayers.value = rawPlayers
      .map((p) => {
        const id = p?.id ?? p?.user_id ?? null
        return {
          id,
          name: p?.name?.full || `${p?.name?.first || ''} ${p?.name?.last || ''}`.trim() || `Player #${id}`,
        }
      })
      .filter((p) => p.id)
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

// ── Strength Assessment ──────────────────────────────────────────────
const strengthPlayers = ref([])
const strengthPlayersLoading = ref(false)
const strengthSaving = ref(false)
const strengthHistoryLoading = ref(false)
const strengthHistory = ref([])
const strengthAssessmentType = ref('first_time')
const strengthSelectedPlayerId = ref('')
const strengthMessage = ref({ type: '', text: '' })
const strengthHelpOpen = ref('')

const toggleStrengthHelp = (section) => {
  strengthHelpOpen.value = strengthHelpOpen.value === section ? '' : section
}

const strengthForm = ref({
  fitness_date: new Date().toISOString().slice(0, 10),
  body_weight_lbs: '',
  front_squat_lbs: '',
  back_squat_lbs: '',
  bench_press_lbs: '',
  dead_lift_lbs: '',
  power_clean_lbs: '',
  hand_strength_lbs: '',
  push_ups: '',
  pull_ups: '',
  vertical_jump_inches: '',
  broad_jump_inches: '',
  med_ball_rotational_throw_ft: '',
  sprint_10yd_sec: '',
  exit_velocity_mph: '',
  bat_speed_mph: '',
  throwing_velo_mph: '',
  pitch_velo_mph: '',
  yd_40_dash_sec: '',
  yd_60_dash_sec: '',
  sleep_hours: '',
  sleep_quality_1_to_5: '',
  recovery_score: '',
  // Mobility (0-5 each)
  shoulder_mobility: '',
  hip_mobility: '',
  ankle_mobility: '',
  hamstring_mobility: '',
  t_spine_rotation: '',
  overhead_squat: '',
  single_leg_balance: '',
  // Throwing workload
  throwing_days_per_week: '',
  throws_per_day_range: '',
  weekly_pitch_count_range: '',
  bullpens_per_week: '',
  long_toss_sessions_per_week: '',
  weighted_ball_sessions_per_week: '',
  throwing_intensity: '',
  // Arm health
  arm_pain: '',
  arm_soreness: '',
  arm_care_completion: '',
  // Hitting
  max_exit_velo: '',
  avg_exit_velo: '',
  contact_percentage: '',
  hard_hit_percentage: '',
  whiff_percentage: '',
  // Pitching
  fastball_velocity: '',
  strike_percentage: '',
  command_percentage: '',
})

const latestStrengthMobilityScore = computed(() => {
  const s = Number(latestStrengthRecord.value?.mobility_score)
  return Number.isFinite(s) ? s : null
})

const computedStrength = computed(() => computeStrengthAssessmentScore({
  ...strengthForm.value,
  mobility_score: latestStrengthMobilityScore.value,
}))

// Full FMTRX baseline breakdown (mirrors the mobile app's assessment).
const computedFmtrx = computed(() => computeFmtrxAssessment({
  ...strengthForm.value,
  mobility_score: latestStrengthMobilityScore.value,
}))

const assessmentModalOpen = ref(false)
const assessmentReportKey = ref(0) // bump to force the reports panel to reload

// ── Coach Dashboard AI practice recommendation ───────────────────────────────
const teamInsight = ref(null)
const teamInsightEdited = ref('')
const teamInsightEditing = ref(false)
const teamInsightKey = () => `fmtrx_team_insight_${activeTeamId.value || 'x'}`
const teamInsightText = computed(() => teamInsightEdited.value || teamInsight.value?.sentence || '')
const fetchTeamInsight = async () => {
  const teamId = activeTeamId.value
  if (!teamId) { teamInsight.value = null; return }
  try {
    const res = await axiosGet(`assessments/team/${teamId}?all=1`)
    const rows = res?.data?.data
    teamInsight.value = buildTeamInsight(Array.isArray(rows) ? rows : [])
  } catch { teamInsight.value = null }
  // Coach override is team-shared (server), with local cache as offline fallback.
  let override = ''
  try {
    const r = await axiosGet(`coach/teams/${teamId}/practice-insight`)
    override = r?.data?.data?.practice_insight || ''
    try { localStorage.setItem(teamInsightKey(), override) } catch (_) { /* noop */ }
  } catch {
    try { override = localStorage.getItem(teamInsightKey()) || '' } catch (_) { override = '' }
  }
  teamInsightEdited.value = override
  teamInsightEditing.value = false
}
const saveTeamInsight = async () => {
  const teamId = activeTeamId.value
  try { localStorage.setItem(teamInsightKey(), teamInsightEdited.value) } catch (_) { /* noop */ }
  try { await axiosPost(`coach/teams/${teamId}/practice-insight`, { practice_insight: teamInsightEdited.value }) } catch (_) { /* offline — local cache kept */ }
  teamInsightEditing.value = false
}
const resetTeamInsight = async () => {
  const teamId = activeTeamId.value
  try { localStorage.removeItem(teamInsightKey()) } catch (_) { /* noop */ }
  teamInsightEdited.value = ''
  try { await axiosPost(`coach/teams/${teamId}/practice-insight`, { practice_insight: null }) } catch (_) { /* offline */ }
  teamInsightEditing.value = false
}
const startEditTeamInsight = () => {
  teamInsightEdited.value = teamInsightText.value
  teamInsightEditing.value = true
}
const selectedStrengthPlayerName = computed(() => {
  const list = Array.isArray(strengthPlayers.value) ? strengthPlayers.value : []
  const p = list.find(x => String(x.id) === String(strengthSelectedPlayerId.value))
  return p?.name || ''
})
const onAssessmentSaved = (payload) => {
  assessmentModalOpen.value = false
  const name = selectedStrengthPlayerName.value || 'Player'
  strengthMessage.value = { type: 'success', text: `Assessment baseline saved for ${name}.` }
  // Refresh any cached strength/dev data + the reports panel so the new baseline shows up.
  fetchStrengthHistory().catch(() => {})
  fetchDevBoard().catch(() => {})
  assessmentReportKey.value++
  void payload
}

const fmtrxSectionRows = computed(() => {
  const f = computedFmtrx.value
  return [
    { label: 'Athletic', value: f.athletic },
    { label: 'Strength', value: f.strength },
    { label: 'Power', value: f.power },
    { label: 'Speed', value: f.speed },
    { label: 'Baseball', value: f.baseball },
    { label: 'Mobility', value: f.mobility },
    { label: 'Hitting', value: f.hitting },
    { label: 'Pitching', value: f.pitching },
    { label: 'Arm Health', value: f.armHealth },
  ]
})

const strengthFormComplete = computed(() => {
  const f = strengthForm.value
  return (
    !!f.fitness_date &&
    f.body_weight_lbs !== '' &&
    f.front_squat_lbs !== '' &&
    f.back_squat_lbs !== '' &&
    f.bench_press_lbs !== '' &&
    f.dead_lift_lbs !== '' &&
    f.power_clean_lbs !== '' &&
    f.hand_strength_lbs !== '' &&
    f.push_ups !== '' &&
    f.pull_ups !== '' &&
    f.vertical_jump_inches !== '' &&
    f.broad_jump_inches !== '' &&
    f.med_ball_rotational_throw_ft !== '' &&
    f.sprint_10yd_sec !== '' &&
    f.exit_velocity_mph !== '' &&
    f.bat_speed_mph !== '' &&
    f.throwing_velo_mph !== '' &&
    f.pitch_velo_mph !== '' &&
    f.yd_40_dash_sec !== '' &&
    f.yd_60_dash_sec !== '' &&
    f.sleep_hours !== '' &&
    f.sleep_quality_1_to_5 !== '' &&
    f.recovery_score !== ''
  )
})

const latestStrengthRecord = computed(() =>
  Array.isArray(strengthHistory.value) && strengthHistory.value.length ? strengthHistory.value[0] : null
)

const latestStrengthScore = computed(() => {
  const s = Number(latestStrengthRecord.value?.strength_score)
  return Number.isFinite(s) ? s : null
})

const strengthDelta = computed(() => {
  if (latestStrengthScore.value == null) return null
  return computedStrength.value.score - latestStrengthScore.value
})

const fetchStrengthPlayers = async () => {
  strengthPlayersLoading.value = true
  try {
    // Roster must reflect ONLY the currently selected team, not every player the
    // coach is associated with. Resolve the active team FIRST so an early call
    // (before the team is set) can't fall through to the full coach roster.
    if (!activeTeamId.value) {
      try { await ensureActiveTeam() } catch (_) { /* noop */ }
    }
    const teamId = activeTeamId.value
    const res = await axiosGet(teamId ? `coach/teams/${teamId}` : 'coach/roster/players')
    const payload = res?.data?.data
    const rawPlayers = Array.isArray(payload)
      ? payload
      : Array.isArray(payload?.data)
        ? payload.data
        : []

    let mapped = rawPlayers
      .map((p) => {
        const id = p?.id ?? p?.user_id ?? null
        return {
          id,
          name: p?.name?.full || `${p?.name?.first || ''} ${p?.name?.last || ''}`.trim() || `Player #${id}`,
        }
      })
      .filter((p) => p.id)

    strengthPlayers.value = mapped

    // Drop a stale selection that isn't on this team, then default to the first.
    if (strengthSelectedPlayerId.value &&
        !mapped.some((p) => String(p.id) === String(strengthSelectedPlayerId.value))) {
      strengthSelectedPlayerId.value = ''
    }
    if (!strengthSelectedPlayerId.value && strengthPlayers.value.length) {
      strengthSelectedPlayerId.value = String(strengthPlayers.value[0].id)
    }
  } catch {
    const fallback = Array.isArray(devBoard.value)
      ? devBoard.value
          .map((p) => ({ id: p?.id, name: p?.name || `Player #${p?.id}` }))
          .filter((p) => p.id)
      : []
    strengthPlayers.value = fallback

    if (!strengthSelectedPlayerId.value && strengthPlayers.value.length) {
      strengthSelectedPlayerId.value = String(strengthPlayers.value[0].id)
    }
  } finally {
    strengthPlayersLoading.value = false
  }
}

const fetchStrengthHistory = async () => {
  strengthMessage.value = { type: '', text: '' }
  if (!strengthSelectedPlayerId.value) {
    strengthHistory.value = []
    return
  }
  strengthHistoryLoading.value = true
  try {
    const res = await axiosGet(`player/fitness/${strengthSelectedPlayerId.value}`)
    strengthHistory.value = Array.isArray(res?.data?.data) ? res.data.data : []
  } catch {
    strengthHistory.value = []
  } finally {
    strengthHistoryLoading.value = false
  }
}

watch(() => strengthSelectedPlayerId.value, () => { fetchStrengthHistory() })

const submitStrengthAssessment = async () => {
  strengthMessage.value = { type: '', text: '' }
  if (!strengthSelectedPlayerId.value) {
    strengthMessage.value = { type: 'error', text: 'Select a player first.' }
    return
  }
  if (!strengthFormComplete.value) {
    strengthMessage.value = { type: 'error', text: 'Complete all strength tests before saving.' }
    return
  }
  if (strengthAssessmentType.value === 'first_time' && latestStrengthScore.value != null) {
    strengthMessage.value = { type: 'error', text: 'This player already has a strength baseline. Use Reassessment.' }
    return
  }
  if (strengthAssessmentType.value === 'reassessment' && latestStrengthScore.value == null) {
    strengthMessage.value = { type: 'error', text: 'No baseline found yet. Choose First-time Assessment.' }
    return
  }
  strengthSaving.value = true
  try {
    await axiosPost('player/fitness', {
      user_id: strengthSelectedPlayerId.value,
      fitness_date: strengthForm.value.fitness_date,
      body_weight: Number(strengthForm.value.body_weight_lbs || 0),
      front_squat: Number(strengthForm.value.front_squat_lbs || 0),
      back_squat: Number(strengthForm.value.back_squat_lbs || 0),
      bench_press: Number(strengthForm.value.bench_press_lbs || 0),
      dead_lift: Number(strengthForm.value.dead_lift_lbs || 0),
      power_clean: Number(strengthForm.value.power_clean_lbs || 0),
      hand_strength: Number(strengthForm.value.hand_strength_lbs || 0),
      push_ups: Number(strengthForm.value.push_ups || 0),
      pull_ups: Number(strengthForm.value.pull_ups || 0),
      vertical_jump: Number(strengthForm.value.vertical_jump_inches || 0),
      broad_jump: Number(strengthForm.value.broad_jump_inches || 0),
      med_ball_rotational_throw: Number(strengthForm.value.med_ball_rotational_throw_ft || 0),
      sprint_10yd: Number(strengthForm.value.sprint_10yd_sec || 0),
      exit_velo: Number(strengthForm.value.exit_velocity_mph || 0),
      bat_speed: Number(strengthForm.value.bat_speed_mph || 0),
      throwing_velo: Number(strengthForm.value.throwing_velo_mph || 0),
      pitch_velo: Number(strengthForm.value.pitch_velo_mph || 0),
      yd_40_dash: Number(strengthForm.value.yd_40_dash_sec || 0),
      yd_60_dash: Number(strengthForm.value.yd_60_dash_sec || 0),
      sleep_hours: Number(strengthForm.value.sleep_hours || 0),
      sleep_quality_1_to_5: Number(strengthForm.value.sleep_quality_1_to_5 || 0),
      recovery_score: Number(strengthForm.value.recovery_score || 0),
      ...(latestStrengthMobilityScore.value !== null ? { mobility_score: latestStrengthMobilityScore.value } : {}),
      strength_score: computedStrength.value.score,
    })
    await fetchStrengthHistory()
    strengthMessage.value = { type: 'success', text: `Saved! Strength score ${computedStrength.value.score} (${computedStrength.value.labels.overall}) saved to player record.` }
  } catch {
    strengthMessage.value = { type: 'error', text: 'Could not save strength assessment.' }
  } finally {
    strengthSaving.value = false
  }
}
// ── End Strength Assessment ───────────────────────────────────────────

const ensureQuickStatsLoaded = async () => {
  if (quickStatsLoaded.value) return

  if (user.userData.type !== 'player') {
    await fetchStrengthPlayers()
  }

  quickStatsLoaded.value = true
}

const allowedDashboardTabs = ['overview', 'development', 'strength']

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
  async (tab) => {
    if (tab === 'strength') {
      await ensureQuickStatsLoaded().catch(e => console.warn('ensureQuickStatsLoaded error:', e?.message ?? e))
    }

    if (tab === 'strength' && !strengthPlayersLoading.value && !strengthPlayers.value.length) {
      await fetchStrengthPlayers().catch(e => console.warn('fetchStrengthPlayers error:', e?.message ?? e))
    }
  },
  { immediate: true }
)

watch(
  () => devPlayerQueryId.value,
  async (nextId) => {
    if (!nextId) {
      autoOpenedDevPlayerId.value = ''
      return
    }
    await tryOpenDevPlayerFromQuery()
  },
  { immediate: true }
)

watch(
  () => devBoard.value,
  async () => {
    await tryOpenDevPlayerFromQuery()
  },
  { deep: false }
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

    // Selected team changed — the assessment roster must follow it.
    strengthSelectedPlayerId.value = ''
    fetchStrengthPlayers().catch(e => console.warn('fetchStrengthPlayers (team change) error:', e?.message ?? e))

    // Priority 2 — heavier, defer until after first paint
    setTimeout(() => {
      fetchPerformanceOverview()
      fetchDevBoard()
      fetchTeamInsight().catch(e => console.warn('fetchTeamInsight error:', e?.message ?? e))
      ensureTeamPlayerCards().catch(e => console.warn('ensureTeamPlayerCards preload error:', e?.message ?? e))
    }, 800)
  },
  { immediate: true }
)
</script>

<template>
  <Layout>
    <div class="min-h-screen bg-[#060b14] text-white">
      <div v-show="!devOnlyMode" class="w-full px-4 py-6 lg:px-8 lg:py-8 pb-28 md:pb-12">

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
          <button
            @click="setDashTab('strength')"
            class="px-5 py-2 rounded-lg text-sm font-black uppercase tracking-wide transition-all"
            :class="dashTab === 'strength' ? 'bg-[#C00000] text-white shadow-lg shadow-red-900/30' : 'text-white/40 hover:text-white'"
          >Assessment</button>
        </div>

        <!-- OVERVIEW TAB -->
        <div v-if="dashTab === 'overview'">

          <!-- AI Practice Recommendation -->
          <div v-if="teamInsightText" class="rounded-2xl border border-[#089BFF]/30 bg-gradient-to-r from-[#0a1830]/90 to-[#0a1020]/90 p-4 mb-5 shadow-xl">
            <div class="flex items-start gap-3">
              <div class="shrink-0 w-9 h-9 rounded-xl bg-[#089BFF]/15 border border-[#089BFF]/40 flex items-center justify-center text-lg">🧠</div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <p class="text-[10px] font-black uppercase tracking-widest text-[#38BDF8]">AI Practice Recommendation</p>
                  <div class="flex gap-1.5">
                    <button v-if="!teamInsightEditing" class="text-[10px] font-black uppercase text-[#38BDF8]/80 hover:text-[#38BDF8]" @click="startEditTeamInsight">Edit</button>
                    <template v-else>
                      <button class="text-[10px] font-black uppercase text-green-300" @click="saveTeamInsight">Save</button>
                      <button class="text-[10px] font-black uppercase text-white/50" @click="fetchTeamInsight">Cancel</button>
                    </template>
                    <button v-if="teamInsightEdited && !teamInsightEditing" class="text-[10px] font-black uppercase text-white/50 hover:text-white/80" @click="resetTeamInsight">Reset</button>
                  </div>
                </div>
                <textarea v-if="teamInsightEditing" v-model="teamInsightEdited" rows="2"
                  class="mt-1 w-full rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-sm text-white outline-none focus:border-[#38BDF8]/60"
                  :placeholder="teamInsight?.sentence || ''"></textarea>
                <p v-else class="mt-1 text-sm font-bold text-white leading-snug">{{ teamInsightText }}</p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 xl:grid-cols-[1fr_1fr_260px] gap-5 mb-5">

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

              <!-- Development Card carousel — one polished card per player, swipe/scroll through -->
              <div class="relative">
                <div
                  ref="devCarousel"
                  class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-1 -mx-1 px-1 dc-scroll"
                  @scroll.passive="onDevScroll"
                >
                  <div
                    v-for="player in devBoard" :key="player.id"
                    class="snap-start shrink-0 w-[420px] max-w-[86vw] cursor-pointer"
                    @click="openSharedPlayerDevelopmentProfile(player)"
                  >
                    <DevelopmentCard :player="player" :team="team" />
                  </div>
                </div>

                <!-- pager: prev · dots · next · counter -->
                <div v-if="devBoard.length > 1" class="flex items-center justify-center gap-3 mt-3">
                  <button
                    class="w-7 h-7 rounded-full border border-white/15 text-white/60 hover:text-white hover:border-white/40 text-lg font-black leading-none disabled:opacity-25 disabled:cursor-default transition"
                    :disabled="devCardIndex <= 0" aria-label="Previous player" @click="devPrev"
                  >‹</button>
                  <div class="flex items-center gap-1.5">
                    <button
                      v-for="(p, i) in devBoard" :key="p.id"
                      class="h-1.5 rounded-full transition-all"
                      :class="i === devCardIndex ? 'w-5 bg-white/85' : 'w-1.5 bg-white/25 hover:bg-white/45'"
                      :aria-label="`Go to player ${i + 1}`" @click="devScrollToIndex(i)"
                    ></button>
                  </div>
                  <button
                    class="w-7 h-7 rounded-full border border-white/15 text-white/60 hover:text-white hover:border-white/40 text-lg font-black leading-none disabled:opacity-25 disabled:cursor-default transition"
                    :disabled="devCardIndex >= devBoard.length - 1" aria-label="Next player" @click="devNext"
                  >›</button>
                  <span class="text-white/40 text-xs font-bold tabular-nums ml-1">{{ devCardIndex + 1 }} / {{ devBoard.length }}</span>
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

          <!-- ── Player Cards ── -->
          <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 backdrop-blur-xl p-4 shadow-xl flex flex-col min-h-0">
            <div class="flex items-center justify-between mb-3 shrink-0">
              <h2 class="text-sm font-black uppercase tracking-widest text-white">Roster</h2>
              <button
                class="text-[10px] font-black uppercase tracking-widest text-[#C00000] hover:text-red-400 transition"
                @click="setDashTab('development')"
              >View All →</button>
            </div>

            <!-- Loading -->
            <div v-if="devBoardLoading" class="flex flex-col gap-2">
              <div v-for="i in 6" :key="i" class="h-14 rounded-xl bg-white/5 animate-pulse"></div>
            </div>

            <!-- Empty -->
            <div v-else-if="!devBoard.length" class="flex-1 flex items-center justify-center text-white/25 text-sm">
              No players yet
            </div>

            <!-- Card list: full height when stacked (mobile/tablet), capped + scrollable only in the wide 3-column desktop layout. -->
            <div v-else class="flex flex-col gap-3 pr-1 max-h-none xl:max-h-[480px] xl:overflow-y-auto" style="scrollbar-width: thin; scrollbar-color: rgba(192,0,0,0.3) transparent;">
              <div
                v-for="p in devBoard" :key="p.id"
                class="relative rounded-2xl overflow-hidden cursor-pointer group"
                style="min-height: 120px;"
                @click="openSharedPlayerDevelopmentProfile(p)"
              >
                <!-- Background photo -->
                <div class="absolute inset-0">
                  <img
                    v-if="p.picture"
                    :src="p.picture"
                    class="w-full h-full object-cover object-top"
                    :alt="p.name"
                  />
                  <div v-else class="w-full h-full bg-gradient-to-br from-[#1a2030] to-[#0a1020] flex items-center justify-center">
                    <img :src="updatedLogo" class="w-12 h-12 opacity-20 object-contain" />
                  </div>
                </div>

                <!-- Dark gradient overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-black/20"></div>

                <!-- Content overlay -->
                <div class="relative z-10 flex flex-col justify-between h-full p-3" style="min-height: 120px;">
                  <!-- Top: name + jersey # small + trend -->
                  <div class="flex items-start justify-between gap-1">
                    <div class="flex flex-col leading-tight">
                      <span class="text-xs font-black text-white drop-shadow">{{ p.name }}</span>
                      <span v-if="p.jersey != null" class="text-[11px] font-black text-white/40 leading-tight">#{{ p.jersey }}</span>
                    </div>
                    <span class="text-base leading-none" :class="trendColor(p.trend)">{{ trendIcon(p.trend) }}</span>
                  </div>

                  <!-- Bottom section: left = height/weight, right = velocity stats -->
                  <div class="flex items-end justify-between gap-2 mt-auto">
                    <!-- Left: height + weight -->
                    <div v-if="p.height_ft != null || p.weight != null" class="flex items-center gap-1.5 text-[10px] text-white/60 font-bold">
                      <span v-if="p.height_ft != null">{{ p.height_ft }}'{{ p.height_in != null ? p.height_in + '"' : '' }}</span>
                      <span v-if="p.height_ft != null && p.weight != null" class="text-white/25">·</span>
                      <span v-if="p.weight != null">{{ p.weight }} lbs</span>
                    </div>
                    <div v-else></div>

                    <!-- Right: FB + EV labeled velocity stats -->
                    <div class="flex items-stretch gap-1.5 shrink-0">
                      <div
                        v-if="p.scores?.bullpen != null"
                        class="flex flex-col items-center px-2.5 py-1 rounded-lg bg-[#C00000]/70 border border-[#C00000] text-white"
                      >
                        <span class="text-[9px] font-black uppercase tracking-widest leading-none opacity-80">FB</span>
                        <span class="text-sm font-black leading-tight tabular-nums">{{ p.scores.bullpen }}</span>
                      </div>
                      <div
                        v-if="p.top_ev_mph != null"
                        class="flex flex-col items-center px-2.5 py-1 rounded-lg bg-green-600/70 border border-green-500 text-white"
                      >
                        <span class="text-[9px] font-black uppercase tracking-widest leading-none opacity-80">EV</span>
                        <span class="text-sm font-black leading-tight tabular-nums">{{ p.top_ev_mph }}</span>
                      </div>
                      <div
                        v-if="p.scores?.overall != null"
                        class="flex flex-col items-center px-2.5 py-1 rounded-lg"
                        :style="{ backgroundColor: scoreColor(p.scores.overall) + '28', border: '1px solid ' + scoreColor(p.scores.overall) + '55' }"
                      >
                        <span class="text-[9px] font-black uppercase tracking-widest leading-none opacity-80" :style="{ color: scoreColor(p.scores.overall) }">OVR</span>
                        <span class="text-sm font-black leading-tight tabular-nums" :style="{ color: scoreColor(p.scores.overall) }">{{ Math.round(p.scores.overall) }}</span>
                      </div>
                    </div>
                  </div>
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
                <!-- Metric cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mb-5">
                  <button
                    v-for="tab in top10Tabs" :key="tab.value"
                    @click="openTop10Modal(tab)"
                    class="text-left rounded-xl border p-3 transition-all"
                    :class="top10Tab === tab.value
                      ? 'bg-[#C00000]/20 border-[#C00000]/60 text-white shadow-lg shadow-red-900/30'
                      : 'bg-white/[0.04] border-white/15 text-white/80 hover:border-white/35 hover:bg-white/[0.07]'"
                  >
                    <div class="text-[11px] font-black uppercase tracking-widest">{{ tab.label }}</div>
                    <div class="mt-2 text-xs text-white/60">Open modal to view top 10 players</div>
                  </button>
                </div>

                <!-- Range filter -->
                <div class="flex items-center gap-2 mb-4">
                  <span class="text-white/30 text-[10px] uppercase tracking-widest">Period</span>
                  <div class="flex gap-1">
                    <button
                      v-for="r in [{ l: 'All', v: 0 }, { l: '1Y', v: 12 }, { l: '1M', v: 6 }, { l: '1W', v: 3 }]"
                      :key="r.v"
                      @click="top10Range = r.v; top10Tab === 12 ? ensureTeamPlayerCards() : getTop10(true)"
                      class="px-2.5 py-1 rounded-lg text-xs font-bold transition border"
                      :class="top10Range === r.v
                        ? 'bg-white/15 border-white/30 text-white'
                        : 'bg-transparent border-white/10 text-white/35 hover:text-white/60'"
                    >{{ r.l }}</button>
                  </div>
                </div>

                <!-- Selected category quick preview -->
                <div v-if="top10Rows.length" class="rounded-xl border border-white/10 bg-white/[0.03] p-3">
                  <div class="flex items-center justify-between gap-2">
                    <div class="text-xs uppercase tracking-widest text-white/45">Selected Category</div>
                    <button class="text-[10px] uppercase tracking-widest text-[#FCA5A5]" @click="openTop10Modal(activeTop10Tab)">Open Top 10</button>
                  </div>
                  <div class="mt-1.5 text-sm font-black text-white">{{ activeTop10Tab.label }}</div>
                  <div class="mt-1 text-xs text-white/70">
                    Leader: {{ top10PlayerName(top10Rows[0]) }} · {{ formatTop10MetricValue(top10Rows[0], activeTop10Tab) }}
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
                <button class="text-sm font-black text-sky-300 hover:text-sky-200 truncate" @click="openSharedPlayerDevelopmentProfile(player)">
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

        <!-- STRENGTH ASSESSMENT TAB -->
        <div v-if="dashTab === 'strength'" class="strength-assessment flex flex-col gap-5">
          <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
              <div class="flex-1 min-w-0">
                <h2 class="text-base font-black uppercase tracking-widest text-white">Assessment</h2>
                <p class="text-xs text-white/45 mt-1">Full FMTRX baseline — athletic, strength, mobility, hitting, pitching &amp; arm health, matching the app.</p>
                <div class="mt-4 max-w-sm">
                  <label class="block text-[11px] uppercase tracking-widest text-white/45 mb-1">Player</label>
                  <select
                    v-model="strengthSelectedPlayerId"
                    class="str-select w-full rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-sm text-white outline-none focus:border-red-400/60"
                    :disabled="strengthPlayersLoading"
                  >
                    <option value="">Select player</option>
                    <option v-for="p in strengthPlayers" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                  </select>
                </div>
              </div>
              <button
                type="button"
                class="px-4 py-2.5 rounded-lg bg-[#C00000] hover:bg-red-700 text-sm font-black uppercase tracking-wide text-white disabled:opacity-50"
                :disabled="!strengthSelectedPlayerId"
                @click="assessmentModalOpen = true"
              >
                {{ strengthSelectedPlayerId ? 'Open Assessment' : 'Select a player first' }}
              </button>
            </div>
          </div>

          <!-- Assessment reports — last baseline + full history, matching Reports. -->
          <div v-if="strengthMessage.text" class="text-sm" :class="strengthMessage.type === 'success' ? 'text-green-300' : 'text-red-300'">{{ strengthMessage.text }}</div>
          <div v-if="!strengthSelectedPlayerId" class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-5 text-sm text-white/35">
            Select a player above to see their assessment reports.
          </div>
          <PlayerAssessmentReport
            v-else
            :key="`${strengthSelectedPlayerId}-${assessmentReportKey}`"
            :player-id="String(strengthSelectedPlayerId)"
            :player-name="selectedStrengthPlayerName"
            :team-name="team?.name || team?.team_name || ''"
          />
        </div><!-- end strength tab -->

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
                      <span v-if="selectedDevCard?.physical?.born_date"> • DOB {{ formatDOB(selectedDevCard.physical.born_date) }}</span>
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

                <!-- Training player reports: match Player Metrics dashboard cards -->
                <div v-if="selectedTrainingHomeReports.length" class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                  <div
                    v-for="report in selectedTrainingHomeReports"
                    :key="report.title"
                    class="rounded-xl border border-white/10 bg-white/5 p-4"
                  >
                    <div class="mb-3">
                      <p class="text-xs font-black tracking-widest uppercase text-white/70">{{ report.title }}</p>
                      <p class="mt-1 text-[11px] font-bold text-white/40">{{ report.subtitle }}</p>
                    </div>

                    <div
                      v-if="homeLineRows(report).length > 1"
                      class="mb-4 rounded-lg bg-black/15 pt-1"
                    >
                      <svg class="h-[150px] w-full" viewBox="0 0 320 150" preserveAspectRatio="none">
                        <line
                          v-for="ratio in [0, 0.5, 1]"
                          :key="`home-grid-${report.title}-${ratio}`"
                          x1="32"
                          x2="306"
                          :y1="14 + ratio * 108"
                          :y2="14 + ratio * 108"
                          stroke="rgba(255,255,255,0.12)"
                          stroke-width="1"
                        />
                        <path
                          v-for="series in homeLineSeries(report)"
                          :key="`${report.title}-${series.key}`"
                          :d="homeLinePath(report, series)"
                          fill="none"
                          :stroke="series.color || '#ff2d55'"
                          :stroke-width="series.dashed ? 2 : 3"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          :stroke-dasharray="series.dashed ? '6 5' : null"
                          vector-effect="non-scaling-stroke"
                        />
                        <template
                          v-for="series in homeLineSeries(report)"
                          :key="`${report.title}-points-${series.key}`"
                        >
                          <circle
                            v-for="(row, index) in homeLineRows(report)"
                            v-show="Number.isFinite(Number(row[series.key])) && Number(row[series.key]) > 0"
                            :key="`${report.title}-${series.key}-${row.label}`"
                            :cx="homeLineX(report, index)"
                            :cy="homeLineY(report, row[series.key])"
                            r="3.5"
                            :fill="series.color || '#ff2d55'"
                            stroke="#101634"
                            stroke-width="1.5"
                            vector-effect="non-scaling-stroke"
                          />
                        </template>
                        <text
                          v-for="(row, index) in homeLineRows(report)"
                          :key="`${report.title}-label-${row.label}`"
                          :x="homeLineX(report, index)"
                          y="142"
                          fill="rgba(255,255,255,0.58)"
                          font-size="9"
                          font-weight="700"
                          text-anchor="middle"
                        >
                          {{ row.shortLabel || row.label.replace(' avg', '') }}
                        </text>
                      </svg>
                      <div class="flex flex-wrap gap-x-3 gap-y-1 px-2 pb-2">
                        <div
                          v-for="series in homeLineSeries(report)"
                          :key="`${report.title}-legend-${series.key}`"
                          class="flex items-center gap-1.5"
                        >
                          <span
                            class="h-2.5 w-2.5 rounded-full"
                            :style="{ backgroundColor: series.color || '#ff2d55' }"
                          ></span>
                          <span class="text-[10px] font-black uppercase text-white/55">{{ series.label }}</span>
                        </div>
                      </div>
                    </div>

                    <div class="space-y-3">
                      <div
                        v-for="row in homeReportRows(report)"
                        :key="`${report.title}-bar-${row.label}`"
                      >
                        <div class="mb-1 flex items-center justify-between gap-3">
                          <p class="text-[11px] font-black uppercase text-white/65">{{ row.label }}</p>
                          <p class="text-xs font-black text-white">{{ fmtDevReport(row.value, report.suffix) }}</p>
                        </div>
                        <div class="relative h-2.5 overflow-hidden rounded-full bg-white/10">
                          <div
                            class="h-full rounded-full"
                            :style="{
                              width: `${Math.max(5, Math.min(100, (Number(row.value) / Math.max(1, Number(report.max))) * 100))}%`,
                              backgroundColor: row.color || '#ff2d55',
                            }"
                          ></div>
                          <div
                            v-if="Number.isFinite(Number(row.expected))"
                            class="absolute top-[-2px] h-4 w-0.5 bg-white/85"
                            :style="{ left: `${Math.max(0, Math.min(100, (Number(row.expected) / Math.max(1, Number(report.max))) * 100))}%` }"
                          ></div>
                        </div>
                      </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                      <div
                        v-for="tile in report.tiles"
                        :key="`${report.title}-tile-${tile.label}`"
                        class="rounded-lg border border-white/10 bg-[#0b1230]/80 p-3"
                      >
                        <p class="text-[10px] font-black uppercase tracking-wide text-white/45">{{ tile.label }}</p>
                        <p class="mt-1 text-lg font-black text-white">{{ tile.value }}</p>
                        <p v-if="tile.sub" class="mt-0.5 text-[10px] font-bold text-white/35">{{ tile.sub }}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Strength Standards Reference -->
                <StrengthStandardsCard
                  :body-weight="strengthChartValues.bodyWeight"
                  :bench-press="strengthChartValues.benchPress"
                  :front-squat="strengthChartValues.frontSquat"
                  :pull-ups="strengthChartValues.pullUps"
                  :push-ups="strengthChartValues.pushUps"
                />

                <!-- Coach Assessment Tool: Strength + Mobility -->
                <CoachAssessmentPanel
                  v-if="selectedDevPlayer?.id && user.userData.type !== 'player'"
                  :player-id="selectedDevPlayer.id"
                  :player-name="selectedDevPlayer.name"
                />

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

    <!-- Top 10 Player Leaders Modal -->
    <Teleport to="body">
      <Transition name="sheet">
        <div
          v-if="top10Modal.visible"
          class="fixed inset-0 z-50 flex items-start justify-center p-3 sm:p-6 overflow-y-auto"
          style="background: rgba(0,0,0,0.65)"
          @click.self="closeTop10Modal"
        >
          <div class="w-full max-w-xl bg-[#0d1b33] rounded-2xl pt-6 pb-6 px-4 sm:px-6 shadow-2xl border border-white/10 mt-1 sm:mt-2 max-h-[92vh] flex flex-col">
            <div class="w-10 h-1 bg-white/20 rounded-full mx-auto mb-5"></div>
            <div class="flex items-start justify-between gap-3">
              <div>
                <h2 class="text-xl font-black text-white">{{ top10Modal.tab?.label || 'Top 10 Players' }}</h2>
                <p class="text-white/50 text-sm mt-0.5">Highest value per player (duplicates removed)</p>
              </div>
              <button class="text-white/60 hover:text-white text-sm font-black" @click="closeTop10Modal">Close</button>
            </div>

            <div v-if="top10Modal.loading" class="flex justify-center py-10">
              <svg class="animate-spin w-6 h-6 text-[#C00000]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
            </div>

            <div v-else-if="!top10Modal.rows.length" class="text-white/40 text-sm py-10 text-center">No player data for this period</div>

            <div v-else class="mt-4 flex-1 min-h-0 flex flex-col gap-2 overflow-y-auto pr-1">
              <div
                v-for="(item, idx) in top10Modal.rows"
                :key="`${top10PlayerName(item)}-${idx}`"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-white/10 bg-white/[0.03]"
              >
                <span
                  class="w-5 text-center text-sm font-black shrink-0"
                  :class="idx === 0 ? 'text-yellow-400' : idx === 1 ? 'text-slate-300' : idx === 2 ? 'text-orange-400' : 'text-white/30'"
                >
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
                <span class="text-xs font-black text-green-300 bg-green-500/10 border border-green-500/20 px-2.5 py-1 rounded-full whitespace-nowrap">
                  {{ formatTop10MetricValue(item, top10Modal.tab) }}
                </span>
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

    <AssessmentModal
      :visible="assessmentModalOpen"
      :player-name="selectedStrengthPlayerName"
      :player-id="strengthSelectedPlayerId"
      :team-id="activeTeamId"
      :players="strengthPlayers"
      @close="assessmentModalOpen = false"
      @player-change="strengthSelectedPlayerId = String($event)"
      @saved="onAssessmentSaved"
    />
  </Layout>
</template>

<style scoped>
/* Development Card carousel — clean scroll-snap, hidden scrollbar */
.dc-scroll { scrollbar-width: none; -ms-overflow-style: none; scroll-padding: 0 4px; }
.dc-scroll::-webkit-scrollbar { display: none; }
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

/* ── Strength Assessment ─────────────────────────────── */
.str-input {
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.04);
  color: rgba(248, 250, 252, 0.95);
  padding: 0.45rem 0.6rem;
  font-size: 0.85rem;
  outline: none;
}

.str-input:focus {
  border-color: rgba(192, 0, 0, 0.65);
}

.str-input::placeholder {
  color: rgba(226, 232, 240, 0.45);
}

.str-select {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  padding-right: 2rem;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5L10 12.5L15 7.5' stroke='%23E2E8F0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.65rem center;
  background-size: 12px;
}

.str-select::-ms-expand { display: none; }

.strength-assessment p,
.strength-assessment span,
.strength-assessment label,
.strength-assessment li,
.strength-assessment button,
.strength-assessment h2,
.strength-assessment h3,
.strength-assessment input,
.strength-assessment select,
.strength-assessment option {
  font-weight: 700;
}

.str-test-title {
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

.str-test-help-cta {
  color: rgba(252, 165, 165, 0.95);
  font-size: 10px;
}

.str-test-help {
  margin-bottom: 0.65rem;
  border: 1px solid rgba(192, 0, 0, 0.35);
  background: rgba(192, 0, 0, 0.12);
  border-radius: 0.5rem;
  padding: 0.45rem 0.6rem;
  font-size: 0.74rem;
  color: rgba(254, 226, 226, 0.98);
  line-height: 1.35;
}
</style>
