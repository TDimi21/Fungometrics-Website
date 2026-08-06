<script setup>
import { computed } from 'vue'
import MetricBarList from './MetricBarList.vue'
import DistributionBar from './DistributionBar.vue'
import TrainingReportCard from './TrainingReportCard.vue'
import { SEGMENT_COLORS } from '../lib/constants.js'

const props = defineProps({
  breakdown: { type: Object, required: true },
  report: { type: Object, default: null },
  metricRows: { type: Array, default: () => [] },
})

const hopSegments = computed(() => ([
  { label: '0 Hops', pct: props.breakdown.hop0Pct, color: SEGMENT_COLORS.red },
  { label: '1 Hop', pct: props.breakdown.hop1Pct, color: SEGMENT_COLORS.maroon },
  { label: '2 Hops', pct: props.breakdown.hop2Pct, color: SEGMENT_COLORS.blue },
  { label: '3 Hops', pct: props.breakdown.hop3Pct, color: SEGMENT_COLORS.green },
]))
</script>

<template>
  <TrainingReportCard v-if="report" :report="report" />
  <MetricBarList :rows="metricRows" />

  <DistributionBar
    v-if="(breakdown.hopTotal || 0) > 0"
    :title="`Hop Distribution (${breakdown.hopTotal} throws)`"
    :segments="hopSegments"
  />
</template>
