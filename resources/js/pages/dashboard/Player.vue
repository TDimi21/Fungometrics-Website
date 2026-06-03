<script setup>
import { computed, onMounted, ref } from 'vue'
import Layout from '@/layout/Layout.vue'
import ModalPlayer from '@/components/dashboard/ModalPlayer.vue'
import { useUserStore } from '@/store/user'
import { useAxiosAuth } from '@/composables/axios-auth'
import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.png'

const { userData } = useUserStore()
const { axiosGet } = useAxiosAuth()

const loading = ref(false)
const activeTopTab = ref('stats')
const activeStatTab = ref('bp')
const isOpenPlayerMetricsModal = ref(false)
const isOpeningPlayerMetricsModal = ref(false)
const playerMetricsRows = ref([])
const playerMetricsScore = ref({})

const battingSessions = ref([])
const bullpenSessions = ref([])
const cageSessions = ref([])
const cageStatRows = ref([])
const trainingSessions = ref([])
const playerFitnessLatest = ref(null)

const statTabs = [
  { key: 'bp', label: 'BP Stats' },
  { key: 'bullpen', label: 'Bullpen' },
  { key: 'cage', label: 'Cage' },
  { key: 'weighted', label: 'Weighted' },
  { key: 'exitVel', label: 'Exit Velocity' },
  { key: 'longToss', label: 'Long Toss' },
]

const POOR = '#191C4A'
const AVG = '#8C234A'
const GREAT = '#ff2d55'

const asArray = (val) => {
  if (Array.isArray(val)) return val
  if (val && typeof val === 'object') return Object.values(val)
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
  const raw = String(src || '').trim()
  if (!raw) return null
  if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('data:') || raw.startsWith('blob:')) return raw
  return raw.startsWith('/') ? raw : `/${raw}`
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

const developmentRoute = computed(() => {
  if (!developmentPlayerId.value) {
    return { name: 'development.index' }
  }

  return {
    name: 'dashboard',
    query: {
      tab: 'overview',
      devOnly: '1',
      devPlayerId: developmentPlayerId.value,
      playerName: playerName.value,
    },
  }
})

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
      axiosGet(`coach/statistics/${pid}`).catch(() => null),
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

  return {
    fullName: asDisplay(pick([first, last].filter(Boolean).join(' '), userData?.name?.full, userData?.name, userData?.user?.name?.full), 'Player'),
    jersey: asDisplay(pick(player?.number_in_shirt, userData?.shirt_number, player?.jersey, p?.jersey)),
    bats: asDisplay(pick(player?.hit_side, userData?.hit_side, player?.batting_side, player?.bats, p?.bats)),
    throws: asDisplay(pick(player?.throw_side, userData?.throw_side, player?.throws, player?.throwing_side, p?.throws)),
    gradYear: asDisplay(pick(player?.graduation_year, userData?.graduation_year, player?.grad_year, p?.graduation_year)),
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
  const tagged = [
    ...battingSessions.value.map((s) => ({ ...s, _label: 'Batting Practice' })),
    ...bullpenSessions.value.map((s) => ({ ...s, _label: 'Bullpen Practice' })),
    ...cageSessions.value.map((s) => ({ ...s, _label: 'Cage Practice' })),
    ...trainingSessions.value.map((s) => ({ ...s, _label: String(s?.modes || s?.mode || 'Training').toUpperCase() })),
  ]
  return tagged
    .sort((a, b) => new Date(b?.created_at || b?.date || 0) - new Date(a?.created_at || a?.date || 0))
    .slice(0, 8)
})

const battingBreakdown = computed(() => {
  const rows = battingSessions.value.flatMap((s) => getSessionRows(s))
  const ev = rows.map((r) => parseNum(r, ['velocity', 'exit_velocity', 'miles_per_hour', 'mph'])).filter((v) => v !== null)
  const la = rows.map((r) => parseNum(r, ['launch_angle', 'angle'])).filter((v) => v !== null)
  const hardHit = ev.filter((v) => v >= 90).length
  const trajectories = rows.map(classifyTrajectory).filter(Boolean)
  const gb = trajectories.filter((t) => t === 'GB').length
  const ld = trajectories.filter((t) => t === 'LD').length
  const fb = trajectories.filter((t) => t === 'FB').length
  return {
    swings: rows.length,
    maxEV: fmt(maxOf(ev), 1),
    avgEV: fmt(avgOf(ev) ?? null, 1),
    hardPct: pct(hardHit, ev.length),
    avgLA: fmt(avgOf(la) ?? null, 1),
    gbPct: pct(gb, trajectories.length),
    ldPct: pct(ld, trajectories.length),
    fbPct: pct(fb, trajectories.length),
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
  const trajectories = rows.map(classifyTrajectory).filter(Boolean)
  const gb = trajectories.filter((t) => t === 'GB').length
  const ld = trajectories.filter((t) => t === 'LD').length
  const fb = trajectories.filter((t) => t === 'FB').length
  return {
    swings: rows.length,
    maxEV: fmt(maxOf(ev), 1),
    avgEV: fmt(avgOf(ev) ?? null, 1),
    hardPct: pct(hard, ev.length),
    gbPct: pct(gb, trajectories.length),
    ldPct: pct(ld, trajectories.length),
    fbPct: pct(fb, trajectories.length),
  }
})

const longTossBreakdown = computed(() => {
  const ltSessions = trainingSessions.value.filter((s) => {
    const mode = String(s?.modes || s?.mode || '').toUpperCase()
    return mode === 'LT' || mode.includes('LONG')
  })
  const rows = ltSessions.flatMap((s) => getSessionRows(s))
  const dists = rows.map((r) => parseNum(r, ['distance', 'dist', 'throw_distance', 'feet'])).filter((v) => v !== null)
  const hops = rows.map((r) => Number(r?.player_hop ?? r?.hop ?? r?.hops ?? NaN))
  const hopAvg = (n) => {
    const vals = rows
      .filter((r, idx) => Number(hops[idx]) === n)
      .map((r) => parseNum(r, ['distance', 'dist', 'throw_distance', 'feet']))
      .filter((v) => v !== null)
    return fmt(avgOf(vals) ?? null, 1)
  }
  return {
    throws: rows.length,
    maxDist: fmt(maxOf(dists), 1),
    avgDist: fmt(avgOf(dists) ?? null, 1),
    hop0: hopAvg(0),
    hop1: hopAvg(1),
    hop2: hopAvg(2),
    hop3: hopAvg(3),
  }
})

const metricRows = computed(() => {
  if (activeStatTab.value === 'bp') {
    const b = battingBreakdown.value
    return [
      { label: 'Max Exit Velocity', value: b.maxEV, unit: 'mph', min: 40, max: 110, thresholds: [75, 95] },
      { label: 'Avg Exit Velocity', value: b.avgEV, unit: 'mph', min: 40, max: 105, thresholds: [60, 80] },
      { label: 'Hard Contact %', value: b.hardPct, unit: '%', min: 0, max: 70, thresholds: [20, 40] },
      { label: 'Avg Launch Angle', value: b.avgLA, unit: '°', min: -5, max: 45, thresholds: [8, 25] },
      { label: 'Ground Ball %', value: b.gbPct, unit: '%', min: 0, max: 70, thresholds: [25, 45] },
      { label: 'Line Drive %', value: b.ldPct, unit: '%', min: 0, max: 70, thresholds: [20, 40] },
      { label: 'Fly Ball %', value: b.fbPct, unit: '%', min: 0, max: 70, thresholds: [20, 40] },
    ]
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
    return [
      { label: 'Total Throws', value: w.throws, unit: '', min: 0, max: 100, thresholds: [20, 50] },
      { label: 'Max Throw Velo', value: w.maxVelo, unit: 'mph', min: 45, max: 110, thresholds: [70, 88] },
      { label: 'Avg Throw Velo', value: w.avgVelo, unit: 'mph', min: 45, max: 100, thresholds: [65, 80] },
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
  if (!Number.isFinite(value) || !row.thresholds) return 'rgba(255,255,255,0.4)'
  if (value < row.thresholds[0]) return POOR
  if (value < row.thresholds[1]) return AVG
  return GREAT
}

const loadData = async () => {
  loading.value = true
  try {
    const [battingRes, bullpenRes, cageRes, trainingRes, fitnessRes] = await Promise.all([
      axiosGet('player/sessions/batting').catch(() => null),
      axiosGet('player/sessions/bullpen').catch(() => null),
      axiosGet('player/sessions/cage').catch(() => null),
      axiosGet('player/sessions/training').catch(() => null),
      developmentPlayerId.value ? axiosGet(`player/fitness/${developmentPlayerId.value}`).catch(() => null) : Promise.resolve(null),
    ])

    battingSessions.value = battingRes?.data?.data?.data || []
    bullpenSessions.value = bullpenRes?.data?.data?.data || []
    cageSessions.value = cageRes?.data?.data?.data || []
    trainingSessions.value = trainingRes?.data?.data?.data || []

    const cageStatResults = await Promise.all(
      cageSessions.value
        .filter((s) => Boolean(s?.id))
        .map((s) => axiosGet(`statistics/${s.id}/cage`).catch(() => null))
    )

    const rowsFromStats = cageStatResults
      .flatMap((res) => extractCageRowsFromStatsResponse(res?.data))
      .filter((r) => r && typeof r === 'object')

    cageStatRows.value = rowsFromStats

    const fit = fitnessRes?.data?.data
    playerFitnessLatest.value = Array.isArray(fit) ? (fit[0] || null) : (fit || null)
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <Layout>
    <div class="min-h-full bg-[#060b14] text-white px-4 py-5 lg:px-6">
      <div class="mx-auto max-w-6xl space-y-4">
        <section class="grid grid-cols-1 lg:grid-cols-[380px_1fr] gap-4">
          <div class="space-y-4">
            <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-[#0b1230]/75 p-4">
              <div class="pointer-events-none absolute inset-0 opacity-20 blur-2xl scale-110" :style="profileBgStyle"></div>

              <div class="relative z-10">
              <h2 class="text-lg font-black tracking-wide mb-3">Player Profile</h2>

              <div class="mb-3 rounded-xl border border-white/10 bg-white/5 p-3">
                <p class="text-xl font-black tracking-wide text-white">{{ playerName }}</p>
                <p class="mt-1 text-xs text-white/70">
                  Height: {{ profile.height }} · Weight: {{ profile.weight }} · Position: {{ profile.position }}
                </p>
              </div>

              <div class="space-y-2 mb-3">
                <div class="h-40 w-full rounded-xl border border-white/25 bg-white/10 overflow-hidden">
                  <img :src="playerImageSrc" :alt="playerName" class="h-full w-full object-cover object-top" />
                </div>

                <RouterLink
                  to="#"
                  @click.prevent="openPlayerMetricsModal"
                  class="flex w-full items-center justify-center rounded-xl border border-[#ff2d55]/60 bg-[#ff2d55]/25 px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
                >
                  Player Metrics
                </RouterLink>

                <RouterLink
                  :to="developmentRoute"
                  class="flex w-full items-center justify-center rounded-xl border border-emerald-400/60 bg-emerald-500/20 px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
                >
                  Development Profile
                </RouterLink>
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
                <div
                  v-for="s in recentSessions"
                  :key="s.id"
                  class="flex items-center justify-between rounded-lg border border-white/10 bg-[#0b1230] px-3 py-2"
                >
                  <p class="text-sm font-bold text-white/90">{{ s._label }}</p>
                  <p class="text-xs text-white/60">{{ (s.created_at || s.date || '').toString().slice(0, 10) || '—' }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-[#0b1230]/75 p-4">
            <h2 class="text-lg font-black tracking-wide mb-3">Stats</h2>
            <div class="mb-4 flex flex-wrap gap-2">
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

          <div v-if="loading" class="py-10 text-center text-white/50">Loading player stats…</div>

          <div v-else class="space-y-3">
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

            <div class="pt-2">
              <div class="flex items-center justify-center gap-8 text-[10px] font-black tracking-widest uppercase">
                <span class="text-[#191C4A]">Poor</span>
                <span class="text-[#8C234A]">Average</span>
                <span class="text-[#ff2d55]">Great</span>
              </div>
            </div>
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
