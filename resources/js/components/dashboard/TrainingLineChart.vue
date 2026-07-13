<script setup>
// Long Toss & Weighted Ball charts — ported to match the app's player-report charts
// exactly (StatisticLongToss.js renderLongTossChart + StatisticWeight.js curve).
//   • longtoss    → "Distance by Throw": green line, yellow dashed EXPECTED (avg
//                   distance), dots colored by hops (≤1 green, ≤2 yellow, else red).
//   • weightedball → "Weighted Ball Velocity Curve": Avg (green) + Top (blue) +
//                   dashed Expected (5oz avg × weight multiplier) across 3–7 oz.
import { computed } from 'vue'

const props = defineProps({
  mode: { type: String, default: 'longtoss' }, // 'longtoss' | 'weightedball'
  points: { type: Array, default: () => [] },
})

const W = 440
const H = 200
const PAD = { l: 40, r: 16, t: 18, b: 30 }
const chartW = W - PAD.l - PAD.r
const chartH = H - PAD.t - PAD.b

const GREEN = '#37D67A'
const YELLOW = '#F7D774'
const BLUE = '#34A7FF'
const RED = '#EF4444'

// app hop ramp: null → white, ≤1 green, ≤2 yellow, else red
const hopColor = (hop) => {
  if (hop == null) return '#fff'
  const h = Number(hop)
  if (h <= 1) return GREEN
  if (h <= 2) return YELLOW
  return RED
}

const WB_WEIGHTS = [3, 4, 5, 6, 7]
const WB_MULT = { 3: 1.04, 4: 1.02, 5: 1, 6: 0.97, 7: 0.94 }

const model = computed(() => {
  const pts = Array.isArray(props.points) ? props.points : []
  if (props.mode === 'weightedball') {
    const byW = {}
    pts.forEach((p) => { byW[Number(p.weight)] = p })
    const avg = WB_WEIGHTS.map((w) => (byW[w]?.avg != null ? Number(byW[w].avg) : 0))
    const top = WB_WEIGHTS.map((w) => (byW[w]?.top != null ? Number(byW[w].top) : 0))
    const fiveAvg = byW[5]?.avg != null ? Number(byW[5].avg) : null
    const expected = WB_WEIGHTS.map((w) => (fiveAvg ? Math.round(fiveAvg * WB_MULT[w] * 10) / 10 : null))
    return {
      title: 'Weighted Ball Velocity Curve',
      xLabels: WB_WEIGHTS.map((w) => `${w}oz`),
      lines: [
        { label: 'Avg', color: GREEN, values: avg, width: 3.5 },
        { label: 'Top', color: BLUE, values: top, width: 3.5 },
      ],
      expected,           // per-x dashed line
      expectedFlat: null,
      dots: null,
      legend: [{ label: 'Avg', color: GREEN }, { label: 'Top', color: BLUE }, { label: 'Expected', color: YELLOW }],
    }
  }
  // long toss
  const dist = pts.map((p) => Number(p.distance) || 0)
  const hops = pts.map((p) => (p.hop != null ? Number(p.hop) : null))
  const expectedFlat = dist.length ? Math.round((dist.reduce((s, v) => s + v, 0) / dist.length) * 10) / 10 : null
  return {
    title: 'Distance by Throw',
    xLabels: pts.map((_, i) => String(i + 1)),
    lines: [{ label: 'Distance', color: GREEN, values: dist, width: 4 }],
    expected: null,
    expectedFlat,           // single horizontal dashed line
    dots: dist.map((d, i) => ({ value: d, hop: hops[i], above: expectedFlat == null || d >= expectedFlat })),
    legend: [{ label: 'Distance', color: GREEN }, { label: 'Expected', color: YELLOW }, { label: 'Dot color = hops', color: '#fff' }],
  }
})

const count = computed(() => model.value.xLabels.length)
const bounds = computed(() => {
  const m = model.value
  const series = [
    ...m.lines.flatMap((l) => l.values),
    ...(m.expected ? m.expected : []),
    ...(m.expectedFlat != null ? [m.expectedFlat] : []),
  ].filter((v) => v != null)
  if (!series.length) return { min: 0, max: 1 }
  const min = Math.max(0, Math.floor(Math.min(...series) - 10))
  const max = Math.ceil(Math.max(...series) + 10)
  return { min, max: max > min ? max : min + 1 }
})

const xAt = (i) => PAD.l + (count.value <= 1 ? chartW / 2 : (i / (count.value - 1)) * chartW)
const yAt = (v) => PAD.t + chartH - ((v - bounds.value.min) / Math.max(1, bounds.value.max - bounds.value.min)) * chartH
const linePath = (values) => values.map((v, i) => (v == null ? null : `${i === 0 ? 'M' : 'L'} ${xAt(i).toFixed(1)} ${yAt(v).toFixed(1)}`)).filter(Boolean).join(' ')
const expectedPath = computed(() => (model.value.expected ? linePath(model.value.expected) : null))

const hasData = computed(() => model.value.lines.some((l) => l.values.some((v) => v > 0)))
</script>

<template>
  <div class="tlc">
    <div class="tlc-title">{{ model.title }}</div>
    <svg :viewBox="`0 0 ${W} ${H}`" class="tlc-svg" preserveAspectRatio="xMidYMid meet">
      <!-- axes -->
      <line :x1="PAD.l" :y1="PAD.t" :x2="PAD.l" :y2="PAD.t + chartH" stroke="rgba(255,255,255,0.22)" stroke-width="1" />
      <line :x1="PAD.l" :y1="PAD.t + chartH" :x2="PAD.l + chartW" :y2="PAD.t + chartH" stroke="rgba(255,255,255,0.22)" stroke-width="1" />
      <text :x="6" :y="yAt(bounds.max) + 4" fill="rgba(255,255,255,0.55)" font-size="10" font-weight="700">{{ bounds.max }}</text>
      <text :x="6" :y="yAt(bounds.min) + 4" fill="rgba(255,255,255,0.55)" font-size="10" font-weight="700">{{ bounds.min }}</text>

      <!-- expected (dashed) -->
      <line v-if="model.expectedFlat != null" :x1="PAD.l" :y1="yAt(model.expectedFlat)" :x2="PAD.l + chartW" :y2="yAt(model.expectedFlat)" :stroke="'#F7D774'" stroke-width="3" stroke-dasharray="6,5" />
      <path v-if="expectedPath" :d="expectedPath" fill="none" stroke="#F7D774" stroke-width="3" stroke-dasharray="6,5" stroke-linejoin="round" />

      <!-- data lines -->
      <path v-for="l in model.lines" :key="l.label" :d="linePath(l.values)" fill="none" :stroke="l.color" :stroke-width="l.width" stroke-linejoin="round" stroke-linecap="round" />

      <!-- dots -->
      <template v-if="model.dots">
        <circle v-for="(d, i) in model.dots" :key="`d${i}`" :cx="xAt(i)" :cy="yAt(d.value)" r="5" :fill="hopColor(d.hop)" :stroke="d.above ? '#fff' : '#EF4444'" stroke-width="1.5" />
      </template>
      <template v-else>
        <g v-for="l in model.lines" :key="`dots-${l.label}`">
          <circle v-for="(v, i) in l.values" :key="`${l.label}-${i}`" :cx="xAt(i)" :cy="yAt(v)" r="4" :fill="l.color" stroke="#0b1322" stroke-width="1.5" />
        </g>
      </template>

      <!-- x labels -->
      <text v-for="(lab, i) in model.xLabels" :key="`x${i}`" :x="xAt(i)" :y="H - PAD.b + 15" fill="rgba(255,255,255,0.5)" font-size="10" text-anchor="middle">{{ lab }}</text>
    </svg>

    <div v-if="!hasData" class="tlc-empty">No throws recorded yet.</div>
    <div class="tlc-legend">
      <span v-for="l in model.legend" :key="l.label" class="tlc-leg" :style="{ color: l.color }">{{ l.label }}</span>
    </div>
  </div>
</template>

<style scoped>
.tlc { width: 100%; }
.tlc-title { font-size: 13px; font-weight: 800; color: #fff; margin-bottom: 6px; }
.tlc-svg { display: block; width: 100%; height: auto; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; }
.tlc-empty { font-size: 12px; color: rgba(255,255,255,0.4); margin-top: 6px; }
.tlc-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-top: 8px; }
.tlc-leg { font-size: 11px; font-weight: 800; letter-spacing: .4px; text-transform: uppercase; }
</style>
