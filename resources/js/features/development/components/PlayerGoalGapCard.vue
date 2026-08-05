<script setup>
defineProps({ goals: { type: Array, default: () => [] } })
</script>
<template>
  <section class="pd-card" data-testid="goals-gaps">
    <header><p>Where Are We Going?</p><h2>Goals &amp; Gaps</h2></header>
    <div v-if="goals.length" class="goals">
      <article v-for="goal in goals" :key="goal.key">
        <strong>{{ goal.label }}</strong>
        <div><span>Current {{ goal.display_value || 'Needs Data' }}</span><span>Goal {{ goal.goal_display || 'not established' }}</span></div>
        <div class="track"><span :style="{ width: `${goal.percentile || 0}%` }" /></div>
        <small>Gap {{ goal.gap_display || 'Needs Data' }}</small>
      </article>
    </div>
    <p v-else class="empty">Goal not established</p>
  </section>
</template>
<style scoped>
.pd-card{padding:14px;background:#071725;border:1px solid #254154;border-radius:10px;color:#edf5fa}.pd-card header p{font-size:9px;letter-spacing:.17em;text-transform:uppercase;color:#7dd3fc}.pd-card h2{font-size:15px;font-weight:900;text-transform:uppercase}.goals{display:grid;gap:9px;margin-top:10px}.goals article{font-size:10px}.goals article>div{display:flex;justify-content:space-between;color:#91a6b6}.goals strong{font-size:11px}.track{height:4px!important;background:#203545;border-radius:9px;margin:5px 0;overflow:hidden}.track span{display:block;height:100%;background:#ef3340}.goals small{color:#f2b84b}.empty{margin-top:10px;border:1px dashed #385164;border-radius:7px;padding:12px;text-align:center;color:#8399a9;font-size:11px}
</style>
