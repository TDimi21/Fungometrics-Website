<script setup>
import { computed } from 'vue'
import { LabelField, InputBase } from '.';

// See InputBase Props for all variants
const props = defineProps({
  label: String,
  required: {
    type: Boolean,
    required: false,
    default: false
  },
  modelValue: [String, Number, Boolean, Object],
  labelClasses: {
    type: String,
    required: false,
  },
  transparent: {
    type: Boolean,
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
</script>

<template>
  <slot>
  <LabelField :text="props.label" :required="props.required" :classes="props.labelClasses">
    <InputBase v-model="value" v-bind="$attrs" :transparent="transparent"/>
  </LabelField>
  </slot>
</template>
