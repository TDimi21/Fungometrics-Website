<script setup>
defineProps({ priorities: { type: Array, default: () => [] } })
</script>
<template>
  <section class="pd-card" data-testid="development-priorities">
    <header><p>How Do We Get There?</p><h2>Development Priorities</h2></header>
    <div v-if="priorities.length" class="priority-list">
      <article v-for="item in priorities.slice(0,3)" :key="item.id">
        <b>{{ item.rank }}</b><div><span>{{ item.priority }} priority · {{ item.category }}</span><strong>{{ item.title }}</strong><p>{{ item.description }}</p></div>
      </article>
    </div>
    <p v-else class="empty">Needs Data — add connected sessions or assessments to establish priorities.</p>
  </section>
</template>
<style scoped>
.pd-card{padding:14px;background:#071725;border:1px solid #254154;border-radius:10px;color:#edf5fa}.pd-card header p{font-size:9px;letter-spacing:.17em;text-transform:uppercase;color:#7dd3fc}.pd-card h2{font-size:15px;font-weight:900;text-transform:uppercase}.priority-list{display:grid;gap:9px;margin-top:11px}.priority-list article{display:grid;grid-template-columns:25px 1fr;gap:8px}.priority-list article>b{width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:#0d6e69;color:#fff;font-size:11px}.priority-list span{display:block;color:#f0b53f;font-size:8px;text-transform:uppercase;letter-spacing:.08em}.priority-list strong{font-size:11px}.priority-list p,.empty{color:#8fa5b4;font-size:10px;line-height:1.4}.empty{margin-top:10px}
</style>
