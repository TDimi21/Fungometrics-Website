<script setup>
import LayoutVue from '../../layout/Layout.vue';
import { defineProps, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useTeamStore } from '@/store/team.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useRouter, useRoute } from 'vue-router'
import { toast } from "@/utils/AlertPlugin"
import { useUserStore } from "@/store/user";
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import DynamicTable from '../../components/practice/DynamicTable.vue';
import { BigButtonField } from '@/components/form'
import { CageIcon } from '../../components/icons';
import { useTrainingStore } from "../../store/training";
import { SendMsgModal, SendMsgStatusModal } from '@/components/shared'
import useSendMsg from '@/composables/useSendMsg.js'
import Loader from '@/components/Loader.vue'
import CageFieldStats from '@/components/statistics/CageFieldStats.vue'

const useTeam = useTeamStore()
const { team } = storeToRefs(useTeam)
const { axiosGet } = useAxiosAuth();
const { userData } = useUserStore();
const router = useRouter()
const route = useRoute()
const training = useTrainingStore();
const { openSendMsgWindow, closeMsgWindow, isShowMsgModal, sendMsg, getSmsPlayers,
  playersStatus, playersToSend, isShowMsgModalStatus, isSending, openStatusModal, statusMsg } = useSendMsg()

const props = defineProps({
  idPractice: {
    Type: String
  }
})
const globalResponse = ref({})
const orderAsc = ref(true)

const ballxballHeadings = ref([])
const ballxballData = ref([])
const isLoading = ref(true)

const headersSort = ref(new Map(
  [
    ['pitch #', 'id'],
    ['player', 'first_name'],
    ['exit velocity', 'launch_velocity'],
    ['launch angle', 'launch_angle'],
    ['spray angle', 'launch_spray'],
    ['distance', 'launch_distance'],
  ]
))

const sorter = ref('id')
const excelHeaderData = ref({})
const excelDataExport = ref([])
onMounted(() => {
  setLabelTitle()
})

const setLabelTitle = () => {
  getStatistic('cage');
}

const tabHeading = ref([
  'Ball by Ball', 'Contact',
  'Trajectory', 'Velocity'
])

const getStatistic = async (mode) => {
  try {
    let param = userData.type === 'player' ? '?player=true':''
    await axiosGet(`statistics/${props.idPractice}/${mode}${param}`)
      .then((response) => {
        if (response) {
          isLoading.value = false
          globalResponse.value = response.data.data
          setBallxBall()
          excelDataExportCage()
          // Por alguna razon debo ordenar primero para evitar que se remueva el id de la tabla
          sortBy('pitch #')
          sortBy('pitch #')
        }

        isLoading.value = false
      })
  } catch (error) {
    isLoading.value = false
    toast.fire({
      icon: 'error',
      title: 'Error',
      text: 'Not fount data',
    })
  }
}

const setBallxBall = () => {
  ballxballHeadings.value = [
    'pitch #', 'player',
    'exit velocity', 'launch angle',
    'spray angle', 'distance'
  ]
  let contador = 1
  let tempData = []
  for (const iterator of globalResponse.value.ball_x_ball) {
    iterator
    tempData.push(
      {
        id: contador,
        player: getPlayerCellWithinPicture(iterator.profile.first_name, iterator.profile.last_name, iterator.id),
        launch_velocity: iterator.launch_angle_velocity ?? '0',
        launch_angle: iterator.launch_angle,
        launch_spray: iterator.spray_angle,
        launch_distance: iterator.distance_travel
      }
    )

    contador++
  }

  ballxballData.value = getSortData(tempData)
}

const getPlayerCellWithinPicture = (name, lastName, id) => {
  return `
  <td id="${id}">
    <div class="flex flex-row justify-start">
      <div class="pl-2 text-start">
        <div>
          <spam>${name} ${lastName}</spam>
        </div>
        <div>
          <spam class="font-semibold">${lastName}</spam>
        </div>
      </div>
    </div>
  </td>`
}

const clearElement = (data) => {
  let value = data
  value = value.player.match(/id=".*"/)[0].toString().replace(`id="`, "").replace(`"`, "");
  return value
}

const getEditData = (event) => {
  let id = clearElement(event)
  let data = Object.values(globalResponse.value.ball_x_ball)
  let editPlayer = data.find((element) => {
    if (element.id == id) {
      return element
    }
  })
  let editData = {
    'players': [{
      'id': editPlayer.user_id,
      'name': {
        'first': editPlayer.profile.first_name,
        'last': editPlayer.profile.last_name,
        'full': editPlayer.profile.first_name + ' ' + editPlayer.profile.last_name
      },
      'body': {
        'ft': 6,
        'inch': 0
      }
    }],
    ...editPlayer
  }
  training.setDataTraining(editData)
  let height = globalResponse.value.cage_meta.height_ft
  let width = globalResponse.value.cage_meta.width_ft
  let legnth = globalResponse.value.cage_meta.length_ft
  router.push({
    path: '/track/training-cage/' + height + '/' + legnth + '/' + width
  })
}

const sortBy = (event) => {
  orderAsc.value = !orderAsc.value

  sorter.value = headersSort.value.get(event)
  setBallxBall()
}

const getSortData = (data) => {
  if (orderAsc.value) {
    if (sorter.value != 'first_name') {
      return data.sort((a, b) => {
        if (isNaN(a[sorter.value]) || isNaN(b[sorter.value])) {
          if (a[sorter.value] > b[sorter.value]) {
            return 1
          } else if (a[sorter.value] < b[sorter.value]) {
            return -1
          }

          return 0
        }

        return a[sorter.value] - b[sorter.value]
      })
    } else {
      return data.sort((a, b) => {
        let valueA = a['player'].match(/>.*</)
        let valueB = b['player'].match(/>.*</)
        valueA = valueA.toString().replace('>', '').replace('<', '')
        valueB = valueB.toString().replace('>', '').replace('<', '')

        if (valueA > valueB) {
          return 1
        } else if (valueA < valueB) {
          return -1
        }

        return 0
      })
    }
  } else {
    if (sorter.value != 'first_name') {
      return data.sort((a, b) => {
        if (isNaN(a[sorter.value]) || isNaN(b[sorter.value])) {
          if (b[sorter.value] > a[sorter.value]) {
            return 1
          } else if (b[sorter.value] < a[sorter.value]) {
            return -1
          }

          return 0
        }

        return b[sorter.value] - a[sorter.value]
      })
    } else {
      return data.sort((a, b) => {
        let valueA = a['player'].match(/>.*</)
        let valueB = b['player'].match(/>.*</)
        valueA = valueA.toString().replace('>', '').replace('<', '')
        valueB = valueB.toString().replace('>', '').replace('<', '')

        if (valueB > valueA) {
          return 1
        } else if (valueB < valueA) {
          return -1
        }

        return 0
      })
    }
  }

}

const excelDataExportCage = () => {
  excelDataExport.value = ballxballData.value = ballxballData.value.map((element) => {
    let name = element.player
    let data = name.match(/[\s\S]<spam>.*?<\/spam>/).toString()
    element.player = data.replace(/<\/?spam>/gi, '').trim()
    return element
  })

  excelHeaderData.value = {
    'Pitch #': "id",
    'Player': "player",
    'Exit velocity': "launch_velocity",
    'Launch angle': "launch_angle",
    'Spray angle': "launch_spray",
    'Distance': "launch_distance",
  }
}

const send = () => {
  sendMsg(route.params.idPractice)
}

if(userData.type === 'coach'){
  getSmsPlayers(route.params.idPractice)
}
</script>
<template>
  <Loader v-show="isSending" />
  <LayoutVue>
    <div class="cage-app-header">
      <button class="cage-back" type="button" @click="router.back()">
        <span aria-hidden="true">←</span> Back
      </button>
      <div class="cage-title">
        <CageIcon class="w-10 flex-shrink-0" />
        <h1>Cage Practice Statistics</h1>
      </div>
      <div v-if="userData.type !== 'player'" class="ml-auto flex gap-3">
        <template v-if="route.params.isComplete == 'true'">
          <form v-if="statusMsg === false" @submit.prevent="openSendMsgWindow(globalResponse.by_player)">
            <BigButtonField color="dark" label="Send sms to players" type="submit" />
          </form>
          <form v-if="statusMsg !== false" @submit.prevent="openStatusModal(route.params.idPractice)">
            <BigButtonField color="dark" label="Check Status" type="submit" />
          </form>
        </template>
      </div>
    </div>
    <section class="cage-stats-app md:px-[5%]">
      <TabGroup>
        <div class="cage-tabbar">
          <TabList class="cage-tabs">
            <Tab as="template" v-slot="{ selected }" v-for="head in tabHeading" :key="head">
              <button class="cage-tab" :class="{ 'cage-tab--active': selected }">
                {{ head }}
              </button>
            </Tab>
          </TabList>
          <download-excel
            class="cage-export"
            :data="excelDataExport" :fields="excelHeaderData" :name="'cageBallxBallTable.xls'"
          >
            <svg width="14" height="17" viewBox="0 0 18 22" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M18 7.71429H12.8571V0H5.14286V7.71429H0L9 16.7143L18 7.71429ZM7.71307 10.2863V2.57202H10.2845V10.2863H11.7888L8.99878 13.0763L6.20878 10.2863H7.71307ZM18 21.8571V19.2856H0V21.8571H18Z" fill="#E10600" />
            </svg>
            <span class="text-sm font-semibold">Export</span>
          </download-excel>
        </div>
        <TabPanels class="cage-panels">
          <TabPanel class="cage-panel cage-panel--table">
            <DynamicTable :is-sorteable="true" :is-loading="isLoading" :headings="ballxballHeadings"
              :table-data="ballxballData" :actionable="true" v-on:edit-event="getEditData($event)"
              v-on:click-header="sortBy($event)" />
          </TabPanel>
          <TabPanel class="cage-panel cage-panel--unified">
            <CageFieldStats
              class="cage-unified"
              mode="contact"
              :balls="globalResponse.ball_x_ball || []"
              :team-name="team?.name || 'Team'"
            />
          </TabPanel>
          <TabPanel class="cage-panel cage-panel--unified">
            <CageFieldStats
              class="cage-unified"
              mode="trajectory"
              :balls="globalResponse.ball_x_ball || []"
              :team-name="team?.name || 'Team'"
            />
          </TabPanel>
          <TabPanel class="cage-panel cage-panel--unified">
            <CageFieldStats
              class="cage-unified"
              mode="velocity"
              :balls="globalResponse.ball_x_ball || []"
              :team-name="team?.name || 'Team'"
            />
          </TabPanel>
        </TabPanels>
      </TabGroup>
    </section>
    <SendMsgModal v-if="isShowMsgModal" @closeModal="closeMsgWindow" @sendMessage="send" :players="playersToSend" />
    <SendMsgStatusModal v-if="isShowMsgModalStatus" @closeModal="isShowMsgModalStatus = !isShowMsgModalStatus"
      :players="playersStatus" />
  </LayoutVue>
</template>
<style scoped>
.cage-app-header {
  display: flex;
  align-items: center;
  gap: 18px;
  margin: 0 5% 18px;
}

.cage-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #fff;
  font-size: 16px;
  font-weight: 800;
  white-space: nowrap;
}

.cage-back span {
  font-size: 30px;
  font-weight: 400;
  line-height: 1;
}

.cage-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cage-title h1 {
  color: #fff;
  font-size: clamp(20px, 2vw, 30px);
  font-weight: 900;
  letter-spacing: .02em;
}

.cage-stats-app {
  --cage-navy: #0a1024;
  --cage-panel: #191f3c;
  --cage-row: #151b4b;
  --cage-row-alt: #30375f;
  --cage-red: #ff2b4a;
}

.cage-tabbar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-bottom: 16px;
}

.cage-tabs {
  display: grid;
  grid-template-columns: repeat(4, minmax(130px, 1fr));
  gap: 2px;
  overflow-x: auto;
}

.cage-tab {
  min-height: 54px;
  padding: 12px 22px;
  border-radius: 10px;
  background: #30323b;
  color: #fff;
  font-size: clamp(12px, 1.2vw, 18px);
  font-weight: 900;
  letter-spacing: .02em;
  text-transform: uppercase;
  white-space: nowrap;
  transition: background-color .15s ease;
}

.cage-tab:hover,
.cage-tab--active {
  background: var(--cage-red);
}

.cage-export {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  padding: 12px 16px;
  border: 1px solid rgba(255, 255, 255, .15);
  border-radius: 10px;
  background: #30323b;
  color: #fff;
  cursor: pointer;
  font-weight: 800;
}

.cage-panels {
  min-height: 560px;
  padding: 18px;
  border: 1px solid rgba(255, 255, 255, .08);
  border-radius: 14px;
  background:
    linear-gradient(rgba(10, 16, 36, .82), rgba(10, 16, 36, .82)),
    url("../../assets/img/fungometrics-stadium.webp") center / cover;
}

.cage-unified {
  width: 100%;
}

.cage-panel :deep(.bg-white),
.cage-panel :deep(.bg-fungo-lightblue) {
  background-color: rgba(25, 31, 60, .94) !important;
  color: #fff !important;
}

.cage-panel :deep(table) {
  color: #fff !important;
  font-variant-numeric: tabular-nums;
}

.cage-panel :deep(thead),
.cage-panel :deep(thead tr),
.cage-panel :deep(th) {
  background: var(--cage-panel) !important;
  color: #fff !important;
  border-color: rgba(255, 255, 255, .08) !important;
  font-weight: 900 !important;
  text-transform: uppercase;
}

.cage-panel :deep(tbody tr:nth-child(odd)) {
  background: rgba(21, 27, 75, .95) !important;
}

.cage-panel :deep(tbody tr:nth-child(even)) {
  background: rgba(48, 55, 95, .95) !important;
}

.cage-panel :deep(td) {
  height: 58px;
  border-color: rgba(255, 255, 255, .06) !important;
  color: #fff !important;
  font-weight: 700;
}

.cage-panel :deep(button:not(.cage-tab)) {
  min-height: 46px;
  border-radius: 9px;
  border-color: rgba(255, 255, 255, .25) !important;
  background: #fff;
  color: #0a1024 !important;
  font-weight: 900;
  text-transform: uppercase;
}

.cage-panel :deep(button.is-active) {
  border-color: var(--cage-red) !important;
  background: var(--cage-red) !important;
  color: #fff !important;
}

.is-active {
  @apply bg-fungo-red text-white
}


.ball-header.foul {
  background-image: url("../../assets/img/login/assteslogin/ballbutton.svg");
  background-repeat: no-repeat;
  background-size: contain;
  background-position: center;
}

.ball-header.weack {
  background-image: url("../../assets/img/training/balltraining-green.svg");
  background-repeat: no-repeat;
  background-size: contain;
  background-position: center;
}

.ball-header.average {
  background-image: url("../../assets/img/training/balltraining.svg");
  background-repeat: no-repeat;
  background-size: contain;
  background-position: center;
}

.ball-header.hard {
  background-image: url("../../assets/img/training/balltraining-blue.svg");
  background-repeat: no-repeat;
  background-size: contain;
  background-position: center;
}

@media (max-width: 900px) {
  .cage-app-header {
    margin-inline: 16px;
    flex-wrap: wrap;
  }

  .cage-title {
    order: -1;
    width: 100%;
  }

  .cage-tabbar {
    align-items: stretch;
    flex-direction: column;
  }

  .cage-tabs {
    grid-template-columns: repeat(4, minmax(120px, 1fr));
  }

  .cage-export {
    align-self: flex-end;
  }

  .cage-panels {
    padding: 10px;
  }
}
</style>
