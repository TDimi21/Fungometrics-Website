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
    <svg :style="changeColor" :width="w" :height="h" viewBox="0 0 41 40" fill="none"
         xmlns="http://www.w3.org/2000/svg"><path
      d="M33.7092 18.1315H13.7554l9.1653-9.16526-2.3282-2.31182L7.47583 19.7711 20.5925 32.8878l2.3118-2.3118-9.1489-9.1653h19.9538v-3.2792z"/></svg>
  </slot>
</template>
