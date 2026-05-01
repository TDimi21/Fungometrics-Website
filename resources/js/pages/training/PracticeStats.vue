<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { toast } from "@/utils/AlertPlugin"
import { BigButtonField } from '@/components/form'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import BattingLogoPractice from "@/components/graphics/BattingLogoPractice.vue"
import { TabBallByBall, TabPitchBreakdown, TabContact, TabTrajectory, TabVelocity } from '@/components/statistics'
import { TabBall, TabPitch, TabCont, TabVelo } from '@/components/statistics/bullpen'
import { useUserStore } from "@/store/user";
import { SendMsgModal, SendMsgStatusModal } from '@/components/shared'
import useSendMsg from '@/composables/useSendMsg.js'
import useSortStatistics from '@/composables/useSortStatistics.js'
import Loader from '@/components/Loader.vue'

const { axiosGet } = useAxiosAuth()
const { userData } = useUserStore();
const { openSendMsgWindow, closeMsgWindow, isShowMsgModal, sendMsg, getSmsPlayers,
  playersStatus, playersToSend, isShowMsgModalStatus, isSending, openStatusModal, statusMsg } = useSendMsg()
const { ordenarElementos } = useSortStatistics()
const route = useRoute()
const tabHeading = ref([
  'Ball by Ball', 'Pitch Breakdown', 'Contact', 'Trajectory', 'Velocity'
])

const isLoading = ref(false)
const statsData = ref([])
const excelDataExport = ref([])
const excelHeaderData = ref({})
const orderAsc = ref(true)

const getStatistic = async() => {
  let endpoint = ''
  switch (route.params.type){
    case 'B':
      endpoint = 'batting'
      break

    case 'P':
      endpoint = 'bullpen'
      break

    case 'L':
      break

    case 'C':
      break

    case 'T':
      break
  }
  try {
    isLoading.value = true
    let param = userData.type === 'player' ? '?player=true':''
    await axiosGet(`statistics/${route.params.idPractice}/${endpoint}${param}`).then(response => {
      statsData.value = response.data.data
      createDataForExcel()
    }).catch( (error) => {
      toast.fire({
        icon: 'error',
        title: 'Error get data',
        text: error.response.data.message,
      })
    })
  } catch (error) {
    toast.fire({
      icon: 'error',
      title: 'Error get data',
      text: error,
    })
  } finally {
    isLoading.value = false
  }
}

const sortData =  (key) => {

  orderAsc.value = !orderAsc.value

  if (!orderAsc.value) {
    return ordenarElementos(statsData.value.ball_x_ball, key, 'asc')
  } else {
    return ordenarElementos(statsData.value.ball_x_ball, key, 'desc')
  }
}

onMounted(() => {
  getStatistic()
  route.params.type == 'P' ? tabHeading.value.splice(tabHeading.value.indexOf('Trajectory'), 1) : ''
})

const createDataForExcel = () => {
  switch (route.params.type) {
    case 'B':
      dataBattingExcel()
      break

    case 'P':
      dataBullpenExcel()
      break

    default:
      dataBullpenExcel()
      break;
  }
}

const dataBattingExcel = () => {
  let position = 1
  excelHeaderData.value = {
    'Pitch #': "pitch",
    'Player': "player",
    'Q.C': "quality_of_contact",
    'TRAJ.': "type_of_hit",
    'B/S': "is_contact",
    'DIR': "field_direction",
    'VELO': "velocity"
  }
  for (const iterator of statsData.value.ball_x_ball) {
    excelDataExport.value.push({
      pitch: position,
      player: iterator.profile.last_name + ' ' + iterator.profile.first_name,
      quality_of_contact: iterator.quality_of_contact,
      type_of_hit: iterator.type_of_hit,
      is_contact: iterator.zone,
      field_direction: iterator.field_direction,
      velocity: iterator.velocity == 0 ? '-': iterator.velocity
    })
    position++
  }
}


const dataBullpenExcel = () => {
  let position = 1
  excelHeaderData.value = {
    'Pitch #': "pitch",
    'Player': "player",
    'Pitch': "pitch_location",
    'TRAJ.': "trajectory",
    'B/S': "is_contact",
    'VELO': "velocity"
  }
  for (const iterator of statsData.value.ball_x_ball) {
    excelDataExport.value.push({
      pitch: position,
      player: iterator.profile.last_name + ' ' + iterator.profile.first_name,
      pitch_location: iterator.type_throw,
      trajectory: iterator.trajectory,
      is_contact: iterator.zone,
      velocity: iterator.miles_per_hour
    })

    position++
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
  <Loader v-show="isSending"/>
  <Layout>
    <h1 class="text-fungo-red text-2xl md:text-[40px] text-center mt-9 mb-6 font-fungo-700">
      {{ route.params.type == 'P' ? 'Bullpen Statistics' : 'Batting Practice Statistics'}}
    </h1>

    <section class="bg-fungo-gray3 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[5%]">

      <div class="flex flex-col items-center lg:flex-row space-y-6 lg:space-y-0 lg:space-x-3 justify-between">
        <div class="w-max flex items-center">
          <BattingLogoPractice class="h-[80px] w-[80px]" />
          <download-excel class="flex cur w-[100px] gap-2 bg-white p-3 rounded-r-full"
          :data="excelDataExport"
          :fields="excelHeaderData"
          :name="route.params.type != 'P'? 'battingBallxBallTable.xls': 'bullpenBallxBallTable.xls'"
          >
            <div>Excel</div>
            <svg width="18" height="22" viewBox="0 0 18 22" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M18 7.71429H12.8571V0H5.14286V7.71429H0L9 16.7143L18 7.71429ZM7.71307 10.2863V2.57202H10.2845V10.2863H11.7888L8.99878 13.0763L6.20878 10.2863H7.71307ZM18 21.8571V19.2856H0V21.8571H18Z" fill="#E10600"/>
            </svg>
          </download-excel>
        </div>
        <div class="w-[100%] lg:w-[50%] flex justify-end space-x-4" v-if="userData.type !== 'player'">
        <template v-if="route.params.isComplete == 'true'">
          <form v-if="statusMsg === false" @submit.prevent="openSendMsgWindow(statsData.by_player)">
            <BigButtonField color="dark" label="Send sms to players" type="submit"/>
          </form>

          <form v-if="statusMsg !== false" @submit.prevent="openStatusModal(route.params.idPractice)">
            <BigButtonField color="dark" label="Check Status" type="submit"/>
          </form>
        </template>
        </div>
      </div>
    </section>

    <section class="mt-[200px] lg:mt-[120px] md:px-[5%]">
      <TabGroup>
        <TabList class="border-b-2 border-fungo-gray3">
          <Tab
            as="template"
            v-slot="{ selected }"
            class="mx-4"
            v-for="head in tabHeading"
          >
            <button
              class="outline-none"
              :class="{ 'text-fungo-red font-fungo-500 border-b-2 border-fungo-red': selected, 'text-fungo-darkblue': !selected }"
            >
              {{ head }}
            </button>
          </Tab>
        </TabList>
        <TabPanels>
          <TabPanel>
            <TabBallByBall
              v-if="route.params.type == 'B'"
              :isLoading="isLoading"
              :tableData="statsData.ball_x_ball"
              @sortData="sortData"
            />
            <TabBall
              v-else
              :isLoading="isLoading"
              :tableData="statsData.ball_x_ball"
              @sortData="sortData"
            />
          </TabPanel>
          <TabPanel>
            <TabPitchBreakdown
              v-if="route.params.type == 'B'"
              :breakdown-data="statsData.by_player"
            />
            <TabPitch
              v-else
              :breakdown-data="statsData.by_player"
              :ballData="statsData.ball_x_ball"
            />
          </TabPanel>
          <TabPanel>
            <TabContact
              v-if="route.params.type == 'B'"
              :contact-data="statsData.by_player"
            />
            <TabCont
              v-else
              :breakdown-data="statsData.by_player"
            />
          </TabPanel>
          <TabPanel
            v-if="route.params.type == 'B'"
          >
            <TabTrajectory
              :trajectoryData="statsData.by_player"
            />
          </TabPanel>
          <TabPanel>
            <TabVelocity
              v-if="route.params.type == 'B'"
              :VelocityData="statsData.by_player"
            />
            <TabVelo
              v-else
              :VelocityData="statsData.by_player"
            />
          </TabPanel>
        </TabPanels>
      </TabGroup>
    </section>

    <SendMsgModal v-if="isShowMsgModal" @closeModal="closeMsgWindow" @sendMessage="send" :players="playersToSend"/>
    <SendMsgStatusModal v-if="isShowMsgModalStatus" @closeModal="isShowMsgModalStatus = !isShowMsgModalStatus" :players="playersStatus"/>
  </Layout>
</template>
