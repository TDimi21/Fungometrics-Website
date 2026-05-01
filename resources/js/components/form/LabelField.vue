<script setup>
const props = defineProps({
  text: {
    type: String,
    required: true,
    default:''
  },
  required: {
    type: Boolean,
    required: false,
    default: false,
  },
  classes: {
    type: [String, Object],
    required: false
  },
  labelPosition: {
    type: String,
    required: false,
    default: 'top' // posible: top, base, base-inverse
  }
});

const labelClass = {
  'flex-col': props.labelPosition === "top",
  'gap-5 items-center': props.labelPosition === "base",
  'gap-5 items-center flex-row-reverse': props.labelPosition === "base-inverse",
  ...props.classes
}

</script>

<template>
  <slot>
  <label
    class="text-lg flex"
    :class="labelClass">
    <div>
      {{ props.text }} <span v-if="props.required" class="text-fungo-red">*</span>
    </div>
    <slot />
  </label>
  </slot>
</template>
