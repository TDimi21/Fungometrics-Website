<script setup>
defineProps({ strength: { type: Object, default: () => ({}) } })
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Strength Metrics</h3>
    <p class="mt-1 text-xs text-slate-400">Sub-scores used to build the overall Strength score.</p>
    <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-slate-300">
      <div>Lower Body Strength: <strong>{{ strength.parts?.lowerBodyStrength ?? '—' }}</strong></div>
      <div>Upper Body Strength: <strong>{{ strength.parts?.upperBodyStrength ?? '—' }}</strong></div>
      <div>Explosive Power: <strong>{{ strength.parts?.explosivePower ?? '—' }}</strong></div>
      <div>Rotational Power: <strong>{{ strength.parts?.rotationalPower ?? '—' }}</strong></div>
    </div>
  </div>
</template>
