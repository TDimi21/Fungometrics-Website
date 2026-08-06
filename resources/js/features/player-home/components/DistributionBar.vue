<script setup>
import { clampPct } from '../lib/playerHomeAdapter.js'

defineProps({
  title: { type: String, required: true },
  // [{ label, pct, color }] — pct may be null; only positive slices render.
  segments: { type: Array, required: true },
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-white/5 p-4">
    <p class="mb-3 text-xs font-black uppercase tracking-widest text-white/60">{{ title }}</p>
    <div class="flex h-6 overflow-hidden rounded-md bg-white/10">
      <template v-for="segment in segments" :key="segment.label">
        <div
          v-if="(segment.pct || 0) > 0"
          class="h-full"
          :style="{ width: `${clampPct(segment.pct)}%`, backgroundColor: segment.color }"
        ></div>
      </template>
    </div>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-y-1 text-xs font-black">
      <span v-for="segment in segments" :key="`label-${segment.label}`" :style="{ color: segment.color }">
        {{ segment.label }} {{ segment.pct ?? 0 }}%
      </span>
    </div>
  </div>
</template>
