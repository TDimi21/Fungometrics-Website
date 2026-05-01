<script setup>
import {defineProps} from 'vue';
import Layout from "../../layout/Layout.vue"
import LongToss from "./LongToss.vue";
import ExitVelocity from "./ExitVelocity.vue";
import WeigthBall from "./WeigthBall.vue";

const props = defineProps({
  mode: {
    type: String
  }
});
</script>
<template>
  <div v-if="mode == 'WB'">
    <WeigthBall></WeigthBall>
  </div>
  <div v-else-if="mode == 'EV'">
    <ExitVelocity></ExitVelocity>
  </div>
  <div v-else>
    <LongToss></LongToss>
  </div>
</template>
