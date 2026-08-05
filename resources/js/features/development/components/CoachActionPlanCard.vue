<script setup>
defineProps({ actions: { type: Array, default: () => [] }, canAddToPlanner: { type: Boolean, default: false }, readOnly: { type: Boolean, default: true } })
</script>
<template>
  <section class="action-card" data-testid="coach-action-plan">
    <header><div><p>Coach Action Plan</p><span>Current recommendation payload · next 1–2 weeks</span></div></header>
    <div v-if="actions.length" class="actions">
      <article v-for="action in actions.slice(0,3)" :key="action.id">
        <b>{{ action.rank }}</b>
        <div>
          <span class="meta">{{ action.priority }} priority · {{ action.category }} · Confidence {{ action.confidence }}</span>
          <h3>{{ action.title }}</h3>
          <p><strong>Why:</strong> {{ action.why || action.description }}</p>
          <p><strong>Action:</strong> {{ action.action || 'Review with the coach.' }}</p>
          <p v-if="action.expected_gain" class="gain"><strong>Expected Gain:</strong> {{ action.expected_gain }}</p>
        </div>
      </article>
      <RouterLink v-if="canAddToPlanner && !readOnly" to="/practice-planner" class="planner-action">Add to Planner</RouterLink>
    </div>
    <p v-else class="empty">Needs Data — no governed coach actions are available yet.</p>
  </section>
</template>
<style scoped>
.action-card{padding:14px;background:#071725;border:1px solid #254154;border-radius:10px;color:#edf5fa}.action-card header p{font-size:13px;text-transform:uppercase;font-weight:900}.action-card header span{font-size:9px;color:#728898}.actions{display:grid;gap:10px;margin-top:11px}.actions article{display:grid;grid-template-columns:25px 1fr;gap:8px}.actions article>b{width:21px;height:21px;border-radius:50%;display:grid;place-items:center;background:#0a7674;font-size:10px}.meta{display:block;text-transform:uppercase;color:#7fdad5;font-size:8px;letter-spacing:.05em}.actions h3{font-size:11px}.actions p{font-size:9px;color:#8fa5b4;line-height:1.4}.actions p strong{color:#c8d3da}.gain{color:#7ed9a7!important}.planner-action{justify-self:start;border:1px solid #ef3340;border-radius:5px;padding:6px 10px;color:#fff;font-size:9px;font-weight:800}.empty{margin-top:10px;color:#859aa9;font-size:10px}
</style>
