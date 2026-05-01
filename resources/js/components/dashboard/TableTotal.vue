<script setup>
import { ref, onMounted } from 'vue'
import { TableCancel, TableEdit, TableHappyFace } from '@/components/icons'
import { Modal } from '@/components/shared'

  defineProps({
    tableData: {
      type: Object,
      required: true
    },
  })
  console.log("Se muestra");
  const tableHeadings = ref([
    "WEIGHT", "BENCH PRESS", "FR. SQUAT", "BACK SQUAT", "DEADLIFT", "CLEAN", "40", "60"
  ])
</script>

<template>
  <section class="px-[10%] md:px-[5%] overflow-x-auto">
    <table class="w-full border-separate space-y-6 text-fungo-darkblue text-center">

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
        <tr v-if="!tableData.length > 0">
          <td colspan="7" class="text-fungo-darkblue text-3xl text-center">No found data</td>
        </tr>
        <tr
          v-else
          v-for="(item, index) in tableData"
          :key="index"
          class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative font-fungo-700"
        >
        <td class="">
            {{ item.body_weight ?? "" }}
          </td>
          <td class="">
            {{ item.bench_press ?? "" }}
          </td>
          <td class="">
            {{ item.front_squat ?? "" }}
          </td>
          <td class="">
            {{ item.back_squat ?? "" }}
          </td>
          <td class="">
            {{ item.dead_lift ?? "" }}
          </td>
          <td class="">
            {{ item.power_clean ?? "" }}
          </td>
          <td class="">
            {{ item.yd_40_dash ?? "" }}
          </td>
          <td class="">
            {{ item.yd_60_dash ?? "" }}
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
