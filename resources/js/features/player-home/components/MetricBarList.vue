<script setup>
import { barColor, barPercent } from '../lib/playerHomeAdapter.js'

defineProps({
  rows: { type: Array, default: () => [] },
})
</script>

<template>
  <div
    v-for="row in rows"
    :key="row.label"
    class="rounded-xl border border-white/10 bg-white/5 px-4 py-3"
  >
    <div class="mb-2 flex items-center justify-between gap-3">
      <p class="text-xs font-black uppercase tracking-wider text-white/70">{{ row.label }}</p>
      <p class="text-sm font-black text-white">
        {{ row.value ?? '—' }}
        <span v-if="row.value !== null && row.value !== undefined && row.unit" class="ml-1 text-white/65">{{ row.unit }}</span>
      </p>
    </div>
    <div class="h-2.5 overflow-hidden rounded-full bg-white/10">
      <div
        class="h-full rounded-full transition-all"
        :style="{ width: `${barPercent(row)}%`, backgroundColor: barColor(row) }"
      ></div>
    </div>
  </div>
</template>
