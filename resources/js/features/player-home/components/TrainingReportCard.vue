<script setup>
import { CHART_COLORS } from '../lib/constants.js'
import {
  fmtReport,
  lineChartPath,
  lineChartRows,
  lineChartSeries,
  lineChartX,
  lineChartY,
} from '../lib/trainingReports.js'

defineProps({
  report: { type: Object, required: true },
})

const gridStroke = 'rgba(255,255,255,0.12)'
const labelFill = 'rgba(255,255,255,0.58)'
// Point outline matches the raised card background so dots read as "punched".
const pointStroke = '#071725'
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-white/5 p-4">
    <div class="mb-3">
      <p class="text-xs font-black uppercase tracking-widest text-white/70">{{ report.title }}</p>
      <p class="mt-1 text-[11px] font-bold text-white/40">{{ report.subtitle }}</p>
    </div>
    <div
      v-if="lineChartRows(report).length > 1"
      class="mb-4 rounded-lg bg-black/15 pt-1"
    >
      <svg class="h-[150px] w-full" viewBox="0 0 320 150" preserveAspectRatio="none">
        <line
          v-for="ratio in [0, 0.5, 1]"
          :key="`grid-${ratio}`"
          x1="32"
          x2="306"
          :y1="14 + ratio * 108"
          :y2="14 + ratio * 108"
          :stroke="gridStroke"
          stroke-width="1"
        />
        <path
          v-for="series in lineChartSeries(report)"
          :key="series.key"
          :d="lineChartPath(report, series)"
          fill="none"
          :stroke="series.color || CHART_COLORS.accent"
          :stroke-width="series.dashed ? 2 : 3"
          stroke-linecap="round"
          stroke-linejoin="round"
          :stroke-dasharray="series.dashed ? '6 5' : null"
          vector-effect="non-scaling-stroke"
        />
        <template
          v-for="series in lineChartSeries(report)"
          :key="`points-${series.key}`"
        >
          <circle
            v-for="(row, index) in lineChartRows(report)"
            v-show="Number.isFinite(Number(row[series.key])) && Number(row[series.key]) > 0"
            :key="`${series.key}-${row.label}`"
            :cx="lineChartX(report, index)"
            :cy="lineChartY(report, row[series.key])"
            r="3.5"
            :fill="series.color || CHART_COLORS.accent"
            :stroke="pointStroke"
            stroke-width="1.5"
            vector-effect="non-scaling-stroke"
          />
        </template>
        <text
          v-for="(row, index) in lineChartRows(report)"
          :key="`label-${row.label}`"
          :x="lineChartX(report, index)"
          y="142"
          :fill="labelFill"
          font-size="9"
          font-weight="700"
          text-anchor="middle"
        >
          {{ row.shortLabel || row.label.replace(' avg', '') }}
        </text>
      </svg>
      <div class="flex flex-wrap gap-x-3 gap-y-1 px-2 pb-2">
        <div
          v-for="series in lineChartSeries(report)"
          :key="`legend-${series.key}`"
          class="flex items-center gap-1.5"
        >
          <span
            class="h-2.5 w-2.5 rounded-full"
            :style="{ backgroundColor: series.color || CHART_COLORS.accent }"
          ></span>
          <span class="text-[10px] font-black uppercase text-white/55">{{ series.label }}</span>
        </div>
      </div>
    </div>
    <div class="space-y-3">
      <div
        v-for="row in report.rows.filter(r => Number.isFinite(Number(r.value)) && Number(r.value) > 0)"
        :key="row.label"
      >
        <div class="mb-1 flex items-center justify-between gap-3">
          <p class="text-[11px] font-black uppercase text-white/65">{{ row.label }}</p>
          <p class="text-xs font-black text-white">{{ fmtReport(row.value, report.suffix) }}</p>
        </div>
        <div class="relative h-2.5 overflow-hidden rounded-full bg-white/10">
          <div
            class="h-full rounded-full"
            :style="{
              width: `${Math.max(5, Math.min(100, (Number(row.value) / Math.max(1, Number(report.max))) * 100))}%`,
              backgroundColor: row.color || CHART_COLORS.accent,
            }"
          ></div>
          <div
            v-if="Number.isFinite(Number(row.expected))"
            class="absolute top-[-2px] h-4 w-0.5 bg-white/85"
            :style="{ left: `${Math.max(0, Math.min(100, (Number(row.expected) / Math.max(1, Number(report.max))) * 100))}%` }"
          ></div>
        </div>
      </div>
    </div>
    <div class="mt-4 grid grid-cols-2 gap-2">
      <div
        v-for="tile in report.tiles"
        :key="tile.label"
        class="rounded-lg border border-white/10 bg-surface-raised/80 p-3"
      >
        <p class="text-[10px] font-black uppercase tracking-wide text-white/45">{{ tile.label }}</p>
        <p class="mt-1 text-lg font-black text-white">{{ tile.value }}</p>
        <p v-if="tile.sub" class="mt-0.5 text-[10px] font-bold text-white/35">{{ tile.sub }}</p>
      </div>
    </div>
  </div>
</template>
