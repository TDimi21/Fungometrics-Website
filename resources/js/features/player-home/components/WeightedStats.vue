<script setup>
import MetricBarList from './MetricBarList.vue'
import TrainingReportCard from './TrainingReportCard.vue'

defineProps({
  report: { type: Object, default: null },
  metricRows: { type: Array, default: () => [] },
})
</script>

<template>
  <TrainingReportCard v-if="report" :report="report" />
  <MetricBarList :rows="metricRows" />
</template>
