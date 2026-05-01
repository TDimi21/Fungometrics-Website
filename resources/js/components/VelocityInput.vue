<script setup>
import {computed} from "vue";
import { toast } from "@/utils/AlertPlugin"

const props = defineProps({
  modelValue: [String, Number, Boolean, Object],
  maxVelocity: {
    Type: Number,
    default: 130
  },
  labelVelocity: {
    Type: String,
    default: "Max velocity 130 mph"
  }
})
const emit = defineEmits(['update:modelValue','eventChange'])
const valueVelocity = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
    emit('eventChange', value)
  }
})
const clearCalc = ()=>{
  valueVelocity.value = 0
}
const removeLast = ()=>{
  if(valueVelocity.value === 0){
    valueVelocity.value = 0
  }else {
    valueVelocity.value = parseInt(valueVelocity.value /10);
  }
}
const addValue = (event)=>{
  emit('update:modelValue', event.target.innerHTML)
  if(valueVelocity.value === 0){
    valueVelocity.value = event.target.innerHTML;
  }else{
    let maxVelocity = valueVelocity.value * 10 + parseInt(event.target.innerHTML);
    if(maxVelocity > props.maxVelocity){
      toast.fire({
        icon: 'warning',
        title: 'Validation',
        text: 'You set value over max speed',
      })
      valueVelocity.value = valueVelocity.value
    }else{
      valueVelocity.value = (valueVelocity.value *10) + parseInt(event.target.innerHTML)
    }
  }
}
</script>
<template>
  <div class="grid grid-cols-1">
    <div class="w-fit mx-auto">
      <input type="text"  v-model="valueVelocity"
             class=" border border-fungo-darkblue rounded max-w-[200px] text-[32px] md:text-[18px]">
    </div>
    <div class="text-[20px] md:text-[16px] font-fungo-700 text-center text-fungo-blue2 my-2 mx-auto">{{ labelVelocity }}</div>
    <div
        class="grid bg-fungo-gray2 grid-cols-3 border rounded justify-items-center gap-1 mx-auto p-3 md:p-2">
      <button class="calc-button" @click="addValue($event)">1</button>
      <button class="calc-button" @click="addValue($event)">2</button>
      <button class="calc-button" @click="addValue($event)">3</button>
      <button class="calc-button" @click="addValue($event)">4</button>
      <button class="calc-button" @click="addValue($event)">5</button>
      <button class="calc-button" @click="addValue($event)">6</button>
      <button class="calc-button" @click="addValue($event)">7</button>
      <button class="calc-button" @click="addValue($event)">8</button>
      <button class="calc-button" @click="addValue($event)">9</button>
      <button class="calc-button" @click="removeLast()">c</button>
      <button class="calc-button" @click="addValue($event)">0</button>
      <button class="calc-button" @click="clearCalc()">x</button>
    </div>
  </div>
</template>
<style scoped>
.ct * {
  border: 1px solid #1a73e8;
}
.calc-button {
  @apply border rounded w-[64px] h-[64px] md:h-[28px] md:w-[60px] bg-white text-[32px] md:text-[20px];
}
</style>
