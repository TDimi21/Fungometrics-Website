<script setup>
import {defineProps} from 'vue';
import LongToss from "./LongToss.vue";
import VelocityScoring from "./VelocityScoring.vue";
import LongTossScoring from "./LongTossScoring.vue";
import { useTrainingStore } from "@/store/training";
import { useRoute } from "vue-router";
import ExitVelocity from "./ExitVelocity.vue";
import WeigthBall from "./WeigthBall.vue";

const training = useTrainingStore();
const route = useRoute();

defineProps({
  mode: {
    type: String
  }
});
</script>
<template>
  <div v-if="mode == 'WB'">
    <WeigthBall v-if="!route.query.session && training.trainingActive.set != null" />
    <VelocityScoring v-else mode="WB" key="WB" />
  </div>
  <div v-else-if="mode == 'EV'">
    <ExitVelocity v-if="!route.query.session && training.trainingActive.set != null" />
    <VelocityScoring v-else mode="EV" key="EV" />
  </div>
  <div v-else>
    <LongToss v-if="!route.query.session && training.trainingActive.set != null" />
    <LongTossScoring v-else />
  </div>
</template>
