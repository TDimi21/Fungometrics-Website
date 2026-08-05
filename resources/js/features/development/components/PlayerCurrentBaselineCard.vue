<script setup>
defineProps({ rows: { type: Array, default: () => [] }, assessmentRoute: { type: [String, Object], default: '/assessment-reports' } })
</script>
<template>
  <section class="pd-card" data-testid="current-baseline">
    <header><p>Where Are We?</p><h2>Current Baseline</h2></header>
    <dl>
      <div v-for="row in rows" :key="row.key"><dt>{{ row.label }}</dt><dd>{{ row.display_value || 'Needs Data' }}</dd></div>
    </dl>
    <RouterLink :to="assessmentRoute" class="assessment-link">View Data Player Assessment</RouterLink>
  </section>
</template>
<style scoped>
.pd-card{padding:14px;background:#071725;border:1px solid #254154;border-radius:10px;color:#edf5fa}.pd-card header p{font-size:9px;letter-spacing:.17em;text-transform:uppercase;color:#7dd3fc}.pd-card h2{font-size:15px;font-weight:900;text-transform:uppercase}.pd-card dl{margin-top:10px}.pd-card dl div{display:flex;justify-content:space-between;gap:10px;padding:5px 0;border-bottom:1px solid #193143;font-size:11px}.pd-card dt{color:#94a8b7}.pd-card dd{font-weight:800;text-align:right}.assessment-link{display:block;margin-top:10px;border:1px solid #ef3340;border-radius:5px;padding:7px;text-align:center;color:#fff;font-size:10px;font-weight:800}
</style>
