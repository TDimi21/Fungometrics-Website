<script setup>
import {dataCoordinates} from "@/utils/dataCoordinatesCatcher";

const props = defineProps({
  ballCoordinates: {
    type: Object
  },
  isInBatting: {
    type: Boolean,
    required: false,
    default: false
  }
})

const conditionsOfColors = { second: 'LF', third: 'CF', fourth: 'RF' }

</script>
<template>
  <div class="grid grid-cols-[repeat(60,1fr)]  zone-catcher">

    <div
      v-if="props.isInBatting"
      v-for="cell in dataCoordinates"

      :id="cell.point"
        class="cell"
        :class="{
          'ballhit left' : ballCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.second),
          'ballhit middle' : ballCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.third),
          'ballhit right' : ballCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.fourth)
        }"
    >
      
    </div>

    <div
      v-else
      class="cell"
      v-for="(points, point) in dataCoordinates"
      :id="points.point"
      :key="point"
      :class="{
        'ballhit' :  props.ballCoordinates.includes(points.point),
        'left' : points.position.includes('L') && props.ballCoordinates.includes(points.point),
        'middle' : points.position.includes('C') && props.ballCoordinates.includes(points.point),
        'right' : points.position.includes('R') && props.ballCoordinates.includes(points.point)
      }"
    >

    </div>
  </div>
</template>
<script>

</script>
<style scoped>

.cell {
  @apply w-[0.35em] h-[0.37em]  xl:w-[0.31em] xl:h-[0.41em] ;
  cursor: pointer;
}

.zone-catcher {
  background-image: url("../../assets/img/training/catcher.png");
  background-repeat: no-repeat;
  background-size: 100% 100%;
  background-position: center;
  height: 600px;
}


.ballhit{
  @apply w-[0.1.5em] sm:w-[0.20em] sm:h-[0.25em] md:w-[0.26em] md:h-[0.35em];
  position: relative;
  transform: scale(6);
  background-color: transparent;
  background-repeat: no-repeat;
  background-size: 3px 3px;
  background-position-x: center;
  background-position-y: 1px;
  z-index: auto;
}
.ballhit.left {
  background-image: url("../../assets/img/training/balltraining-green.svg");
}
.ballhit.middle {
  background-image: url("../../assets/img/training/balltraining.svg");
}
.ballhit.right {
  background-image: url("../../assets/img/training/balltraining-blue.svg");
}
</style>
