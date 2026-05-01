<script setup>

const props = defineProps({
  color: {
    type: String,
    required: false,
    default: '000',
  },
  classes: {
    type: String,
    required: false,
    default: ''
  }
})

const changeColor = {
  'fill': '#'+props.color,
  'stroke': '#'+props.color,
  'fill-opacity': 1,
}
</script>
<template>
  <slot>
      <div>
    <svg :style="changeColor" :class="props.classes" width="40" height="40" viewBox="0 0 40 40" fill="none"
         xmlns="http://www.w3.org/2000/svg"><path
      d="M13.819 27.7995l7.5093-7.5257-7.5093-7.5257 2.3118-2.3118 9.8375 9.8375-9.8375 9.8375-2.3118-2.3118z"
      /></svg>
      </div>
  </slot>
</template>
<style scoped>

</style>
