<script setup>
import { onMounted, ref } from 'vue'
import Layout from '@/layout/Layout.vue'
import { useRoute } from 'vue-router'
import { SearchIcon } from '@/components/icons'
import { PracticeTitle } from '@/components/practice'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { SelectField, InputBase, BigButtonField } from '@/components/form'
import BattingLogoPractice from "@/components/graphics/BattingLogoPractice.vue"
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import { TableTab, HittingTab, PitchingTab } from '@/components/TabLiveABStatistics/primary'
import useSortStatistics from '@/composables/useSortStatistics.js'
import { useTeamStore } from '@/store/team.js'
import { useLiveABStore } from '@/store/liveAB.js'
import { storeToRefs } from 'pinia'

const { axiosGet } = useAxiosAuth()
const route = useRoute()
const { ordenarElementos } = useSortStatistics()
const statisticsData = ref([])
const orderAsc = ref(true)
const useTeam = useTeamStore()
const useLiveAB = useLiveABStore()

const tabHeading = ref(['Tables', 'Hitting', 'Pitching'])

const excelHeaderData = ref({})
const excelDataExport = ref([])
const { teamsAndPlayers } = storeToRefs(useLiveAB)
const isLoading = ref(false)

const getStatistic = async () => {
  try {
    isLoading.value = !isLoading.value
    await axiosGet(`statistics/${route.params.id}/liveab`)
      .then(response => {
        statisticsData.value = response.data.data
        excelExportDataAB()
      })

  } catch (error) {
    console.log(error);
  } finally {
    isLoading.value = !isLoading.value
  }
}

const getPlayersFromTeam = async () => {
  let filterPlayers = await useTeam.getTeamsFromApi()
  let playersToAdd = []
  filterPlayers.forEach(element => {
    if (element.players.length > 0) {
      element.players.forEach(playr => {
        playersToAdd.push(playr)
      })
    }
  });
  teamsAndPlayers.value = playersToAdd
}

const sortData = (key) => {

  orderAsc.value = !orderAsc.value

  if (!orderAsc.value) {
    return ordenarElementos(statisticsData.value.ball_x_ball, key, 'asc')
  } else {
    return ordenarElementos(statisticsData.value.ball_x_ball, key, 'desc')
  }
}

onMounted(() => {
  getStatistic()
  getPlayersFromTeam()
})

const excelExportDataAB = () => {
  let dataTable = []
  let count = 1
  for (const iterator of statisticsData.value.ball_x_ball) {
    dataTable.push({
      'id': iterator.sort + 1,
      'pitcher': iterator.pitching.profile.first_name + " " + iterator.pitching.profile.last_name,
      'hitter': iterator.batting.profile.first_name + " " + iterator.batting.profile.last_name,
      'count': iterator.count_b_s,
      'pitch_Type': iterator.pitching.type_throw,
      'B_S': iterator.batting.zone == "S" ? "Strike" : "Ball",
      'Q_S': iterator.batting.quality_of_contact,
      'total_B': showTotalBasesValue(iterator),
      'trajectory': iterator.pitching.trajectory,
      'direction': iterator.batting.field_direction ?? '-',
      'pitch_vel': iterator.pitching.miles_per_hour ?? 0,
      'exit_vel': iterator.batting.velocity ?? 0
    })
    count++
  }

  excelDataExport.value = dataTable
  excelHeaderData.value = {
    'Pitch #': 'id',
    'Pitcher': 'pitcher',
    'Hitter': 'hitter',
    'Count': 'count',
    'Pitch Type': 'pitch_Type',
    'B/S': 'B_S',
    'Q.C': 'Q_S',
    'Total Bases': 'total_B',
    'Trajectory': 'trajectory',
    'Direction': 'direction',
    'Pitch velocity': 'pitch_vel',
    'Exit Velocity': 'exit_vel',
  }
}

const showTotalBasesValue = (item) => {
  let valueToShow = ''
  if (item.bases == 7 && item.pitching.trajectory != 'F') {
    valueToShow = 'K'
  } else if (item.bases === 4) {
    valueToShow = 'BB'
  } else if (item.bases == 6) {
    valueToShow = 'HBP'
  } else if (item.bases === 5) {
    valueToShow = 'HR'
  } else if (item.bases === 8) {
    valueToShow = 'Out/E'
  } else if (item.bases == 7 && item.pitching.trajectory == 'F') {
    valueToShow = '-'
  } else {
    valueToShow = item.bases === 0 ? '-' : item.bases + 'B'
  }

  return valueToShow
}
</script>
<template>
  <Layout>
    <practice-title title="LiveAB Mode Practices Statistics" />

    <section class="bg-fungo-gray3 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[5%]">
      <div class="flex flex-col items-center space-y-6 lg:flex-row lg:space-y-0 lg:space-x-3">
        <div class="w-max">
          <batting-logo-practice class="h-[80px] w-[80px] hidden lg:block" />
        </div>
        <div class="w-full lg:w-[30%] hidden">
          <div class="flex items-center space-x-3 flex-nowrap">
            <label for="entries">Show</label>
            <select-field v-model="entriesModel" :options="entriesOption" />
          </div>
        </div>
        <div class="w-full lg:w-[50%] hidden">
          <form action="" name="search-practice" class="flex flex-row items-center space-x-3 flex-nowrap">
            <label for="search" class="block">Search</label>
            <input-base v-model="searchPractice" inputType="search" class="inline-flex w-[65%]" />
            <button type="submit" role="submit"
              class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-fungo-red">
              <search-icon />
            </button>
          </form>
        </div>
        <div class="w-[100%] lg:w-[50%] flex justify-between items-center">
          <download-excel class="flex w-[100px] gap-2 bg-white p-3 rounded-r-full" :data="excelDataExport"
            :fields="excelHeaderData" :name="'liveABBallxBallTable.xls'">
            <div>Excel</div>
            <svg width="18" height="22" viewBox="0 0 18 22" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M18 7.71429H12.8571V0H5.14286V7.71429H0L9 16.7143L18 7.71429ZM7.71307 10.2863V2.57202H10.2845V10.2863H11.7888L8.99878 13.0763L6.20878 10.2863H7.71307ZM18 21.8571V19.2856H0V21.8571H18Z"
                fill="#E10600" />
            </svg>
          </download-excel>
        </div>
      </div>
    </section>

    <!-- tab navigation -->
    <section class="mt-[200px] lg:mt-[120px] md:px-[5%]">
      <tab-group>
        <tab-list class="border-b-2 border-fungo-gray3">
          <tab as="template" v-slot="{ selected }" class="mx-4" v-for="head in tabHeading">
            <button class="outline-none"
              :class="{ 'text-fungo-red font-fungo-500 border-b-2 border-fungo-red': selected, 'text-fungo-darkblue': !selected }">
              {{ head }}
            </button>
          </tab>
        </tab-list>
        <tab-panels>
          <tab-panel>
            <table-tab :stats-data="statisticsData" @sortData="sortData" :isLoading="isLoading"/>
          </tab-panel>
          <tab-panel>
            <hitting-tab :stats-data="statisticsData" />
          </tab-panel>
          <tab-panel>
            <pitching-tab :stats-data="statisticsData" />
          </tab-panel>
        </tab-panels>
      </tab-group>
    </section>
  </Layout>
</template>
