<script setup>
import { ref, onMounted } from 'vue'
import { dataCoordinates } from "@/utils/dataCoordinatesField";

const props = defineProps({
  fieldCoordinates: {
    type: Object
  },
  typeOfCondition: { /* posible values [qtyContact, trajectory or ...] */
    type: String,
    required: true
  }
})

const conditionsOfColors = ref({ first: '', second: '', third: '', fourth: '' })

const defineConditionColor = () => {
  switch (props.typeOfCondition) {
    case 'qtyContact':
      conditionsOfColors.value = { first: 'MF', second: 'W', third: 'A', fourth: 'H' }
      break;

    case 'trajectory':
      conditionsOfColors.value = { first: 'SM', second: 'F', third: 'LD', fourth: 'GB', fifty: 'FB' }
      break;
  }
}

onMounted(() => {
  defineConditionColor()
})
</script>

<template>
  <div class=" grid grid-cols-[repeat(80,1fr)] zone-field">
    <div v-for="cell in dataCoordinates"
      :id="cell.point"
      class="cell"
      :class="{
        'ballhit-field white' : fieldCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.first),
        'ballhit-field green' : fieldCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.second),
        'ballhit-field yellow' : fieldCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.third),
        'ballhit-field blue' : fieldCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.fourth),
        'ballhit-field purple' : fieldCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.fifty)
      }"
    >
    </div>
  </div>
</template>
<script>
export default {
  name: 'GridField'
}
</script>
<style scoped>
.zone-field {
  background-image: url("../../assets/img/training/field.png");
  background-repeat: no-repeat;
  background-size: 100% 100%;
  background-position: center;
}

.cell {
  @apply w-[0.265em] h-[0.26em] 2xl:h-[0.31em] 2xl:w-[0.365em]  xl:h-[0.21em] xl:w-[0.365em] ;
  cursor: pointer;
}

.foul-zone {
  background-color: #852F4FAF;
  @apply w-[0.265em] h-[0.26em] ;
}

.ballhit-field {
  @apply w-[0.265em] h-[0.26em] scale-[3.5]
  bg-no-repeat bg-cover xl:bg-contain;
  position: relative;
  background-color: transparent;
  background-repeat: no-repeat;
  z-index: auto;
}
.ballhit-field.white {
  background-image: url("../../assets/img/login/assteslogin/ballbutton.svg");
}
.ballhit-field.green {
  background-image: url("../../assets/img/training/balltraining-green.svg");
}
.ballhit-field.yellow {
  background-image: url("../../assets/img/training/balltraining.svg");
}
.ballhit-field.blue {
  background-image: url("../../assets/img/training/balltraining-blue.svg");
}
.ballhit-field.purple {
  background-image: url("../../assets/img/training/ball-purple.svg");
}
</style>
