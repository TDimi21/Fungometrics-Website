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
 return isBasic.value == false ? ['HITTERS', 'AB', 'PA', 'h', 'total bases', '1b', '2b.', '3b', 'hr', 'K', 'BB', 'hbp', 'ba.', 'obp', 'slg', 'ops'] 
                                : ['HITTERS', 'AB', 'PA', 'h', 'pa/bb', 'bb/k', 'c%', 'xbh', 'ps/pa', '2s avg', '6+', '6+ %', 'hard hit%', 'ld%', 'gb%', 'fly%', 'babip']
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
          <tr v-for="(item, index) in tableData['hitter-basic'].players" :key="index" class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
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
            <td>{{ item.AB }}</td>
            <td>{{ item.PA }}</td>
            <td>{{ item.H }}</td>
            <td>{{ item['TOTAL-BASES'] }}</td>
            <td>{{ item.B1 }}</td>
            <td>{{ item.B2 }}</td>
            <td>{{ item.B3 }}</td>
            <td>{{ item.HR }}</td>
            <td>{{ item.k }}</td>
            <td>{{ item.BB }}</td>
            <td>{{ item.HBP }}</td>
            <td>{{ item.BA }}</td>
            <td>{{ item.OBP }}</td>
            <td>{{ item.SLG }}</td>
            <td>{{ item.SLG }}</td>
          </tr>
          <tr class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
            <td>Team</td>
            <td>{{ tableData['hitter-basic'].team_totals.AB }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.PA }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.H }}</td>
            <td>{{ tableData['hitter-basic'].team_totals['TOTAL-BASES'] }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.B1 }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.B2 }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.B3 }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.HR }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.k }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.BB }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.HBP }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.BA }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.OBP }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.SLG }}</td>
            <td>{{ tableData['hitter-basic'].team_totals.SLG }}</td>
          </tr>
        </template>

        <template v-else>
          <tr v-for="(item, index) in tableData['hitter-advance'].players" :key="index" class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
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
            <td>{{ item.AB }}</td>
            <td>{{ item.PA }}</td>
            <td>{{ item.H }}</td>
            <td>{{ item.PA_BB }}</td>
            <td>{{ item.BB_K }}</td>
            <td>{{ item.C_PERCENTS }}</td>
            <td>{{ item.XBH }}</td>
            <td>{{ item.PS_PA }}</td>
            <td>{{ item['2SAVG'] }} %</td>
            <td>{{ item['6+'] }}</td>
            <td>{{ item['6+ %'] }} %</td>
            <td>{{ item.HARDHIT }}</td>
            <td>{{ item.LDP }} %</td>
            <td>{{ item.GBP }} %</td>
            <td>{{ item.FLYP }} %</td>
            <td>{{ item.BABIP }}</td>
          </tr>
          <tr class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
            <td>Team</td>
            <td>{{ tableData['hitter-advance'].team_totals.AB }}</td>
            <td>{{ tableData['hitter-advance'].team_totals.PA }}</td>
            <td>{{ tableData['hitter-advance'].team_totals.H }}</td>
            <td>{{ tableData['hitter-advance'].team_totals.PA_BB }}</td>
            <td>{{ tableData['hitter-advance'].team_totals.BB_K }}</td>
            <td>{{ tableData['hitter-advance'].team_totals.C_PERCENTS }}</td>
            <td>{{ tableData['hitter-advance'].team_totals.XBH }}</td>
            <td>{{ tableData['hitter-advance'].team_totals.PS_PA }}</td>
            <td>{{ tableData['hitter-advance'].team_totals['2SAVG'] }} %</td>
            <td>{{ tableData['hitter-advance'].team_totals['6+'] }}</td>
            <td>{{ tableData['hitter-advance'].team_totals['6+ %'] }} %</td>
            <td>{{ tableData['hitter-advance'].team_totals.HARDHIT }}</td>
            <td>{{ tableData['hitter-advance'].team_totals.LDP }} %</td>
            <td>{{ tableData['hitter-advance'].team_totals.GBP }} %</td>
            <td>{{ tableData['hitter-advance'].team_totals.FLYP }} %</td>
            <td>{{ tableData['hitter-advance'].team_totals.BABIP }}</td>
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