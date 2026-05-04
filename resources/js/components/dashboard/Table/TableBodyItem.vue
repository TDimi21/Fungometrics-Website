<script setup>
  const props = defineProps({
    item: [Object, String, Number, Boolean],
  })
</script>

<template>
  <th
    scope="row"
    class="py-4 px-3 max-w-[100px] text-ellipsis overflow-hidden text-sm text-white/80"
    :class="{ 'font-medium whitespace-nowrap': !props.main }"
  >
  <slot>
    {{ props.item }}
  </slot>
  </th>
</template>
