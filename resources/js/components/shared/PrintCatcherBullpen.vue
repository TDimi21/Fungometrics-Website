<script setup>
import { ref, onMounted } from 'vue'
import {dataCoordinates} from "@/utils/dataCoordinatesCatcher";

const props = defineProps({
  ballCoordinates: {
    type: Object
  },
  typeOfCondition: { /* posible values [qtyContact, trajectory or ...] */
    type: String,
    required: true
  }
})

const conditionsOfColors = ref({ first: '', second: '', third: '', fourth: '', fifth: '' })

const defineConditionColor = () => {
  switch (props.typeOfCondition) {
    case 'bullpenColor':
      conditionsOfColors.value = { first: 'OTHER', second: 'SL', third: 'CB', fourth: 'CH', fifth: 'FB' }
      break;

    case 'trajectory':
      conditionsOfColors.value = { first: 'FB', second: 'PF', third: 'LD', fourth: 'GB' }
      break;
  }
}

onMounted(() => {
  defineConditionColor()
})
</script>
<template>
  <div class="zone-catcher grid grid-cols-[repeat(60,1fr)] grid-rows-[repeat(60,1fr)] w-full">

    <div
      v-for="cell in dataCoordinates"
      :id="cell.point"
      class="cell"
      :class="{
        'ballhit cv' : props.ballCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.third),
        'ballhit other' : props.ballCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.first),
        'ballhit fb' : props.ballCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.fifth),
        'ballhit sl' : props.ballCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.second),
        'ballhit ch' : props.ballCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.fourth)
      }"
    >

    </div>
  </div>
</template>
<script>

</script>
<style scoped>

.zone-catcher {
  background-image: url("../../assets/img/training/catcher.png");
  background-repeat: no-repeat;
  background-size: 100% 100%;
  background-position: center;
  aspect-ratio: 598 / 740;
}

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
.ballhit.cv {
  background-image: url("../../assets/img/training/balltraining-green.svg");
}
.ballhit.other {
  background-image: url("../../assets/img/training/ball-orange.svg");
}
.ballhit.fb {
  background-image: url("../../assets/img/training/balltraining-blue.svg");
}

.ballhit.sl {
  background-image: url("../../assets/img/training/balltraining.svg");
}

.ballhit.ch {
  background-image: url("../../assets/img/training/ball-purple.svg");
}
</style>
