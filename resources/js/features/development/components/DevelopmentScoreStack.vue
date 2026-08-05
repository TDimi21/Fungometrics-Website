<script setup>
import DevelopmentScoreCard from './DevelopmentScoreCard.vue'
defineProps({ scores: { type: Array, default: () => [] } })
</script>
<template>
  <section class="score-stack" data-testid="development-scores">
    <header><p>Development Scores</p><span>Backend-governed scores · 0–100</span></header>
    <DevelopmentScoreCard v-for="score in scores" :key="score.key" :score="score" />
  </section>
</template>
<style scoped>
.score-stack{display:grid;gap:7px}.score-stack header{display:flex;justify-content:space-between;align-items:flex-end;padding:0 2px 2px;color:#fff}.score-stack header p{text-transform:uppercase;font-size:11px;font-weight:900;letter-spacing:.11em}.score-stack header span{font-size:9px;color:#708797}
</style>
