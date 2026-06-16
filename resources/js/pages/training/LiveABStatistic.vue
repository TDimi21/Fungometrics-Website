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

const buildPlayersFromBallData = () => {
  const playerMap = {}
  ;(statisticsData.value.ball_x_ball ?? []).forEach(ball => {
    if (ball.batting?.batter_id && ball.batting?.profile) {
      const id = String(ball.batting.batter_id)
      const p = ball.batting.profile
      playerMap[id] = {
        id,
        name: { first: p.first_name ?? '', last: p.last_name ?? '', full: `${p.first_name ?? ''} ${p.last_name ?? ''}`.trim() },
        avatar: p.picture ?? null,
      }
    }
    if (ball.pitching?.pitcher_id && ball.pitching?.profile) {
      const id = String(ball.pitching.pitcher_id)
      const p = ball.pitching.profile
      playerMap[id] = {
        id,
        name: { first: p.first_name ?? '', last: p.last_name ?? '', full: `${p.first_name ?? ''} ${p.last_name ?? ''}`.trim() },
        avatar: p.picture ?? null,
      }
    }
  })
  teamsAndPlayers.value = Object.values(playerMap)
}

const getStatistic = async () => {
  try {
    isLoading.value = !isLoading.value
    await axiosGet(`statistics/${route.params.id}/liveab`)
      .then(response => {
        statisticsData.value = response.data.data
        buildPlayersFromBallData()
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
    if (element.players?.length > 0) {
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
      'outs': outsRecordedForRow(iterator),
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
    'Outs': 'outs',
    'Trajectory': 'trajectory',
    'Direction': 'direction',
    'Pitch velocity': 'pitch_vel',
    'Exit Velocity': 'exit_vel',
  }
}

const toNumber = (value, fallback = 0) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : fallback
}

const normalizePlayResult = (item) => String(item?.play_result || '').toLowerCase()

const getStrikeCountBefore = (item) => {
  const count = String(item?.count_b_s || '')
  const parts = count.split('-')
  return toNumber(parts?.[1], 0)
}

const isDroppedThirdStrikeSafe = (item) => {
  const playResult = normalizePlayResult(item)
  return playResult.includes('dropped') && playResult.includes('safe')
}

const isStrikeoutEvent = (item) => {
  const playResult = normalizePlayResult(item)
  if (playResult.includes('strikeout') || playResult === 'k') return true

  const strikesBefore = getStrikeCountBefore(item)
  const zone = String(item?.batting?.zone || item?.pitching?.zone || '').toUpperCase()
  const trajectory = String(item?.pitching?.trajectory || '').toUpperCase()
  const bases = toNumber(item?.bases, 0)

  if (bases === 7 && trajectory !== 'F') return true

  if (strikesBefore === 2) {
    if (zone === 'S' && ['TK', 'SM'].includes(trajectory)) return true
    if (zone === 'B' && trajectory === 'SM') return true
  }

  return false
}

const outsRecordedForRow = (item) => {
  const explicitOuts = item?.outs_recorded
  if (explicitOuts !== undefined && explicitOuts !== null && explicitOuts !== '') {
    return toNumber(explicitOuts, 0)
  }

  if (isDroppedThirdStrikeSafe(item)) return 0
  if (isStrikeoutEvent(item)) return 1

  return toNumber(item?.bases, 0) === 8 ? 1 : 0
}

const showTotalBasesValue = (item) => {
  const bases = toNumber(item?.bases, 0)

  if (isDroppedThirdStrikeSafe(item)) return 'K (safe)'
  if (isStrikeoutEvent(item)) return 'K'
  if (bases === 4) return 'BB'
  if (bases === 6) return 'HBP'
  if (bases === 5) return 'HR'
  if (bases === 8) return 'Out/E'
  if (bases === 0) return '-'

  return `${bases}B`
}
</script>
<template>
  <Layout>
    <!-- Page header: logo + title -->
    <div class="flex items-center gap-4 mb-8 md:px-[5%]">
      <batting-logo-practice class="h-[56px] w-[56px] flex-shrink-0" />
      <h1 class="text-app-red text-2xl md:text-3xl font-bold tracking-wide">LiveAB Mode Practices Statistics</h1>
    </div>

    <!-- Tabs + Export button -->
    <section class="md:px-[5%]">
      <tab-group>
        <!-- Tab bar row: tabs left, Excel export right -->
        <div class="flex items-center justify-between border-b-2 border-white/10 mb-4">
          <tab-list class="flex">
            <tab as="template" v-slot="{ selected }" v-for="head in tabHeading" :key="head">
              <button class="outline-none pb-2 px-4 text-sm font-semibold transition-colors whitespace-nowrap"
                :class="{ 'text-app-gold border-b-2 border-app-gold': selected, 'text-app-muted hover:text-white': !selected }">
                {{ head }}
              </button>
            </tab>
          </tab-list>

          <download-excel
            class="flex items-center gap-2 bg-app-card hover:bg-app-card-hover border border-white/10 text-white px-4 py-2 rounded-lg cursor-pointer transition-colors mb-1 flex-shrink-0"
            :data="excelDataExport"
            :fields="excelHeaderData"
            :name="'liveABBallxBallTable.xls'"
          >
            <svg width="14" height="17" viewBox="0 0 18 22" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M18 7.71429H12.8571V0H5.14286V7.71429H0L9 16.7143L18 7.71429ZM7.71307 10.2863V2.57202H10.2845V10.2863H11.7888L8.99878 13.0763L6.20878 10.2863H7.71307ZM18 21.8571V19.2856H0V21.8571H18Z"
                fill="#E10600" />
            </svg>
            <span class="text-sm font-semibold">Export</span>
          </download-excel>
        </div>

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
