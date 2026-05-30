<script setup>
defineProps({
  current: { type: Object, default: () => ({}) },
  scores: { type: Object, default: () => ({}) },
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Where Are We?</h3>
    <p class="mt-1 text-xs text-slate-400">Current baseline from the most recent performance, strength, mobility, and recovery data.</p>
    <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-slate-300 md:grid-cols-4">
      <div>Current EV (mph): <strong>{{ current.avg_exit_velocity ?? '—' }}</strong></div>
      <div>Current Pitch Velo (mph): <strong>{{ current.avg_pitch_velocity ?? '—' }}</strong></div>
      <div>Batting Performance Score: <strong>{{ current.bp_score ?? '—' }}</strong></div>
      <div>Bullpen Performance Score: <strong>{{ current.bullpen_score ?? '—' }}</strong></div>
      <div>Strength Composite: <strong>{{ scores.strengthScore ?? '—' }}</strong></div>
      <div>Mobility Composite: <strong>{{ scores.mobilityScore ?? '—' }}</strong></div>
      <div>Recovery Composite: <strong>{{ scores.recoveryScore ?? '—' }}</strong></div>
    </div>
  </div>
</template>
