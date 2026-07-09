<script setup>
defineProps({
  rows: { type: Array, default: () => [] },
  ageLabel: { type: [String, Number], default: '' },
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Percentile Rankings</h3>
    <p class="mt-1 text-xs text-slate-400">Compares this player to age/level benchmarks. P90+ = Elite, P75–89 = Above Average, P50–74 = Average, P25–49 = Below Average, &lt;P25 = Needs Development.</p>
    <div class="mt-3 overflow-x-auto">
      <table class="min-w-full text-left text-sm text-slate-300">
        <thead>
          <tr class="text-xs uppercase text-slate-400">
            <th class="py-2 pr-4">Metric</th>
            <th class="py-2 pr-4">Age</th>
            <th class="py-2 pr-4">Value</th>
            <th class="py-2 pr-4">Percentile</th>
            <th class="py-2">Label</th>
            <th class="py-2 pr-4">Goal</th>
            <th class="py-2 pr-4">Gap</th>
            <th class="py-2">Trend</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, idx) in rows" :key="idx" class="border-t border-white/10">
            <td class="py-2 pr-4">{{ r.metric }}</td>
            <td class="py-2 pr-4">{{ ageLabel || '—' }}</td>
            <td class="py-2 pr-4">{{ r.value }}</td>
            <td class="py-2 pr-4">{{ r.percentile !== null && r.percentile !== undefined ? `${r.percentile}th` : '—' }}</td>
            <td class="py-2">{{ r.label }}</td>
            <td class="py-2 pr-4">{{ r.goal || 'Benchmark' }}</td>
            <td class="py-2 pr-4">{{ r.gap || 'Needs Data' }}</td>
            <td class="py-2 font-black"
              :class="r.trend === '↑' ? 'text-emerald-300' : r.trend === '↓' ? 'text-red-300' : r.trend === '→' ? 'text-yellow-300' : 'text-slate-500'">
              {{ r.trend || '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
