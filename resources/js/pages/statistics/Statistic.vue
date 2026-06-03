<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useTeamStore } from "@/store/team"
import { useUserStore } from "@/store/user"
import {toast} from "@/utils/AlertPlugin"
import { SelectTeams, DropDownMultiple } from '@/components/shared'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { InputBase, LabelField, SelectField, BigButtonField } from '@/components/form'
import {
  BattingTotals, BattingPercentage, AverageAndMaxVelocity, BattingLeftTOH_QOS,
  LiveABPitcherAdvance, BattingRightTOH_QOS, BattingMiddleTOH_QOS, PitchingTotals,
  PitchingPercentage, PitchingAverageTopVelocity, PitchingFastGroundBallPercentages,
  PitchingCurveLinePercentages, PitchingChangeupFlyPercentages, PitchingSliderFoulPercentages,
  PitchingOtherPercentages, PitchingStrikeFastballPercentages, PitchingStrikeCurveballPercentage,
  PitchingStrikechangeupPercentage, PitchingStrikeSliderPercentage, PitchingStrikeOtherPercentage,
  CageLaunchTotal, CageLaunchPercentage, CageSprayTotals, CageSprayPercentages, CageSprayAverage,
  WeightedTotals, WeightedAverage, WeightedMax, ExitTotals, ExitPercentage, ExitAverage,
  ExitTop, LongDistanceTotal, LongDistancePercentage, LongDistanceAverage, LongTotals, LongMax,
  LongAverage, LiveABHitterBasic, LiveABHitterAdvance, LiveABPitcherBasic,
  DropDownOptionsOfSession, LiveABPitcherBreakDown, LiveABHitterContact, LiveABPitcherPitchBreakdown,
  LiveABPitcherContact, LiveABHitterTrajectory, LiveABHitterVelocity, LiveABPitcherVelocity,
  CageLaunchAverage,PitchingPopFlies
} from '@/components/globalStats/index.js'

const { teams } = useTeamStore();
const { userData } = useUserStore();
const { axiosGet } = useAxiosAuth()
const route = useRoute()
const isPlayerLogin = ref(false)

const dataTeam = ref({
  selectTeam : "",
})

const dataFilter = ref({
  sinceWhen: '',
  until: '',
  players: [],
  sessions: [],
  options: {}
})

const optionsPlayer = ref({})
const optionsSession = ref({
  "B": "Batting",
  "P": 'Bullpen',
  "C": 'Cage',
  "EV": 'Exit Velocity',
  "LT": 'Long Toss',
  "WB": 'Weighted Ball',
  "L":  'Live AB',
})

const buildOptionsFromSessions = (sessions) => {
  const selected = Array.isArray(sessions) ? sessions : []
  const options = {}
  if (selected.includes('B')) options.B = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
  if (selected.includes('P')) options.P = [10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28]
  if (selected.includes('C')) options.C = [29, 30, 31, 32, 33, 34]
  if (selected.includes('EV')) options.EV = [35, 36, 37, 38]
  if (selected.includes('LT')) options.LT = [39, 40, 41, 42, 43, 44]
  if (selected.includes('WB')) options.WB = [45, 46, 47]
  if (selected.includes('L')) options.L = [48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58]
  return options
}

const playerInfo = ref([])
const tableData = ref({
  batting: {
    totals: {},
    percents: {},
    average_velocity_breakdown: {},
    max_velocity_breakdown: {},
    "TOH-L": {},
    "TOH-R": {},
    "TOH-M": {},
    "QOH-L": {},
    "QOH-R": {},
    "QOH-M": {}
  },
  bullpen: {
    totals: {},
    percents: {},
    average_velocity_breakdown: {},
    top_velocity_breakdown: {},
    'TOT-FAST': {},
    'TRAJECTORY-GB': {},
    'TOT-CURVE': {},
    'TRAJECTORY-LD': {},
    'TOT-CHANGE': {},
    'TRAJECTORY-FB': {},
    'TRAJECTORY-PF': {},
    'TOT-SLIDER': {},
    'TRAJECTORY-FOUL': {},
    'TOT-OTHER': {},
    'TOT-SLIDER-STRIKE': {},
    'TOT-FAST-STRIKE': {},
    'TOT-CURVE-STRIKE': {},
    'TOT-CHANGE-STRIKE': {},
    'TOT-OTHER-STRIKE': {},
  },
  cage: {
    'launch-angle-totals': {},
    'launch-angle-percents': {},
    'spray-angle-totals': {},
    'spray-angle-percents': {},
    'launch-angle-average-exit-velocity': {},
    'spray-angle-average-exit-velocity': {}
  },
  'exit_velocity': {
    'totals': {},
    'percents': {},
    'average-velocity': {},
    'top-velocity': {}
  },
  long_toss: {
    'totals-distances': {},
    'percents-distances': {},
    'average-distance': {},
    'average-hops': {},
    'total-hops': {},
    'max-hops': {},
  },
  weight_ball: {
    totals: {},
    'average-velocity': {},
    'max-velocity': {}
  },
  live: {
    'hitter-basic': {},
    'hitter-advance': {},
    'pitcher-basic': {},
    'pitcher-advance': {},
    'hitter-pitch-breakdown': {},
    'hitter-contact': {},
    'hitter-trajectory': {},
    'hitter-velocity': {},
    'pitcher-pitch-breakdown': {},
    'pitcher-contact': {},
    'pitcher-velocity': {},
  }
})

const loading = ref(false)

const countSelectedOptions = (options) => {
  if (!options || typeof options !== 'object') return 0
  return Object.values(options).reduce((total, value) => {
    if (!Array.isArray(value)) return total
    return total + value.length
  }, 0)
}

const hasAnyStatisticsData = (payload) => {
  const sections = ['batting', 'bullpen', 'cage', 'exit_velocity', 'long_toss', 'weight_ball', 'live']
  return sections.some((sectionName) => {
    const section = payload?.[sectionName]
    if (!section || typeof section !== 'object') return false
    return Object.values(section).some((entry) => {
      if (!entry || typeof entry !== 'object') return false
      const hasPlayers = entry.players && Object.keys(entry.players).length > 0
      const hasTeamTotals = entry.team_totals && Object.keys(entry.team_totals).length > 0
      return hasPlayers || hasTeamTotals
    })
  })
}

const getStatistic = () => {
  loading.value = true
  tableData.value = {
    batting: {
      totals: {},
      percents: {},
      average_velocity_breakdown: {},
      max_velocity_breakdown: {},
      "TOH-L": {},
      "TOH-R": {},
      "TOH-M": {},
      "QOH-L": {},
      "QOH-R": {},
      "QOH-M": {}
    },
    bullpen: {
      totals: {},
      percents: {},
      average_velocity_breakdown: {},
      top_velocity_breakdown: {},
      'TOT-FAST': {},
      'TRAJECTORY-GB': {},
      'TOT-CURVE': {},
      'TRAJECTORY-LD': {},
      'TOT-CHANGE': {},
      'TRAJECTORY-FB': {},
      'TRAJECTORY-PF': {},
      'TOT-SLIDER': {},
      'TRAJECTORY-FOUL': {},
      'TOT-OTHER': {},
      'TOT-SLIDER-STRIKE': {},
      'TOT-FAST-STRIKE': {},
      'TOT-CURVE-STRIKE': {},
      'TOT-CHANGE-STRIKE': {},
      'TOT-OTHER-STRIKE': {}
    },
    cage: {
      'launch-angle-totals': {},
      'launch-angle-percents': {},
      'spray-angle-totals': {},
      'spray-angle-percents': {},
      'launch-angle-average-exit-velocity': {},
      'spray-angle-average-exit-velocity': {}
    },
    'exit_velocity': {
      'totals': {},
      'percents': {},
      'average-velocity': {},
      'top-velocity': {}
    },
    long_toss: {
      'totals-distances': {},
      'percents-distances': {},
      'average-distance': {},
      'average-hops': {},
      'total-hops': {},
      'max-hops': {},
    },
    weight_ball: {
      totals: {},
      'average-velocity': {},
      'max-velocity': {}
    },
    live: {
      'hitter-basic': {},
      'hitter-advance': {},
      'pitcher-basic': {},
      'pitcher-advance': {},
      'hitter-pitch-breakdown': {},
      'hitter-contact': {},
      'hitter-trajectory': {},
      'hitter-velocity': {},
      'pitcher-pitch-breakdown': {},
      'pitcher-contact': {},
      'pitcher-velocity': {},
    }
  }
  try {
    if(dataFilter.value.sinceWhen == '' || dataFilter.value.until == ''){
      throw new Error('Select a valid date!')
    }

    if(dataFilter.value.players.length <= 0){
      throw new Error('Select a player!')
    }

    if(dataFilter.value.sessions.length <= 0){
      throw new Error('Select a session training!')
    }

    const optionsCount = countSelectedOptions(dataFilter.value.options)
    if ((Object.keys(dataFilter.value.options).length <= 0 || optionsCount <= 0) && dataFilter.value.sessions.length > 0) {
      dataFilter.value.options = buildOptionsFromSessions(dataFilter.value.sessions)
    }

    if(Object.keys(dataFilter.value.options).length <= 0){
      throw new Error('Select a training option!')
    }

    const endpoint = isPlayerLogin.value
      ? 'result/statistics/player/' + userData.id
      : 'result/statistics/' + (dataTeam.value.selectTeam == '' ? teams[0].id : dataTeam.value.selectTeam)

    axiosGet(endpoint, {
      dates: 	[  dataFilter.value.sinceWhen, dataFilter.value.until],
      players: isPlayerLogin.value ? [String(userData.id)] : dataFilter.value.players,
      options: dataFilter.value.options
    }).then((data)=> {
      const payload = data?.data?.data || {}
      setBattingAllTables(payload)
      setBullpenAllTables(payload)
      setCageAllTables(payload)
      setExitVelocityTables(payload)
      setLongTossTables(payload)
      setWeigthBallTables(payload)
      setLiveTables(payload)
      if (!hasAnyStatisticsData(payload)) {
        toast.fire({
          icon: 'info',
          title: 'No data',
          text: 'No statistics found for the selected filters.',
        })
      }
    }).catch((error) => {
      console.log(error)
      toast.fire({
        icon: 'error',
        title: 'Request failed',
        text: error?.response?.data?.message || error?.message || 'Unable to load statistics.',
      })
    }).finally(() => {
      loading.value = false
    })
  } catch (error) {
    console.log(error)
    loading.value = false
    toast.fire({
      icon: 'warning',
      title: 'Validation',
      text: error.message,
    })
  }
}

onMounted(async () => {
  isPlayerLogin.value = userData?.type === 'player'

  if (isPlayerLogin.value) {
    optionsPlayer.value = {
      [String(userData.id)]: userData?.name?.full || userData?.name || 'Player',
    }
    dataFilter.value.players = [String(userData.id)]
  }

  await setPlayerList()

  if (route.query?.auto !== '1') {
    return
  }

  const queryTeam = route.query?.team ? String(route.query.team) : ''
  if (queryTeam) {
    dataTeam.value.selectTeam = queryTeam
    await setPlayerList(queryTeam)
  }

  const querySince = route.query?.since ? String(route.query.since) : ''
  const queryUntil = route.query?.until ? String(route.query.until) : ''
  const queryPlayers = route.query?.players ? String(route.query.players).split(',').filter(Boolean) : []
  const querySessions = route.query?.sessions ? String(route.query.sessions).split(',').filter(Boolean) : []

  if (querySince) dataFilter.value.sinceWhen = querySince
  if (queryUntil) dataFilter.value.until = queryUntil
  if (queryPlayers.length > 0) dataFilter.value.players = queryPlayers
  if (querySessions.length > 0) {
    dataFilter.value.sessions = querySessions
    dataFilter.value.options = buildOptionsFromSessions(querySessions)
  }

  if (
    dataFilter.value.sinceWhen &&
    dataFilter.value.until &&
    dataFilter.value.players.length > 0 &&
    dataFilter.value.sessions.length > 0 &&
    Object.keys(dataFilter.value.options).length > 0
  ) {
    getStatistic()
  }
})

const format = (current_datetime)=>{
    let formatted_date = current_datetime.getFullYear() + "/" + (current_datetime.getMonth() + 1) + "/" + current_datetime.getDate()
    return formatted_date;
}

const setPlayerList = async (idTeam) =>{
  if (isPlayerLogin.value) {
    optionsPlayer.value = {
      [String(userData.id)]: userData?.name?.full || userData?.name || 'Player',
    }
    dataFilter.value.players = [String(userData.id)]
    return
  }

  let id = idTeam ?? teams[0].id
  await axiosGet('coach/teams/'+ id).then((response)=>{
    let player = {}
    for (const iterator of response.data.data) {
      let id = iterator.id
      player[id] = iterator.name.full
    }
    optionsPlayer.value = player
  }).catch((e)=>{
    e
  })
}


const setBattingAllTables = (data) => {
  const batting = data?.batting && typeof data.batting === 'object' ? data.batting : {}
  let keys = Object.keys(tableData.value.batting)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(batting, key)){
      const elementP = batting[key]['players']
      const elementT = batting[key]['team_totals']
      tableData.value.batting[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.batting[key]['team'] = elementT
    }
  }
}

const setBullpenAllTables = (data) => {
  const bullpen = data?.bullpen && typeof data.bullpen === 'object' ? data.bullpen : {}
  let keys = Object.keys(tableData.value.bullpen)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(bullpen, key)){
      const elementP = bullpen[key]['players']
      const elementT = bullpen[key]['team_totals']
      tableData.value.bullpen[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.bullpen[key]['team'] = elementT
    }
  }
}


const setCageAllTables = (data) => {
  const cage = data?.cage && typeof data.cage === 'object' ? data.cage : {}
  let keys = Object.keys(tableData.value.cage)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(cage, key)){
      const elementP = cage[key]['players']
      const elementT = cage[key]['team_totals']
      tableData.value.cage[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.cage[key]['team'] = elementT
    }
  }
}

const setExitVelocityTables = (data) => {
  const exitVelocity = data?.exit_velocity && typeof data.exit_velocity === 'object' ? data.exit_velocity : {}
  let keys = Object.keys(tableData.value.exit_velocity)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(exitVelocity, key)){
      const elementP = exitVelocity[key]['players']
      const elementT = exitVelocity[key]['team_totals']
      tableData.value.exit_velocity[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.exit_velocity[key]['team'] = elementT
    }
  }
}

const setLongTossTables = (data) => {
  const longToss = data?.long_toss && typeof data.long_toss === 'object' ? data.long_toss : {}
  let keys = Object.keys(tableData.value.long_toss)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(longToss, key)){
      const elementP = longToss[key]['players']
      const elementT = longToss[key]['team_totals']
      tableData.value.long_toss[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.long_toss[key]['team'] = elementT
    }
  }
}

const setWeigthBallTables = (data) => {
  const weightBall = data?.weight_ball && typeof data.weight_ball === 'object' ? data.weight_ball : {}
  let keys = Object.keys(tableData.value.weight_ball)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(weightBall, key)){
      const elementP = weightBall[key]['players']
      const elementT = weightBall[key]['team_totals']
      tableData.value.weight_ball[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.weight_ball[key]['team'] = elementT
    }
  }
}

const setLiveTables = (data) => {
  const live = data?.live && typeof data.live === 'object' ? data.live : {}
  let keys = Object.keys(tableData.value.live)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(live, key)){
      const elementP = live[key]['players']
      const elementT = live[key]['team_totals']
      tableData.value.live[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.live[key]['team'] = elementT
    }
  }
}

const addNameToPlayersData = (players) => {
  for (const key in players) {
    if (Object.hasOwnProperty.call(optionsPlayer.value, key)) {
      const element = optionsPlayer.value[key];
      players[key] = {
        'player': element,
        ...players[key]}
    }
  }

  return players
}

</script>

<template>
  <Layout>
    <div class="min-h-screen bg-[#060b14] text-white">
      <div class="px-4 py-6 lg:px-8 lg:py-8 pb-28 md:pb-10">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-2xl font-black uppercase tracking-widest text-white md:text-3xl">Statistics</h1>
          <div class="flex items-center gap-2">
            <RouterLink
              to="/statistic"
              class="rounded-lg border border-red-400/60 bg-red-500/30 px-3 py-1.5 text-xs font-black tracking-wider text-white"
            >
              Legacy
            </RouterLink>
            <RouterLink
              to="/new-statistic"
              class="rounded-lg border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-black tracking-wider text-white/90 hover:bg-white/20 transition"
            >
              New
            </RouterLink>
            <button
              v-if="!isPlayerLogin"
              type="button"
              class="rounded-xl border border-red-400/60 bg-red-500/20 px-4 py-2 text-xs font-black tracking-wider text-red-200 hover:bg-red-500/30 transition"
              @click="$emit('open-add-player')"
              onclick="window.dispatchEvent(new Event('open-add-player-modal'))"
            >+ ADD PLAYERS</button>
          </div>
        </div>

        <!-- Filters card -->
        <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-5 mb-6">
          <h2 class="text-xs font-black uppercase tracking-widest text-white/40 mb-4">Filters</h2>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- Team selector -->
            <div v-if="!isPlayerLogin" class="lg:col-span-2">
              <div class="relative overflow-hidden rounded-2xl border border-white/20 shadow-2xl backdrop-blur-xl p-4 cursor-pointer hover:bg-white/15 transition"
                v-for="t in teams" :key="t.id"
                :class="dataTeam.selectTeam == t.id ? 'border-red-500/60 bg-white/10' : 'bg-white/5'"
                @click="dataTeam.selectTeam = t.id; setPlayerList(t.id)"
              >
                <div v-if="t.logo" class="absolute inset-0 bg-cover bg-center opacity-20" :style="{ backgroundImage: `url(${t.logo})` }"></div>
                <div class="absolute inset-0 bg-[#001030]/80"></div>
                <div class="relative z-10 flex items-center gap-4">
                  <div class="h-16 w-16 overflow-hidden rounded-xl border border-white/20 bg-slate-950 shadow-lg shrink-0">
                    <img v-if="t.logo" :src="t.logo" alt="Team" class="h-full w-full object-cover" />
                    <div v-else class="h-full w-full flex items-center justify-center text-2xl font-black text-white/30">FM</div>
                  </div>
                  <div>
                    <h2 class="text-2xl font-black leading-tight text-white">{{ t.name }}</h2>
                    <p class="mt-1 text-xs text-white/60">Player development tracking · Sessions · Stats</p>
                  </div>
                  <div class="ml-auto">
                    <div v-if="dataTeam.selectTeam == t.id" class="w-4 h-4 rounded-full bg-red-500 border-2 border-red-300"></div>
                    <div v-else class="w-4 h-4 rounded-full border-2 border-white/30"></div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="lg:col-span-2 rounded-xl border border-white/15 bg-white/5 p-3">
              <p class="text-xs uppercase tracking-widest text-white/45">Player Scope</p>
              <p class="text-sm font-black text-white mt-1">{{ userData?.name?.full || userData?.name || 'Player' }}</p>
            </div>

            <!-- Date range -->
            <div class="flex flex-col gap-2">
              <label for="stats-since" class="text-[10px] font-black uppercase tracking-widest text-white/40">Since When</label>
              <InputBase id="stats-since" name="since_when" v-model="dataFilter.sinceWhen" inputType="date" inputClasses="w-full rounded-lg border border-white/20 bg-white/10 text-white px-3 py-2 text-sm outline-none" required="true"/>
            </div>
            <div class="flex flex-col gap-2">
              <label for="stats-until" class="text-[10px] font-black uppercase tracking-widest text-white/40">Until</label>
              <InputBase id="stats-until" name="until" v-model="dataFilter.until" inputType="date" inputClasses="w-full rounded-lg border border-white/20 bg-white/10 text-white px-3 py-2 text-sm outline-none" required="true"/>
            </div>

            <!-- Players -->
            <div v-if="!isPlayerLogin" class="flex flex-col gap-2">
              <p class="text-[10px] font-black uppercase tracking-widest text-white/40">Players</p>
              <DropDownMultiple v-model="dataFilter.players" :options="optionsPlayer" />
            </div>

            <!-- Sessions -->
            <div class="flex flex-col gap-2">
              <p class="text-[10px] font-black uppercase tracking-widest text-white/40">Session Types</p>
              <DropDownMultiple v-model="dataFilter.sessions" :options="optionsSession"/>
            </div>

            <!-- Options -->
            <div class="flex flex-col gap-2">
              <p class="text-[10px] font-black uppercase tracking-widest text-white/40">Training Options</p>
              <DropDownOptionsOfSession v-model="dataFilter.options" :seletedSessionShow="dataFilter.sessions"/>
            </div>

            <!-- Show button -->
            <div class="flex items-end">
              <button
                type="button"
                class="w-full rounded-xl bg-[#C00000] hover:bg-red-700 transition px-4 py-2.5 text-sm font-black uppercase tracking-wider text-white"
                @click.stop.prevent="getStatistic"
              >Show Statistics</button>
            </div>
          </div>
        </div>

        <!-- Loading spinner -->
        <div v-if="loading" class="flex justify-center my-8">
          <div class="w-10 h-10 border-4 border-red-500 border-b-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Data tables -->
        <div class="space-y-1 rounded-2xl overflow-hidden border border-white/10">

    <div>
      <batting-totals
      v-if="Object.keys(tableData.batting.totals).includes('team')"
      :players="tableData.batting.totals.players"
      :team="tableData.batting.totals.team"/>
    </div>
    <div>
      <batting-percentage
      v-if="Object.keys(tableData.batting.percents).includes('team')"
      :players="tableData.batting.percents.players"
      :team="tableData.batting.percents.team"/>
    </div>
    <div>
      <average-and-max-velocity
      v-if="Object.keys(tableData.batting.average_velocity_breakdown).includes('team')
      || Object.keys(tableData.batting.max_velocity_breakdown).includes('team')"
      :players="tableData.batting.average_velocity_breakdown.players"
      :team="tableData.batting.average_velocity_breakdown.team"
      :playersvelo="tableData.batting.max_velocity_breakdown.players"
      :teamvelo="tableData.batting.max_velocity_breakdown.team"
      />
    </div>
    <div>
      <BattingLeftTOH_QOS
      v-if="Object.keys(tableData.batting['TOH-L']).includes('team')
      || Object.keys(tableData.batting['QOH-L']).includes('team')"
      :players="tableData.batting['TOH-L'].players"
      :team="tableData.batting['TOH-L'].team"
      :playersQ="tableData.batting['QOH-L'].players"
      :teamQ="tableData.batting['QOH-L'].team"
      />
    </div>
    <div>
      <BattingRightTOH_QOS
        v-if="Object.keys(tableData.batting['TOH-R']).includes('team') || Object.keys(tableData.batting['QOH-R']).includes('team')"
        :players="tableData.batting['TOH-R'].players"
        :team="tableData.batting['TOH-R'].team"
        :playersQ="tableData.batting['QOH-R'].players"
        :teamQ="tableData.batting['QOH-R'].team"
      />
    </div>
    <div>
      <BattingMiddleTOH_QOS
        v-if="Object.keys(tableData.batting['TOH-M']).includes('team') || Object.keys(tableData.batting['QOH-M']).includes('team')"
        :players="tableData.batting['TOH-M'].players"
        :team="tableData.batting['TOH-M'].team"
        :playersQ="tableData.batting['QOH-M'].players"
        :teamQ="tableData.batting['QOH-M'].team"
      />
    </div>
    <div class="bullpen-stat-section space-y-4">
    <div class="bg-fungo-gray3">
      <PitchingTotals
        v-if="Object.keys(tableData.bullpen.totals).includes('team') && Object.keys(tableData.bullpen.totals).includes('team')"
        :players="tableData.bullpen.totals.players"
        :team="tableData.bullpen.totals.team"
      />
    </div>
    <div>
      <PitchingPercentage
        v-if="Object.keys(tableData.bullpen.percents).includes('team') && Object.keys(tableData.bullpen.percents).includes('team')"
        :players="tableData.bullpen.percents.players"
        :team="tableData.bullpen.percents.team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <PitchingAverageTopVelocity
        v-if="Object.keys(tableData.bullpen.average_velocity_breakdown).includes('team') || Object.keys(tableData.bullpen.top_velocity_breakdown).includes('team')"
        :players="tableData.bullpen.average_velocity_breakdown.players"
        :team="tableData.bullpen.average_velocity_breakdown.team"
        :playersTOP="tableData.bullpen.top_velocity_breakdown.players"
        :teamTOP="tableData.bullpen.top_velocity_breakdown.team"
      />
    </div>
    <div>
      <PitchingFastGroundBallPercentages
        v-if="Object.keys(tableData.bullpen['TOT-FAST']).includes('team') || Object.keys(tableData.bullpen['TRAJECTORY-GB']).includes('team')"
        :players="tableData.bullpen['TOT-FAST'].players"
        :team="tableData.bullpen['TOT-FAST'].team"
        :playersGB="tableData.bullpen['TRAJECTORY-GB'].players"
        :teamGB="tableData.bullpen['TRAJECTORY-GB'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <PitchingCurveLinePercentages
        v-if="Object.keys(tableData.bullpen['TOT-CURVE']).includes('team') || Object.keys(tableData.bullpen['TRAJECTORY-LD']).includes('team')"
        :players="tableData.bullpen['TOT-CURVE']['players'] ? tableData.bullpen['TOT-CURVE']['players']: []"
        :team="tableData.bullpen['TOT-CURVE']['team']"
        :playersLD="Object.keys(tableData.bullpen['TRAJECTORY-LD']).includes('players') ? tableData.bullpen['TRAJECTORY-LD']['players']: []"
        :teamLD="tableData.bullpen['TRAJECTORY-LD']['team']"
      />
    </div>
    <div>
      <PitchingChangeupFlyPercentages
        v-if="tableData.bullpen['TOT-CHANGE'].team || tableData.bullpen['TRAJECTORY-FB'].team"
        :players="tableData.bullpen['TOT-CHANGE'].players"
        :team="tableData.bullpen['TOT-CHANGE'].team"
        :playersFB="tableData.bullpen['TRAJECTORY-FB'].players"
        :teamFB="tableData.bullpen['TRAJECTORY-FB'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <PitchingSliderFoulPercentages
        v-if="tableData.bullpen['TOT-SLIDER'].team || tableData.bullpen['TRAJECTORY-FOUL'].team"
        :players="tableData.bullpen['TOT-SLIDER'].players"
        :team="tableData.bullpen['TOT-SLIDER'].team"
        :playersFOUL="tableData.bullpen['TRAJECTORY-FOUL'].players"
        :teamFOUL="tableData.bullpen['TRAJECTORY-FOUL'].team"
      />
    </div>
    <div>
      <PitchingOtherPercentages
        v-if="tableData.bullpen['TOT-OTHER'] && tableData.bullpen['TOT-OTHER'].team && tableData.bullpen['TOT-OTHER'].players"
        :players="tableData.bullpen['TOT-OTHER'].players"
        :team="tableData.bullpen['TOT-OTHER'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <PitchingStrikeFastballPercentages
        v-if="tableData.bullpen['TOT-FAST-STRIKE'] && tableData.bullpen['TOT-FAST-STRIKE'].team && tableData.bullpen['TOT-FAST-STRIKE'].players"
        :players="tableData.bullpen['TOT-FAST-STRIKE'].players"
        :team="tableData.bullpen['TOT-FAST-STRIKE'].team"
      />
    </div>
    <div>
      <PitchingPopFlies
      v-if="Object.keys(tableData.bullpen['TRAJECTORY-PF']).includes('team')"
        :players="tableData.bullpen['TRAJECTORY-PF'].players"
        :team="tableData.bullpen['TRAJECTORY-PF'].team"
      />
    </div>
    <div>
      <PitchingStrikeCurveballPercentage
        v-if="tableData.bullpen['TOT-CURVE-STRIKE'] && tableData.bullpen['TOT-CURVE-STRIKE'].team && tableData.bullpen['TOT-CURVE-STRIKE'].players"
        :players="tableData.bullpen['TOT-CURVE-STRIKE'].players"
        :team="tableData.bullpen['TOT-CURVE-STRIKE'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <PitchingStrikechangeupPercentage
        v-if="tableData.bullpen['TOT-CHANGE-STRIKE'] && tableData.bullpen['TOT-CHANGE-STRIKE'].team && tableData.bullpen['TOT-CHANGE-STRIKE'].players"
        :players="tableData.bullpen['TOT-CHANGE-STRIKE'].players"
        :team="tableData.bullpen['TOT-CHANGE-STRIKE'].team"
      />
    </div>
    <div>
      <PitchingStrikeSliderPercentage
        v-if="tableData.bullpen['TOT-SLIDER-STRIKE'] && tableData.bullpen['TOT-SLIDER-STRIKE'].team && tableData.bullpen['TOT-SLIDER-STRIKE'].players"
        :players="tableData.bullpen['TOT-SLIDER-STRIKE'].players"
        :team="tableData.bullpen['TOT-SLIDER-STRIKE'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <PitchingStrikeOtherPercentage
        v-if="tableData.bullpen['TOT-OTHER-STRIKE'] && tableData.bullpen['TOT-OTHER-STRIKE'].team && tableData.bullpen['TOT-OTHER-STRIKE'].players"
        :players="tableData.bullpen['TOT-OTHER-STRIKE'].players"
        :team="tableData.bullpen['TOT-OTHER-STRIKE'].team"
      />
    </div>
    </div>
    <div class="other-stat-section space-y-4">
    <div class="bg-fungo-gray3">
      <CageLaunchTotal
        v-if="tableData.cage['launch-angle-totals'] && tableData.cage['launch-angle-totals'].team && tableData.cage['launch-angle-totals'].players"
        :players="tableData.cage['launch-angle-totals'].players"
        :team="tableData.cage['launch-angle-totals'].team"
      />
    </div>
    <div>
      <CageLaunchPercentage
        v-if="tableData.cage['launch-angle-percents'] && tableData.cage['launch-angle-percents'].team && tableData.cage['launch-angle-percents'].players"
        :players="tableData.cage['launch-angle-percents'].players"
        :team="tableData.cage['launch-angle-percents'].team"
      />
    </div>
    <div>
      <CageLaunchAverage
        v-if="tableData.cage['launch-angle-average-exit-velocity'] && tableData.cage['launch-angle-average-exit-velocity'].team && tableData.cage['launch-angle-average-exit-velocity'].players"
        :players="tableData.cage['launch-angle-average-exit-velocity'].players"
        :team="tableData.cage['launch-angle-average-exit-velocity'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <CageSprayTotals
        v-if="tableData.cage['spray-angle-totals'] && tableData.cage['spray-angle-totals'].team && tableData.cage['spray-angle-totals'].players"
        :players="tableData.cage['spray-angle-totals'].players"
        :team="tableData.cage['spray-angle-totals'].team"
      />
    </div>
    <div>
      <CageSprayPercentages
        v-if="tableData.cage['spray-angle-percents'] && tableData.cage['spray-angle-percents'].team && tableData.cage['spray-angle-percents'].players"
        :players="tableData.cage['spray-angle-percents'].players"
        :team="tableData.cage['spray-angle-percents'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <CageSprayAverage
        v-if="tableData.cage['spray-angle-average-exit-velocity'] && tableData.cage['spray-angle-average-exit-velocity'].team && tableData.cage['spray-angle-average-exit-velocity'].players"
        :players="tableData.cage['spray-angle-average-exit-velocity'].players"
        :team="tableData.cage['spray-angle-average-exit-velocity'].team"
      />
    </div>
    <div>
      <WeightedTotals
        v-if="tableData.weight_ball.totals && tableData.weight_ball.totals.team && tableData.weight_ball.totals.players"
        :players="tableData.weight_ball.totals.players"
        :team="tableData.weight_ball.totals.team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <WeightedAverage
        v-if="tableData.weight_ball['average-velocity'] && tableData.weight_ball['average-velocity'].team && tableData.weight_ball['average-velocity'].players"
        :players="tableData.weight_ball['average-velocity'].players"
        :team="tableData.weight_ball['average-velocity'].team"
      />
    </div>
    <div>
      <WeightedMax
        v-if="tableData.weight_ball['max-velocity'] && tableData.weight_ball['max-velocity'].team && tableData.weight_ball['max-velocity'].players"
        :players="tableData.weight_ball['max-velocity'].players"
        :team="tableData.weight_ball['max-velocity'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <ExitTotals
        v-if="tableData.exit_velocity.totals && tableData.exit_velocity.totals.team && tableData.exit_velocity.totals.players"
        :players="tableData.exit_velocity.totals.players"
        :team="tableData.exit_velocity.totals.team"
      />
    </div>
    <div>
      <ExitPercentage
        v-if="tableData.exit_velocity.percents && tableData.exit_velocity.percents.team && tableData.exit_velocity.percents.players"
        :players="tableData.exit_velocity.percents.players"
        :team="tableData.exit_velocity.percents.team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <ExitAverage
        v-if="tableData.exit_velocity['average-velocity'] && tableData.exit_velocity['average-velocity'].team && tableData.exit_velocity['average-velocity'].players"
        :players="tableData.exit_velocity['average-velocity'].players"
        :team="tableData.exit_velocity['average-velocity'].team"
      />
    </div>
    <div>
      <ExitTop
        v-if="tableData.exit_velocity['top-velocity'] && tableData.exit_velocity['top-velocity'].team && tableData.exit_velocity['top-velocity'].players"
        :players="tableData.exit_velocity['top-velocity'].players"
        :team="tableData.exit_velocity['top-velocity'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <LongDistanceTotal
        v-if="tableData.long_toss['totals-distances'] && tableData.long_toss['totals-distances'].team && tableData.long_toss['totals-distances'].players"
        :players="tableData.long_toss['totals-distances'].players"
        :team="tableData.long_toss['totals-distances'].team"
      />
    </div>
    <div>
      <LongDistancePercentage
        v-if="tableData.long_toss['percents-distances'] && tableData.long_toss['percents-distances'].team && tableData.long_toss['percents-distances'].players"
        :players="tableData.long_toss['percents-distances'].players"
        :team="tableData.long_toss['percents-distances'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <LongDistanceAverage
        v-if="tableData.long_toss['average-distance'] && tableData.long_toss['average-distance'].team && tableData.long_toss['average-distance'].players"
        :players="tableData.long_toss['average-distance'].players"
        :team="tableData.long_toss['average-distance'].team"
      />
    </div>
    <div>
      <LongTotals
        v-if="tableData.long_toss['total-hops'] && tableData.long_toss['total-hops'].team && tableData.long_toss['total-hops'].players"
        :players="tableData.long_toss['total-hops'].players"
        :team="tableData.long_toss['total-hops'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <LongMax
        v-if="tableData.long_toss['max-hops'] && tableData.long_toss['max-hops'].team && tableData.long_toss['max-hops'].players"
        :players="tableData.long_toss['max-hops'].players"
        :team="tableData.long_toss['max-hops'].team"
      />
    </div>
    <div>
      <LongAverage
        v-if="tableData.long_toss['average-hops'] && tableData.long_toss['average-hops'].team && tableData.long_toss['average-hops'].players"
        :players="tableData.long_toss['average-hops'].players"
        :team="tableData.long_toss['average-hops'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <LiveABHitterBasic
        v-if="tableData.live['hitter-basic'] && tableData.live['hitter-basic'].team && tableData.live['hitter-basic'].players"
        :players="tableData.live['hitter-basic'].players"
        :team="tableData.live['hitter-basic'].team"
      />
    </div>
    <div>
      <LiveABHitterAdvance
        v-if="tableData.live['hitter-advance'] && tableData.live['hitter-advance'].team && tableData.live['hitter-advance'].players"
        :players="tableData.live['hitter-advance'].players"
        :team="tableData.live['hitter-advance'].team"
      />
    </div>
    <div>
      <LiveABPitcherBasic
        v-if="tableData.live['pitcher-basic'] && tableData.live['pitcher-basic'].team && tableData.live['pitcher-basic'].players"
        :players="tableData.live['pitcher-basic'].players"
        :team="tableData.live['pitcher-basic'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <LiveABPitcherAdvance
        v-if="tableData.live['pitcher-advance'] && tableData.live['pitcher-advance'].team && tableData.live['pitcher-advance'].players"
        :players="tableData.live['pitcher-advance'].players"
        :team="tableData.live['pitcher-advance'].team"
      />
    </div>
    <div class="bg-fungo-gray3">
      <LiveABPitcherBreakDown
        v-if="tableData.live['hitter-pitch-breakdown'] && tableData.live['hitter-pitch-breakdown'].team && tableData.live['hitter-pitch-breakdown'].players"
        :player="tableData.live['hitter-pitch-breakdown'].players"
        :team="tableData.live['hitter-pitch-breakdown'].team"
      />
    </div>
    <div>
      <LiveABHitterContact
        v-if="tableData.live['hitter-contact'] && tableData.live['hitter-contact'].team && tableData.live['hitter-contact'].players"
        :player="tableData.live['hitter-contact'].players"
        :team="tableData.live['hitter-contact'].team"
      />
    </div>

    <div>
      <LiveABHitterTrajectory
        v-if="tableData.live['hitter-trajectory'] && tableData.live['hitter-trajectory'].team && tableData.live['hitter-trajectory'].players"
        :player="tableData.live['hitter-trajectory'].players"
        :team="tableData.live['hitter-trajectory'].team"
      />
    </div>
    <div>
      <LiveABHitterVelocity
        v-if="tableData.live['hitter-velocity'] && tableData.live['hitter-velocity'].team && tableData.live['hitter-velocity'].players"
        :player="tableData.live['hitter-velocity'].players"
        :team="tableData.live['hitter-velocity'].team"
      />
    </div>
    <!-- Pitcher tables pendientes hitter-->

    <div>
      <LiveABPitcherPitchBreakdown
        v-if="tableData.live['pitcher-pitch-breakdown'] && tableData.live['pitcher-pitch-breakdown'].team && tableData.live['pitcher-pitch-breakdown'].players"
        :player="tableData.live['pitcher-pitch-breakdown'].players"
        :team="tableData.live['pitcher-pitch-breakdown'].team"
      />
    </div>

    <div>
      <LiveABPitcherContact
        v-if="tableData.live['pitcher-contact'] && tableData.live['pitcher-contact'].team && tableData.live['pitcher-contact'].players"
        :player="tableData.live['pitcher-contact'].players"
        :team="tableData.live['pitcher-contact'].team"
      />
    </div>
    <div>
      <LiveABPitcherVelocity
        v-if="tableData.live['pitcher-velocity'] && tableData.live['pitcher-velocity'].team && tableData.live['pitcher-velocity'].players"
        :player="tableData.live['pitcher-velocity'].players"
        :team="tableData.live['pitcher-velocity'].team"
      />
    </div>

    </div>

        </div><!-- end data tables -->
      </div><!-- end px wrapper -->
    </div><!-- end bg wrapper -->
  </Layout>
</template>

<style scoped>
.bullpen-stat-section > div {
  padding: 1rem;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 1rem;
  background: rgba(10, 16, 32, 0.8);
  box-shadow: 0 14px 36px rgba(0, 0, 0, 0.28);
}

.bullpen-stat-section > div:empty {
  display: none;
}

.bullpen-stat-section .bg-fungo-gray3 {
  background: rgba(10, 16, 32, 0.8) !important;
}

.bullpen-stat-section :deep(h1) {
  color: #f8fafc !important;
  font-size: 0.95rem !important;
  text-align: center;
  margin: 0 0 0.9rem !important;
  font-weight: 900 !important;
  letter-spacing: 0.07em;
  text-transform: uppercase;
}

.bullpen-stat-section :deep(section) {
  margin-top: 0 !important;
  padding: 0 !important;
  overflow-x: auto;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 0.85rem;
  background: rgba(2, 8, 23, 0.65);
}

.bullpen-stat-section :deep(table) {
  width: 100%;
  min-width: 620px;
  border-collapse: separate !important;
  border-spacing: 0 !important;
  color: #e5e7eb !important;
}

.bullpen-stat-section :deep(thead th) {
  padding: 0.7rem 0.55rem !important;
  color: #e2e8f0 !important;
  font-size: 0.72rem !important;
  font-weight: 900 !important;
  letter-spacing: 0.06em;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  white-space: nowrap;
  background: rgba(15, 23, 42, 0.95) !important;
}

.bullpen-stat-section :deep(tbody td) {
  padding: 0.62rem 0.55rem !important;
  text-align: center;
  color: #e5e7eb !important;
  font-size: 0.8rem !important;
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
  border-right: 1px solid rgba(255, 255, 255, 0.06);
  background: transparent !important;
}

.bullpen-stat-section :deep(thead th:first-child),
.bullpen-stat-section :deep(tbody td:first-child) {
  text-align: left;
  padding-left: 0.85rem !important;
  font-weight: 800;
}

.bullpen-stat-section :deep(tbody tr:first-child td) {
  background: rgba(192, 0, 0, 0.16) !important;
  color: #fee2e2 !important;
  font-weight: 900 !important;
}

.bullpen-stat-section :deep(tbody tr:nth-child(n+2):nth-child(odd) td) {
  background: rgba(255, 255, 255, 0.03) !important;
}

.bullpen-stat-section :deep(tbody tr:nth-child(n+2):nth-child(even) td) {
  background: rgba(148, 163, 184, 0.05) !important;
}

.bullpen-stat-section :deep(tbody tr:hover td) {
  background: rgba(59, 130, 246, 0.12) !important;
}

.bullpen-stat-section :deep(::-webkit-scrollbar) {
  width: 4px;
  height: 4px;
}

.bullpen-stat-section :deep(::-webkit-scrollbar-thumb) {
  background: #334155;
  border-radius: 8px;
}

.bullpen-stat-section :deep(::-webkit-scrollbar-thumb:active) {
  background: #1e293b;
}

.bullpen-stat-section :deep(::-webkit-scrollbar-track) {
  background: rgba(15, 23, 42, 0.85);
  border-radius: 8px;
}

.other-stat-section > div {
  padding: 1rem;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 1rem;
  background: rgba(10, 16, 32, 0.8);
  box-shadow: 0 14px 36px rgba(0, 0, 0, 0.28);
}

.other-stat-section > div:empty {
  display: none;
}

.other-stat-section .bg-fungo-gray3 {
  background: rgba(10, 16, 32, 0.8) !important;
}

.other-stat-section :deep(h1) {
  color: #f8fafc !important;
  font-size: 0.95rem !important;
  text-align: center;
  margin: 0 0 0.9rem !important;
  font-weight: 900 !important;
  letter-spacing: 0.07em;
  text-transform: uppercase;
}

.other-stat-section :deep(section) {
  margin-top: 0 !important;
  padding: 0 !important;
  overflow-x: auto;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 0.85rem;
  background: rgba(2, 8, 23, 0.65);
}

.other-stat-section :deep(table) {
  width: 100%;
  min-width: 620px;
  border-collapse: separate !important;
  border-spacing: 0 !important;
  color: #e5e7eb !important;
}

.other-stat-section :deep(thead th) {
  padding: 0.7rem 0.55rem !important;
  color: #e2e8f0 !important;
  font-size: 0.72rem !important;
  font-weight: 900 !important;
  letter-spacing: 0.06em;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  white-space: nowrap;
  background: rgba(15, 23, 42, 0.95) !important;
}

.other-stat-section :deep(tbody td) {
  padding: 0.62rem 0.55rem !important;
  text-align: center;
  color: #e5e7eb !important;
  font-size: 0.8rem !important;
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
  border-right: 1px solid rgba(255, 255, 255, 0.06);
  background: transparent !important;
}

.other-stat-section :deep(thead th:first-child),
.other-stat-section :deep(tbody td:first-child) {
  text-align: left;
  padding-left: 0.85rem !important;
  font-weight: 800;
}

.other-stat-section :deep(tbody tr:first-child td) {
  background: rgba(192, 0, 0, 0.16) !important;
  color: #fee2e2 !important;
  font-weight: 900 !important;
}

.other-stat-section :deep(tbody tr:nth-child(n+2):nth-child(odd) td) {
  background: rgba(255, 255, 255, 0.03) !important;
}

.other-stat-section :deep(tbody tr:nth-child(n+2):nth-child(even) td) {
  background: rgba(148, 163, 184, 0.05) !important;
}

.other-stat-section :deep(tbody tr:hover td) {
  background: rgba(59, 130, 246, 0.12) !important;
}

.other-stat-section :deep(::-webkit-scrollbar) {
  width: 4px;
  height: 4px;
}

.other-stat-section :deep(::-webkit-scrollbar-thumb) {
  background: #334155;
  border-radius: 8px;
}

.other-stat-section :deep(::-webkit-scrollbar-thumb:active) {
  background: #1e293b;
}

.other-stat-section :deep(::-webkit-scrollbar-track) {
  background: rgba(15, 23, 42, 0.85);
  border-radius: 8px;
}
</style>
