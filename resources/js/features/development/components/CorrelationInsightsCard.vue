<script setup>
defineProps({ correlation: { type: Object, default: () => ({ available: false }) } })
</script>
<template>
  <section class="insights" data-testid="correlation-insights">
    <header><h2>Correlation Insights</h2><p>Observed associations in connected player data; these do not establish causation.</p></header>
    <div v-if="correlation.available" class="insight-grid">
      <article class="positive"><span>Positive relationship</span><strong>{{ correlation.positive?.value ?? '—' }}</strong><p>{{ correlation.positive?.label || 'Needs Data' }}</p></article>
      <article class="negative"><span>Negative relationship</span><strong>{{ correlation.negative?.value ?? '—' }}</strong><p>{{ correlation.negative?.label || 'Needs Data' }}</p></article>
      <article><span>Confidence</span><strong class="capitalize">{{ correlation.confidence }}</strong><p>Observed association only</p></article>
    </div>
    <p v-else class="empty">Not enough connected data yet</p>
  </section>
</template>
<style scoped>
.insights{padding:12px;background:#071725;border:1px solid #254154;border-radius:10px;color:#edf5fa}.insights h2{text-transform:uppercase;font-size:12px;font-weight:900}.insights header p{font-size:9px;color:#7890a0}.insight-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:9px}.insight-grid article{border:1px solid #235068;border-radius:7px;padding:9px;text-align:center}.insight-grid span{display:block;color:#6fc9e7;font-size:8px;text-transform:uppercase}.insight-grid strong{font-size:12px}.insight-grid p{color:#8298a7;font-size:8px}.positive{border-color:#317051!important}.negative{border-color:#743648!important}.empty{margin-top:8px;border:1px dashed #3b5364;border-radius:6px;padding:12px;text-align:center;color:#849aaa;font-size:10px}@media(max-width:520px){.insight-grid{grid-template-columns:1fr}}
</style>
