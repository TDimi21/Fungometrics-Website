// Pure mapping / formatting helpers for the player home dashboard.
// No axios, no stores — everything here takes data in and returns data out,
// so it can be unit-tested directly (tests/frontend/playerHomeAdapter.spec.js).

import { parseDOB, resolveBornValue } from '@/utils/dob.js'
import {
  BAR_COLOR_AVG,
  BAR_COLOR_GREAT,
  BAR_COLOR_POOR,
  SESSION_MODE_LABEL_MAP,
  SESSION_REPORT_TYPE,
  SESSION_TYPE_COLOR,
  SESSION_TYPE_LABEL_MAP,
  WEIGHTED_VELO_SCALES,
} from './constants.js'

// ── Generic value helpers ───────────────────────────────────────────────

export const pick = (...vals) => {
  for (const v of vals) {
    if (v === null || v === undefined) continue
    if (typeof v === 'string' && v.trim() === '') continue
    return v
  }
  return null
}

export const asDisplay = (v, fallback = '—') => {
  const x = pick(v)
  return x == null ? fallback : String(x)
}

export const asArray = (val) => {
  if (Array.isArray(val)) return val
  if (val && typeof val === 'object') return Object.values(val)
  return []
}

// ── Formatters ──────────────────────────────────────────────────────────

export const normalizeImageSrc = (src, apiBase = import.meta.env?.VITE_API_ENDPOINT || import.meta.env?.API_ENDPOINT || '') => {
  // Reject non-string junk (e.g. a File object left in the model) that would
  // stringify to "[object File]" and render as a broken image.
  if (!src || typeof src !== 'string') return null
  const raw = src.trim()
  if (!raw || raw.startsWith('[object')) return null
  if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('data:') || raw.startsWith('blob:')) return raw
  // Relative path → resolve against the API host (where uploaded images live),
  // NOT the current page origin (which is just the SPA shell).
  const host = String(apiBase).replace(/\/api\/?$/, '').replace(/\/$/, '')
  const path = raw.startsWith('/') ? raw : `/${raw}`
  return host ? `${host}${path}` : path
}

export const formatHeight = (ft, inch, composed) => {
  const ftNum = Number(ft)
  const inNum = Number(inch)
  if (Number.isFinite(ftNum)) {
    return `${ftNum}' ${Number.isFinite(inNum) ? inNum : 0}"`
  }
  if (typeof composed === 'string' && composed.trim()) return composed.trim()
  return '—'
}

export const formatGradYearShort = (yearLike) => {
  if (yearLike == null) return null
  const raw = String(yearLike).trim()
  if (!raw) return null
  const digits = raw.replace(/[^0-9]/g, '')
  if (digits.length < 2) return null
  return `'${digits.slice(-2).padStart(2, '0')}`
}

export const deriveGradYearFromBirthdate = (...birthCandidates) => {
  const raw = birthCandidates.find((v) => v != null && String(v).trim() !== '')
  if (!raw) return null
  const dob = parseDOB(raw)
  if (!dob) return null
  return formatGradYearShort(dob.getFullYear() + 18)
}

export const formatPositions = (positionsRaw) => {
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

export const normalizeMode = (modeLike) => {
  const rawValue = modeLike && typeof modeLike === 'object'
    ? (modeLike?.modes || modeLike?.mode || modeLike?.mode_type || modeLike?.type_mode || modeLike?.training_mode || modeLike?.practice_mode || '')
    : modeLike
  const mode = String(rawValue || '').trim().toUpperCase().replace(/[_-]/g, ' ')
  if (!mode) return null
  const compact = mode.replace(/\s+/g, '')
  if (['EV', 'EXITVELOCITY', 'EXITVELO'].includes(compact)) return 'EV'
  if (['WB', 'WEIGHTBALL', 'WEIGHTEDBALL', 'WEIGHTEDBALLS'].includes(compact)) return 'WB'
  if (['LT', 'LONGTOSS'].includes(compact) || mode.includes('LONG TOSS')) return 'LT'
  if (compact === 'HP' || compact === 'HITORPITCH') return 'HP'
  return compact
}

// ── Latest-metric coalescing (fitness rows are newest-first) ────────────

// Latest recorded value for a lift/speed metric. Rows are newest-first, so we
// scan them in order and return the first REAL value — skipping 0/blank, which
// for a 1RM or a dash time means "not recorded that day" rather than an actual
// result. This is why a player who logged a bench max last week (but only EV
// today) still shows it.
export const metricValue = (rows, ...keys) => {
  for (const row of asArray(rows)) {
    for (const key of keys) {
      const v = row?.[key]
      if (v == null) continue
      const s = String(v).trim()
      if (!s || s === 'null' || s === 'undefined') continue
      const n = Number(s)
      if (Number.isFinite(n) && n === 0) continue // 0 = not recorded for a lift/dash
      return s
    }
  }
  return '-'
}

export const buildStrengthLine = (rows) => {
  const bench = metricValue(rows, 'bench_press', 'bench', 'benchpress')
  const dl = metricValue(rows, 'dead_lift', 'deadlift', 'dead')
  const bs = metricValue(rows, 'back_squat', 'backsquat', 'back')
  const fs = metricValue(rows, 'front_squat', 'frontsquat', 'front')
  const clean = metricValue(rows, 'power_clean', 'clean', 'powerclean')
  return `Bench ${bench} · DL ${dl} · BS ${bs} · FS ${fs} · Clean ${clean}`
}

export const buildSpeedLine = (rows) => {
  const forty = metricValue(rows, 'yd_40_dash', 'dash_40', 'forty', '40_time')
  const sixty = metricValue(rows, 'yd_60_dash', 'dash_60', 'sixty', '60_time')
  return `40 ${forty} · 60 ${sixty}`
}

// ── Dashboard-summary payload mapping ───────────────────────────────────

export const EMPTY_BREAKDOWNS = {
  batting: {
    swings: 0, maxEV: null, avgEV: null, hardPct: null, missPct: null,
    sprayTotal: 0, lfPct: null, cfPct: null, rfPct: null,
    gbPct: null, ldPct: null, fbPct: null, pfPct: null, trajTotal: 0,
    damageScore: null, zonePerf: null, compPct: null, consistency: null,
  },
  bullpen: {
    total: 0, maxFB: null, avgFB: null, strikePct: null, firstStrikePct: null,
    locationAccuracyPct: null, competitivePct: null, qualityPct: null,
    pitchTypeStats: [], missPattern: [],
  },
  cage: {
    swings: 0, avgEV: null, maxEV: null, hardPct: null, barrelPct: null,
    avgLA: null, laConsistency: null, sweetPct: null, swingQualityPct: null,
    pullPct: null, centerPct: null, oppoPct: null, sprayTotal: 0, damage: null,
  },
  weighted: { throws: 0, maxVelo: null, avgVelo: null, byWeight: [] },
  exitVel: {
    swings: 0, maxEV: null, avgEV: null, hardPct: null,
    gbPct: null, ldPct: null, fbPct: null,
    gbAvgEV: null, ldAvgEV: null, fbAvgEV: null,
    gbCount: 0, ldCount: 0, fbCount: 0, trajTotal: 0,
  },
  longToss: {
    throws: 0, maxDist: null, avgDist: null,
    hop0: null, hop1: null, hop2: null, hop3: null,
    hop0Count: 0, hop1Count: 0, hop2Count: 0, hop3Count: 0, hopTotal: 0,
    hop0Pct: null, hop1Pct: null, hop2Pct: null, hop3Pct: null,
  },
}

export const EMPTY_COUNTS = {
  batting: 0, bullpen: 0, cage: 0, training: 0, weighted: 0, exitVel: 0, longToss: 0,
}

const sessionLabel = (type, mode) => {
  if (type === 'T') return SESSION_MODE_LABEL_MAP[mode] || 'Training Mode'
  return SESSION_TYPE_LABEL_MAP[type] || 'Training Mode'
}

export const mapRecentSession = (session) => {
  const type = String(session?.type || '').toUpperCase()
  const mode = normalizeMode(session?.mode)
  return {
    id: session?.id ?? null,
    _label: sessionLabel(type, mode),
    _date: session?.date || null,
    _sourceType: type,
    _mode: mode,
    _reportType: type === 'T' ? (SESSION_REPORT_TYPE[mode] || null) : (SESSION_REPORT_TYPE[type] || null),
    total_balls: Number(session?.total_balls || 0),
    is_completed: session?.is_completed ? 2 : 1,
    end_note: session?.end_note ?? null,
  }
}

// Maps the GET player/dashboard-summary payload into the shapes the dashboard
// renders, filling safe defaults for anything missing.
export const mapDashboardSummary = (payload) => {
  const breakdowns = payload?.breakdowns || {}
  const merged = {}
  for (const key of Object.keys(EMPTY_BREAKDOWNS)) {
    merged[key] = { ...EMPTY_BREAKDOWNS[key], ...(breakdowns[key] || {}) }
  }
  // The template also reads byType on the bullpen breakdown (legacy alias).
  merged.bullpen.byType = merged.bullpen.pitchTypeStats

  return {
    counts: { ...EMPTY_COUNTS, ...(payload?.counts || {}) },
    breakdowns: merged,
    recentSessions: asArray(payload?.recent_sessions).map(mapRecentSession).filter((s) => s.id),
  }
}

export const recapTypeKey = (session) => {
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

export const recapTypeStyle = (session) => SESSION_TYPE_COLOR[recapTypeKey(session)] || SESSION_TYPE_COLOR.live

// ── Metric bar rows per stat tab ────────────────────────────────────────

export const metricRowsForTab = (tab, breakdowns) => {
  if (tab === 'bp') return []
  if (tab === 'bullpen') {
    const b = breakdowns.bullpen
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
  if (tab === 'cage') {
    const c = breakdowns.cage
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
  if (tab === 'weighted') {
    const w = breakdowns.weighted
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
  if (tab === 'longToss') {
    const l = breakdowns.longToss
    return [
      { label: 'Max Distance', value: l.maxDist, unit: 'ft', min: 0, max: 400, thresholds: [150, 280] },
      { label: 'Avg Distance', value: l.avgDist, unit: 'ft', min: 0, max: 350, thresholds: [120, 250] },
      { label: 'Avg Dist (0 Hops)', value: l.hop0, unit: 'ft', min: 0, max: 400, thresholds: [200, 300] },
      { label: 'Avg Dist (1 Hop)', value: l.hop1, unit: 'ft', min: 0, max: 420, thresholds: [150, 330] },
      { label: 'Avg Dist (2 Hops)', value: l.hop2, unit: 'ft', min: 0, max: 430, thresholds: [150, 350] },
      { label: 'Avg Dist (3 Hops)', value: l.hop3, unit: 'ft', min: 0, max: 450, thresholds: [150, 390] },
    ]
  }
  const e = breakdowns.exitVel
  return [
    { label: 'Max Exit Velocity', value: e.maxEV, unit: 'mph', min: 0, max: 115, thresholds: [70, 90] },
    { label: 'Avg Exit Velocity', value: e.avgEV, unit: 'mph', min: 0, max: 110, thresholds: [55, 75] },
    { label: 'Hard Hit %', value: e.hardPct, unit: '%', min: 0, max: 60, thresholds: [10, 35] },
    { label: 'Ground Ball %', value: e.gbPct, unit: '%', min: 0, max: 70, thresholds: [25, 45] },
    { label: 'Line Drive %', value: e.ldPct, unit: '%', min: 0, max: 70, thresholds: [20, 40] },
    { label: 'Fly Ball %', value: e.fbPct, unit: '%', min: 0, max: 70, thresholds: [20, 40] },
  ]
}

export const barPercent = (row) => {
  if (row.value == null || row.min == null || row.max == null) return 0
  const v = Number(row.value)
  if (!Number.isFinite(v)) return 0
  const p = ((v - row.min) / (row.max - row.min)) * 100
  return Math.max(0, Math.min(100, p))
}

export const barColor = (row) => {
  const value = Number(row.value)
  if (!Number.isFinite(value)) return 'rgba(255,255,255,0.4)'
  const p = barPercent(row)
  if (p < 100 / 3) return BAR_COLOR_POOR
  if (p < 200 / 3) return BAR_COLOR_AVG
  return BAR_COLOR_GREAT
}

export const clampPct = (value) => Math.max(0, Math.min(100, Number(value || 0)))

// ── Profile view models ─────────────────────────────────────────────────

export const buildProfile = (userData, fitnessLatest) => {
  const p = userData?.profile || {}
  const player = userData?.player || {}
  const wt = pick(fitnessLatest?.body_weight, p?.weight, player?.weight, userData?.weight)

  return {
    height: formatHeight(
      pick(player?.height_in_ft, userData?.ft),
      pick(player?.height_in_inch, userData?.inch),
      pick(p?.height, player?.height),
    ),
    weight: wt != null ? wt : '—',
    position: formatPositions(pick(userData?.positions, p?.positions, p?.position, player?.positions, player?.position)),
  }
}

export const buildCoachProfile = (userData) => {
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
}

export const buildSchoolTeamText = (coachProfile) => {
  const parts = [coachProfile?.school, coachProfile?.team]
    .map((v) => String(v || '').trim())
    .filter((v) => v && v !== '—')
  return parts.length ? parts.join(' · ') : '—'
}

export const buildModalPlayerItem = (userData, playerId) => {
  const profileData = userData?.profile || {}
  const playerData = userData?.player || {}

  const first = profileData?.first_name || userData?.name?.first || userData?.first_name || ''
  const last = profileData?.last_name || userData?.name?.last || userData?.last_name || ''
  const full = `${first} ${last}`.trim() || userData?.name?.full || userData?.name || 'Player'

  return {
    id: playerId || userData?.id || null,
    name: { first, last, full },
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
}

// ── Sleep check-in helpers ──────────────────────────────────────────────

export const todayDateKey = (now = new Date()) => {
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

export const fitnessDateKey = (value) => {
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

export const hasSleepLoggedToday = (rows = [], now = new Date()) => {
  const today = todayDateKey(now)
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

// ── Benchmark task helpers ──────────────────────────────────────────────

export const humanizeTaskValue = (value, fallback = '—') => {
  const raw = String(value || '').trim()
  if (!raw) return fallback
  return raw
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

export const benchmarkRefreshCompletionMessage = (refresh) => {
  const status = refresh?.refresh_status
  if (status === 'completed' || status === 'partial') {
    const signal = asArray(refresh?.changed_signals)[0]?.message
    return signal
      ? `Benchmark intelligence refreshed. ${signal}`
      : 'Benchmark intelligence refreshed.'
  }

  if (status === 'failed') {
    return 'Task completed. Benchmark refresh will update next time the dashboard loads.'
  }

  if (status === 'skipped' && asArray(refresh?.warnings).some((warning) => String(warning).toLowerCase().includes('pending coach review'))) {
    return 'Submitted for coach review. Benchmark intelligence will refresh after approval.'
  }

  return ''
}

export const taskStatusClass = (status) => ({
  assigned: 'border-sky-300/30 bg-sky-500/15 text-sky-100',
  in_progress: 'border-amber-300/30 bg-amber-500/15 text-amber-100',
  completed: 'border-emerald-300/30 bg-emerald-500/15 text-emerald-100',
  dismissed: 'border-white/10 bg-white/5 text-white/45',
}[status] || 'border-white/10 bg-white/5 text-white/60')

export const taskReviewStatusLabel = (status) => ({
  not_required: 'No Review Required',
  pending_review: 'Pending Coach Review',
  approved: 'Approved',
  rejected: 'Rejected',
  correction_requested: 'Correction Requested',
}[status] || humanizeTaskValue(status, 'Not Submitted'))

export const taskReviewStatusClass = (status) => ({
  pending_review: 'border-amber-300/30 bg-amber-500/15 text-amber-100',
  approved: 'border-emerald-300/30 bg-emerald-500/15 text-emerald-100',
  rejected: 'border-accent-2/30 bg-accent-2/10 text-red-100',
  correction_requested: 'border-sky-300/30 bg-sky-500/15 text-sky-100',
  not_required: 'border-white/10 bg-white/5 text-white/45',
}[status] || 'border-white/10 bg-white/5 text-white/45')

export const benchmarkTaskReviewNotice = (task) => {
  const status = task?.review_status
  if (status === 'pending_review') {
    return 'Your submission is waiting for coach review.'
  }
  if (status === 'approved') {
    return 'Approved by your coach. FMTRX benchmark intelligence can use this data.'
  }
  if (status === 'correction_requested') {
    return task?.correction_message || 'Your coach requested a correction. Open the task to update it.'
  }
  if (status === 'rejected') {
    return task?.rejection_reason || 'This submission was rejected by your coach.'
  }
  return ''
}

export const taskPriorityClass = (priority) => ({
  critical: 'text-accent-2',
  high: 'text-amber-200',
  medium: 'text-sky-200',
  low: 'text-white/55',
}[priority] || 'text-white/55')

export const formatTaskDate = (value) => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value).slice(0, 10)
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}
