<script setup>
defineProps({ insights: { type: Array, default: () => [] } })
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Correlation Insights</h3>
    <p class="mt-1 text-xs text-slate-400">Relationships between changes (example: rising strength with rising EV).</p>
    <ul class="mt-3 space-y-2 text-sm text-slate-300">
      <li v-for="(item, idx) in insights" :key="idx" class="rounded-lg border border-white/10 p-3">{{ item }}</li>
    </ul>
  </div>
</template>
