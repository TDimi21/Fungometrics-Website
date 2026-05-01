<script setup xmlns="http://www.w3.org/1999/html">
import Layout from "../../layout/Layout.vue"
import {useTeamStore} from "../../store/team";
import {useTrainingStore} from "../../store/training";
import {reactive, ref, onMounted, onUnmounted } from "vue";
import GridCatcher from "./GridCatcher.vue";
import GridField from './GridField.vue'
import VelocityInput from "../../components/VelocityInput.vue";
import {toast} from "@/utils/AlertPlugin"
import {useAxiosAuth} from '@/composables/axios-auth.js'
import { Modal } from '@/components/shared'
import { storeToRefs } from 'pinia'
import Loader from "../../components/Loader.vue";
import { useRouter } from 'vue-router'
import DefaultImg from '@/assets/img/noavatar.png'
import LiveABLogoPractice from "../../components/graphics/LiveABLogoPractice.vue";
import { PitchAndTrajectoryBtn, Counters, EndLiveABModal, BasesAndQtyContact } from '@/components/liveAB'
import { usePlayerStore } from '@/store/players.js'
import { useLiveABStore } from '@/store/liveAB.js'

const { players } = usePlayerStore()
const { setStatusPlayers, getStatusPlayer } = useLiveABStore()

const {team, teams} = useTeamStore();
const {axiosPost, axiosPut, axiosGet} = useAxiosAuth()
const training = useTrainingStore();
const router = useRouter()

const isLoading = reactive({status: true})
const currentBatterId = ref('')
const currentPitcherID = ref('')
const { liveTurn, livePitches } = storeToRefs(training)
const playerCardPitcher = ref(training.trainingActive.players['pitchers'][0]);
const playerListPitchers = [...training.trainingActive.players['pitchers']];
const playerCardBatter = ref(training.trainingActive.players['batters'][0]);
const playerListBatters = [...training.trainingActive.players['batters']];

const totalCounters = ref({
  pitches: 0,
  pitchesVsBatters: 0,
  balls: 0,
  strike: 0
})
const isMiss = ref(false)

const balls = ref(0);
const change = ref(0);
const endNote = ref('');
const picked = ref('progress');
const sort = ref( training.trainingActive.sort ?? 0);
const dataPlayer = ref('');
const isOpenAdd = ref(false)
const isOpenAdd2 = ref(false)
const hittersToAddList = ref([]);
const pitchersToAddList = ref([])

/* all data to process  */
const gridPitcherData = ref()
const gridBattingData = ref()

const dataProcess = ref({
  practice_id: training.trainingActive.id,
  sort: totalCounters.value.pitches,
  bases: 0,
  pitch_location: '',
  pitch_mark: '',
  zone: 'T',
  type_of_hit: 'TK',
  is_contact: false,
  turn: {
    turn: liveTurn,
    pitches: livePitches,
    strike: totalCounters.value.strike,
    ball: totalCounters.value.balls,
    is_over: false,
  },
  pitching: {
    team_id: training.trainingActive.teams[0].team_id ? training.trainingActive.teams[0].team_id : training.trainingActive.teams[0].id,
    pitcher_id: playerCardPitcher.value.id,
    miles_per_hour: 0,
    type_throw: 'N',
  },
  batting: {
    team_id: training.trainingActive.teams[1].team_id ? training.trainingActive.teams[1].team_id : training.trainingActive.teams[1].id,
    batter_id: playerCardBatter.value.id,
    quality_of_contact: 'N',
    field_mark: '',
    field_direction: '',
    velocity: 0
  }
});

const dataEdit = ref({
  pitch: {
    strike: {}
  },
  field: {}
})

const isOpen = ref(false)

function closeModal() {
  isOpen.value = false
}

function openModal() {
  isOpen.value = true
}

const isChangeHitter = ref(false)

const changeDataPitchers = (event) => {
  if (training.trainingActive.sort != null) {
    const elementID = event.target.value;
    playerCardPitcher.value = playerListPitchers.find((item) => item.id === elementID)
    dataProcess.value.pitching.pitcher_id = playerCardPitcher.value.id
  } else {
    const elementID = event.target.value;
    playerCardPitcher.value = playerListPitchers.find((item) => item.id === elementID)
    dataProcess.value.pitching.pitcher_id = playerCardPitcher.value.id
    change.value += 1;
    localStorage.removeItem('pitch')
    dataProcess.value.batting.velocity = 0
  
    // training.increaseTurn()
  
    totalCounters.value.pitchesVsBatters = getStatusPlayer(playerCardPitcher.value.id, playerCardBatter.value.id)
    localStorage.setItem('currentPitcherID', elementID)
  }

}

const changeDataBatters = (event) => {
  if (training.trainingActive.sort != null) {
    const elementID = event.target.value;
    playerCardBatter.value = playerListBatters.find((item) => item.id === elementID)
    dataProcess.value.batting.batter_id = playerCardBatter.value.id
  } else {
    const elementID = event.target.value;
    playerCardBatter.value = playerListBatters.find((item) => item.id === elementID)
    dataProcess.value.batting.batter_id = playerCardBatter.value.id
    change.value += 1;
    localStorage.removeItem('pitch')
    dataProcess.value.batting.velocity = 0
  
    // training.increaseTurn()
  
    totalCounters.value.pitchesVsBatters = getStatusPlayer(playerCardPitcher.value.id, playerCardBatter.value.id)
  
    localStorage.setItem('currentBatterId', elementID)
  }

}


const setTrajectory = (event) => {
  setGridCatcherData()

  if (dataProcess.value.pitch_mark === '') {
    clearStyle('active-btn-trajectory')
    toast.fire({
      icon: 'warning',
      title: 'Validation',
      text: 'If you not select a field and type of contact You dont set a Trajectory !!! ',
    })
  } else {
    clearStyle('active-btn-trajectory')
    event.target.classList.add('active-btn-trajectory')
    dataProcess.value.type_of_hit = event.target.value
  }
}
const setTypeThrow = (event) => {
  setGridCatcherData()

  if (dataProcess.value.pitch_mark === '') {
    clearStyle('active-btn-contact')
    toast.fire({
      icon: 'warning',
      title: 'Validation',
      text: 'If you not select a field zone only you register Miss/Foul contact !!! ',
    })
  } else {
    clearStyle('active-btn-contact')
    event.target.classList.add('active-btn-contact')
    dataProcess.value.pitching.type_throw = event.target.value
  }
}

const setTotalBase = (event) => {
  dataProcess.value.batting.field_direction = gridBattingData.value.zone
  dataProcess.value.batting.field_mark = gridBattingData.value.point

  if (dataProcess.value.batting.field_mark == '') {
    clearStyle('active-btn-contact')
    toast.fire({
      icon: 'warning',
      title: 'Validation',
      text: 'Please, select point on field',
    })
  } else {
    clearStyle('active-btn-contact')
    event.target.classList.add('active-btn-contact')
    dataProcess.value.bases = event.target.value
  }
}

const setQtyOfContact = (event) => {
   if (dataProcess.value.batting.field_mark == '') {
     clearStyle('active-btn-trajectory')
     toast.fire({
       icon: 'warning',
       title: 'Validation',
       text: 'Please, select point on field',
     })
   } else {
    clearStyle('active-btn-trajectory')
    event.target.classList.add('active-btn-trajectory')
    dataProcess.value.batting.quality_of_contact = event.target.value
   }
}

const clearStyle = (className) => {
  let btns = document.getElementsByClassName(className);
  if (btns !== undefined) {
    [].forEach.call(btns, function (el) {
      el.classList.remove(className);
    });
  }
}

const setGridCatcherData = () => {
  if (gridPitcherData.value !== undefined) {
    dataProcess.value.pitch_mark = gridPitcherData.value.point
    dataProcess.value.pitch_location = gridPitcherData.value.position
    dataProcess.value.zone = gridPitcherData.value.strike.status ? 'S' : 'B'
  }
}

const save = async () => {
  await setGridCatcherData()

  dataProcess.value.is_contact !== undefined ? dataProcess.value.is_contact = true : dataProcess.value.is_contact = false

  if (validations()) {

    if ( !['SM', 'F', 'HBP', 'TK'].includes(dataProcess.value.type_of_hit)) {

      isMiss.value = true
      if(training.trainingActive.sort != null){
          setEditBatters()
        }
    } else {
      await determineBallOrStrike()
      // dataProcess.value.batting.quality_of_contact = await dataProcess.value.type_of_hit
      dataProcess.value.batting.field_mark = 0

      try {
        isLoading.status = !isLoading.status
        if(training.trainingActive.sort != null){
          if ( !['SM', 'F', 'HBP', 'TK'].includes(dataProcess.value.type_of_hit)) {

            isMiss.value = true
            setEditBatters()
          }else {
            setEditPtcher()
          }
        }else {
          /* this condition is only verified if turn of pitcher has end
            the proccess should be continued indenpendent of this
           */
          if (dataProcess.value.turn.ball == 3 || dataProcess.value.turn.strike == 3 || dataProcess.value.type_of_hit == 'HBP') {
            dataProcess.value.turn.is_over = true
          }

          if (dataProcess.value.turn.ball === 3) dataProcess.value.bases = 4
          if (dataProcess.value.turn.strike === 2) dataProcess.value.bases = 7
          if (dataProcess.value.type_of_hit == 'HBP') dataProcess.value.bases = 6

          await axiosPost('result/liveab', dataProcess.value).then(async (response) => {
            if (response) {
              toast.fire({
                icon: 'success',
                title: 'Save training',
                text: 'Training saved',
              })
              // training.increasePitches()
              totalCounters.value.pitchesVsBatters++

              totalCounters.value.balls = response.data.data.turn.ball
              totalCounters.value.strike = response.data.data.turn.strike
              localStorage.setItem('countBall', response.data.data.turn.ball)
              localStorage.setItem('countStrike', response.data.data.turn.strike)

              training.increaseTurn()
              /* save state of data */
              setStatusPlayers( playerCardPitcher.value.id, playerCardBatter.value.id, totalCounters.value.pitchesVsBatters)

              if (response.data.data.turn.is_over) {
                localStorage.removeItem('countBall')
                localStorage.removeItem('countStrike')
                isChangeHitter.value = true

                // totalCounters.value = { pitches: 0, pitchesVsBatters: 0, balls: 0, strike: 0 }
                totalCounters.value.balls = 0
                totalCounters.value.strike = 0
                dataProcess.value.turn.ball = 0
                dataProcess.value.turn.strike = 0

                training.resetTurn()
              }
            }
          })
        }
      } catch (error) {
        console.log('error', error);
      } finally {
        isLoading.status = !isLoading.status
        await resetDataProcess()
      }
    }

  }
}

const resetDataProcess = () => {
  localStorage.removeItem('pitch')
  change.value += 1;
  dataProcess.value = {
    practice_id: training.trainingActive.id,
    sort: totalCounters.value.pitches,
    bases: 0,
    pitch_location: '',
    pitch_mark: '',
    type_of_hit: 'TK',
    is_contact: false,
    turn: {
      turn: liveTurn,
      pitches: livePitches,
      strike: totalCounters.value.strike,
      ball: totalCounters.value.balls,
      is_over: false,
    },
    pitching: {
      team_id: training.trainingActive.teams[0].team_id ? training.trainingActive.teams[0].team_id : training.trainingActive.teams[0].id,
      pitcher_id: playerCardPitcher.value.id,
      miles_per_hour: 0,
      type_throw: 'N',
    },
    batting: {
      team_id: training.trainingActive.teams[0].team_id ? training.trainingActive.teams[0].team_id : training.trainingActive.teams[0].id,
      batter_id: playerCardBatter.value.id,
      quality_of_contact: 'N',
      field_mark: '',
      field_direction: '',
      velocity: 0
    }
  }

  gridPitcherData.value = ''
  gridBattingData.value = ''
  clearStyle('active-btn-contact')
  clearStyle('active-btn-trajectory')
}

const determineBallOrStrike = () => {


  totalCounters.value.pitches++
}

/* FIXME  Undefined array key "sort" when edit from stats*/
const saveWithBattingData = async() => {
  try {

    isLoading.status = !isLoading.status
    if(training.trainingActive.sort != null){
      // await axiosGet('result/liveab/'+ training.trainingActive.id).then((response)=>{
      //   response;
      // });
      let updateData = {
        'turn': {
          'turn' : training.trainingActive.turn,
          'pitches' : training.trainingActive.turn_pitches,
          'strike' :  training.trainingActive.turn_strike,
          'ball' : training.trainingActive.turn_ball,
          'is_over' : training.trainingActive.turn_is_over,
        },
        'sort' : training.trainingActive.sort,
        'bases' : dataProcess.value.bases,
        'pitch_location' : dataProcess.value.pitch_location,
        'pitch_mark' : dataProcess.value.pitch_mark,
        'type_of_hit' : dataProcess.value.type_of_hit,
        'is_contact' : dataProcess.value.is_contact,
        'zone': dataProcess.value.zone,
        'batting': {
          'quality_of_contact' : dataProcess.value.batting.quality_of_contact,
          'field_mark' : dataProcess.value.batting.field_mark,
          'field_direction' : dataProcess.value.batting.field_direction,
          'velocity' : dataProcess.value.batting.velocity,
          'batter_id': dataProcess.value.batting.batter_id,
        },
        'pitching': {
          'miles_per_hour' : dataProcess.value.pitching.miles_per_hour,
          'type_throw' : dataProcess.value.pitching.type_throw,
          'pitcher_id': dataProcess.value.pitching.pitcher_id,
        }        
      }
      await axiosPut('result/liveab/'+ training.trainingActive.id, updateData)
      .then(async (response) => {
        if (response) {
          toast.fire({
            icon: 'success',
            title: 'Update training',
            text: 'Training updated',
          })
          router.push({
            path: '/trainingb/live'
          })
        }
      })
    }else {
      await axiosPost('result/liveab', dataProcess.value).then(async (response) => {
        if (response) {
          toast.fire({
            icon: 'success',
            title: 'Save training',
            text: 'Training saved',
          })
          totalCounters.value.pitches++
          totalCounters.value.pitchesVsBatters++
          // dataProcess.value.turn.turn++
          isMiss.value = false
          isChangeHitter.value = true
          // totalCounters.value = { pitches: 0, pitchesVsBatters: 0, balls: 0, strike: 0 }
          totalCounters.value.balls = 0
          totalCounters.value.strike = 0
          dataProcess.value.turn.ball = 0
          dataProcess.value.turn.strike = 0

          setStatusPlayers( playerCardPitcher.value.id, playerCardBatter.value.id, totalCounters.value.pitchesVsBatters)
          training.resetTurn()
        }
      })
    }

  } catch (error) {
    console.log('eer b', error);
  } finally {
    isLoading.status = !isLoading.status
    resetDataProcess()
  }
}

const validations = () => {
  let canCotinue = false

  if (dataProcess.value.pitch_mark !== '') {
      canCotinue = true
  } else {
    toast.fire({
      icon: 'warning',
      timer: 3500,
      title: 'Validation',
      text: 'To save result, You must select pitch area!!! ',
    })
    return;
  }

  return canCotinue
}

const endPractice = async () => {
  if (picked.value !== 'completed') {
    await router.push('/')
  } else {

    let dataEnd = {
      end_note: endNote.value,
      is_completed: true,
    }
    try {
      if (endNote.value === '') {
        toast.fire({
          icon: 'warning',
          title: 'End training',
          text: 'The practice note is required',
        })

      } else {
        isLoading.status = !isLoading.status;
        await axiosPut('training/' + dataProcess.value.practice_id, dataEnd).then(async (response) => {
          if (response) {
            isLoading.status = !isLoading.status;
            toast.fire({
              icon: 'success',
              title: 'End training',
              text: 'Finished session' + dataProcess.value.practice_id,
            })
            router.push({ name: 'dashboard'} )
          }

        })
      }
    } catch (error) {
      isLoading.status = !isLoading.status;
      toast.fire({
        icon: 'error',
        title: 'End training',
        text: 'Sorry it is not possible process this information in this moment',
      })
    }
  }

}

onMounted(() => {
  currentBatterId.value = localStorage.getItem('currentBatterId')
  currentPitcherID.value = localStorage.getItem('currentPitcherID')

  if (currentBatterId.value == null || currentPitcherID.value == null) {
    localStorage.setItem('currentBatterId', playerCardBatter.value.id)
    localStorage.setItem('currentPitcherID', playerCardPitcher.value.id)
    currentBatterId.value = playerCardBatter.value.id
    currentPitcherID.value = playerCardPitcher.value.id
  }

  totalCounters.value.pitchesVsBatters = getStatusPlayer(currentPitcherID.value, currentBatterId.value)

  if(training.trainingActive.sort != null){
    setEditPtcher()
  }
  compareListPlayers()
  totalCounters.value.pitches = livePitches

  let ball = localStorage.getItem('countBall')
  let strike = localStorage.getItem('countStrike')
  totalCounters.value.balls = ball ? parseInt(ball) : 0
  totalCounters.value.strike = strike ? parseInt(strike) : 0
  dataProcess.value.turn.ball = totalCounters.value.balls ?? 0
  dataProcess.value.turn.strike = totalCounters.value.strike ?? 0
})

onUnmounted(() => {
  localStorage.removeItem('currentBatterId')
  localStorage.removeItem('currentPitcherID')
})

const setEditPtcher = () => {
  dataEdit.value.pitch.position = training.trainingActive.pitchersEdit.pitch_side
  dataEdit.value.pitch.point = training.trainingActive.pitchersEdit.pitch_mark
  dataProcess.value.pitching.miles_per_hour = training.trainingActive.pitchersEdit.miles_per_hour
  dataProcess.value.pitching.type_throw = training.trainingActive.pitchersEdit.type_throw
  let pitch = training.trainingActive.pitchersEdit.type_throw
  let trajectory = training.trainingActive.pitchersEdit.trajectory
  let butonActiveTrajectory = document.querySelector("button[value='"+pitch+"'].pt")
  let butonActivePitch = document.querySelector("button[value='"+trajectory+"'].tj")
  if ( butonActiveTrajectory !== null) butonActiveTrajectory.classList.add('active-btn-contact')
  // butonActiveTrajectory.classList.add('active-btn-trajectory')
  if ( butonActivePitch !== null ) butonActivePitch.classList.add('active-btn-trajectory')
  // butonActivePitch.classList.add('active-btn-trajectory')
  dataProcess.value.type_of_hit = trajectory
}

const setEditBatters = () => {
  setTimeout(()=> {
    dataEdit.value.field.zone = training.trainingActive.battersEdit.field_direction
    dataEdit.value.field.point = training.trainingActive.battersEdit.field_mark
    dataProcess.value.batting.field_direction = dataEdit.value.field.zone
    dataProcess.value.batting.field_mark = dataEdit.value.field.point
    let pitch = training.trainingActive.bases
    let trajectory = training.trainingActive.battersEdit.quality_of_contact

    let totalBases = document.querySelector("button[value='"+pitch+"'].tb")
    let qualityContact = document.querySelector("button[value='"+trajectory+"'].qc")
    console.log('btn1', totalBases);
    console.log('btn2', qualityContact);
    if( totalBases !== null ) totalBases.classList.add('active-btn-contact')
    // totalBases.classList.add('active-btn-trajectory')
    if ( qualityContact !== null ) qualityContact.classList.add('active-btn-trajectory')
    // qualityContact.classList.add('active-btn-trajectory')
    dataProcess.value.batting.velocity = training.trainingActive.battersEdit.velocity
    dataProcess.value.batting.quality_of_contact = trajectory
    dataProcess.value.bases = pitch
  },300)
}

const resumenPractice = () => {
  let link = router.resolve({ name: 'training.statsLiveAB', params: { id: dataProcess.value.practice_id }})
  window.open(link.href)
}

const compareListPlayers = async () => {
  const listBatters = ref([]);
  const listPitchers = ref([])
  playerListBatters.forEach(object => {
    listBatters.value.push(object.id)
  });
  playerListPitchers.forEach(object => {
    listPitchers.value.push(object.id)
  });
  for (let index = 0; index < players.length; index++) {
    const element = players[index];
    if(!listBatters.value.includes(element.id)){
      hittersToAddList.value.push(element)
    }
    if(!listPitchers.value.includes(element.id)){
      pitchersToAddList.value.push(element)
    }
  }
}

const addPlayer = async (typeAdd) => {
  isLoading.status = !isLoading.status;
  try {
    if (dataPlayer.value == undefined || dataPlayer.value === '') {
      isLoading.status = !isLoading.status;
      toast.fire({
        icon: 'warning',
        title: 'Validation',
        text: "Select one player",
      })
      return;
    } else {
      const sortValue = ref([])
      if (training.trainingActive.lineup == null || training.trainingActive.lineup == undefined || training.trainingActive.lineup.length == 0) {
        training.trainingActive.lineup = []
        sortValue.value.push(sort.value + 1)
      } else {
        training.trainingActive.lineup.forEach(element => {
          sortValue.value.push(element.sort)
        })
      }

      let dataToAdd = {
        player: dataPlayer.value,
        pitching: typeAdd == 'pitcher' ? true : false,
        batting: typeAdd == 'batter' ? true : false,
        sort: Math.max(...sortValue.value) + 1
      }

      await axiosPost(`coach/lineup/${training.trainingActive.id}`, dataToAdd).then(async (response) => {
        if (response) {
          isLoading.status = !isLoading.status;
          toast.fire({
            icon: 'success',
            title: 'Save player',
            text: 'Player added',
          })
          training.trainingActive.lineup.push(response.data.data)
          if(typeAdd == 'batter'){
            training.trainingActive.players.batters.push(response.data.data.player)
          } else if (typeAdd == 'pitcher'){
            training.trainingActive.players.pitchers.push(response.data.data.player)
          }
          // playerList.push(response.data.data.player)
          isOpenAdd.value = false
          training.setCountBallsTraining(balls.value)
          router.go(router.currentRoute)
          // await router.replace("/track/batting")
        }
      })
    }
  } catch (error) {
    console.log(error);
    isLoading.status = !isLoading.status;
    toast.fire({
      icon: 'error',
      title: 'Not add player',
      text: 'Sorry it is not possible save the information in this moment',
    })
  }
}
</script>
<template>
  <Loader v-show="!isLoading.status"/>
  <Layout>

    <div class="grid justify-center mt-6">
      <div class="">
        <div class="flex flex-col md:flex-row gap-4 lg:gap-12 justify-evenly lg:justify-start h-auto md:h-[6em] bg-white rounded rounded-lg">
          <div class="flex">

            <LiveABLogoPractice class="" color="082247" height="75" width="75"/>

            <div class="lg:ml-5">
              <h3 class="text-fungo-red  text-[0.6em] lg:text-[1.2em] fungo-700  mt-4 lg:mt-2">Live AB Practice</h3>
              <h1 class="text-fungo-darkblue text-[1.2em] font-fungo-800 lg:text-[1.7em] lg:-mt-2">Pitcher</h1>
            </div>
          </div>
          <div
            class="border bg-fungo-gray7 grid grid-cols-1 lg:grid-cols-2 min-w-[150px] w-[150px] lg:w-[300px] lg:min-w-[300px] content-center justify-evenly">
            <div
              class="hidden ml-3  lg:inline-flex ">
              <img :src="playerCardPitcher.picture ? playerCardPitcher.picture : DefaultImg" :alt="playerCardPitcher.name.full" class="img-player">
            </div>
            <div
              class="flex flex-col justify-center gap-x-2 mx-auto text-fungo-darkblue font-fungo-400 text-[16px] lg:w-[195px] lg:-ml-10">
              <div class="font-fungo-800">{{ playerCardPitcher.name.full }}</div>
              <div>Jersey: <span class="text-fungo-red font-fungo-800">{{ playerCardPitcher.shirt_number }}</span></div>
            </div>
          </div>
          <div v-if="training.trainingActive.players['pitchers'].length > 1 "
               class="grid content-center">
            <div class="text-fungo-darkblue font-fungo-800 text-[16px] lg:w-[195px] px-3">Change player</div>
            <select class="text-[12px] w-[90%]" @change="changeDataPitchers($event)">
              <option v-for="player in playerListPitchers " :value="player.id" :selected="player.id === currentPitcherID">{{ player.name.full }}</option>
            </select>
          </div>
          <div class="grid content-center mr-10">
            <div @click="isOpenAdd2 = true" class="text-white bg-fungo-darkblue font-fungo-800 text-center
                py-2 rounded-2xl cursor-pointer text-[16px] max-w-[150px] px-4">
              Add pitcher
            </div>
          </div>
        </div>
      </div>
      <div class="w-full mt-5 order-1 xl:col-span-5">

        <div class="grid grid-cols-3 text-[16px] text-center gap-1">
          <div class="grid content-center  text-[16px] xl:text-[24px]  text-fungo-blue2">
            <button
              @click="resumenPractice"
            >
              Show Statistics!! >
            </button>
          </div>
          <div v-if="training.trainingActive.sort == null" class="grid content-center text-[16px] xl:text-[24px] text-fungo-blue2">
            <button @click="openModal">end practice ></button>
          </div>
        </div>

      </div>
    </div>
    <div class="md:grid justify-center">
      <div class="">
        <div class="flex flex-col md:flex-row gap-4 lg:gap-12 justify-evenly lg:justify-start h-auto md:h-[6em] bg-white rounded rounded-lg">
          <div class="flex">

            <LiveABLogoPractice class="" color="082247" height="75" width="75"/>

            <div class="lg:ml-5">
              <h3 class="text-fungo-red  text-[0.6em] lg:text-[1.2em] fungo-700  mt-4 lg:mt-2">Live AB Practice Session</h3>
              <h1 class="text-fungo-darkblue text-[1.2em] font-fungo-800 lg:text-[1.7em] lg:-mt-2">Hitter</h1>
            </div>
          </div>
          <div
            class="border bg-fungo-gray7 grid grid-cols-1 lg:grid-cols-2 min-w-[150px] w-[150px] lg:w-[300px] lg:min-w-[300px] content-center justify-evenly">
            <div
              class="hidden ml-3  lg:inline-flex ">
              <img :src="playerCardBatter.picture ? playerCardBatter.picture : DefaultImg" :alt="playerCardBatter" class="img-player">
            </div>
            <div
              class="flex flex-col justify-center gap-x-2 mx-auto text-fungo-darkblue font-fungo-400 text-[16px] lg:w-[195px] lg:-ml-10">
              <div class="font-fungo-800">{{ playerCardBatter.name.full }}</div>
              <div>Jersey: <span class="text-fungo-red font-fungo-800">{{ playerCardBatter.shirt_number }}</span></div>
            </div>
          </div>
          <div v-if="training.trainingActive.players['batters'].length > 1 "
               class="grid content-center">
            <div class="text-fungo-darkblue font-fungo-800 text-[16px] lg:w-[195px] px-3">Change player</div>
            <select class="text-[12px] w-[90%]" @change="changeDataBatters($event)">
              <option v-for="player in playerListBatters " :value="player.id" :selected="player.id === currentBatterId">{{ player.name.full }}</option>
            </select>
          </div>
          <div class="grid content-center mr-10">
            <div @click="isOpenAdd = true" class="text-white bg-fungo-darkblue font-fungo-800 text-center
                py-2 rounded-2xl cursor-pointer text-[16px] max-w-[150px] px-4">
              Add hitter
            </div>
          </div>
        </div>
      </div>
    </div>
    <div
      class="grid grid-flow-row grid-cols-1 lg:grid-cols-2 justify-center mt-2 xl:mt-5 gap-3 "
      :class="{'lg:grid-cols-2 xl:grid-cols-3' : !isMiss, 'xl:grid-cols-4' : isMiss, 'w-[80%] mx-auto' : dataEdit.pitch.point > 0 }"
    >
      <template v-if="!isMiss">
      <div class=" grid grid-flow-row md:grid-flow-col p-3">
        <div>
          <GridCatcher :key="change" v-model="gridPitcherData" class="h-fit w-fit"  :pitchMark="dataEdit.pitch"/>
        </div>

        <Counters v-if="training.trainingActive.sort == null" :totalCounters="totalCounters"/>

      </div>

      <PitchAndTrajectoryBtn @setTypeThrow="setTypeThrow" @setTrajectory="setTrajectory"/>

      <div class="  rounded p-2 gap-3 text-fungo-darkblue bg-white">
        <div class="md:h-[325px]">
          <VelocityInput :key="change" v-model="dataProcess.pitching.miles_per_hour"/>
          <div class="grid mt-5">
            <button
              class="rounded-xl rounded-l-3xl border bg-fungo-darkblue text-white mx-auto"
              @click="save">
              <div
                class="grid grid-cols-2 w-[200px]">
                <div class="m-1 p-1"><img
                  class="w-[20px] h-[20px] xl:w-[30px] xl:h-[30px]"
                  src="../../assets/img/login/assteslogin/ballbutton.png"></div>
                <div class="grid content-center items-center mr-8 text-[20px] -ml-12">{{ training.trainingActive.sort == null ? 'Save':'Change' }}</div>
              </div>
            </button>
          </div>
        </div>
      </div>
      </template>

      <template v-else>
        <div class="grid grid-flow-col p-3 col-span-2">
          <div>
            <GridField :key="change" v-model="gridBattingData" :pitchMark="dataEdit.field"/>
          </div>
          <Counters v-if="training.trainingActive.sort == null" :totalCounters="totalCounters"/>
        </div>

        <BasesAndQtyContact @setTotalBase="setTotalBase" @setQtyOfContact="setQtyOfContact"/>

        <div class="  rounded p-2 gap-3 text-fungo-darkblue bg-white">
          <div class="md:h-[325px]">
            <VelocityInput :key="change" v-model="dataProcess.batting.velocity"/>
            <div class="grid mt-5">
              <button
                class="rounded-xl rounded-l-3xl border bg-fungo-darkblue text-white mx-auto"
                @click="saveWithBattingData">
                <div
                  class="grid grid-cols-2 w-[200px]">
                  <div class="m-1 p-1"><img
                    class="w-[20px] h-[20px] xl:w-[30px] xl:h-[30px]"
                    src="../../assets/img/login/assteslogin/ballbutton.png"></div>
                  <div class="grid content-center items-center mr-8 text-[20px] -ml-12">{{ training.trainingActive.sort == null ? 'Save':'Change' }}</div>
                </div>
              </button>
            </div>
          </div>
        </div>

      </template>
    </div>

    <EndLiveABModal
      :isOpen="isOpen"
      v-model:picked="picked"
      v-model:endNote="endNote"
      @endPractice="endPractice"
    />

    <Modal
      modalTitle="Change hitter"
      :isOpen="isChangeHitter"
    >
      <template #content>
        <section class="space-y-5 grid grid-cols-2 md:grid-cols-3">
          <p>Please change hitter</p>
        </section>
      </template>

      <template #actions>
        <button
          type="button"
          class="inline-flex justify-center rounded-md bg-fungo-lightblue px-4 py-1"
          @click="isChangeHitter = false"
        >
          Close
        </button>
      </template>
    </Modal>

    <div v-if="isOpenAdd">
      <div class="fixed inset-0 z-50 flex justify-center items-center">
        <div class="flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 pb-4 drop-shadow-xl min-h-[30%] max-h-[35%]
          lg:min-h-[30%] lg:max-h-[35%] w-[85%] md:w-[100%] ml-3 lg:ml-0">
          <div>
            <div class="flex flex-row w-[100%] items-center mb-3 px-4 ">
              <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">Add player</h1>
              <div class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]" @click="isOpenAdd = false">
                <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
              </div>
            </div>
          </div>
          <div class="bg-fungo-gray2 mb-5 py-10 px-[3%]">
            <form action="" name="add-player" class="grid tems-center w-[95%] lg:w-[100%]">
              <select class="text-[16px] w-[100%] rounded-lg" v-model="dataPlayer">
                <option value="" disabled selected>Select one player</option>
                <option v-for="player in hittersToAddList " :value="player.id">{{ player.name.full }}</option>
              </select>
            </form>
          </div>
          <div class="flex flex-row justify-center">
            <div class="justify-center">
              <button @click="addPlayer('batter')" class="grid place-items-center grid-flow-col flex-row rounded-xl w-[200px] lg:w-[250px]
                  px-2 py-1 text-xl md:text-[12px] lg:text-[16px] bg-fungo-red text-white hover:bg-fungo-red-hover" type="submit">
                  Add
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
    </div>

    <div v-if="isOpenAdd2">
      <div class="fixed inset-0 z-50 flex justify-center items-center">
        <div class="flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 pb-4 drop-shadow-xl min-h-[30%] max-h-[35%]
          lg:min-h-[30%] lg:max-h-[35%] w-[85%] md:w-[100%] ml-3 lg:ml-0">
          <div>
            <div class="flex flex-row w-[100%] items-center mb-3 px-4 ">
              <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">Add player</h1>
              <div class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]" @click="isOpenAdd2 = false">
                <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
              </div>
            </div>
          </div>
          <div class="bg-fungo-gray2 mb-5 py-10 px-[3%]">
            <form action="" name="add-player" class="grid tems-center w-[95%] lg:w-[100%]">
              <select class="text-[16px] w-[100%] rounded-lg" v-model="dataPlayer">
                <option value="" disabled selected>Select one player</option>
                <option v-for="player in pitchersToAddList " :value="player.id">{{ player.name.full }}</option>
              </select>
            </form>
          </div>
          <div class="flex flex-row justify-center">
            <div class="justify-center">
              <button @click="addPlayer('pitcher')" class="grid place-items-center grid-flow-col flex-row rounded-xl w-[200px] lg:w-[250px]
                  px-2 py-1 text-xl md:text-[12px] lg:text-[16px] bg-fungo-red text-white hover:bg-fungo-red-hover" type="submit">
                  Add
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
    </div>

  </Layout>
</template>
<style scoped>
.ct * {
  border: 1px solid #1a73e8;
}

.img-player {
  height: 75px;
  width: 75px;
  border: 5px solid #d9d9d9;
  border-radius: 100px;
}
</style>
