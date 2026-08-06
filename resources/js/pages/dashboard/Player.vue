<script setup>
// Re-export shim: the player home dashboard moved to
// resources/js/features/player-home/. Import the feature page directly in new
// code; this file only keeps old direct imports of pages/dashboard/Player.vue
// working.
import PlayerHomeDashboard from '@/features/player-home/pages/PlayerHomeDashboard.vue'
</script>

<template>
  <PlayerHomeDashboard />
</template>
