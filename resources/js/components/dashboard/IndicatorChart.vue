<script setup>
const props = defineProps(['labelTitle', 'labelValue', 'color'])
</script>

<template>
  <div class="flex flex-col">
    <div class="flex justify-between items-center space-y-3">
      <span>{{ props.labelTitle }}</span>
      <span>{{ props.labelValue }}%</span>
    </div>
    <progress class="progress-bar" max="100" :value="props.labelValue"></progress>
  </div>
</template>

<style scoped>
progress::-webkit-progress-bar, progress {
  background-color: #DBDFF1;
  width: 100%;
  height: 5px;
}

progress::-webkit-progress-value {
  background: v-bind('props.color');
}
progress::-moz-progress-bar {
  background: v-bind('props.color');
}
progress {
  background: v-bind('props.color');
}
</style>