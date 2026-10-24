<script setup>
import { useRoute } from 'vue-router'
import { useTrainingStore } from '@/store/training'
import TrainingCage from './TrainingCage.vue'
import CageScoring from './CageScoring.vue'
const props = defineProps(['cageHeight', 'lengthCage', 'widthCage'])
const route = useRoute(), training = useTrainingStore()
</script>
<template><TrainingCage v-if="!route.query.session && training.trainingActive.distance_travel != null" v-bind="props" /><CageScoring v-else /></template>
