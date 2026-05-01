<script setup>
import { ref, computed } from 'vue'
import { Switch } from '@headlessui/vue'
import { useGetPlayerAb } from '@/composables/useGetPlayerAb.js'

defineProps({
  tableData: {
    type: [Array, Object],
    required: true,
    default: []
  }
})

const { getPlayerInfo } = useGetPlayerAb()

const isBasic = ref(false)

const headings = computed(() => {
 return isBasic.value == false ? ['Pitcher', 'total pitches', 'bf', 'ball', 'strike', 'strike%', 'fb', 'fbssss%', 'ch', 'chs%', 'cv', 'cvs%', 'SL', 'sls%', 'other', 'other%'] 
                                : ['Pitcher', 'total pitches', 'hits', 'p/bf', '<=3%', 'fps%', 'fbso%', 'fpsw%', 'fpsh%', 'sm%', 'k/bf%', 'weak%', 'babip', 'ld%', 'gb%', 'fly%']
})
</script>
<template>
  <section class="mt-4 overflow-x-auto">
    <div class="flex flex-row justify-center items-center space-x-4">
      <span>Basic</span>
      <Switch
        v-model="isBasic"
        :class="isBasic ? 'bg-fungo-darkblue' : 'bg-fungo-blue2'"
        class="relative inline-flex h-[28px] w-[64px] shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75"
      >
        <span class="sr-only">Use setting</span>
        <span
          aria-hidden="true"
          :class="isBasic ? 'translate-x-9' : 'translate-x-0'"
          class="pointer-events-none inline-block h-[25px] w-[25px] transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
        />
      </Switch>
      <span>Advance</span>
    </div>
    <table class="w-full border-separate space-y-6 text-fungo-darkblue">

      <thead class="bg-fungo-lightblue">
        <tr class="divide-x divide-[#000]">
          <th v-for="(heading, index) in headings" :key="index" class="py-3 px-2 md:px-0 font-fungo-500 uppercase w-min">
            {{ heading }}
          </th>
        </tr>
      </thead>

      <tbody>
        <!-- <tr v-if="isLoading" class="w-full">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">Loading data...</td>
        </tr>
        <tr v-else-if="!tableData.length > 0" class="w-full">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">There is no data</td>
        </tr> -->
        <template v-if="!isBasic">
          <tr v-for="(item, index) in tableData['pitcher-basic'].players" :key="index" class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
            <td class="w-[100px] lg:w-[250px] lg:max-w-[250px]">
              <div class="grid grid-cols-2 place-items-center w-[200px] lg:w-auto">
                <img
                  :src="getPlayerInfo(index).avatar"
                  class="w-[70px] h-[70px] rounded-full border-[5px] border-fungo-gray"
                >
                <p class="">
                  {{ getPlayerInfo(index).name.full }}
                </p>
              </div>
            </td>
            <td>{{ item.TOTAL_PITCHES }}</td>
            <td>{{ item.BF }}</td>
            <td>{{ item.BALL }}</td>
            <td>{{ item.STRIKES }}</td>
            <td>{{ item['STRIKES %'] }} %</td>
            <td>{{ item.FB }}</td>
            <td>{{ item['FB %'] }} %</td>
            <td>{{ item.CH }}</td>
            <td>{{ item['CH %'] }} %</td>
            <td>{{ item.CB }}</td>
            <td>{{ item['CB %'] }} %</td>
            <td>{{ item.SL }}</td>
            <td>{{ item['SL %'] }} %</td>
            <td>{{ item.OTHER }}</td>
            <td>{{ item['OTHER %'] }} %</td>
          </tr>
          <tr class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
            <td>Team</td>
            <td>{{ tableData['pitcher-basic'].team_totals.TOTAL_PITCHES }}</td>
            <td>{{ tableData['pitcher-basic'].team_totals.BF }}</td>
            <td>{{ tableData['pitcher-basic'].team_totals.BALL }}</td>
            <td>{{ tableData['pitcher-basic'].team_totals.STRIKES }}</td>
            <td>{{ tableData['pitcher-basic'].team_totals['STRIKES %'] }} %</td>
            <td>{{ tableData['pitcher-basic'].team_totals.FB }}</td>
            <td>{{ tableData['pitcher-basic'].team_totals['FB %'] }} %</td>
            <td>{{ tableData['pitcher-basic'].team_totals.CH }}</td>
            <td>{{ tableData['pitcher-basic'].team_totals['CH %'] }} %</td>
            <td>{{ tableData['pitcher-basic'].team_totals.CB }}</td>
            <td>{{ tableData['pitcher-basic'].team_totals['CB %'] }} %</td>
            <td>{{ tableData['pitcher-basic'].team_totals.SL }}</td>
            <td>{{ tableData['pitcher-basic'].team_totals['SL %'] }} %</td>
            <td>{{ tableData['pitcher-basic'].team_totals.OTHER }}</td>
            <td>{{ tableData['pitcher-basic'].team_totals['OTHER %'] }} %</td>
          </tr>
        </template>

        <template v-else>
          <tr v-for="(item, index) in tableData['pitcher-advance'].players" :key="index" class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
            <td class="w-[100px] lg:w-[250px] lg:max-w-[250px]">
              <div class="grid grid-cols-2 place-items-center w-[200px] lg:w-auto">
                <img
                  :src="getPlayerInfo(index).avatar"
                  class="w-[70px] h-[70px] rounded-full border-[5px] border-fungo-gray"
                >
                <p class="">
                  {{ getPlayerInfo(index).name.full }}
                </p>
              </div>
            </td>
            <td>{{ item.TOTAL_PITCHES }}</td>
            <td>{{ item.Hits }}</td>
            <td>{{ item['P/BF'] }}</td>
            <td>{{ item['<=3 %'] }} %</td>
            <td>{{ item['FPS%'] }} %</td>
            <td>{{ item['FPSo%'] }} %</td>
            <td>{{ item['FPSw%'] }} %</td>
            <td>{{ item['FPSh%'] }} %</td>
            <td>{{ item['SM%'] }} %</td>
            <td>{{ item['K/BF%'] }} %</td>
            <td>{{ item['Weak%'] }} %</td>
            <td>{{ item.BABIP }}</td>
            <td>{{ item['LD%'] }} %</td>
            <td>{{ item['GB%'] }} %</td>
            <td>{{ item['Fly%'] }} %</td>
          </tr>

          <tr class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
            <td>Team</td>
            <td>{{ tableData['pitcher-advance'].team_totals.TOTAL_PITCHES }}</td>
            <td>{{ tableData['pitcher-advance'].team_totals.Hits }}</td>
            <td>{{ tableData['pitcher-advance'].team_totals['P/BF'] }}</td>
            <td>{{ tableData['pitcher-advance'].team_totals['<=3 %'] }} %</td>
            <td>{{ tableData['pitcher-advance'].team_totals['FPS%'] }} %</td>
            <td>{{ tableData['pitcher-advance'].team_totals['FPSo%'] }} %</td>
            <td>{{ tableData['pitcher-advance'].team_totals['FPSw%'] }} %</td>
            <td>{{ tableData['pitcher-advance'].team_totals['FPSh%'] }} %</td>
            <td>{{ tableData['pitcher-advance'].team_totals['SM%'] }} %</td>
            <td>{{ tableData['pitcher-advance'].team_totals['K/BF%'] }} %</td>
            <td>{{ tableData['pitcher-advance'].team_totals['Weak%'] }} %</td>
            <td>{{ tableData['pitcher-advance'].team_totals.BABIP }}</td>
            <td>{{ tableData['pitcher-advance'].team_totals['LD%'] }} %</td>
            <td>{{ tableData['pitcher-advance'].team_totals['GB%'] }} %</td>
            <td>{{ tableData['pitcher-advance'].team_totals['Fly%'] }} %</td>
          </tr>
        </template>
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
  background-color: #ADE8F4;
}

table tbody tr:nth-child(even)::after {
  background-color: #DADADA;
}
</style>