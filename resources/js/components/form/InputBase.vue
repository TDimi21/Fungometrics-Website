<script setup>
import { computed } from 'vue'


const props = defineProps({
  modelValue: [String, Number, Boolean, Object],
  inputType: {
    type: String,
    required: false,
    default: 'text'
  },
  placeholder: {
    type: String,
    required: false
  },
  required: {
    type: String,
    required: false,
    default: 'text'
  },
  inputClasses: {
    type: String,
    required: false,
  },
  transparent: {
    type: Boolean,
    required: false,
    default: false
  },
  decorator:{
    type: Boolean,
    required: false,
    default: false
  },
  enableInput:{
    type: Boolean,
    required: false,
    default: false
  },
  maxValue:{
    type: String,
    required: false,
    default: ""
  },
  minValue:{
    type: String,
    required: false,
    default: ""
  }
});

const emit = defineEmits(['update:modelValue']);

const value = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  }
})


const inputClass = [
  props.transparent ? 'bg-transparent border border-fungo-lightblue' : 'bg-white border border-fungo-darkblue text-fungo-darkblue',
  props.inputClasses,
  props.decorator ?'pr-[3.5em]': ''
];
</script>

<template>
  <input
      class="h-10 rounded appearance-none "
      :class="inputClass"
      :type="props.inputType"
      v-model="value"
      :disabled="props.enableInput"
      :placeholder="props.placeholder"
      :required="props.required"
      autocomplete="off"
      :min="minValue"
      :max="maxValue">
</template>

<style scoped>
  input[type=number]::-webkit-inner-spin-button,
  input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }

  input[type=number] { -moz-appearance:textfield; }

</style>
