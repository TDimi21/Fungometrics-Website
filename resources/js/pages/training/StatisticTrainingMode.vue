<script setup>
import LayoutVue from '../../layout/Layout.vue';
import { defineProps, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useTeamStore } from '@/store/team.js'
import { useTrainingStore } from "../../store/training";
import { useAxiosAuth } from '@/composables/axios-auth.js'
import router from "../../../router";
import { useRoute } from 'vue-router'
import { toast } from "@/utils/AlertPlugin"
import PracticeTitle from '../../components/practice/PracticeTitle.vue';
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import DynamicTable from '../../components/practice/DynamicTable.vue';
import LongTossIcon from '../../components/icons/LongTossIcon.vue';
import TrainingModeIcon from '../../components/icons/TrainingModeIcon.vue';
import WeightBallIcon from '../../components/icons/WeightBallIcon.vue';
import { BigButtonField } from '@/components/form'
import { isArray } from '@vue/shared';
import { SendMsgModal, SendMsgStatusModal } from '@/components/shared'
import useSendMsg from '@/composables/useSendMsg.js'
import Loader from '@/components/Loader.vue'
import { useUserStore } from "../../store/user";

const useTeam = useTeamStore()
const { team } = storeToRefs(useTeam)
const { axiosGet } = useAxiosAuth();
const { userData } = useUserStore();

const { openSendMsgWindow, closeMsgWindow, isShowMsgModal, sendMsg, getSmsPlayers,
  playersStatus, playersToSend, isShowMsgModalStatus, isSending, openStatusModal, statusMsg } = useSendMsg()
const route = useRoute()

const props = defineProps({
  mode: {
    Type: String
  },
  idPractice: {
    Type: String
  }
})
const globalResponse = ref({})
const globalPlayers = ref([])
const truePlayersIds = ref([])
const globalWeightList = ref([])
const headersSort = ref(new Map(
  [
    ['pitch #', 'id'],
    ['player', 'first_name'],
    ['set', 'set'],
    ['distance', 'distance'],
    ['hops', 'hop'],
    ['ball weight', 'ballWeigth'],
    ['velocity', 'velocity'],
    ['trajectory', 'trajectory']
  ]
))

const sorter = ref('id')
const orderAsc = ref(true)

const labelType = ref("")
const labelList = ref("")
const labelAverage = ref("")
const ballxballHeadings = ref([])
const ballxballData = ref([])
const isLoading = ref(true)

const ListThrowsLabel = ref([])
const listThrowsData = ref([])

const averageHeaders = ref([])
const averageData = ref([])
const averageDataTeam = ref([])

const longTossFilter = ref([
  { text: 'NONE', value: 0 },
  { text: '1 HOP', value: 1 },
  { text: '2 HOP', value: 2 },
  { text: '3 HOP', value: 3 }
]);

const ExitVelocityFilter = ref([
  { text: 'line drive', value: 'LD' },
  { text: 'ground ball', value: 'GB' },
  { text: 'fly ball', value: 'FB' },
])

const nameFile = ref('')

const filter = ref("a")
const maxThrow = ref("")
const selectedPlayer = ref('')

const excelHeaderData = ref({})
const excelDataExport = ref([])
const activeRow = ref(0)

onMounted(() => {
  setLabelTitle()
})

const setLabelTitle = () => {
  switch (props.mode) {
    case "EV":
      nameFile.value = 'exitVelocityBallxBallTable.xls'
      excelHeaderData.value = {
        'Pitch #': "id",
        'Player': "player",
        'Set': "set",
        'Trajectory': "trajectory",
        'Velocity': "velocity",
      }
      labelType.value = 'exit velocity'
      labelList.value = 'list of exit velocity'
      labelAverage.value = 'average exit velocity'
      getStatistic('exitvelocity');
      break;
    case "WB":
      nameFile.value = 'weightedBallxBallTable.xls'
      excelHeaderData.value = {
        'Pitch #': "id",
        'Player': "player",
        'Set': "set",
        'Weight': "ballWeigth",
        'Velocity': "velocity",
      }
      labelType.value = 'weighted ball'
      labelList.value = 'list of throws'
      labelAverage.value = 'average velocity'
      getStatistic('weightball');
      break
    default:
      nameFile.value = 'longTossBallxBallTable.xls'
      excelHeaderData.value = {
        'Pitch #': "id",
        'Player': "player",
        'Set': "set",
        'Distance': "distance",
        'Hops': "hop",
      }
      labelType.value = 'long toss'
      labelList.value = 'list of distance'
      labelAverage.value = 'total distance'
      getStatistic('longtoss');
      break;
  }
}

const tabHeading = ref([
  'Ball by Ball', 'Leaders'
])

const getStatistic = async (mode) => {
  try {
    let param = userData.type === 'player' ? '?player=true' : ''
    await axiosGet(`statistics/${props.idPractice}/${mode}${param}`)
      .then((response) => {
        if (response) {
          isLoading.value = false
          globalResponse.value = response
          getPlayersId()
          switch (mode) {
            case 'exitvelocity':
              setExitVelocityBallxBall(response)
              setExitAverageTable(response)
              break;
            case 'weightball':
              setWeigthBallxBall(response)
              getUniqueWeight()
              setWeigthBallTable(response)
              break;
            default:
              setLongTossBallxBall(response)
              setLongTossHopTable(response)
              break;
          }

          excelDataExportTrainigMode()
          sortBy('pitch #')
        }

        isLoading.value = false
      })
  } catch (error) {
    isLoading.value = false
    toast.fire({
      icon: 'error',
      title: 'Error',
      text: 'Not found data',
    })
  }
}

const setLongTossBallxBall = (data) => {
  ballxballHeadings.value = [
    'pitch #',
    'player',
    'set',
    'distance',
    'hops',
  ]
  const ballxball = data.data.data.ball_x_ball
  let tempData = []
  for (const key in ballxball) {
    if (Object.hasOwnProperty.call(ballxball, key)) {
      const element = ballxball[key];
      tempData.push({
        id: element.sort + 1,
        player: getPlayerCell(element.profile.picture, element.profile.first_name, element.profile.last_name, element.id),
        set: element.set,
        distance: element.distance,
        hop: element.hop,
        idPlayer: element.id
      })
    }
  }
  ballxballData.value = getSortData(tempData)
  setLisThrowsLongToss(ballxball)
}

const setExitVelocityBallxBall = (data) => {
  ballxballHeadings.value = [
    'pitch #',
    'player',
    'set',
    'trajectory',
    'velocity',
  ]
  const ballxball = data.data.data.ball_x_ball
  let tempData = []
  for (const key in ballxball) {
    if (Object.hasOwnProperty.call(ballxball, key)) {
      const element = ballxball[key];
      tempData.push({
        id: element.sort + 1,
        player: getPlayerCell(element.profile.picture, element.profile.first_name, element.profile.last_name, element.id),
        set: element.set,
        trajectory: element.trajectory,
        velocity: element.velocity,
        idPlayer: element.id
      }
      )
    }
  }
  ballxballData.value = getSortData(tempData)

  setListThrowExitVelocity(ballxball);
}

const setWeigthBallxBall = (data) => {
  ballxballHeadings.value = [
    'pitch #',
    'player',
    'set',
    'ball weight',
    'velocity',
  ]

  const ballxball = data.data.data.ball_x_ball
  let n = 1
  let tempData = []
  for (const key in ballxball) {
    if (Object.hasOwnProperty.call(ballxball, key)) {
      const element = ballxball[key];
      console.log('ssg', element);
      tempData.push({
        id: element.sort + 1,
        player: getPlayerCell(element.profile.picture, element.profile.first_name, element.profile.last_name, element.id),
        set: element.set,
        ballWeigth: element.weight,
        velocity: element.velocity,
        idPlayer: element.id
      })
    }
  }

  ballxballData.value = getSortData(tempData)
  setListThrowWeightBall(ballxball)
}


const setLisThrowsLongToss = (data) => {
  let distances = [];
  ListThrowsLabel.value = [
    'throws #',
    'distance'
  ]

  let n = 1;
  listThrowsData.value = []
  for (const key in data) {
    if (Object.hasOwnProperty.call(data, key)) {
      const element = data[key];
      distances.push(element.distance)

      listThrowsData.value.push({
        id: n++,
        distance: element.distance
      })
    }
  }

  maxThrow.value = Math.max(...distances)
}

const setListThrowExitVelocity = (data) => {
  let velocity = [];
  ListThrowsLabel.value = [
    'throws #',
    'velocity'
  ]

  let n = 1;
  listThrowsData.value = []
  for (const key in data) {
    if (Object.hasOwnProperty.call(data, key)) {
      const element = data[key];
      velocity.push(element.velocity)

      listThrowsData.value.push({
        id: n++,
        velocity: element.velocity
      })
    }
  }

  maxThrow.value = Math.max(...velocity)
}

const setListThrowWeightBall = (data) => {
  let velocity = [];
  ListThrowsLabel.value = [
    'throws #',
    'velocity'
  ]

  let n = 1;
  listThrowsData.value = []
  for (const key in data) {
    if (Object.hasOwnProperty.call(data, key)) {
      const element = data[key];
      velocity.push(element.velocity)

      listThrowsData.value.push({
        id: n++,
        velocity: element.velocity
      })
    }
  }

  maxThrow.value = Math.max(...velocity)
}

const getPlayerCell = (picture, name, lastName, id) => {
  picture = picture ? picture : 'https://fungometrics.s3.amazonaws.com/logo.png';
  return `
  <td id="${id}">
    <div class="flex flex-row justify-start">
      <img src="${picture}" class="w-12 h-12 rounded-full"  alt="">
      <div class="pl-2 text-start">
        <div>
          <span>${name} ${lastName}</span>
        </div>
        <div>
          <span>${lastName}</span>
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
          <span>${name} ${lastName}</span>
        </div>
        <div>
          <span>${lastName}</span>
        </div>
      </div>
    </div>
  </td>`
}

const getTeamCellWithinPicture = () => {
  return `
  <td id="${team.value.id}">
    <div class="flex flex-row justify-start">
      <div class="pl-2 text-start">
        <div>
          <span>${team.value.name}</span>
        </div>
      </div>
    </div>
  </td>`
}

const filterLongToss = (value) => {
  filter.value = value
  let dataFiltered = []
  console.log("Paso3");
  if (value == 'a') {
    console.log("Paso a");
    if (selectedPlayer.value == '') {
      console.log("Paso b");
      setLisThrowsLongToss(globalResponse.value.data.data.ball_x_ball)
    } else {
      console.log("Paso c");
      console.log(selectedPlayer.value);
      dataFiltered = Object.values(globalResponse.value.data.data.ball_x_ball).filter((item) => item.profile.id == selectedPlayer.value)
      setLisThrowsLongToss(dataFiltered)
    }
  } else {
    console.log("Paso 1.1");
    if (selectedPlayer.value == '') {
      console.log("Paso 1.2");
      dataFiltered = Object.values(globalResponse.value.data.data.ball_x_ball).filter((item) => item.hop == value)
      setLisThrowsLongToss(dataFiltered)
    } else {
      console.log("Paso 1.3");
      dataFiltered = Object.values(globalResponse.value.data.data.ball_x_ball).filter((item) => (item.hop == value && item.profile.id == selectedPlayer.value))
      setLisThrowsLongToss(dataFiltered)
    }
  }
}

const filterExitVelocity = (value) => {
  filter.value = value
  let dataFiltered = []
  if (value == 'a') {
    if (selectedPlayer.value == '') {
      setListThrowExitVelocity(globalResponse.value.data.data.ball_x_ball)
    } else {
      dataFiltered = Object.values(globalResponse.value.data.data.ball_x_ball).filter((item) => item.profile.id == selectedPlayer.value)
      setListThrowExitVelocity(dataFiltered)
    }
  } else {
    if (selectedPlayer.value == '') {
      dataFiltered = Object.values(globalResponse.value.data.data.ball_x_ball).filter((item) => item.trajectory == value)
      setListThrowExitVelocity(dataFiltered)
    } else {
      dataFiltered = Object.values(globalResponse.value.data.data.ball_x_ball).filter((item) => item.trajectory == value && item.profile.id == selectedPlayer.value)
      setListThrowExitVelocity(dataFiltered)
    }
  }
}

const filterWeigthBall = (value) => {
  filter.value = value
  let dataFiltered = []
  if (value == 'a') {
    if (selectedPlayer.value == '') {
      setListThrowWeightBall(globalResponse.value.data.data.ball_x_ball)
    } else {
      dataFiltered = Object.values(globalResponse.value.data.data.ball_x_ball).filter((item) => item.profile.id == selectedPlayer.value)
      setListThrowWeightBall(dataFiltered)
    }
  } else {
    if (selectedPlayer.value == '') {
      dataFiltered = Object.values(globalResponse.value.data.data.ball_x_ball).filter((item) => item.weight == value)
      setListThrowWeightBall(dataFiltered)
    } else {
      dataFiltered = Object.values(globalResponse.value.data.data.ball_x_ball).filter((item) => item.weight == value && item.profile.id == selectedPlayer.value)
      setListThrowWeightBall(dataFiltered)
    }
  }
}

const getPlayersId = () => {

  let dataArrayValues = Object.values(globalResponse.value.data.data.ball_x_ball).map((item, key) => {
    return item.profile.id
  })

  let dataTruePlayersIds = Object.values(globalResponse.value.data.data.ball_x_ball).map((item, key) => {
    return item.profile.user_id
  })

  globalPlayers.value = dataArrayValues.filter((item, index) => {
    return dataArrayValues.indexOf(item) === index
  })

  truePlayersIds.value = dataTruePlayersIds.filter((item, index) => {
    return dataTruePlayersIds.indexOf(item) === index
  })
}

const getUniqueWeight = () => {
  let dataArrayValues = Object.values(globalResponse.value.data.data.ball_x_ball).map((item, key) => {
    return item.weight
  })
  let tempList = []
  tempList = dataArrayValues.filter((item, index) => {
    return dataArrayValues.indexOf(item) === index
  })

  globalWeightList.value = tempList.sort((a, b) => a - b)
}

const setLongTossHopTable = (data) => {
  averageHeaders.value = [
    '1 hop',
    '2 hop',
    '3 hop',
    'no hop',
    'max dis',
    'player'
  ]
  const listBalls = data.data.data.ball_x_ball
  let hop1Team = {
    value: 0.0,
    throws: 0
  }
  let hop2Team = {
    value: 0.0,
    throws: 0
  }
  let hop3Team = {
    value: 0.0,
    throws: 0
  }
  let noHopTeam = {
    value: 0.0,
    throws: 0
  }
  let distanceTeam = []
  averageData.value = []
  globalPlayers.value.forEach(playerId => {
    let firstName = '';
    let lastName = ''
    let hop1 = {
      value: 0.0,
      throws: 0
    }
    let hop2 = {
      value: 0.0,
      throws: 0
    }
    let hop3 = {
      value: 0.0,
      throws: 0
    }
    let noHop = {
      value: 0.0,
      throws: 0
    }
    let distance = []
    for (const key in listBalls) {
      if (Object.hasOwnProperty.call(listBalls, key)) {
        const element = listBalls[key];
        if (playerId == element.profile.id) {
          distance.push(element.distance)
          distanceTeam.push(element.distance)
          firstName = element.profile.first_name
          lastName = element.profile.last_name
          switch (element.hop) {
            case 1:
              hop1.value += element.distance
              hop1.throws++
              hop1Team.value += element.distance
              hop1Team.throws++
              break;
            case 2:
              hop2.value += element.distance
              hop2.throws++
              hop2Team.value += element.distance
              hop2Team.throws++
              break;
            case 3:
              hop3.value += element.distance
              hop3.throws++
              hop3Team.value += element.distance
              hop3Team.throws++
              break;
            default:
              noHop.value += element.distance
              noHop.throws++
              noHopTeam.value += element.distance
              noHopTeam.throws++
              break;
          }
        }

      }
    }

    averageData.value.push([
      isNaN(hop1.value / hop1.throws) ? 0 : Math.round((hop1.value / hop1.throws) * 100) / 100,
      isNaN(hop2.value / hop2.throws) ? 0 : Math.round((hop2.value / hop2.throws) * 100) / 100,
      isNaN(hop3.value / hop3.throws) ? 0 : Math.round((hop3.value / hop3.throws) * 100) / 100,
      isNaN(noHop.value / noHop.throws) ? 0 : Math.round((noHop.value / noHop.throws) * 100) / 100,
      Math.round(Math.max(...distance) * 100) / 100,
      getPlayerCellWithinPicture(firstName, lastName, playerId)
    ])
  });

  averageDataTeam.value.unshift([
    isNaN(hop1Team.value / hop1Team.throws) ? 0 : Math.round((hop1Team.value / hop1Team.throws) * 100) / 100,
    isNaN(hop2Team.value / hop2Team.throws) ? 0 : Math.round((hop2Team.value / hop2Team.throws) * 100) / 100,
    isNaN(hop3Team.value / hop3Team.throws) ? 0 : Math.round((hop3Team.value / hop3Team.throws) * 100) / 100,
    isNaN(noHopTeam.value / noHopTeam.throws) ? 0 : Math.round((noHopTeam.value / noHopTeam.throws) * 100) / 100,
    Math.max(...distanceTeam),
    getTeamCellWithinPicture()
  ])
}

const setExitAverageTable = (data) => {
  averageHeaders.value = [
    'line drive',
    'fly ball',
    'ground ball',
    'max ev',
    'player'
  ]

  const listBalls = data.data.data.ball_x_ball
  let velocityTeam = []
  let ldTeam = {
    value: 0.0,
    throws: 0
  }
  let fbTeam = {
    value: 0.0,
    throws: 0
  }
  let gbTeam = {
    value: 0.0,
    throws: 0
  }

  globalPlayers.value.forEach(playerId => {
    let firstName = '';
    let lastName = ''
    let velocity = []
    let ld = {
      value: 0.0,
      throws: 0
    }
    let fb = {
      value: 0.0,
      throws: 0
    }
    let gb = {
      value: 0.0,
      throws: 0
    }

    for (const key in listBalls) {
      if (Object.hasOwnProperty.call(listBalls, key)) {
        const element = listBalls[key];
        if (playerId == element.profile.id) {
          firstName = element.profile.first_name
          lastName = element.profile.last_name
          velocity.push(element.velocity)
          velocityTeam.push(element.velocity)
          switch (element.trajectory) {
            case 'LD':
              ld.value += element.velocity
              ld.throws++
              ldTeam.value += element.velocity
              ldTeam.throws++
              break;
            case 'FB':
              fb.value += element.velocity
              fb.throws++
              fbTeam.value += element.velocity
              fbTeam.throws++
              break;
            case 'GB':
              gb.value += element.velocity
              gb.throws++
              gbTeam.value += element.velocity
              gbTeam.throws++
              break;
            default:

              break;
          }
        }

      }
    }

    averageData.value.push([
      isNaN(ld.value / ld.throws) ? 0 : Math.round((ld.value / ld.throws) * 100) / 100,
      isNaN(fb.value / fb.throws) ? 0 : Math.round((fb.value / fb.throws) * 100) / 100,
      isNaN(gb.value / gb.throws) ? 0 : Math.round((gb.value / gb.throws) * 100) / 100,
      Math.round(Math.max(...velocity) * 100) / 100,
      getPlayerCellWithinPicture(firstName, lastName, playerId)
    ])
  });

  averageDataTeam.value.unshift([
    isNaN(ldTeam.value / ldTeam.throws) ? 0 : Math.round((ldTeam.value / ldTeam.throws) * 100) / 100,
    isNaN(fbTeam.value / fbTeam.throws) ? 0 : Math.round((fbTeam.value / fbTeam.throws) * 100) / 100,
    isNaN(gbTeam.value / gbTeam.throws) ? 0 : Math.round((gbTeam.value / gbTeam.throws) * 100) / 100,
    Math.round(Math.max(...velocityTeam) * 100) / 100,
    getTeamCellWithinPicture()
  ])
}

const setWeigthBallTable = (data) => {
  averageHeaders.value = [
    ...globalWeightList.value,
    'max ev',
    'player'
  ]
  const listBalls = Object.values(data.data.data.ball_x_ball)
  let velocityList = []
  let maxVelocityList = []
  let promVelocityList = []
  globalWeightList.value.forEach((obj, index) => {
    velocityList[index] = {
      value: obj,
      data: []
    }
    promVelocityList[index] = []
  })
  velocityList.forEach((itemArray) => {
    listBalls.forEach((obj) => {
      if (obj.weight == itemArray.value) {
        itemArray.data.push(obj.velocity)
      }
    })
  })
  globalPlayers.value.forEach(playerId => {
    let ite = 0
    let sumVelocityList = []
    let allVelocityList = []
    let firstName = ''
    let lastName = ''
    listBalls.forEach((register) => {
      if (register.profile.id == playerId) {
        allVelocityList.push(register.velocity)
      }
    })
    globalWeightList.value.forEach((element) => {
      let dataFiltered = []
      let playerData = listBalls.filter((item) => item.profile.id == playerId)
      firstName = playerData[0].profile.first_name
      lastName = playerData[0].profile.last_name

      dataFiltered = listBalls.filter((item) => item.weight == element && item.profile.id == playerId)
      if (dataFiltered.length) {
        let iteNumber = dataFiltered.map((item) => item.velocity).length
        sumVelocityList[ite] = Math.round(dataFiltered.map((item) => item.velocity).reduce((a, b) => a + b, 0) * 100) / 100
        sumVelocityList[ite] = Math.round((sumVelocityList[ite] / iteNumber) * 100) / 100
      } else {
        sumVelocityList[ite] = 0
      }
      ite++
    })

    averageData.value.push([
      ...sumVelocityList,
      Math.max(...allVelocityList),
      getPlayerCellWithinPicture(firstName, lastName, playerId)
    ])
  })
  velocityList.forEach((itemArray, index) => {
    let sum = 0
    itemArray.data.forEach((obj) => {
      sum += obj
      maxVelocityList.push(obj)
    })
    promVelocityList[index] = Math.round((sum / itemArray.data.length) * 100) / 100
  })
  averageDataTeam.value.unshift([
    ...promVelocityList,
    Math.max(...maxVelocityList),
    getTeamCellWithinPicture()
  ])
}


const getListForPlayer = (event) => {
  if (event.player == 'player') {
    activeRow.value = event.index + 1
  } else {
    activeRow.value = 0
  }
  let id = clearElement(event.item)
  if (id != team.value.id) {
    selectedPlayer.value = id;
  } else {
    selectedPlayer.value = '';
  }

  switch (props.mode) {
    case 'EV':
      filterExitVelocity(filter.value ?? 'a')
      break;
    case 'WB':
      filterWeigthBall(filter.value ?? 'a')
      break;
    default:
      filterLongToss(filter.value ?? 'a')
      break;
  }

}

const clearElement = (data) => {
  let value = isArray(data) ? data[data.length - 1] : data
  value = value.match(/id=".*"/)[0].toString().replace(`id="`, "").replace(`"`, "");
  return value
}

const sortBy = (event) => {
  orderAsc.value = !orderAsc.value
  sorter.value = headersSort.value.get(event)
  switch (props.mode) {
    case 'EV':
      setExitVelocityBallxBall(globalResponse.value)
      break;
    case 'WB':
      setWeigthBallxBall(globalResponse.value)
      break;
    default:
      setLongTossBallxBall(globalResponse.value)
      break;
  }
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

const getEditData = (event) => {
  let id = event.idPlayer
  let data = Object.values(globalResponse.value.data.data.ball_x_ball)
  let editPlayer = data.find(element => element.id === id)
  let setData = {
    "balls": 0,
    "bxs": 0,
    "set": editPlayer.set
  }
  let editData = getDataForMode(editPlayer)
  let training = useTrainingStore()
  training.countThrowArray = {}
  training.countThrowArray[editPlayer.user_id] = setData
  training.setDataTraining(editData)

  router.push({
    path: '/track/training-mode/' + props.mode,
  })
}


const getDataForMode = (editPlayer) => {
  let editData = {}
  switch (props.mode) {
    case 'EV':
      editData = {
        'players': [{
          'id': editPlayer.user_id,
          'name': {
            'first': editPlayer.profile.first_name,
            'last': editPlayer.profile.last_name,
            'full': editPlayer.profile.first_name + ' ' + editPlayer.profile.last_name
          }
        }],
        'id': editPlayer.id,
        'practice_id': editPlayer.practice_id,
        'sort': editPlayer.sort,
        'trajectory': editPlayer.trajectory,
        'user_id': editPlayer.user_id,
        'velocity': editPlayer.velocity,
        'set': editPlayer.set,
        'team_id': editPlayer.team_id
      }
      break;

    case 'WB':
      editData = {
        'players': [{
          'id': editPlayer.user_id,
          'name': {
            'first': editPlayer.profile.first_name,
            'last': editPlayer.profile.last_name,
            'full': editPlayer.profile.first_name + ' ' + editPlayer.profile.last_name
          }
        }],
        'id': editPlayer.id,
        'practice_id': editPlayer.practice_id,
        'sort': editPlayer.sort,
        'user_id': editPlayer.user_id,
        'velocity': editPlayer.velocity,
        'weight': editPlayer.weight,
        'set': editPlayer.weight,
        'team_id': editPlayer.team_id
      }
      break;

    default:
      editData = {
        'players': [{
          'id': editPlayer.user_id,
          'name': {
            'first': editPlayer.profile.first_name,
            'last': editPlayer.profile.last_name,
            'full': editPlayer.profile.first_name + ' ' + editPlayer.profile.last_name
          }
        }],
        'id': editPlayer.id,
        'practice_id': editPlayer.practice_id,
        'sort': editPlayer.sort,
        'user_id': editPlayer.user_id,
        'distance': editPlayer.distance,
        'hop': editPlayer.hop,
        'set': editPlayer.set,
        'team_id': editPlayer.team_id
      }
      break;
  }

  return editData;
}

const excelDataExportTrainigMode = () => {
  excelDataExport.value = ballxballData.value = ballxballData.value.map((element) => {
    let name = element.player
    let data = name.match(/[\s\S]<span>.*?<\/span>/).toString()
    element.player = data.replace(/<\/?span>/gi, '').trim()
    return element
  })
}

const send = () => {
  // console.log(globalResponse.value.data.data.ball_x_ball);
  // sendMsg(Object.values(globalResponse.value.data.data.ball_x_ball), route.params.idPractice)
  sendMsg(route.params.idPractice)
}
if (userData.type === 'coach') {
  getSmsPlayers(route.params.idPractice)
}
</script>
<template>
  <LayoutVue>
    <Loader v-show="isSending" />
    <PracticeTitle class="capitalize" :title="labelType + ' statistics'" />
    <section class="flex w-full justify-between px-[5%]">
      <div class="flex items-center w-max">
        <LongTossIcon class="w-12" v-if="props.mode == 'LT'" />
        <TrainingModeIcon class="w-12" v-else-if="props.mode == 'EV'" />
        <WeightBallIcon class="w-12" v-else-if="props.mode == 'WB'" />
        <download-excel class="flex w-[100px] gap-2 bg-white p-3 rounded-r-full" :data="excelDataExport"
          :fields="excelHeaderData" :name="nameFile">
          <div>Excel</div>
          <svg width="18" height="22" viewBox="0 0 18 22" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd"
              d="M18 7.71429H12.8571V0H5.14286V7.71429H0L9 16.7143L18 7.71429ZM7.71307 10.2863V2.57202H10.2845V10.2863H11.7888L8.99878 13.0763L6.20878 10.2863H7.71307ZM18 21.8571V19.2856H0V21.8571H18Z"
              fill="#E10600" />
          </svg>
        </download-excel>
      </div>
      <div class="flex space-x-4">
        <template v-if="route.params.isComplete == 'true'">
          <form v-if="statusMsg === false" @submit.prevent="openSendMsgWindow(truePlayersIds, 'mode')">
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
            <DynamicTable :actionable="true" :is-sorteable="true" v-on:edit-event="getEditData($event)"
              v-on:click-header="sortBy($event)" :is-loading="isLoading" :headings="ballxballHeadings"
              :table-data="ballxballData" />
          </TabPanel>
          <TabPanel>
            <div class="grid grid-cols-3 gap-4 p-2 mt-4 bg-fungo-lightblue">
              <div class="col-span-5 text-center uppercase md:col-span-3 xl:col-span-2">{{ labelList }}</div>
              <div class="hidden uppercase xl:inline">{{ labelAverage }}</div>
            </div>
            <div class="grid grid-cols-5 gap-4 my-[45px]">
              <!--Colum-->
              <div class="col-span-5 mx-auto w-60 md:w-full md:col-span-1">
                <div class="flex pl-2 bg-white border-l-4 justify-beteewn h-14 border-fungo-red xl:w-40">
                  <span class="text-fungo-red place-self-center">Max #</span>
                  <span class="px-4 text-xl place-self-center font-fungo-800">{{ maxThrow }}</span>
                </div>
                <div class="flex flex-col px-4 py-6 mt-10 bg-white xl:w-40">
                  <span class="mb-4 text-fungo-red">Select</span>
                  <div v-if="props.mode == 'LT'">
                    <button :class="filter == 'a' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="filterLongToss('a')">All</button>
                    <button v-for="item in longTossFilter" :class="filter == item.value ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="filterLongToss(item.value)">{{ item.text }}</button>
                  </div>
                  <div v-if="props.mode == 'EV'">
                    <button :class="filter == 'a' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="filterExitVelocity('a')">All</button>
                    <button v-for="item in ExitVelocityFilter" :class="filter == item.value ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="filterExitVelocity(item.value)">{{ item.text }}</button>
                  </div>
                  <div v-if="props.mode == 'WB'">
                    <button :class="filter == 'a' ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="filterWeigthBall('a')">All</button>
                    <button v-for="item in globalWeightList" :class="filter == item ? 'is-active' : ''"
                      class="w-full mt-4 border border-1 border-fungo-red text-fungo-red"
                      @click="filterWeigthBall(item)">{{ item }}</button>
                  </div>
                </div>
              </div>
              <!--Colum-->
              <div class="col-span-5 px-2 bg-white md:col-span-4 xl:col-span-2 ">
                <DynamicTable :headings="ListThrowsLabel" :table-data="listThrowsData" />
              </div>
              <!--Colum-->
              <div class="col-span-5 p-2 text-center uppercase bg-fungo-lightblue xl:hidden">{{ labelAverage }}</div>
              <div class="col-span-5 px-2 bg-white xl:col-span-2">
                <DynamicTable v-on:clicked-data="getListForPlayer($event)" :headings="averageHeaders" :have_team="true"
                  :table-dataTeam="averageDataTeam" :table-data="averageData" :activeRow="activeRow" />
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
</style>
