<script setup>
defineProps({
  player: { type: Object, default: () => ({}) },
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <p class="text-xl font-semibold text-white">{{ player.name || 'Player' }}</p>
    <p class="mt-1 text-sm text-slate-300">
      Age {{ player.age || '—' }} · {{ player.grade || '—' }} · {{ player.position || '—' }}
    </p>
    <p class="mt-1 text-xs text-slate-400">
      Throws {{ player.throws || '—' }} / Bats {{ player.bats || '—' }} · {{ player.height || '—' }} · {{ player.weight || '—' }} lbs
    </p>
  </div>
</template>
