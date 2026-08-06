<script setup>
import { computed } from 'vue'
import DistributionBar from './DistributionBar.vue'
import { clampPct } from '../lib/playerHomeAdapter.js'
import { BAR_COLOR_AVG, BAR_COLOR_GREAT, SEGMENT_COLORS } from '../lib/constants.js'

const props = defineProps({
  breakdown: { type: Object, required: true },
})

const gauges = computed(() => {
  const b = props.breakdown
  return [
    { label: 'Hard Contact %', value: b.hardPct, unit: '%', width: clampPct(b.hardPct), color: BAR_COLOR_GREAT },
    { label: 'Miss %', value: b.missPct, unit: '%', width: clampPct(b.missPct), color: SEGMENT_COLORS.red },
    { label: 'Avg Exit Velocity', value: b.avgEV, unit: 'mph', width: clampPct(((Number(b.avgEV || 0) - 40) / 60) * 100), color: BAR_COLOR_AVG },
    { label: 'Top Exit Velocity', value: b.maxEV, unit: 'mph', width: clampPct(((Number(b.maxEV || 0) - 50) / 60) * 100), color: BAR_COLOR_GREAT },
  ]
})

const spraySegments = computed(() => ([
  { label: 'Left', pct: props.breakdown.lfPct, color: SEGMENT_COLORS.red },
  { label: 'Center', pct: props.breakdown.cfPct, color: SEGMENT_COLORS.blue },
  { label: 'Right', pct: props.breakdown.rfPct, color: SEGMENT_COLORS.green },
]))

const profileSegments = computed(() => ([
  { label: 'GB', pct: props.breakdown.gbPct, color: SEGMENT_COLORS.orange },
  { label: 'LD', pct: props.breakdown.ldPct, color: SEGMENT_COLORS.green },
  { label: 'FB', pct: props.breakdown.fbPct, color: SEGMENT_COLORS.blue },
  { label: 'PF', pct: props.breakdown.pfPct, color: SEGMENT_COLORS.purple },
]))

const zoneRows = computed(() => {
  const zone = props.breakdown.zonePerf
  if (!zone) return []
  return [
    { label: 'Upper Zone', value: zone.upperHardPct },
    { label: 'Lower Zone', value: zone.lowerHardPct },
    { label: 'Inner Half', value: zone.innerHardPct },
    { label: 'Outer Half', value: zone.outerHardPct },
  ]
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
    <p class="text-xs font-black uppercase tracking-widest text-white/60">
      Contact Metrics ({{ breakdown.swings }} swings)
    </p>
  </div>

  <div v-if="breakdown.swings < 1" class="rounded-xl border border-white/10 bg-white/5 px-4 py-6 text-center text-sm text-white/45">
    Log BP sessions to unlock detailed contact metrics.
  </div>

  <template v-else>
    <div v-for="gauge in gauges" :key="gauge.label" class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
      <div class="mb-2 flex items-center justify-between gap-3">
        <p class="text-xs font-black uppercase tracking-wider text-white/70">{{ gauge.label }}</p>
        <p class="text-sm font-black text-white">{{ gauge.value ?? '—' }}<span v-if="gauge.value !== null" class="ml-1 text-white/65">{{ gauge.unit }}</span></p>
      </div>
      <div class="h-2.5 overflow-hidden rounded-full bg-white/10">
        <div class="h-full rounded-full" :style="{ width: `${gauge.width}%`, backgroundColor: gauge.color }"></div>
      </div>
    </div>

    <DistributionBar
      v-if="breakdown.sprayTotal >= 3"
      :title="`Spray Distribution (${breakdown.sprayTotal} balls in play)`"
      :segments="spraySegments"
    />

    <DistributionBar
      v-if="breakdown.trajTotal >= 3"
      :title="`Batted Ball Profile (${breakdown.trajTotal} contact balls)`"
      :segments="profileSegments"
    />

    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
      <div class="mb-2 flex items-center justify-between gap-3">
        <p class="text-xs font-black uppercase tracking-wider text-white/70">Damage Score™</p>
        <p class="text-sm font-black text-white">{{ breakdown.damageScore ?? '—' }}</p>
      </div>
      <div class="h-2.5 overflow-hidden rounded-full bg-white/10">
        <div class="h-full rounded-full" :style="{ width: `${clampPct(breakdown.damageScore)}%`, backgroundColor: SEGMENT_COLORS.red }"></div>
      </div>
    </div>

    <div v-if="breakdown.zonePerf" class="rounded-xl border border-white/10 bg-white/5 p-4">
      <p class="mb-3 text-xs font-black uppercase tracking-widest text-white/60">Zone Hard Contact %</p>
      <div class="space-y-2">
        <div v-for="z in zoneRows" :key="z.label" class="rounded-lg border border-white/10 bg-surface-raised p-3">
          <div class="mb-1 flex items-center justify-between gap-3"><p class="text-xs font-bold text-white/80">{{ z.label }}</p><p class="text-xs font-black text-white">{{ z.value ?? '—' }}<span v-if="z.value !== null">%</span></p></div>
          <div class="h-2.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full" :style="{ width: `${clampPct(z.value)}%`, backgroundColor: BAR_COLOR_GREAT }"></div></div>
        </div>
      </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
      <div class="mb-2 flex items-center justify-between gap-3">
        <p class="text-xs font-black uppercase tracking-wider text-white/70">Competitive Swing %</p>
        <p class="text-sm font-black text-white">{{ breakdown.compPct ?? '—' }}<span v-if="breakdown.compPct !== null" class="ml-1 text-white/65">%</span></p>
      </div>
      <div class="h-2.5 overflow-hidden rounded-full bg-white/10">
        <div class="h-full rounded-full" :style="{ width: `${clampPct(breakdown.compPct)}%`, backgroundColor: BAR_COLOR_AVG }"></div>
      </div>
    </div>

    <div v-if="breakdown.consistency" class="rounded-xl border border-white/10 bg-white/5 p-4">
      <p class="mb-3 text-xs font-black uppercase tracking-widest text-white/60">Round Consistency (first 10 vs last 10)</p>
      <div class="space-y-2 text-xs">
        <div class="flex items-center justify-between"><span class="text-white/65">Hard Contact Change</span><span class="font-black text-white">{{ breakdown.consistency.hardDrop > 0 ? `-${breakdown.consistency.hardDrop}` : breakdown.consistency.hardDrop < 0 ? `+${Math.abs(breakdown.consistency.hardDrop)}` : '→ Same' }}</span></div>
        <div class="flex items-center justify-between"><span class="text-white/65">Miss Change</span><span class="font-black text-white">{{ breakdown.consistency.missDiff > 0 ? `+${breakdown.consistency.missDiff} more` : breakdown.consistency.missDiff < 0 ? `${breakdown.consistency.missDiff} fewer` : '→ Same' }}</span></div>
        <div v-if="breakdown.consistency.evDrop !== null" class="flex items-center justify-between"><span class="text-white/65">EV Drop</span><span class="font-black text-white">{{ breakdown.consistency.evDrop > 0 ? `-${breakdown.consistency.evDrop} mph` : breakdown.consistency.evDrop < 0 ? `+${Math.abs(breakdown.consistency.evDrop)} mph` : '→ Steady' }}</span></div>
      </div>
    </div>
  </template>
</template>
