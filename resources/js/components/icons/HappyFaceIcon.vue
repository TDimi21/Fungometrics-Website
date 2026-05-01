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
      <svg :style="changeColor" :width="w" :height="h" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path fill-rule="evenodd" clip-rule="evenodd" d="M5 30C5 16.2 16.175 5 29.975 5C43.8 5 55 16.2 55 30C55 43.8 43.8 55 29.975 55C16.175 55 5 43.8 5 30ZM10 30C10 41.05 18.95 50 30 50C41.05 50 50 41.05 50 30C50 18.95 41.05 10 30 10C18.95 10 10 18.95 10 30ZM38.75 27.5C40.825 27.5 42.5 25.825 42.5 23.75C42.5 21.675 40.825 20 38.75 20C36.675 20 35 21.675 35 23.75C35 25.825 36.675 27.5 38.75 27.5ZM25 23.75C25 25.825 23.325 27.5 21.25 27.5C19.175 27.5 17.5 25.825 17.5 23.75C17.5 21.675 19.175 20 21.25 20C23.325 20 25 21.675 25 23.75Z" fill="#082247"/>
        <path d="M23 33C23 34.8565 23.7375 36.637 25.0503 37.9497C26.363 39.2625 28.1435 40 30 40C31.8565 40 33.637 39.2625 34.9497 37.9497C36.2625 36.637 37 34.8565 37 33" stroke="#082247" stroke-width="4" stroke-linecap="round"/>
      </svg>
    </div>
  </slot>
</template>
