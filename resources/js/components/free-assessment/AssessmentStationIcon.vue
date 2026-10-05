<script setup>
defineProps({ station: { type: String, required: true } })
</script>
<template>
  <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <template v-if="station === 'exit_velocity'"><path d="m7 25 7-9L24 4c3-3 7 1 4 4L17 20l-9 6M5 25l3 3M8 24l2 2"/><path d="m15 16 3 3"/></template>
    <template v-else-if="station === 'pitching_velocity'"><circle cx="16" cy="16" r="12"/><path d="M10 6c5 5 5 15 0 20M22 6c-5 5-5 15 0 20M10 10l3-1M11 15l3-1M11 20l3 1M22 10l-3-1M21 15l-3-1M21 20l-3 1"/></template>
    <template v-else-if="station === 'sprint_10yd' || station === 'broad_jump'"><circle cx="21" cy="5" r="2.5" fill="currentColor" stroke="none"/><path d="m13 11 5-3 4 6 5 1M18 9l-4 10-6 4-3 5M14 18l7 3-2 7M13 11l-3 5-5-1"/></template>
    <template v-else-if="station === 'shuttle_5_10_5'"><path d="m4 24 3-9 3 9ZM22 24l3-9 3 9ZM13 14l3-9 3 9ZM2 26h10M20 26h10M11 16h10M10 21h12M12 19l-2 2 2 2M20 19l2 2-2 2"/></template>
    <template v-else-if="station === 'grip_strength'"><path d="M10 15V8a2 2 0 0 1 4 0v8-11a2 2 0 0 1 4 0v11-9a2 2 0 0 1 4 0v10-5a2 2 0 0 1 4 0v9c0 6-4 9-10 9-4 0-7-3-9-7l-3-6c-1-3 2-4 4-2l2 3"/></template>
    <template v-else><path d="M3 13v6M7 9v14M11 6v20M21 6v20M25 9v14M29 13v6M11 16h10M3 16h8M21 16h8"/></template>
  </svg>
</template>
<style scoped>svg{width:100%;height:100%}</style>
