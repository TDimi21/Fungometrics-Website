<script setup>
import { ref } from 'vue'

const props = defineProps({
  tableData: {
    type: Object,
    required: true
  }
})
const tableHeadings = ref([
  "DATE", "WEIGHT", "BENCH PRESS", "FR. SQUAT", "BACK SQUAT", "DEADLIFT", "CLEAN", "HAND STR", "PUSH UPS", "PULL UPS", "VERT", "BROAD", "MED BALL ROT", "10Y", "40", "60", "EXIT VELO", "BAT SPEED", "THROW VELO", "PITCH VELO", "SLEEP HRS", "SLEEP Q", "RECOVERY", "MOBILITY"
])

const date = (info) => {
  const fecha = new Date(info);
  if (!isNaN(fecha.getMonth() + 1) && !isNaN(fecha.getDate()) && !isNaN(fecha.getFullYear())) {
    return `${fecha.getMonth() + 1}/${fecha.getDate()}/${fecha.getFullYear()}`
  }else{
    return ""
  }
}

</script>

<template>
  <section class="px-[4%] md:px-[3%] mt-[3%] overflow-x-auto">
    <table class="w-full border-separate space-y-6 text-white">

      <thead class="bg-[#121a29] text-center">
        <tr class="divide-x divide-white/10">
          <th
            v-for="(heading, index) in tableHeadings"
            :key="index"
            class="py-3 font-fungo-500 text-[13px] tracking-wide text-white/80"
          >
            {{ heading }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="!tableData.length > 0">
          <td colspan="9" class="text-white/60 text-xl text-center py-8">No metric logs found</td>
        </tr>
        <template v-else v-for="(item, index) in tableData" :key="index">
          <tr class="bg-[#0f172a] even:bg-[#111b2e] border border-white/10 relative font-fungo-700 text-[14px]">
            <td class="w-[200px] max-w-[200px]">
              {{ date(item.fitness_date ?? "") }}
            </td>
            <td class="w-[150px] max-w-[150px]">
              {{ item.body_weight ?? "" }}
            </td>
            <td class="w-[150px] max-w-[150px]">
              {{ item.bench_press ?? "" }}
            </td>
            <td class="w-[150px] max-w-[150px]">
              {{ item.front_squat ?? "" }}
            </td>
            <td class="w-[150px] max-w-[150px]">
              {{ item.back_squat ?? "" }}
            </td>
            <td class="w-[100px] max-w-[100px]">
              {{ item.dead_lift ?? "" }}
            </td>
            <td class="w-[100px] max-w-[100px]">
              {{ item.power_clean ?? "" }}
            </td>
            <td class="w-[100px] max-w-[100px]">
              {{ item.hand_strength ?? "" }}
            </td>
            <td class="w-[100px] max-w-[100px]">
              {{ item.push_ups ?? "" }}
            </td>
            <td class="w-[100px] max-w-[100px]">
              {{ item.pull_ups ?? "" }}
            </td>
            <td class="w-[100px] max-w-[100px]">
              {{ item.vertical_jump ?? "" }}
            </td>
            <td class="w-[100px] max-w-[100px]">
              {{ item.broad_jump ?? "" }}
            </td>
            <td class="w-[120px] max-w-[120px]">
              {{ item.med_ball_rotational_throw ?? "" }}
            </td>
            <td class="w-[90px] max-w-[90px]">
              {{ item.sprint_10yd ?? "" }}
            </td>
            <td class="w-[150px] max-w-[150px]">
              {{ item.yd_40_dash ?? "" }}
            </td>
            <td class="w-[150px] max-w-[150px]">
              {{ item.yd_60_dash ?? "" }}
            </td>
            <td class="w-[120px] max-w-[120px]">
              {{ item.exit_velo ?? "" }}
            </td>
            <td class="w-[120px] max-w-[120px]">
              {{ item.bat_speed ?? "" }}
            </td>
            <td class="w-[120px] max-w-[120px]">
              {{ item.throwing_velo ?? "" }}
            </td>
            <td class="w-[120px] max-w-[120px]">
              {{ item.pitch_velo ?? "" }}
            </td>
            <td class="w-[140px] max-w-[140px]">
              {{ item.sleep_hours ?? "" }}
            </td>
            <td class="w-[140px] max-w-[140px]">
              {{ item.sleep_quality_1_to_5 ?? "" }}
            </td>
            <td class="w-[140px] max-w-[140px]">
              {{ item.recovery_score ?? "" }}
            </td>
            <td class="w-[140px] max-w-[140px]">
              {{ item.mobility_score ?? "" }}
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </section>
</template>

<style scoped>
table{
  border-spacing: 0 10px;
}
table tbody tr td {
  @apply text-center py-3 px-1 2xl:px-5;
}

table tbody tr::after{
  content: '';
  position: absolute;
  left: -1px;
  top: 0;
  height: 100%;
  width: 3px;
  background-color: rgba(192, 0, 0, 0.7);
}
table tbody tr:nth-child(even)::after{
  background-color: rgba(192, 0, 0, 0.35);
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
  background: #C00000;
  border-radius: 6px;
}

::-webkit-scrollbar-thumb:active {
  background: #8b0000;
}
::-webkit-scrollbar-track {
  border: 22px solid #121a29;
  background: #060b14;
  border-radius: 6px;
}
::-webkit-scrollbar-corner {
  background: transparent;
}
</style>
