<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const { axiosGet } = useAxiosAuth()

const loading = ref(false)
const animPaused = ref(false)
const items = ref([])

const WEIGHTED_VELO_SCALES = {
  3: { min: 60, max: 110 },
  4: { min: 58, max: 108 },
  5: { min: 55, max: 100 },
  6: { min: 52, max: 95 },
  7: { min: 50, max: 92 },
  9: { min: 45, max: 85 },
}

const asArray = (val) => {
  if (Array.isArray(val)) return val
  if (val && typeof val === 'object') return Object.values(val)
  return []
}

const extractSessionList = (response) => {
  const payload = response?.data
  const cands = [
    payload?.data?.data,
    payload?.data?.items,
    payload?.data?.results,
    payload?.data,
    payload?.items,
    payload?.results,
  ]

  for (const c of cands) {
    if (Array.isArray(c)) return c
  }

  const dig = (obj) => {
    if (!obj || typeof obj !== 'object') return null
    for (const v of Object.values(obj)) {
      if (Array.isArray(v)) return v
    }
    for (const v of Object.values(obj)) {
      if (v && typeof v === 'object') {
        const found = dig(v)
        if (found) return found
      }
    }
    return null
  }

  return dig(payload) || []
}

const toNum = (v) => {
  const n = Number(v)
  return Number.isFinite(n) ? n : null
}

const readNum = (row, keys = []) => {
  for (const key of keys) {
    const n = toNum(row?.[key])
    if (n !== null && n > 0) return n
  }
  return null
}

const top3 = (values = []) => {
  const nums = values
    .map((v) => Number(v))
    .filter((v) => Number.isFinite(v) && v > 0)
    .sort((a, b) => b - a)
    .slice(0, 3)
  return nums
}

const takeRows = (session, keys = []) => {
  const rows = []
  keys.forEach((key) => {
    rows.push(...asArray(session?.[key]))
  })
  return rows
}

const normalizeMode = (modeLike) => {
  const mode = String(modeLike || '').trim().toUpperCase()
  if (!mode) return ''
  if (mode.includes('LONG')) return 'LT'
  return mode
}

const shortSessionLabel = (session) => {
  const type = String(session?.type || '').toUpperCase()
  const mode = normalizeMode(session?.mode || session?.modes)
  if (type === 'B') return 'BP'
  if (type === 'P') return 'PEN'
  if (type === 'C') return 'CAGE'
  if (type === 'L') return 'LIVEAB'
  if (type === 'T') {
    if (mode === 'EV') return 'EV'
    if (mode === 'LT') return 'LT'
    if (mode === 'WB') return 'WB'
    if (mode === 'HP') return 'HP'
    return 'TRAIN'
  }
  return type || 'SESSION'
}

const fmtDate = (raw) => {
  const d = raw ? new Date(raw) : null
  if (!d || Number.isNaN(d.getTime())) return ''
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${mm}/${dd}`
}

const computeWeightScore = (trainingSessions = []) => {
  const groups = {}

  trainingSessions.forEach((session) => {
    const mode = normalizeMode(session?.mode || session?.modes)
    const likelyWB = mode === 'WB' || asArray(session?.weight_ball).length > 0
    if (!likelyWB) return

    const rows = [
      ...takeRows(session, ['weight_ball', 'weightball', 'weight_balls']),
      ...takeRows(session, ['practice_match_result', 'results']),
    ]

    rows.forEach((row) => {
      const weight = readNum(row, ['weight', 'weighted_ball', 'ball_weight'])
      const velo = readNum(row, ['velocity', 'weighted_velocity', 'miles_per_hour', 'mph'])
      if (!weight || !velo) return
      const key = Number(weight)
      if (!groups[key]) groups[key] = []
      groups[key].push(velo)
    })
  })

  let weightedTotal = 0
  let countTotal = 0
  Object.keys(groups).forEach((weightKey) => {
    const weight = Number(weightKey)
    const velos = groups[weight] || []
    if (!velos.length) return

    const avg = velos.reduce((a, b) => a + b, 0) / velos.length
    const scale = WEIGHTED_VELO_SCALES[weight] || { min: 45, max: 110 }
    const ratio = (avg - scale.min) / ((scale.max - scale.min) || 1)
    const score = Math.max(0, Math.min(100, ratio * 100))

    weightedTotal += score * velos.length
    countTotal += velos.length
  })

  if (!countTotal) return null
  return Math.round(weightedTotal / countTotal)
}

const computeWeightScoreFromRows = (rows = []) => {
  const groups = {}

  rows.forEach((row) => {
    const weight = readNum(row, ['weight', 'weighted_ball', 'ball_weight'])
    const velo = readNum(row, ['velocity', 'weighted_velocity', 'miles_per_hour', 'mph'])
    if (!weight || !velo) return
    const key = Number(weight)
    if (!groups[key]) groups[key] = []
    groups[key].push(velo)
  })

  let weightedTotal = 0
  let countTotal = 0

  Object.keys(groups).forEach((weightKey) => {
    const weight = Number(weightKey)
    const velos = groups[weight] || []
    if (!velos.length) return

    const avg = velos.reduce((a, b) => a + b, 0) / velos.length
    const scale = WEIGHTED_VELO_SCALES[weight] || { min: 45, max: 110 }
    const ratio = (avg - scale.min) / ((scale.max - scale.min) || 1)
    const score = Math.max(0, Math.min(100, ratio * 100))

    weightedTotal += score * velos.length
    countTotal += velos.length
  })

  if (!countTotal) return null
  return Math.round(weightedTotal / countTotal)
}

const load = async () => {
  if (loading.value) return
  loading.value = true
  try {
    const [battingRes, bullpenRes, trainingRes, createdRes] = await Promise.all([
      axiosGet('player/sessions/batting').catch(() => null),
      axiosGet('player/sessions/bullpen').catch(() => null),
      axiosGet('player/sessions/training').catch(() => null),
      axiosGet('player/sessions/created').catch(() => null),
    ])

    const batting = extractSessionList(battingRes)
    const bullpen = extractSessionList(bullpenRes)
    const training = extractSessionList(trainingRes)
    const created = extractSessionList(createdRes)

    const evVelos = batting.flatMap((session) => {
      const rows = takeRows(session, ['practice_match_result', 'results', 'batting', 'ball_x_ball', 'ball_by_ball_results'])
      return rows
        .map((row) => readNum(row?.batting || row, ['velocity', 'exit_velocity', 'launch_angle_velocity', 'mph']))
        .filter((v) => v !== null)
    })

    const pitchVelos = bullpen.flatMap((session) => {
      const rows = takeRows(session, ['bullpen', 'pitchers', 'practice_match_result', 'results', 'pitches', 'ball_by_ball_results'])
      return rows
        .map((row) => readNum(row, ['miles_per_hour', 'pitch_velocity', 'velocity', 'mph']))
        .filter((v) => v !== null)
    })

    const evTop3 = top3(evVelos)
    const pitchTop3 = top3(pitchVelos)
    const weightScore = computeWeightScore(training)

    const wbSessionScores = training
      .filter((session) => {
        const mode = normalizeMode(session?.mode || session?.modes)
        return mode === 'WB' || asArray(session?.weight_ball).length > 0
      })
      .map((session) => {
        const rows = [
          ...takeRows(session, ['weight_ball', 'weightball', 'weight_balls']),
          ...takeRows(session, ['practice_match_result', 'results']),
        ]
        return computeWeightScoreFromRows(rows)
      })
      .filter((v) => v !== null)

    const weightTop3 = top3(wbSessionScores)

    const last5 = created
      .slice()
      .sort((a, b) => new Date(b?.created_at || b?.updated_at || 0) - new Date(a?.created_at || a?.updated_at || 0))
      .slice(0, 5)
      .map((s) => {
        const label = shortSessionLabel(s)
        const dateTag = fmtDate(s?.created_at || s?.updated_at || s?.date)
        const balls = Number(s?.total_balls || s?.total_ball || s?.balls || 0)
        return `${label}${dateTag ? ` ${dateTag}` : ''}${balls > 0 ? ` (${balls})` : ''}`
      })

    const nextItems = [
      `EV Top 3 ${evTop3.length ? `${evTop3.map((v) => v.toFixed(1)).join(' | ')} mph` : '-'}`,
      `Pitch Top 3 ${pitchTop3.length ? `${pitchTop3.map((v) => v.toFixed(1)).join(' | ')} mph` : '-'}`,
      `FMTRX Weight Top 3 ${weightTop3.length ? weightTop3.join(' | ') : (weightScore !== null ? weightScore : '-')}`,
      `Last 5 Sessions ${last5.length ? last5.join(' | ') : 'none yet'}`,
    ]

    items.value = nextItems
  } finally {
    loading.value = false
  }
}

let refreshTimer = null
onMounted(() => {
  load()
  refreshTimer = setInterval(load, 5 * 60 * 1000)
})
onUnmounted(() => clearInterval(refreshTimer))

const tickerItems = computed(() => items.value)
</script>

<template>
  <div
    v-if="tickerItems.length > 0"
    class="ticker-bar"
    @mouseenter="animPaused = true"
    @mouseleave="animPaused = false"
    aria-label="Player performance ticker"
    role="marquee"
  >
    <div class="ticker-label">
      <span class="ticker-label-icon">📈</span>
      <span class="ticker-label-text">PLAYER LIVE</span>
    </div>

    <div class="ticker-divider"></div>

    <div class="ticker-viewport">
      <div class="ticker-track" :class="{ 'ticker-track--paused': animPaused }">
        <template v-for="pass in 2" :key="pass">
          <span
            v-for="(item, idx) in tickerItems"
            :key="`${pass}-${idx}`"
            class="ticker-item"
          >
            <span class="ticker-item-value">{{ item }}</span>
            <span class="ticker-sep" aria-hidden="true">·</span>
          </span>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ticker-bar {
  display: flex;
  align-items: center;
  height: 36px;
  overflow: hidden;
  background: linear-gradient(90deg, rgba(192,0,0,0.18) 0%, rgba(0,20,60,0.55) 100%);
  border-bottom: 1px solid rgba(255,255,255,0.08);
  border-top: 1px solid rgba(192,0,0,0.2);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  position: relative;
  z-index: 36;
  width: 100%;
  flex-shrink: 0;
}

.ticker-label {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  padding: 0 10px 0 12px;
  height: 100%;
  background: rgba(192,0,0,0.85);
  border-right: 1px solid rgba(255,255,255,0.15);
}

.ticker-label-icon {
  font-size: 13px;
  line-height: 1;
}

.ticker-label-text {
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.12em;
  color: #fff;
  white-space: nowrap;
  text-transform: uppercase;
}

.ticker-divider {
  width: 1px;
  height: 14px;
  background: rgba(255,255,255,0.18);
  flex-shrink: 0;
}

.ticker-viewport {
  flex: 1;
  overflow: hidden;
  height: 100%;
  display: flex;
  align-items: center;
  mask-image: linear-gradient(90deg, transparent 0px, black 28px, black calc(100% - 28px), transparent 100%);
  -webkit-mask-image: linear-gradient(90deg, transparent 0px, black 28px, black calc(100% - 28px), transparent 100%);
}

.ticker-track {
  display: flex;
  align-items: center;
  white-space: nowrap;
  animation: ticker-scroll 55s linear infinite;
  will-change: transform;
}

.ticker-track--paused {
  animation-play-state: paused;
}

@keyframes ticker-scroll {
  0%   { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

.ticker-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0 10px;
}

.ticker-item-value {
  font-weight: 800;
  color: rgba(255,255,255,0.95);
  font-size: 13px;
  letter-spacing: 0.01em;
}

.ticker-sep {
  color: rgba(255,255,255,0.22);
  font-size: 16px;
  padding-left: 10px;
}
</style>
