<script setup>
defineProps({
  recovery: { type: Object, default: () => ({}) },
  current: { type: Object, default: () => ({}) },
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Recovery + Sleep</h3>
    <p class="mt-2 text-sm text-slate-300">Sleep avg: <strong>{{ current.sleep_hours ?? '—' }} hrs</strong></p>
    <p class="text-sm text-slate-300">Recovery score: <strong>{{ recovery.score ?? '—' }}</strong></p>
    <p class="mt-2 text-xs text-slate-400">Low soreness/stress boosts score. Missing data falls back safely.</p>
  </div>
</template>
