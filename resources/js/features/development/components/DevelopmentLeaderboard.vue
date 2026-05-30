<script setup>
defineProps({ players: { type: Array, default: () => [] } })
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Development Leaderboard</h3>
    <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-300">
      <li v-for="(p, idx) in players" :key="idx">
        {{ p.name }} — <strong>{{ p.developmentIndex ?? '—' }}</strong>
      </li>
    </ol>
  </div>
</template>
