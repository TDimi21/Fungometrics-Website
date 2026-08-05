<script setup>
import PercentileMetricRow from './PercentileMetricRow.vue'
defineProps({ group: { type: Object, required: true } })
</script>
<template>
  <section class="category" :data-category="group.key">
    <h3><span aria-hidden="true">◆</span>{{ group.label }}</h3>
    <PercentileMetricRow v-for="metric in group.metrics" :key="metric.key" :metric="metric" />
  </section>
</template>
<style scoped>
.category h3{display:flex;align-items:center;gap:8px;padding:9px 11px;background:#061521;color:#ff4b56;text-transform:uppercase;font-size:11px;font-weight:900;letter-spacing:.09em}.category h3 span{font-size:8px}.category+ .category{border-top:1px solid #345064}
</style>
