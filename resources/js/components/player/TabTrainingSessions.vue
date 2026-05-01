<script setup>
import {onMounted, ref} from 'vue'
import { TableStats, TableStart } from '@/components/icons'
import { useTrainingStore } from "@/store/training";
import { useAxiosAuth } from '@/composables/axios-auth.js'
import {useRouter} from "vue-router";
import { usePlayerResume } from '@/composables/usePlayerResume.js'

const activeTraining = useTrainingStore();
const router = useRouter()
const { axiosGet } = useAxiosAuth()
const props = defineProps({
  playerData: {
    type: Object,
    required: false,
    default: {}
  }
})
const tableData = ref([])
const pages = ref(1)
const isLoading = ref(false)
const tableHeadings = ref([
  "TEAM / PLAYER", "MODE TYPE", "TOTALS BALLS","STATUS/CREATED AT", "START/RESUME", "STATS"
])

const { resumenModePlayer } = usePlayerResume()

const getTrainigs = async(page = 1) => {
  try {
    isLoading.value = true
    await axiosGet(`player/sessions/training`).then((response) => {
      if (response) {
        let array = response.data.data.data
        tableData.value = array.filter(item => item.modes == 'WB' || item.modes == 'LT' || item.modes == 'EV')
        pages.value = response.data.data.current_page + 1
        console.log(tableData.value)
      }
    })
  } catch (error) {
    /*  */
    tableData.value = []
  } finally {
    isLoading.value = false
  }
}

const resumeTraining = (training) => {
  resumenModePlayer(training)
}

onMounted(() => {
  getTrainigs(pages.value)
})
</script>

<template>
  <section class="mt-4 overflow-x-auto">
    <table class="w-full border-separate space-y-6 text-fungo-darkblue">

      <thead class="bg-fungo-lightblue">
        <tr class="divide-x divide-[#000]">
          <th v-for="(heading, index) in tableHeadings"
            :key="index" class="py-3 font-fungo-500"
          >
            {{ heading }}
          </th>
        </tr>
      </thead>

      <tbody>
      <tr v-if="isLoading" class="w-full">
        <td colspan="9" class="text-fungo-darkblue text-3xl text-center">Loading data...</td>
      </tr>
      <tr v-else-if="!tableData.length > 0" class="w-full">
        <td colspan="9" class="text-fungo-darkblue text-3xl text-center">There is no data</td>
      </tr>
      <tr v-else v-for="(item, index) in tableData" :key="index" class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
        <td class="w-[100px] lg:w-[300px] lg:max-w-[400px]">
          <div class="flex flex-col text-[16px] items-start px-5">
            <template v-if="item.team !== null">
                <h1><span class="text-[16px] font-fungo-700">Team: </span>{{ item.team.name }} </h1>
            </template>
            <template v-else>
              <h1 class="text-[18px] font-fungo-700">Personal practice</h1>
            </template>
          </div>
        </td>
        <td>
          {{ item.modes === 'WB' ? 'Weight Ball':'' }}
          {{ item.modes === 'LT' ? 'Long Toss':'' }}
          {{ item.modes === 'EV' ? 'Exit Velocity':'' }}

        </td>
        <td>
          {{ item.modes === 'WB' ? item.weight_ball.length :'' }}
          {{ item.modes === 'LT' ? item.long_toss.length :'' }}
          {{ item.modes === 'EV' ? item.exit_velocity.length :'' }}

        </td>
        <td class="w-[200px] max-w-[200px]">
          <progress max="100" :value="item.is_completed ? 100 : 50"
                    class="rounded overflow-hidden h-[7px]" :class="{ 'in-proress' : !item.is_completed, 'completed' : item.is_completed }">
          </progress>
        </td>
        <td class="w-[150px] max-w-[150px]">
          <button @click.prevent="resumeTraining(item)" :class="{'hidden' : item.is_completed}">
            <TableStart />
          </button>
        </td>
        <td class="w-[80px] max-w[80px]">
          <button @click="router.push({ name: 'training.statsMode', params: { 'idPractice': item.id,
          'mode': item.modes, }})"
                  class="hover:bg-fungo-gray3 rounded-full p-2"
          >
            <TableStats />
          </button>
        </td>
      </tr>
      </tbody>
    </table>
  </section>
</template>

<style scoped>
table {
  border-spacing: 0 10px;
}

table tbody tr td {
  @apply text-center py-4 px-1 2xl:px-5;
}

table tbody tr::after {
  content: '';
  position: absolute;
  left: -1px;
  top: 0;
  height: 100%;
  width: 3px;
  /* background-color: #ADE8F4; */
}

table tbody tr:nth-child(even)::after {
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
