<script setup>
import { computed } from 'vue'

const props = defineProps({
  points: { type: Array, default: () => [] },
  pitchTypes: { type: Array, default: () => [] },
})

const colors = { FB: '#31c9ff', '2FB': '#16d39a', CV: '#b985ff', SL: '#ff5b7d', KN: '#ffd166' }
const color = type => colors[type] || '#d5dde9'
const width = 680
const height = 390
const plot = { left: 62, right: 22, top: 22, bottom: 56 }
const range = computed(() => {
  const xs = props.points.map(point => Number(point.horizontal_break)).filter(Number.isFinite)
  const ys = props.points.map(point => Number(point.vertical_break)).filter(Number.isFinite)
  const xMin = Math.floor(Math.min(0, ...xs) / 5) * 5 - 2
  const xMax = Math.ceil(Math.max(0, ...xs) / 5) * 5 + 2
  const yMin = Math.floor(Math.min(0, ...ys) / 5) * 5 - 2
  const yMax = Math.ceil(Math.max(0, ...ys) / 5) * 5 + 2
  return { xMin, xMax, yMin, yMax, xSpan: Math.max(1, xMax - xMin), ySpan: Math.max(1, yMax - yMin) }
})
const x = value => plot.left + ((Number(value) - range.value.xMin) / range.value.xSpan) * (width - plot.left - plot.right)
const y = value => plot.top + ((range.value.yMax - Number(value)) / range.value.ySpan) * (height - plot.top - plot.bottom)
const ticks = (min, max) => Array.from({ length: 5 }, (_, index) => Math.round((min + ((max - min) * index) / 4) * 10) / 10)
const xTicks = computed(() => ticks(range.value.xMin, range.value.xMax))
const yTicks = computed(() => ticks(range.value.yMin, range.value.yMax))
const centroids = computed(() => props.pitchTypes
  .filter(row => Number.isFinite(Number(row.centroid?.horizontal_break)) && Number.isFinite(Number(row.centroid?.vertical_break)))
  .map(row => ({ type: row.pitch_type, x: row.centroid.horizontal_break, y: row.centroid.vertical_break })))
</script>

<template>
  <section class="movement-chart report-section" aria-labelledby="movement-title">
    <div class="section-heading">
      <div><span>Pitch movement profile</span><h2 id="movement-title">Horizontal vs. vertical break</h2></div>
      <p>Every outlined dot is one pitch. Larger labeled markers show pitch-type averages.</p>
    </div>
    <div v-if="points.length" class="chart-wrap">
      <svg :viewBox="`0 0 ${width} ${height}`" role="img" aria-labelledby="movement-title movement-description">
        <desc id="movement-description">Scatterplot of horizontal and vertical break in inches, grouped by text-labeled pitch type.</desc>
        <g v-for="tick in xTicks" :key="`x-${tick}`">
          <line :x1="x(tick)" :x2="x(tick)" :y1="plot.top" :y2="height-plot.bottom" class="grid" />
          <text :x="x(tick)" :y="height-31" text-anchor="middle" class="tick">{{ tick }}</text>
        </g>
        <g v-for="tick in yTicks" :key="`y-${tick}`">
          <line :x1="plot.left" :x2="width-plot.right" :y1="y(tick)" :y2="y(tick)" class="grid" />
          <text :x="49" :y="y(tick)+4" text-anchor="end" class="tick">{{ tick }}</text>
        </g>
        <line v-if="range.xMin <= 0 && range.xMax >= 0" :x1="x(0)" :x2="x(0)" :y1="plot.top" :y2="height-plot.bottom" class="zero" />
        <line v-if="range.yMin <= 0 && range.yMax >= 0" :x1="plot.left" :x2="width-plot.right" :y1="y(0)" :y2="y(0)" class="zero" />
        <g v-for="point in points" :key="point.event_id" :aria-label="`${point.pitch_type} pitch ${point.pitch_number}: ${point.horizontal_break} horizontal, ${point.vertical_break} vertical`">
          <circle :cx="x(point.horizontal_break)" :cy="y(point.vertical_break)" r="5" :fill="color(point.pitch_type)" fill-opacity=".68" stroke="#07101e" stroke-width="1.5"><title>{{ point.pitch_type }} #{{ point.pitch_number }} · H {{ point.horizontal_break }} in · V {{ point.vertical_break }} in</title></circle>
        </g>
        <g v-for="center in centroids" :key="`center-${center.type}`">
          <circle :cx="x(center.x)" :cy="y(center.y)" r="13" :fill="color(center.type)" stroke="#fff" stroke-width="3" />
          <text :x="x(center.x)" :y="y(center.y)+3" text-anchor="middle" class="centroid-label">{{ center.type }}</text>
        </g>
        <text :x="(plot.left+width-plot.right)/2" :y="height-7" text-anchor="middle" class="axis-label">Horizontal Break (inches)</text>
        <text :transform="`translate(15 ${(plot.top+height-plot.bottom)/2}) rotate(-90)`" text-anchor="middle" class="axis-label">Vertical Break (inches)</text>
      </svg>
    </div>
    <p v-else class="empty">No pitches contain both horizontal and vertical break.</p>
    <div class="legend" aria-label="Pitch type legend">
      <span v-for="row in pitchTypes" :key="row.pitch_type"><i :style="{ background: color(row.pitch_type) }"></i><b>{{ row.pitch_type }}</b> {{ row.display_name }}</span>
    </div>
    <ul class="sr-only">
      <li v-for="row in pitchTypes" :key="`summary-${row.pitch_type}`">{{ row.display_name }} average movement: {{ row.average_horizontal_break }} inches horizontal and {{ row.average_vertical_break }} inches vertical.</li>
    </ul>
  </section>
</template>

<style scoped>
.movement-chart{padding:24px;border:1px solid rgba(255,255,255,.11);border-radius:18px;background:rgba(10,19,37,.88)}.section-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:16px}.section-heading span{color:#2ed5ce;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.section-heading h2{margin-top:4px;color:#fff;font-size:22px;font-weight:900}.section-heading p{max-width:340px;color:#8493a7;font-size:11px;line-height:1.5;text-align:right}.chart-wrap{overflow:hidden;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:linear-gradient(180deg,rgba(5,13,27,.92),rgba(11,24,41,.92))}.chart-wrap svg{display:block;width:100%;height:auto;min-height:320px}.grid{stroke:rgba(255,255,255,.08);stroke-width:1}.zero{stroke:rgba(255,255,255,.45);stroke-width:1.5;stroke-dasharray:6 5}.tick{fill:#7f90a8;font-size:11px}.axis-label{fill:#aab7c8;font-size:12px;font-weight:800}.centroid-label{fill:#07101e;font-size:8px;font-weight:1000}.legend{display:flex;flex-wrap:wrap;gap:10px 18px;margin-top:14px}.legend span{display:flex;align-items:center;gap:6px;color:#9dacbe;font-size:10px}.legend i{width:10px;height:10px;border:2px solid #fff;border-radius:50%}.legend b{color:#fff}.empty{padding:50px;color:#8493a7;text-align:center}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}@media(max-width:700px){.movement-chart{padding:15px}.section-heading{align-items:flex-start;flex-direction:column}.section-heading p{text-align:left}.chart-wrap svg{min-height:260px}}
</style>
