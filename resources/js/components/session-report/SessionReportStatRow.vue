<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: { type: String, default: '' },
  value: { type: [Number, String], default: null },
  unit: { type: String, default: '' },
  min: { type: Number, default: 0 },
  max: { type: Number, default: 100 },
  thresholds: { type: Array, default: () => [40, 70] },
  reverse: { type: Boolean, default: false },
})

const number = computed(() => {
  const parsed = Number.parseFloat(props.value)
  return Number.isFinite(parsed) ? parsed : null
})
const width = computed(() => {
  if (number.value === null || props.max === props.min) return 0
  return Math.min(100, Math.max(0, ((number.value - props.min) / (props.max - props.min)) * 100))
})
const color = computed(() => {
  if (number.value === null) return '#64748b'
  const [low, high] = props.thresholds
  if (!props.reverse) return number.value >= high ? '#2ECC71' : number.value >= low ? '#F39C12' : '#E74C3C'
  return number.value <= low ? '#2ECC71' : number.value <= high ? '#F39C12' : '#E74C3C'
})
</script>

<template>
  <div class="flex items-center gap-3">
    <span class="w-48 shrink-0 truncate text-sm font-bold text-white/85">{{ label }}</span>
    <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-white/15">
      <div class="h-full rounded-full transition-all duration-500" :style="{ width: `${width}%`, backgroundColor: color }" />
    </div>
    <span class="w-20 shrink-0 text-right text-base font-black" :style="{ color }">
      {{ number ?? '—' }}{{ number === null ? '' : unit }}
    </span>
  </div>
</template>
