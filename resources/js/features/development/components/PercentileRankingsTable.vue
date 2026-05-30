<script setup>
defineProps({ rows: { type: Array, default: () => [] } })
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Percentile Rankings</h3>
    <p class="mt-1 text-xs text-slate-400">Compares this player to age/level benchmarks. 50th = average, 75th+ = above average.</p>
    <div class="mt-3 overflow-x-auto">
      <table class="min-w-full text-left text-sm text-slate-300">
        <thead>
          <tr class="text-xs uppercase text-slate-400">
            <th class="py-2 pr-4">Metric</th>
            <th class="py-2 pr-4">Value</th>
            <th class="py-2 pr-4">Percentile</th>
            <th class="py-2">Label</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, idx) in rows" :key="idx" class="border-t border-white/10">
            <td class="py-2 pr-4">{{ r.metric }}</td>
            <td class="py-2 pr-4">{{ r.value }}</td>
            <td class="py-2 pr-4">{{ r.percentile ? `${r.percentile}th` : '—' }}</td>
            <td class="py-2">{{ r.label }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
