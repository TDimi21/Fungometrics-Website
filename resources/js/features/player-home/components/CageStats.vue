<script setup>
import { computed } from 'vue'
import MetricBarList from './MetricBarList.vue'
import DistributionBar from './DistributionBar.vue'
import { SEGMENT_COLORS } from '../lib/constants.js'

const props = defineProps({
  breakdown: { type: Object, required: true },
  metricRows: { type: Array, default: () => [] },
})

const spraySegments = computed(() => ([
  { label: 'Pull', pct: props.breakdown.pullPct, color: SEGMENT_COLORS.red },
  { label: 'Center', pct: props.breakdown.centerPct, color: SEGMENT_COLORS.blue },
  { label: 'Oppo', pct: props.breakdown.oppoPct, color: SEGMENT_COLORS.green },
]))
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
    <p class="text-xs font-black uppercase tracking-widest text-white/60">
      Cage Metrics ({{ breakdown.swings }} swings)
    </p>
  </div>

  <MetricBarList :rows="metricRows" />

  <DistributionBar
    v-if="breakdown.sprayTotal > 0"
    :title="`Spray Efficiency (${breakdown.sprayTotal} swings)`"
    :segments="spraySegments"
  />
</template>
