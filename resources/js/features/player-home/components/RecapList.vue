<script setup>
import DashCard from '@/features/shared/components/DashCard.vue'
import { recapTypeStyle } from '../lib/playerHomeAdapter.js'

defineProps({
  sessions: { type: Array, default: () => [] },
})

defineEmits(['open-report'])
</script>

<template>
  <DashCard title="Recap">
    <div v-if="sessions.length === 0" class="text-sm text-white/50">No recent sessions found.</div>
    <div v-else class="space-y-2">
      <button
        v-for="s in sessions"
        :key="s.id"
        type="button"
        class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left transition"
        :class="[
          recapTypeStyle(s).bg,
          recapTypeStyle(s).border,
          s._reportType ? 'cursor-pointer hover:border-accent-2/60 hover:bg-surface-raised' : 'cursor-default opacity-70'
        ]"
        @click="$emit('open-report', s)"
      >
        <div class="min-w-0">
          <span
            class="mb-1 inline-flex rounded-md border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
            :class="[recapTypeStyle(s).bg, recapTypeStyle(s).border, recapTypeStyle(s).text]"
          >
            {{ recapTypeStyle(s).label }}
          </span>
          <p class="truncate text-sm font-bold text-white/90">{{ s._label }}</p>
          <p class="text-xs text-white/60">{{ (s._date || '').toString().slice(0, 10) || '—' }}</p>
        </div>
        <p v-if="s._reportType" class="text-xs font-black text-white/40">REPORT ›</p>
      </button>
    </div>
  </DashCard>
</template>
