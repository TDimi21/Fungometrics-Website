<script setup>
import CreateTraining from './CreateTraining.vue'
import CreateBattingBullpen from './CreateBattingBullpen.vue'
defineProps({ slug: String })
</script>
<template><CreateBattingBullpen v-if="['batting', 'bullpen'].includes(slug)" :key="slug" /><CreateTraining v-else :slug="slug" /></template>
