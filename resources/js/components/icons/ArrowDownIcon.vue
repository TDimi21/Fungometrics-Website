<script setup>
const props = defineProps({
  color: {
    type: String,
    required: false,
    default: '000',
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
    <svg :style="changeColor" width="40" height="40" viewBox="0 0 41 40" fill="none" xmlns="http://www.w3.org/2000/svg"><path
      d="M12.9666 14.1992l7.5257 7.5093 7.5257-7.5093 2.3118 2.3119-9.8375 9.8375-9.8375-9.8375 2.3118-2.3119z"
      /></svg>
    </div>
  </slot>
</template>
