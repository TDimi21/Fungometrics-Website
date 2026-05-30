<script setup>
/**
 * SessionReport.vue
 *
 * Unified session report page for all 6 training types:
 *   batting (B)  → GET /statistics/{id}/batting
 *   bullpen (P)  → GET /statistics/{id}/bullpen
 *   cage (C)     → GET /statistics/{id}/cage
 *   exit_vel (T/EV) → GET /statistics/{id}/exitvelocity
 *   long_toss (T/LT) → GET /statistics/{id}/longtoss
 *   weight_ball (T/WB) → GET /statistics/{id}/weightball
 *
 * Route: /session/report/:id/:type
 *   :type = batting | bullpen | cage | exit_velocity | long_toss | weight_ball
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const route  = useRoute()
const router = useRouter()
const { axiosGet } = useAxiosAuth()

const sessionId   = route.params.id
const sessionType = route.params.type   // batting|bullpen|cage|exit_velocity|long_toss|weight_ball
const sessionDate = route.query.date ?? null
const sessionNote = route.query.note ?? null

const loading   = ref(true)
const error     = ref(null)
const rawData   = ref(null)
const breakdown = ref(null)

// ─── Type config ──────────────────────────────────────────────────────────────
const TYPE_CONFIG = {
  batting:      { label: 'BATTING PRACTICE REPORT', abbr: 'FPS', color: '#F39C12', endpoint: 'batting' },
  bullpen:      { label: 'BULLPEN SESSION REPORT',  abbr: 'BPS', color: '#9B59B6', endpoint: 'bullpen' },
  cage:         { label: 'CAGE SESSION REPORT',     abbr: 'FCS', color: '#1ABC9C', endpoint: 'cage' },
  exit_velocity:{ label: 'EXIT VELOCITY REPORT',   abbr: 'EVS', color: '#E74C3C', endpoint: 'exitvelocity' },
  long_toss:    { label: 'LONG TOSS REPORT',        abbr: 'LTS', color: '#EC407A', endpoint: 'longtoss' },
  weight_ball:  { label: 'WEIGHTED BALL REPORT',    abbr: 'WBS', color: '#F1C40F', endpoint: 'weightball' },
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
  if (score >= 90) return '🔥 Elite'
  if (score >= 80) return 'Strong'
  if (score >= 70) return 'Productive'
  if (score >= 60) return 'Development'
  return 'Needs Work'
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
  const t = String(p.pitch_type || p.type_of_pitch || p.pitch || '').toUpperCase().trim()
  if (t === 'FB' || t === 'FASTBALL' || t === '4S' || t === '2S') return 'FB'
  if (t === 'CH' || t === 'CHANGEUP' || t === 'CHANGE') return 'CH'
  if (t === 'SL' || t === 'SLIDER') return 'SL'
  if (t === 'CV' || t === 'CURVE' || t === 'CURVEBALL' || t === 'CB') return 'CV'
  return 'Other'
}
function isStrikePitch(p) {
  const bs = String(p.ball_strike || p.pitch_result || p.result || '').toUpperCase()
  return bs.includes('STRIKE') || bs === 'S' || p.is_strike === true || p.is_strike === 1
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
    const tr = String(p.trajectory || p.type_of_hit || '').toUpperCase()
    if (tr && CONTACT_SCORES[tr] != null) { csSum += CONTACT_SCORES[tr]; csCount++ }
  })
  const contactSuppressionScore = csCount > 0 ? csSum / csCount : 70
  let compCount = 0
  pitches.forEach(p => {
    const tr = String(p.trajectory || p.type_of_hit || '').toUpperCase()
    if (isStrikePitch(p) || tr === 'GB') compCount++
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
  return null
})

// ─── Load data ────────────────────────────────────────────────────────────────
onMounted(async () => {
  if (!sessionId) { error.value = 'No session ID.'; loading.value = false; return }
  try {
    const res = await axiosGet(`statistics/${sessionId}/${cfg.value.endpoint}`)
    rawData.value = res?.data?.data ?? res?.data ?? null
  } catch (e) {
    error.value = 'Failed to load session data.'
    loading.value = false
    return
  }

  const rd = rawData.value
  if (!rd) { error.value = 'No data returned.'; loading.value = false; return }

  if (sessionType === 'batting') {
    const balls = rd.ball_by_ball_results || rd.ball_x_ball || rd.ball_by_ball || rd.pitches || rd.results || (Array.isArray(rd) ? rd : [])
    breakdown.value = computeFPS(balls)
    if (!breakdown.value) error.value = 'No swing data found for this session.'
  }
  else if (sessionType === 'bullpen') {
    const nested = rd.bullpen || rd.P || rd.pitching || null
    const nestedArr = Array.isArray(nested) ? nested : nested ? (nested.ball_by_ball_results || nested.ball_x_ball || nested.pitches || null) : null
    const pitches = rd.ball_by_ball_results || rd.ball_by_ball || rd.ball_x_ball || rd.pitches || rd.results || nestedArr || (Array.isArray(rd) ? rd : [])
    breakdown.value = computeBPS(pitches)
    if (!breakdown.value) error.value = 'No pitch data found for this session.'
  }
  else if (sessionType === 'cage') {
    const rows = rd.cage_results || rd.results || rd.cage || (Array.isArray(rd) ? rd : [])
    breakdown.value = computeCageBreakdown(rows)
    if (!breakdown.value) error.value = 'No swing data found for this session.'
  }
  else if (sessionType === 'exit_velocity') {
    const rows = rd.results || rd.exit_velocity || (Array.isArray(rd) ? rd : [])
    breakdown.value = computeEVBreakdown(rows)
    if (!breakdown.value) error.value = 'No exit velocity data found for this session.'
  }
  else if (sessionType === 'long_toss') {
    const rows = rd.results || rd.long_toss || rd.longtoss || (Array.isArray(rd) ? rd : [])
    breakdown.value = computeLTBreakdown(rows)
    if (!breakdown.value) error.value = 'No long toss data found for this session.'
  }
  else if (sessionType === 'weight_ball') {
    const rows = rd.results || rd.weight_ball || rd.weightball || (Array.isArray(rd) ? rd : [])
    breakdown.value = computeWBBreakdown(rows)
    if (!breakdown.value) error.value = 'No weighted ball data found for this session.'
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
    <div class="min-h-screen bg-[#080c1a] pb-20">

      <!-- Header -->
      <div class="flex items-center gap-3 px-5 pt-6 pb-4">
        <button @click="router.back()" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition">
          <svg class="w-4 h-4 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <h1 class="text-sm font-black uppercase tracking-widest text-white/60">Session Report</h1>
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
        <div class="mx-4 rounded-2xl border border-white/10 overflow-hidden mb-5"
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
            <p class="text-white/35 text-xs">
              <template v-if="sessionType === 'batting' && breakdown">{{ breakdown.total }} swings</template>
              <template v-else-if="sessionType === 'bullpen' && breakdown">{{ breakdown.total }} pitches</template>
              <template v-else-if="sessionType === 'cage' && breakdown">{{ breakdown.totalSwings }} swings</template>
              <template v-else-if="sessionType === 'exit_velocity' && breakdown">{{ breakdown.total }} swings · Avg {{ breakdown.avgEV }} mph</template>
              <template v-else-if="sessionType === 'long_toss' && breakdown">{{ breakdown.total }} throws · Max {{ breakdown.maxDist }} ft</template>
              <template v-else-if="sessionType === 'weight_ball' && breakdown">{{ breakdown.total }} throws · Top {{ breakdown.topVelo }} mph</template>
            </p>
          </div>
        </div>

        <!-- Error / no data -->
        <div v-if="error && !breakdown" class="mx-4 rounded-xl bg-red-500/10 border border-red-500/30 p-5 text-center mb-5">
          <p class="text-red-400 text-sm">{{ error }}</p>
        </div>

        <!-- ── Coach note ── -->
        <div v-if="sessionNote" class="mx-4 rounded-xl p-4 mb-5 border-l-4"
             :style="{ backgroundColor: cfg.color + '15', borderColor: cfg.color }">
          <p class="text-xs font-black uppercase tracking-widest mb-1" :style="{ color: cfg.color }">💬 Coach Notes</p>
          <p class="text-white/80 text-sm leading-relaxed">{{ sessionNote }}</p>
        </div>

        <!-- ══════════ BATTING sections ══════════ -->
        <template v-if="sessionType === 'batting' && breakdown">
          <!-- Exit Velocity -->
          <section v-if="breakdown.avgEV > 0" class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">⚡ Exit Velocity</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Avg Exit Velocity" :value="breakdown.avgEV" unit="mph" :min="40" :max="110" :thresholds="[68, 87]"/>
              <StatRow label="Top Exit Velocity"  :value="breakdown.topEV" unit="mph" :min="50" :max="115" :thresholds="[80, 97]"/>
            </div>
          </section>

          <!-- Contact Quality -->
          <section v-if="breakdown.evTotal > 0" class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">💥 Contact Quality ({{ breakdown.evTotal }} swings)</h3>
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
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">🚀 Launch Profile ({{ breakdown.tohTotal }} swings)</h3>
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
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">🎯 At-Bat Quality</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Competitive Swing %" :value="breakdown.compPct" unit="%" :min="0" :max="80" :thresholds="[25, 45]"/>
              <StatRow label="Miss Rate" :value="breakdown.missPct" unit="%" :min="0" :max="60" :thresholds="[30, 15]" :reverse="true"/>
            </div>
          </section>

          <!-- FPS Components -->
          <section class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">📊 Score Breakdown</h3>
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
        </template>

        <!-- ══════════ BULLPEN sections ══════════ -->
        <template v-if="sessionType === 'bullpen' && breakdown">
          <!-- Velocity -->
          <section v-if="breakdown.maxFBVelo > 0" class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">🔥 Velocity</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Max FB Velo" :value="breakdown.maxFBVelo" unit="mph" :min="50" :max="105" :thresholds="[80, 90]"/>
              <StatRow label="Avg FB Velo" :value="breakdown.avgFBVelo" unit="mph" :min="50" :max="105" :thresholds="[75, 87]"/>
            </div>
          </section>

          <!-- Command -->
          <section class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">🎯 Command ({{ breakdown.total }} pitches)</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Overall Strike %" :value="breakdown.strikePct" unit="%" :min="0" :max="100" :thresholds="[45, 65]"/>
              <template v-for="g in breakdown.typeGroups" :key="g.label">
                <StatRow :label="`${g.label}  ${g.strikes}/${g.count} strikes`" :value="g.strikePct" unit="%" :min="0" :max="100" :thresholds="[45, 65]"/>
              </template>
              <StatRow label="Competitive Pitch %" :value="breakdown.compPct" unit="%" :min="0" :max="100" :thresholds="[50, 70]"/>
            </div>
          </section>

          <!-- Pitch type cards -->
          <section v-if="breakdown.typeGroups?.length" class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">⚾ Pitch Type Breakdown</h3>
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
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">📉 Velocity Trend</h3>
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

          <!-- BPS Components -->
          <section class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">📊 Score Breakdown</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow v-for="c in [
                { key: 'strikeRateScore',          label: 'Strike Rate (30%)'          },
                { key: 'pitchTypeScore',            label: 'Pitch Type Strikes (20%)'   },
                { key: 'contactSuppressionScore',   label: 'Contact Suppression (20%)'  },
                { key: 'competitiveScore',          label: 'Competitive Pitch % (15%)'  },
                { key: 'veloStabilityScore',        label: 'Velo Stability (15%)'       },
              ]" :key="c.key" :label="c.label" :value="breakdown[c.key]" unit="" :min="0" :max="100" :thresholds="[60, 80]"/>
            </div>
          </section>
        </template>

        <!-- ══════════ CAGE sections ══════════ -->
        <template v-if="sessionType === 'cage' && breakdown">
          <!-- EV stats -->
          <section class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">⚡ Exit Velocity</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow v-if="breakdown.avgEV" label="Avg Exit Velocity" :value="breakdown.avgEV" unit="mph" :min="40" :max="110" :thresholds="[68, 87]"/>
              <StatRow v-if="breakdown.maxEV" label="Top Exit Velocity"  :value="breakdown.maxEV" unit="mph" :min="50" :max="115" :thresholds="[80, 97]"/>
              <StatRow v-if="breakdown.hardHitPct != null" label="Hard Hit % (≥90 mph)" :value="breakdown.hardHitPct" unit="%" :min="0" :max="60" :thresholds="[10, 35]"/>
              <StatRow v-if="breakdown.barrelPct != null"  label="Barrel %" :value="breakdown.barrelPct" unit="%" :min="0" :max="30" :thresholds="[5, 15]"/>
            </div>
          </section>

          <!-- Launch Angle -->
          <section class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">📐 Launch Angle</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow v-if="breakdown.avgLA != null"      label="Avg Launch Angle"  :value="breakdown.avgLA"      unit="°"  :min="-10" :max="50" :thresholds="[8, 25]"/>
              <StatRow v-if="breakdown.sweetSpotPct != null" label="Sweet Spot % (8–32°)" :value="breakdown.sweetSpotPct" unit="%" :min="0" :max="80" :thresholds="[15, 40]"/>
              <StatRow v-if="breakdown.swingQualityPct != null" label="Swing Quality %" :value="breakdown.swingQualityPct" unit="%" :min="0" :max="80" :thresholds="[20, 50]"/>
            </div>
          </section>

          <!-- Spray Chart -->
          <section v-if="breakdown.sprayTotal > 0" class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">🎯 Spray ({{ breakdown.sprayTotal }} swings)</h3>
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
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">⚡ Velocity Stats</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Avg Exit Velocity" :value="breakdown.avgEV" unit="mph" :min="40" :max="110" :thresholds="[68, 87]"/>
              <StatRow label="Top Exit Velocity"  :value="breakdown.topEV" unit="mph" :min="50" :max="115" :thresholds="[80, 97]"/>
              <StatRow label="Hard Hit % (≥90 mph)" :value="breakdown.hhPct" unit="%" :min="0" :max="80" :thresholds="[25, 50]"/>
            </div>
          </section>

          <section v-if="breakdown.total > 0" class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">📊 Trajectory Split ({{ breakdown.total }} swings)</h3>
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
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">📏 Distance</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Avg Distance"  :value="breakdown.avgDist" unit=" ft" :min="0"  :max="300" :thresholds="[100, 200]"/>
              <StatRow label="Max Distance"  :value="breakdown.maxDist" unit=" ft" :min="0"  :max="350" :thresholds="[150, 250]"/>
              <StatRow label="Zero-Hop Rate" :value="breakdown.zeroHopPct" unit="%" :min="0" :max="100" :thresholds="[20, 50]"/>
            </div>
          </section>

          <!-- Distance distribution -->
          <section class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">📊 Distance Distribution ({{ breakdown.total }} throws)</h3>
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
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">💨 Velocity</h3>
            <div class="rounded-xl bg-white/5 border border-white/8 p-4 space-y-3">
              <StatRow label="Avg Velocity" :value="breakdown.avgVelo" unit=" mph" :min="50" :max="110" :thresholds="[70, 88]"/>
              <StatRow label="Top Velocity" :value="breakdown.topVelo" unit=" mph" :min="60" :max="120" :thresholds="[80, 95]"/>
            </div>
          </section>

          <section v-if="breakdown.weightBreakdown?.length" class="mx-4 mb-5">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">⚾ By Ball Weight</h3>
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

        <!-- ══════════ Auto-feedback tips (all types) ══════════ -->
        <section v-if="tips.length" class="mx-4 mb-5">
          <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-2">💡 What This Means</h3>
          <div class="space-y-2">
            <div v-for="(tip, i) in tips" :key="i"
                 class="flex items-start gap-3 p-3.5 rounded-xl bg-white/5 border border-white/8">
              <span class="text-xl shrink-0 mt-0.5">{{ tip.icon }}</span>
              <p class="text-white/75 text-sm leading-relaxed">{{ tip.text }}</p>
            </div>
          </div>
        </section>

      </template>
    </div>
  </Layout>
</template>

<!-- ── Inline sub-components ─────────────────────────────────────────────────── -->
<script>
// StatRow — a labelled horizontal stat bar
const StatRow = {
  props: { label: String, value: [Number, String], unit: { type: String, default: '' }, min: { type: Number, default: 0 }, max: { type: Number, default: 100 }, thresholds: { type: Array, default: () => [40, 70] }, reverse: { type: Boolean, default: false } },
  computed: {
    num() { return parseFloat(this.value) },
    pct() { return Math.min(100, Math.max(0, ((this.num - this.min) / (this.max - this.min)) * 100)) },
    color() {
      const n = this.num
      const [lo, hi] = this.thresholds
      if (!this.reverse) { return n >= hi ? '#2ECC71' : n >= lo ? '#F39C12' : '#E74C3C' }
      return n <= lo ? '#2ECC71' : n <= hi ? '#F39C12' : '#E74C3C'
    },
  },
  template: `
    <div class="flex items-center gap-3">
      <span class="text-xs text-white/50 w-44 shrink-0 truncate">{{ label }}</span>
      <div class="flex-1 h-1.5 bg-white/8 rounded-full overflow-hidden">
        <div class="h-full rounded-full transition-all duration-500" :style="{ width: pct + '%', backgroundColor: color }"/>
      </div>
      <span class="text-sm font-black w-16 text-right shrink-0" :style="{ color }">{{ num }}{{ unit }}</span>
    </div>`,
}

// SegBar — segmented colour bar
const SegBar = {
  props: { segments: Array },
  template: `
    <div>
      <div class="flex h-5 rounded overflow-hidden">
        <div v-for="s in segments.filter(x => x.pct > 0)" :key="s.label"
             :style="{ flex: s.pct, backgroundColor: s.color }"/>
      </div>
      <div class="flex justify-between mt-1">
        <span v-for="s in segments.filter(x => x.pct > 0)" :key="s.label"
              class="text-[10px] font-black" :style="{ color: s.color }">{{ s.label }}</span>
      </div>
    </div>`,
}

export default { components: { StatRow, SegBar } }
</script>
