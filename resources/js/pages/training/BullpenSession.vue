<script setup>
import { useRoute } from 'vue-router'
import { useTrainingStore } from '@/store/training'
import Bullpen from './Bullpen.vue'
import BullpenScoring from './BullpenScoring.vue'
const route = useRoute(), training = useTrainingStore()
</script>
<template><Bullpen v-if="!route.query.session && training.trainingActive.sort != null" /><BullpenScoring v-else /></template>
