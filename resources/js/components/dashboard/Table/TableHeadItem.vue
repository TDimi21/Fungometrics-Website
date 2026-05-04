<script setup>
const props = defineProps({
  item: Object,
});
</script>

<template>
  <th scope="col" class="py-3 text-xs font-black uppercase tracking-wider text-white/60">
    <div class="text-center">
      {{ props.item.title }}
    </div>
  </th>
</template>
