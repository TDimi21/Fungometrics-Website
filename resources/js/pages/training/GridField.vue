<script setup>
import {dataCoordinates} from "../../utils/dataCoordinatesField";
import {computed, watch} from "vue";
import { toast } from "@/utils/AlertPlugin"


const props = defineProps({
  modelValue: [String, Number, Boolean, Object],
  pitchParent:'',
  pitchMark: {
    type: Object,
    require: false
  }
})

const emit = defineEmits(['update:modelValue'])

const pitch = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  }
})

watch(
    props.pitchMark, (newValue, afterValue) => {
      if(afterValue.point != null){
        setFielMarkEdit(newValue, afterValue)
      }
    }
  )

const setFielMarkEdit = (value, value2) => {
  emit('update:modelValue', value)
  let ball = document.querySelector('div[id="'+ value.point + '"]')
  if ( ball !== null ) ball.classList.add('ballhit-field')
}

const coordinateSet = (item, e) => {
  // console.log(localStorage.getItem('pitch'))
  // if(localStorage.getItem('pitch') === null ){
  //   toast.fire({
  //     icon: 'warning',
  //     title: 'set previous data',
  //     text: 'To set position in field first, set point in grid of catcher',
  //   })
  // }else{
    let balls = document.querySelectorAll('.cell.ballhit-field')
    if (balls !== undefined) {
      [].forEach.call(balls, function (el) {
        el.classList.remove("ballhit-field");
      });
    }
    e.target.classList.add('ballhit-field');
    emit('update:modelValue', item)
  // }

}
</script>
<template>
  <div class=" grid grid-cols-[repeat(80,1fr)] zone-field">
    <div v-for="cell in dataCoordinates"
         :id="cell.point"
         class="cell"
         @click="coordinateSet(cell,$event)"

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
  @apply w-[0.265em] h-[0.26em] 2xl:h-[0.31em] 2xl:w-[0.365em]  xl:h-[0.21em]  ;
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
  background-image: url("../../assets/img/training/balltraining.svg");
  background-repeat: no-repeat;
  z-index: auto;
}


</style>
