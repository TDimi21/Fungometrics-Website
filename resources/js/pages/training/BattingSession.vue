<script setup>
import { useRoute } from 'vue-router'
import { useTrainingStore } from '@/store/training'
import Batting from './Batting.vue'
import BattingScoring from './BattingScoring.vue'
const route = useRoute(), training = useTrainingStore()
</script>
<template><Batting v-if="!route.query.session && training.trainingActive.sort != null" /><BattingScoring v-else /></template>
