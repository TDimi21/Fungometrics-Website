<script setup>
defineProps({
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  categories: { type: Array, default: () => [] },
  category: { type: String, default: '' },
  metricKey: { type: String, default: '' },
  metrics: { type: Array, default: () => [] },
  selectedMetric: { type: Object, default: null },
  rows: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:category', 'update:metricKey', 'retry', 'selectPlayer'])

const titleCase = (value) => String(value || 'Other').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
const percentileLabel = (value) => {
  const rank = Math.round(Number(value))
  const suffix = rank % 100 >= 11 && rank % 100 <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[rank % 10] || 'th')
  return `${rank}${suffix}`
}
const percentileColor = (value) => Number(value) >= 75 ? '#2ecc71' : Number(value) >= 40 ? '#efa92f' : '#ff2d4f'
const actualLabel = (row) => {
  if (!row || !Number.isFinite(Number(row.actual)) || Number(row.actual) <= 0) return '—'
  const value = Number(row.actual)
  const precision = row.unit === 's' || row.unit === 'sec' ? 2 : Number.isInteger(value) ? 0 : 1
  return `${value.toFixed(precision)} ${row.unit || ''}`.trim()
}
</script>

<template>
  <section>
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
      <div class="max-w-3xl">
        <div class="text-[10px] font-black uppercase tracking-[0.18em] text-[#ff4058]">Age-Adjusted Team Rankings</div>
        <h3 class="mt-1 text-lg font-black uppercase tracking-wide text-white">Top 25 by Metric</h3>
        <p class="mt-1 text-xs leading-relaxed text-white/45">
          Rankings use each player’s governed age-benchmark percentile. The actual result and benchmark group remain visible so athletes of different ages are compared fairly.
        </p>
      </div>
      <div class="grid w-full gap-2 sm:grid-cols-2 xl:w-auto xl:min-w-[520px]">
        <label class="flex flex-col gap-1 text-[9px] font-black uppercase tracking-widest text-white/35">
          Category
          <select :value="category" class="rounded-lg border border-white/15 bg-[#10192a] px-3 py-2.5 text-xs font-bold normal-case tracking-normal text-white outline-none focus:border-[#C00000]" @change="emit('update:category', $event.target.value)">
            <option v-for="option in categories" :key="option" :value="option">{{ titleCase(option) }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1 text-[9px] font-black uppercase tracking-widest text-white/35">
          Metric
          <select :value="metricKey" class="rounded-lg border border-white/15 bg-[#10192a] px-3 py-2.5 text-xs font-bold normal-case tracking-normal text-white outline-none focus:border-[#C00000]" @change="emit('update:metricKey', $event.target.value)">
            <option v-for="metric in metrics" :key="metric.key" :value="metric.key">{{ metric.label }} ({{ metric.playerCount }})</option>
          </select>
        </label>
      </div>
    </div>

    <div v-if="loading" class="mt-5 space-y-2">
      <div v-for="index in 6" :key="index" class="h-11 animate-pulse rounded-lg bg-white/5" />
    </div>
    <div v-else-if="error" class="mt-5 rounded-xl border border-red-500/25 bg-red-500/5 px-4 py-6 text-center">
      <p class="text-sm text-red-200/80">{{ error }}</p>
      <button type="button" class="mt-3 rounded-lg border border-red-400/30 px-3 py-1.5 text-xs font-black uppercase tracking-wide text-red-300" @click="emit('retry')">Try Again</button>
    </div>
    <div v-else-if="!selectedMetric || !rows.length" class="mt-5 rounded-xl border border-dashed border-white/10 py-10 text-center text-sm text-white/30">
      No players have a valid age-adjusted percentile for this metric yet.
    </div>
    <div v-else class="mt-5 overflow-x-auto rounded-xl border border-white/10">
      <div class="flex items-center justify-between gap-3 border-b border-white/10 bg-white/[0.025] px-4 py-3">
        <div>
          <b class="text-sm text-white">{{ selectedMetric.label }}</b>
          <span class="ml-2 text-[10px] uppercase tracking-widest text-white/30">{{ titleCase(selectedMetric.category) }}</span>
        </div>
        <span class="text-[10px] font-bold text-white/35">{{ rows.length }} ranked player{{ rows.length === 1 ? '' : 's' }}</span>
      </div>
      <table class="w-full min-w-[820px] border-collapse">
        <thead>
          <tr class="bg-black/10 text-left text-[9px] font-black uppercase tracking-widest text-white/30">
            <th class="w-14 px-4 py-3">Rank</th>
            <th class="px-3 py-3">Player</th>
            <th class="px-3 py-3">Age</th>
            <th class="px-3 py-3">Benchmark Group</th>
            <th class="min-w-[210px] px-3 py-3">Age Percentile</th>
            <th class="px-3 py-3 text-right">Actual Result</th>
            <th class="px-4 py-3">Classification</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="`${row.metricKey}-${row.playerId}`" class="cursor-pointer border-t border-white/[0.06] text-xs text-white/75 transition hover:bg-white/[0.035]" @click="emit('selectPlayer', row)">
            <td class="px-4 py-3"><span class="inline-flex h-7 w-7 items-center justify-center rounded-lg font-black" :class="index === 0 ? 'bg-[#C00000] text-white' : 'bg-white/[0.06] text-white/55'">{{ row.rank }}</span></td>
            <td class="px-3 py-3 font-black text-white">{{ row.playerName }}</td>
            <td class="px-3 py-3 tabular-nums">{{ row.age != null ? `${row.age} yrs` : '—' }}</td>
            <td class="px-3 py-3"><span class="rounded-md border border-sky-400/20 bg-sky-400/5 px-2 py-1 text-[10px] font-black text-sky-300">{{ row.ageGroupLabel }}</span></td>
            <td class="px-3 py-3">
              <div class="flex items-center gap-3">
                <b class="w-10 text-right text-sm tabular-nums" :style="{ color: percentileColor(row.percentile) }">{{ percentileLabel(row.percentile) }}</b>
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/10"><i class="block h-full rounded-full" :style="{ width: `${row.percentile}%`, backgroundColor: percentileColor(row.percentile) }" /></div>
              </div>
            </td>
            <td class="px-3 py-3 text-right text-sm font-black tabular-nums text-white">{{ actualLabel(row) }}</td>
            <td class="px-4 py-3"><b class="block text-[10px] uppercase tracking-wide" :style="{ color: percentileColor(row.percentile) }">{{ titleCase(row.label) }}</b><small class="mt-0.5 block text-[9px] text-white/30">{{ titleCase(row.confidence) }} confidence</small></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
