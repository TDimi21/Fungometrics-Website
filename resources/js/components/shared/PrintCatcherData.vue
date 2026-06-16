<script setup>
import { dataCoordinates } from "@/utils/dataCoordinatesCatcher"

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
  <!-- aspect-ratio matches the catcher.png image (598×740) so the grid covers it exactly -->
  <div class="zone-catcher grid grid-cols-[repeat(60,1fr)] grid-rows-[repeat(60,1fr)] w-full">

    <div
      v-if="props.isInBatting"
      v-for="cell in dataCoordinates"
      :id="cell.point"
      class="cell"
      :class="{
        'ballhit left'   : ballCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.second),
        'ballhit middle' : ballCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.third),
        'ballhit right'  : ballCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.fourth)
      }"
    />

    <div
      v-else
      class="cell"
      v-for="(points, point) in dataCoordinates"
      :id="points.point"
      :key="point"
      :class="{
        'ballhit'  : props.ballCoordinates.includes(points.point),
        'left'     : points.position.includes('L') && props.ballCoordinates.includes(points.point),
        'middle'   : points.position.includes('C') && props.ballCoordinates.includes(points.point),
        'right'    : points.position.includes('R') && props.ballCoordinates.includes(points.point)
      }"
    />

  </div>
</template>

<style scoped>
.zone-catcher {
  background-image: url("../../assets/img/training/catcher.png");
  background-repeat: no-repeat;
  background-size: 100% 100%;
  background-position: center;
  /* image is 598×740 — aspect-ratio locks height proportional to width */
  aspect-ratio: 598 / 740;
}

/* cells fill their grid track — no explicit size needed */
.cell {
  width: 100%;
  height: 100%;
  cursor: pointer;
}

.ballhit {
  width: 100%;
  height: 100%;
  position: relative;
  transform: scale(4);
  background-color: transparent;
  background-repeat: no-repeat;
  background-size: contain;
  background-position: center;
  z-index: 1;
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
