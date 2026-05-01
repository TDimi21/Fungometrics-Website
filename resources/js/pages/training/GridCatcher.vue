<script setup>
import {dataCoordinates} from "../../utils/dataCoordinatesCatcher";
import {computed, watch} from "vue";


const props = defineProps({
  modelValue: [String, Number, Boolean, Object],
  pitchMark: {
    type: Object,
    require: false
  }
})

const emit = defineEmits(['update:modelValue'])

const field = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  }
})

const coordinateSet = (item,e)=>{

  let balls =  document.querySelectorAll('.cell.ballhit')
  if(balls !== undefined){
    [].forEach.call(balls, function(el) {
      el.classList.remove("ballhit");
    });
  }
  e.target.classList.add('ballhit')
  if(localStorage.getItem('pitch')===undefined){
    localStorage.setItem('pitch',JSON.stringify(item))
  }else{
    localStorage.setItem('pitch',JSON.stringify(item))
  }
  console.log(item);
  emit('update:modelValue', item)
}


watch(
    props.pitchMark, (newValue, afterValue) => {
      if(afterValue.point != null){
        setFielMarkEdit(newValue, afterValue)
      }
    }
  )

const setFielMarkEdit = (value, value2) => {
  emit('update:modelValue', value)
  let ball = document.querySelector('div[id="pointGet'+ value.point + '"]')
  ball.classList.add('ballhit')
}
</script>
<template>
  <div class="flex grid grid-cols-[repeat(60,1fr)]  zone-catcher">

    <div class="cell" v-for="n in dataCoordinates" @click="coordinateSet(n,$event)"
         :id="'pointGet'+n.point"
         :key="n.point"
         :class="n.strike.status?'strike-zone':''"
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

.strike-zone {
  background-color: #2F855A90;
  @apply w-[0.35em] h-[0.37em]  xl:w-[0.31em] xl:h-[0.41em] ;
}

.zone-catcher {
  background-image: url("../../assets/img/training/catcher.png");
  background-repeat: no-repeat;
  background-size: 100% 100%;
  background-position: center;
}


.ballhit{
  @apply w-[0.1.5em] sm:w-[0.20em] sm:h-[0.25em] md:w-[0.26em] md:h-[0.35em];
  position: relative;
  transform: scale(6);
  background-color: transparent;
  background-image: url("../../assets/img/training/balltraining.svg");
  background-repeat: no-repeat;
  background-size: 3px 3px;
  background-position-x: center;
  background-position-y: 1px;
  z-index: auto;
}


/*@media only screen and (min-width: 768px) {*/
/*  .ballhit {*/
/*    @apply w-[0.1.5em] h-[0.13em] sm:w-[0.20em] sm:h-[0.25em] md:w-[0.26em] md:h-[0.35em];*/
/*    position: relative;*/

/*    transform: scale(6);*/
/*    background-color: transparent;*/
/*    background-image: url("../../assets/img/training/balltraining.svg");*/
/*    background-repeat: no-repeat;*/
/*    background-size: 3px 3px;*/
/*    background-position-x: center;*/
/*    background-position-y: 1px;*/
/*    z-index: auto;*/
/*  }*/
/*}*/


</style>
