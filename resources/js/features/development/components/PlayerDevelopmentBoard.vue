<script setup>
defineProps({
  players: { type: Array, default: () => [] },
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Player Development Board</h3>
    <div class="mt-3 overflow-x-auto">
      <table class="min-w-full text-left text-sm text-slate-300">
        <thead>
          <tr class="text-xs uppercase text-slate-400">
            <th class="py-2 pr-4">Player</th>
            <th class="py-2 pr-4">PDI</th>
            <th class="py-2 pr-4">Status</th>
            <th class="py-2">Trend</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(p, idx) in players" :key="idx" class="border-t border-white/10">
            <td class="py-2 pr-4">{{ p.name }}</td>
            <td class="py-2 pr-4">{{ p.developmentIndex ?? '—' }}</td>
            <td class="py-2 pr-4">{{ p.status ?? '—' }}</td>
            <td class="py-2">{{ p.trend ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
