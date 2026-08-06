<script setup>
/**
 * StatsStrikeZonePlot.vue
 *
 * Vue/SVG port of the app's StrikeZonePlot (screens/../StrikeZonePlot.js). Plots
 * each pitch as a numbered dot on a 3×3 strike zone (+ home plate), placed by its
 * `pitch_mark` (60×60 grid). Dot color is caller-supplied via `colorOf`.
 */
import { computed } from 'vue'
import { markToColRow } from '@/features/player-home/lib/constants.js'

const props = defineProps({
  balls: { type: Array, default: () => [] },
  colorOf: { type: Function, default: () => '#3498DB' },
  markKey: { type: String, default: 'pitch_mark' },
})

const W = 300
const H = 285 // matches the app's 200:190 aspect
const MIN_ROW = 15, MAX_ROW = 45, MIN_COL = 16, MAX_COL = 45

const decodeMark = (pm) => {
  const n = parseInt(pm, 10)
  if (!n || n < 1 || n > 3600) return null
  return markToColRow(n)
}

// Strike-zone box geometry in SVG units.
const box = (() => {
  const l = ((MIN_COL - 1) / 59) * W
  const r = ((MAX_COL - 1) / 59) * W
  const t = ((MIN_ROW - 1) / 59) * H
  const b = ((MAX_ROW - 1) / 59) * H
  const w = r - l, h = b - t
  const cx = l + w / 2
  const pH = H * 0.07, pW = w * 0.5
  const py = b + pH * 0.5
  const plate = [
    `M${cx},${py - pH * 0.5}`,
    `L${cx + pW / 2},${py - pH * 0.2}`,
    `L${cx + pW / 2},${py + pH * 0.2}`,
    `L${cx},${py + pH * 0.5}`,
    `L${cx - pW / 2},${py + pH * 0.2}`,
    `L${cx - pW / 2},${py - pH * 0.2} Z`,
  ].join(' ')
  return { l, r, t, b, w, h, plate }
})()

const dots = computed(() => {
  const out = []
  ;(Array.isArray(props.balls) ? props.balls : []).forEach((ball, i) => {
    const p = decodeMark(ball[props.markKey] ?? ball.pitch_mark ?? ball.pitch_location)
    if (!p) return
    out.push({
      key: i,
      n: i + 1,
      cx: ((p.col - 1) / 59) * W,
      cy: ((p.row - 1) / 59) * H,
      color: props.colorOf(ball) || '#3498DB',
    })
  })
  return out
})
const R = 13
</script>

<template>
  <div class="szp">
    <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="xMidYMid meet" class="szp-svg">
      <!-- strike zone box + 3×3 grid -->
      <rect :x="box.l" :y="box.t" :width="box.w" :height="box.h" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="1.5" />
      <line :x1="box.l + box.w / 3" :y1="box.t" :x2="box.l + box.w / 3" :y2="box.b" stroke="rgba(255,255,255,0.2)" stroke-width="0.8" />
      <line :x1="box.l + (box.w * 2) / 3" :y1="box.t" :x2="box.l + (box.w * 2) / 3" :y2="box.b" stroke="rgba(255,255,255,0.2)" stroke-width="0.8" />
      <line :x1="box.l" :y1="box.t + box.h / 3" :x2="box.r" :y2="box.t + box.h / 3" stroke="rgba(255,255,255,0.2)" stroke-width="0.8" />
      <line :x1="box.l" :y1="box.t + (box.h * 2) / 3" :x2="box.r" :y2="box.t + (box.h * 2) / 3" stroke="rgba(255,255,255,0.2)" stroke-width="0.8" />
      <path :d="box.plate" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="1" />

      <!-- pitch dots -->
      <g v-for="d in dots" :key="d.key">
        <circle :cx="d.cx" :cy="d.cy" :r="R" :fill="d.color" stroke="#fff" stroke-width="1.5" />
        <text :x="d.cx" :y="d.cy" fill="#fff" :font-size="R * 0.85" font-weight="800" text-anchor="middle" dominant-baseline="central">{{ d.n }}</text>
      </g>
    </svg>
  </div>
</template>

<style scoped>
.szp { width: 100%; display: flex; align-items: center; justify-content: center; }
.szp-svg { display: block; width: 100%; height: auto; max-height: 460px; }
</style>
