<script setup>
// Bullpen strike-zone heat / velocity map — ported from the app's
// ContactHeatmapOverlay60 + VelocityGridOverlay60. Pitches carry a 60×60 grid
// location (pitch_mark); heatmap = Gaussian-blurred density on a thermal ramp,
// velo grid = average mph per cell. Drawn on a dark zone (not a catcher photo).
import { computed } from 'vue'

const props = defineProps({
  pitches: { type: Array, default: () => [] }, // { pitch_mark, velocity }
  mode: { type: String, default: 'heatmap' },  // 'heatmap' | 'grid'
})

const BASE = 60
const VB = 300 // svg viewBox size

const cellOf = (mark) => {
  const m = Number(mark)
  if (!m || m <= 0) return null
  return { row: ((m - 1) % BASE) + 1, col: Math.floor((m - 1) / BASE) + 1 }
}

// ── heatmap (density → jet ramp) ──
const heat = computed(() => {
  if (props.mode !== 'heatmap') return { cells: [], min: 0, max: 0 }
  const counts = new Array(BASE * BASE).fill(0)
  ;(props.pitches || []).forEach((p) => {
    const c = cellOf(p.pitch_mark)
    if (c) counts[(c.row - 1) * BASE + (c.col - 1)] += 1
  })
  const radius = 5; const sigma = 2.2; const kernel = []
  for (let y = -radius; y <= radius; y++) for (let x = -radius; x <= radius; x++) kernel.push({ x, y, w: Math.exp(-(x * x + y * y) / (2 * sigma * sigma)) })
  const blurred = new Array(BASE * BASE).fill(0)
  for (let r = 0; r < BASE; r++) {
    for (let c = 0; c < BASE; c++) {
      let s = 0
      kernel.forEach(({ x, y, w }) => { const rr = r + y; const cc = c + x; if (rr < 0 || rr >= BASE || cc < 0 || cc >= BASE) return; const v = counts[rr * BASE + cc]; if (v) s += v * w })
      blurred[r * BASE + c] = s
    }
  }
  let min = null; let max = 0
  blurred.forEach((v) => { if (v > 0) { min = min == null ? v : Math.min(min, v); max = Math.max(max, v) } })
  const G = 28; const bucket = BASE / G; const out = []
  for (let r = 1; r <= G; r++) {
    for (let c = 1; c <= G; c++) {
      const rs = Math.floor((r - 1) * bucket); const re = Math.floor(r * bucket) - 1
      const cs = Math.floor((c - 1) * bucket); const ce = Math.floor(c * bucket) - 1
      let acc = 0; let n = 0
      for (let rr = rs; rr <= re; rr++) for (let cc = cs; cc <= ce; cc++) { acc += blurred[rr * BASE + cc] || 0; n++ }
      out.push({ row: r, col: c, value: n ? acc / n : 0 })
    }
  }
  return { cells: out, min: min ?? 0, max: max ?? 0, G }
})

const jet = (value, min, max) => {
  if (!value || max <= 0) return null
  const tRaw = max > min ? (value - min) / (max - min) : 0
  const t = Math.pow(Math.max(0, Math.min(1, tRaw)), 0.8)
  const stops = [[0, 0, 0, 200], [0.18, 0, 90, 255], [0.38, 0, 220, 235], [0.55, 0, 225, 60], [0.72, 225, 230, 0], [0.86, 255, 130, 0], [1, 235, 0, 0]]
  let a = stops[0]; let b = stops[stops.length - 1]
  for (let i = 0; i < stops.length - 1; i++) if (t >= stops[i][0] && t <= stops[i + 1][0]) { a = stops[i]; b = stops[i + 1]; break }
  const span = b[0] - a[0] || 1; const tt = (t - a[0]) / span
  const r = Math.round(a[1] + (b[1] - a[1]) * tt); const g = Math.round(a[2] + (b[2] - a[2]) * tt); const bl = Math.round(a[3] + (b[3] - a[3]) * tt)
  return `rgba(${r},${g},${bl},${(0.12 + 0.88 * t).toFixed(3)})`
}

const heatBlobs = computed(() => {
  const { cells, min, max, G } = heat.value
  if (!cells.length) return []
  const cw = VB / G
  return cells.map((p) => ({ cx: (p.col - 0.5) * cw, cy: (p.row - 0.5) * cw, r: cw * 0.95, fill: jet(p.value, min, max) })).filter((b) => b.fill)
})

// ── velocity grid (avg mph per cell → red intensity) ──
const grid = computed(() => {
  if (props.mode !== 'grid') return { cells: [], min: 0, max: 0 }
  const G = 5; const bucket = BASE / G; const agg = new Map()
  ;(props.pitches || []).forEach((p) => {
    const c = cellOf(p.pitch_mark); if (!c) return
    const mph = Number(p.velocity ?? p.miles_per_hour) || 0; if (!mph) return
    const row = Math.min(G, Math.ceil(c.row / bucket)); const col = Math.min(G, Math.ceil(c.col / bucket))
    const key = `${row}-${col}`; const cur = agg.get(key) || { sum: 0, count: 0, row, col }
    cur.sum += mph; cur.count += 1; agg.set(key, cur)
  })
  let min = null; let max = null; const out = []
  agg.forEach((v) => { const avg = v.count ? v.sum / v.count : 0; if (avg) { min = min == null ? avg : Math.min(min, avg); max = max == null ? avg : Math.max(max, avg) } out.push({ row: v.row, col: v.col, avg }) })
  return { cells: out, min: min ?? 0, max: max ?? 0, G }
})

const gridCells = computed(() => {
  const { cells, min, max, G } = grid.value
  if (!cells.length) return []
  const cw = VB / G
  const redA = (avg) => (!avg || max <= 0) ? 'rgba(255,0,0,0.06)' : (min === max ? 'rgba(255,0,0,0.45)' : `rgba(255,0,0,${(0.12 + ((avg - min) / (max - min)) * 0.68).toFixed(3)})`)
  return cells.map((c) => ({ x: (c.col - 1) * cw, y: (c.row - 1) * cw, w: cw, cx: (c.col - 0.5) * cw, cy: (c.row - 0.5) * cw, fill: redA(c.avg), label: Math.round(c.avg) }))
})

// centered 3×3 strike-zone box
const zone = (() => { const bw = VB * 0.34; const bh = VB * 0.44; const l = (VB - bw) / 2; const t = (VB - bh) / 2; return { l, t, bw, bh, r: l + bw, b: t + bh } })()
</script>

<template>
  <div class="bzm">
    <svg :viewBox="`0 0 ${VB} ${VB}`" class="bzm-svg" preserveAspectRatio="xMidYMid meet">
      <defs><filter id="bzm-blur" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="4.5" /></filter></defs>
      <rect :width="VB" :height="VB" fill="#05070d" />

      <!-- heatmap blobs -->
      <g v-if="mode === 'heatmap'" filter="url(#bzm-blur)">
        <circle v-for="(b, i) in heatBlobs" :key="i" :cx="b.cx" :cy="b.cy" :r="b.r" :fill="b.fill" />
      </g>

      <!-- velocity grid -->
      <g v-else>
        <g v-for="(c, i) in gridCells" :key="i">
          <rect :x="c.x" :y="c.y" :width="c.w" :height="c.w" :fill="c.fill" stroke="rgba(255,255,255,0.06)" stroke-width="0.5" />
          <text v-if="c.label" :x="c.cx" :y="c.cy" fill="#fff" font-size="15" font-weight="800" text-anchor="middle" dominant-baseline="central" style="paint-order: stroke; stroke: rgba(0,0,0,0.5); stroke-width: 2px;">{{ c.label }}</text>
        </g>
      </g>

      <!-- strike-zone box (3×3) -->
      <g pointer-events="none">
        <rect :x="zone.l" :y="zone.t" :width="zone.bw" :height="zone.bh" fill="none" stroke="rgba(255,255,255,0.55)" stroke-width="1.5" />
        <line :x1="zone.l + zone.bw / 3" :y1="zone.t" :x2="zone.l + zone.bw / 3" :y2="zone.b" stroke="rgba(255,255,255,0.22)" stroke-width="0.8" />
        <line :x1="zone.l + zone.bw * 2 / 3" :y1="zone.t" :x2="zone.l + zone.bw * 2 / 3" :y2="zone.b" stroke="rgba(255,255,255,0.22)" stroke-width="0.8" />
        <line :x1="zone.l" :y1="zone.t + zone.bh / 3" :x2="zone.r" :y2="zone.t + zone.bh / 3" stroke="rgba(255,255,255,0.22)" stroke-width="0.8" />
        <line :x1="zone.l" :y1="zone.t + zone.bh * 2 / 3" :x2="zone.r" :y2="zone.t + zone.bh * 2 / 3" stroke="rgba(255,255,255,0.22)" stroke-width="0.8" />
      </g>
    </svg>
  </div>
</template>

<style scoped>
.bzm { width: 100%; }
.bzm-svg { display: block; width: 100%; height: auto; max-height: 420px; }
</style>
