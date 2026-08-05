<script setup>
defineProps({ metrics: { type: Array, default: () => [] } })
</script>
<template>
  <section class="support" data-testid="mobility-assessment"><h2>Mobility Assessment</h2><p>Movement scores from the latest player assessment.</p><article v-for="metric in metrics" :key="metric.label"><div><span>{{ metric.label }}</span><strong>{{ metric.display_value || 'Needs Data' }}</strong></div><div class="bar" :class="{ dashed: !metric.available }"><i :style="{ width: `${Math.max(0, Math.min(100, metric.bar_value ?? metric.value ?? 0))}%` }" /></div><small>{{ metric.description }}</small></article></section>
</template>
<style scoped>
.support{padding:12px;background:#071725;border:1px solid #254154;border-radius:10px;color:#edf5fa}.support h2{text-transform:uppercase;font-size:11px;font-weight:900}.support>p,small{font-size:8px;color:#7890a0}.support article{margin-top:8px}.support article>div:first-child{display:flex;justify-content:space-between;font-size:9px}.bar{height:4px;background:#263d4d;margin-top:3px}.bar i{display:block;height:100%;background:#ef3340}.bar.dashed{background:repeating-linear-gradient(90deg,#425766 0 7px,transparent 7px 11px)}
</style>
