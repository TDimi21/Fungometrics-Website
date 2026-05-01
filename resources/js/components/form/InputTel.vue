<script setup>
import { computed } from 'vue'


const props = defineProps({
  modelValue: [String, Number, Boolean, Object],
  placeholder: {
    type: String,
    required: false
  },
  inputClasses: {
    type: String,
    required: false,
  },
  transparent: {
    type: Boolean,
    required: false,
    default: false
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
  props.inputClasses
];

function filterKey (e) {
  if(e.keyCode == 8) {
    return true;
  } else if(e.keyCode < 48 || e.keyCode > 57) {
    e.preventDefault();
  }
}
</script>

<template>
  <slot>
  <div class="w-full relative">
    <div
      class="absolute left-0 bg-fungo-lightblue flex items-center justify-center w-[18%] h-[2.38em] text-fungo-darkblue font-fungo-poppins input-tel-decorator">
      + 1
    </div>
    <input
        class="h-10 rounded appearance-none w-[100%] float-right input-tel-text"
        :class="inputClass"
        type="number"
        v-model="value"
        @keydown="filterKey"
        :placeholder="props.placeholder"
        :required="props.required">
  </div>
  </slot>
</template>
<style scoped>
 .input-tel-decorator{
   position: absolute;
   left: 1px;
   top: 1px;
   border: 1px transparent;
   border-right: 1px solid #000;
   border-top-left-radius: 4px;
   border-bottom-left-radius: 4px;
 }

 .input-tel-text{
   /* padding-left: 5em; */
   @apply pl-12 lg:pl-[5.5rem];
 }

 input[type="number"]::-webkit-inner-spin-button,
  input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }

  input[type="number"] {
    -moz-appearance: textfield;
  }
</style>
