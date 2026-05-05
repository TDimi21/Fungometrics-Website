<script setup>
import { ref, onMounted, computed } from 'vue'
import {Carousel, Navigation, Pagination, Slide} from 'vue3-carousel'
import Layout from "../../layout/Layout.vue"
import {useUserStore} from "../../store/user";
import {usePlayerStore} from "../../store/players";
import {useTeamStore} from "../../store/team";
import PlayerCard from "../../components/PlayerCard.vue";
import { ChartCard, IndicatorChart, FeedsTable, TopTable } from '@/components/dashboard'
import DashboardSprayChart from '@/components/dashboard/DashboardSprayChart.vue'
import VelocityZoneChart from '@/components/dashboard/VelocityZoneChart.vue'
import SmTakeZoneChart from '@/components/dashboard/SmTakeZoneChart.vue'
import PitchHeatmapChart from '@/components/dashboard/PitchHeatmapChart.vue'
import PitchTypeStatsCard from '@/components/dashboard/PitchTypeStatsCard.vue'
import PlayerCompare from '@/components/dashboard/PlayerCompare.vue'
import { LabelField } from '@/components/form'
import { DropDownMultiple } from '@/components/shared'
import ArrowDownIcon from "@/components/icons/ArrowDownIcon.vue";
import useChart from '@/composables/useChart.js'
import useChartOptions from '@/composables/useChartOptions.js'
import { SearchIcon } from '@/components/icons'

const user = useUserStore();
const players = usePlayerStore();
const { team } = useTeamStore();

const playerSearch = ref('')
const feedTab = ref('feed')
const filteredPlayers = computed(() =>
  players.players?.filter(p => p.name?.full?.toLowerCase().includes(playerSearch.value.toLowerCase())) ?? []
)
const totalSessions = computed(() => players.players?.reduce((acc, p) => acc + (p.sessions_count ?? 0), 0) ?? 0)
const openAddPlayersModal = () => window.dispatchEvent(new Event('open-add-player-modal'))
const openChangeTeamModal = () => window.dispatchEvent(new Event('open-change-team-modal'))

const {
  ballStrike, ballStrikeSeries, isloading, directional,
  typeHitsBatting, pitchThrows, pitchVelocityAverage,
  typeHitsPitching, launchAngleAverage, smTake, contactSpray, formModel, getFilteredDataChart,
  seriesDinamicChart, getStaticChartData, loadOnMounted, monthsShow
} = useChart()
const { donutChartOptions, barChartOptions, radiaChartOptions, dinamicChartoptions } = useChartOptions()

const breakpoints = {
  640: {
    itemsToShow: 1,
    snapAlign: 'center',
  },

  768: {
    itemsToShow: 3,
    snapAlign: 'center',
  },
  // 1024 and up
  1024: {
    itemsToShow: 4,
    snapAlign: 'center',
  },
  1200: {
    itemsToShow: 5,
    snapAlign: 'center',
  }
}

const ranges = [
  { text: 'All time', value: 12 },
  { text: '1 Year', value: 10 },
  { text: '6 Months', value: 5 },
  { text: '3 Months', value: 2 },
  { text: '1 Month', value: 1 }
]

const optionsPlayer = ref()

const setPlayerData = () => {
  let player = {}
  for (const iterator of players.players) {
    let id = iterator.id
    player[id] = iterator.name.full
  }
  optionsPlayer.value = player
}

if (user.userData.type  != 'player') getStaticChartData()

onMounted(() => {
  loadOnMounted()
  setPlayerData()
})
</script>
<template>
  <Layout>
    <div class="min-h-screen bg-[#001440] text-white relative">

      <div class="relative z-10 px-4 py-6 lg:px-8 lg:py-8 pb-28 md:pb-10">

        <!-- Team + Players Row -->
        <div v-if="user.userData.type === 'coach'" class="mb-8 grid grid-cols-1 xl:grid-cols-[minmax(380px,1fr)_2fr] gap-5 items-start">
          <div class="space-y-4">
            <div
              class="relative overflow-hidden rounded-2xl border border-white/20 shadow-2xl backdrop-blur-xl p-4 lg:p-5 cursor-pointer hover:bg-white/15 transition min-h-[210px]"
              @click="openChangeTeamModal"
              title="Click to switch team"
            >
              <div
                v-if="team?.logo"
                class="absolute inset-0 bg-cover bg-center opacity-25"
                :style="{ backgroundImage: `url(${team.logo})` }"
              ></div>
              <div class="absolute inset-0 bg-[#001030]/80"></div>

              <div class="relative z-10 flex flex-col h-full justify-between gap-4">
                <div class="flex items-center gap-4">
                  <div class="h-16 w-16 overflow-hidden rounded-xl border border-white/20 bg-slate-950 shadow-lg shrink-0">
                    <img v-if="team?.logo" :src="team.logo" alt="Team" class="h-full w-full object-cover" />
                    <div v-else class="h-full w-full flex items-center justify-center text-2xl font-black text-white/30">FM</div>
                  </div>
                  <div>
                    <h1 class="text-2xl font-black leading-tight text-white md:text-3xl">{{ team?.name ?? 'My Team' }}</h1>
                    <p class="mt-1 text-xs md:text-sm text-white/60">Player development tracking · Sessions · Stats</p>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    class="col-span-2 rounded-xl border border-red-400/60 bg-red-500/20 p-2 text-center text-xs font-black tracking-wider text-red-200 hover:bg-red-500/30 transition"
                    @click.stop="openAddPlayersModal"
                  >
                    + ADD PLAYERS
                  </button>
                  <div class="rounded-xl border border-white/10 bg-white/10 p-3 text-center">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-white/45">Players</div>
                    <div class="mt-1 text-2xl font-black text-white">{{ players.players?.length ?? 0 }}</div>
                  </div>
                  <div class="rounded-xl border border-white/10 bg-white/10 p-3 text-center">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-white/45">Sessions</div>
                    <div class="mt-1 text-2xl font-black text-white">{{ totalSessions }}</div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <div class="min-w-0">
            <div class="mb-3 flex items-center justify-between">
              <h2 class="text-xl font-black text-white md:text-2xl">Players</h2>
              <div class="relative w-full max-w-xs ml-4">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-white/35" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input v-model="playerSearch" placeholder="Search players" class="w-full rounded-full border border-white/10 bg-white/10 py-2.5 pl-10 pr-4 text-sm font-semibold text-white placeholder:text-white/35 outline-none backdrop-blur-xl" />
              </div>
            </div>
            <div class="player-cards-scroll flex flex-nowrap gap-4 overflow-x-scroll pb-3 snap-x snap-mandatory">
              <div class="flex-none w-[220px] snap-start" v-for="item in filteredPlayers" :key="item.id">
                <PlayerCard :item="item" />
              </div>
            </div>
          </div>
        </div>

        <!-- Feeds + Top Table Row -->
        <div v-if="user.userData.type === 'coach'" class="mb-8">
          <div class="rounded-2xl border border-[#004080]/60 bg-[#002060]/50 backdrop-blur-xl p-5 shadow-xl">
            <!-- Toggle pills -->
            <div class="flex gap-2 mb-4">
              <button
                @click="feedTab = 'feed'"
                class="px-5 py-1.5 rounded-full text-xs font-black uppercase tracking-wider transition-all border"
                :class="feedTab === 'feed'
                  ? 'bg-[#C00000] border-[#C00000] text-white shadow'
                  : 'bg-transparent border-white/20 text-white/50 hover:border-white/40 hover:text-white'"
              >Activity Feed</button>
              <button
                @click="feedTab = 'top10'"
                class="px-5 py-1.5 rounded-full text-xs font-black uppercase tracking-wider transition-all border"
                :class="feedTab === 'top10'
                  ? 'bg-[#C00000] border-[#C00000] text-white shadow'
                  : 'bg-transparent border-white/20 text-white/50 hover:border-white/40 hover:text-white'"
              >Top 10</button>
            </div>
            <feeds-table v-if="feedTab === 'feed'" />
            <top-table v-else />
          </div>
        </div>

        <!-- Charts + Stats layout -->
        <div class="mt-2 space-y-5">

          <!-- Charts grid -->
          <div v-if="!isloading" class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <!-- Row 1: Spray chart + Player Comparison side by side -->
            <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
              <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">Contact vs Balls In/Out of Zone</h3>
              <DashboardSprayChart :contactSpray="contactSpray" :ballStrike="ballStrike" />
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
              <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">Player Comparison — Contact for LiveAB &amp; BP</h3>
              <PlayerCompare />
            </div>

            <!-- Batting contact -->
            <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
              <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">Batting &amp; LiveAB Contact</h3>
              <div class="flex flex-col space-y-3 mt-2">
                <indicator-chart :labelTitle="`GB ${typeHitsBatting.GB.count}`" :labelValue="typeHitsBatting.GB.percent" color="#F8A488"/>
                <indicator-chart :labelTitle="`LD ${typeHitsBatting.LD.count}`" :labelValue="typeHitsBatting.LD.percent" color="#ADE8F4"/>
                <indicator-chart :labelTitle="`FLY ${typeHitsBatting.FLY.count}`" :labelValue="typeHitsBatting.FLY.percent" color="#8676FF"/>
                <indicator-chart :labelTitle="`SM/F ${typeHitsBatting['SM/F'].count}`" :labelValue="typeHitsBatting['SM/F'].percent" color="#FFB457"/>
                <indicator-chart :labelTitle="`TAKE ${typeHitsBatting.TAKE.count}`" :labelValue="typeHitsBatting.TAKE.percent" color="#03F1E3"/>
              </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
              <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">Pitches Thrown</h3>
              <apexchart width="100%" type="bar" height="300" :options="barChartOptions(pitchThrows.totals)" :series="[{ name: 'Thrown', data: [pitchThrows.totals, pitchThrows.FB, pitchThrows.CH, pitchThrows.CB, pitchThrows.SL, pitchThrows.OTHER] }]"/>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
              <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">Directional</h3>
              <apexchart width="100%" type="radialBar" :options="radiaChartOptions" :series="[directional.RIGHT.percent, directional.MIDDLE.percent, directional.LEFT.percent]"/>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
              <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">Average Pitch Velocity</h3>
              <apexchart width="100%" type="bar" height="300" :options="barChartOptions(pitchVelocityAverage.totals / 5, 2, 2)" :series="[{ name: 'Average', data: [pitchVelocityAverage.FB, pitchVelocityAverage.CH, pitchVelocityAverage.CB, pitchVelocityAverage.SL, pitchVelocityAverage.OTHER] }]"/>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
              <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">Launch Angle Avg Velocity</h3>
              <apexchart width="100%" type="bar" height="300" :options="barChartOptions(pitchThrows.totals, 1, 4)" :series="[{ name: 'Average', data: [launchAngleAverage['-0'], launchAngleAverage['0-6'], launchAngleAverage['6-15'], launchAngleAverage['15-24'], launchAngleAverage['24-40'], launchAngleAverage['40-55'], launchAngleAverage['55+']] }]"/>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
              <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">S/M Take</h3>
              <apexchart width="100%" type="bar" height="300" :options="barChartOptions(smTake.totals, 1, 2)" :series="[{ name: 'S/M', data: [smTake.FB.SM, smTake.CH.SM, smTake.CB.SM, smTake.SL.SM, smTake.OTHER.SM]}, { name: 'Take', data: [smTake.FB.TAKE, smTake.CH.TAKE, smTake.CB.TAKE, smTake.SL.TAKE, smTake.OTHER.TAKE]}]"/>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
              <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">Pitching &amp; LiveAB Contact</h3>
              <div class="flex flex-col space-y-3 mt-2">
                <indicator-chart :labelTitle="`GB ${typeHitsPitching.GB.count}`" :labelValue="typeHitsPitching.GB.percent" color="#F8A488"/>
                <indicator-chart :labelTitle="`FLY ${typeHitsPitching.FLY.count}`" :labelValue="typeHitsPitching.FLY.percent" color="#8676FF"/>
                <indicator-chart :labelTitle="`LD ${typeHitsPitching.LD.count}`" :labelValue="typeHitsPitching.LD.percent" color="#ADE8F4"/>
                <indicator-chart :labelTitle="`SM/F ${typeHitsPitching['SM'].count}`" :labelValue="typeHitsPitching['SM'].percent" color="#FFB457"/>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 col-span-full">
              <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
                <VelocityZoneChart />
              </div>
              <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
                <PitchHeatmapChart />
              </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4">
              <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">Pitch Type Breakdown</h3>
              <PitchTypeStatsCard />
            </div>


          </div>
          <div v-else class="text-white/50 text-center py-20">Loading charts...</div>

          <!-- Live Stats — bottom bar -->
          <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-5">
            <div class="flex items-center gap-3 mb-4">
              <h2 class="text-lg font-black text-white">Live Stats</h2>
              <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="flex items-center justify-between rounded-xl border border-white/10 bg-white/10 px-4 py-3">
                <span class="text-sm font-bold text-white/55">Strike %</span>
                <span class="text-2xl font-black text-white">{{ ballStrike.strikes.percent ?? '--' }}%</span>
              </div>
              <div class="flex items-center justify-between rounded-xl border border-white/10 bg-white/10 px-4 py-3">
                <span class="text-sm font-bold text-white/55">Avg Pitch Velo</span>
                <span class="text-2xl font-black text-white">{{ pitchVelocityAverage?.FB ?? '--' }}</span>
              </div>
              <div class="flex items-center justify-between rounded-xl border border-white/10 bg-white/10 px-4 py-3">
                <span class="text-sm font-bold text-white/55">Total Pitches</span>
                <span class="text-2xl font-black text-white">{{ pitchThrows?.totals ?? '--' }}</span>
              </div>
            </div>
          </div>

        </div>

      </div>
    </div>
  </Layout>
</template>

<style scoped>
.player-cards-scroll {
  scrollbar-width: auto;
  scrollbar-color: #e10600 rgba(255, 255, 255, 0.15);
}

.player-cards-scroll::-webkit-scrollbar {
  height: 10px;
}

.player-cards-scroll::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.15);
  border-radius: 9999px;
}

.player-cards-scroll::-webkit-scrollbar-thumb {
  background: #e10600;
  border-radius: 9999px;
}
</style>
