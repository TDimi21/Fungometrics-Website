<script setup>
  const props = defineProps({
    item: [Object, String, Number, Boolean],
  })
</script>

<template>
  <th
    scope="row"
    class="py-4 px-3 max-w-[100px] text-ellipsis overflow-hidden text-sm"
    :class="{ 'font-medium text-gray-900 whitespace-nowrap': !props.main }"
  >
  <slot>
    {{ props.item }}
  </slot>
  </th>
</template>
