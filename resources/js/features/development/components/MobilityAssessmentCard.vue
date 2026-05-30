<script setup>
defineProps({ mobility: { type: Object, default: () => ({}) } })
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Mobility Assessment</h3>
    <p class="mt-1 text-xs text-slate-400">Left-vs-right movement symmetry checks. Smaller differences are better.</p>
    <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-slate-300">
      <div>Shoulder IR diff: <strong>{{ mobility.asymmetries?.shoulder_ir_diff ?? '—' }}</strong></div>
      <div>Hip IR diff: <strong>{{ mobility.asymmetries?.hip_ir_diff ?? '—' }}</strong></div>
      <div>Ankle diff: <strong>{{ mobility.asymmetries?.ankle_diff ?? '—' }}</strong></div>
      <div>Thoracic diff: <strong>{{ mobility.asymmetries?.thoracic_diff ?? '—' }}</strong></div>
    </div>
  </div>
</template>
