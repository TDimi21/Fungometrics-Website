<script setup>
defineProps({
  recommendations: { type: Array, default: () => [] },
  coachNotes: { type: String, default: '' },
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Coach Action Plan</h3>
    <p class="mt-1 text-xs text-slate-400">Simple execution plan for the next 1–2 weeks based on current data.</p>
    <div class="mt-3 space-y-2 text-sm text-slate-300">
      <p><strong>Primary focus:</strong> {{ recommendations[0]?.title || 'No data yet' }}</p>
      <p><strong>Secondary focus:</strong> {{ recommendations[1]?.title || 'No data yet' }}</p>
      <p><strong>Recommended session template:</strong> {{ recommendations[0]?.recommendation || 'No data yet' }}</p>
      <p><strong>Suggested goal (2 weeks):</strong> +1 to +2 points in weakest score domain.</p>
      <p><strong>Coach notes:</strong> {{ coachNotes || 'No coach notes yet.' }}</p>
    </div>
  </div>
</template>
