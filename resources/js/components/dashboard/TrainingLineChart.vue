<script setup>
// Line chart for the Long Toss and Weighted Ball Performance Review panels — ported
// from the app's player report charts.
//   • longtoss  → "Distance by Throw": green distance line, dots colored by hops.
//   • weightedball → "Velocity Curve": avg (green) + top (blue) velocity per weight.
import { computed } from 'vue'

const props = defineProps({
  mode: { type: String, default: 'longtoss' }, // 'longtoss' | 'weightedball'
  points: { type: Array, default: () => [] },
})

const W = 440
const H = 240
const PAD = { l: 40, r: 16, t: 18, b: 40 }

const series = computed(() => {
  const pts = Array.isArray(props.points) ? props.points : []
  if (props.mode === 'weightedball') {
    return {
      title: 'Weighted Ball Velocity Curve',
      xLabels: pts.map((p) => `${p.weight}oz`),
      lines: [
        { key: 'avg', label: 'Avg', color: '#37D67A', values: pts.map((p) => p.avg) },
        { key: 'top', label: 'Top', color: '#34A7FF', values: pts.map((p) => p.top) },
      ],
      dots: null,
      unit: 'mph',
    }
  }
  return {
    title: 'Distance by Throw',
    xLabels: pts.map((_, i) => String(i + 1)),
    lines: [{ key: 'distance', label: 'Distance', color: '#37D67A', values: pts.map((p) => p.distance) }],
    dots: pts.map((p) => ({ value: p.distance, hop: p.hop })),
    unit: 'ft',
  }
})

const count = computed(() => series.value.xLabels.length)
const bounds = computed(() => {
  const all = series.value.lines.flatMap((l) => l.values).filter((v) => v != null)
  if (!all.length) return { min: 0, max: 1 }
  const min = Math.min(...all)
  const max = Math.max(...all)
  const pad = (max - min) * 0.18 || 5
  return { min: Math.max(0, Math.round(min - pad)), max: Math.round(max + pad) }
})

const xAt = (i) => PAD.l + (count.value <= 1 ? 0.5 : i / (count.value - 1)) * (W - PAD.l - PAD.r)
const yAt = (v) => {
  const { min, max } = bounds.value
  const t = max > min ? (v - min) / (max - min) : 0.5
  return H - PAD.b - t * (H - PAD.t - PAD.b)
}
const linePath = (values) => values
  .map((v, i) => (v == null ? null : `${i === 0 ? 'M' : 'L'} ${xAt(i).toFixed(1)} ${yAt(v).toFixed(1)}`))
  .filter(Boolean).join(' ')

const hopColor = (hop) => {
  const h = Number(hop) || 0
  if (h < 0.5) return '#37D67A'
  if (h < 1.5) return '#F7D774'
  if (h < 2.5) return '#F59E0B'
  return '#EF4444'
}

// Show every label when few points; thin out when many (long toss can be 20+).
const shownXLabels = computed(() => {
  const labels = series.value.xLabels
  const step = Math.ceil(labels.length / 10) || 1
  return labels.map((label, i) => ({ label, i, show: i % step === 0 || i === labels.length - 1 }))
})
</script>

<template>
  <div class="tlc">
    <div class="tlc-title">{{ series.title }}</div>
    <svg :viewBox="`0 0 ${W} ${H}`" class="tlc-svg" preserveAspectRatio="xMidYMid meet">
      <!-- y grid + labels (min/max) -->
      <line :x1="PAD.l" :y1="PAD.t" :x2="PAD.l" :y2="H - PAD.b" stroke="rgba(255,255,255,0.12)" stroke-width="1" />
      <line :x1="PAD.l" :y1="H - PAD.b" :x2="W - PAD.r" :y2="H - PAD.b" stroke="rgba(255,255,255,0.12)" stroke-width="1" />
      <text :x="PAD.l - 6" :y="PAD.t + 4" fill="rgba(255,255,255,0.4)" font-size="10" text-anchor="end">{{ bounds.max }}</text>
      <text :x="PAD.l - 6" :y="H - PAD.b" fill="rgba(255,255,255,0.4)" font-size="10" text-anchor="end">{{ bounds.min }}</text>

      <!-- lines -->
      <path v-for="l in series.lines" :key="l.key" :d="linePath(l.values)" fill="none" :stroke="l.color" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />

      <!-- dots -->
      <template v-if="series.dots">
        <circle v-for="(d, i) in series.dots" :key="`d${i}`" :cx="xAt(i)" :cy="yAt(d.value)" r="4.5" :fill="hopColor(d.hop)" stroke="#0b1322" stroke-width="1.5" />
      </template>
      <template v-else>
        <g v-for="l in series.lines" :key="`dots-${l.key}`">
          <circle v-for="(v, i) in l.values" :key="`${l.key}-${i}`" :cx="xAt(i)" :cy="yAt(v)" r="4" :fill="l.color" stroke="#0b1322" stroke-width="1.5" />
        </g>
      </template>

      <!-- x labels -->
      <text v-for="x in shownXLabels" v-show="x.show" :key="`x${x.i}`" :x="xAt(x.i)" :y="H - PAD.b + 16" fill="rgba(255,255,255,0.45)" font-size="10" text-anchor="middle">{{ x.label }}</text>
    </svg>

    <div class="tlc-legend">
      <span v-for="l in series.lines" :key="l.key" class="tlc-leg"><span class="tlc-dot" :style="{ background: l.color }"></span>{{ l.label }}</span>
      <span v-if="series.dots" class="tlc-leg tlc-leg--hint">Dot color = hops</span>
    </div>
  </div>
</template>

<style scoped>
.tlc { width: 100%; }
.tlc-title { font-size: 12px; font-weight: 800; color: #fff; margin-bottom: 6px; }
.tlc-svg { display: block; width: 100%; height: auto; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; }
.tlc-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-top: 8px; }
.tlc-leg { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; letter-spacing: .3px; text-transform: uppercase; color: rgba(255,255,255,0.6); }
.tlc-dot { width: 10px; height: 10px; border-radius: 3px; }
.tlc-leg--hint { color: rgba(255,255,255,0.35); font-weight: 700; }
</style>
