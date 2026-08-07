<script setup>
import { computed } from 'vue'

const props = defineProps({ segments: { type: Array, default: () => [] } })
const visibleSegments = computed(() => props.segments.filter((segment) => Number(segment?.pct) > 0))
</script>

<template>
  <div>
    <div class="flex h-6 overflow-hidden rounded border border-white/20">
      <div
        v-for="segment in visibleSegments"
        :key="segment.label"
        :style="{ flex: segment.pct, backgroundColor: segment.color }"
      />
    </div>
    <div class="mt-1 flex justify-between">
      <span
        v-for="segment in visibleSegments"
        :key="segment.label"
        class="text-[11px] font-black"
        :style="{ color: segment.color }"
      >{{ segment.label }}</span>
    </div>
  </div>
</template>
