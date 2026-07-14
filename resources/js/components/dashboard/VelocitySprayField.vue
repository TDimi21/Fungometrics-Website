<script setup>
// Field spray chart — ported from the app's CageFieldPlot. Two modes:
//   • heatmap (batting): fair territory split into spray sectors × depth bands,
//     each colored by average exit velocity with the mph labeled.
//   • spray (cage): every swing plotted as a dot at its real spray/distance,
//     colored by velocity, with a trajectory arc when a launch angle is known.
// Drawn field (mowing stripes, tan infield, diamond, mound) with the fence
// distance arcs, so ball positions line up exactly with the geometry.
import { ref, computed } from 'vue'
import { configFromLevel, createFieldMapper, fenceAt, baseCoords } from '@/utils/ballFieldGeometry'
import { fieldMarkToBalls } from '@/utils/fieldMark'

const props = defineProps({
  balls: { type: Array, default: () => [] },
  mode: { type: String, default: 'heatmap' },   // 'heatmap' | 'spray'
  useFieldMark: { type: Boolean, default: true }, // batting taps a grid; cage has real spray
  trajectoryLines: { type: Boolean, default: true },
  level: { type: String, default: 'hs' },
  // When false, hide the built-in filter chips + legend so a parent (e.g. the
  // Contact stats tab) can drive filtering from its own controls.
  showChrome: { type: Boolean, default: true },
})

const W = 460
const H = Math.round(W / 1.5)
const cfg = configFromLevel(props.level)
const mapper = createFieldMapper(W, H, cfg)
const fences = cfg.fences

const FILTERS = [
  { key: 'ALL', label: 'All' }, { key: 'GB', label: 'Ground' }, { key: 'PF', label: 'Pop Fly' },
  { key: 'FB', label: 'Fly Ball' }, { key: 'LD', label: 'Line Drive' },
]
const filter = ref('ALL')

const SPRAY_EDGES = [-45, -27, -9, 9, 27, 45]
const DEPTH_BANDS = [[0.0, 0.5], [0.5, 1.0]]

const velColor = (v, min, max, alpha = 0.72) => {
  const range = max - min
  const n = range > 0 ? Math.max(0, Math.min(1, (v - min) / range)) : 0.5
  const stops = [[59, 130, 246], [34, 197, 94], [234, 179, 8], [249, 115, 22], [239, 68, 68]]
  const seg = n * (stops.length - 1)
  const i = Math.min(stops.length - 2, Math.floor(seg))
  const t = seg - i
  const c = stops[i].map((a, k) => Math.round(a + (stops[i + 1][k] - a) * t))
  return `rgba(${c[0]},${c[1]},${c[2]},${alpha})`
}

// Cage records no trajectory code — only launch angle — so classify it the same
// way the app does (PF ≥45°, FB ≥25°, LD ≥8°, else GB). Batting already has a code.
const trajOf = (b) => {
  const t = String(b.trajectory ?? b.type_of_hit ?? '').toUpperCase()
  if (t) return t
  const la = Number(b.launch_angle)
  if (!Number.isFinite(la)) return ''
  if (la >= 45) return 'PF'
  if (la >= 25) return 'FB'
  if (la >= 8) return 'LD'
  return 'GB'
}
const filteredBalls = computed(() => {
  const all = Array.isArray(props.balls) ? props.balls : []
  if (filter.value === 'ALL') return all
  return all.filter((b) => trajOf(b) === filter.value)
})
// Normalize to { spray_angle, distance_travel, velocity, launch_angle, trajectory }.
const plottedBalls = computed(() => (props.useFieldMark
  ? fieldMarkToBalls(filteredBalls.value, cfg)
  : filteredBalls.value.map((b) => ({
    spray_angle: Number(b.spray_angle) || 0,
    distance_travel: Number(b.distance_travel) || 0,
    velocity: Number(b.velocity ?? b.launch_angle_velocity ?? b.exit_velocity) || 0,
    launch_angle: b.launch_angle != null ? Number(b.launch_angle) : null,
    trajectory: b.trajectory ?? b.type_of_hit ?? null,
  }))))
const swingCount = computed(() => filteredBalls.value.length)

const arcPoly = (a1, a2, f1, f2) => {
  const steps = 5
  const pts = []
  for (let i = 0; i <= steps; i++) { const a = a1 + ((a2 - a1) * i) / steps; const p = mapper.mapBall(f1 * fenceAt(a, fences), a); pts.push(`${p.px},${p.py}`) }
  for (let i = steps; i >= 0; i--) { const a = a1 + ((a2 - a1) * i) / steps; const p = mapper.mapBall(f2 * fenceAt(a, fences), a); pts.push(`${p.px},${p.py}`) }
  return pts.join(' ')
}

// heatmap zones (batting)
const zones = computed(() => {
  if (props.mode !== 'heatmap') return []
  const balls = plottedBalls.value
  const out = []
  for (let si = 0; si < SPRAY_EDGES.length - 1; si++) {
    const a1 = SPRAY_EDGES[si]; const a2 = SPRAY_EDGES[si + 1]
    for (const [f1, f2] of DEPTH_BANDS) {
      const zb = balls.filter((b) => {
        const s = b.spray_angle
        if (!(s >= a1 && s < a2)) return false
        const frac = (b.distance_travel || 0) / (fenceAt(s, fences) || 300)
        return frac >= f1 && (frac < f2 || f2 >= 1)
      })
      const evs = zb.map((b) => b.velocity).filter((v) => v > 0)
      const avg = evs.length ? evs.reduce((a, v) => a + v, 0) / evs.length : null
      out.push({ a1, a2, f1, f2, avg })
    }
  }
  const avgs = out.map((z) => z.avg).filter((v) => v != null)
  const min = avgs.length ? Math.min(...avgs) : 0
  const max = avgs.length ? Math.max(...avgs) : 1
  return out.map((z) => {
    const c = mapper.mapBall(((z.f1 + z.f2) / 2) * fenceAt((z.a1 + z.a2) / 2, fences), (z.a1 + z.a2) / 2)
    return { key: `${z.a1}_${z.f1}`, points: arcPoly(z.a1, z.a2, z.f1, z.f2), cx: c.px, cy: c.py, avg: z.avg, color: z.avg != null ? velColor(z.avg, min, max) : 'rgba(255,255,255,0.035)' }
  })
})

// spray dots + trajectory arcs (cage)
const dots = computed(() => {
  if (props.mode !== 'spray') return []
  const balls = plottedBalls.value.filter((b) => (b.distance_travel || 0) > 0)
  const vs = balls.map((b) => b.velocity).filter((v) => v > 0)
  const min = vs.length ? Math.min(...vs) : 0
  const max = vs.length ? Math.max(...vs) : 1
  const home = mapper.home
  return balls.map((b, i) => {
    const p = mapper.mapBall(b.distance_travel, b.spray_angle)
    const color = b.velocity > 0 ? velColor(b.velocity, min, max, 0.95) : 'rgba(255,255,255,0.7)'
    let trajPath = null
    if (props.trajectoryLines && b.launch_angle != null) {
      const mx = (home.px + p.px) / 2; const my = (home.py + p.py) / 2
      const len = Math.hypot(p.px - home.px, p.py - home.py)
      const arcFactor = Math.min(0.6, Math.max(0, (b.launch_angle || 0) / 90))
      const offset = Math.min(H * 0.85, len * arcFactor * 2)
      trajPath = `M ${home.px} ${home.py} Q ${mx} ${my - offset} ${p.px} ${p.py}`
    }
    return { key: i, cx: p.px, cy: p.py, color, trajPath }
  })
})

// ── field geometry ──
const infieldR = cfg.basePathFt * 1.05
const moundFt = cfg.basePathFt * 0.676
const fairWedge = (() => {
  const pts = [`M ${mapper.home.px} ${mapper.home.py}`]
  for (let a = -45; a <= 45; a += 2) { const p = mapper.mapBall(fenceAt(a, fences), a); pts.push(`L ${p.px} ${p.py}`) }
  return `${pts.join(' ')} Z`
})()
const infieldWedge = (() => {
  const pts = [`M ${mapper.home.px} ${mapper.home.py}`]
  for (let a = -45; a <= 45; a += 3) { const p = mapper.mapBall(infieldR, a); pts.push(`L ${p.px} ${p.py}`) }
  return `${pts.join(' ')} Z`
})()
const infieldArc = (() => {
  const pts = []
  for (let a = -45; a <= 45; a += 2) { const p = mapper.mapBall(infieldR, a); pts.push(`${a === -45 ? 'M' : 'L'} ${p.px} ${p.py}`) }
  return pts.join(' ')
})()
const fenceArc = (() => {
  const pts = []
  for (let a = -45; a <= 45; a += 2) { const p = mapper.mapBall(fenceAt(a, fences), a); pts.push(`${a === -45 ? 'M' : 'L'} ${p.px} ${p.py}`) }
  return pts.join(' ')
})()
const foulLines = (() => {
  const l = mapper.mapBall(fenceAt(-45, fences), -45); const r = mapper.mapBall(fenceAt(45, fences), 45)
  return [{ x1: mapper.home.px, y1: mapper.home.py, x2: l.px, y2: l.py }, { x1: mapper.home.px, y1: mapper.home.py, x2: r.px, y2: r.py }]
})()
const bc = baseCoords(cfg.basePathFt)
const diamond = (() => { const p = (a) => { const m = mapper.mapFeet(a[0], a[1]); return `${m.px},${m.py}` }; return `${p(bc.home)} ${p(bc.first)} ${p(bc.second)} ${p(bc.third)}` })()
const bases = [bc.first, bc.second, bc.third].map((a) => mapper.mapFeet(a[0], a[1]))
const homePt = mapper.mapFeet(0, 0)
const mound = mapper.mapFeet(0, moundFt)
const stripes = (() => {
  const sw = W / 13; const arr = []
  for (let i = 0; i < 13; i++) arr.push({ x: i * sw, w: sw + 1, fill: i % 2 ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)' })
  return arr
})()
const distanceLabels = (() => {
  const rows = [{ d: fences.lineL, a: -45 }, { d: fences.gapL, a: -22.5 }, { d: fences.center, a: 0 }, { d: fences.gapR, a: 22.5 }, { d: fences.lineR, a: 45 }]
  return rows.map(({ d, a }) => { const pt = mapper.mapBall(d, a); return { x: pt.px, y: pt.py, label: d } })
})()
</script>

<template>
  <div class="vsf">
    <div v-if="showChrome" class="vsf-filters">
      <button v-for="f in FILTERS" :key="f.key" class="vsf-chip" :class="{ 'vsf-chip--on': filter === f.key }" @click="filter = f.key">{{ f.label }}</button>
      <span class="vsf-count">{{ swingCount }} {{ mode === 'spray' ? 'result' : 'swing' }}{{ swingCount === 1 ? '' : 's' }}</span>
    </div>

    <div class="vsf-fieldwrap">
      <svg :viewBox="`0 0 ${W} ${H}`" class="vsf-svg" preserveAspectRatio="xMidYMid meet">
        <defs><clipPath :id="`fair-${W}`"><path :d="fairWedge" /></clipPath></defs>

        <!-- grass + mowing stripes -->
        <g :clip-path="`url(#fair-${W})`">
          <path :d="fairWedge" fill="#1c5a2c" />
          <rect v-for="(s, i) in stripes" :key="`s${i}`" :x="s.x" y="0" :width="s.w" :height="H" :fill="s.fill" />
          <path :d="infieldWedge" fill="#c08a52" opacity="0.92" />
          <polygon :points="diamond" fill="#2a7d3c" />
        </g>

        <!-- infield arc + foul lines -->
        <path :d="infieldArc" fill="none" stroke="rgba(255,255,255,0.55)" stroke-width="1" stroke-dasharray="4 4" />
        <line v-for="(l, i) in foulLines" :key="`fl${i}`" :x1="l.x1" :y1="l.y1" :x2="l.x2" :y2="l.y2" stroke="rgba(255,255,255,0.75)" stroke-width="1.4" />
        <!-- bases, mound, home -->
        <rect v-for="(b, i) in bases" :key="`b${i}`" :x="b.px - 3" :y="b.py - 3" width="6" height="6" fill="#fff" transform-origin="center" :transform="`rotate(45 ${b.px} ${b.py})`" />
        <circle :cx="mound.px" :cy="mound.py" r="7" fill="#c08a52" stroke="rgba(255,255,255,0.4)" stroke-width="0.8" />
        <circle :cx="homePt.px" :cy="homePt.py" r="4" fill="#fff" />

        <!-- HEATMAP zones -->
        <template v-if="mode === 'heatmap'">
          <polygon v-for="z in zones" :key="z.key" :points="z.points" :fill="z.color" stroke="rgba(255,255,255,0.22)" stroke-width="0.8" />
          <text v-for="z in zones" :key="`t${z.key}`" v-show="z.avg != null" :x="z.cx" :y="z.cy" fill="#fff" font-size="13" font-weight="800" text-anchor="middle" dominant-baseline="central" style="paint-order: stroke; stroke: rgba(0,0,0,0.55); stroke-width: 2.5px;">{{ z.avg != null ? Math.round(z.avg) : '' }}</text>
        </template>

        <!-- SPRAY dots + trajectory arcs -->
        <template v-else>
          <path v-for="d in dots" v-show="d.trajPath" :key="`p${d.key}`" :d="d.trajPath" fill="none" :stroke="d.color" stroke-width="1.2" stroke-opacity="0.45" />
          <circle v-for="d in dots" :key="`d${d.key}`" :cx="d.cx" :cy="d.cy" r="3.4" :fill="d.color" stroke="rgba(255,255,255,0.85)" stroke-width="0.8" />
        </template>

        <!-- fence + distance markers -->
        <path :d="fenceArc" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" />
        <g v-for="(d, i) in distanceLabels" :key="`d${i}`">
          <rect :x="d.x - 15" :y="d.y - 8" width="30" height="15" rx="3" fill="#7f1d1d" stroke="#ef4444" stroke-width="0.8" />
          <text :x="d.x" :y="d.y" fill="#fff" font-size="9" font-weight="800" text-anchor="middle" dominant-baseline="central">{{ d.label }}</text>
        </g>
      </svg>

      <div v-if="swingCount === 0" class="vsf-empty">{{ mode === 'spray' ? 'No cage results with a spray angle yet' : 'No batting swings yet' }}</div>
    </div>

    <div v-if="showChrome" class="vsf-legend">
      <span class="vsf-legend-label">Exit velocity</span>
      <span class="vsf-legend-bar"></span>
      <span class="vsf-legend-ends">low → high mph</span>
    </div>
  </div>
</template>

<style scoped>
.vsf { width: 100%; }
.vsf-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-bottom: 10px; }
.vsf-chip { font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 8px; cursor: pointer; color: rgba(255,255,255,.55); background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1); transition: .15s; }
.vsf-chip:hover { color: #fff; border-color: rgba(255,255,255,.25); }
.vsf-chip--on { color: #fff; background: #C00000; border-color: #C00000; }
.vsf-count { margin-left: auto; font-size: 10px; font-weight: 700; color: rgba(255,255,255,.35); }
.vsf-fieldwrap { position: relative; border-radius: 12px; overflow: hidden; background: radial-gradient(120% 80% at 50% 100%, #0e2033 0%, #070e18 70%); border: 1px solid rgba(255,255,255,.08); }
.vsf-svg { display: block; width: 100%; height: auto; }
.vsf-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.4); font-size: 13px; text-align: center; padding: 0 16px; pointer-events: none; }
.vsf-legend { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
.vsf-legend-label { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,.35); }
.vsf-legend-bar { flex: 1; height: 6px; border-radius: 99px; max-width: 180px; background: linear-gradient(90deg, #3b82f6, #22c55e, #eab308, #f97316, #ef4444); }
.vsf-legend-ends { font-size: 9px; color: rgba(255,255,255,.3); }
</style>
