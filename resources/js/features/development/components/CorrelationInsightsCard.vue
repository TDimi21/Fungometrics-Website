<script setup>
defineProps({
  insights: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Correlation Insights</h3>
    <p class="mt-1 text-xs text-slate-400">What appears to be driving or limiting player development.</p>

    <div v-if="summary?.fallback" class="mt-3 rounded-lg border border-white/10 p-3 text-sm text-slate-300">
      More paired sessions are needed to calculate true correlations. Continue collecting mobility, strength, bullpen, and long toss data.
    </div>

    <div v-else class="mt-3 space-y-3 text-sm text-slate-300">
      <div class="rounded-lg border border-white/10 p-3">
        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Top Contributors</p>
        <ol class="mt-2 space-y-1">
          <li v-for="(item, idx) in summary?.topContributors || []" :key="item" class="flex justify-between gap-3">
            <span>{{ idx + 1 }}. {{ item }}</span>
          </li>
          <li v-if="!(summary?.topContributors || []).length" class="text-slate-500">Needs Data</li>
        </ol>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="rounded-lg border border-amber-300/15 bg-amber-500/10 p-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-amber-200/80">Current Limiter</p>
          <p class="mt-1 font-black text-white">{{ summary?.limiter || 'Needs More Paired Data' }}</p>
        </div>
        <div class="rounded-lg border border-cyan-300/15 bg-cyan-500/10 p-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-cyan-200/80">Confidence</p>
          <p class="mt-1 font-black capitalize text-white">{{ summary?.confidence || 'low' }}</p>
        </div>
      </div>

      <div class="rounded-lg border border-white/10 p-3">
        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Evidence</p>
        <ul class="mt-2 space-y-1 text-xs text-slate-400">
          <li v-for="(item, idx) in summary?.evidence || insights" :key="idx">{{ item }}</li>
          <li v-if="!(summary?.evidence || insights || []).length">Needs Data</li>
        </ul>
      </div>
    </div>
  </div>
</template>
