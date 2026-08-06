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

const hasProfile = computed(() =>
  (props.breakdown.gbPct || 0) + (props.breakdown.ldPct || 0) + (props.breakdown.fbPct || 0) > 0
)

const profileSegments = computed(() => ([
  { label: 'GB', pct: props.breakdown.gbPct, color: SEGMENT_COLORS.navy },
  { label: 'LD', pct: props.breakdown.ldPct, color: SEGMENT_COLORS.maroon },
  { label: 'FB', pct: props.breakdown.fbPct, color: SEGMENT_COLORS.red },
]))
</script>

<template>
  <TrainingReportCard v-if="report" :report="report" />
  <MetricBarList :rows="metricRows" />

  <DistributionBar
    v-if="hasProfile"
    title="Batted Ball Profile (EV Training)"
    :segments="profileSegments"
  />
</template>
