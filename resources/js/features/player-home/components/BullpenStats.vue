<script setup>
import { computed } from 'vue'
import MetricBarList from './MetricBarList.vue'
import { clampPct } from '../lib/playerHomeAdapter.js'
import { SEGMENT_COLORS } from '../lib/constants.js'

const props = defineProps({
  breakdown: { type: Object, required: true },
  metricRows: { type: Array, default: () => [] },
})

const veloRows = computed(() => props.breakdown.pitchTypeStats.filter((x) => x.avgMph !== null))
const emeraldBar = '#34d399'
const amberBar = '#fbbf24'
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
    <p class="text-xs font-black uppercase tracking-widest text-white/60">
      Command Metrics ({{ breakdown.total }} pitches)
    </p>
  </div>

  <MetricBarList :rows="metricRows" />

  <div
    v-if="breakdown.pitchTypeStats.length > 0"
    class="rounded-xl border border-white/10 bg-white/5 p-4"
  >
    <p class="mb-3 text-xs font-black uppercase tracking-widest text-white/60">Strike % by Pitch Type</p>
    <div class="space-y-3">
      <div v-for="pt in breakdown.pitchTypeStats" :key="`strike-${pt.type}`" class="rounded-lg border border-white/10 bg-surface-raised p-3">
        <div class="mb-1 flex items-center justify-between gap-3">
          <p class="text-xs font-bold text-white/80">{{ pt.type }} {{ pt.strikes }}/{{ pt.count }} strikes</p>
          <p class="text-xs font-black text-white">{{ pt.strikePct ?? '—' }}<span v-if="pt.strikePct !== null">%</span></p>
        </div>
        <div class="h-2.5 overflow-hidden rounded-full bg-white/10">
          <div class="h-full rounded-full" :style="{ width: `${clampPct(pt.strikePct)}%`, backgroundColor: emeraldBar }"></div>
        </div>
      </div>
    </div>
  </div>

  <div
    v-if="veloRows.length > 0"
    class="rounded-xl border border-white/10 bg-white/5 p-4"
  >
    <p class="mb-3 text-xs font-black uppercase tracking-widest text-white/60">Avg Velo by Pitch Type</p>
    <div class="space-y-3">
      <div v-for="pt in veloRows" :key="`velo-${pt.type}`" class="rounded-lg border border-white/10 bg-surface-raised p-3">
        <div class="mb-1 flex items-center justify-between gap-3">
          <p class="text-xs font-bold text-white/80">{{ pt.type }}</p>
          <p class="text-xs font-black text-white">{{ pt.avgMph }} mph</p>
        </div>
        <div class="h-2.5 overflow-hidden rounded-full bg-white/10">
          <div
            class="h-full rounded-full"
            :style="{ width: `${clampPct(((Number(pt.avgMph || 0) - 50) / (100 - 50)) * 100)}%`, backgroundColor: amberBar }"
          ></div>
        </div>
      </div>
    </div>
  </div>

  <div
    v-if="breakdown.missPattern.length > 0"
    class="rounded-xl border border-white/10 bg-white/5 p-4"
  >
    <p class="mb-3 text-xs font-black uppercase tracking-widest text-white/60">Miss Location Pattern</p>
    <div class="space-y-2">
      <div v-for="miss in breakdown.missPattern" :key="miss.label" class="rounded-lg border border-white/10 bg-surface-raised p-3">
        <div class="mb-1 flex items-center justify-between gap-3">
          <p class="text-xs font-bold text-white/80">{{ miss.label }}</p>
          <p class="text-xs font-black text-white">{{ miss.pct }}%</p>
        </div>
        <div class="h-2.5 overflow-hidden rounded-full bg-white/10">
          <div class="h-full rounded-full" :style="{ width: `${clampPct(miss.pct)}%`, backgroundColor: SEGMENT_COLORS.red }"></div>
        </div>
      </div>
    </div>
  </div>
</template>
