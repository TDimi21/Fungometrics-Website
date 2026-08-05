<script setup>
defineProps({ recovery: { type: Object, default: () => ({}) } })
</script>
<template>
  <section class="support" data-testid="recovery-sleep"><h2>Recovery + Sleep</h2><p>Readiness support, not a medical diagnosis.</p><div class="metric"><span>Sleep Average</span><strong>{{ recovery.sleep?.display_value || 'Needs Data' }}</strong></div><div class="metric"><span>Recovery Score</span><strong>{{ recovery.score?.display_value || 'Needs Data' }}</strong><div class="bar" :class="{ dashed: !recovery.score?.available }"><i :style="{ width: `${recovery.score?.value || 0}%` }" /></div></div><small>{{ recovery.message }}</small></section>
</template>
<style scoped>
.support{padding:12px;background:#071725;border:1px solid #254154;border-radius:10px;color:#edf5fa}.support h2{text-transform:uppercase;font-size:11px;font-weight:900}.support>p,.support small{font-size:8px;color:#7890a0}.metric{margin-top:10px}.metric>span{display:block;color:#8da2b1;font-size:9px}.metric strong{font-size:12px}.bar{height:4px;background:#263d4d;margin-top:4px}.bar i{display:block;height:100%;background:#4fa6d7}.bar.dashed{background:repeating-linear-gradient(90deg,#425766 0 7px,transparent 7px 11px)}
</style>
