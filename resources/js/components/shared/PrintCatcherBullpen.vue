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
  <div class="grid grid-cols-[repeat(60,1fr)]  zone-catcher">

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
  @apply w-[0.265em] h-[0.26em] scale-[3.5]
  bg-no-repeat bg-cover xl:bg-contain;
  position: relative;
  background-color: transparent;
  background-repeat: no-repeat;
  z-index: auto;
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
