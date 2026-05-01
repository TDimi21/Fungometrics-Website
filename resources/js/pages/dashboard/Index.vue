<script setup>
import { ref, onMounted, watch } from 'vue'
import {Carousel, Navigation, Pagination, Slide} from 'vue3-carousel'
import Layout from "../../layout/Layout.vue"
import {useUserStore} from "../../store/user";
import {usePlayerStore} from "../../store/players";
import PlayerCard from "../../components/PlayerCard.vue";
import { ChartCard, IndicatorChart, FeedsTable, TopTable } from '@/components/dashboard'
import { LabelField } from '@/components/form'
import { DropDownMultiple } from '@/components/shared'
import ArrowDownIcon from "@/components/icons/ArrowDownIcon.vue";
import useChart from '@/composables/useChart.js'
import useChartOptions from '@/composables/useChartOptions.js'
import { SearchIcon } from '@/components/icons'

const user = useUserStore();
const players = usePlayerStore();

const {
  ballStrike, ballStrikeSeries, isloading, directional,
  typeHitsBatting, pitchThrows, pitchVelocityAverage,
  typeHitsPitching, launchAngleAverage, smTake, formModel, getFilteredDataChart,
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

const chartToShow = [
  { text: 'Avg Exit Velocity for each Session', value: 1 },
  { text: 'Max Exit Velocity for each session', value: 2 },
  { text: 'Max Cage Distance', value: 3 },
  { text: 'Total strike percentage per session', value: 4 },
  { text: 'Max FB Velocity for each session', value: 5 },
  { text: 'Average throw velocity for each weight', value: 6 },
  { text: 'Max distance throw with 0 hops per session', value: 7 },
  { text: 'Average Training Exit Velo per Session', value: 8 },
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


    <div class="flex flex-row flex-wrap min-h-screen w-full font-fungo-poppins">
      <div v-if="user.userData.type === 'coach'" class="basis-full dash-header border">
        <div class="w-[90%] h-[75%]">
          <h1 class="text-fungo-red font-fungo-800 text-2xl">TEAM</h1>
          <carousel :breakpoints="breakpoints">
            <slide v-for="item in players.players" :key="item">
              <PlayerCard :item="item"/>
            </slide>

            <template #addons>
              <navigation/>
              <pagination/>
            </template>
          </carousel>

        </div>
      </div>
      <div class="basis-full dash-body border m-5 flex flex-col">
        <div class="h-max mb-10">
          <section class="bg-fungo-gray4 rounded-xl shadow-xl px-11 py-4 my-11">
            <form @submit.prevent="getFilteredDataChart" class="grid grid-cols-1 xl:grid-cols-4 gap-x-8" v-if="!isloading">
              <div>
                <label-field text="Chart to show"/>
                <select class="bg-white h-10 bg-none w-full rounded-[5px]" v-model="formModel.type" @change="getFilteredDataChart">
                  <option v-for="chart in chartToShow" :key="chart.value" :value="chart.value">{{ chart.text }}</option>
                </select>
              </div>
              <div>
                <label-field text="Date"/>
                <select class="bg-white h-10 bg-none w-full rounded-[5px]" v-model="formModel.range" @change="getFilteredDataChart">
                  <option v-for="range in ranges" :key="range.value" :value="range.value">{{ range.text }}</option>
                </select>
              </div>
              <div>
                <label-field text="Player"/>
                <DropDownMultiple v-model="formModel.players" :options="optionsPlayer" :isSelectAll="true"/>
              </div>
              <!-- <div class="place-self-end">
                <button type="submit" role="submit" class="bg-fungo-red inline-flex rounded-lg w-10 h-10 items-center justify-center">
                  <SearchIcon />
                </button>
              </div> -->
            </form>
          </section>
          <section class="main-chart" ref="mainChart">
            <apexchart width="100%" type="line" height="600px" :options="dinamicChartoptions(formModel.range, monthsShow)" :series="seriesDinamicChart"/>
          </section>
        </div>
        <div>
          <div class="grid grid-cols-1 xl:grid-cols-2 xl:space-x-8 space-y-14 xl:space-y-0">
            <section class="grid grid-cols-1 xl:grid-cols-2 xl:space-x-8 space-y-14 xl:space-y-0">
              <div v-if="isloading">
                <h2>Load...</h2>
              </div>
              <template v-else>
                <article class="space-y-14">
                  <h3 class="text-fungo-red text-2xl font-fungo-700">Team Statistics </h3>
                  <chart-card
                    firstTitle="Contact vs Ball/Strikes"
                    secondTitle="Hitter Awareness"
                    thirdTitle="VS Ball / Strikes"
                  >
                    <template v-slot:chart-body>
                      <apexchart width="100%" type="donut" :options="donutChartOptions" :series="ballStrikeSeries"></apexchart>
                      <indicator-chart
                        :labelTitle="`Strikes ${ballStrike.strikes.count}`"
                        :labelValue="ballStrike.strikes.percent"
                        color="#01F1E3"
                      />
                      <indicator-chart
                        :labelTitle="`Balls ${ballStrike.balls.count}`"
                        :labelValue="ballStrike.balls.percent"
                        color="#8676FF"
                      />
                    </template>
                  </chart-card>

                  <chart-card
                    firstTitle="Batting & LiveAB"
                    secondTitle="Contact"
                    thirdTitle="Batting / LiveAB"
                  >
                    <template v-slot:chart-body>
                      <section class="mt-5 flex flex-col space-y-4">
                        <indicator-chart
                          :labelTitle="`GB ${typeHitsBatting.GB.count}`"
                          :labelValue="typeHitsBatting.GB.percent"
                          color="#F8A488"
                        />
                        <indicator-chart
                          :labelTitle="`LD ${typeHitsBatting.LD.count}`"
                          :labelValue="typeHitsBatting.LD.percent"
                          color="#ADE8F4"
                        />
                        <indicator-chart
                          :labelTitle="`FLY ${typeHitsBatting.FLY.count}`"
                          :labelValue="typeHitsBatting.FLY.percent"
                          color="#8676FF"
                        />
                        <indicator-chart
                          :labelTitle="`SM/F ${typeHitsBatting['SM/F'].count}`"
                          :labelValue="typeHitsBatting['SM/F'].percent"
                          color="#FFB457"
                        />
                        <indicator-chart
                          :labelTitle="`TAKE ${typeHitsBatting.TAKE.count}`"
                          :labelValue="typeHitsBatting.TAKE.percent"
                          color="#03F1E3"
                        />
                      </section>
                    </template>
                  </chart-card>

                  <chart-card
                    firstTitle="LiveAB & Bullpen"
                    secondTitle="Pitches Thrown"
                    thirdTitle="LiveAB / Bullpen"
                  >
                    <template v-slot:chart-body>
                      <apexchart
                        width="100%" type="bar" height="350"
                        :options="barChartOptions(pitchThrows.totals)"
                        :series="[{ name: 'Thrown', data: [pitchThrows.totals, pitchThrows.FB, pitchThrows.CH, pitchThrows.CB, pitchThrows.SL, pitchThrows.OTHER ] }]">
                      </apexchart>
                    </template>
                  </chart-card>

                  <chart-card
                    firstTitle="LiveAB"
                    secondTitle="S/M Take"
                    thirdTitle="LiveAB"
                  >
                    <template v-slot:chart-body>
                      <apexchart
                        width="100%" type="bar" height="350"
                        :options="barChartOptions(smTake.totals, 1, 2,)"
                        :series="[{ name: 'S/M', data: [ smTake.FB.SM, smTake.CH.SM, smTake.CB.SM, smTake.SL.SM, smTake.OTHER.SM ]},
                                  { name: 'Take', data: [ smTake.FB.TAKE, smTake.CH.TAKE, smTake.CB.TAKE, smTake.SL.TAKE, smTake.OTHER.TAKE ]}]">
                      </apexchart>
                    </template>
                  </chart-card>

                </article>

                <article class="space-y-8">
                  <chart-card
                    firstTitle="Batting Practice"
                    secondTitle="Directional"
                    thirdTitle="Left / Middle / Right"
                  >
                    <template v-slot:chart-body>
                      <apexchart width="100%" type="radialBar"
                        :options="radiaChartOptions"
                        :series="[directional.RIGHT.percent, directional.MIDDLE.percent, directional.LEFT.percent]">
                      </apexchart>
                    </template>
                  </chart-card>

                  <chart-card
                    firstTitle="LiveAB & Bullpen"
                    secondTitle="Average Pitch Velocity"
                    thirdTitle="LiveAB / Bullpen"
                  >
                    <template v-slot:chart-body>
                      <apexchart
                        width="100%" type="bar" height="350"
                        :options="barChartOptions(pitchVelocityAverage.totals / 5, 2, 2)"
                        :series="[{ name: 'Average', data: [pitchVelocityAverage.FB, pitchVelocityAverage.CH, pitchVelocityAverage.CB, pitchVelocityAverage.SL, pitchVelocityAverage.OTHER ] }]">
                      </apexchart>
                    </template>
                  </chart-card>

                  <chart-card
                    firstTitle="Pitching & LiveAB"
                    secondTitle="Contact"
                    thirdTitle="Pitching / LiveAB"
                  >
                    <template v-slot:chart-body>
                      <section class="mt-5 flex flex-col space-y-4">
                        <indicator-chart
                          :labelTitle="`GB ${typeHitsPitching.GB.count}`"
                          :labelValue="typeHitsPitching.GB.percent"
                          color="#F8A488"
                        />
                        <indicator-chart
                          :labelTitle="`FLY ${typeHitsPitching.FLY.count}`"
                          :labelValue="typeHitsPitching.FLY.percent"
                          color="#8676FF"
                        />
                        <indicator-chart
                          :labelTitle="`LD ${typeHitsPitching.LD.count}`"
                          :labelValue="typeHitsPitching.LD.percent"
                          color="#ADE8F4"
                        />
                        <indicator-chart
                          :labelTitle="`SM/F ${typeHitsPitching['SM'].count}`"
                          :labelValue="typeHitsPitching['SM'].percent"
                          color="#FFB457"
                        />
                      </section>
                    </template>
                  </chart-card>

                  <chart-card
                    firstTitle="Cage Mode"
                    secondTitle="Launch Angle Average Velocity"
                  >
                    <template v-slot:chart-body>
                      <apexchart
                        width="100%" type="bar" height="350"
                        :options="barChartOptions(pitchThrows.totals, 1, 4)"
                        :series="[
                        {
                          name: 'Average',
                          data: [
                            launchAngleAverage['-0'],
                            launchAngleAverage['0-6'],
                            launchAngleAverage['6-15'],
                            launchAngleAverage['15-24'],
                            launchAngleAverage['24-40'],
                            launchAngleAverage['40-55'],
                            launchAngleAverage['55+']
                          ]
                        }
                      ]">
                      </apexchart>
                    </template>
                  </chart-card>

                </article>
              </template>
            </section>

            <div class="flex items-center flex-col space-y-8">
              <top-table />
              <feeds-table />
            </div>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>
<style scoped>

.dash-arrows-container {
  position: relative;
  top: 0;
  left: 0;
  display: flex;
  justify-content: space-between;
  align-content: center;
  flex-wrap: nowrap;
}

.dash-header {
  @apply bg-[#F7F8F9] h-[23%] w-full  flex flex-col justify-center items-center;
}


.dash-body {
  @apply bg-[#E7EAEE] min-h-[80%]  w-full;
}

</style>
