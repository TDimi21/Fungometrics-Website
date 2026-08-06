<script setup>
import DashCard from '@/features/shared/components/DashCard.vue'

defineProps({
  counts: { type: Object, required: true },
})

defineEmits(['open-metrics'])
</script>

<template>
  <DashCard title="Player Metrics">
    <template #badge>
      <button
        type="button"
        class="rounded-lg border border-accent-2/60 bg-accent-2/25 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white"
        @click="$emit('open-metrics')"
      >
        Open
      </button>
    </template>

    <div class="grid grid-cols-2 gap-3">
      <div class="rounded-xl border border-white/10 bg-white/5 p-3">
        <p class="text-[10px] uppercase tracking-widest text-white/60">Batting</p>
        <p class="mt-1 text-2xl font-black">{{ counts.batting }}</p>
      </div>
      <div class="rounded-xl border border-white/10 bg-white/5 p-3">
        <p class="text-[10px] uppercase tracking-widest text-white/60">Bullpen</p>
        <p class="mt-1 text-2xl font-black">{{ counts.bullpen }}</p>
      </div>
      <div class="rounded-xl border border-white/10 bg-white/5 p-3">
        <p class="text-[10px] uppercase tracking-widest text-white/60">Cage</p>
        <p class="mt-1 text-2xl font-black">{{ counts.cage }}</p>
      </div>
      <div class="rounded-xl border border-white/10 bg-white/5 p-3">
        <p class="text-[10px] uppercase tracking-widest text-white/60">Training</p>
        <p class="mt-1 text-2xl font-black">{{ counts.training }}</p>
      </div>
    </div>
  </DashCard>
</template>
