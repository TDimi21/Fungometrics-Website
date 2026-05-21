<script setup>
import axios from 'axios'
import { ref, onMounted, watch } from 'vue'
import Layout from '@/layout/Layout.vue'
import { useTeamStore } from "@/store/team"
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
const { axiosGet } = useAxiosAuth()

const props = defineProps({
  slug: {
    type: String,
    required: true
  }
})

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

    if(Object.keys(dataFilter.value.options).length <= 0){
      throw new Error('Select a training option!')
    }

    let id = dataTeam.value.selectTeam == '' ? teams[0].id : dataTeam.value.selectTeam
    axiosGet('result/statistics/'+ id, {
      dates: 	[  dataFilter.value.sinceWhen, dataFilter.value.until],
      players: dataFilter.value.players,
      options: dataFilter.value.options
    }).then((data)=> {
      console.log(data.data.data)
      setBattingAllTables(data.data.data)
      setBullpenAllTables(data.data.data)
      setCageAllTables(data.data.data)
      setExitVelocityTables(data.data.data)
      setLongTossTables(data.data.data)
      setWeigthBallTables(data.data.data)
      setLiveTables(data.data.data)
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

onMounted(() => {
  //getStatistic()
  setPlayerList()
})

const format = (current_datetime)=>{
    let formatted_date = current_datetime.getFullYear() + "/" + (current_datetime.getMonth() + 1) + "/" + current_datetime.getDate()
    return formatted_date;
}

const setPlayerList = async (idTeam) =>{
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
  let keys = Object.keys(tableData.value.batting)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(data['batting'], key)){
      const elementP = data['batting'][key]['players']
      const elementT = data['batting'][key]['team_totals']
      tableData.value.batting[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.batting[key]['team'] = elementT
    }
  }
}

const setBullpenAllTables = (data) => {
  let keys = Object.keys(tableData.value.bullpen)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(data['bullpen'], key)){
      const elementP = data['bullpen'][key]['players']
      const elementT = data['bullpen'][key]['team_totals']
      tableData.value.bullpen[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.bullpen[key]['team'] = elementT
    }
  }
}


const setCageAllTables = (data) => {
  let keys = Object.keys(tableData.value.cage)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(data['cage'], key)){
      const elementP = data['cage'][key]['players']
      const elementT = data['cage'][key]['team_totals']
      tableData.value.cage[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.cage[key]['team'] = elementT
    }
  }
}

const setExitVelocityTables = (data) => {
  let keys = Object.keys(tableData.value.exit_velocity)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(data['exit_velocity'], key)){
      const elementP = data['exit_velocity'][key]['players']
      const elementT = data['exit_velocity'][key]['team_totals']
      tableData.value.exit_velocity[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.exit_velocity[key]['team'] = elementT
    }
  }
}

const setLongTossTables = (data) => {
  let keys = Object.keys(tableData.value.long_toss)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(data['long_toss'], key)){
      const elementP = data['long_toss'][key]['players']
      const elementT = data['long_toss'][key]['team_totals']
      tableData.value.long_toss[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.long_toss[key]['team'] = elementT
    }
  }
}

const setWeigthBallTables = (data) => {
  let keys = Object.keys(tableData.value.weight_ball)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(data['weight_ball'], key)){
      const elementP = data['weight_ball'][key]['players']
      const elementT = data['weight_ball'][key]['team_totals']
      tableData.value.weight_ball[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.weight_ball[key]['team'] = elementT
    }
  }
}

const setLiveTables = (data) => {
  let keys = Object.keys(tableData.value.live)
  for (const key of keys) {
    if(Object.hasOwnProperty.call(data['live'], key)){
      const elementP = data['live'][key]['players']
      const elementT = data['live'][key]['team_totals']
      tableData.value.live[key]['players'] = addNameToPlayersData(elementP)
      tableData.value.live[key]['team'] = elementT
    }
  }
	console.log("TableData", tableData.value);
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
    <div class="min-h-screen bg-[#001440] text-white">
      <div class="px-4 py-6 lg:px-8 lg:py-8 pb-28 md:pb-10">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-2xl font-black uppercase tracking-widest text-white md:text-3xl">Statistics</h1>
          <button
            type="button"
            class="rounded-xl border border-red-400/60 bg-red-500/20 px-4 py-2 text-xs font-black tracking-wider text-red-200 hover:bg-red-500/30 transition"
            @click="$emit('open-add-player')"
            onclick="window.dispatchEvent(new Event('open-add-player-modal'))"
          >+ ADD PLAYERS</button>
        </div>

        <!-- Filters card -->
        <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-5 mb-6">
          <h2 class="text-xs font-black uppercase tracking-widest text-white/40 mb-4">Filters</h2>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- Team selector -->
            <div class="lg:col-span-2">
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

            <!-- Date range -->
            <div class="flex flex-col gap-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-white/40">Since When</label>
              <InputBase v-model="dataFilter.sinceWhen" inputType="date" inputClasses="w-full rounded-lg border border-white/20 bg-white/10 text-white px-3 py-2 text-sm outline-none" required="true"/>
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-white/40">Until</label>
              <InputBase v-model="dataFilter.until" inputType="date" inputClasses="w-full rounded-lg border border-white/20 bg-white/10 text-white px-3 py-2 text-sm outline-none" required="true"/>
            </div>

            <!-- Players -->
            <div class="flex flex-col gap-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-white/40">Players</label>
              <DropDownMultiple v-model="dataFilter.players" :options="optionsPlayer" />
            </div>

            <!-- Sessions -->
            <div class="flex flex-col gap-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-white/40">Session Types</label>
              <DropDownMultiple v-model="dataFilter.sessions" :options="optionsSession"/>
            </div>

            <!-- Options -->
            <div class="flex flex-col gap-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-white/40">Training Options</label>
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

    <div class="bg-fungo-gray3">
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
    <div class="bg-fungo-gray3">
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
    <div class="bg-fungo-gray3">
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

    <LiveABHitterTrajectory
      v-if="tableData.live['hitter-trajectory'] && tableData.live['hitter-trajectory'].team && tableData.live['hitter-trajectory'].players"
      :player="tableData.live['hitter-trajectory'].players"
      :team="tableData.live['hitter-trajectory'].team"
    />
    <LiveABHitterVelocity
      v-if="tableData.live['hitter-velocity'] && tableData.live['hitter-velocity'].team && tableData.live['hitter-velocity'].players"
      :player="tableData.live['hitter-velocity'].players"
      :team="tableData.live['hitter-velocity'].team"
    />
    <!-- Pitcher tables pendientes hitter-->

    <LiveABPitcherPitchBreakdown
      v-if="tableData.live['pitcher-pitch-breakdown'] && tableData.live['pitcher-pitch-breakdown'].team && tableData.live['pitcher-pitch-breakdown'].players"
      :player="tableData.live['pitcher-pitch-breakdown'].players"
      :team="tableData.live['pitcher-pitch-breakdown'].team"
    />

    <LiveABPitcherContact
      v-if="tableData.live['pitcher-contact'] && tableData.live['pitcher-contact'].team && tableData.live['pitcher-contact'].players"
      :player="tableData.live['pitcher-contact'].players"
      :team="tableData.live['pitcher-contact'].team"
    />
    <LiveABPitcherVelocity
      v-if="tableData.live['pitcher-velocity'] && tableData.live['pitcher-velocity'].team && tableData.live['pitcher-velocity'].players"
      :player="tableData.live['pitcher-velocity'].players"
      :team="tableData.live['pitcher-velocity'].team"
    />

        </div><!-- end data tables -->
      </div><!-- end px wrapper -->
    </div><!-- end bg wrapper -->
  </Layout>
</template>
