<script setup>
import { ref, onMounted } from 'vue'
import { TableCancel, TableEdit, TableHappyFace } from '@/components/icons'
import { Modal } from '@/components/shared'

  const props = defineProps({
    tableData: {
      type: Object,
      required: true
    },
    isLoading: {
      type: Boolean,
      required: true
    }
  })
  const tableHeadings = ref([
    "MAX EXIT VELOCITY", "MAX DISTANCE", "MAX FAST BALL", "MAX LONG TOSS", "MAX STRIKE", "MAX WEIGHTED BALL",
  ])
  const newArray = Object.values(props.tableData.top)
</script>

<template>
  <section class="px-[10%] md:px-[5%] overflow-x-auto">
    <table class="w-full border-separate space-y-6 text-fungo-darkblue">

      <thead class="bg-fungo-lightblue text-center">
        <tr class="divide-x divide-[#000]">
          <th
            v-for="(heading, index) in tableHeadings"
            :key="index"
            class="py-3 font-fungo-500"
          >
            {{ heading }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="isLoading" class="w-full">
          <td colspan="6" class="text-fungo-darkblue text-3xl text-center">Loading data...</td>
        </tr>
        <tr v-else-if="!newArray.length > 0">
          <td colspan="6" class="text-fungo-darkblue text-3xl text-center">No found data</td>
        </tr>
        <tr
          v-else
          v-for="(i, index) in 10"
          :key="i"
          class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative font-fungo-700"
        >
          <td class="w-[200px] max-w-[200px] font-fungo-700">
              {{ newArray[0][index] ?? "-" }}
            </td>
            <td class="w-[200px] max-w-[200px] font-fungo-700">
              {{ newArray[1][index] ?? "-" }}
            </td>
            <td class="w-[200px] max-w-[200px] font-fungo-700">
              {{ newArray[2][index] ?? "-" }}
            </td>
            <td class="w-[200px] max-w-[200px] font-fungo-700">
              {{ newArray[3][index] ?? "-" }}
            </td>
            <td class="w-[200px] max-w-[200px] font-fungo-700">
              {{ newArray[4][index] ?? "-" }}
            </td>
            <td class="w-[200px] max-w-[200px] font-fungo-700">
              {{ newArray[5][index] ?? "-" }}
            </td>
        </tr>
      </tbody>
    </table>
  </section>
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

::-webkit-scrollbar {
  width: 4px;
  height: 4px;
}
::-webkit-scrollbar-button {
  width: 0px;
  height: 0px;
}
::-webkit-scrollbar-thumb {
  @apply bg-fungo-darkblue-hover rounded-md;
}

::-webkit-scrollbar-thumb:active {
  @apply bg-fungo-darkblue;
}
::-webkit-scrollbar-track {
  border: 22px solid #918383;
  @apply bg-fungo-dark-gray rounded-md;
}
::-webkit-scrollbar-corner {
  background: transparent;
}
</style>
