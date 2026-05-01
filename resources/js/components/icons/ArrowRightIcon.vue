<script setup>

const props = defineProps({
  color: {
    type: String,
    required: false,
    default: '000',
  },
  h: {
    type: String,
    required: false,
    default: '40',
  },
  w: {
    type: String,
    required: false,
    default: '40',
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
        <svg :style="changeColor" :width="w" :height="h" viewBox="0 0 40 40" fill="none"
             xmlns="http://www.w3.org/2000/svg"><path
          d="M19.7913 6.65442l-2.3119 2.31182 9.1489 9.16526H6.67456v3.2792H26.6283l-9.1489 9.1653 2.3119 2.3118 13.1166-13.1167L19.7913 6.65442z"/></svg>
      </div>
  </slot>
</template>
<style scoped>

</style>
