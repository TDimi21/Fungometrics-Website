<script setup>
import { ref, onMounted } from 'vue'
import { dataCoordinates } from "@/utils/dataCoordinatesField"

const props = defineProps({
  fieldCoordinates: {
    type: Object
  },
  typeOfCondition: {
    type: String,
    required: true
  }
})

const conditionsOfColors = ref({ first: '', second: '', third: '', fourth: '' })

const defineConditionColor = () => {
  switch (props.typeOfCondition) {
    case 'qtyContact':
      conditionsOfColors.value = { first: 'MF', second: 'W', third: 'A', fourth: 'H' }
      break
    case 'trajectory':
      conditionsOfColors.value = { first: 'SM', second: 'F', third: 'LD', fourth: 'GB', fifty: 'FB' }
      break
  }
}

onMounted(defineConditionColor)
</script>

<template>
  <!-- aspect-ratio matches fieldbatting.png (439×282) so the grid covers it exactly -->
  <div class="zone-field grid grid-cols-[repeat(80,1fr)] grid-rows-[repeat(81,1fr)] w-full">
    <div
      v-for="cell in dataCoordinates"
      :id="cell.point"
      class="cell"
      :class="{
        'ballhit-field white'  : fieldCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.first),
        'ballhit-field green'  : fieldCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.second),
        'ballhit-field yellow' : fieldCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.third),
        'ballhit-field blue'   : fieldCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.fourth),
        'ballhit-field purple' : fieldCoordinates.find(({ point, feature }) => point == cell.point && feature == conditionsOfColors.fifty)
      }"
    />
  </div>
</template>

<script>
export default { name: 'GridField' }
</script>

<style scoped>
.zone-field {
  background-image: url("../../assets/img/training/fieldbatting.png");
  background-repeat: no-repeat;
  background-size: 100% 100%;
  background-position: center;
  /* image is 439×282 — aspect-ratio locks height proportional to width */
  aspect-ratio: 439 / 282;
}

.cell {
  width: 100%;
  height: 100%;
  cursor: pointer;
}

.ballhit-field {
  width: 100%;
  height: 100%;
  transform: scale(3);
  background-color: transparent;
  background-repeat: no-repeat;
  background-size: contain;
  background-position: center;
  position: relative;
  z-index: 1;
}
.ballhit-field.white  { background-image: url("../../assets/img/login/assteslogin/ballbutton.svg"); }
.ballhit-field.green  { background-image: url("../../assets/img/training/balltraining-green.svg"); }
.ballhit-field.yellow { background-image: url("../../assets/img/training/balltraining.svg"); }
.ballhit-field.blue   { background-image: url("../../assets/img/training/balltraining-blue.svg"); }
.ballhit-field.purple { background-image: url("../../assets/img/training/ball-purple.svg"); }
</style>
