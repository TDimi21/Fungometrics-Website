<script setup>
defineProps({
  player: { type: Object, default: () => ({}) },
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <p class="text-[11px] uppercase tracking-wider text-slate-400">Who is this athlete?</p>
    <div class="flex items-center gap-4 mt-1">
      <div class="flex-shrink-0 w-16 h-16 rounded-full overflow-hidden ring-2 ring-white/15 bg-slate-800 flex items-center justify-center">
        <img v-if="player.picture" :src="player.picture" :alt="player.name" class="w-full h-full object-cover" />
        <span v-else class="text-xl font-black text-white/40">
          {{ (player.name || 'P').charAt(0).toUpperCase() }}
        </span>
      </div>
      <div>
        <p class="text-xl font-semibold text-white">{{ player.name || 'Player' }}</p>
        <p class="mt-0.5 text-sm text-slate-300">
          Age {{ player.age || '—' }} · {{ player.grade || '—' }} · {{ player.position || '—' }}
        </p>
        <p class="mt-0.5 text-xs text-slate-400">
          Throws {{ player.throws || '—' }} / Bats {{ player.bats || '—' }} · {{ player.height || '—' }} · {{ player.weight || '—' }} lbs
        </p>
      </div>
    </div>
    <p class="mt-2 text-xs text-slate-500">Use this as context for all scores and recommendations below.</p>
  </div>
</template>
