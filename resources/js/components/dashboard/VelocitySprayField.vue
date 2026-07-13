<script setup>
// Batting velocity spray field — ported from the app's CageFieldPlot (heatmap mode).
// Splits fair territory into spray sectors × depth bands, averages exit velocity in
// each, and colors blue(slow)→red(fast) with the mph labeled, over a drawn field
// with the fence distance arcs. Filter by trajectory (All / GB / PF / FB / LD).
import { ref, computed } from 'vue'
import { configFromLevel, createFieldMapper, fenceAt, baseCoords } from '@/utils/ballFieldGeometry'
import { fieldMarkToBalls } from '@/utils/fieldMark'

const props = defineProps({
  balls: { type: Array, default: () => [] }, // contact_spray balls: { point, velocity, trajectory }
  level: { type: String, default: 'hs' },
})

const W = 460
const H = Math.round(W / 1.5) // FIELD_ASPECT
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

// Blue(low) → cyan → green → yellow → orange → red(high) heat over the shown range.
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

const filteredBalls = computed(() => {
  const all = Array.isArray(props.balls) ? props.balls : []
  if (filter.value === 'ALL') return all
  return all.filter((b) => String(b.trajectory ?? b.type_of_hit ?? '').toUpperCase() === filter.value)
})
const swingCount = computed(() => filteredBalls.value.length)

const arcPoly = (a1, a2, f1, f2) => {
  const steps = 5
  const pts = []
  for (let i = 0; i <= steps; i++) { const a = a1 + ((a2 - a1) * i) / steps; const p = mapper.mapBall(f1 * fenceAt(a, fences), a); pts.push(`${p.px},${p.py}`) }
  for (let i = steps; i >= 0; i--) { const a = a1 + ((a2 - a1) * i) / steps; const p = mapper.mapBall(f2 * fenceAt(a, fences), a); pts.push(`${p.px},${p.py}`) }
  return pts.join(' ')
}

const zones = computed(() => {
  const balls = fieldMarkToBalls(filteredBalls.value, cfg)
  const out = []
  for (let si = 0; si < SPRAY_EDGES.length - 1; si++) {
    const a1 = SPRAY_EDGES[si]
    const a2 = SPRAY_EDGES[si + 1]
    for (const [f1, f2] of DEPTH_BANDS) {
      const zb = balls.filter((b) => {
        const s = b.spray_angle
        if (!(s >= a1 && s < a2)) return false
        const frac = (b.distance_travel || 0) / (fenceAt(s, fences) || 300)
        return frac >= f1 && (frac < f2 || f2 >= 1)
      })
      const evs = zb.map((b) => b.velocity).filter((v) => v > 0)
      const avg = evs.length ? evs.reduce((a, v) => a + v, 0) / evs.length : null
      out.push({ a1, a2, f1, f2, avg, count: zb.length })
    }
  }
  const avgs = out.map((z) => z.avg).filter((v) => v != null)
  const min = avgs.length ? Math.min(...avgs) : 0
  const max = avgs.length ? Math.max(...avgs) : 1
  return out.map((z) => {
    const midA = (z.a1 + z.a2) / 2
    const midF = (z.f1 + z.f2) / 2
    const c = mapper.mapBall(midF * fenceAt(midA, fences), midA)
    return {
      key: `${z.a1}_${z.f1}`,
      points: arcPoly(z.a1, z.a2, z.f1, z.f2),
      cx: c.px, cy: c.py, avg: z.avg,
      color: z.avg != null ? velColor(z.avg, min, max) : 'rgba(255,255,255,0.035)',
    }
  })
})

// ── static field geometry (fair wedge, fence arc, foul lines, diamond, labels) ──
const fairWedge = (() => {
  const home = mapper.home
  const pts = [`M ${home.px} ${home.py}`]
  for (let a = -45; a <= 45; a += 2) { const p = mapper.mapBall(fenceAt(a, fences), a); pts.push(`L ${p.px} ${p.py}`) }
  pts.push('Z')
  return pts.join(' ')
})()
const fenceArc = (() => {
  const pts = []
  for (let a = -45; a <= 45; a += 2) { const p = mapper.mapBall(fenceAt(a, fences), a); pts.push(`${a === -45 ? 'M' : 'L'} ${p.px} ${p.py}`) }
  return pts.join(' ')
})()
const foulLines = (() => {
  const home = mapper.home
  const l = mapper.mapBall(fenceAt(-45, fences), -45)
  const r = mapper.mapBall(fenceAt(45, fences), 45)
  return [{ x1: home.px, y1: home.py, x2: l.px, y2: l.py }, { x1: home.px, y1: home.py, x2: r.px, y2: r.py }]
})()
const diamond = (() => {
  const bc = baseCoords(cfg.basePathFt)
  const p = (a) => { const m = mapper.mapFeet(a[0], a[1]); return `${m.px},${m.py}` }
  return `${p(bc.home)} ${p(bc.first)} ${p(bc.second)} ${p(bc.third)}`
})()
const distanceLabels = (() => {
  const rows = [
    { d: fences.lineL, a: -45 }, { d: fences.gapL, a: -22.5 }, { d: fences.center, a: 0 },
    { d: fences.gapR, a: 22.5 }, { d: fences.lineR, a: 45 },
  ]
  return rows.map(({ d, a }) => { const pt = mapper.mapBall(d, a); return { x: pt.px, y: pt.py, label: d } })
})()
</script>

<template>
  <div class="vsf">
    <div class="vsf-filters">
      <button
        v-for="f in FILTERS" :key="f.key"
        class="vsf-chip" :class="{ 'vsf-chip--on': filter === f.key }"
        @click="filter = f.key"
      >{{ f.label }}</button>
      <span class="vsf-count">{{ swingCount }} swing{{ swingCount === 1 ? '' : 's' }}</span>
    </div>

    <div class="vsf-fieldwrap">
      <svg :viewBox="`0 0 ${W} ${H}`" class="vsf-svg" preserveAspectRatio="xMidYMid meet">
        <!-- fair territory grass -->
        <path :d="fairWedge" fill="#153a1f" stroke="rgba(255,255,255,0.10)" stroke-width="1" />
        <!-- velocity heat zones -->
        <polygon
          v-for="z in zones" :key="z.key"
          :points="z.points" :fill="z.color"
          stroke="rgba(255,255,255,0.22)" stroke-width="0.8"
        />
        <!-- fence + foul lines -->
        <path :d="fenceArc" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" />
        <line v-for="(l, i) in foulLines" :key="`f${i}`" :x1="l.x1" :y1="l.y1" :x2="l.x2" :y2="l.y2" stroke="rgba(255,255,255,0.5)" stroke-width="1.2" />
        <!-- infield diamond -->
        <polygon :points="diamond" fill="rgba(180,120,60,0.28)" stroke="rgba(255,255,255,0.4)" stroke-width="1" />
        <!-- zone velocity labels -->
        <text
          v-for="z in zones" :key="`t${z.key}`"
          v-show="z.avg != null"
          :x="z.cx" :y="z.cy" fill="#fff" font-size="13" font-weight="800"
          text-anchor="middle" dominant-baseline="central"
          style="paint-order: stroke; stroke: rgba(0,0,0,0.5); stroke-width: 2px;"
        >{{ z.avg != null ? Math.round(z.avg) : '' }}</text>
        <!-- fence distance labels -->
        <g v-for="(d, i) in distanceLabels" :key="`d${i}`">
          <rect :x="d.x - 15" :y="d.y - 8" width="30" height="15" rx="3" fill="#7f1d1d" stroke="#ef4444" stroke-width="0.8" />
          <text :x="d.x" :y="d.y" fill="#fff" font-size="9" font-weight="800" text-anchor="middle" dominant-baseline="central">{{ d.label }}</text>
        </g>
      </svg>

      <div v-if="swingCount === 0" class="vsf-empty">No batting swings yet</div>
    </div>

    <!-- legend -->
    <div class="vsf-legend">
      <span class="vsf-legend-label">Exit velocity</span>
      <span class="vsf-legend-bar"></span>
      <span class="vsf-legend-ends">low → high mph</span>
    </div>
  </div>
</template>

<style scoped>
.vsf { width: 100%; }
.vsf-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-bottom: 10px; }
.vsf-chip {
  font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 8px; cursor: pointer;
  color: rgba(255,255,255,.55); background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1);
  transition: .15s;
}
.vsf-chip:hover { color: #fff; border-color: rgba(255,255,255,.25); }
.vsf-chip--on { color: #fff; background: #C00000; border-color: #C00000; }
.vsf-count { margin-left: auto; font-size: 10px; font-weight: 700; color: rgba(255,255,255,.35); }
.vsf-fieldwrap {
  position: relative; border-radius: 12px; overflow: hidden;
  background: radial-gradient(120% 80% at 50% 100%, #0e2033 0%, #070e18 70%);
  border: 1px solid rgba(255,255,255,.08);
}
.vsf-svg { display: block; width: 100%; height: auto; }
.vsf-empty {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  color: rgba(255,255,255,.4); font-size: 13px; pointer-events: none;
}
.vsf-legend { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
.vsf-legend-label { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,.35); }
.vsf-legend-bar {
  flex: 1; height: 6px; border-radius: 99px; max-width: 180px;
  background: linear-gradient(90deg, #3b82f6, #22c55e, #eab308, #f97316, #ef4444);
}
.vsf-legend-ends { font-size: 9px; color: rgba(255,255,255,.3); }
</style>
