<script setup>
  import { computed } from 'vue'
  import ArrowDownIcon from "../icons/ArrowDownIcon.vue";

  let selectUid = 0

  const props = defineProps({
    modelValue: [String, Number, Boolean, Object],
    id: {
      type: String,
      required: false,
      default: ''
    },
    name: {
      type: String,
      required: false,
      default: ''
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
    options: {
      type: Object,
      required: true
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

  const fallbackId = `select-field-${++selectUid}`
  const selectId = computed(() => props.id || fallbackId)
  const selectName = computed(() => props.name || selectId.value)


  const inputClass = [
    'bg-white border border-fungo-darkblue text-fungo-darkblue rounded-[5px]',
    props.inputClasses
  ];
  </script>

<template>
  <slot>
  <div class="relative w-full">
    <select :id="selectId" :name="selectName" class="bg-white h-10 appearance-none bg-none w-full" :class="inputClass" v-model="value" style="z-index: 9">
      <option class="text-fungo-darkblue" v-for="(state, index) in options" :value="index">{{ state }}</option>
    </select>
    <div class="arrow-position"> <ArrowDownIcon color="26364D"/> </div>

  </div>
  </slot>
</template>

<style scoped>
.arrow-position{
  z-index: 0;
  position: absolute;
  top: 0;
  right: 0;
}
</style>
