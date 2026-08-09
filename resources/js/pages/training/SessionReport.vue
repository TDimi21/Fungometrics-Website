<script setup>
/**
 * SessionReport.vue
 *
 * Unified session report page for all 7 training types:
 *   batting (B)  → GET /statistics/{id}/batting
 *   bullpen (P)  → GET /statistics/{id}/bullpen
 *   cage (C)     → GET /statistics/{id}/cage
 *   exit_vel (T/EV) → GET /statistics/{id}/exitvelocity
 *   long_toss (T/LT) → GET /statistics/{id}/longtoss
 *   weight_ball (T/WB) → GET /statistics/{id}/weightball
 *   live_ab (L) → GET /statistics/{id}/liveab
 *
 * Route: /session/report/:id/:type
 *   :type = batting | bullpen | cage | exit_velocity | long_toss | weight_ball | live_ab
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { ageFromDOB, resolveBornValue } from '@/utils/dob.js'
import { extractSessionRows } from '@/utils/sessionReportRows.js'
import { useAccessStore } from '@/store/access.js'
import StatRow from '@/components/session-report/SessionReportStatRow.vue'
import SegBar from '@/components/session-report/SessionReportSegmentBar.vue'

const route  = useRoute()
const router = useRouter()
const { axiosGet } = useAxiosAuth()

const sessionId   = route.params.id
const sessionType = route.params.type   // batting|bullpen|cage|exit_velocity|long_toss|weight_ball|live_ab
const access = useAccessStore()
const canScriptedBp = computed(() => access.canAccess('scripted_bp'))
const canScriptedBullpen = computed(() => access.canAccess('scripted_bullpen'))
const sessionDate = route.query.date ?? null
const sessionNote = route.query.note ?? null
const playerOnly = route.query.scope === 'player'

const loading   = ref(true)
const error     = ref(null)
const rawData   = ref(null)
const breakdown = ref(null)
const scriptedBp = ref(null)
const scriptedBullpen = ref(null)
const selectedBullpenPitch = ref(null)

// ─── Type config ──────────────────────────────────────────────────────────────
const TYPE_CONFIG = {
  batting:      { label: 'BATTING PRACTICE REPORT', abbr: 'FPS', color: '#F39C12', endpoint: 'batting' },
  bullpen:      { label: 'BULLPEN SESSION REPORT',  abbr: 'BPS', color: '#9B59B6', endpoint: 'bullpen' },
  cage:         { label: 'CAGE SESSION REPORT',     abbr: 'FCS', color: '#1ABC9C', endpoint: 'cage' },
  exit_velocity:{ label: 'EXIT VELOCITY REPORT',   abbr: 'EVS', color: '#E74C3C', endpoint: 'exitvelocity' },
  long_toss:    { label: 'LONG TOSS REPORT',        abbr: 'LTS', color: '#EC407A', endpoint: 'longtoss' },
  weight_ball:  { label: 'WEIGHTED BALL REPORT',    abbr: 'WBS', color: '#F1C40F', endpoint: 'weightball' },
  live_ab:      { label: 'LIVE AB SESSION REPORT',  abbr: 'LAS', color: '#E84393', endpoint: 'liveab' },
}

const cfg = computed(() => TYPE_CONFIG[sessionType] ?? TYPE_CONFIG.batting)

// ─── Helpers ─────────────────────────────────────────────────────────────────
const fmt = n => n != null ? Number(n).toFixed(1) : '—'
const pct = n => n != null ? Number(n).toFixed(1) + '%' : '—'
const mph = n => n != null ? Number(n).toFixed(1) + ' mph' : '—'
const ft  = n => n != null ? Number(n).toFixed(0) + ' ft' : '—'

function gradeColor(score) {
  if (score == null) return '#475569'
  if (score >= 80) return '#2ECC71'
  if (score >= 70) return '#27AE60'
  if (score >= 60) return '#F39C12'
  if (score >= 50) return '#E67E22'
  return '#E74C3C'
}

function gradeLabel(score) {
  if (score == null) return ''
  if (sessionType === 'live_ab') {
    if (score >= 90) return 'Elite Live AB'
    if (score >= 80) return 'Winning Rep'
    if (score >= 70) return 'Competitive Rep'
    if (score >= 60) return 'Development Rep'
    return 'Poor Rep'
  }
  if (sessionType === 'bullpen') {
    if (score >= 90) return '🔥 Game-Ready Command'
    if (score >= 80) return 'Strong Session'
    if (score >= 70) return 'Productive but Inconsistent'
    if (score >= 60) return 'Too Many Misses / Damage'
    return 'Reset / Mechanical Day'
  }
  if (score >= 90) return '🔥 Elite'
  if (score >= 80) return 'Strong'
  if (score >= 70) return 'Productive'
  if (score >= 60) return 'Development'
  return 'Needs Work'
}

function bpScriptGradeLabel(score) {
  if (score >= 90) return 'Elite'
  if (score >= 80) return 'Winning'
  if (score >= 70) return 'Competitive'
  if (score >= 60) return 'Development'
  return 'Needs Work'
}

function execGrade(pct) {
  if (pct == null) return null
  if (pct >= 85) return { label: 'Elite', color: '#22c55e' }
  if (pct >= 75) return { label: 'Winning', color: '#8b5cf6' }
  if (pct >= 65) return { label: 'Competitive', color: '#f59e0b' }
  if (pct >= 55) return { label: 'Development', color: '#f97316' }
  return { label: 'Needs Work', color: '#ef4444' }
}

// ─── Scripted BP scorecard helpers (app parity) ─────────────────────────────
const ROUND_WEIGHTS = {
  CONTACT: 1.0,
  BARREL: 1.1,
  LINE_DRIVE: 1.1,
  OPPO_GAP: 1.2,
  PULL_DAMAGE: 1.2,
  GAP_TO_GAP: 1.2,
  FASTBALL_HUNT: 1.3,
  OFFSPEED_ADJ: 1.3,
  SAC_FLY: 1.3,
  HIT_AND_RUN: 1.3,
  TWO_STRIKE: 1.5,
  BEHIND_COUNT: 1.5,
  PRESSURE: 1.6,
  CHAMPIONSHIP: 2.0,
}

const ROUND_LABELS = {
  BARREL: 'Barrel',
  OPPO_GAP: 'Oppo Gap',
  PULL_DAMAGE: 'Pull Damage',
  TWO_STRIKE: 'Two-Strike',
  SAC_FLY: 'Sac Fly',
  HIT_AND_RUN: 'Hit & Run',
  LINE_DRIVE: 'Line Drive',
  HARD_CONTACT: 'Hard Contact',
  FASTBALL_HUNT: 'Fastball Hunt',
  OFFSPEED_ADJ: 'Off-Speed Adj.',
  GAP_TO_GAP: 'Gap to Gap',
  INSIDE_PITCH: 'Inside Pitch',
  OUTSIDE_PITCH: 'Outside Pitch',
  FIRST_PITCH_ATK: 'First Pitch Attack',
  BEHIND_COUNT: 'Behind Count',
  ADVANTAGE_COUNT: 'Advantage Count',
  OPPO_POWER: 'Oppo Power',
  CONTACT: 'Contact',
  PRESSURE: 'Pressure',
  CHAMPIONSHIP: 'Championship',
}

function swingBasePoints(contactType) {
  const ct = String(contactType || '').toUpperCase()
  if (ct === 'H' || ct === 'HARD') return 6
  if (ct === 'A' || ct === 'AVG' || ct === 'AVERAGE') return 4
  if (ct === 'W' || ct === 'WEAK') return 2
  return 0
}

function computeScoreFromSwings(swings) {
  if (!swings?.length) return 0
  let totalPts = 0
  swings.forEach((s) => {
    totalPts += Math.min(10, Math.max(0, swingBasePoints(s.contact_type || s.quality_of_contact || '')))
  })
  return Math.min(100, Math.max(0, Math.round((totalPts / (swings.length * 10)) * 100)))
}

function computeWeightedScore(rounds) {
  if (!rounds?.length) return 0
  let weightSum = 0
  let scoreSum = 0
  rounds.forEach((r) => {
    const w = ROUND_WEIGHTS[r.round_type] ?? 1.0
    scoreSum += (Number(r.round_score) || 0) * w
    weightSum += w
  })
  return weightSum > 0 ? Math.round(scoreSum / weightSum) : 0
}

function buildBattersFromBalls(balls) {
  const map = {}
  ;(balls || []).forEach((s) => {
    const bid = String(s.batter_id ?? s.player_id ?? '')
    if (!bid) return
    if (!map[bid]) {
      const fn = s.profile?.first_name ?? s.first_name ?? ''
      const ln = s.profile?.last_name ?? s.last_name ?? ''
      const full = s.batter_name ?? `${fn} ${ln}`.trim()
      map[bid] = {
        batter_id: bid,
        batter_name: full || `Batter #${bid.slice(0, 6)}`,
        swings: [],
      }
    }
    map[bid].swings.push(s)
  })

  return Object.values(map).map((b) => {
    const score = computeScoreFromSwings(b.swings)
    const totalPts = b.swings.reduce(
      (sum, s) => sum + Math.min(10, swingBasePoints(s.contact_type || s.quality_of_contact || '')),
      0,
    )
    return {
      batter_id: b.batter_id,
      batter_name: b.batter_name,
      total_swings: b.swings.length,
      total_points: totalPts,
      max_points: b.swings.length * 10,
      avg_score: score,
      rounds: [],
    }
  })
}

function computeScriptedBpTeamScore(batters, allSwings) {
  const hasRounds = (batters || []).some((b) => (b.rounds || []).length > 0)
  if (hasRounds) {
    let totalPts = 0
    let maxPts = 0
    ;(batters || []).forEach((b) => {
      const swings = Number(b.total_swings) || 0
      totalPts += ((Number(b.avg_score) || 0) / 100) * swings * 10
      maxPts += swings * 10
    })
    return maxPts > 0 ? Math.round((totalPts / maxPts) * 100) : 0
  }
  return computeScoreFromSwings(allSwings || [])
}

// ─── Scripted bullpen scorecard helpers (app parity) ────────────────────────
function coordToXY(coord) {
  if (!coord || coord <= 0) return null
  const x = Math.ceil(coord / 60)
  const y = coord - (x - 1) * 60
  return { x, y }
}

function computePitchDistance(coordA, coordB) {
  const a = coordToXY(coordA)
  const b = coordToXY(coordB)
  if (!a || !b) return null
  return Math.sqrt((a.x - b.x) ** 2 + (a.y - b.y) ** 2)
}

function getZoneLabelFromCoord(coord) {
  if (!coord || coord < 1 || coord > 3600) return 'BALL'
  const rw = Math.ceil(coord / 60)
  const rh = coord - (rw - 1) * 60
  const MIN_V = 15
  const MAX_V = 45
  const MIN_H = 16
  const MAX_H = 45
  if (!(rh >= MIN_V && rh <= MAX_V && rw >= MIN_H && rw <= MAX_H)) return 'BALL'
  const vThird = (MAX_V - MIN_V + 1) / 3
  const hThird = (MAX_H - MIN_H + 1) / 3
  const v = rh < MIN_V + vThird ? 'T' : rh < MIN_V + vThird * 2 ? 'M' : 'B'
  const h = rw < MIN_H + hThird ? 'L' : rw < MIN_H + hThird * 2 ? 'C' : 'R'
  return v + h
}

function mapThrowType(t) {
  const m = { FB: 1, CH: 2, SL: 3, CV: 4 }
  return m[String(t || '').toUpperCase()] || 5
}

function calcAge(dobStr) {
  return ageFromDOB(dobStr)
}

function getAgeGroup(age) {
  if (!age || age <= 6) return '5-6'
  if (age <= 8) return '7-8'
  if (age <= 10) return '9-10'
  if (age <= 12) return '11-12'
  if (age <= 14) return '13-14'
  if (age <= 16) return '15-16'
  if (age <= 18) return '17-18'
  if (age <= 22) return '19-22'
  if (age <= 30) return '23-30'
  if (age <= 40) return '31-40'
  return '41+'
}

const VELO_TABLES = {
  '5-6': { 1: [45, 42, 39, 36, 33, 30, 27, 24, 21], 2: [35, 32, 29, 26, 23, 20, 17, 14, 11], 3: [36, 33, 30, 27, 24, 21, 18, 15, 12], 4: [33, 30, 27, 24, 21, 18, 15, 12, 9] },
  '7-8': { 1: [52, 49, 46, 43, 40, 37, 34, 31, 28], 2: [42, 39, 36, 33, 30, 27, 24, 21, 18], 3: [42, 39, 36, 33, 30, 27, 24, 21, 18], 4: [39, 36, 33, 30, 27, 24, 21, 18, 15] },
  '9-10': { 1: [60, 57, 54, 51, 48, 45, 42, 39, 36], 2: [50, 47, 44, 41, 38, 35, 32, 29, 26], 3: [50, 47, 44, 41, 38, 35, 32, 29, 26], 4: [47, 44, 41, 38, 35, 32, 29, 26, 23] },
  '11-12': { 1: [70, 67, 64, 61, 58, 55, 52, 49, 46], 2: [60, 57, 54, 51, 48, 45, 42, 39, 36], 3: [60, 57, 54, 51, 48, 45, 42, 39, 36], 4: [57, 54, 51, 48, 45, 42, 39, 36, 33] },
  '13-14': { 1: [82, 79, 76, 73, 70, 67, 64, 61, 58], 2: [72, 69, 66, 63, 60, 57, 54, 51, 48], 3: [72, 69, 66, 63, 60, 57, 54, 51, 48], 4: [69, 66, 63, 60, 57, 54, 51, 48, 45] },
  '15-16': { 1: [90, 87, 84, 81, 78, 75, 72, 69, 66], 2: [82, 79, 76, 73, 70, 67, 64, 61, 58], 3: [82, 79, 76, 73, 70, 67, 64, 61, 58], 4: [78, 75, 72, 69, 66, 63, 60, 57, 54] },
  '17-18': { 1: [94, 91, 88, 85, 82, 79, 76, 73, 70], 2: [86, 83, 80, 77, 74, 71, 68, 65, 62], 3: [86, 83, 80, 77, 74, 71, 68, 65, 62], 4: [82, 79, 76, 73, 70, 67, 64, 61, 58] },
  '19-22': { 1: [97, 94, 91, 88, 85, 82, 79, 76, 73], 2: [90, 87, 84, 81, 78, 75, 72, 69, 66], 3: [90, 87, 84, 81, 78, 75, 72, 69, 66], 4: [86, 83, 80, 77, 74, 71, 68, 65, 62] },
  '23-30': { 1: [100, 97, 94, 91, 88, 85, 82, 79, 76], 2: [92, 89, 86, 83, 80, 77, 74, 71, 68], 3: [92, 89, 86, 83, 80, 77, 74, 71, 68], 4: [88, 85, 82, 79, 76, 73, 70, 67, 64] },
  '31-40': { 1: [95, 92, 89, 86, 83, 80, 77, 74, 71], 2: [87, 84, 81, 78, 75, 72, 69, 66, 63], 3: [87, 84, 81, 78, 75, 72, 69, 66, 63], 4: [83, 80, 77, 74, 71, 68, 65, 62, 59] },
  '41+': { 1: [88, 85, 82, 79, 76, 73, 70, 67, 64], 2: [80, 77, 74, 71, 68, 65, 62, 59, 56], 3: [80, 77, 74, 71, 68, 65, 62, 59, 56], 4: [76, 73, 70, 67, 64, 61, 58, 55, 52] },
}

const SCRIPT_ZONE_LABELS = {
  TL: 'Hi-In',
  TC: 'Hi',
  TR: 'Hi-Out',
  ML: 'In',
  MC: 'Ctr',
  MR: 'Out',
  BL: 'Lo-In',
  BC: 'Lo',
  BR: 'Lo-Out',
  BALL: 'Ball',
}

const TYPE_LABELS = { 1: 'FB', 2: 'CH', 3: 'SL', 4: 'CV', 5: 'OTHER' }

function throwTypeLabel(typeId) {
  return TYPE_LABELS[Number(typeId)] || 'OTHER'
}

function veloScoreFromTable(mph, thresholds) {
  for (let i = 0; i < thresholds.length; i++) {
    if (mph >= thresholds[i]) return 10 - i
  }
  return 1
}

function veloScore(actualMph, typeId, pitcherAge) {
  if (!actualMph || actualMph <= 0) return 0
  const ageGroup = getAgeGroup(pitcherAge)
  const table = VELO_TABLES[ageGroup]
  const tId = [1, 2, 3, 4].includes(typeId) ? typeId : 1
  return veloScoreFromTable(actualMph, table[tId])
}

function locationScore(distance) {
  if (distance == null) return null
  return Math.max(0, Math.round(65 - distance))
}

function trajectoryScore(qualityId, trajectoryStr) {
  const id = qualityId != null ? Number(qualityId) : null
  if (id != null && !Number.isNaN(id) && id > 0) {
    if (id === 5) return 10
    if (id === 4) return 8
    if (id === 1) return 7
    if (id === 3) return 4
    if (id === 2) return 1
    return null
  }
  const t = String(trajectoryStr || '').toUpperCase().trim()
  if (t === 'SM' || t === 'S/M') return 10
  if (t === 'F' || t === 'FOUL') return 8
  if (t === 'GB' || t === 'GROUND BALL') return 7
  if (t === 'FB' || t === 'FLY' || t === 'FLY BALL') return 4
  if (t === 'LD' || t === 'LINE DRIVE') return 1
  return null
}

function competitiveScore(isStrike, distance, actualCoord) {
  let zone = null
  if (actualCoord && actualCoord > 0) zone = getZoneLabelFromCoord(actualCoord)

  if (zone && zone !== 'BALL') {
    if (['TL', 'TR', 'BL', 'BR'].includes(zone)) return 15
    if (['TC', 'ML', 'MR', 'BC'].includes(zone)) return 10
    return 7
  }
  if (distance != null) {
    if (distance <= 5) return 12
    if (distance <= 12) return 4
    return 0
  }
  if (isStrike) return 7
  return 0
}

function executionScore(pitch) {
  const hasLocation = pitch.distance_from_intended != null
  const hasVelo = pitch.miles_per_hour != null && pitch.miles_per_hour > 0
  const hasStrike = pitch.is_strike != null
  if (!hasLocation && !hasVelo && !hasStrike) return null

  const locVal = hasLocation ? locationScore(pitch.distance_from_intended) : null
  const veloVal = hasVelo ? veloScore(pitch.miles_per_hour, pitch.type_of_throw_id, pitch.pitcher_age) : null
  const trajVal = trajectoryScore(pitch.quality_of_throw_id, pitch.trajectory)
  const compVal = competitiveScore(pitch.is_strike, pitch.distance_from_intended, pitch.pitch_location)

  const score = (locVal != null ? locVal : 0) + (veloVal != null ? veloVal : 0) + (trajVal != null ? trajVal : 0) + compVal
  const maxPossible = (locVal != null ? 65 : 0) + (veloVal != null ? 10 : 0) + (trajVal != null ? 10 : 0) + 15
  return { score, maxPossible }
}

function executionPct(result) {
  if (!result || !result.maxPossible) return null
  return Math.round((result.score / result.maxPossible) * 100)
}

function executionBreakdown(pitch) {
  const hasLocation = pitch.distance_from_intended != null
  const hasVelo = pitch.miles_per_hour != null && pitch.miles_per_hour > 0
  const locVal = hasLocation ? locationScore(pitch.distance_from_intended) : null
  const veloVal = hasVelo ? veloScore(pitch.miles_per_hour, pitch.type_of_throw_id, pitch.pitcher_age) : null
  const trajVal = trajectoryScore(pitch.quality_of_throw_id, pitch.trajectory)
  const compVal = competitiveScore(pitch.is_strike, pitch.distance_from_intended, pitch.pitch_location)

  let compNote = 'not recorded'
  if (pitch.pitch_location && pitch.pitch_location > 0) {
    const zone = getZoneLabelFromCoord(pitch.pitch_location)
    if (zone && zone !== 'BALL') {
      const corners = ['TL', 'TR', 'BL', 'BR']
      const edges = ['TC', 'ML', 'MR', 'BC']
      if (corners.includes(zone)) compNote = `Corner (${zone}) — Elite Edge`
      else if (edges.includes(zone)) compNote = `Edge (${zone}) — Comp Strike`
      else compNote = `Middle (${zone}) — Hittable`
    } else if (pitch.distance_from_intended != null) {
      const d = pitch.distance_from_intended
      if (d <= 5) compNote = `Chase (${Number(d).toFixed(1)} off)`
      else if (d <= 12) compNote = `Comp Miss (${Number(d).toFixed(1)} off)`
      else compNote = `Far Miss (${Number(d).toFixed(1)} off)`
    }
  } else if (pitch.is_strike != null) {
    compNote = pitch.is_strike ? 'Strike (no coord)' : 'Ball (no coord)'
  }

  let trajNote = 'not recorded'
  const TRAJ_LABELS = { 10: 'S/M — Dominant', 8: 'Foul — Competitive', 7: 'GB', 4: 'Fly Ball', 1: 'LD — Squared Up' }
  if (trajVal != null) trajNote = TRAJ_LABELS[trajVal] || `id:${pitch.quality_of_throw_id}`

  return {
    loc: locVal,
    locMax: locVal != null ? 65 : null,
    locNote: locVal != null ? `${Number(pitch.distance_from_intended).toFixed(1)} off target` : 'not recorded',
    velo: veloVal,
    veloMax: veloVal != null ? 10 : null,
    veloNote: veloVal != null ? `${pitch.miles_per_hour} mph · age ${pitch.pitcher_age || '?'} (${getAgeGroup(pitch.pitcher_age)})` : 'not recorded',
    traj: trajVal,
    trajMax: trajVal != null ? 10 : null,
    trajNote,
    comp: compVal,
    compMax: 15,
    compNote,
  }
}

function buildPitcherSummaries(pitches) {
  const map = {}
  ;(pitches || []).forEach((p) => {
    const id = String(p.pitcher_id || 'unknown')
    if (!map[id]) map[id] = { id, name: p.player_name || `#${id}`, results: [], strikes: 0, total: 0 }
    const r = executionScore(p)
    if (r) map[id].results.push(r)
    if (p.is_strike === true || p.is_strike === 1) map[id].strikes++
    map[id].total++
  })

  return Object.values(map)
    .map((entry) => {
      const pcts = entry.results.map((r) => executionPct(r)).filter((n) => n != null)
      const avgPct = pcts.length ? Math.round(pcts.reduce((a, b) => a + b, 0) / pcts.length) : null
      const avgScore = entry.results.length ? Math.round(entry.results.reduce((a, b) => a + b.score, 0) / entry.results.length) : null
      const avgMax = entry.results.length ? Math.round(entry.results.reduce((a, b) => a + b.maxPossible, 0) / entry.results.length) : null
      return {
        ...entry,
        avgPct,
        avgScore,
        avgMax,
        strikePct: entry.total ? Math.round((entry.strikes / entry.total) * 100) : 0,
      }
    })
    .sort((a, b) => (b.avgPct ?? -1) - (a.avgPct ?? -1))
}

function asStrike(value) {
  if (value === true || value === 1 || value === '1') return true
  const s = String(value || '').toUpperCase()
  return s === 'S' || s.includes('STRIKE')
}

// ─── BATTING: FPS computation ─────────────────────────────────────────────────
function normQOC(raw) {
  const s = String(raw ?? '').toUpperCase().trim()
  if (s === 'H' || s === 'HARD')    return 'H'
  if (s === 'A' || s === 'AVERAGE') return 'A'
  if (s === 'W' || s === 'WEAK')    return 'W'
  if (s === 'MF' || s === 'F')      return 'MF'
  return ''
}
function normTOH(raw) {
  const s = String(raw ?? '').toUpperCase().trim()
  if (s === 'LD' || s.includes('LINE'))   return 'LD'
  if (s === 'FB' || s.includes('FLY'))    return 'FB'
  if (s === 'PF' || s.includes('POP') || s.includes('FOUL')) return 'PF'
  if (s === 'GB' || s.includes('GROUND')) return 'GB'
  return ''
}

function computeFPS(balls) {
  if (!balls?.length) return null
  const swings = balls.map(b => {
    const qoc = normQOC(b.batting?.quality_of_contact ?? b.quality_of_contact ?? '')
    const toh = normTOH(b.batting?.type_of_hit ?? b.type_of_hit ?? '')
    const rawEV = parseFloat(b.batting?.velocity ?? b.batting?.exit_velocity ?? b.exit_velocity ?? b.velocity ?? 0)
    const ev = rawEV >= 10 && rawEV <= 125 ? rawEV : null
    return { qoc, toh, ev }
  })
  const total = swings.length
  const swingsWithQOC = swings.filter(s => s.qoc === 'H' || s.qoc === 'A' || s.qoc === 'W')
  const contactScore = swingsWithQOC.length > 0
    ? swingsWithQOC.reduce((sum, s) => sum + (s.qoc === 'H' ? 100 : s.qoc === 'A' ? 70 : 40), 0) / swingsWithQOC.length
    : 50

  const evSwings = swings.filter(s => s.ev !== null)
  const avgEV = evSwings.length ? evSwings.reduce((a, s) => a + s.ev, 0) / evSwings.length : 0
  const topEV = evSwings.length ? Math.max(...evSwings.map(s => s.ev)) : 0
  const evScore = topEV > 0 ? Math.min(100, (avgEV / topEV) * 100) : 0

  const hardCount = swings.filter(s => s.ev !== null && s.ev >= 90).length
  const avgCount  = swings.filter(s => s.qoc === 'A').length
  const weakCount = swings.filter(s => s.qoc === 'W').length
  const evTotal   = evSwings.length
  const hardPct   = evTotal > 0 ? Math.round((hardCount / evTotal) * 100) : 0
  const avgPct    = evTotal > 0 ? Math.round((avgCount  / evTotal) * 100) : 0
  const weakPct   = evTotal > 0 ? Math.round((weakCount / evTotal) * 100) : 0

  const launchScores = swings.map(s => s.toh === 'LD' ? 100 : s.toh === 'FB' ? 80 : s.toh === 'PF' ? 60 : s.toh === 'GB' ? 50 : null).filter(v => v !== null)
  const launchScore  = launchScores.length > 0 ? launchScores.reduce((a, b) => a + b, 0) / launchScores.length : 50

  const ldCount  = swings.filter(s => s.toh === 'LD').length
  const flyCount = swings.filter(s => s.toh === 'FB').length
  const pfCount  = swings.filter(s => s.toh === 'PF').length
  const gbCount  = swings.filter(s => s.toh === 'GB').length
  const tohTotal = ldCount + flyCount + pfCount + gbCount
  const ldPct    = tohTotal > 0 ? Math.round((ldCount  / tohTotal) * 100) : 0
  const flyPct   = tohTotal > 0 ? Math.round((flyCount / tohTotal) * 100) : 0
  const pfPct    = tohTotal > 0 ? Math.round((pfCount  / tohTotal) * 100) : 0
  const gbPct    = tohTotal > 0 ? Math.round((gbCount  / tohTotal) * 100) : 0

  const compCount = swings.filter(s => s.qoc === 'H' || (s.qoc === 'A' && (s.toh === 'LD' || s.toh === 'FB'))).length
  const compScore = (compCount / total) * 100
  const missCount = swings.filter(s => s.qoc === 'MF' || (!s.qoc && !s.toh)).length
  const missPct   = (missCount / total) * 100
  const missScore = Math.max(0, 100 - missPct)
  const fps = Math.round(contactScore * 0.30 + evScore * 0.25 + launchScore * 0.20 + compScore * 0.15 + missScore * 0.10)

  return { total, fps, contactScore: Math.round(contactScore), evScore: Math.round(evScore), launchScore: Math.round(launchScore),
    compScore: Math.round(compScore), missScore: Math.round(missScore),
    avgEV: Math.round(avgEV * 10) / 10, topEV: Math.round(topEV * 10) / 10,
    compPct: Math.round(compScore), missPct: Math.round(missPct),
    hardCount, avgCount, weakCount, evTotal,
    hardPct, avgPct, weakPct, ldCount, flyCount, pfCount, gbCount, tohTotal, ldPct, flyPct, pfPct, gbPct }
}

// ─── BULLPEN: BPS computation ─────────────────────────────────────────────────
const ORDERED_TYPES = ['FB', 'CH', 'SL', 'CV', 'Other']
function normPitchType(p) {
  const typeId = Number(p.type_of_throw_id)
  if (typeId === 1) return 'FB'
  if (typeId === 2) return 'CH'
  if (typeId === 3) return 'SL'
  if (typeId === 4) return 'CV'
  if (typeId === 5) return 'Other'

  const t = String(p.type_of_throw_msg || p.type_throw || p.type_of_throw || p.pitch_type || p.type_of_pitch || p.pitch || '').toUpperCase().trim()
  if (['FB', 'FASTBALL', 'FF', 'FT', 'SI', '4S', '2S'].includes(t)) return 'FB'
  if (['CH', 'CHANGEUP', 'CHANGE-UP', 'CHANGE'].includes(t)) return 'CH'
  if (t === 'SL' || t === 'SLIDER') return 'SL'
  if (['CV', 'CU', 'CURVE', 'CURVEBALL', 'CB'].includes(t)) return 'CV'
  return 'Other'
}
function isStrikePitch(p) {
  const bs = String(p.ball_strike || p.pitch_result || p.result || '').toUpperCase()
  return bs === 'STRIKE' || bs === 'S' || p.is_strike === true || p.is_strike === 1
}
function isFoulPitch(p) {
  const bs = String(p.ball_strike || p.pitch_result || p.result || '').toUpperCase()
  const tr = String(p.trajectory || p.contact_trajectory || p.type_of_hit || '').toUpperCase()
  return bs.includes('FOUL') || tr === 'FOUL' || tr === 'PF'
}
function isSwingMissPitch(p) {
  if (!isStrikePitch(p) || isFoulPitch(p)) return false
  const tr = String(p.trajectory || p.contact_trajectory || p.type_of_hit || '').toUpperCase()
  if (tr) return false
  const bs = String(p.ball_strike || p.pitch_result || p.result || '').toUpperCase()
  return bs.includes('SWING') || bs.includes('MISS') || p.swing_miss === true || p.swing_miss === 1 || p.is_swing_miss === true || p.is_swing_miss === 1
}
function pitchVelo(p) { return parseFloat(p.miles_per_hour || p.pitch_velocity || p.velocity || 0) || 0 }

function computeBPS(pitches) {
  if (!pitches?.length) return null
  const total = pitches.length
  let strikeCount = 0
  const groups = {}
  ORDERED_TYPES.forEach(l => { groups[l] = { label: l, count: 0, strikes: 0, veloSum: 0 } })
  pitches.forEach(p => {
    if (isStrikePitch(p)) strikeCount++
    const l = normPitchType(p)
    const g = groups[l] || (groups[l] = { label: l, count: 0, strikes: 0, veloSum: 0 })
    g.count++; if (isStrikePitch(p)) g.strikes++; g.veloSum += pitchVelo(p)
  })
  const strikeRateScore = (strikeCount / total) * 100
  const typeGroups = Object.values(groups).filter(g => g.count > 0).map(g => ({
    label: g.label, count: g.count, strikes: g.strikes,
    strikePct: Math.round((g.strikes / g.count) * 100),
    avgMph: g.count > 0 ? Math.round(g.veloSum / g.count) : 0,
  }))
  let ptTotal = 0, ptWeight = 0
  typeGroups.forEach(g => { ptTotal += g.strikePct * g.count; ptWeight += g.count })
  const pitchTypeScore = ptWeight > 0 ? ptTotal / ptWeight : 0
  const third = Math.max(1, Math.floor(total / 3))
  const fv = pitches.slice(0, third).map(pitchVelo).filter(v => v > 0)
  const lv = pitches.slice(-third).map(pitchVelo).filter(v => v > 0)
  const firstAvgVelo = fv.length ? fv.reduce((a, b) => a + b, 0) / fv.length : 0
  const lastAvgVelo  = lv.length ? lv.reduce((a, b) => a + b, 0) / lv.length : 0
  const veloDrop = Math.max(0, firstAvgVelo - lastAvgVelo)
  const veloStabilityScore = veloDrop <= 1 ? 100 : veloDrop <= 3 ? 80 : veloDrop <= 5 ? 60 : 40
  const CONTACT_SCORES = { GB: 100, FOUL: 85, PF: 85, FB: 70, LD: 50 }
  let csSum = 0, csCount = 0
  pitches.forEach(p => {
    const tr = String(p.trajectory || p.contact_trajectory || p.type_of_hit || '').toUpperCase()
    if (tr && CONTACT_SCORES[tr] != null) { csSum += CONTACT_SCORES[tr]; csCount++ }
  })
  const contactSuppressionScore = csCount > 0 ? csSum / csCount : 70
  let compCount = 0
  pitches.forEach(p => {
    const tr = String(p.trajectory || p.contact_trajectory || p.type_of_hit || '').toUpperCase()
    if (isStrikePitch(p) || isSwingMissPitch(p) || isFoulPitch(p) || tr === 'GB') compCount++
  })
  const competitiveScore = (compCount / total) * 100
  const bps = Math.round(strikeRateScore * 0.30 + pitchTypeScore * 0.20 + veloStabilityScore * 0.15 + contactSuppressionScore * 0.20 + competitiveScore * 0.15)
  const strikePct = Math.round((strikeCount / total) * 100)
  const fbPitches = pitches.filter(p => normPitchType(p) === 'FB')
  const fbVelos   = fbPitches.map(pitchVelo).filter(v => v > 0)
  const maxFBVelo = fbVelos.length ? Math.max(...fbVelos) : 0
  const avgFBVelo = fbVelos.length ? Math.round((fbVelos.reduce((a, b) => a + b, 0) / fbVelos.length) * 10) / 10 : 0
  return { total, bps, strikeRateScore: Math.round(strikeRateScore), pitchTypeScore: Math.round(pitchTypeScore),
    veloStabilityScore: Math.round(veloStabilityScore), contactSuppressionScore: Math.round(contactSuppressionScore),
    competitiveScore: Math.round(competitiveScore), strikePct, compPct: Math.round((compCount / total) * 100),
    maxFBVelo, avgFBVelo, firstAvgVelo: Math.round(firstAvgVelo * 10) / 10, lastAvgVelo: Math.round(lastAvgVelo * 10) / 10,
    veloDrop: Math.round(veloDrop * 10) / 10, typeGroups }
}

// ─── CAGE: compute from raw rows ──────────────────────────────────────────────
function computeCageBreakdown(rows) {
  if (!rows?.length) return null
  const swings = rows.map(r => ({
    ev: parseFloat(r.launch_angle_velocity ?? r.velocity ?? r.exit_velocity ?? 0) || null,
    la: parseFloat(r.launch_angle ?? 0),
    sa: parseFloat(r.spray_angle ?? 0),
    dist: parseFloat(r.distance_travel ?? r.distance ?? 0),
    toh: String(r.type_of_hit ?? '').toUpperCase(),
  }))
  const evSwings  = swings.filter(s => s.ev !== null && s.ev > 0)
  const laSwings  = swings.filter(s => !isNaN(s.la))
  const avgEV     = evSwings.length ? Math.round((evSwings.reduce((a, s) => a + s.ev, 0) / evSwings.length) * 10) / 10 : null
  const maxEV     = evSwings.length ? Math.max(...evSwings.map(s => s.ev)) : null
  const hardHitPct = evSwings.length ? parseFloat(((evSwings.filter(s => s.ev >= 90).length / evSwings.length) * 100).toFixed(1)) : null
  const barrelPct  = evSwings.length ? parseFloat(((evSwings.filter(s => s.ev >= 98 && s.la >= 8 && s.la <= 32).length / evSwings.length) * 100).toFixed(1)) : null
  const avgLA      = laSwings.length ? parseFloat((laSwings.reduce((a, s) => a + s.la, 0) / laSwings.length).toFixed(1)) : null
  const sweetSpotPct = laSwings.length ? parseFloat(((laSwings.filter(s => s.la >= 8 && s.la <= 32).length / laSwings.length) * 100).toFixed(1)) : null

  // Spray
  const saSwings = swings.filter(s => !isNaN(s.sa))
  const pullCount  = saSwings.filter(s => s.sa >= 10 && s.sa <= 30).length
  const midCount   = saSwings.filter(s => s.sa > 30 && s.sa <= 50).length
  const oppoCount  = saSwings.filter(s => s.sa > 50).length
  const pullPct    = saSwings.length ? parseFloat(((pullCount / saSwings.length) * 100).toFixed(1)) : null
  const midPct     = saSwings.length ? parseFloat(((midCount  / saSwings.length) * 100).toFixed(1)) : null
  const oppoPct    = saSwings.length ? parseFloat(((oppoCount / saSwings.length) * 100).toFixed(1)) : null

  const qualityCount   = swings.filter(s => s.ev >= 75 && s.la >= 5 && s.la <= 35).length
  const swingQualityPct = laSwings.length ? parseFloat(((qualityCount / laSwings.length) * 100).toFixed(1)) : null

  // Damage score — composite
  const evComp  = avgEV !== null ? Math.min(100, ((avgEV - 50) / 50) * 100) : 50
  const laComp  = sweetSpotPct !== null ? Math.min(100, sweetSpotPct * 2) : 0
  const hhComp  = hardHitPct !== null ? Math.min(100, hardHitPct * 2) : 0
  const damageScore = Math.round(evComp * 0.45 + laComp * 0.30 + hhComp * 0.25)

  return { totalSwings: rows.length, avgEV, maxEV, hardHitPct, barrelPct, avgLA, sweetSpotPct, pullPct, midPct, oppoPct, swingQualityPct, damageScore, sprayTotal: saSwings.length }
}

// ─── EV: compute from raw rows ────────────────────────────────────────────────
function computeEVBreakdown(rows) {
  if (!rows?.length) return null
  const velos = rows.map(r => parseFloat(r.velocity ?? r.exit_velocity ?? 0)).filter(v => v > 0)
  if (!velos.length) return null
  const avgEV    = Math.round((velos.reduce((a, b) => a + b, 0) / velos.length) * 10) / 10
  const topEV    = Math.max(...velos)
  const hardHit  = velos.filter(v => v >= 90).length
  const hhPct    = parseFloat(((hardHit / velos.length) * 100).toFixed(1))
  const byTraj   = {}
  rows.forEach(r => {
    const t = String(r.trajectory ?? '').toUpperCase() || 'UNKNOWN'
    byTraj[t] = (byTraj[t] || 0) + 1
  })
  const total = rows.length
  const ldPct  = parseFloat((((byTraj['LD'] ?? 0)  / total) * 100).toFixed(1))
  const fbPct  = parseFloat((((byTraj['FLY'] ?? byTraj['FB'] ?? 0) / total) * 100).toFixed(1))
  const gbPct  = parseFloat((((byTraj['GB'] ?? 0)  / total) * 100).toFixed(1))
  const puPct  = parseFloat((((byTraj['PU'] ?? 0)  / total) * 100).toFixed(1))
  const evPowerScore = Math.round(Math.min(100, ((avgEV - 60) / 35) * 100))
  const tBonus = ldPct >= 30 ? 25 : fbPct >= 30 ? 18 : 12
  const evs    = Math.min(100, Math.round((evPowerScore * 0.60 + tBonus * 0.25 + Math.min(100, hhPct * 2) * 0.15)))
  return { total, evs, avgEV, topEV, hardHit, hhPct, evPowerScore, ldPct, fbPct, gbPct, puPct, byTraj }
}

// ─── LONG TOSS: compute from raw rows ────────────────────────────────────────
function computeLTBreakdown(rows) {
  if (!rows?.length) return null
  const throws = rows.map(r => ({ dist: parseFloat(r.distance ?? 0), hop: parseInt(r.hop ?? r.player_hop ?? 0, 10) || 0, uid: r.user_id }))
  const dists  = throws.map(t => t.dist).filter(d => d > 0)
  if (!dists.length) return null
  const avgDist    = parseFloat((dists.reduce((a, b) => a + b, 0) / dists.length).toFixed(1))
  const maxDist    = Math.max(...dists)
  const zeroHops   = throws.filter(t => t.hop === 0).length
  const zeroHopPct = parseFloat(((zeroHops / throws.length) * 100).toFixed(1))
  const avgHop     = parseFloat((throws.reduce((a, t) => a + t.hop, 0) / throws.length).toFixed(2))
  // distance buckets for bar display
  const buckets = { '<50': 0, '50-99': 0, '100-149': 0, '150-199': 0, '200-249': 0, '250+': 0 }
  dists.forEach(d => {
    if (d < 50) buckets['<50']++
    else if (d < 100) buckets['50-99']++
    else if (d < 150) buckets['100-149']++
    else if (d < 200) buckets['150-199']++
    else if (d < 250) buckets['200-249']++
    else buckets['250+']++
  })
  return { total: throws.length, avgDist, maxDist, zeroHopPct, avgHop, buckets }
}

// ─── WB: compute from raw rows ────────────────────────────────────────────────
function computeWBBreakdown(rows) {
  if (!rows?.length) return null
  const throws = rows.map(r => ({
    velo: parseFloat(r.velocity ?? 0),
    weight: parseInt(r.weight ?? 0, 10),
    uid: r.user_id,
  })).filter(t => t.velo > 0)
  if (!throws.length) return null
  const velos = throws.map(t => t.velo)
  const avgVelo = parseFloat((velos.reduce((a, b) => a + b, 0) / velos.length).toFixed(1))
  const topVelo = Math.max(...velos)
  const weights = [...new Set(throws.map(t => t.weight).filter(w => w > 0))].sort((a, b) => a - b)
  // velocity by ball weight
  const byWeight = {}
  throws.forEach(t => {
    if (!t.weight) return
    if (!byWeight[t.weight]) byWeight[t.weight] = []
    byWeight[t.weight].push(t.velo)
  })
  const weightBreakdown = Object.entries(byWeight).map(([w, vs]) => ({
    weight: Number(w),
    count: vs.length,
    avgVelo: parseFloat((vs.reduce((a, b) => a + b, 0) / vs.length).toFixed(1)),
    maxVelo: Math.max(...vs),
  })).sort((a, b) => a.weight - b.weight)
  return { total: throws.length, avgVelo, topVelo, weights, weightBreakdown }
}

// ─── LIVE AB: match the app's hitter/pitcher/competition report ─────────────
const LIVE_HITTER_RESULT = { HR: 35, '3B': 32, '2B': 28, '1B': 24, BB: 22, HBP: 18, Out: 10, Error: 10, K: 0 }
const LIVE_PITCHER_RESULT = { K: 35, Out: 28, Error: 28, BB: 5, HBP: 0, '1B': 14, '2B': 8, '3B': 4, HR: 0 }
const LIVE_COMPETITION = { 1: 1, 2: 2, 3: 4, 4: 6, 5: 8 }

function liveResult(row) {
  const play = String(row?.play_result || '').trim().toUpperCase().replace(/\s+/g, '_')
  if (['ERROR', 'ROE'].includes(play)) return 'Error'
  if (play.includes('STRIKEOUT')) return 'K'
  if (['OUT', 'GROUND_OUT', 'FLY_OUT', 'LINE_OUT', 'POP_OUT', 'FOUL_OUT', 'FORCE_OUT', 'TAG_OUT', 'DOUBLE_PLAY', 'TRIPLE_PLAY', 'SAC_BUNT', 'SAC_FLY', 'INFIELD_FLY'].includes(play)) return 'Out'
  const raw = row?.total_bases ?? row?.bases
  const values = { 1: '1B', 2: '2B', 3: '3B', 4: 'BB', 5: 'HR', 6: 'HBP', 7: 'K', 8: 'Out' }
  const text = String(raw ?? '').trim().toUpperCase()
  return values[text] || ({ '1B': '1B', '2B': '2B', '3B': '3B', HR: 'HR', BB: 'BB', HBP: 'HBP', K: 'K', OUT: 'Out', ERROR: 'Error' }[text] ?? null)
}

function liveBallStrike(row) {
  const trajectory = String(row?.contact_trajectory ?? row?.batting?.type_of_hit ?? row?.pitching?.trajectory ?? '').toUpperCase()
  if (trajectory === 'HBP' || trajectory === 'HIT BY PITCH') return 'HBP'
  if (trajectory && !['TAKE', 'TK', '-'].includes(trajectory)) return 'S'
  const zone = row?.zone ?? row?.batting?.zone ?? row?.pitching?.zone ?? row?.pitch_location
  return String(zone || '').toUpperCase() === 'S' ? 'S' : 'B'
}

function liveName(row, side) {
  const nested = row?.[side]
  const direct = side === 'batting' ? row?.batter_name : row?.pitcher_name
  if (direct) return direct
  const profile = nested?.profile
  return profile?.full_name || `${profile?.first_name || ''} ${profile?.last_name || ''}`.trim() || null
}

function computeLiveABBreakdown(rows) {
  if (!rows?.length) return null
  const sorted = [...rows].sort((a, b) => (Number(a.sort) || 0) - (Number(b.sort) || 0))
  const plateAppearances = []
  let current = []
  let balls = 0
  let strikes = 0

  sorted.forEach(row => {
    const pitch = { ...row, _countBefore: `${balls}-${strikes}` }
    current.push(pitch)
    const result = liveResult(pitch)
    const ended = Boolean(result) || pitch.turn_is_over === true || Number(pitch.turn_is_over) === 1
    if (ended) {
      plateAppearances.push({ pitches: current, terminal: pitch })
      current = []; balls = 0; strikes = 0
      return
    }
    const call = liveBallStrike(pitch)
    if (call === 'B') balls++
    if (call === 'S') {
      const trajectory = String(pitch.contact_trajectory || '').toUpperCase()
      if (trajectory !== 'FOUL' || strikes < 2) strikes++
    }
  })
  if (current.length) plateAppearances.push({ pitches: current, terminal: current[current.length - 1] })
  if (!plateAppearances.length) return null

  const velocities = sorted.map(row => Number(row.pitch_velocity ?? row.pitching?.miles_per_hour ?? 0)).filter(v => v > 0)
  const averageVelocity = velocities.length ? velocities.reduce((sum, v) => sum + v, 0) / velocities.length : 0
  const sortedVelocities = [...velocities].sort((a, b) => a - b)
  const topVelocityThreshold = sortedVelocities[Math.floor(sortedVelocities.length * 0.9)] || averageVelocity
  const hitterMap = {}
  const pitcherMap = {}

  const paScores = plateAppearances.map(pa => {
    const terminal = pa.terminal
    const result = liveResult(terminal)
    const contact = String(terminal.contact_quality ?? terminal.batting?.quality_of_contact ?? '').toLowerCase()
    const trajectory = String(terminal.contact_trajectory ?? terminal.batting?.type_of_hit ?? '').toLowerCase()
    const hitterContact = ['hard', 'h'].includes(contact) ? 25 : ['solid', 'average', 'avg', 'medium', 'a'].includes(contact) ? 15 : ['weak', 'w'].includes(contact) ? 6 : 0
    const hitterTrajectory = ['ld', 'line drive'].includes(trajectory) ? 20 : ['fly', 'fb', 'fly ball'].includes(trajectory) ? 14 : ['gb', 'ground ball'].includes(trajectory) ? 10 : trajectory === 'foul' ? 6 : ['take', 'tk'].includes(trajectory) ? 5 : 0
    const hitterCount = ({ '0-0': 5, '1-0': 6, '2-0': 7, '3-0': 6, '3-1': 8, '2-1': 7, '1-1': 5, '0-1': 4, '0-2': 10, '1-2': 9, '2-2': 8, '3-2': 10 })[terminal._countBefore] ?? 5
    const exitVelo = Number(terminal.exit_velocity ?? terminal.batting?.velocity ?? 0)
    const exitBonus = exitVelo >= 95 ? 10 : exitVelo >= 90 ? 8 : exitVelo >= 85 ? 6 : exitVelo >= 80 ? 4 : exitVelo >= 75 ? 2 : 0
    const hitterScore = Math.min(100, (LIVE_HITTER_RESULT[result] ?? 5) + hitterContact + hitterTrajectory + hitterCount + exitBonus)

    const call = liveBallStrike(terminal)
    const zoneScore = call === 'S' ? (['sw/miss', 'miss'].includes(trajectory) ? 20 : trajectory === 'foul' ? 12 : 16) : call === 'HBP' ? -5 : 0
    const contactScore = ['hard', 'h'].includes(contact) ? 0 : ['solid', 'average', 'avg', 'medium', 'a'].includes(contact) ? 12 : ['weak', 'w'].includes(contact) ? 22 : ['sw/miss', 'miss'].includes(trajectory) ? 25 : 12
    const countScore = ({ '0-0': 5, '0-1': 7, '0-2': 10, '1-2': 9, '2-2': 7, '3-2': 6, '1-0': 3, '2-0': 1, '3-0': 0, '3-1': 1 })[terminal._countBefore] ?? 5
    const pitchVelo = Number(terminal.pitch_velocity ?? terminal.pitching?.miles_per_hour ?? 0)
    const veloScore = !pitchVelo || !velocities.length ? 5 : pitchVelo >= topVelocityThreshold ? 10 : pitchVelo >= averageVelocity ? 8 : pitchVelo >= averageVelocity * 0.95 ? 5 : 2
    const pitcherScore = Math.min(100, Math.max(0, (LIVE_PITCHER_RESULT[result] ?? 14) + zoneScore + contactScore + countScore + veloScore))
    const competitionScore = pa.pitches.length >= 6 ? 10 : LIVE_COMPETITION[pa.pitches.length] ?? 1

    const hitter = liveName(terminal, 'batting')
    const pitcher = liveName(terminal, 'pitching')
    if (hitter) hitterMap[hitter] = { total: (hitterMap[hitter]?.total || 0) + hitterScore, count: (hitterMap[hitter]?.count || 0) + 1 }
    if (pitcher) pitcherMap[pitcher] = { total: (pitcherMap[pitcher]?.total || 0) + pitcherScore, count: (pitcherMap[pitcher]?.count || 0) + 1 }
    return { hitterScore, pitcherScore, competitionScore, result: result || 'Other', pitchCount: pa.pitches.length }
  })

  const avg = key => paScores.reduce((sum, score) => sum + score[key], 0) / paScores.length
  const hitterAvg = Math.round(avg('hitterScore'))
  const pitcherAvg = Math.round(avg('pitcherScore'))
  const competitionAvg = Math.round((avg('competitionScore') / 10) * 100)
  const sessionScore = Math.round(hitterAvg * 0.45 + pitcherAvg * 0.45 + competitionAvg * 0.10)
  const resultDist = {}
  const pitchDistBuckets = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, '6+': 0 }
  paScores.forEach(score => {
    resultDist[score.result] = (resultDist[score.result] || 0) + 1
    const bucket = score.pitchCount >= 6 ? '6+' : score.pitchCount
    pitchDistBuckets[bucket] = (pitchDistBuckets[bucket] || 0) + 1
  })
  const leaders = map => Object.entries(map).map(([name, value]) => ({ name, avg: Math.round(value.total / value.count), abs: value.count })).sort((a, b) => b.avg - a.avg).slice(0, 3)

  return { totalPitches: rows.length, totalPAs: plateAppearances.length, sessionScore, hitterAvg, pitcherAvg, competitionAvg, resultDist, pitchDistBuckets, topHitters: leaders(hitterMap), topPitchers: leaders(pitcherMap) }
}

// ─── Tips ─────────────────────────────────────────────────────────────────────
const tips = computed(() => {
  const bd = breakdown.value
  if (!bd) return []
  const t = []

  if (sessionType === 'batting') {
    if (bd.hardPct >= 40) t.push({ icon: '💥', text: `${bd.hardPct}% hard contact — you're driving the ball well. Keep attacking the zone.` })
    else if (bd.hardPct >= 20) t.push({ icon: '🎯', text: `${bd.hardPct}% hard contact. Focus on staying through the ball and using the whole field.` })
    else t.push({ icon: '⚠️', text: 'Hard contact rate is low. Work on bat path and making solid contact before swinging harder.' })
    if (bd.avgEV >= 85) t.push({ icon: '⚡', text: `Avg exit velocity of ${bd.avgEV} mph is excellent. Real barrel power.` })
    else if (bd.avgEV >= 72) t.push({ icon: '📈', text: `Avg exit velocity of ${bd.avgEV} mph is solid. Strength training and timing can push this higher.` })
    else if (bd.avgEV > 0) t.push({ icon: '🏋️', text: `Avg exit velocity of ${bd.avgEV} mph. Work on hip rotation and bat speed.` })
    if (bd.ldPct >= 35) t.push({ icon: '🟢', text: `${bd.ldPct}% line drives — excellent launch profile for run production.` })
    else if (bd.gbPct >= 50) t.push({ icon: '🔻', text: `High ground ball rate (${bd.gbPct}%). Elevate your swing plane.` })
  }

  if (sessionType === 'bullpen') {
    if (bd.strikePct >= 65) t.push({ icon: '🎯', text: 'Great strike-throwing session. You attacked the zone consistently.' })
    else if (bd.strikePct >= 45) t.push({ icon: '📊', text: 'Strike rate is solid but there is room to attack the zone more confidently.' })
    else t.push({ icon: '⚠️', text: 'Strike rate needs work. Focus on first-pitch strikes and staying in the zone.' })
    if (bd.veloDrop <= 1) t.push({ icon: '💪', text: 'Velocity was rock-solid from start to finish. Great arm endurance.' })
    else if (bd.veloDrop > 3) t.push({ icon: '🔋', text: `Velo dropped ${bd.veloDrop} mph by the end. Focus on arm conditioning and recovery.` })
    if (bd.compPct >= 75) t.push({ icon: '🔄', text: 'Excellent competitive pitch rate. Keep expanding the zone with two strikes.' })
    else if (bd.compPct >= 55) t.push({ icon: '🔄', text: 'Decent competitive pitch rate. Work on adding late movement to get more weak contact.' })
    else t.push({ icon: '🔄', text: 'Competitive pitch rate needs work. Focus on quality misses and finishing pitches.' })
  }

  if (sessionType === 'cage') {
    if (bd.avgEV >= 87) t.push({ icon: '🔥', text: `Avg exit velocity of ${bd.avgEV} mph — elite contact. Keep attacking the ball with intention.` })
    else if (bd.avgEV >= 68) t.push({ icon: '📈', text: `Avg exit velocity of ${bd.avgEV} mph. Focus on staying through the ball to push above 87.` })
    if (bd.avgLA !== null) {
      if (bd.avgLA >= 8 && bd.avgLA <= 25) t.push({ icon: '✅', text: `Avg launch angle of ${bd.avgLA}° — right in the sweet spot.` })
      else if (bd.avgLA < 8) t.push({ icon: '⬆️', text: `Avg launch angle of ${bd.avgLA}° is too low. Get under the ball slightly to generate loft.` })
      else t.push({ icon: '⬇️', text: `Avg launch angle of ${bd.avgLA}° is too high. Tighten your swing path to avoid pop-ups.` })
    }
    if (bd.hardHitPct >= 35) t.push({ icon: '💪', text: `${bd.hardHitPct}% hard hit rate — you're barreling the ball consistently. Game-ready power.` })
  }

  if (sessionType === 'exit_velocity') {
    if (bd.hhPct >= 50) t.push({ icon: '🔥', text: `${bd.hhPct}% hard hit rate (≥90 mph) — elite exit velocity consistency.` })
    else if (bd.hhPct >= 30) t.push({ icon: '💪', text: `${bd.hhPct}% hard hit rate. Solid power — work on your swing plane to push this above 50%.` })
    else t.push({ icon: '⚙️', text: `${bd.hhPct}% hard hit rate. Focus on hip rotation and bat speed to generate more exit velocity.` })
    if (bd.ldPct >= 30) t.push({ icon: '🟢', text: `${bd.ldPct}% line drives — ideal trajectory for exit velocity training.` })
  }

  if (sessionType === 'long_toss') {
    if (bd.maxDist >= 250) t.push({ icon: '🚀', text: `Max distance of ${bd.maxDist} ft — excellent arm extension and carry.` })
    else if (bd.maxDist >= 150) t.push({ icon: '📏', text: `Max distance of ${bd.maxDist} ft. Work toward the 250 ft extension target.` })
    else t.push({ icon: '🎯', text: `Max distance of ${bd.maxDist} ft. Gradually increase distance through proper warm-up progression.` })
    if (bd.zeroHopPct >= 50) t.push({ icon: '🏹', text: `${bd.zeroHopPct}% zero-hop rate — great arm carry and flat flight path.` })
    else if (bd.zeroHopPct >= 20) t.push({ icon: '⬆️', text: `${bd.zeroHopPct}% zero-hop rate. Focus on driving the ball on a flat plane.` })
  }

  if (sessionType === 'weight_ball') {
    if (bd.topVelo >= 95) t.push({ icon: '🔥', text: `Top velocity of ${bd.topVelo} mph — elite arm strength output with weighted balls.` })
    else if (bd.topVelo >= 80) t.push({ icon: '💪', text: `Top velocity of ${bd.topVelo} mph. Continue progressing through the weight ball program.` })
    if (bd.weights?.length >= 3) t.push({ icon: '⚾', text: `Used ${bd.weights.length} different ball weights — great progression variety.` })
    if (bd.avgVelo >= 85) t.push({ icon: '📈', text: `Avg velocity of ${bd.avgVelo} mph — consistent arm output across the session.` })
  }

  if (sessionType === 'live_ab') {
    t.push({ icon: '📊', text: `This session scored ${bd.sessionScore}/100 across ${bd.totalPAs} plate appearances and ${bd.totalPitches} pitches.` })
    t.push(bd.hitterAvg >= 70
      ? { icon: '⚾', text: `Hitters averaged ${bd.hitterAvg}/100 with productive at-bats and quality contact.` }
      : { icon: '⚾', text: `Hitters averaged ${bd.hitterAvg}/100. Focus on count awareness and contact quality.` })
    t.push(bd.pitcherAvg >= 70
      ? { icon: '🔥', text: `Pitchers averaged ${bd.pitcherAvg}/100 with strong zone command and contact management.` }
      : { icon: '🎯', text: `Pitchers averaged ${bd.pitcherAvg}/100. Focus on getting ahead and finishing advantage counts.` })
  }

  return t
})

// ─── Main score ───────────────────────────────────────────────────────────────
const mainScore = computed(() => {
  const bd = breakdown.value
  if (!bd) return null
  if (sessionType === 'batting')       return bd.fps
  if (sessionType === 'bullpen')       return bd.bps
  if (sessionType === 'cage')          return bd.damageScore
  if (sessionType === 'exit_velocity') return bd.evs
  if (sessionType === 'live_ab')       return bd.sessionScore
  return null
})

// ─── Load data ────────────────────────────────────────────────────────────────
onMounted(async () => {
  if (!sessionId) { error.value = 'No session ID.'; loading.value = false; return }
  try {
    const res = await axiosGet(`statistics/${sessionId}/${cfg.value.endpoint}`, playerOnly ? { player: 1 } : undefined)
    rawData.value = res?.data?.data ?? res?.data ?? null
  } catch (e) {
    error.value = 'Failed to load session data.'
    loading.value = false
    return
  }

  const rd = rawData.value
  if (!rd) { error.value = 'No data returned.'; loading.value = false; return }

  const loadScriptedBp = async () => {
    const balls = extractSessionRows(rd, 'batting')
    let scriptedRes = null
    try {
      const r = await axiosGet(`result/scripted-bp/${sessionId}`)
      scriptedRes = r?.data?.data ?? r?.data ?? null
    } catch {
      scriptedRes = null
    }

    const hasScriptedBatters = Array.isArray(scriptedRes?.batters) && scriptedRes.batters.length > 0
    const batters = hasScriptedBatters ? scriptedRes.batters : buildBattersFromBalls(balls)
    const hasAny = batters.length > 0 || balls.length > 0
    if (!hasAny) {
      scriptedBp.value = null
      return
    }

    const rows = batters.map((b) => {
      const rounds = Array.isArray(b.rounds) ? b.rounds : []
      const weighted = rounds.length ? computeWeightedScore(rounds) : Math.round(Number(b.avg_score) || 0)
      const score = rounds.length ? Math.max(Math.round(Number(b.avg_score) || 0), weighted) : Math.round(Number(b.avg_score) || 0)
      const bestRound = rounds.reduce((best, r) => ((Number(r.round_score) || 0) > (Number(best?.round_score) || -1) ? r : best), null)
      const worstRound = rounds.reduce((worst, r) => ((Number(r.round_score) || 100) < (Number(worst?.round_score) || 101) ? r : worst), null)
      return {
        id: b.batter_id,
        name: b.batter_name || `Batter #${String(b.batter_id || '').slice(0, 6)}`,
        swings: Number(b.total_swings) || 0,
        rounds,
        score,
        grade: bpScriptGradeLabel(score),
        bestRound: bestRound ? ROUND_LABELS[bestRound.round_type] ?? bestRound.round_type : null,
        worstRound: worstRound ? ROUND_LABELS[worstRound.round_type] ?? worstRound.round_type : null,
      }
    }).sort((a, b) => b.score - a.score)

    scriptedBp.value = {
      teamScore: computeScriptedBpTeamScore(batters, balls),
      batters: rows,
      totalSwings: rows.reduce((sum, r) => sum + r.swings, 0),
    }
  }

  const loadScriptedBullpen = () => {
    const rows = extractSessionRows(rd, 'bullpen')

    const normalized = (rows || []).map((ball) => {
      const intendedCoord = ball.intended_location || ball.intended_coordinate || ball.script_location || ball.target_location || null
      const actualCoord = ball.pitch_mark || ball.pitch_location || ball.actual_location || 0
      const rawDist = computePitchDistance(intendedCoord, actualCoord)
      const actualZone = getZoneLabelFromCoord(actualCoord)
      const intendedZone = intendedCoord ? getZoneLabelFromCoord(intendedCoord) : null
      return {
        ...ball,
        type_of_throw_id: ball.type_of_throw_id || (ball.type_throw ? mapThrowType(ball.type_throw) : 0),
        miles_per_hour: ball.miles_per_hour || ball.pitch_velocity || ball.velocity || null,
        quality_of_throw_id: ball.quality_of_throw_id || ball.quality_id || null,
        trajectory: ball.trajectory || ball.quality_of_throw || ball.quality || null,
        is_strike: asStrike(ball.is_strike ?? ball.ball_strike ?? ball.pitch_result ?? ball.result),
        pitch_location: actualCoord,
        intended_location: intendedCoord,
        intended_zone_label: intendedZone ? (SCRIPT_ZONE_LABELS[intendedZone] || intendedZone) : null,
        actual_zone_label: actualZone ? (SCRIPT_ZONE_LABELS[actualZone] || actualZone) : null,
        distance_from_intended: rawDist != null ? Math.round(rawDist * 10) / 10 : null,
        player_name: ball.profile
          ? `${ball.profile.first_name || ''} ${ball.profile.last_name || ''}`.trim()
          : ball.player_name || ball.last_name || null,
        pitcher_age: ageFromDOB(resolveBornValue(ball)),
      }
    })

    const totalPitches = normalized.length
    if (!totalPitches) {
      scriptedBullpen.value = null
      return
    }

    const strikeCount = normalized.filter((p) => p.is_strike === true || p.is_strike === 1).length
    const strikePct = totalPitches ? Math.round((strikeCount / totalPitches) * 100) : 0
    const allResults = normalized.map((p) => executionScore(p)).filter((r) => r != null)
    const allPcts = allResults.map((r) => executionPct(r)).filter((n) => n != null)
    const sessionAvgPct = allPcts.length ? Math.round(allPcts.reduce((a, b) => a + b, 0) / allPcts.length) : null
    const sessionAvgScore = allResults.length ? Math.round(allResults.reduce((a, b) => a + b.score, 0) / allResults.length) : null
    const sessionAvgMax = allResults.length ? Math.round(allResults.reduce((a, b) => a + b.maxPossible, 0) / allResults.length) : null
    const avgDistVals = normalized.map((p) => p.distance_from_intended).filter((d) => d != null)
    const avgDist = avgDistVals.length ? (avgDistVals.reduce((a, b) => a + b, 0) / avgDistVals.length).toFixed(1) : null

    const pitchRows = normalized.map((p, idx) => {
      const result = executionScore(p)
      const pctValue = executionPct(result)
      const grade = execGrade(pctValue)
      return {
        id: p.id || `${p.pitcher_id || 'p'}-${idx}`,
        number: normalized.length - idx,
        pitcher: p.player_name || `#${p.pitcher_id || 'unknown'}`,
        type: throwTypeLabel(p.type_of_throw_id),
        resultText: p.is_strike ? 'STR' : 'BALL',
        resultColor: p.is_strike ? '#22c55e' : '#ef4444',
        mph: p.miles_per_hour || null,
        offTarget: p.distance_from_intended,
        score: result?.score ?? null,
        maxScore: result?.maxPossible ?? null,
        pct: pctValue,
        grade,
        pitch: p,
      }
    })

    scriptedBullpen.value = {
      totalPitches,
      strikePct,
      sessionAvgPct,
      sessionAvgScore,
      sessionAvgMax,
      sessionGrade: execGrade(sessionAvgPct),
      avgDist,
      pitchers: buildPitcherSummaries(normalized),
      pitches: pitchRows,
    }
  }

  if (sessionType === 'batting') {
    const balls = extractSessionRows(rd, 'batting')
    breakdown.value = computeFPS(balls)
    if (!breakdown.value) error.value = 'No swing data found for this session.'
    await loadScriptedBp()
  }
  else if (sessionType === 'bullpen') {
    const pitches = extractSessionRows(rd, 'bullpen')
    breakdown.value = computeBPS(pitches)
    if (!breakdown.value) error.value = 'No pitch data found for this session.'
    loadScriptedBullpen()
  }
  else if (sessionType === 'cage') {
    const rows = extractSessionRows(rd, 'cage')
    breakdown.value = computeCageBreakdown(rows)
    if (!breakdown.value) error.value = 'No swing data found for this session.'
  }
  else if (sessionType === 'exit_velocity') {
    const rows = extractSessionRows(rd, 'exit_velocity')
    breakdown.value = computeEVBreakdown(rows)
    if (!breakdown.value) error.value = 'No exit velocity data found for this session.'
  }
  else if (sessionType === 'long_toss') {
    const rows = extractSessionRows(rd, 'long_toss')
    breakdown.value = computeLTBreakdown(rows)
    if (!breakdown.value) error.value = 'No long toss data found for this session.'
  }
  else if (sessionType === 'weight_ball') {
    const rows = extractSessionRows(rd, 'weight_ball')
    breakdown.value = computeWBBreakdown(rows)
    if (!breakdown.value) error.value = 'No weighted ball data found for this session.'
  }
  else if (sessionType === 'live_ab') {
    const rows = extractSessionRows(rd, 'live_ab')
    breakdown.value = computeLiveABBreakdown(rows)
    if (!breakdown.value) error.value = 'No Live AB pitch data found for this session.'
  }

  loading.value = false
})

const displayDate = computed(() => {
  if (!sessionDate) return null
  try { return new Date(sessionDate).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) }
  catch { return sessionDate }
})
</script>

<template>
  <Layout>
    <div class="session-report min-h-screen bg-[#060b14] pb-20 text-white">

      <div class="mx-auto w-full max-w-[1600px]">

      <!-- Header -->
      <div class="flex items-center gap-3 px-5 pt-6 pb-4">
        <button @click="router.back()" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition">
          <svg class="w-4 h-4 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <h1 class="text-base font-black uppercase tracking-widest text-white">Session Report</h1>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-32 gap-4">
        <svg class="animate-spin w-8 h-8" :style="{ color: cfg.color }" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        <p class="text-white/40 text-sm">Loading session data…</p>
      </div>

      <template v-else>
        <!-- ── Hero card ── -->
        <div class="mx-4 rounded-2xl border border-white/20 overflow-hidden mb-5 shadow-[0_0_0_1px_rgba(192,0,0,0.15)]"
             :style="{ background: `linear-gradient(135deg, ${cfg.color}18, ${cfg.color}08)`, borderColor: cfg.color + '40' }">
          <div class="flex flex-col items-center py-8 px-6 text-center">
            <!-- Badge -->
            <span class="text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg mb-3"
                  :style="{ backgroundColor: cfg.color + '33', color: cfg.color }">
              {{ cfg.label }}
            </span>

            <!-- Date -->
            <p v-if="displayDate" class="text-white/70 text-base font-bold mb-4">{{ displayDate }}</p>

            <!-- Main score -->
            <div v-if="mainScore != null" class="mb-3">
              <span class="text-[88px] font-black leading-none" :style="{ color: gradeColor(mainScore) }">{{ mainScore }}</span>
              <div class="flex items-center justify-center gap-2 mt-1">
                <span class="text-xs font-black tracking-widest text-white/30">{{ cfg.abbr }}</span>
                <span class="text-sm font-black" :style="{ color: gradeColor(mainScore) }">{{ gradeLabel(mainScore) }}</span>
              </div>
              <!-- Score bar -->
              <div class="mt-3 w-48 h-2 bg-white/10 rounded-full overflow-hidden mx-auto">
                <div class="h-full rounded-full transition-all duration-700"
                     :style="{ width: Math.min(mainScore, 100) + '%', backgroundColor: gradeColor(mainScore) }"/>
              </div>
            </div>

            <!-- Counts -->
            <p class="text-white/70 text-sm font-semibold">
              <template v-if="sessionType === 'batting' && breakdown">{{ breakdown.total }} swings</template>
              <template v-else-if="sessionType === 'bullpen' && breakdown">{{ breakdown.total }} pitches</template>
              <template v-else-if="sessionType === 'cage' && breakdown">{{ breakdown.totalSwings }} swings</template>
              <template v-else-if="sessionType === 'exit_velocity' && breakdown">{{ breakdown.total }} swings · Avg {{ breakdown.avgEV }} mph</template>
              <template v-else-if="sessionType === 'long_toss' && breakdown">{{ breakdown.total }} throws · Max {{ breakdown.maxDist }} ft</template>
              <template v-else-if="sessionType === 'weight_ball' && breakdown">{{ breakdown.total }} throws · Top {{ breakdown.topVelo }} mph</template>
              <template v-else-if="sessionType === 'live_ab' && breakdown">{{ breakdown.totalPAs }} plate appearances · {{ breakdown.totalPitches }} pitches</template>
            </p>
          </div>
        </div>

        <!-- Error / no data -->
        <div v-if="error && !breakdown" class="mx-4 rounded-xl bg-red-500/10 border border-red-500/30 p-5 text-center mb-5">
          <p class="text-red-400 text-sm">{{ error }}</p>
        </div>

        <!-- ── Coach note ── -->
        <div v-if="sessionNote" class="mx-4 rounded-xl p-4 mb-5 border-l-4 shadow-sm"
             :style="{ backgroundColor: cfg.color + '15', borderColor: cfg.color }">
          <p class="text-sm font-black uppercase tracking-widest mb-1" :style="{ color: cfg.color }">💬 Coach Notes</p>
          <p class="text-white text-base leading-relaxed font-medium">{{ sessionNote }}</p>
        </div>

        <!-- ══════════ BATTING sections ══════════ -->
        <template v-if="sessionType === 'batting' && breakdown">
          <!-- Exit Velocity -->
          <section v-if="breakdown.avgEV > 0" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">⚡ Exit Velocity</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Avg Exit Velocity" :value="breakdown.avgEV" unit="mph" :min="40" :max="110" :thresholds="[68, 87]"/>
              <StatRow label="Top Exit Velocity"  :value="breakdown.topEV" unit="mph" :min="50" :max="115" :thresholds="[80, 97]"/>
            </div>
          </section>

          <!-- Contact Quality -->
          <section v-if="breakdown.evTotal > 0" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">💥 Contact Quality ({{ breakdown.evTotal }} swings)</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4">
              <StatRow label="Hard Contact %" :value="breakdown.hardPct" unit="%" :min="0" :max="70" :thresholds="[20, 40]"/>
              <SegBar :segments="[
                { pct: breakdown.hardPct, color: '#2ECC71', label: `Hard ${breakdown.hardPct}%` },
                { pct: breakdown.avgPct,  color: '#F39C12', label: `Avg ${breakdown.avgPct}%`  },
                { pct: breakdown.weakPct, color: '#E74C3C', label: `Weak ${breakdown.weakPct}%` },
              ]" class="mt-3"/>
            </div>
          </section>

          <!-- Launch Profile -->
          <section v-if="breakdown.tohTotal > 0" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">🚀 Launch Profile ({{ breakdown.tohTotal }} swings)</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4">
              <SegBar :segments="[
                { pct: breakdown.ldPct,  color: '#3498DB', label: `LD ${breakdown.ldPct}%`  },
                { pct: breakdown.flyPct, color: '#2ECC71', label: `FB ${breakdown.flyPct}%` },
                { pct: breakdown.pfPct,  color: '#F39C12', label: `PF ${breakdown.pfPct}%`  },
                { pct: breakdown.gbPct,  color: '#E74C3C', label: `GB ${breakdown.gbPct}%`  },
              ]" class="mb-3"/>
              <div class="grid grid-cols-4 gap-2 mt-3">
                <div v-for="item in [
                  { label: 'Line Drive', pct: breakdown.ldPct, color: '#3498DB' },
                  { label: 'Fly Ball',   pct: breakdown.flyPct, color: '#2ECC71' },
                  { label: 'Pop Fly',    pct: breakdown.pfPct, color: '#F39C12' },
                  { label: 'Ground Ball',pct: breakdown.gbPct, color: '#E74C3C' },
                ]" :key="item.label" class="text-center">
                  <div class="text-xl font-black" :style="{ color: item.color }">{{ item.pct }}%</div>
                  <div class="text-[9px] text-white/30 mt-0.5">{{ item.label }}</div>
                </div>
              </div>
            </div>
          </section>

          <!-- At-Bat Quality -->
          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">🎯 At-Bat Quality</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Competitive Swing %" :value="breakdown.compPct" unit="%" :min="0" :max="80" :thresholds="[25, 45]"/>
              <StatRow label="Miss Rate" :value="breakdown.missPct" unit="%" :min="0" :max="60" :thresholds="[30, 15]" :reverse="true"/>
            </div>
          </section>

          <!-- FPS Components -->
          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">📊 Score Breakdown</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow v-for="c in [
                { key: 'contactScore', label: '🟢 Contact Quality (30%)'   },
                { key: 'evScore',      label: '🔵 Exit Velocity (25%)'      },
                { key: 'launchScore',  label: '🟡 Launch Profile (20%)'     },
                { key: 'compScore',    label: '🟣 Competitive Swings (15%)' },
                { key: 'missScore',    label: '🔴 Miss Control (10%)'       },
              ]" :key="c.key" :label="c.label" :value="breakdown[c.key]" unit="" :min="0" :max="100" :thresholds="[60, 80]"/>
            </div>
          </section>

          <section v-if="scriptedBp && canScriptedBp" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">📋 Scripted BP Scorecard</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-3">
                <div class="rounded-lg bg-white/5 border border-white/10 px-3 py-2">
                  <p class="text-[10px] uppercase tracking-widest text-white/50">Team Score</p>
                  <p class="text-2xl font-black" :style="{ color: gradeColor(scriptedBp.teamScore) }">{{ scriptedBp.teamScore }}</p>
                </div>
                <div class="rounded-lg bg-white/5 border border-white/10 px-3 py-2">
                  <p class="text-[10px] uppercase tracking-widest text-white/50">Batters</p>
                  <p class="text-2xl font-black text-white">{{ scriptedBp.batters.length }}</p>
                </div>
                <div class="rounded-lg bg-white/5 border border-white/10 px-3 py-2">
                  <p class="text-[10px] uppercase tracking-widest text-white/50">Total Swings</p>
                  <p class="text-2xl font-black text-white">{{ scriptedBp.totalSwings }}</p>
                </div>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-200">
                  <thead>
                    <tr class="text-[11px] uppercase tracking-widest text-white/45 border-b border-white/10">
                      <th class="py-2 pr-3">Batter</th>
                      <th class="py-2 pr-3 text-right">Score</th>
                      <th class="py-2 pr-3 text-right">Swings</th>
                      <th class="py-2 pr-3">Best Round</th>
                      <th class="py-2">Focus Round</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="b in scriptedBp.batters" :key="b.id" class="border-b border-white/5">
                      <td class="py-2 pr-3 font-bold text-white">{{ b.name }}</td>
                      <td class="py-2 pr-3 text-right font-black" :style="{ color: gradeColor(b.score) }">{{ b.score }} · {{ b.grade }}</td>
                      <td class="py-2 pr-3 text-right">{{ b.swings }}</td>
                      <td class="py-2 pr-3">{{ b.bestRound || '—' }}</td>
                      <td class="py-2">{{ b.worstRound || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </template>

        <!-- ══════════ BULLPEN sections ══════════ -->
        <template v-if="sessionType === 'bullpen' && breakdown">
          <!-- Velocity -->
          <section v-if="breakdown.maxFBVelo > 0" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">🔥 Velocity</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Max FB Velo" :value="breakdown.maxFBVelo" unit="mph" :min="50" :max="105" :thresholds="[80, 90]"/>
              <StatRow label="Avg FB Velo" :value="breakdown.avgFBVelo" unit="mph" :min="50" :max="105" :thresholds="[75, 87]"/>
            </div>
          </section>

          <!-- Command -->
          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">🎯 Command ({{ breakdown.total }} pitches)</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Overall Strike %" :value="breakdown.strikePct" unit="%" :min="0" :max="100" :thresholds="[45, 65]"/>
              <template v-for="g in breakdown.typeGroups" :key="g.label">
                <StatRow :label="`${g.label}  ${g.strikes}/${g.count} strikes`" :value="g.strikePct" unit="%" :min="0" :max="100" :thresholds="[45, 65]"/>
              </template>
              <StatRow label="Competitive Pitch %" :value="breakdown.compPct" unit="%" :min="0" :max="100" :thresholds="[50, 70]"/>
            </div>
          </section>

          <!-- Pitch type cards -->
          <section v-if="breakdown.typeGroups?.some(g => g.avgMph > 0)" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">⚡ Avg Velo by Pitch Type</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow v-for="g in breakdown.typeGroups.filter(group => group.avgMph > 0)" :key="`velo-${g.label}`" :label="g.label" :value="g.avgMph" unit="mph" :min="50" :max="105" :thresholds="[72, 85]"/>
            </div>
          </section>

          <!-- Pitch type cards -->
          <section v-if="breakdown.typeGroups?.length" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">⚾ Pitch Type Breakdown</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 divide-y divide-white/5 overflow-hidden">
              <div v-for="g in breakdown.typeGroups" :key="g.label" class="flex items-center gap-3 px-4 py-3">
                <span class="text-xs font-black px-2.5 py-1 rounded-lg" :style="{ backgroundColor: cfg.color + '33', color: cfg.color }">{{ g.label }}</span>
                <span class="text-white/70 text-sm">{{ g.count }} pitches</span>
                <span class="text-white/50 text-sm">{{ g.strikePct }}% strikes</span>
                <span v-if="g.avgMph" class="text-white/50 text-sm ml-auto">{{ g.avgMph }} mph</span>
              </div>
            </div>
          </section>

          <!-- Velo fade -->
          <section v-if="breakdown.firstAvgVelo > 0" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">📉 Velocity Trend</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4">
              <div class="flex items-center justify-around">
                <div class="text-center">
                  <div class="text-2xl font-black text-white">{{ breakdown.firstAvgVelo }}</div>
                  <div class="text-[10px] text-white/35 mt-1">Early avg (mph)</div>
                </div>
                <div class="text-white/25 text-lg">→</div>
                <div class="text-center">
                  <div class="text-2xl font-black" :class="breakdown.veloDrop > 3 ? 'text-red-400' : 'text-white'">{{ breakdown.lastAvgVelo }}</div>
                  <div class="text-[10px] text-white/35 mt-1">Late avg (mph)</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-black" :class="breakdown.veloDrop > 3 ? 'text-red-400' : 'text-green-400'">-{{ breakdown.veloDrop }}</div>
                  <div class="text-[10px] text-white/35 mt-1">Drop</div>
                </div>
              </div>
            </div>
          </section>

          <!-- BPS Components (matches the app report) -->
          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">📊 BPS Components</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow v-for="c in [
                { key: 'veloStabilityScore', label: 'Velo Stability' },
                { key: 'contactSuppressionScore', label: 'Contact Suppression' },
                { key: 'bps', label: 'Bullpen Performance Score' },
              ]" :key="c.key" :label="c.label" :value="breakdown[c.key]" unit="" :min="0" :max="100" :thresholds="[60, 80]"/>
            </div>
          </section>

          <section v-if="scriptedBullpen && canScriptedBullpen" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">📋 Scripted Bullpen Scorecard</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4">
              <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-3">
                <div class="rounded-lg bg-white/5 border border-white/10 px-3 py-2">
                  <p class="text-[10px] uppercase tracking-widest text-white/50">Execution Score</p>
                  <p class="text-xl font-black text-white">
                    {{ scriptedBullpen.sessionAvgScore != null ? `${scriptedBullpen.sessionAvgScore}/${scriptedBullpen.sessionAvgMax}` : '—' }}
                  </p>
                </div>
                <div class="rounded-lg bg-white/5 border border-white/10 px-3 py-2">
                  <p class="text-[10px] uppercase tracking-widest text-white/50">Session Grade</p>
                  <p class="text-xl font-black" :style="{ color: scriptedBullpen.sessionGrade?.color || '#e2e8f0' }">
                    {{ scriptedBullpen.sessionAvgPct != null ? `${scriptedBullpen.sessionAvgPct}%` : '—' }}
                  </p>
                </div>
                <div class="rounded-lg bg-white/5 border border-white/10 px-3 py-2">
                  <p class="text-[10px] uppercase tracking-widest text-white/50">Strike %</p>
                  <p class="text-xl font-black text-white">{{ scriptedBullpen.strikePct }}%</p>
                </div>
                <div class="rounded-lg bg-white/5 border border-white/10 px-3 py-2">
                  <p class="text-[10px] uppercase tracking-widest text-white/50">Avg Off Target</p>
                  <p class="text-xl font-black text-white">{{ scriptedBullpen.avgDist ?? '—' }}</p>
                </div>
              </div>

              <div v-if="scriptedBullpen.pitchers?.length" class="space-y-2">
                <div v-for="p in scriptedBullpen.pitchers" :key="p.id" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 flex items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-black text-white">{{ p.name }}</p>
                    <p class="text-xs text-white/60">{{ p.total }} pitches · {{ p.strikePct }}% strikes</p>
                  </div>
                  <div class="text-right" v-if="p.avgScore != null">
                    <p class="text-lg font-black" :style="{ color: execGrade(p.avgPct)?.color || '#e2e8f0' }">{{ p.avgScore }}/{{ p.avgMax }}</p>
                    <p class="text-[11px] font-bold text-white/60">{{ p.avgPct }}% · {{ execGrade(p.avgPct)?.label || '—' }}</p>
                  </div>
                  <div v-else class="text-xs text-white/50">No scripted data</div>
                </div>
              </div>

              <div v-if="scriptedBullpen.pitches?.length" class="mt-4">
                <p class="text-[10px] uppercase tracking-widest text-white/50 mb-2">Pitch by Pitch — click score for breakdown</p>
                <div class="overflow-x-auto">
                  <table class="min-w-full text-left text-sm text-slate-200">
                    <thead>
                      <tr class="text-[11px] uppercase tracking-widest text-white/45 border-b border-white/10">
                        <th class="py-2 pr-3">#</th>
                        <th class="py-2 pr-3">Type</th>
                        <th class="py-2 pr-3">Pitcher</th>
                        <th class="py-2 pr-3">Result</th>
                        <th class="py-2 pr-3 text-right">Grade</th>
                        <th class="py-2 pr-3 text-right">Off</th>
                        <th class="py-2 text-right">MPH</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in scriptedBullpen.pitches" :key="row.id" class="border-b border-white/5">
                        <td class="py-2 pr-3 text-white/70">{{ row.number }}</td>
                        <td class="py-2 pr-3 font-bold text-white">{{ row.type }}</td>
                        <td class="py-2 pr-3">{{ row.pitcher }}</td>
                        <td class="py-2 pr-3 font-black" :style="{ color: row.resultColor }">{{ row.resultText }}</td>
                        <td class="py-2 pr-3 text-right">
                          <button
                            v-if="row.score != null"
                            @click="selectedBullpenPitch = row"
                            class="inline-flex items-center gap-1 rounded-md border border-white/20 bg-white/10 px-2 py-1 font-black hover:bg-white/15"
                            :style="{ color: row.grade?.color || '#e2e8f0' }"
                          >
                            {{ row.pct }}% · {{ row.score }}/{{ row.maxScore }}
                          </button>
                          <span v-else class="text-white/40">—</span>
                        </td>
                        <td class="py-2 pr-3 text-right">{{ row.offTarget != null ? Number(row.offTarget).toFixed(1) : '—' }}</td>
                        <td class="py-2 text-right">{{ row.mph || '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </section>
        </template>

        <!-- ══════════ CAGE sections ══════════ -->
        <template v-if="sessionType === 'cage' && breakdown">
          <!-- EV stats -->
          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">⚡ Exit Velocity</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow v-if="breakdown.avgEV" label="Avg Exit Velocity" :value="breakdown.avgEV" unit="mph" :min="40" :max="110" :thresholds="[68, 87]"/>
              <StatRow v-if="breakdown.maxEV" label="Top Exit Velocity"  :value="breakdown.maxEV" unit="mph" :min="50" :max="115" :thresholds="[80, 97]"/>
              <StatRow v-if="breakdown.hardHitPct != null" label="Hard Hit % (≥90 mph)" :value="breakdown.hardHitPct" unit="%" :min="0" :max="60" :thresholds="[10, 35]"/>
              <StatRow v-if="breakdown.barrelPct != null"  label="Barrel %" :value="breakdown.barrelPct" unit="%" :min="0" :max="30" :thresholds="[5, 15]"/>
            </div>
          </section>

          <!-- Launch Angle -->
          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">📐 Launch Angle</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow v-if="breakdown.avgLA != null"      label="Avg Launch Angle"  :value="breakdown.avgLA"      unit="°"  :min="-10" :max="50" :thresholds="[8, 25]"/>
              <StatRow v-if="breakdown.sweetSpotPct != null" label="Sweet Spot % (8–32°)" :value="breakdown.sweetSpotPct" unit="%" :min="0" :max="80" :thresholds="[15, 40]"/>
              <StatRow v-if="breakdown.swingQualityPct != null" label="Swing Quality %" :value="breakdown.swingQualityPct" unit="%" :min="0" :max="80" :thresholds="[20, 50]"/>
            </div>
          </section>

          <!-- Spray Chart -->
          <section v-if="breakdown.sprayTotal > 0" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">🎯 Spray ({{ breakdown.sprayTotal }} swings)</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4">
              <div class="grid grid-cols-3 gap-3 text-center">
                <div v-for="item in [
                  { label: 'Pull',   pct: breakdown.pullPct,  color: '#3498DB' },
                  { label: 'Middle', pct: breakdown.midPct,   color: '#2ECC71' },
                  { label: 'Oppo',   pct: breakdown.oppoPct,  color: '#E74C3C' },
                ]" :key="item.label">
                  <div class="text-2xl font-black" :style="{ color: item.color }">{{ item.pct ?? '—' }}%</div>
                  <div class="text-[10px] text-white/35 mt-0.5">{{ item.label }}</div>
                </div>
              </div>
            </div>
          </section>
        </template>

        <!-- ══════════ EXIT VELOCITY sections ══════════ -->
        <template v-if="sessionType === 'exit_velocity' && breakdown">
          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">⚡ Velocity Stats</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Avg Exit Velocity" :value="breakdown.avgEV" unit="mph" :min="40" :max="110" :thresholds="[68, 87]"/>
              <StatRow label="Top Exit Velocity"  :value="breakdown.topEV" unit="mph" :min="50" :max="115" :thresholds="[80, 97]"/>
              <StatRow label="Hard Hit % (≥90 mph)" :value="breakdown.hhPct" unit="%" :min="0" :max="80" :thresholds="[25, 50]"/>
            </div>
          </section>

          <section v-if="breakdown.total > 0" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">📊 Trajectory Split ({{ breakdown.total }} swings)</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4">
              <SegBar :segments="[
                { pct: breakdown.ldPct, color: '#3498DB', label: `LD ${breakdown.ldPct}%` },
                { pct: breakdown.fbPct, color: '#2ECC71', label: `FB ${breakdown.fbPct}%` },
                { pct: breakdown.gbPct, color: '#E74C3C', label: `GB ${breakdown.gbPct}%` },
                { pct: breakdown.puPct, color: '#F39C12', label: `PU ${breakdown.puPct}%` },
              ]" class="mb-3"/>
              <div class="grid grid-cols-4 gap-2 mt-3">
                <div v-for="item in [
                  { label: 'Line Drive', pct: breakdown.ldPct, color: '#3498DB' },
                  { label: 'Fly Ball',   pct: breakdown.fbPct, color: '#2ECC71' },
                  { label: 'Ground',     pct: breakdown.gbPct, color: '#E74C3C' },
                  { label: 'Pop-Up',     pct: breakdown.puPct, color: '#F39C12' },
                ]" :key="item.label" class="text-center">
                  <div class="text-xl font-black" :style="{ color: item.color }">{{ item.pct }}%</div>
                  <div class="text-[9px] text-white/30 mt-0.5">{{ item.label }}</div>
                </div>
              </div>
            </div>
          </section>
        </template>

        <!-- ══════════ LONG TOSS sections ══════════ -->
        <template v-if="sessionType === 'long_toss' && breakdown">
          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">📏 Distance</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Avg Distance"  :value="breakdown.avgDist" unit=" ft" :min="0"  :max="300" :thresholds="[100, 200]"/>
              <StatRow label="Max Distance"  :value="breakdown.maxDist" unit=" ft" :min="0"  :max="350" :thresholds="[150, 250]"/>
              <StatRow label="Zero-Hop Rate" :value="breakdown.zeroHopPct" unit="%" :min="0" :max="100" :thresholds="[20, 50]"/>
            </div>
          </section>

          <!-- Distance distribution -->
          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">📊 Distance Distribution ({{ breakdown.total }} throws)</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-2">
              <div v-for="(count, bucket) in breakdown.buckets" :key="bucket" class="flex items-center gap-3">
                <span class="text-xs font-bold text-white/50 w-20 shrink-0">{{ bucket }} ft</span>
                <div class="flex-1 h-4 bg-white/5 rounded-full overflow-hidden">
                  <div class="h-full rounded-full" :style="{ width: breakdown.total ? (count / breakdown.total * 100) + '%' : '0%', backgroundColor: cfg.color }"/>
                </div>
                <span class="text-xs font-black text-white/60 w-6 text-right">{{ count }}</span>
              </div>
            </div>
          </section>
        </template>

        <!-- ══════════ WEIGHT BALL sections ══════════ -->
        <template v-if="sessionType === 'weight_ball' && breakdown">
          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">💨 Velocity</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Avg Velocity" :value="breakdown.avgVelo" unit=" mph" :min="50" :max="110" :thresholds="[70, 88]"/>
              <StatRow label="Top Velocity" :value="breakdown.topVelo" unit=" mph" :min="60" :max="120" :thresholds="[80, 95]"/>
            </div>
          </section>

          <section v-if="breakdown.weightBreakdown?.length" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">⚾ By Ball Weight</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 divide-y divide-white/5 overflow-hidden">
              <div v-for="w in breakdown.weightBreakdown" :key="w.weight" class="flex items-center gap-3 px-4 py-3">
                <span class="text-xs font-black px-2.5 py-1 rounded-lg min-w-[48px] text-center" :style="{ backgroundColor: cfg.color + '33', color: cfg.color }">{{ w.weight }} oz</span>
                <span class="text-white/70 text-sm">{{ w.count }} throws</span>
                <span class="text-white/50 text-sm">avg {{ w.avgVelo }} mph</span>
                <span class="text-white/40 text-sm ml-auto">top {{ w.maxVelo }} mph</span>
              </div>
            </div>
          </section>
        </template>

        <!-- ══════════ LIVE AB sections ══════════ -->
        <template v-if="sessionType === 'live_ab' && breakdown">
          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">📊 Score Breakdown</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Hitter Score" :value="breakdown.hitterAvg" unit="" :min="0" :max="100" :thresholds="[55, 70]"/>
              <StatRow label="Pitcher Score" :value="breakdown.pitcherAvg" unit="" :min="0" :max="100" :thresholds="[55, 70]"/>
              <StatRow label="Competition" :value="breakdown.competitionAvg" unit="" :min="0" :max="100" :thresholds="[55, 70]"/>
            </div>
          </section>

          <section v-if="breakdown.topHitters.length || breakdown.topPitchers.length" class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">🏆 Top Performers</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div v-if="breakdown.topHitters.length" class="rounded-xl bg-white/5 border border-white/8 p-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-white/45 mb-2">⚾ Hitters</p>
                <div v-for="player in breakdown.topHitters" :key="`h-${player.name}`" class="flex items-center gap-3 py-2 border-b border-white/5 last:border-0">
                  <span class="min-w-0 flex-1 truncate text-sm font-bold text-white">{{ player.name }}</span>
                  <span class="text-xs text-white/45">{{ player.abs }} AB</span>
                  <span class="rounded-full px-2.5 py-1 text-xs font-black" :style="{ color: gradeColor(player.avg), backgroundColor: gradeColor(player.avg) + '22' }">{{ player.avg }}</span>
                </div>
              </div>
              <div v-if="breakdown.topPitchers.length" class="rounded-xl bg-white/5 border border-white/8 p-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-white/45 mb-2">🔥 Pitchers</p>
                <div v-for="player in breakdown.topPitchers" :key="`p-${player.name}`" class="flex items-center gap-3 py-2 border-b border-white/5 last:border-0">
                  <span class="min-w-0 flex-1 truncate text-sm font-bold text-white">{{ player.name }}</span>
                  <span class="text-xs text-white/45">{{ player.abs }} AB</span>
                  <span class="rounded-full px-2.5 py-1 text-xs font-black" :style="{ color: gradeColor(player.avg), backgroundColor: gradeColor(player.avg) + '22' }">{{ player.avg }}</span>
                </div>
              </div>
            </div>
          </section>

          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">⚾ Result Distribution</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 flex flex-wrap gap-2">
              <div v-for="(count, result) in breakdown.resultDist" :key="result" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-center min-w-[82px]">
                <p class="text-sm font-black" :style="{ color: cfg.color }">{{ result }}</p>
                <p class="text-xl font-black text-white">{{ count }}</p>
                <p class="text-[10px] text-white/40">{{ Math.round(count / breakdown.totalPAs * 100) }}%</p>
              </div>
            </div>
          </section>

          <section class="mx-4 mb-5">
            <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">🎯 Pitches Per At-Bat</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-2">
              <div v-for="(count, bucket) in breakdown.pitchDistBuckets" :key="bucket" v-show="count > 0" class="flex items-center gap-3">
                <span class="w-20 shrink-0 text-xs font-bold text-white/55">{{ bucket }} pitch{{ String(bucket) === '1' ? '' : 'es' }}</span>
                <div class="h-3 flex-1 overflow-hidden rounded-full bg-white/5">
                  <div class="h-full rounded-full" :style="{ width: (count / breakdown.totalPAs * 100) + '%', backgroundColor: cfg.color }"/>
                </div>
                <span class="w-10 text-right text-xs font-black text-white/60">{{ count }} AB</span>
              </div>
            </div>
          </section>
        </template>

        <!-- ══════════ Auto-feedback tips (all types) ══════════ -->
        <section v-if="tips.length" class="mx-4 mb-5">
          <h3 class="text-[12px] font-black uppercase tracking-widest text-white/85 mb-2">💡 What This Means</h3>
          <div class="space-y-2">
            <div v-for="(tip, i) in tips" :key="i"
                 class="flex items-start gap-3 p-3.5 rounded-xl bg-white/5 border border-white/8">
              <span class="text-xl shrink-0 mt-0.5">{{ tip.icon }}</span>
              <p class="text-white/75 text-sm leading-relaxed">{{ tip.text }}</p>
            </div>
          </div>
        </section>

      </template>

      <div v-if="selectedBullpenPitch" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4" @click.self="selectedBullpenPitch = null">
        <div class="w-full max-w-2xl rounded-xl border border-white/20 bg-slate-900 p-4">
          <div class="mb-3 flex items-start justify-between gap-3">
            <div>
              <p class="text-xs uppercase tracking-widest text-white/50">Pitch #{{ selectedBullpenPitch.number }} · {{ selectedBullpenPitch.type }}</p>
              <p class="text-lg font-black text-white">{{ selectedBullpenPitch.pitcher }}</p>
              <p class="text-sm" :style="{ color: selectedBullpenPitch.grade?.color || '#e2e8f0' }">
                {{ selectedBullpenPitch.pct }}% · {{ selectedBullpenPitch.score }}/{{ selectedBullpenPitch.maxScore }}
                <span class="text-white/60">({{ selectedBullpenPitch.grade?.label || '—' }})</span>
              </p>
            </div>
            <button class="rounded-md border border-white/20 px-2 py-1 text-sm text-white/80 hover:bg-white/10" @click="selectedBullpenPitch = null">Close</button>
          </div>

          <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <div v-for="item in [
              { key: 'loc', title: 'Location', maxKey: 'locMax', note: 'locNote' },
              { key: 'velo', title: 'Velocity', maxKey: 'veloMax', note: 'veloNote' },
              { key: 'traj', title: 'Trajectory', maxKey: 'trajMax', note: 'trajNote' },
              { key: 'comp', title: 'Competitive', maxKey: 'compMax', note: 'compNote' },
            ]" :key="item.key" class="rounded-lg border border-white/15 bg-white/5 p-3">
              <p class="text-[10px] uppercase tracking-widest text-white/45">{{ item.title }}</p>
              <p class="mt-1 text-xl font-black text-white">
                {{ executionBreakdown(selectedBullpenPitch.pitch)[item.key] ?? '—' }}
                <span class="text-sm font-bold text-white/50">/{{ executionBreakdown(selectedBullpenPitch.pitch)[item.maxKey] ?? '—' }}</span>
              </p>
              <p class="mt-1 text-xs text-white/70">{{ executionBreakdown(selectedBullpenPitch.pitch)[item.note] }}</p>
            </div>
          </div>
        </div>
      </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.session-report .rounded-xl.bg-white\/5,
.session-report .rounded-2xl.bg-white\/5 {
  background-color: rgba(15, 23, 42, 0.92) !important;
}

.session-report .border-white\/8,
.session-report .border-white\/10 {
  border-color: rgba(148, 163, 184, 0.35) !important;
}

.session-report .text-white\/30,
.session-report .text-white\/35,
.session-report .text-white\/40,
.session-report .text-white\/50,
.session-report .text-white\/60,
.session-report .text-white\/70,
.session-report .text-white\/75,
.session-report .text-white\/80 {
  color: rgba(241, 245, 249, 0.9) !important;
}
</style>
