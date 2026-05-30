<script setup>
defineProps({ recommendations: { type: Array, default: () => [] } })
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">How Do We Get There?</h3>
    <div v-if="recommendations.length" class="mt-3 space-y-3">
      <div v-for="(r, idx) in recommendations.slice(0, 3)" :key="idx" class="rounded-lg border border-white/10 p-3">
        <p class="text-sm font-semibold text-white">{{ r.title }}</p>
        <p class="text-xs text-slate-300">{{ r.recommendation }}</p>
      </div>
    </div>
    <p v-else class="mt-3 text-sm text-slate-400">No recommendations yet.</p>
  </div>
</template>
