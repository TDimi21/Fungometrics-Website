<script setup>
import LayoutVue from '../../layout/Layout.vue';
import { defineProps, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useTeamStore } from '@/store/team.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useRouter, useRoute } from 'vue-router'
import { toast } from "@/utils/AlertPlugin"
import { useUserStore } from "@/store/user";
import { PrintSprayCage } from '@/components/practice'
import PracticeTitle from '../../components/practice/PracticeTitle.vue';
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import DynamicTable from '../../components/practice/DynamicTable.vue';
import { BigButtonField } from '@/components/form'
import { CageIcon } from '../../components/icons';
import { useTrainingStore } from "../../store/training";
import { SendMsgModal, SendMsgStatusModal } from '@/components/shared'
import useSendMsg from '@/composables/useSendMsg.js'
import Loader from '@/components/Loader.vue'

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
const globalPlayers = ref([])
const orderAsc = ref(true)

const ballxballHeadings = ref([])
const ballxballData = ref([])
const isLoading = ref(true)

const ListThrowsLabel = ref([
  'SWING #', 'VELOCITY'
])
const listThrowsData = ref([])

const averageHeaders = ref([
  'fb', 'pf', 'ld', 'gb', 'total swings', 'player'
])
const averageData = ref([])

const averageHeadersVelocity = ref([
  "<60%", "60-69%", '70-79%', '80-89%', '90+%', 'max ev', 'player'
])
const averageDataVelocity = ref([])

const filterContact = ref("a")
const filterTrajectory = ref("a")
const filterVelocity = ref('')

const coordinatesContact = ref([])
const globalCoordinatesContact = ref([])
const coordinatesTrayectory = ref([])
const selectedPlayer = ref('')
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
          getPlayersId()
          setBallxBall()
          setContact()
          setVelocity()
          setFilterContact('a')
          setFilterTrajectory('a')
          setFilterVelocity('a')
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

const getPlayerCell = (picture, name, lastName) => {
  return `
  <td>
    <div class="flex flex-row justify-start">
      <img src="${picture}" class="w-12 h-12 rounded-full"  alt="">
      <div class="pl-2 text-start">
        <div>
          ${name} ${lastName}</spam>
        </div>
        <div>
          ${lastName}</spam>
        </div>
      </div>
    </div>
  </td>`
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

const getPlayersId = () => {

  let dataArrayValues = Object.values(globalResponse.value.ball_x_ball).map((item, key) => {
    return item.user_id
  })

  globalPlayers.value = dataArrayValues.filter((item, index) => {
    return dataArrayValues.indexOf(item) === index
  })
}

const setFilterContact = (value) => {
  filterContact.value = value
  coordinatesContact.value = []
  if (value != 'a') {
    for (const iterator of globalCoordinatesContact.value) {
      if (iterator.type == value) {
        if (selectedPlayer.value != '') {
          if (selectedPlayer.value == iterator.id) {
            coordinatesContact.value.push(iterator)
          }
        } else {
          coordinatesContact.value.push(iterator)
        }
      }
    }
  } else {
    for (const iterator of globalCoordinatesContact.value) {
      if (selectedPlayer.value != '') {
        if (selectedPlayer.value == iterator.id) {
          coordinatesContact.value.push(iterator)
        }
      } else {
        coordinatesContact.value.push(iterator)
      }
    }
  }

}

const setFilterTrajectory = (value) => {
  filterTrajectory.value = value
  coordinatesTrayectory.value = []
  for (const iterator of globalCoordinatesContact.value) {
    if (selectedPlayer.value != '') {
      if (iterator.id == selectedPlayer.value) {
        coordinatesTrayectory.value.push(iterator)
      }
    } else {
      coordinatesTrayectory.value.push(iterator)
    }
  }
}

const setFilterVelocity = (value) => {
  filterVelocity.value = value
  let count = 1
  listThrowsData.value = []
  for (const iterator of globalCoordinatesContact.value) {
    if (iterator.type == value && value != 'a') {
      if (selectedPlayer.value != '') {
        if (iterator.id == selectedPlayer.value) {
          listThrowsData.value.push([
            count,
            iterator.launch_angle_velocity ?? 0
          ])
        }
      } else {
        listThrowsData.value.push([
          count,
          iterator.launch_angle_velocity ?? 0
        ])
      }

      count++
    } else if (value == 'a') {
      if (selectedPlayer.value != '') {
        if (iterator.id == selectedPlayer.value) {
          listThrowsData.value.push([
            count,
            iterator.launch_angle_velocity ?? 0
          ])
        }
      } else {
        listThrowsData.value.push([
          count,
          iterator.launch_angle_velocity ?? 0
        ])
      }

      count++
    }

  }
}

const setContact = () => {
  averageData.value = []
  let hederGroundBall = 0;
  let hederPopFly = 0;
  let hederFlyBall = 0;
  let hederLineDrive = 0;

  let tempData = []
  for (const iterator of globalPlayers.value) {
    let groundBall = 0;
    let popFly = 0;
    let flyBall = 0;
    let lineDrive = 0;
    for (const key in globalResponse.value.by_fly_ball) {
      if (Object.hasOwnProperty.call(globalResponse.value.by_fly_ball, key)) {
        const fb = globalResponse.value.by_fly_ball[key];
        if (fb.user_id == iterator) {
          flyBall++;
          setSprayChart(fb, 'fb', fb.user_id)
        }
      }
    }

    for (const key in globalResponse.value.by_line_drive) {
      if (Object.hasOwnProperty.call(globalResponse.value.by_line_drive, key)) {
        const ld = globalResponse.value.by_line_drive[key];
        if (ld.user_id == iterator) {
          lineDrive++;
          setSprayChart(ld, 'ld', ld.user_id)
        }
      }
    }


    for (const key in globalResponse.value.by_pop_fly) {
      if (Object.hasOwnProperty.call(globalResponse.value.by_pop_fly, key)) {
        const pf = globalResponse.value.by_pop_fly[key];
        if (pf.user_id == iterator) {
          popFly++;
          setSprayChart(pf, 'pf', pf.user_id)
        }
      }
    }

    for (const gb in globalResponse.value.by_ground_ball) {
      if (globalResponse.value.by_ground_ball[gb].user_id == iterator) {
        groundBall++;
        setSprayChart(globalResponse.value.by_ground_ball[gb], 'gb', globalResponse.value.by_ground_ball[gb].user_id)
      }
    }

    const name = globalResponse.value.by_player[iterator][0].profile.first_name
    const lastName = globalResponse.value.by_player[iterator][0].profile.last_name
    hederGroundBall += groundBall;
    hederPopFly += popFly;
    hederFlyBall += flyBall;
    hederLineDrive += lineDrive;

    tempData.push({
      'id': iterator,
      "fb": flyBall,
      "pf": popFly,
      "ld": lineDrive,
      "gb": groundBall,
      "total": (flyBall + popFly + lineDrive + groundBall),
      "player": `${name} ${lastName}`,
      'lastName': lastName
    })
  }

  averageData.value = [
    {
      "id": "",
      "fb": hederFlyBall,
      "pf": hederPopFly,
      "ld": hederLineDrive,
      "gb": hederGroundBall,
      "total": (hederFlyBall + hederPopFly + hederLineDrive + hederGroundBall),
      "team": team.value.name
    },
    ...tempData
  ]
}

const setSprayChart = (value, type, id) => {
  globalCoordinatesContact.value.push({
    id: id,
    type: type,
    cage_mark: value.cage_mark,
    cage_position: value.cage_position,
    launch_angle_velocity: (value.launch_angle_velocity).toFixed(2),
    launch_angle: value.launch_angle,
    distance_travel: value.distance_travel,
    spray_angle: value.spray_angle
  })
}

const setVelocity = () => {
  let velocities = globalResponse.value.by_velocities
  let tempData = []
  let headerData = {
    'min': 0,
    'low': 0,
    'mid': 0,
    'high': 0,
    'max': 0,
    'ev': 0,
    'team': "",
  }
  for (const iterator of globalPlayers.value) {
    let min = 0
    let low = 0
    let mid = 0
    let high = 0;
    let max = 0;

    min = timesUsed(velocities.min, iterator)
    headerData.min += min
    low = timesUsed(velocities.low, iterator)
    headerData.low += low
    mid = timesUsed(velocities.mid, iterator)
    headerData.mid += mid
    high = timesUsed(velocities.high, iterator)
    headerData.high += high
    max = timesUsed(velocities.max, iterator)
    headerData.max += max

    let maxTimeUsedAllCategories = (min + low + mid + high + max)
    min = turnToPorcent(min, maxTimeUsedAllCategories)
    low = turnToPorcent(low, maxTimeUsedAllCategories)
    mid = turnToPorcent(mid, maxTimeUsedAllCategories)
    high = turnToPorcent(high, maxTimeUsedAllCategories)
    max = turnToPorcent(max, maxTimeUsedAllCategories)
    const name = globalResponse.value.by_player[iterator][0].profile.first_name
    const lastName = globalResponse.value.by_player[iterator][0].profile.last_name

    tempData.push({
      'id': iterator,
      "min": (min).toFixed(2),
      "low": (low).toFixed(2),
      "mid": (mid).toFixed(2),
      "high": (high).toFixed(2),
      "max": (max).toFixed(2),
      "ev": getMaxVelocityForPlayer(globalResponse.value.by_player[iterator]),
      "player": `${name} ${lastName}`,
      "lastName": lastName
    })
  }

  let maxTimeUsedAllCategories = (headerData['min'] + headerData['low'] + headerData['mid'] + headerData['high'] + headerData['max'])

  headerData['min'] = Number(turnToPorcent(headerData['min'], maxTimeUsedAllCategories)).toFixed(2)
  headerData['low'] = Number(turnToPorcent(headerData['low'], maxTimeUsedAllCategories)).toFixed(2)
  headerData['mid'] = Number(turnToPorcent(headerData['mid'], maxTimeUsedAllCategories)).toFixed(2)
  headerData['high'] = Number(turnToPorcent(headerData['high'], maxTimeUsedAllCategories)).toFixed(2)
  headerData['max'] = Number(turnToPorcent(headerData['max'], maxTimeUsedAllCategories)).toFixed(2)

  averageDataVelocity.value = [
    {
      'id': "",
      ...headerData,
      'ev': getMaxVelocityForTeam(),
      "player": team.value.name
    },
    ...tempData
  ]
}

const timesUsed = (value, id) => {
  if (Array.isArray(value)) {
    let numberOfSwings = 0
    for (const iterator of value) {
      if (iterator.user_id == id) {
        numberOfSwings++
      }
    }

    return numberOfSwings;
  } else {
    let numberOfSwings = 0
    for (const key in value) {
      if (Object.hasOwnProperty.call(value, key)) {
        const element = value[key];
        if (element.user_id == id) {
          numberOfSwings++
        }
      }
    }

    return numberOfSwings;
  }
}

const turnToPorcent = (currentForType, maxForAllTypeVelocity) => {
  let porcentForType = 0
  if (maxForAllTypeVelocity != 0) {
    porcentForType = 100 / maxForAllTypeVelocity
  }

  return currentForType * porcentForType
}

const getMaxVelocityForPlayer = (listtVelocities) => {
  let maxVelocity = 0
  for (const player of listtVelocities) {
    maxVelocity = maxVelocity > player.launch_angle_velocity ? maxVelocity : player.launch_angle_velocity
  }

  return maxVelocity
}

const getMaxVelocityForTeam = () => {
  let maxVelocity = 0
  for (const player of globalResponse.value.ball_x_ball) {
    maxVelocity = maxVelocity > player.launch_angle_velocity ? maxVelocity : player.launch_angle_velocity
  }

  return maxVelocity
}

const getSelectedValues = (data) => {
  let val = null
  if (data.id != "") {
    val = data.id
  }
  selectedPlayer.value = val ?? ''
  setFilterContact(filterContact.value ?? 'a')
  setFilterTrajectory(filterTrajectory.value ?? 'a')
  setFilterVelocity(filterVelocity.value ?? 'a')
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
    'Launch angle': "launch_velocity",
    'Spray angle': "launch_velocity",
    'Distance': "launch_velocity",
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
    <PracticeTitle class="capitalize" :title="'Cage practices statistics'" />
    <section class="flex w-full justify-between px-[5%]">
      <div class="flex items-center w-max">
        <CageIcon class="w-12 mr-2" />
        <download-excel class="flex w-[100px] gap-2 bg-white p-3 rounded-r-full" :data="excelDataExport"
          :fields="excelHeaderData" :name="'cageBallxBallTable.xls'">
          <div>Excel</div>
          <svg width="18" height="22" viewBox="0 0 18 22" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd"
              d="M18 7.71429H12.8571V0H5.14286V7.71429H0L9 16.7143L18 7.71429ZM7.71307 10.2863V2.57202H10.2845V10.2863H11.7888L8.99878 13.0763L6.20878 10.2863H7.71307ZM18 21.8571V19.2856H0V21.8571H18Z"
              fill="#E10600" />
          </svg>
        </download-excel>
      </div>

      <div v-if="userData.type !== 'player'" class="flex space-x-4">
        <template v-if="route.params.isComplete == 'true'">
          <form v-if="statusMsg === false" @submit.prevent="openSendMsgWindow(globalResponse.by_player)">
            <BigButtonField color="dark" label="Send sms to players" type="submit" />
          </form>
          <form v-if="statusMsg !== false" @submit.prevent="openStatusModal(route.params.idPractice)">
            <BigButtonField color="dark" label="Check Status" type="submit" />
          </form>
        </template>
      </div>
    </section>
    <section class="mt-[44px] lg:mt-[60px] md:px-[5%]">
      <TabGroup>
        <TabList class="border-b-2 border-fungo-gray3">
          <Tab as="template" v-slot="{ selected }" class="mx-4" v-for="head in tabHeading">
            <button class="outline-none"
              :class="{ 'text-fungo-red font-fungo-500 border-b-2 border-fungo-red': selected, 'text-fungo-darkblue': !selected }">
              {{ head }}
            </button>
          </Tab>
        </TabList>
        <TabPanels>
          <TabPanel>
            <DynamicTable :is-sorteable="true" :is-loading="isLoading" :headings="ballxballHeadings"
              :table-data="ballxballData" :actionable="true" v-on:edit-event="getEditData($event)"
              v-on:click-header="sortBy($event)" />
          </TabPanel>
          <TabPanel>
            <div class="grid grid-cols-3 gap-4 p-2 mt-4 bg-fungo-lightblue">
              <div class="col-span-5 text-center uppercase md:col-span-3 xl:col-span-2">Spray Chart</div>
              <div class="hidden text-center uppercase xl:inline">QUALITY OF CONTACT BREAKDOWN</div>
            </div>
            <div class="grid grid-cols-12 gap-4 my-[45px]">
              <!--Colum-->
              <div class="col-span-12 mx-auto md:col-span-3 w-60 md:w-full xl:col-span-2">
                <div class="flex flex-col px-4 py-6 mt-10 bg-white xl:w-40">
                  <span class="mb-2 text-fungo-red">Select</span>
                  <div>
                    <button :class="filterContact == 'a' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterContact('a')">All</button>
                    <button :class="filterContact == 'gb' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterContact('gb')">Ground</button>
                    <button :class="filterContact == 'pf' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterContact('pf')">Pop fly</button>
                    <button :class="filterContact == 'fb' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterContact('fb')">Fly ball</button>
                    <button :class="filterContact == 'ld' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterContact('ld')">Line drive</button>

                  </div>
                </div>
              </div>
              <!--Colum-->
              <PrintSprayCage :key="filterContact + selectedPlayer" :coordinates="coordinatesContact"
                :position="filterContact" />
              <!--Colum-->
              <div class="col-span-12 p-2 text-center uppercase bg-fungo-lightblue xl:hidden">QUALITY OF CONTACT BREAKDOWN
              </div>
              <div class="col-span-12 px-2 bg-white xl:col-span-4">
                <section class="mt-[3px] lg:mt-[3px] overflow-x-auto pt-2">
                  <table class="w-full space-y-6 border-collapse text-fungo-darkblue">
                    <thead class="bg-fungo-lightblue">
                      <tr class="h-8 bg-white">
                        <th class="ball-header foul"></th>
                        <th class="ball-header weack"></th>
                        <th class="ball-header average"></th>
                        <th class="ball-header hard"></th>
                      </tr>
                      <tr class="h-2">

                      </tr>
                      <tr class="uppercase divide-x divide-[#000]">
                        <th v-for="(heading, index) in averageHeaders" :key="index" class='px-2 py-3 font-fungo-500'
                          v-on:click="$emit('click-header', heading)">
                          <div class="flex justify-center">
                            {{ heading }} <img v-if="isSorteable" src="@/assets/img/icons/sort-solid.svg" alt="sort data"
                              class="w-3 ml-2">
                          </div>
                        </th>
                      </tr>
                    </thead>

                    <tbody>
                      <tr v-if="isLoading" class="w-full">
                        <td colspan="9" class="text-3xl text-center text-fungo-darkblue">Loading data...</td>
                      </tr>
                      <tr v-else-if="!averageData.length > 0">
                        <td colspan="9" class="text-3xl text-center text-fungo-darkblue">No found data</td>
                      </tr>
                      <tr v-else v-for="item in averageData" v-on:click="getSelectedValues(item)" :class="{
                        'bg-fungo-blue text-white': selectedPlayer == item.id,
                      }">
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.fb }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.pf }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.ld }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.gb }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.total }}
                        </td>
                        <td class="min-w-[10px] max-w-[90px]">
                          <div class="flex flex-row justify-start">
                            <div class="pl-2 text-start">
                              <div>
                                <spam>{{ item.player ?? item.team }}</spam>
                              </div>
                              <div>
                                <spam class="font-semibold">{{ item.lastName }}</spam>
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                      <tr v-else v-for="item in averageData" v-on:click="getSelectedValues(item)" :class="{
                        'bg-fungo-blue text-white': selectedPlayer == item.id,
                      }">
                        <td class="text-center h-14 min-w-[10px] max-w-[90px]">
                          {{ item.fb }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.pf }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.ld }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.gb }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.total }}
                        </td>
                        <td class="min-w-[10px] max-w-[90px]">
                          <div class="flex flex-row justify-start">
                            <div class="pl-2 text-start">
                              <div>
                                <spam>{{ item.player ?? item.team }}</spam>
                              </div>
                              <div>
                                <spam class="font-semibold">{{ item.lastName }}</spam>
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </section>
              </div>
            </div>
          </TabPanel>
          <TabPanel>
            <div class="grid grid-cols-3 gap-4 p-2 mt-4 bg-fungo-lightblue">
              <div class="col-span-5 text-center uppercase md:col-span-3 xl:col-span-2">Spray Chart</div>
              <div class="hidden text-center uppercase xl:inline">TRAJECTORY BREAKDOWN</div>
            </div>
            <div class="grid grid-cols-12 gap-4 my-[45px]">
              <!--Colum-->
              <div class="col-span-12 mx-auto md:col-span-3 w-60 md:w-full xl:col-span-2">
                <div class="flex flex-col px-4 py-6 mt-10 bg-white xl:w-40">
                  <span class="mb-2 text-fungo-red">Select</span>
                  <div>
                    <button :class="filterTrajectory == 'a' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterTrajectory('a')">All</button>
                    <button :class="filterTrajectory == 'L' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterTrajectory('L')">Left</button>
                    <button :class="filterTrajectory == 'C' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterTrajectory('C')">Middle</button>
                    <button :class="filterTrajectory == 'R' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterTrajectory('R')">Right</button>
                  </div>
                </div>
              </div>
              <!--Colum-->
              <PrintSprayCage :key="filterTrajectory + selectedPlayer" :coordinates="coordinatesTrayectory"
                :filterPosition="true" :position="filterTrajectory" />
              <!--Colum-->
              <div class="col-span-12 p-2 text-center uppercase bg-fungo-lightblue xl:hidden">TRAJECTORY BREAKDOWN</div>
              <div class="col-span-12 px-2 bg-white xl:col-span-4">
                <section class="mt-[3px] lg:mt-[3px] overflow-x-auto">
                  <table class="w-full space-y-6 border-collapse text-fungo-darkblue">
                    <thead class="bg-fungo-lightblue">
                      <tr class="h-8 bg-white">
                        <th class="ball-header foul"></th>
                        <th class="ball-header weack"></th>
                        <th class="ball-header average"></th>
                        <th class="ball-header hard"></th>
                      </tr>
                      <tr class="h-2">

                      </tr>
                      <tr class="uppercase divide-x divide-[#000]">
                        <th v-for="(heading, index) in averageHeaders" :key="index" class='px-2 py-3 font-fungo-500'
                          v-on:click="$emit('click-header', heading)">
                          <div class="flex justify-center">
                            {{ heading }} <img v-if="isSorteable" src="@/assets/img/icons/sort-solid.svg" alt="sort data"
                              class="w-3 ml-2">
                          </div>
                        </th>
                      </tr>
                    </thead>

                    <tbody>
                      <tr v-if="isLoading" class="w-full">
                        <td colspan="9" class="text-3xl text-center text-fungo-darkblue">Loading data...</td>
                      </tr>
                      <tr v-else-if="!averageData.length > 0">
                        <td colspan="9" class="text-3xl text-center text-fungo-darkblue">No found data</td>
                      </tr>
                      <tr v-else v-for="item in averageData" v-on:click="getSelectedValues(item)" :class="{
                        'bg-fungo-blue text-white': selectedPlayer == item.id,
                      }">
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.fb }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.pf }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.ld }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.gb }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.total }}
                        </td>
                        <td class="min-w-[10px] max-w-[90px]">
                          <div class="flex flex-row justify-start">
                            <div class="pl-2 text-start">
                              <div>
                                <spam>{{ item.player ?? item.team }}</spam>
                              </div>
                              <div>
                                <spam class="font-semibold">{{ item.lastName }}</spam>
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                      <tr v-else v-for="item in averageData" v-on:click="getSelectedValues(item)" :class="{
                        'bg-fungo-blue text-white': selectedPlayer == item.id,
                      }">
                        <td class="text-center h-14 min-w-[10px] max-w-[90px]">
                          {{ item.fb }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.pf }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.ld }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.gb }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.total }}
                        </td>
                        <td class="min-w-[10px] max-w-[90px]">
                          <div class="flex flex-row justify-start">
                            <div class="pl-2 text-start">
                              <div>
                                <spam>{{ item.player ?? item.team }}</spam>
                              </div>
                              <div>
                                <spam class="font-semibold">{{ item.lastName }}</spam>
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </section>
              </div>
            </div>
          </TabPanel>
          <TabPanel>
            <div class="grid grid-cols-3 gap-4 p-2 mt-4 bg-fungo-lightblue">
              <div class="col-span-5 text-center uppercase md:col-span-3 xl:col-span-2">LIST OF EXIT VELOCITY</div>
              <div class="hidden text-center uppercase xl:inline">EXIT VELOCITY</div>
            </div>
            <div class="grid grid-cols-5 gap-4 my-[45px]">
              <!--Colum-->
              <div class="col-span-5 mx-auto w-60 md:w-full md:col-span-1">
                <div class="flex flex-col px-4 py-6 mt-10 bg-white xl:w-40">
                  <span class="mb-2 text-fungo-red">Select</span>
                  <div>
                    <button :class="filterVelocity == 'a' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterVelocity('a')">All</button>
                    <button :class="filterVelocity == 'gb' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterVelocity('gb')">Ground</button>
                    <button :class="filterVelocity == 'pf' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterVelocity('pf')">Pop fly</button>
                    <button :class="filterVelocity == 'fb' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterVelocity('fb')">Fly ball</button>
                    <button :class="filterVelocity == 'ld' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="setFilterVelocity('ld')">Line drive</button>

                  </div>
                </div>
              </div>
              <!--Colum-->
              <div class="col-span-5 px-2 bg-white md:col-span-4 xl:col-span-2 ">
                <DynamicTable :headings="ListThrowsLabel" :table-data="listThrowsData" />
              </div>
              <!--Colum-->
              <div class="col-span-5 p-2 text-center uppercase bg-fungo-lightblue xl:hidden">EXIT VELOCITY</div>
              <div class="col-span-5 px-2 bg-white xl:col-span-2">
                <section class="mt-[3px] lg:mt-[3px] overflow-x-auto pt-2">
                  <table class="w-full space-y-6 border-collapse text-fungo-darkblue">
                    <thead class="bg-fungo-lightblue">
                      <tr class="uppercase divide-x divide-[#000]">
                        <th v-for="(heading, index) in averageHeadersVelocity" :key="index"
                          class='px-2 py-3 font-fungo-500' v-on:click="$emit('click-header', heading)">
                          <div class="flex justify-center">
                            {{ heading }} <img v-if="isSorteable" src="@/assets/img/icons/sort-solid.svg" alt="sort data"
                              class="w-3 ml-2">
                          </div>
                        </th>
                      </tr>
                    </thead>

                    <tbody>
                      <tr v-if="isLoading" class="w-full">
                        <td colspan="9" class="text-3xl text-center text-fungo-darkblue">Loading data...</td>
                      </tr>
                      <tr v-else-if="!averageDataVelocity.length > 0">
                        <td colspan="9" class="text-3xl text-center text-fungo-darkblue">No found data</td>
                      </tr>
                      <tr v-else v-for="item in averageDataVelocity" v-on:click="getSelectedValues(item)" :class="{
                        'bg-fungo-blue text-white': selectedPlayer == item.id,
                      }">
                        <td class="text-center h-14 min-w-[10px] max-w-[90px]">
                          {{ item.min }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.low }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.mid }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.high }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.max }}
                        </td>
                        <td class="text-center min-w-[10px] max-w-[90px]">
                          {{ item.ev }}
                        </td>
                        <td class="min-w-[10px] max-w-[90px]">
                          <div class="flex flex-row justify-start">
                            <div class="pl-2 text-start">
                              <div>
                                <spam>{{ item.player ?? item.team }}</spam>
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </section>
              </div>
            </div>
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
</style>
