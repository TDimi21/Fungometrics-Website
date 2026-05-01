<script setup>
import { TableHeadItem, TableBodyItem } from ".";
const props = defineProps({
  header: Array,
  items: [Array, Object],
  isLoading: {
    type: Boolean,
    required: false,
    default: false
  }
});
</script>

<template>
  <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
    <table class="w-full border-separate space-y-6 text-fungo-darkblue">
      <thead
        class="bg-fungo-lightblue"
      >
        <tr class="divide-x divide-[#000]">
          <TableHeadItem
            v-for="(item, index) in props.header"
            :key="item.title + index"
            :item="item"
            @sortable="$emit('sortable', item)"
          />
        </tr>
      </thead>
      <tbody>
        <tr v-if="props.isLoading">
          <td colspan="4" class="w-full text-center py-5">
            <div class="animate-pulse grid grid-cols-4 gap-x-2">
              <div class="h-3 bg-slate-300 rounded-lg"></div>
              <div class="h-3 bg-slate-300 rounded-lg"></div>
              <div class="h-3 bg-slate-300 rounded-lg"></div>
              <div class="h-3 bg-slate-300 rounded-lg"></div>
            </div>
          </td>
        </tr>
        <tr v-else-if="props.items == null || props.items.length <= 0">
          <td colspan="4" class="w-full text-center py-5">
            <p>No Data</p>
          </td>
        </tr>
        <tr
          v-else
          class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative"
          v-for="(item, index) in props.items"
        >
          
          <template v-for="(itemHeader, indexHeader) in props.header" :key="indexHeader">
            <TableBodyItem :item="itemHeader.value.split('.').reduce((acc, part) => acc && acc[part], item)" :main="itemHeader.main">
              <slot :name="itemHeader.slot" v-bind="{item, index}">

              </slot>
            </TableBodyItem>
          </template>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
table{
  border-spacing: 0 10px;
}
table tbody tr td {
  @apply text-center py-4 px-1 2xl:px-5;
}

table tbody tr::after{
  content: '';
  position: absolute;
  left: -1px;
  top: 0;
  height: 100%;
  width: 3px;
  background-color: #ADE8F4;
}
table tbody tr:nth-child(even)::after{
  background-color: #DADADA;
}
</style>