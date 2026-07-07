<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { parseDOB, resolveBornValue } from '@/utils/dob.js'
import Layout from '@/layout/Layout.vue'
import ModalPlayer from '@/components/dashboard/ModalPlayer.vue'
import ArmCarePanel from '@/components/training/ArmCarePanel.vue'
import { useUserStore } from '@/store/user'
import { useAxiosAuth } from '@/composables/axios-auth'
import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.png'

const userStore = useUserStore()
const { userData } = userStore
const { axiosGet, axiosPost } = useAxiosAuth()
const router = useRouter()

const loading = ref(false)
const connectionMessage = ref('')
const activeTopTab = ref('stats')
const activeStatTab = ref('bp')
const lastStatTab = ref('bp')
const toggleArmCare = () => {
  if (activeStatTab.value === 'armCare') {
    activeStatTab.value = lastStatTab.value
  } else {
    lastStatTab.value = activeStatTab.value
    activeStatTab.value = 'armCare'
  }
}
const isOpenPlayerMetricsModal = ref(false)
const isOpeningPlayerMetricsModal = ref(false)
const playerMetricsRows = ref([])
const playerMetricsScore = ref({})

const battingSessions = ref([])
const bullpenSessions = ref([])
const cageSessions = ref([])
const createdSessions = ref([])
const cageStatRows = ref([])
const trainingSessions = ref([])
const playerFitnessLatest = ref(null)
const sleepCheckinOpen = ref(false)
const sleepCheckinSaving = ref(false)
const sleepCheckinCheckedDate = ref('')
const sleepCheckinForm = ref({
  sleep_hours: '',
  sleep_quality_1_to_5: '',
})

const statTabs = [
  { key: 'bp', label: 'BP Stats' },
  { key: 'bullpen', label: 'Bullpen' },
  { key: 'cage', label: 'Cage' },
  { key: 'weighted', label: 'Weighted' },
  { key: 'exitVel', label: 'Exit Velocity' },
  { key: 'longToss', label: 'Long Toss' },
]

const POOR = '#ef4444'
const AVG = '#facc15'
const GREAT = '#22c55e'
const ONE_HOUR_MS = 3600000
const todayDateKey = () => {
  const d = new Date()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${month}-${day}`
}
const fitnessDateKey = (value) => {
  if (!value) return ''
  if (typeof value === 'string') {
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (match) return `${match[1]}-${match[2]}-${match[3]}`
  }
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${month}-${day}`
}
const hasSleepLoggedToday = (rows = []) => {
  const today = todayDateKey()
  return (Array.isArray(rows) ? rows : [rows])
    .filter(Boolean)
    .some((row) => {
      const isToday = fitnessDateKey(row?.fitness_date || row?.created_at || row?.date) === today
      if (!isToday) return false
      const hours = Number(row?.sleep_hours)
      const quality = Number(row?.sleep_quality_1_to_5)
      return Number.isFinite(hours) && hours > 0 && Number.isFinite(quality) && quality >= 1 && quality <= 5
    })
}

const WEIGHTED_VELO_SCALES = {
  3: { min: 60, max: 110, thresholds: [75, 88] },
  4: { min: 58, max: 108, thresholds: [72, 85] },
  5: { min: 55, max: 100, thresholds: [68, 80] },
  6: { min: 52, max: 95, thresholds: [65, 77] },
  7: { min: 50, max: 92, thresholds: [62, 74] },
  9: { min: 45, max: 85, thresholds: [58, 70] },
}

const SESSION_MODE_LABEL_MAP = {
  EV: 'Exit Velocity',
  LT: 'Long Toss',
  WB: 'Weighted Ball',
  HP: 'Hit or Pitch',
}

const SESSION_REPORT_TYPE = {
  B: 'batting',
  P: 'bullpen',
  C: 'cage',
  EV: 'exit_velocity',
  LT: 'long_toss',
  WB: 'weight_ball',
}

// Maps a training session's mode to its stats route segment so per-session
// ball-by-ball data can be pulled (statistics/{id}/{segment}).
const trainingModeEndpoint = (session) => {
  const mode = String(session?.modes || session?.mode || '').toUpperCase()
  if (mode === 'WB') return 'weightball'
  if (mode === 'EV') return 'exitvelocity'
  if (mode === 'LT' || mode.includes('LONG')) return 'longtoss'
  return null
}

const SESSION_TYPE_COLOR = {
  batting:       { bg: 'bg-sky-500/20', border: 'border-sky-500/40', text: 'text-sky-300', label: 'BATTING' },
  bullpen:       { bg: 'bg-violet-500/20', border: 'border-violet-500/40', text: 'text-violet-300', label: 'BULLPEN' },
  cage:          { bg: 'bg-emerald-500/20', border: 'border-emerald-500/40', text: 'text-emerald-300', label: 'CAGE' },
  live:          { bg: 'bg-orange-500/20', border: 'border-orange-500/40', text: 'text-orange-300', label: 'LIVE AB' },
  long_toss:     { bg: 'bg-pink-500/20', border: 'border-pink-500/40', text: 'text-pink-300', label: 'LONG TOSS' },
  weight_ball:   { bg: 'bg-yellow-500/20', border: 'border-yellow-500/40', text: 'text-yellow-300', label: 'WEIGHT BALL' },
  exit_velocity: { bg: 'bg-red-500/20', border: 'border-red-500/40', text: 'text-red-300', label: 'EXIT VEL' },
}

const asArray = (val) => {
  if (Array.isArray(val)) return val
  if (val && typeof val === 'object') return Object.values(val)
  return []
}

const rowsFromApi = (res) => {
  const payload = res?.data?.data
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (payload && typeof payload === 'object') {
    const nested = payload?.data
    if (Array.isArray(nested)) return nested
  }
  return []
}

const flatten = (val) => {
  if (Array.isArray(val)) return val.flatMap((v) => flatten(v))
  if (val && typeof val === 'object') return Object.values(val).flatMap((v) => flatten(v))
  return val == null ? [] : [val]
}

const toObjectRows = (val) => {
  if (val == null) return []

  if (typeof val === 'string') {
    const raw = val.trim()
    if (!raw) return []
    try {
      return toObjectRows(JSON.parse(raw))
    } catch {
      return []
    }
  }

  if (Array.isArray(val)) {
    return val.flatMap((item) => toObjectRows(item))
  }

  if (val && typeof val === 'object') {
    const keys = Object.keys(val)
    const looksLikeMetricRow = [
      'launch_angle',
      'spray_angle',
      'launch_angle_velocity',
      'velocity',
      'exit_velocity',
      'distance_travel',
      'type_of_hit',
    ].some((k) => keys.includes(k))

    if (looksLikeMetricRow) return [val]

    return Object.values(val).flatMap((item) => toObjectRows(item))
  }

  return []
}

const parseNum = (row, keys) => {
  for (const k of keys) {
    const n = Number(row?.[k])
    if (Number.isFinite(n) && n > 0) return n
  }
  return null
}

const parseHop = (row) => {
  const raw = row?.player_hop ?? row?.hop ?? row?.hops ?? row?.hop_count ?? row?.number_of_hops ?? row?.bounce ?? null
  if (raw == null) return null

  if (typeof raw === 'number') {
    return Number.isFinite(raw) ? raw : null
  }

  if (typeof raw === 'string') {
    const direct = Number(raw)
    if (Number.isFinite(direct)) return direct
    const match = raw.match(/\d+/)
    return match ? Number(match[0]) : null
  }

  if (typeof raw === 'object') {
    const candidates = [raw?.value, raw?.hop, raw?.hops, raw?.count, ...Object.values(raw)]
    for (const candidate of candidates) {
      if (candidate == null) continue
      const n = typeof candidate === 'number' ? candidate : Number(String(candidate).match(/\d+/)?.[0] ?? candidate)
      if (Number.isFinite(n)) return n
    }
  }

  return null
}

const tryLen = (arr) => (Array.isArray(arr) ? arr.length : undefined)

const normalizeMode = (modeLike) => {
  const mode = String(modeLike || '').trim().toUpperCase()
  if (!mode) return null
  if (mode === 'EV') return 'EV'
  if (mode === 'WB') return 'WB'
  if (mode === 'LT' || mode.includes('LONG')) return 'LT'
  if (mode === 'HP') return 'HP'
  return mode
}

const isCompletedSession = (session) => (
  session?.is_completed === 2 ||
  session?.is_completed === 1 ||
  session?.is_completed === true ||
  session?.finished === true
)

const computeTotalBalls = (session, sourceType) => {
  const modeHint = normalizeMode(session?.mode || session?.modes)
  const explicitCount =
    (typeof session?.total_balls === 'number' ? session.total_balls : undefined) ??
    (typeof session?.total_ball === 'number' ? session.total_ball : undefined) ??
    (typeof session?.balls === 'number' ? session.balls : undefined) ??
    (typeof session?.pitches_count === 'number' ? session.pitches_count : undefined) ??
    (typeof session?.total_pitches === 'number' ? session.total_pitches : undefined) ??
    (typeof session?.throws_count === 'number' ? session.throws_count : undefined)

  if (typeof explicitCount === 'number') return explicitCount

  if (sourceType === 'B') {
    const v = tryLen(session?.batting) ?? tryLen(session?.practice_match_result) ?? tryLen(session?.results)
    if (v !== undefined) return v
  }

  if (sourceType === 'P') {
    const v = tryLen(session?.bullpen) ?? tryLen(session?.pitchers) ?? tryLen(session?.practice_match_result) ?? tryLen(session?.results) ?? tryLen(session?.pitches)
    if (v !== undefined) return v
  }

  if (sourceType === 'C') {
    const v = tryLen(session?.cage) ?? tryLen(session?.batters) ?? tryLen(session?.practice_match_result) ?? tryLen(session?.results)
    if (v !== undefined) return v
  }

  if (sourceType === 'T') {
    const v = tryLen(session?.practice_match_result) ?? tryLen(session?.results)
    if (v !== undefined) return v
    if (modeHint === 'LT') {
      const lt = tryLen(session?.throws)
      if (lt !== undefined) return lt
    }
  }

  return 0
}

const mapRecentSession = (session, sourceType, createdBySelf = false) => {
  const mode = normalizeMode(session?.mode || session?.modes)
  const date = session?.created_at || session?.updated_at || session?.date || session?.started || null
  const resolvedType = sourceType || String(session?.type || '').toUpperCase()
  const reportType = resolvedType === 'T'
    ? (SESSION_REPORT_TYPE[mode] || null)
    : (SESSION_REPORT_TYPE[resolvedType] || null)

  const label = resolvedType === 'T'
    ? (SESSION_MODE_LABEL_MAP[mode] || 'Training Mode')
    : (resolvedType === 'B'
      ? 'Batting Practice'
      : resolvedType === 'P'
        ? 'Bullpen Practice'
        : resolvedType === 'C'
          ? 'Cage Practice'
          : resolvedType === 'L'
            ? 'LiveAB Practice'
            : 'Training Mode')

  return {
    ...session,
    _label: label,
    _date: date,
    _sourceType: resolvedType,
    _mode: mode,
    _reportType: reportType,
    total_balls: computeTotalBalls(session, resolvedType),
    is_completed: isCompletedSession(session) ? 2 : 1,
    created_by_self: createdBySelf,
  }
}

const shouldShowRecentSession = (session) => {
  if (!session) return false
  const hasBalls = Number(session.total_balls || 0) > 0
  const isDone = Number(session.is_completed) === 2
  const isFreshSelfCreated = Boolean(
    session.created_by_self &&
    session._date &&
    (Date.now() - new Date(session._date).getTime()) < ONE_HOUR_MS
  )
  return hasBalls || isDone || isFreshSelfCreated
}

const fmt = (v, d = 1) => (Number.isFinite(v) ? Number(v.toFixed(d)) : null)
const pct = (num, den) => (den > 0 ? fmt((num / den) * 100, 1) : null)
const maxOf = (arr) => (arr.length > 0 ? Math.max(...arr) : null)
const avgOf = (arr) => (arr.length > 0 ? arr.reduce((a, b) => a + b, 0) / arr.length : null)

const normalizePitchType = (row) => {
  const raw = String(row?.type_of_throw_msg || row?.pitch_type || row?.type_throw || row?.type_of_throw || row?.name || '').toUpperCase().trim()
  if (['FB', 'FASTBALL', 'FF', 'FT', 'SI'].includes(raw)) return 'FB'
  if (['CH', 'CHANGEUP', 'CHANGE-UP', 'CHANGE UP'].includes(raw)) return 'CH'
  if (['SL', 'SLIDER'].includes(raw)) return 'SL'
  if (['CV', 'CU', 'CURVEBALL', 'CURVE'].includes(raw)) return 'CV'
  return raw || 'OTHER'
}

const pitchTypeIdFromRow = (row) => {
  const id = Number(row?.type_of_throw_id || 0)
  if (Number.isFinite(id) && id > 0) return id
  const t = normalizePitchType(row)
  if (t === 'FB') return 1
  if (t === 'CH') return 2
  if (t === 'SL') return 3
  if (t === 'CV') return 4
  return 5
}

const isStrikeZoneMark = (mark) => {
  const m = Number(mark)
  if (!Number.isFinite(m) || m <= 0) return false
  const col = Math.floor((m - 1) / 60) + 1
  const row = ((m - 1) % 60) + 1
  return col >= 19 && col <= 41 && row >= 18 && row <= 43
}

const classifyTrajectory = (row) => {
  const raw = String(row?.trajectory || row?.type_of_hit || row?.type_of_hit_msg || '').toUpperCase().trim()
  if (['GB', 'GROUND BALL', 'GROUNDER'].includes(raw)) return 'GB'
  if (['LD', 'LINE DRIVE', 'LINER'].includes(raw)) return 'LD'
  if (['FB', 'FLY BALL', 'FLYBALL', 'PU', 'POPUP', 'POP UP', 'POP-UP'].includes(raw)) return 'FB'
  const la = Number(row?.launch_angle)
  if (Number.isFinite(la)) {
    if (la < 10) return 'GB'
    if (la <= 25) return 'LD'
    return 'FB'
  }
  return null
}

const isSwingMiss = (row) => {
  const raw = String(
    row?.type_of_hit_msg ||
    row?.batting?.type_of_hit ||
    row?.type_of_hit ||
    row?.batting?.trajectory ||
    row?.trajectory ||
    '',
  ).toUpperCase().trim()

  return ['SM', 'SW/MISS', 'SWING MISS', 'MISS', 'SWING AND MISS'].includes(raw)
}

const upper = (v) => String(v || '').toUpperCase().trim()

const getSessionRows = (session) => {
  const rows = []
  rows.push(...asArray(session?.practice_match_result))
  rows.push(...asArray(session?.results))
  rows.push(...asArray(session?.batting))
  rows.push(...asArray(session?.bullpen))
  rows.push(...asArray(session?.cage))
  rows.push(...asArray(session?.weight_ball))
  rows.push(...asArray(session?.long_toss))
  rows.push(...asArray(session?.exit_velocity))
  rows.push(...flatten(session?.ball_x_ball || []))
  return rows
}

const extractCageRowsFromStatsResponse = (responseData) => {
  const sources = [
    responseData?.data?.data?.ball_by_ball_results,
    responseData?.data?.data?.ball_x_ball,
    responseData?.data?.data?.ball_by_ball,
    responseData?.data?.data?.results,
    responseData?.data?.data?.practice_match_result,
    responseData?.data?.ball_by_ball_results,
    responseData?.data?.ball_x_ball,
    responseData?.data?.ball_by_ball,
    responseData?.data?.results,
    responseData?.data?.practice_match_result,
    responseData?.ball_by_ball_results,
    responseData?.ball_x_ball,
    responseData?.ball_by_ball,
    responseData?.results,
    responseData?.practice_match_result,
  ]

  for (const src of sources) {
    const arr = toObjectRows(src)
    if (arr.length > 0) return arr
  }

  return []
}

const pick = (...vals) => {
  for (const v of vals) {
    if (v === null || v === undefined) continue
    if (typeof v === 'string' && v.trim() === '') continue
    return v
  }
  return null
}

const asDisplay = (v, fallback = '—') => {
  const x = pick(v)
  return x == null ? fallback : String(x)
}

const normalizeImageSrc = (src) => {
  // Reject non-string junk (e.g. a File object left in the model) that would
  // stringify to "[object File]" and render as a broken image.
  if (!src || typeof src !== 'string') return null
  const raw = src.trim()
  if (!raw || raw.startsWith('[object')) return null
  if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('data:') || raw.startsWith('blob:')) return raw
  // Relative path → resolve against the API host (where uploaded images live),
  // NOT the current page origin (which is just the SPA shell).
  const apiBase = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
  const host = String(apiBase).replace(/\/api\/?$/, '').replace(/\/$/, '')
  const path = raw.startsWith('/') ? raw : `/${raw}`
  return host ? `${host}${path}` : path
}

// Fall back to the default logo if the avatar URL fails to load (dead link,
// expired blob: from a previous session, wrong host, etc.) instead of a broken icon.
const onAvatarError = (e) => {
  if (e?.target && e.target.src !== updatedLogo) e.target.src = updatedLogo
}

const formatHeight = (ft, inch, composed) => {
  const ftNum = Number(ft)
  const inNum = Number(inch)
  if (Number.isFinite(ftNum)) {
    return `${ftNum}' ${Number.isFinite(inNum) ? inNum : 0}\"`
  }
  if (typeof composed === 'string' && composed.trim()) return composed.trim()
  return '—'
}

const formatGradYearShort = (yearLike) => {
  if (yearLike == null) return null
  const raw = String(yearLike).trim()
  if (!raw) return null
  const digits = raw.replace(/[^0-9]/g, '')
  if (digits.length < 2) return null
  return `'${digits.slice(-2).padStart(2, '0')}`
}

const deriveGradYearFromBirthdate = (...birthCandidates) => {
  const raw = birthCandidates.find((v) => v != null && String(v).trim() !== '')
  if (!raw) return null
  const dob = parseDOB(raw)
  if (!dob) return null
  const gradYear = dob.getFullYear() + 18
  return formatGradYearShort(gradYear)
}

const formatPositions = (positionsRaw) => {
  if (!positionsRaw) return '—'
  const arr = Array.isArray(positionsRaw)
    ? positionsRaw
    : (positionsRaw && typeof positionsRaw === 'object' ? Object.values(positionsRaw) : [positionsRaw])

  const labels = arr
    .map((p) => {
      if (typeof p === 'string') return p
      if (!p || typeof p !== 'object') return ''
      return p.abbreviation || p.code || p.name || p.position || ''
    })
    .map((v) => String(v || '').trim())
    .filter(Boolean)

  return labels.length ? labels.join(', ') : '—'
}

const playerName = computed(() => userData?.name?.full || userData?.name || 'Player')

const playerImageSrc = computed(() => {
  const raw = pick(
    userData?.avatar,
    userData?.profile?.picture,
    userData?.profile?.avatar,
    userData?.player?.picture,
    userData?.user?.avatar,
    userData?.user?.profile?.picture,
  )

  return normalizeImageSrc(raw) || updatedLogo
})

const profileBgStyle = computed(() => ({
  backgroundImage: `url('${updatedLogo}')`,
  backgroundSize: '70%',
  backgroundPosition: 'center',
  backgroundRepeat: 'no-repeat',
}))

const developmentPlayerId = computed(() => {
  return (
    userData?.player?.id ||
    userData?.user?.player?.id ||
    userData?.user?.id ||
    userData?.id ||
    null
  )
})

const maybeOpenSleepCheckin = (fitnessRows = []) => {
  const today = todayDateKey()
  if (sleepCheckinCheckedDate.value === today) return
  sleepCheckinCheckedDate.value = today
  if (hasSleepLoggedToday(fitnessRows)) {
    sleepCheckinOpen.value = false
    return
  }
  sleepCheckinForm.value = { sleep_hours: '', sleep_quality_1_to_5: '' }
  sleepCheckinOpen.value = true
}

const saveSleepCheckin = async () => {
  const playerId = developmentPlayerId.value || userData?.id
  const hours = Number(sleepCheckinForm.value.sleep_hours)
  const quality = Number(sleepCheckinForm.value.sleep_quality_1_to_5)
  if (!playerId) return
  if (!Number.isFinite(hours) || hours <= 0 || hours > 24) {
    connectionMessage.value = 'Enter sleep hours between 0 and 24.'
    return
  }
  if (!Number.isInteger(quality) || quality < 1 || quality > 5) {
    connectionMessage.value = 'Sleep quality must be a whole number from 1 to 5.'
    return
  }
  sleepCheckinSaving.value = true
  connectionMessage.value = ''
  try {
    const res = await axiosPost('player/fitness', {
      user_id: playerId,
      fitness_date: todayDateKey(),
      sleep_hours: hours,
      sleep_quality_1_to_5: quality,
    })
    const saved = res?.data?.data || null
    if (saved) playerFitnessLatest.value = saved
    sleepCheckinOpen.value = false
  } catch (error) {
    connectionMessage.value = error?.response?.data?.message || 'Could not save sleep check-in.'
  } finally {
    sleepCheckinSaving.value = false
  }
}

const openDevelopmentProfile = async () => {
  await router.push({
    name: 'dashboard',
    query: {
      tab: 'overview',
      devOnly: '1',
      devPlayerId: developmentPlayerId.value || undefined,
      playerName: playerName.value,
    },
  })
}

const modalPlayerItem = computed(() => {
  const profileData = userData?.profile || {}
  const playerData = userData?.player || {}

  const first = profileData?.first_name || userData?.name?.first || userData?.first_name || ''
  const last = profileData?.last_name || userData?.name?.last || userData?.last_name || ''
  const full = `${first} ${last}`.trim() || userData?.name?.full || userData?.name || 'Player'

  return {
    id: developmentPlayerId.value || userData?.id || null,
    name: {
      first,
      last,
      full,
    },
    email: userData?.email || userData?.user?.email || null,
    avatar: normalizeImageSrc(
      userData?.avatar ||
      profileData?.avatar ||
      profileData?.picture ||
      playerData?.avatar ||
      playerData?.picture,
    ) || null,
    body: {
      full_height: formatHeight(
        pick(playerData?.height_in_ft, userData?.ft),
        pick(playerData?.height_in_inch, userData?.inch),
        pick(profileData?.height, playerData?.height),
      ),
    },
    shirt_number: playerData?.number_in_shirt || userData?.shirt_number || null,
    number_in_shirt: playerData?.number_in_shirt || userData?.shirt_number || null,
    throw_side: playerData?.throw_side || userData?.throw_side || null,
    hit_side: playerData?.hit_side || userData?.hit_side || null,
    age: userData?.born?.age || null,
    positions: asArray(userData?.positions || profileData?.positions || playerData?.positions),
  }
})

const openPlayerMetricsModal = async () => {
  const pid = developmentPlayerId.value || userData?.id || null
  if (!pid) return

  isOpeningPlayerMetricsModal.value = true
  isOpenPlayerMetricsModal.value = true

  try {
    const [scoreRes, fitnessRes] = await Promise.all([
      axiosGet(`player/statistics/${pid}`).catch(() => null),
      axiosGet(`player/fitness/${pid}`).catch(() => null),
    ])

    playerMetricsScore.value = scoreRes?.data?.data ?? {}
    const fit = fitnessRes?.data?.data
    playerMetricsRows.value = Array.isArray(fit) ? fit : (fit ? [fit] : [])
  } finally {
    isOpeningPlayerMetricsModal.value = false
  }
}

const profile = computed(() => {
  const p = userData?.profile || {}
  const player = userData?.player || {}
  const wt = pick(
    playerFitnessLatest.value?.body_weight,
    p?.weight,
    player?.weight,
    userData?.weight,
  )

  return {
    height: formatHeight(
      pick(player?.height_in_ft, userData?.ft),
      pick(player?.height_in_inch, userData?.inch),
      pick(p?.height, player?.height),
    ),
    weight: wt != null ? wt : '—',
    position: formatPositions(pick(userData?.positions, p?.positions, p?.position, player?.positions, player?.position)),
  }
})

const coachProfile = computed(() => {
  const p = userData?.profile || {}
  const player = userData?.player || {}
  const team = userData?.team || {}
  const first = pick(p?.first_name, userData?.name?.first, userData?.first_name) || ''
  const last = pick(p?.last_name, userData?.name?.last, userData?.last_name) || ''
  const derivedGradYear = deriveGradYearFromBirthdate(
    resolveBornValue(p),
    resolveBornValue(userData),
    resolveBornValue(player),
  )

  return {
    fullName: asDisplay(pick([first, last].filter(Boolean).join(' '), userData?.name?.full, userData?.name, userData?.user?.name?.full), 'Player'),
    jersey: asDisplay(pick(player?.number_in_shirt, userData?.shirt_number, player?.jersey, p?.jersey)),
    bats: asDisplay(pick(player?.hit_side, userData?.hit_side, player?.batting_side, player?.bats, p?.bats)),
    throws: asDisplay(pick(player?.throw_side, userData?.throw_side, player?.throws, player?.throwing_side, p?.throws)),
    gradYear: asDisplay(derivedGradYear || formatGradYearShort(pick(player?.graduation_year, userData?.graduation_year, player?.grad_year, p?.graduation_year))),
    school: asDisplay(pick(player?.school_name, userData?.school_name, player?.school, userData?.school, p?.school)),
    role: asDisplay(pick(player?.role, p?.role)),
    team: asDisplay(pick(team?.name, userData?.team_name, player?.team_name, p?.team_name)),
  }
})

const schoolTeamText = computed(() => {
  const parts = [coachProfile.value?.school, coachProfile.value?.team]
    .map((v) => String(v || '').trim())
    .filter((v) => v && v !== '—')
  return parts.length ? parts.join(' · ') : '—'
})

const metricValue = (...keys) => {
  const fit = playerFitnessLatest.value || {}
  for (const key of keys) {
    const v = fit?.[key]
    if (v == null) continue
    const s = String(v).trim()
    if (!s || s === 'null' || s === 'undefined') continue
    return s
  }
  return '-'
}

const strengthLine = computed(() => {
  const bench = metricValue('bench_press', 'bench', 'benchpress')
  const dl = metricValue('dead_lift', 'deadlift', 'dead')
  const bs = metricValue('back_squat', 'backsquat', 'back')
  const fs = metricValue('front_squat', 'frontsquat', 'front')
  const clean = metricValue('power_clean', 'clean', 'powerclean')
  return `Bench ${bench} · DL ${dl} · BS ${bs} · FS ${fs} · Clean ${clean}`
})

const speedLine = computed(() => {
  const forty = metricValue('yd_40_dash', 'dash_40', 'forty', '40_time')
  const sixty = metricValue('yd_60_dash', 'dash_60', 'sixty', '60_time')
  return `40 ${forty} · 60 ${sixty}`
})

const openSessionReports = () => {
  router.push({ name: 'sessions.all', query: { scope: 'player' } })
}

const openAssessmentReports = () => {
  router.push({ name: 'assessment.reports', query: { scope: 'player' } })
}

const openArmCare = () => {
  router.push({ name: 'arm.care' })
}

const sessionCounts = computed(() => {
  const weighted = trainingSessions.value.filter((s) => String(s?.modes || s?.mode || '').toUpperCase() === 'WB')
  const ev = trainingSessions.value.filter((s) => String(s?.modes || s?.mode || '').toUpperCase() === 'EV')
  const lt = trainingSessions.value.filter((s) => String(s?.modes || s?.mode || '').toUpperCase().includes('LT'))
  return {
    batting: battingSessions.value.length,
    bullpen: bullpenSessions.value.length,
    cage: cageSessions.value.length,
    training: trainingSessions.value.length,
    weighted: weighted.length,
    exitVel: ev.length,
    longToss: lt.length,
  }
})

const recentSessions = computed(() => {
  const created = Array.isArray(createdSessions.value) ? createdSessions.value : []

  const withCreated = (base, createdType) => {
    const ids = new Set(base.map((s) => s?.id).filter(Boolean))
    const extra = created.filter((s) => String(s?.type || '').toUpperCase() === createdType && !ids.has(s?.id))
    return [...base, ...extra]
  }

  const batting = withCreated(battingSessions.value, 'B')
  const bullpen = withCreated(bullpenSessions.value, 'P')
  const cage = withCreated(cageSessions.value, 'C')
  const training = withCreated(trainingSessions.value, 'T')
  const liveab = withCreated([], 'L')
  const selfCreatedIds = new Set(created.map((s) => s?.id).filter(Boolean))

  return [
    ...batting.map((s) => mapRecentSession(s, 'B', selfCreatedIds.has(s?.id))),
    ...bullpen.map((s) => mapRecentSession(s, 'P', selfCreatedIds.has(s?.id))),
    ...cage.map((s) => mapRecentSession(s, 'C', selfCreatedIds.has(s?.id))),
    ...liveab.map((s) => mapRecentSession(s, 'L', selfCreatedIds.has(s?.id))),
    ...training.map((s) => mapRecentSession(s, 'T', selfCreatedIds.has(s?.id))),
  ]
    .filter((s) => s?.id)
    .filter(shouldShowRecentSession)
    .sort((a, b) => new Date(b?._date || 0) - new Date(a?._date || 0))
    .slice(0, 8)
})

const openRecapReport = (session) => {
  if (!session?.id || !session?._reportType) return
  const date = session?._date || null
  const note = session?.end_note || null
  router.push({
    name: 'session.report',
    params: { id: session.id, type: session._reportType },
    query: { date, note },
  })
}

const recapTypeKey = (session) => {
  const source = String(session?._sourceType || '').toUpperCase()
  const mode = String(session?._mode || '').toUpperCase()

  if (source === 'B') return 'batting'
  if (source === 'P') return 'bullpen'
  if (source === 'C') return 'cage'
  if (source === 'L') return 'live'
  if (source === 'T') {
    if (mode === 'EV') return 'exit_velocity'
    if (mode === 'LT') return 'long_toss'
    if (mode === 'WB') return 'weight_ball'
    return 'live'
  }

  return 'live'
}

const recapTypeStyle = (session) => {
  const key = recapTypeKey(session)
  return SESSION_TYPE_COLOR[key] || SESSION_TYPE_COLOR.live
}

const battingBreakdown = computed(() => {
  const rows = battingSessions.value.flatMap((s) => getSessionRows(s))

  const swings = rows
    .filter((r) => r && typeof r === 'object')
    .map((r) => {
      const ev = parseNum(r, ['velocity', 'exit_velocity', 'miles_per_hour', 'mph'])
        ?? parseNum(r?.batting || {}, ['velocity', 'exit_velocity', 'miles_per_hour', 'mph'])

      const qoc = upper(
        r?.batting?.quality_of_contact ||
        r?.quality_of_contact ||
        r?.quality_of_contact_msg,
      )

      const toh = upper(
        r?.batting?.type_of_hit ||
        r?.type_of_hit ||
        r?.type_of_hit_msg,
      )

      const dir = upper(
        r?.batting?.field_direction ||
        r?.field_direction,
      )

      const pitchMark = Number(r?.pitch_mark ?? r?.batting?.pitch_mark ?? 0)

      return {
        ev: Number.isFinite(ev) && ev >= 10 && ev <= 125 ? ev : null,
        qoc,
        toh,
        dir,
        pitchMark: Number.isFinite(pitchMark) ? pitchMark : 0,
        isMiss: qoc === 'MF' || qoc === 'F' || isSwingMiss(r),
      }
    })

  const total = swings.length
  const evBalls = swings.filter((s) => s.ev !== null)
  const hardCount = evBalls.filter((s) => s.ev >= 90).length
  const hardPct = pct(hardCount, evBalls.length)
  const missCount = swings.filter((s) => s.isMiss).length
  const missPct = pct(missCount, total)
  const avgEV = fmt(avgOf(evBalls.map((s) => s.ev)) ?? null, 1)
  const topEV = fmt(maxOf(evBalls.map((s) => s.ev)), 1)

  const sprayBalls = swings.filter((s) => s.dir && !s.isMiss)
  const lfPct = pct(sprayBalls.filter((s) => s.dir === 'LF').length, sprayBalls.length)
  const cfPct = pct(sprayBalls.filter((s) => s.dir === 'CF').length, sprayBalls.length)
  const rfPct = pct(sprayBalls.filter((s) => s.dir === 'RF').length, sprayBalls.length)

  const contactBalls = swings.filter((s) => !s.isMiss)
  const bpGb = contactBalls.filter((s) => s.toh === 'GB').length
  const bpLd = contactBalls.filter((s) => s.toh === 'LD').length
  const bpFb = contactBalls.filter((s) => s.toh === 'FB').length
  const bpPf = contactBalls.filter((s) => s.toh === 'PF').length
  const trajTotal = bpGb + bpLd + bpFb + bpPf

  const gbPct = pct(bpGb, trajTotal)
  const ldPct = pct(bpLd, trajTotal)
  const fbPct = pct(bpFb, trajTotal)
  const pfPct = pct(bpPf, trajTotal)

  const avgEVNum = Number(avgEV)
  const missPctNum = Number(missPct)
  const evScore = Number.isFinite(avgEVNum) ? (Math.min(avgEVNum, 110) / 110) * 100 : 0
  const hdLdRate = total > 0 ? ((hardCount + bpLd) / total) * 100 : 0
  const missAdj = Number.isFinite(missPctNum) ? (100 - missPctNum) : 0
  const damageScore = total > 0 ? fmt(evScore * 0.4 + hdLdRate * 0.35 + missAdj * 0.25, 1) : null

  const pitchBalls = swings.filter((s) => s.pitchMark > 0 && ['H', 'A', 'W'].includes(s.qoc))
  let upperH = 0; let lowerH = 0; let innerH = 0; let outerH = 0
  let upperN = 0; let lowerN = 0; let innerN = 0; let outerN = 0
  pitchBalls.forEach((s) => {
    const m = Number(s.pitchMark)
    const col = Math.floor((m - 1) / 60) + 1
    const row = ((m - 1) % 60) + 1
    const isHard = s.qoc === 'H'
    if (row < 30) { upperN++; if (isHard) upperH++ } else { lowerN++; if (isHard) lowerH++ }
    if (col > 30) { innerN++; if (isHard) innerH++ } else { outerN++; if (isHard) outerH++ }
  })

  const zonePerf = pitchBalls.length >= 5
    ? {
      upperHardPct: pct(upperH, upperN),
      lowerHardPct: pct(lowerH, lowerN),
      innerHardPct: pct(innerH, innerN),
      outerHardPct: pct(outerH, outerN),
    }
    : null

  const compCount = swings.filter((s) => s.qoc === 'H' || (s.qoc === 'A' && (s.toh === 'LD' || s.toh === 'FB'))).length
  const compPct = pct(compCount, total)

  let consistency = null
  if (swings.length >= 20) {
    const first10 = swings.slice(0, 10)
    const last10 = swings.slice(-10)
    const first10Ev = first10.filter((s) => s.ev !== null).map((s) => s.ev)
    const last10Ev = last10.filter((s) => s.ev !== null).map((s) => s.ev)
    const avgF10 = avgOf(first10Ev)
    const avgL10 = avgOf(last10Ev)
    consistency = {
      hardDrop: first10.filter((s) => s.qoc === 'H').length - last10.filter((s) => s.qoc === 'H').length,
      missDiff: last10.filter((s) => s.isMiss).length - first10.filter((s) => s.isMiss).length,
      evDrop: (avgF10 != null && avgL10 != null) ? fmt(avgF10 - avgL10, 1) : null,
    }
  }

  return {
    swings: total,
    maxEV: topEV,
    avgEV,
    hardPct,
    missPct,
    sprayTotal: sprayBalls.length,
    lfPct,
    cfPct,
    rfPct,
    gbPct,
    ldPct,
    fbPct,
    pfPct,
    trajTotal,
    damageScore,
    zonePerf,
    compPct,
    consistency,
  }
})

const bullpenBreakdown = computed(() => {
  const rows = bullpenSessions.value.flatMap((s) => getSessionRows(s))
  const normalized = rows.map((r) => {
    const mph = parseNum(r, ['miles_per_hour', 'velocity', 'pitch_velocity', 'mph'])
    const locMark = Number(r?.pitch_mark || r?.pitch_location || 0)
    let strike = Number(r?.is_strike) === 1 || r?.is_strike === true || String(r?.result || r?.ball_strike || '').toUpperCase().includes('STRIKE')
    if (!strike && locMark > 0) {
      strike = isStrikeZoneMark(locMark)
    }
    return {
      mph,
      strike,
      typeId: pitchTypeIdFromRow(r),
      quality: Number(r?.quality_of_throw_id || 0),
      numBalls: r?.num_balls !== undefined ? Number(r?.num_balls) : -1,
      numStrikes: r?.num_strikes !== undefined ? Number(r?.num_strikes) : -1,
      locMark,
    }
  })

  const mphs = normalized.map((r) => r.mph).filter((v) => v !== null)
  const strikes = normalized.filter((r) => r.strike).length
  const fbOnly = normalized.filter((i) => i.typeId === 1).map((i) => i.mph).filter((v) => v !== null)

  const firstPitch = normalized.filter((p) => p.numBalls === 0 && p.numStrikes === 0)
  const firstStrikePct = pct(firstPitch.filter((p) => p.strike).length, firstPitch.length)

  const hasQualityData = normalized.some((p) => Number.isFinite(p.quality) && p.quality > 0)
  const locationAccuracyPct = hasQualityData ? pct(normalized.filter((p) => p.quality === 1).length, normalized.length) : null
  const competitivePct = hasQualityData
    ? pct(normalized.filter((p) => p.strike || p.quality === 2).length, normalized.length)
    : pct(strikes, normalized.length)
  const qualityPct = hasQualityData
    ? pct(normalized.filter((p) => p.strike && p.quality === 1).length, normalized.length)
    : null

  const pitchTypes = [
    { id: 1, label: 'FB' },
    { id: 2, label: 'CH' },
    { id: 3, label: 'SL' },
    { id: 4, label: 'CV' },
    { id: 5, label: 'OTHER' },
  ]

  const pitchTypeStats = pitchTypes.map(({ id, label }) => {
    const items = normalized.filter((i) => (id === 5 ? !i.typeId || i.typeId === 5 : i.typeId === id))
    const vel = items.map((i) => i.mph).filter((v) => v !== null)
    return {
      type: label,
      strikes: items.filter((i) => i.strike).length,
      strikePct: pct(items.filter((i) => i.strike).length, items.length),
      avgMph: fmt(avgOf(vel) ?? null, 1),
      count: items.length,
    }
  }).filter((x) => x.count > 0)

  const missPitches = normalized.filter((p) => !p.strike && p.locMark > 0)
  let missPattern = []
  if (missPitches.length >= 3) {
    let armHigh = 0; let armMid = 0; let armLow = 0
    let gloveHigh = 0; let gloveMid = 0; let gloveLow = 0
    let up = 0; let down = 0

    missPitches.forEach((p) => {
      const m = Number(p.locMark)
      const col = Math.floor((m - 1) / 60) + 1
      const row = ((m - 1) % 60) + 1
      const isHigh = row < 18
      const isLow = row > 43
      const isArm = col > 41
      const isGlove = col < 19

      if (isArm && isHigh) armHigh++
      else if (isArm && isLow) armLow++
      else if (isArm) armMid++
      else if (isGlove && isHigh) gloveHigh++
      else if (isGlove && isLow) gloveLow++
      else if (isGlove) gloveMid++
      else if (isHigh) up++
      else down++
    })

    const tot = missPitches.length
    const toPct = (n) => fmt((n / tot) * 100, 0)

    missPattern = [
      { label: 'Arm-Side High', pct: toPct(armHigh) },
      { label: 'Arm-Side', pct: toPct(armMid) },
      { label: 'Arm-Side Low', pct: toPct(armLow) },
      { label: 'Glove-Side High', pct: toPct(gloveHigh) },
      { label: 'Glove-Side', pct: toPct(gloveMid) },
      { label: 'Glove-Side Low', pct: toPct(gloveLow) },
      { label: 'Straight Up', pct: toPct(up) },
      { label: 'Straight Down', pct: toPct(down) },
    ]
      .filter((r) => (r.pct ?? 0) > 0)
      .sort((a, b) => (b.pct || 0) - (a.pct || 0))
      .slice(0, 4)
  }

  return {
    total: normalized.length,
    maxFB: fmt(maxOf(mphs), 1),
    avgFB: fmt(avgOf(fbOnly) ?? avgOf(mphs) ?? null, 1),
    strikePct: pct(strikes, normalized.length),
    firstStrikePct,
    locationAccuracyPct,
    competitivePct,
    qualityPct,
    byType: pitchTypeStats,
    pitchTypeStats,
    missPattern,
  }
})

const cageBreakdown = computed(() => {
  const rows = cageStatRows.value.length > 0
    ? cageStatRows.value
    : cageSessions.value.flatMap((s) => getSessionRows(s))
  const ev = rows.map((r) => parseNum(r, ['launch_angle_velocity', 'velocity', 'exit_velocity', 'miles_per_hour'])).filter((v) => v !== null)
  const la = rows.map((r) => parseNum(r, ['launch_angle', 'angle'])).filter((v) => v !== null)
  const hard = ev.filter((v) => v >= 90).length
  const barrel = rows.filter((r) => {
    const velo = parseNum(r, ['launch_angle_velocity', 'velocity', 'exit_velocity', 'miles_per_hour'])
    const angle = parseNum(r, ['launch_angle', 'angle'])
    return velo !== null && angle !== null && velo >= 85 && angle >= 8 && angle <= 30
  }).length
  const sweet = la.filter((a) => a >= 8 && a <= 32).length
  const quality = rows.filter((r) => {
    const velo = parseNum(r, ['launch_angle_velocity', 'velocity', 'exit_velocity', 'miles_per_hour'])
    const angle = parseNum(r, ['launch_angle', 'angle'])
    return velo !== null && angle !== null && velo >= 75 && angle >= 5 && angle <= 35
  }).length

  const sprayVals = rows
    .map((r) => parseNum(r, ['spray_angle', 'spray', 'direction_angle']))
    .filter((v) => v !== null)
  const pull = sprayVals.filter((v) => v <= -18).length
  const center = sprayVals.filter((v) => v > -18 && v < 18).length
  const oppo = sprayVals.filter((v) => v >= 18).length

  let laStdDev = null
  if (la.length >= 3) {
    const mean = avgOf(la)
    const sqDiff = la.map((v) => (v - mean) ** 2)
    laStdDev = Math.sqrt(avgOf(sqDiff) ?? 0)
  }
  const laConsistency = laStdDev == null ? null : fmt(Math.max(0, 30 - laStdDev), 1)

  const avgEV = avgOf(ev)
  const hardPct = pct(hard, ev.length)
  const sweetPct = pct(sweet, la.length)
  const damage = avgEV == null || hardPct == null || sweetPct == null
    ? null
    : fmt((Math.min(avgEV, 110) / 110) * 100 * 0.4 + sweetPct * 0.35 + hardPct * 0.25, 1)
  return {
    swings: rows.length,
    avgEV: fmt(avgEV ?? null, 1),
    maxEV: fmt(maxOf(ev), 1),
    hardPct,
    barrelPct: pct(barrel, la.length || rows.length),
    avgLA: fmt(avgOf(la) ?? null, 1),
    laConsistency,
    sweetPct,
    swingQualityPct: pct(quality, la.length || rows.length),
    pullPct: pct(pull, sprayVals.length),
    centerPct: pct(center, sprayVals.length),
    oppoPct: pct(oppo, sprayVals.length),
    sprayTotal: sprayVals.length,
    damage,
  }
})

const weightedBreakdown = computed(() => {
  const weighted = trainingSessions.value.filter((s) => String(s?.modes || s?.mode || '').toUpperCase() === 'WB')
  const rows = weighted.flatMap((s) => getSessionRows(s))
  const groups = {}
  rows.forEach((r) => {
    const w = parseNum(r, ['weight', 'weighted_ball', 'ball_weight'])
    const v = parseNum(r, ['velocity', 'weighted_velocity', 'miles_per_hour', 'mph'])
    if (w == null || v == null) return
    if (!groups[w]) groups[w] = []
    groups[w].push(v)
  })
  const byWeight = Object.keys(groups).map((w) => ({
    weight: Number(w),
    count: groups[w].length,
    avgVelo: fmt(avgOf(groups[w]) ?? null, 1),
    maxVelo: fmt(maxOf(groups[w]), 1),
  })).sort((a, b) => a.weight - b.weight)
  const all = Object.values(groups).flat()
  return {
    throws: rows.length,
    maxVelo: fmt(maxOf(all), 1),
    avgVelo: fmt(avgOf(all) ?? null, 1),
    byWeight,
  }
})

const exitVelBreakdown = computed(() => {
  const evSessions = trainingSessions.value.filter((s) => String(s?.modes || s?.mode || '').toUpperCase() === 'EV')
  const rows = evSessions.flatMap((s) => getSessionRows(s))
  const ev = rows.map((r) => parseNum(r, ['velocity', 'exit_velocity', 'launch_angle_velocity', 'miles_per_hour'])).filter((v) => v !== null)
  const hard = ev.filter((v) => v >= 90).length
  const classified = rows
    .map((row) => ({
      trajectory: classifyTrajectory(row),
      velocity: parseNum(row, ['velocity', 'exit_velocity', 'launch_angle_velocity', 'miles_per_hour']),
    }))
    .filter((row) => row.trajectory)
  const trajectories = classified.map((row) => row.trajectory)
  const gb = trajectories.filter((t) => t === 'GB').length
  const ld = trajectories.filter((t) => t === 'LD').length
  const fb = trajectories.filter((t) => t === 'FB').length
  const avgTrajEV = (key) => fmt(avgOf(classified.filter((row) => row.trajectory === key).map((row) => row.velocity).filter((v) => v !== null)) ?? null, 1)
  return {
    swings: rows.length,
    maxEV: fmt(maxOf(ev), 1),
    avgEV: fmt(avgOf(ev) ?? null, 1),
    hardPct: pct(hard, ev.length),
    gbPct: pct(gb, trajectories.length),
    ldPct: pct(ld, trajectories.length),
    fbPct: pct(fb, trajectories.length),
    gbAvgEV: avgTrajEV('GB'),
    ldAvgEV: avgTrajEV('LD'),
    fbAvgEV: avgTrajEV('FB'),
    gbCount: gb,
    ldCount: ld,
    fbCount: fb,
    trajTotal: trajectories.length,
  }
})

const longTossBreakdown = computed(() => {
  const ltSessions = trainingSessions.value.filter((s) => {
    const mode = String(s?.modes || s?.mode || '').toUpperCase()
    return mode === 'LT' || mode.includes('LONG')
  })
  const rows = ltSessions.flatMap((s) => getSessionRows(s))
  const dists = rows.map((r) => parseNum(r, ['distance', 'dist', 'throw_distance', 'feet'])).filter((v) => v !== null)
  const hops = rows.map((r) => parseHop(r))
  const hopAvg = (n) => {
    const vals = rows
      .filter((r, idx) => Number(hops[idx]) === n)
      .map((r) => parseNum(r, ['distance', 'dist', 'throw_distance', 'feet']))
      .filter((v) => v !== null)
    return fmt(avgOf(vals) ?? null, 1)
  }

  const hopCount = (n) => hops.filter((h) => Number(h) === n).length
  const hop0Count = hopCount(0)
  const hop1Count = hopCount(1)
  const hop2Count = hopCount(2)
  const hop3Count = hopCount(3)
  const hopTotal = hop0Count + hop1Count + hop2Count + hop3Count

  return {
    throws: rows.length,
    maxDist: fmt(maxOf(dists), 1),
    avgDist: fmt(avgOf(dists) ?? null, 1),
    hop0: hopAvg(0),
    hop1: hopAvg(1),
    hop2: hopAvg(2),
    hop3: hopAvg(3),
    hop0Count,
    hop1Count,
    hop2Count,
    hop3Count,
    hopTotal,
    hop0Pct: pct(hop0Count, hopTotal),
    hop1Pct: pct(hop1Count, hopTotal),
    hop2Pct: pct(hop2Count, hopTotal),
    hop3Pct: pct(hop3Count, hopTotal),
  }
})

const fmtReport = (value, suffix = '') => {
  const n = Number(value)
  if (!Number.isFinite(n)) return '—'
  return `${Number.isInteger(n) ? n : n.toFixed(1)}${suffix}`
}

const lineChartRows = (report) => {
  const series = report?.lineSeries || []
  return (report?.rows || []).filter((row) => series.some((item) => Number.isFinite(Number(row[item.key])) && Number(row[item.key]) > 0))
}
const lineChartSeries = (report) => {
  const rows = lineChartRows(report)
  return (report?.lineSeries || []).filter((item) => rows.some((row) => Number.isFinite(Number(row[item.key])) && Number(row[item.key]) > 0))
}
const lineChartValues = (report) => {
  const rows = lineChartRows(report)
  return lineChartSeries(report)
    .flatMap((item) => rows.map((row) => Number(row[item.key])))
    .filter((value) => Number.isFinite(value) && value > 0)
}
const lineChartRange = (report) => {
  const values = lineChartValues(report)
  const max = Math.max(Number(report?.max || 0), ...values, 1)
  const rawMin = values.length ? Math.min(...values) : 0
  const min = Math.max(0, rawMin - Math.max(5, (max - rawMin) * 0.2))
  return { min, max, span: Math.max(1, max - min) }
}
const lineChartX = (report, index) => {
  const rows = lineChartRows(report)
  const left = 32
  const right = 14
  const width = 320
  if (rows.length <= 1) return left
  return left + (index / (rows.length - 1)) * (width - left - right)
}
const lineChartY = (report, value) => {
  const top = 14
  const bottom = 28
  const height = 150
  const range = lineChartRange(report)
  return top + (1 - ((Number(value) - range.min) / range.span)) * (height - top - bottom)
}
const lineChartPath = (report, series) => lineChartRows(report)
  .map((row, index) => ({ x: lineChartX(report, index), y: lineChartY(report, row[series.key]), value: Number(row[series.key]) }))
  .filter((point) => Number.isFinite(point.value) && point.value > 0)
  .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(1)} ${point.y.toFixed(1)}`)
  .join(' ')

const weightedPlayerReport = computed(() => {
  const w = weightedBreakdown.value
  if (!w.byWeight.length) return null
  const five = w.byWeight.find((row) => Number(row.weight) === 5)
  const base = Number(five?.avgVelo || w.byWeight[0]?.avgVelo || 0)
  const multipliers = { 3: 1.04, 4: 1.02, 5: 1, 6: 0.97, 7: 0.94, 9: 0.90 }
  const max = Math.max(...w.byWeight.map((row) => Number(row.maxVelo || row.avgVelo || 0)), base * 1.06 || 1)
  const rows = w.byWeight.map((row) => {
    const expected = base && multipliers[row.weight] ? base * multipliers[row.weight] : null
    return {
      label: `${row.weight} oz avg`,
      shortLabel: `${row.weight} oz`,
      value: row.avgVelo,
      topValue: row.maxVelo,
      expected,
      color: Number(row.weight) < 5 ? '#34A7FF' : Number(row.weight) === 5 ? '#37D67A' : '#F7D774',
    }
  })
  const best = rows
    .map((row) => ({ ...row, delta: row.expected ? Number(row.value) - row.expected : 0 }))
    .sort((a, b) => b.delta - a.delta)[0]
  return {
    title: 'Weighted Ball Velocity Curve',
    subtitle: 'All weighted ball throws',
    max,
    suffix: ' mph',
    rows,
    lineSeries: [
      { key: 'value', label: 'Avg', color: '#37D67A' },
      { key: 'topValue', label: 'Top', color: '#34A7FF' },
      { key: 'expected', label: 'Expected', color: '#FFFFFF', dashed: true },
    ],
    tiles: [
      { label: 'Top Velo', value: fmtReport(w.maxVelo, ' mph'), sub: 'Best recorded' },
      { label: 'Best Weight', value: best?.label?.replace(' avg', '') || '—', sub: best ? fmtReport(best.value, ' mph avg') : '' },
      { label: '5 oz Avg', value: fmtReport(five?.avgVelo, ' mph'), sub: 'Regulation ball' },
      { label: 'Weights', value: String(w.byWeight.length), sub: 'Tracked groups' },
    ],
  }
})

const exitVelocityPlayerReport = computed(() => {
  const e = exitVelBreakdown.value
  if (!e.swings) return null
  const rows = [
    { label: 'LD avg', shortLabel: 'LD', value: e.ldAvgEV, color: '#37D67A' },
    { label: 'GB avg', shortLabel: 'GB', value: e.gbAvgEV, color: '#34A7FF' },
    { label: 'FB avg', shortLabel: 'FB', value: e.fbAvgEV, color: '#F7D774' },
  ]
  return {
    title: 'Exit Velocity by Trajectory',
    subtitle: `${e.trajTotal || 0} classified swings`,
    max: Math.max(Number(e.maxEV || 0), 100),
    suffix: ' mph',
    rows,
    lineSeries: [
      { key: 'value', label: 'Avg EV', color: '#ff2d55' },
    ],
    tiles: [
      { label: 'Avg EV', value: fmtReport(e.avgEV, ' mph'), sub: 'All EV swings' },
      { label: 'Top EV', value: fmtReport(e.maxEV, ' mph'), sub: 'Best recorded' },
      { label: 'Hard Hit %', value: fmtReport(e.hardPct, '%'), sub: '90+ mph' },
      { label: 'Line Drives', value: fmtReport(e.ldPct, '%'), sub: `${e.ldCount || 0} swings` },
    ],
  }
})

const longTossPlayerReport = computed(() => {
  const l = longTossBreakdown.value
  if (!l.throws) return null
  const rows = [
    { label: '0 hops avg', shortLabel: '0', value: l.hop0, color: '#37D67A' },
    { label: '1 hop avg', shortLabel: '1', value: l.hop1, color: '#34A7FF' },
    { label: '2 hops avg', shortLabel: '2', value: l.hop2, color: '#F7D774' },
    { label: '3 hops avg', shortLabel: '3', value: l.hop3, color: '#ff2d55' },
  ]
  const cleanPct = l.throws ? ((l.hop0Count + l.hop1Count) / l.throws) * 100 : null
  return {
    title: 'Long Toss Distance by Hops',
    subtitle: `${l.throws} throws`,
    max: Math.max(Number(l.maxDist || 0), 300),
    suffix: ' ft',
    rows,
    lineSeries: [
      { key: 'value', label: 'Avg Distance', color: '#37D67A' },
    ],
    tiles: [
      { label: 'Top Distance', value: fmtReport(l.maxDist, ' ft'), sub: 'Best throw' },
      { label: 'Avg Distance', value: fmtReport(l.avgDist, ' ft'), sub: 'All throws' },
      { label: 'Clean Carry', value: fmtReport(cleanPct, '%'), sub: '0-1 hops' },
      { label: 'Full Carry', value: fmtReport(l.hop0Pct, '%'), sub: '0 hops' },
    ],
  }
})

const activeTrainingReport = computed(() => {
  if (activeStatTab.value === 'weighted') return weightedPlayerReport.value
  if (activeStatTab.value === 'exitVel') return exitVelocityPlayerReport.value
  if (activeStatTab.value === 'longToss') return longTossPlayerReport.value
  return null
})

const metricRows = computed(() => {
  if (activeStatTab.value === 'bp') {
    return []
  }
  if (activeStatTab.value === 'bullpen') {
    const b = bullpenBreakdown.value
    return [
      { label: 'Max FB Velo', value: b.maxFB, unit: 'mph', min: 50, max: 105, thresholds: [75, 90] },
      { label: 'Avg FB Velo', value: b.avgFB, unit: 'mph', min: 50, max: 100, thresholds: [70, 85] },
      { label: 'Overall Strike %', value: b.strikePct, unit: '%', min: 40, max: 85, thresholds: [55, 65] },
      { label: 'First-Pitch Strike %', value: b.firstStrikePct, unit: '%', min: 40, max: 85, thresholds: [55, 65] },
      { label: 'Location Accuracy %', value: b.locationAccuracyPct, unit: '%', min: 0, max: 80, thresholds: [35, 55] },
      { label: 'Competitive Pitch %', value: b.competitivePct, unit: '%', min: 40, max: 90, thresholds: [60, 75] },
      { label: 'Quality Pitch %', value: b.qualityPct, unit: '%', min: 0, max: 70, thresholds: [30, 50] },
    ]
  }
  if (activeStatTab.value === 'cage') {
    const c = cageBreakdown.value
    return [
      { label: 'Avg Exit Velocity', value: c.avgEV, unit: 'mph', min: 40, max: 110, thresholds: [68, 87] },
      { label: 'Top Exit Velocity', value: c.maxEV, unit: 'mph', min: 50, max: 115, thresholds: [80, 97] },
      { label: 'Hard Hit % (90+ mph)', value: c.hardPct, unit: '%', min: 0, max: 60, thresholds: [10, 35] },
      { label: 'Barrel % (EV+LA)', value: c.barrelPct, unit: '%', min: 0, max: 35, thresholds: [5, 15] },
      { label: 'Sweet Spot %', value: c.sweetPct, unit: '%', min: 0, max: 75, thresholds: [15, 40] },
      { label: 'LA Consistency', value: c.laConsistency, unit: '', min: 0, max: 30, thresholds: [10, 20] },
      { label: 'Swing Quality %', value: c.swingQualityPct, unit: '%', min: 0, max: 80, thresholds: [20, 50] },
      { label: 'Damage Score', value: c.damage, unit: '', min: 0, max: 100, thresholds: [25, 55] },
      { label: 'Avg Launch Angle', value: c.avgLA, unit: '°', min: -10, max: 50, thresholds: [8, 25] },
    ]
  }
  if (activeStatTab.value === 'weighted') {
    const w = weightedBreakdown.value
    const byWeightRows = w.byWeight.map((entry) => {
      const scale = WEIGHTED_VELO_SCALES[entry.weight] || { min: 45, max: 110, thresholds: [65, 80] }
      return {
        label: `${entry.weight} oz Avg Velo (${entry.count})`,
        value: entry.avgVelo,
        unit: 'mph',
        min: scale.min,
        max: scale.max,
        thresholds: scale.thresholds,
      }
    })

    return [
      { label: 'Total Throws', value: w.throws, unit: '', min: 0, max: 100, thresholds: [20, 50] },
      { label: 'Max Throw Velo', value: w.maxVelo, unit: 'mph', min: 45, max: 110, thresholds: [70, 88] },
      { label: 'Avg Throw Velo', value: w.avgVelo, unit: 'mph', min: 45, max: 100, thresholds: [65, 80] },
      ...byWeightRows,
    ]
  }
  if (activeStatTab.value === 'longToss') {
    const l = longTossBreakdown.value
    return [
      { label: 'Max Distance', value: l.maxDist, unit: 'ft', min: 0, max: 400, thresholds: [150, 280] },
      { label: 'Avg Distance', value: l.avgDist, unit: 'ft', min: 0, max: 350, thresholds: [120, 250] },
      { label: 'Avg Dist (0 Hops)', value: l.hop0, unit: 'ft', min: 0, max: 400, thresholds: [200, 300] },
      { label: 'Avg Dist (1 Hop)', value: l.hop1, unit: 'ft', min: 0, max: 420, thresholds: [150, 330] },
      { label: 'Avg Dist (2 Hops)', value: l.hop2, unit: 'ft', min: 0, max: 430, thresholds: [150, 350] },
      { label: 'Avg Dist (3 Hops)', value: l.hop3, unit: 'ft', min: 0, max: 450, thresholds: [150, 390] },
    ]
  }
  const e = exitVelBreakdown.value
  return [
    { label: 'Max Exit Velocity', value: e.maxEV, unit: 'mph', min: 0, max: 115, thresholds: [70, 90] },
    { label: 'Avg Exit Velocity', value: e.avgEV, unit: 'mph', min: 0, max: 110, thresholds: [55, 75] },
    { label: 'Hard Hit %', value: e.hardPct, unit: '%', min: 0, max: 60, thresholds: [10, 35] },
    { label: 'Ground Ball %', value: e.gbPct, unit: '%', min: 0, max: 70, thresholds: [25, 45] },
    { label: 'Line Drive %', value: e.ldPct, unit: '%', min: 0, max: 70, thresholds: [20, 40] },
    { label: 'Fly Ball %', value: e.fbPct, unit: '%', min: 0, max: 70, thresholds: [20, 40] },
  ]
})

const barPercent = (row) => {
  if (row.value == null || row.min == null || row.max == null) return 0
  const v = Number(row.value)
  if (!Number.isFinite(v)) return 0
  const p = ((v - row.min) / (row.max - row.min)) * 100
  return Math.max(0, Math.min(100, p))
}

const barColor = (row) => {
  const value = Number(row.value)
  if (!Number.isFinite(value)) return 'rgba(255,255,255,0.4)'
  const p = barPercent(row)
  if (p < 100 / 3) return POOR
  if (p < 200 / 3) return AVG
  return GREAT
}

const loadData = async () => {
  loading.value = true
  connectionMessage.value = ''

  const safeGet = async (url) => {
    try {
      return await axiosGet(url)
    } catch (error) {
      if ([401, 403].includes(Number(error?.response?.status))) {
        connectionMessage.value = `Player API connection failed on ${url} (${error?.response?.status}). Log out, then log back in on this localhost site.`
      }
      return null
    }
  }

  try {
    const meRes = await safeGet('player/me')
    const freshUser = meRes?.data?.data
    if (freshUser && typeof freshUser === 'object') {
      Object.assign(userData, freshUser)
      await userStore.setData(userData)
    }

    const [battingRes, bullpenRes, cageRes, trainingRes, createdRes, fitnessRes] = await Promise.all([
      safeGet('player/sessions/batting'),
      safeGet('player/sessions/bullpen'),
      safeGet('player/sessions/cage'),
      safeGet('player/sessions/training'),
      safeGet('player/sessions/created'),
      developmentPlayerId.value ? safeGet(`player/fitness/${developmentPlayerId.value}`) : Promise.resolve(null),
    ])

    battingSessions.value = rowsFromApi(battingRes)
    bullpenSessions.value = rowsFromApi(bullpenRes)
    cageSessions.value = rowsFromApi(cageRes)
    trainingSessions.value = rowsFromApi(trainingRes)
    createdSessions.value = rowsFromApi(createdRes)

    const cageStatResults = await Promise.all(
      cageSessions.value
        .filter((s) => Boolean(s?.id))
        .map((s) => axiosGet(`statistics/${s.id}/cage`).catch(() => null))
    )

    const rowsFromStats = cageStatResults
      .flatMap((res) => extractCageRowsFromStatsResponse(res?.data))
      .filter((r) => r && typeof r === 'object')

    cageStatRows.value = rowsFromStats

    // Training-mode sessions (Weighted / Exit Velocity / Long Toss). The list
    // endpoint eager-loads the ball relations, but only when they belong to the
    // authenticated user — otherwise they arrive empty. When a session has no
    // embedded rows, pull them explicitly via the correct per-mode stats route
    // (same pattern as cage above). Guarded so we never double-count sessions
    // that already carried embedded rows.
    const hasEmbeddedRows = (s) =>
      [s?.weight_ball, s?.exit_velocity, s?.long_toss, s?.ball_x_ball, s?.results]
        .some((a) => Array.isArray(a) && a.length > 0)
    await Promise.all(
      trainingSessions.value
        .filter((s) => s?.id && trainingModeEndpoint(s) && !hasEmbeddedRows(s))
        .map(async (s) => {
          const res = await axiosGet(`statistics/${s.id}/${trainingModeEndpoint(s)}`).catch(() => null)
          const rows = res?.data?.data?.ball_x_ball ?? res?.data?.ball_x_ball ?? []
          s.ball_x_ball = Array.isArray(rows) ? rows : []
        })
    )
    // Re-assign to trigger the Weighted / EV / Long Toss breakdown computeds.
    trainingSessions.value = [...trainingSessions.value]

    const fit = fitnessRes?.data?.data
    const fitnessRows = Array.isArray(fit) ? fit : (fit ? [fit] : [])
    playerFitnessLatest.value = fitnessRows[0] || null
    maybeOpenSleepCheckin(fitnessRows)
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <Layout>
    <div
      v-if="sleepCheckinOpen"
      class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 px-4"
    >
      <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#101634] p-5 text-white shadow-2xl">
        <h2 class="text-2xl font-black">Daily Recovery Check-In</h2>
        <p class="mt-2 text-sm font-semibold leading-6 text-white/65">
          Log today's sleep so your player metrics stay current.
        </p>

        <label class="mt-5 block text-xs font-black uppercase tracking-widest text-white/60">
          Hours slept
        </label>
        <input
          v-model="sleepCheckinForm.sleep_hours"
          type="number"
          min="0"
          max="24"
          step="0.1"
          class="mt-2 h-12 w-full rounded-xl border border-white/15 bg-white/10 px-3 text-lg font-black text-white outline-none focus:border-[#ff2d55]"
          placeholder="8"
        />

        <label class="mt-4 block text-xs font-black uppercase tracking-widest text-white/60">
          Sleep quality (1-5)
        </label>
        <input
          v-model="sleepCheckinForm.sleep_quality_1_to_5"
          type="number"
          min="1"
          max="5"
          step="1"
          class="mt-2 h-12 w-full rounded-xl border border-white/15 bg-white/10 px-3 text-lg font-black text-white outline-none focus:border-[#ff2d55]"
          placeholder="4"
        />

        <div class="mt-5 flex justify-end gap-3">
          <button
            class="h-11 rounded-xl border border-white/15 bg-white/10 px-5 text-sm font-black text-white/70"
            :disabled="sleepCheckinSaving"
            @click="sleepCheckinOpen = false"
          >
            Later
          </button>
          <button
            class="h-11 rounded-xl bg-[#ff2d55] px-6 text-sm font-black text-white disabled:opacity-60"
            :disabled="sleepCheckinSaving"
            @click="saveSleepCheckin"
          >
            {{ sleepCheckinSaving ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
    <div class="min-h-full bg-[#060b14] text-white px-4 py-5 lg:px-6">
      <div class="mx-auto max-w-6xl space-y-4">
        <div
          v-if="connectionMessage"
          class="rounded-lg border border-[#ff2d55]/50 bg-[#ff2d55]/15 px-4 py-3 text-sm text-white"
        >
          {{ connectionMessage }}
        </div>
        <section class="grid grid-cols-1 lg:grid-cols-[380px_1fr] gap-4">
          <div class="space-y-4">
            <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-[#0b1230]/75 p-4">
              <div class="pointer-events-none absolute inset-0 opacity-20 blur-2xl scale-110" :style="profileBgStyle"></div>

              <div class="relative z-10">
              <h2 class="text-lg font-black tracking-wide mb-3">Player Profile</h2>

              <div class="mb-3 overflow-hidden rounded-xl border border-white/20 bg-white/10">
                <div class="relative h-52 w-full">
                  <img :src="playerImageSrc" :alt="playerName" class="h-full w-full object-cover object-top" @error="onAvatarError" />
                  <div class="absolute inset-0 bg-gradient-to-r from-[#050b1f]/90 via-[#050b1f]/65 to-[#050b1f]/20"></div>

                  <p class="absolute right-4 top-3 text-4xl font-black text-white/95">#{{ coachProfile.jersey !== '—' ? coachProfile.jersey : '' }}</p>

                  <div class="absolute left-4 right-4 bottom-3">
                    <div class="min-w-0 pr-2">
                      <p class="truncate text-3xl font-black tracking-wide text-white">{{ playerName }}</p>
                      <p class="mt-1 text-xl text-white/90">Height: {{ profile.height }}</p>
                      <p class="text-xl text-white/90">Weight: {{ profile.weight }}</p>
                      <p class="text-xl text-white/90">Position: {{ profile.position }}</p>
                      <p class="mt-1 truncate text-sm text-white/70">{{ strengthLine }}</p>
                      <p class="truncate text-sm text-white/70">{{ speedLine }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mb-3 space-y-2">
                <button
                  type="button"
                  @click="openDevelopmentProfile"
                  class="flex w-full items-center justify-center rounded-xl border border-emerald-400/50 bg-emerald-500/15 px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
                >
                  Development Profile
                </button>

                <button
                  type="button"
                  @click="openPlayerMetricsModal"
                  class="flex w-full items-center justify-center rounded-xl border border-[#ff2d55]/60 bg-[#ff2d55] px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
                >
                  Player Metrics ›
                </button>

                <button
                  type="button"
                  @click="openSessionReports"
                  class="flex w-full items-center justify-center rounded-xl border border-[#8C234A]/80 bg-[#8C234A] px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
                >
                  Session Reports ›
                </button>

                <button
                  type="button"
                  @click="openAssessmentReports"
                  class="flex w-full items-center justify-center rounded-xl border border-sky-400/60 bg-sky-500/20 px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
                >
                  Assessment Reports ›
                </button>

                <button
                  type="button"
                  @click="openArmCare"
                  class="flex w-full items-center justify-center rounded-xl border border-amber-400/60 bg-amber-500/15 px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
                >
                  Arm Care ›
                </button>
              </div>

              <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                  <p class="text-[10px] uppercase tracking-widest text-white/55">Name</p>
                  <p class="mt-1 font-bold">{{ coachProfile.fullName }}</p>
                </div>
                <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                  <p class="text-[10px] uppercase tracking-widest text-white/55">Jersey</p>
                  <p class="mt-1 font-bold">{{ coachProfile.jersey }}</p>
                </div>
                <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                  <p class="text-[10px] uppercase tracking-widest text-white/55">B/T</p>
                  <p class="mt-1 font-bold">{{ coachProfile.bats }} / {{ coachProfile.throws }}</p>
                </div>
                <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                  <p class="text-[10px] uppercase tracking-widest text-white/55">Grad Year</p>
                  <p class="mt-1 font-bold">{{ coachProfile.gradYear }}</p>
                </div>
                <div class="rounded-lg border border-white/10 bg-white/5 p-3 col-span-2">
                  <p class="text-[10px] uppercase tracking-widest text-white/55">School / Team</p>
                  <p class="mt-1 font-bold">{{ schoolTeamText }}</p>
                </div>
              </div>
              </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-[#0b1230]/75 p-4">
              <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-black tracking-wide">Player Metrics</h2>
                <RouterLink
                  to="#"
                  @click.prevent="openPlayerMetricsModal"
                  class="rounded-lg border border-[#ff2d55]/60 bg-[#ff2d55]/25 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white"
                >
                  Open
                </RouterLink>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                  <p class="text-[10px] uppercase tracking-widest text-white/60">Batting</p>
                  <p class="text-2xl font-black mt-1">{{ sessionCounts.batting }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                  <p class="text-[10px] uppercase tracking-widest text-white/60">Bullpen</p>
                  <p class="text-2xl font-black mt-1">{{ sessionCounts.bullpen }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                  <p class="text-[10px] uppercase tracking-widest text-white/60">Cage</p>
                  <p class="text-2xl font-black mt-1">{{ sessionCounts.cage }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                  <p class="text-[10px] uppercase tracking-widest text-white/60">Training</p>
                  <p class="text-2xl font-black mt-1">{{ sessionCounts.training }}</p>
                </div>
              </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-[#0b1230]/75 p-4">
              <h2 class="text-lg font-black tracking-wide mb-3">Recap</h2>
              <div v-if="recentSessions.length === 0" class="text-sm text-white/50">No recent sessions found.</div>
              <div v-else class="space-y-2">
                <button
                  v-for="s in recentSessions"
                  :key="s.id"
                  type="button"
                  @click="openRecapReport(s)"
                  class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left transition"
                  :class="[
                    recapTypeStyle(s).bg,
                    recapTypeStyle(s).border,
                    s._reportType ? 'hover:border-[#ff2d55]/60 hover:bg-[#0f173f] cursor-pointer' : 'opacity-70 cursor-default'
                  ]"
                >
                  <div class="min-w-0">
                    <span
                      class="mb-1 inline-flex rounded-md border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                      :class="[recapTypeStyle(s).bg, recapTypeStyle(s).border, recapTypeStyle(s).text]"
                    >
                      {{ recapTypeStyle(s).label }}
                    </span>
                    <p class="text-sm font-bold text-white/90 truncate">{{ s._label }}</p>
                    <p class="text-xs text-white/60">{{ (s._date || '').toString().slice(0, 10) || '—' }}</p>
                  </div>
                  <p class="text-xs font-black text-white/40" v-if="s._reportType">REPORT ›</p>
                </button>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-[#0b1230]/75 p-4">
            <div class="sticky top-0 z-30 -mx-4 mb-4 border-b border-white/10 bg-[#0b1230]/95 px-4 pb-3 pt-1 backdrop-blur">
              <div class="mb-4 flex justify-center">
                <div class="relative flex w-full max-w-sm rounded-2xl border border-white/15 bg-[#0b1230] p-1.5 shadow-lg">
                  <span
                    class="absolute inset-y-1.5 left-1.5 w-[calc(50%-0.375rem)] rounded-xl bg-[#ff2d55] shadow-md transition-transform duration-300 ease-out"
                    :class="activeStatTab === 'armCare' ? 'translate-x-full' : 'translate-x-0'"
                  ></span>
                  <button
                    type="button"
                    class="relative z-10 flex-1 rounded-xl py-3.5 text-base font-black uppercase tracking-wide transition-colors duration-200"
                    :class="activeStatTab !== 'armCare' ? 'text-white' : 'text-white/55'"
                    @click="activeStatTab === 'armCare' && toggleArmCare()"
                  >
                    Stats
                  </button>
                  <button
                    type="button"
                    class="relative z-10 flex-1 rounded-xl py-3.5 text-base font-black uppercase tracking-wide transition-colors duration-200"
                    :class="activeStatTab === 'armCare' ? 'text-white' : 'text-white/55'"
                    @click="activeStatTab !== 'armCare' && toggleArmCare()"
                  >
                    Arm Care
                  </button>
                </div>
              </div>
              <div v-if="activeStatTab !== 'armCare'" class="flex flex-wrap justify-center gap-2">
                <button
                  v-for="tab in statTabs"
                  :key="tab.key"
                  class="rounded-full border px-4 py-2 text-sm font-black"
                  :class="activeStatTab === tab.key ? 'border-[#ff2d55] bg-[#ff2d55] text-white' : 'border-white/20 bg-white/5 text-white/65'"
                  @click="activeStatTab = tab.key"
                >
                  {{ tab.label }}
                </button>
              </div>
            </div>

          <div v-if="loading" class="py-10 text-center text-white/50">Loading player stats…</div>

          <div v-else class="space-y-3">
            <ArmCarePanel v-if="activeStatTab === 'armCare'" />

            <template v-else>
            <div
              v-if="activeStatTab === 'bp'"
              class="rounded-xl border border-white/10 bg-white/5 px-4 py-3"
            >
              <p class="text-xs font-black tracking-widest uppercase text-white/60">
                Contact Metrics ({{ battingBreakdown.swings }} swings)
              </p>
            </div>

            <template v-if="activeStatTab === 'bp'">
              <div v-if="battingBreakdown.swings < 1" class="rounded-xl border border-white/10 bg-white/5 px-4 py-6 text-center text-sm text-white/45">
                Log BP sessions to unlock detailed contact metrics.
              </div>

              <template v-else>
                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                  <div class="mb-2 flex items-center justify-between gap-3">
                    <p class="text-xs font-black tracking-wider uppercase text-white/70">Hard Contact %</p>
                    <p class="text-sm font-black text-white">{{ battingBreakdown.hardPct ?? '—' }}<span v-if="battingBreakdown.hardPct !== null" class="text-white/65 ml-1">%</span></p>
                  </div>
                  <div class="h-2.5 rounded-full bg-white/10 overflow-hidden"><div class="h-full rounded-full bg-[#22c55e]" :style="{ width: `${Math.max(0, Math.min(100, Number(battingBreakdown.hardPct || 0)))}%` }"></div></div>
                </div>

                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                  <div class="mb-2 flex items-center justify-between gap-3">
                    <p class="text-xs font-black tracking-wider uppercase text-white/70">Miss %</p>
                    <p class="text-sm font-black text-white">{{ battingBreakdown.missPct ?? '—' }}<span v-if="battingBreakdown.missPct !== null" class="text-white/65 ml-1">%</span></p>
                  </div>
                  <div class="h-2.5 rounded-full bg-white/10 overflow-hidden"><div class="h-full rounded-full bg-[#ff2d55]" :style="{ width: `${Math.max(0, Math.min(100, Number(battingBreakdown.missPct || 0)))}%` }"></div></div>
                </div>

                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                  <div class="mb-2 flex items-center justify-between gap-3">
                    <p class="text-xs font-black tracking-wider uppercase text-white/70">Avg Exit Velocity</p>
                    <p class="text-sm font-black text-white">{{ battingBreakdown.avgEV ?? '—' }}<span v-if="battingBreakdown.avgEV !== null" class="text-white/65 ml-1">mph</span></p>
                  </div>
                  <div class="h-2.5 rounded-full bg-white/10 overflow-hidden"><div class="h-full rounded-full bg-[#facc15]" :style="{ width: `${Math.max(0, Math.min(100, ((Number(battingBreakdown.avgEV || 0) - 40) / 60) * 100))}%` }"></div></div>
                </div>

                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                  <div class="mb-2 flex items-center justify-between gap-3">
                    <p class="text-xs font-black tracking-wider uppercase text-white/70">Top Exit Velocity</p>
                    <p class="text-sm font-black text-white">{{ battingBreakdown.maxEV ?? '—' }}<span v-if="battingBreakdown.maxEV !== null" class="text-white/65 ml-1">mph</span></p>
                  </div>
                  <div class="h-2.5 rounded-full bg-white/10 overflow-hidden"><div class="h-full rounded-full bg-[#22c55e]" :style="{ width: `${Math.max(0, Math.min(100, ((Number(battingBreakdown.maxEV || 0) - 50) / 60) * 100))}%` }"></div></div>
                </div>

                <div v-if="battingBreakdown.sprayTotal >= 3" class="rounded-xl border border-white/10 bg-white/5 p-4">
                  <p class="mb-3 text-xs font-black tracking-widest uppercase text-white/60">Spray Distribution ({{ battingBreakdown.sprayTotal }} balls in play)</p>
                  <div class="h-6 rounded-md overflow-hidden bg-white/10 flex">
                    <div v-if="(battingBreakdown.lfPct || 0) > 0" class="h-full bg-[#ff2d55]" :style="{ width: `${Math.max(0, Math.min(100, Number(battingBreakdown.lfPct || 0)))}%` }"></div>
                    <div v-if="(battingBreakdown.cfPct || 0) > 0" class="h-full bg-[#3498DB]" :style="{ width: `${Math.max(0, Math.min(100, Number(battingBreakdown.cfPct || 0)))}%` }"></div>
                    <div v-if="(battingBreakdown.rfPct || 0) > 0" class="h-full bg-[#2ECC71]" :style="{ width: `${Math.max(0, Math.min(100, Number(battingBreakdown.rfPct || 0)))}%` }"></div>
                  </div>
                  <div class="mt-2 flex items-center justify-between text-xs font-black">
                    <span class="text-[#ff2d55]">Left {{ battingBreakdown.lfPct ?? 0 }}%</span>
                    <span class="text-[#3498DB]">Center {{ battingBreakdown.cfPct ?? 0 }}%</span>
                    <span class="text-[#2ECC71]">Right {{ battingBreakdown.rfPct ?? 0 }}%</span>
                  </div>
                </div>

                <div v-if="battingBreakdown.trajTotal >= 3" class="rounded-xl border border-white/10 bg-white/5 p-4">
                  <p class="mb-3 text-xs font-black tracking-widest uppercase text-white/60">Batted Ball Profile ({{ battingBreakdown.trajTotal }} contact balls)</p>
                  <div class="h-6 rounded-md overflow-hidden bg-white/10 flex">
                    <div v-if="(battingBreakdown.gbPct || 0) > 0" class="h-full bg-[#F39C12]" :style="{ width: `${Math.max(0, Math.min(100, Number(battingBreakdown.gbPct || 0)))}%` }"></div>
                    <div v-if="(battingBreakdown.ldPct || 0) > 0" class="h-full bg-[#2ECC71]" :style="{ width: `${Math.max(0, Math.min(100, Number(battingBreakdown.ldPct || 0)))}%` }"></div>
                    <div v-if="(battingBreakdown.fbPct || 0) > 0" class="h-full bg-[#3498DB]" :style="{ width: `${Math.max(0, Math.min(100, Number(battingBreakdown.fbPct || 0)))}%` }"></div>
                    <div v-if="(battingBreakdown.pfPct || 0) > 0" class="h-full bg-[#9B59B6]" :style="{ width: `${Math.max(0, Math.min(100, Number(battingBreakdown.pfPct || 0)))}%` }"></div>
                  </div>
                  <div class="mt-2 flex flex-wrap items-center justify-between gap-y-1 text-xs font-black">
                    <span class="text-[#F39C12]">GB {{ battingBreakdown.gbPct ?? 0 }}%</span>
                    <span class="text-[#2ECC71]">LD {{ battingBreakdown.ldPct ?? 0 }}%</span>
                    <span class="text-[#3498DB]">FB {{ battingBreakdown.fbPct ?? 0 }}%</span>
                    <span class="text-[#9B59B6]">PF {{ battingBreakdown.pfPct ?? 0 }}%</span>
                  </div>
                </div>

                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                  <div class="mb-2 flex items-center justify-between gap-3">
                    <p class="text-xs font-black tracking-wider uppercase text-white/70">Damage Score™</p>
                    <p class="text-sm font-black text-white">{{ battingBreakdown.damageScore ?? '—' }}</p>
                  </div>
                  <div class="h-2.5 rounded-full bg-white/10 overflow-hidden"><div class="h-full rounded-full bg-[#ff2d55]" :style="{ width: `${Math.max(0, Math.min(100, Number(battingBreakdown.damageScore || 0)))}%` }"></div></div>
                </div>

                <div v-if="battingBreakdown.zonePerf" class="rounded-xl border border-white/10 bg-white/5 p-4">
                  <p class="mb-3 text-xs font-black tracking-widest uppercase text-white/60">Zone Hard Contact %</p>
                  <div class="space-y-2">
                    <div v-for="z in [
                      { label: 'Upper Zone', value: battingBreakdown.zonePerf.upperHardPct },
                      { label: 'Lower Zone', value: battingBreakdown.zonePerf.lowerHardPct },
                      { label: 'Inner Half', value: battingBreakdown.zonePerf.innerHardPct },
                      { label: 'Outer Half', value: battingBreakdown.zonePerf.outerHardPct },
                    ]" :key="z.label" class="rounded-lg border border-white/10 bg-[#0b1230] p-3">
                      <div class="mb-1 flex items-center justify-between gap-3"><p class="text-xs font-bold text-white/80">{{ z.label }}</p><p class="text-xs font-black text-white">{{ z.value ?? '—' }}<span v-if="z.value !== null">%</span></p></div>
                      <div class="h-2.5 rounded-full bg-white/10 overflow-hidden"><div class="h-full rounded-full bg-[#22c55e]" :style="{ width: `${Math.max(0, Math.min(100, Number(z.value || 0)))}%` }"></div></div>
                    </div>
                  </div>
                </div>

                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                  <div class="mb-2 flex items-center justify-between gap-3">
                    <p class="text-xs font-black tracking-wider uppercase text-white/70">Competitive Swing %</p>
                    <p class="text-sm font-black text-white">{{ battingBreakdown.compPct ?? '—' }}<span v-if="battingBreakdown.compPct !== null" class="text-white/65 ml-1">%</span></p>
                  </div>
                  <div class="h-2.5 rounded-full bg-white/10 overflow-hidden"><div class="h-full rounded-full bg-[#facc15]" :style="{ width: `${Math.max(0, Math.min(100, Number(battingBreakdown.compPct || 0)))}%` }"></div></div>
                </div>

                <div v-if="battingBreakdown.consistency" class="rounded-xl border border-white/10 bg-white/5 p-4">
                  <p class="mb-3 text-xs font-black tracking-widest uppercase text-white/60">Round Consistency (first 10 vs last 10)</p>
                  <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between"><span class="text-white/65">Hard Contact Change</span><span class="font-black text-white">{{ battingBreakdown.consistency.hardDrop > 0 ? `-${battingBreakdown.consistency.hardDrop}` : battingBreakdown.consistency.hardDrop < 0 ? `+${Math.abs(battingBreakdown.consistency.hardDrop)}` : '→ Same' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-white/65">Miss Change</span><span class="font-black text-white">{{ battingBreakdown.consistency.missDiff > 0 ? `+${battingBreakdown.consistency.missDiff} more` : battingBreakdown.consistency.missDiff < 0 ? `${battingBreakdown.consistency.missDiff} fewer` : '→ Same' }}</span></div>
                    <div v-if="battingBreakdown.consistency.evDrop !== null" class="flex items-center justify-between"><span class="text-white/65">EV Drop</span><span class="font-black text-white">{{ battingBreakdown.consistency.evDrop > 0 ? `-${battingBreakdown.consistency.evDrop} mph` : battingBreakdown.consistency.evDrop < 0 ? `+${Math.abs(battingBreakdown.consistency.evDrop)} mph` : '→ Steady' }}</span></div>
                  </div>
                </div>
              </template>
            </template>

            <div
              v-if="activeStatTab === 'bullpen'"
              class="rounded-xl border border-white/10 bg-white/5 px-4 py-3"
            >
              <p class="text-xs font-black tracking-widest uppercase text-white/60">
                Command Metrics ({{ bullpenBreakdown.total }} pitches)
              </p>
            </div>

            <div
              v-if="activeStatTab === 'cage'"
              class="rounded-xl border border-white/10 bg-white/5 px-4 py-3"
            >
              <p class="text-xs font-black tracking-widest uppercase text-white/60">
                Cage Metrics ({{ cageBreakdown.swings }} swings)
              </p>
            </div>

            <div
              v-if="activeTrainingReport"
              class="rounded-xl border border-white/10 bg-white/5 p-4"
            >
              <div class="mb-3">
                <p class="text-xs font-black tracking-widest uppercase text-white/70">{{ activeTrainingReport.title }}</p>
                <p class="mt-1 text-[11px] font-bold text-white/40">{{ activeTrainingReport.subtitle }}</p>
              </div>
              <div
                v-if="lineChartRows(activeTrainingReport).length > 1"
                class="mb-4 rounded-lg bg-black/15 pt-1"
              >
                <svg class="h-[150px] w-full" viewBox="0 0 320 150" preserveAspectRatio="none">
                  <line
                    v-for="ratio in [0, 0.5, 1]"
                    :key="`grid-${ratio}`"
                    x1="32"
                    x2="306"
                    :y1="14 + ratio * 108"
                    :y2="14 + ratio * 108"
                    stroke="rgba(255,255,255,0.12)"
                    stroke-width="1"
                  />
                  <path
                    v-for="series in lineChartSeries(activeTrainingReport)"
                    :key="series.key"
                    :d="lineChartPath(activeTrainingReport, series)"
                    fill="none"
                    :stroke="series.color || '#ff2d55'"
                    :stroke-width="series.dashed ? 2 : 3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    :stroke-dasharray="series.dashed ? '6 5' : null"
                    vector-effect="non-scaling-stroke"
                  />
                  <template
                    v-for="series in lineChartSeries(activeTrainingReport)"
                    :key="`points-${series.key}`"
                  >
                    <circle
                      v-for="(row, index) in lineChartRows(activeTrainingReport)"
                      v-show="Number.isFinite(Number(row[series.key])) && Number(row[series.key]) > 0"
                      :key="`${series.key}-${row.label}`"
                      :cx="lineChartX(activeTrainingReport, index)"
                      :cy="lineChartY(activeTrainingReport, row[series.key])"
                      r="3.5"
                      :fill="series.color || '#ff2d55'"
                      stroke="#101634"
                      stroke-width="1.5"
                      vector-effect="non-scaling-stroke"
                    />
                  </template>
                  <text
                    v-for="(row, index) in lineChartRows(activeTrainingReport)"
                    :key="`label-${row.label}`"
                    :x="lineChartX(activeTrainingReport, index)"
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
                    v-for="series in lineChartSeries(activeTrainingReport)"
                    :key="`legend-${series.key}`"
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
                  v-for="row in activeTrainingReport.rows.filter(r => Number.isFinite(Number(r.value)) && Number(r.value) > 0)"
                  :key="row.label"
                >
                  <div class="mb-1 flex items-center justify-between gap-3">
                    <p class="text-[11px] font-black uppercase text-white/65">{{ row.label }}</p>
                    <p class="text-xs font-black text-white">{{ fmtReport(row.value, activeTrainingReport.suffix) }}</p>
                  </div>
                  <div class="relative h-2.5 overflow-hidden rounded-full bg-white/10">
                    <div
                      class="h-full rounded-full"
                      :style="{
                        width: `${Math.max(5, Math.min(100, (Number(row.value) / Math.max(1, Number(activeTrainingReport.max))) * 100))}%`,
                        backgroundColor: row.color || '#ff2d55',
                      }"
                    ></div>
                    <div
                      v-if="Number.isFinite(Number(row.expected))"
                      class="absolute top-[-2px] h-4 w-0.5 bg-white/85"
                      :style="{ left: `${Math.max(0, Math.min(100, (Number(row.expected) / Math.max(1, Number(activeTrainingReport.max))) * 100))}%` }"
                    ></div>
                  </div>
                </div>
              </div>
              <div class="mt-4 grid grid-cols-2 gap-2">
                <div
                  v-for="tile in activeTrainingReport.tiles"
                  :key="tile.label"
                  class="rounded-lg border border-white/10 bg-[#0b1230]/80 p-3"
                >
                  <p class="text-[10px] font-black uppercase tracking-wide text-white/45">{{ tile.label }}</p>
                  <p class="mt-1 text-lg font-black text-white">{{ tile.value }}</p>
                  <p v-if="tile.sub" class="mt-0.5 text-[10px] font-bold text-white/35">{{ tile.sub }}</p>
                </div>
              </div>
            </div>

            <div
              v-if="activeStatTab !== 'bp'"
              v-for="row in metricRows"
              :key="row.label"
              class="rounded-xl border border-white/10 bg-white/5 px-4 py-3"
            >
              <div class="mb-2 flex items-center justify-between gap-3">
                <p class="text-xs font-black tracking-wider uppercase text-white/70">{{ row.label }}</p>
                <p class="text-sm font-black text-white">
                  {{ row.value ?? '—' }}
                  <span v-if="row.value !== null && row.value !== undefined && row.unit" class="text-white/65 ml-1">{{ row.unit }}</span>
                </p>
              </div>
              <div class="h-2.5 rounded-full bg-white/10 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all"
                  :style="{ width: `${barPercent(row)}%`, backgroundColor: barColor(row) }"
                ></div>
              </div>
            </div>

            <div
              v-if="activeStatTab === 'bullpen' && bullpenBreakdown.pitchTypeStats.length > 0"
              class="rounded-xl border border-white/10 bg-white/5 p-4"
            >
              <p class="mb-3 text-xs font-black tracking-widest uppercase text-white/60">Strike % by Pitch Type</p>
              <div class="space-y-3">
                <div v-for="pt in bullpenBreakdown.pitchTypeStats" :key="`strike-${pt.type}`" class="rounded-lg border border-white/10 bg-[#0b1230] p-3">
                  <div class="mb-1 flex items-center justify-between gap-3">
                    <p class="text-xs font-bold text-white/80">{{ pt.type }} {{ pt.strikes }}/{{ pt.count }} strikes</p>
                    <p class="text-xs font-black text-white">{{ pt.strikePct ?? '—' }}<span v-if="pt.strikePct !== null">%</span></p>
                  </div>
                  <div class="h-2.5 rounded-full bg-white/10 overflow-hidden">
                    <div class="h-full rounded-full bg-emerald-400" :style="{ width: `${Math.max(0, Math.min(100, Number(pt.strikePct || 0)))}%` }"></div>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="activeStatTab === 'bullpen' && bullpenBreakdown.pitchTypeStats.some((pt) => pt.avgMph !== null)"
              class="rounded-xl border border-white/10 bg-white/5 p-4"
            >
              <p class="mb-3 text-xs font-black tracking-widest uppercase text-white/60">Avg Velo by Pitch Type</p>
              <div class="space-y-3">
                <div v-for="pt in bullpenBreakdown.pitchTypeStats.filter((x) => x.avgMph !== null)" :key="`velo-${pt.type}`" class="rounded-lg border border-white/10 bg-[#0b1230] p-3">
                  <div class="mb-1 flex items-center justify-between gap-3">
                    <p class="text-xs font-bold text-white/80">{{ pt.type }}</p>
                    <p class="text-xs font-black text-white">{{ pt.avgMph }} mph</p>
                  </div>
                  <div class="h-2.5 rounded-full bg-white/10 overflow-hidden">
                    <div
                      class="h-full rounded-full bg-amber-400"
                      :style="{ width: `${Math.max(0, Math.min(100, ((Number(pt.avgMph || 0) - 50) / (100 - 50)) * 100))}%` }"
                    ></div>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="activeStatTab === 'bullpen' && bullpenBreakdown.missPattern.length > 0"
              class="rounded-xl border border-white/10 bg-white/5 p-4"
            >
              <p class="mb-3 text-xs font-black tracking-widest uppercase text-white/60">Miss Location Pattern</p>
              <div class="space-y-2">
                <div v-for="miss in bullpenBreakdown.missPattern" :key="miss.label" class="rounded-lg border border-white/10 bg-[#0b1230] p-3">
                  <div class="mb-1 flex items-center justify-between gap-3">
                    <p class="text-xs font-bold text-white/80">{{ miss.label }}</p>
                    <p class="text-xs font-black text-white">{{ miss.pct }}%</p>
                  </div>
                  <div class="h-2.5 rounded-full bg-white/10 overflow-hidden">
                    <div class="h-full rounded-full bg-[#ff2d55]" :style="{ width: `${Math.max(0, Math.min(100, Number(miss.pct || 0)))}%` }"></div>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="activeStatTab === 'cage' && cageBreakdown.sprayTotal > 0"
              class="rounded-xl border border-white/10 bg-white/5 p-4"
            >
              <p class="mb-3 text-xs font-black tracking-widest uppercase text-white/60">Spray Efficiency ({{ cageBreakdown.sprayTotal }} swings)</p>

              <div class="h-6 rounded-md overflow-hidden bg-white/10 flex">
                <div
                  v-if="(cageBreakdown.pullPct || 0) > 0"
                  class="h-full bg-[#ff2d55]"
                  :style="{ width: `${Math.max(0, Math.min(100, Number(cageBreakdown.pullPct || 0)))}%` }"
                ></div>
                <div
                  v-if="(cageBreakdown.centerPct || 0) > 0"
                  class="h-full bg-[#3498DB]"
                  :style="{ width: `${Math.max(0, Math.min(100, Number(cageBreakdown.centerPct || 0)))}%` }"
                ></div>
                <div
                  v-if="(cageBreakdown.oppoPct || 0) > 0"
                  class="h-full bg-[#2ECC71]"
                  :style="{ width: `${Math.max(0, Math.min(100, Number(cageBreakdown.oppoPct || 0)))}%` }"
                ></div>
              </div>

              <div class="mt-2 flex items-center justify-between text-xs font-black">
                <span class="text-[#ff2d55]">Pull {{ cageBreakdown.pullPct ?? 0 }}%</span>
                <span class="text-[#3498DB]">Center {{ cageBreakdown.centerPct ?? 0 }}%</span>
                <span class="text-[#2ECC71]">Oppo {{ cageBreakdown.oppoPct ?? 0 }}%</span>
              </div>
            </div>

            <div
              v-if="activeStatTab === 'exitVel' && (exitVelBreakdown.gbPct || 0) + (exitVelBreakdown.ldPct || 0) + (exitVelBreakdown.fbPct || 0) > 0"
              class="rounded-xl border border-white/10 bg-white/5 p-4"
            >
              <p class="mb-3 text-xs font-black tracking-widest uppercase text-white/60">
                Batted Ball Profile (EV Training)
              </p>

              <div class="h-6 rounded-md overflow-hidden bg-white/10 flex">
                <div
                  v-if="(exitVelBreakdown.gbPct || 0) > 0"
                  class="h-full bg-[#191C4A]"
                  :style="{ width: `${Math.max(0, Math.min(100, Number(exitVelBreakdown.gbPct || 0)))}%` }"
                ></div>
                <div
                  v-if="(exitVelBreakdown.ldPct || 0) > 0"
                  class="h-full bg-[#8C234A]"
                  :style="{ width: `${Math.max(0, Math.min(100, Number(exitVelBreakdown.ldPct || 0)))}%` }"
                ></div>
                <div
                  v-if="(exitVelBreakdown.fbPct || 0) > 0"
                  class="h-full bg-[#ff2d55]"
                  :style="{ width: `${Math.max(0, Math.min(100, Number(exitVelBreakdown.fbPct || 0)))}%` }"
                ></div>
              </div>

              <div class="mt-2 flex items-center justify-between text-xs font-black">
                <span class="text-[#191C4A]">GB {{ exitVelBreakdown.gbPct ?? 0 }}%</span>
                <span class="text-[#8C234A]">LD {{ exitVelBreakdown.ldPct ?? 0 }}%</span>
                <span class="text-[#ff2d55]">FB {{ exitVelBreakdown.fbPct ?? 0 }}%</span>
              </div>
            </div>

            <div
              v-if="activeStatTab === 'longToss' && (longTossBreakdown.hopTotal || 0) > 0"
              class="rounded-xl border border-white/10 bg-white/5 p-4"
            >
              <p class="mb-3 text-xs font-black tracking-widest uppercase text-white/60">
                Hop Distribution ({{ longTossBreakdown.hopTotal }} throws)
              </p>

              <div class="h-6 rounded-md overflow-hidden bg-white/10 flex">
                <div
                  v-if="(longTossBreakdown.hop0Pct || 0) > 0"
                  class="h-full bg-[#ff2d55]"
                  :style="{ width: `${Math.max(0, Math.min(100, Number(longTossBreakdown.hop0Pct || 0)))}%` }"
                ></div>
                <div
                  v-if="(longTossBreakdown.hop1Pct || 0) > 0"
                  class="h-full bg-[#8C234A]"
                  :style="{ width: `${Math.max(0, Math.min(100, Number(longTossBreakdown.hop1Pct || 0)))}%` }"
                ></div>
                <div
                  v-if="(longTossBreakdown.hop2Pct || 0) > 0"
                  class="h-full bg-[#3498DB]"
                  :style="{ width: `${Math.max(0, Math.min(100, Number(longTossBreakdown.hop2Pct || 0)))}%` }"
                ></div>
                <div
                  v-if="(longTossBreakdown.hop3Pct || 0) > 0"
                  class="h-full bg-[#2ECC71]"
                  :style="{ width: `${Math.max(0, Math.min(100, Number(longTossBreakdown.hop3Pct || 0)))}%` }"
                ></div>
              </div>

              <div class="mt-2 flex flex-wrap items-center justify-between gap-y-1 text-xs font-black">
                <span class="text-[#ff2d55]">0 Hops {{ longTossBreakdown.hop0Pct ?? 0 }}%</span>
                <span class="text-[#8C234A]">1 Hop {{ longTossBreakdown.hop1Pct ?? 0 }}%</span>
                <span class="text-[#3498DB]">2 Hops {{ longTossBreakdown.hop2Pct ?? 0 }}%</span>
                <span class="text-[#2ECC71]">3 Hops {{ longTossBreakdown.hop3Pct ?? 0 }}%</span>
              </div>
            </div>

            <div class="pt-2">
              <div class="flex items-center justify-center gap-8 text-[10px] font-black tracking-widest uppercase">
                <span class="text-[#ef4444]">Poor</span>
                <span class="text-[#facc15]">Average</span>
                <span class="text-[#22c55e]">Great</span>
              </div>
            </div>
            </template>
          </div>
          </div>
        </section>
      </div>
    </div>

    <ModalPlayer
      v-if="isOpenPlayerMetricsModal"
      :isOpen="isOpenPlayerMetricsModal"
      :item="modalPlayerItem"
      :response="playerMetricsRows"
      :score="playerMetricsScore"
      @closeModal="isOpenPlayerMetricsModal = false"
    />
  </Layout>
</template>
