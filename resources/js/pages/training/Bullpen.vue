<script setup xmlns="http://www.w3.org/1999/html">
import Layout from "../../layout/Layout.vue"
import {useTeamStore} from "../../store/team";
import {useTrainingStore} from "../../store/training";
import {onUnmounted, reactive, ref, onMounted} from "vue";
import GridCatcher from "./GridCatcher.vue";
import BattingLogoPractice from "../../components/graphics/BattingLogoPractice.vue";
import VelocityInput from "../../components/VelocityInput.vue";
import {toast} from "@/utils/AlertPlugin"
import {useAxiosAuth} from '@/composables/axios-auth.js'
import {Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot} from '@headlessui/vue'
import { usePlayerStore } from '@/store/players.js'
import Loader from "../../components/Loader.vue";
import router from "../../../router";
import { useUserStore } from "@/store/user";
import DefaultImg from '@/assets/img/noavatar.png'

const {team, teams} = useTeamStore();
const {axiosPost, axiosPut} = useAxiosAuth()
const { userData } = useUserStore();
const { players } = usePlayerStore()
const isLoading = reactive({status: true})
const training = useTrainingStore();
const playerCard = ref(training.trainingActive.players[0]);
const playerList = [...training.trainingActive.players];
const playerToAddList = ref([]);
const pitches = ref(0);
const sort = ref(training.trainingActive.sort ?? 0);
const change = ref(0);
const endNote = ref('');
const picked = ref('progress');
const dataProcess = ref({
  pitch: '',
  pitchType: training.trainingActive.type_throw ?? '-',
  trajectory: training.trainingActive.trajectory ?? 'TK',
  player: playerCard.value.id,
  team: team.id,
  mph: training.trainingActive.miles_per_hour ?? 0,
  practice: training.trainingActive.id,
});
const dataPlayer = ref('');

const dataEdit = ref({
  pitch: {
    strike: {}
  }
})
const currentPlayerID = ref('')

const isOpen = ref(false)
const isOpenAdd = ref(false)
const togglePitch = ref(false)
const toggleTrajectory = ref(false)

function closeModal() {
  isOpen.value = false
}

function openModal() {
  isOpen.value = true
}

const changeData = (event) => {
  let lastIdSelected = playerCard.value.id
  let player = training.getPlayerInfo(lastIdSelected)
  if(!player){
    training.addPLayerInfo(lastIdSelected, {'pitch': pitches.value,'name': playerCard.value.name.full})
  }else {
    if(pitches.value > player.pitch) {
      training.addPLayerInfo(lastIdSelected, {'pitch': pitches.value, 'name': playerCard.value.name.full})
    }
  }

  pitches.value = 0
  const elementID = event.target.value;
  playerCard.value = playerList.find((item) => item.id === elementID)
  dataProcess.value.player = playerCard.value.id
  player = training.getPlayerInfo(playerCard.value.id)
  if(player){
    pitches.value = player.pitch
  }else {
    pitches.value = 0
  }
  // console.log("Da");
  if(player){
    pitches.value = player.pitch
  }else {
    pitches.value = 0
  }
  // if(player){
  //   console.log("D3");
  //   pitches.value = player.pitch
  // }else {
  //   pitches.value = training.countBalls != 0 ? training.countBalls : 0;
  //   // pitches.value = 0
  // }
  change.value += 1;
  localStorage.removeItem('pitch')
  dataProcess.value.velocity = 0
  localStorage.setItem('currentPlayerID', elementID)
  // sort.value += 1;
}


const setPitch = (event) => {
  clearStyle('active-btn-contact')

  if ( dataProcess.value.pitchType == event.target.value ) {
    // clearStyle('active-btn-contact')
    event.target.classList.remove('active-btn-contact')
    dataProcess.value.pitchType = null
  } else {
    event.target.classList.add('active-btn-contact')
    dataProcess.value.pitchType = event.target.value
  }
}
const setTrajectory = (event) => {
  clearStyle('active-btn-trajectory')
  // toggleTrajectory.value = !toggleTrajectory.value
  if ( dataProcess.value.trajectory == event.target.value ) {
    // clearStyle('active-btn-trajectory')
    event.target.classList.remove('active-btn-trajectory')
    dataProcess.value.trajectory = 'TK'
  } else {
    event.target.classList.add('active-btn-trajectory')
    dataProcess.value.trajectory = event.target.value
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

const addPlayer = async () => {
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
        pitching: true,
        batting: false,
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
          training.trainingActive.players.push(response.data.data.player)
          // playerList.push(response.data.data.player)
          isOpenAdd.value = false
          training.setCountBallsTraining(pitches.value)
          router.go(router.currentRoute)
          // await router.replace("/track/batting")
        }
      })
    }
  } catch (error) {
    isLoading.status = !isLoading.status;
    toast.fire({
      icon: 'error',
      title: 'Not add player',
      text: 'Sorry it is not possible save the information in this moment',
    })
  }
}

const save = async () => {
  isLoading.status = !isLoading.status;

  if (dataProcess.value.pitch === '') {
    toast.fire({
      icon: 'warning',
      title: 'Validation',
      text: 'Please, set catcher location',
    })
    isLoading.status = !isLoading.status;
    return
  }

  if (dataProcess.value.pitchType === '' || dataProcess.value.pitchType === '-') {
    toast.fire({
      icon: 'warning',
      title: 'Validation',
      text: 'Please, set pitch type',
    })
    isLoading.status = !isLoading.status;
    return
  }

  let zoneCatcher = 'T'

  if (dataProcess.value.pitch != "") {
    zoneCatcher = dataProcess.value.pitch.strike.status ? 'S' : 'B'
  }

  let isStrike = false;
  if (dataProcess.value.pitch.strike.status && dataProcess.value.trajectory === 'SM') {
    isStrike = !isStrike;
  }
  let dataToSave = {
    practice_id: dataProcess.value.practice,
    type_throw: dataProcess.value.pitchType,
    team_id: dataProcess.value.team,
    pitcher_id: playerCard.value.id,
    pitch_side: dataProcess.value.pitch.position ? dataProcess.value.pitch.position : '',
    pitch_mark: dataProcess.value.pitch.point ? dataProcess.value.pitch.point : null,
    trajectory: dataProcess.value.trajectory != null ? dataProcess.value.trajectory : 'TK',
    miles_per_hour: dataProcess.value.mph,
    is_strike: isStrike,
    sort: sort.value,
    zone: zoneCatcher
  }

  try {
    if(training.trainingActive.sort != null){
      await axiosPut('result/bullpen/'+ training.trainingActive.id, {
        'pitch_side' : dataToSave.pitch_side,
        'pitch_mark' : dataToSave.pitch_mark,
        'is_strike' : dataToSave.is_strike,
        'miles_per_hour' : dataToSave.miles_per_hour,
        'type_throw' : dataToSave.type_throw,
        'trajectory' : dataToSave.trajectory,
        'is_in_match' : dataToSave.is_in_match,
        'sort' : training.trainingActive.sort,
      }).then(async (response) => {
        if (response) {
          isLoading.status = !isLoading.status;
          toast.fire({
            icon: 'success',
            title: 'Update training',
            text: 'Training updated',
          })

          router.push({
            path: '/training/bullpen'
          })
        }
      })
    }else{
      await axiosPost('result/bullpen', dataToSave).then(async (response) => {
        if (response) {
          isLoading.status = !isLoading.status;
          toast.fire({
            icon: 'success',
            title: 'Save training',
            text: 'Training saved',
          })
        }
        pitches.value++
        sort.value++
        training.addPLayerInfo(playerCard.value.id, {'pitch': pitches.value, 'name': playerCard.value.name.full})
      })
    }
  } catch (error) {
    isLoading.status = !isLoading.status;
    toast.fire({
      icon: 'error',
      title: 'Not save training',
      text: 'Sorry it is not possible save the information in this moment',
    })
  } finally {
    zoneCatcher = 'T'
  }
  localStorage.removeItem('pitch')
  change.value += 1;
  dataProcess.value.mph = 0;
  dataProcess.value.pitch = '';
  dataProcess.value.pitchType = '';
  dataProcess.value.trajectory = training.trainingActive.trajectory ?? 'TK';
  clearStyle('active-btn-trajectory');
  clearStyle('active-btn-contact');
}

const endPractice = async () => {
  if (picked.value !== 'completed') {
    if (userData.type == "player") {
      training.addPLayerInfo(userData.id, {
        'balls': 0,
      })
    }
    training.setCountBallsTraining(0);
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
        await axiosPut('training/' + dataProcess.value.practice, dataEnd).then(async (response) => {
          if (response) {
            isLoading.status = !isLoading.status;
            toast.fire({
              icon: 'success',
              title: 'End training',
              text: 'Finished session' + dataProcess.value.practice,
            })
            let name_route = ''
            if (userData.type == "player") {
              training.addPLayerInfo(userData.id, {
                'balls': 0,
              })
              name_route = '/player-dashboard'
            } else {
              name_route = '/dashboard'
            }
            await router.push(name_route)
          }
          training.setCountBallsTraining(0);
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
const openStatistics = () => {
  let link = router.resolve({ name: 'training.stats', params: { 'idPractice': training.trainingActive.id, 'type': 'P' } })
  window.open(link.href)
}

const compareListPlayers = async () => {
  const list = ref([]);
  playerList.forEach(object => {
    list.value.push(object.id)
  });
  for (let index = 0; index < players.length; index++) {
    const element = players[index];
    if(!list.value.includes(element.id)){
      playerToAddList.value.push(element)
    }
  }
}
onUnmounted(() => {
  training.trainingActive = null
  localStorage.removeItem('currentPlayerID')
})

onMounted(()=>{
  currentPlayerID.value = localStorage.getItem('currentPlayerID')
  
  if (currentPlayerID.value == null) {
    localStorage.setItem('currentPlayerID', playerCard.value.id)
    currentPlayerID.value = playerCard.value.id
  }
  let player = training.getPlayerInfo(currentPlayerID.value)
  if(player){
    pitches.value = player.pitch ?? 0
  }else {
    pitches.value = training.countBalls != 0 ? training.countBalls : 0;
    // pitches.value = 0
  }
  playerCard.value = playerList.find((item) => item.id === currentPlayerID.value)
  setEditData()
  compareListPlayers()
})

const setEditData = () => {
  dataEdit.value.pitch.strike.status = training.trainingActive.is_strike
  dataEdit.value.pitch.position = training.trainingActive.pitch_side
  dataEdit.value.pitch.point = training.trainingActive.pitch_mark
  let pitch = training.trainingActive.type_throw
  let trajectory = training.trainingActive.trajectory
  let butonActiveTrajectory = document.querySelector("button[value='"+pitch+"'].pt")
  let butonActivePitch = document.querySelector("button[value='"+trajectory+"'].tj")
  if (butonActivePitch !== null) butonActivePitch.classList.add('active-btn-trajectory')
  if (butonActiveTrajectory !== null) butonActiveTrajectory.classList.add('active-btn-contact')
}

</script>
<template>
  <Loader v-show="!isLoading.status"/>
  <Layout>
    <div class="min-h-screen px-3 pb-8" :class="userData.type == 'player' ? 'pt-16' : 'pt-4'">

      <!-- ── Header Card ── -->
      <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4 mb-4 flex flex-col md:flex-row md:items-center gap-4">

        <!-- Logo + Title -->
        <div class="flex items-center gap-3 shrink-0">
          <BattingLogoPractice color="ffffff" height="52" width="52"/>
          <div>
            <p class="text-red-400 text-xs font-semibold uppercase tracking-widest">Bullpen Practice</p>
            <h1 class="text-white text-2xl font-bold leading-tight">Pitcher</h1>
          </div>
        </div>

        <!-- Player Card -->
        <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-2 min-w-[200px]">
          <img :src="playerCard.picture ? playerCard.picture : DefaultImg" alt="" class="w-12 h-12 rounded-full border-2 border-white/20 object-cover"/>
          <div>
            <div class="text-white font-bold text-sm">{{ playerCard.name.full }}</div>
            <div class="text-white/50 text-xs">Jersey: <span class="text-red-400 font-bold">{{ playerCard.shirt_number }}</span></div>
          </div>
        </div>

        <!-- Change Player + Add Player (coach only) -->
        <template v-if="userData.type !== 'player'">
          <div v-if="training.trainingActive.players.length > 1" class="flex items-center gap-3 flex-wrap">
            <div>
              <p class="text-white/50 text-xs mb-1">Change player</p>
              <select
                class="bg-white/10 border border-white/20 text-white rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-red-400/60"
                @change="changeData($event)">
                <option v-for="player in playerList" :value="player.id" :selected="player.id == currentPlayerID"
                  class="bg-[#060b14] text-white">{{ player.name.full }}</option>
              </select>
            </div>
            <button @click="isOpenAdd = true"
              class="mt-4 px-4 py-1.5 rounded-xl border border-red-400/60 bg-red-500/20 text-red-300 text-sm font-semibold hover:bg-red-500/30 transition-colors">
              + Add Player
            </button>
          </div>
          <div v-else>
            <button @click="isOpenAdd = true"
              class="px-4 py-1.5 rounded-xl border border-red-400/60 bg-red-500/20 text-red-300 text-sm font-semibold hover:bg-red-500/30 transition-colors">
              + Add Player
            </button>
          </div>
        </template>

        <!-- Stats row (right-aligned) -->
        <div v-if="training.trainingActive.sort == null" class="md:ml-auto flex items-center gap-3 flex-wrap">
          <!-- Pitch Count -->
          <div class="rounded-xl border border-white/10 bg-white/5 px-5 py-2 text-center min-w-[100px]">
            <div class="text-white/50 text-xs uppercase tracking-wide">Pitches</div>
            <div class="text-white text-3xl font-bold">{{ pitches }}</div>
          </div>
          <!-- Show Stats -->
          <button @click="openStatistics"
            class="flex items-center gap-2 px-4 py-2 rounded-xl border border-white/10 bg-white/5 text-white/70 text-sm hover:bg-white/10 transition-colors">
            <svg height="16" viewBox="0 0 40 41" width="16" xmlns="http://www.w3.org/2000/svg" class="fill-white/70">
              <path clip-rule="evenodd" d="M8.64783 8.99553V31.9497H31.602V20.4726h3.2792v11.4771c0 1.8036-1.4756 3.2792-3.2792 3.2792H8.64783c-1.81995 0-3.27918-1.4756-3.27918-3.2792V8.99553c0-1.80355 1.45923-3.27918 3.27918-3.27918H20.1249v3.27918H8.64783zm14.75907 0V5.71635H34.884V17.1935h-3.2791v-5.8862L15.4877 27.4245l-2.3118-2.3118L29.293 8.99553h-5.8861z" fill-rule="evenodd"/>
            </svg>
            Statistics
          </button>
          <!-- End Practice -->
          <button @click="openModal"
            class="flex items-center gap-2 px-4 py-2 rounded-xl border border-red-400/60 bg-red-500/20 text-red-300 text-sm font-semibold hover:bg-red-500/30 transition-colors">
            End Practice
          </button>
        </div>
      </div>

      <!-- ── Main Grid ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

        <!-- GridCatcher -->
        <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-3 flex items-center justify-center">
          <div class="h-fit w-fit mx-auto">
            <GridCatcher :key="change" v-model="dataProcess.pitch" :pitchMark="dataEdit.pitch"/>
          </div>
        </div>

        <!-- Pitch + Trajectory Buttons -->
        <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-5 flex flex-col justify-center">
          <div class="grid grid-cols-2 gap-4">
            <!-- Pitch Type -->
            <div class="flex flex-col gap-2">
              <div class="text-red-400 text-xs font-bold uppercase tracking-widest text-center mb-1">Pitch Type</div>
              <button class="pitch-btn pt" value="FB" @click="setPitch($event)">FB</button>
              <button class="pitch-btn pt" value="CH" @click="setPitch($event)">CH</button>
              <button class="pitch-btn pt" value="SL" @click="setPitch($event)">SL</button>
              <button class="pitch-btn pt" value="CB" @click="setPitch($event)">CV</button>
              <button class="pitch-btn pt" value="OTHER" @click="setPitch($event)">Other</button>
            </div>
            <!-- Trajectory -->
            <div class="flex flex-col gap-2">
              <div class="text-red-400 text-xs font-bold uppercase tracking-widest text-center mb-1">Trajectory</div>
              <button class="pitch-btn tj" value="GB" @click="setTrajectory($event)">GB</button>
              <button class="pitch-btn tj" value="LD" @click="setTrajectory($event)">LD</button>
              <button class="pitch-btn tj" value="FB" @click="setTrajectory($event)">Fly</button>
              <button class="pitch-btn tj" value="F"  @click="setTrajectory($event)">Foul</button>
              <button class="pitch-btn tj" value="SM" @click="setTrajectory($event)">S/M</button>
            </div>
          </div>
        </div>

        <!-- Velocity + Save -->
        <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-5 flex flex-col gap-4">
          <div class="text-white/70 text-sm text-center font-medium">Miles per hour</div>
          <VelocityInput :key="change" v-model="dataProcess.mph"/>
          <div class="flex justify-center mt-2">
            <button @click="save"
              class="flex items-center gap-3 px-8 py-3 rounded-2xl bg-[#C00000] hover:bg-red-700 text-white font-bold text-lg transition-colors shadow-lg shadow-red-900/30">
              <img class="w-6 h-6" src="../../assets/img/login/assteslogin/ballbutton.png"/>
              {{ training.trainingActive.sort == null ? 'Save' : 'Change' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Add Player Modal ── -->
    <div v-if="isOpenAdd">
      <div class="fixed inset-0 z-50 flex justify-center items-center px-4">
        <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#060b14]/95 backdrop-blur-xl shadow-2xl p-6">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-white text-xl font-bold">Add Player</h2>
            <button @click="isOpenAdd = false" class="text-white/40 hover:text-white transition-colors text-2xl leading-none">&times;</button>
          </div>
          <div class="mb-5">
            <select class="w-full bg-white/10 border border-white/20 text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-red-400/60"
              v-model="dataPlayer">
              <option value="" disabled selected class="bg-[#060b14]">Select one player</option>
              <option v-for="player in playerToAddList" :value="player.id" class="bg-[#060b14] text-white">{{ player.name.full }}</option>
            </select>
          </div>
          <div class="flex justify-center">
            <button @click="addPlayer()"
              class="px-8 py-2.5 rounded-xl bg-[#C00000] hover:bg-red-700 text-white font-bold transition-colors">
              Add
            </button>
          </div>
        </div>
      </div>
      <div class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm"></div>
    </div>

    <!-- ── End Practice Modal ── -->
    <TransitionRoot :show="isOpen" appear as="template">
      <Dialog as="div" class="relative z-10" @close="closeModal">
        <TransitionChild
          as="template"
          enter="duration-300 ease-out" enter-from="opacity-0" enter-to="opacity-100"
          leave="duration-200 ease-in"  leave-from="opacity-100" leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"/>
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4">
            <TransitionChild
              as="template"
              enter="duration-300 ease-out" enter-from="opacity-0 scale-95" enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"  leave-from="opacity-100 scale-100" leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="w-full max-w-md rounded-2xl border border-white/10 bg-[#060b14]/95 backdrop-blur-xl p-6 shadow-2xl">
                <DialogTitle as="h2" class="text-2xl font-bold text-white mb-4">
                  End Practice
                </DialogTitle>

                <div class="flex flex-col gap-3">
                  <!-- In Progress option -->
                  <label class="flex items-center gap-4 p-4 rounded-xl border border-white/10 bg-white/5 cursor-pointer hover:bg-white/10 transition-colors"
                    :class="picked === 'progress' ? 'border-yellow-400/40 bg-yellow-500/10' : ''">
                    <svg class="w-9 h-9 shrink-0" fill="none" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                      <path clip-rule="evenodd" d="M5 30C5 16.2 16.175 5 29.975 5C43.8 5 55 16.2 55 30C55 43.8 43.8 55 29.975 55C16.175 55 5 43.8 5 30ZM10 30C10 41.05 18.95 50 30 50C41.05 50 50 41.05 50 30C50 18.95 41.05 10 30 10C18.95 10 10 18.95 10 30ZM38.75 27.5C40.825 27.5 42.5 25.825 42.5 23.75C42.5 21.675 40.825 20 38.75 20C36.675 20 35 21.675 35 23.75C35 25.825 36.675 27.5 38.75 27.5ZM25 23.75C25 25.825 23.325 27.5 21.25 27.5C19.175 27.5 17.5 25.825 17.5 23.75C17.5 21.675 19.175 20 21.25 20C23.325 20 25 21.675 25 23.75Z" fill="#FFB457" fill-rule="evenodd"/>
                      <path d="M37 39C37 37.1435 36.2625 35.363 34.9497 34.0503C33.637 32.7375 31.8565 32 30 32C28.1435 32 26.363 32.7375 25.0503 34.0503C23.7375 35.363 23 37.1435 23 39" stroke="#FFB457" stroke-linecap="round" stroke-width="4"/>
                    </svg>
                    <div class="flex-1">
                      <div class="text-white/50 text-xs mb-0.5">Status</div>
                      <div class="text-white font-semibold">In progress</div>
                      <progress class="rounded overflow-hidden h-[5px] in-proress w-full mt-1" max="100" value="50"></progress>
                    </div>
                    <input v-model="picked" checked class="accent-yellow-400 w-5 h-5" name="end-session" type="radio" value="progress"/>
                  </label>

                  <!-- Completed option -->
                  <label class="flex items-center gap-4 p-4 rounded-xl border border-white/10 bg-white/5 cursor-pointer hover:bg-white/10 transition-colors"
                    :class="picked === 'completed' ? 'border-green-400/40 bg-green-500/10' : ''">
                    <svg class="w-9 h-9 shrink-0" fill="none" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                      <path clip-rule="evenodd" d="M5 30C5 16.2 16.175 5 29.975 5C43.8 5 55 16.2 55 30C55 43.8 43.8 55 29.975 55C16.175 55 5 43.8 5 30ZM10 30C10 41.05 18.95 50 30 50C41.05 50 50 41.05 50 30C50 18.95 41.05 10 30 10C18.95 10 10 18.95 10 30ZM38.75 27.5C40.825 27.5 42.5 25.825 42.5 23.75C42.5 21.675 40.825 20 38.75 20C36.675 20 35 21.675 35 23.75C35 25.825 36.675 27.5 38.75 27.5ZM25 23.75C25 25.825 23.325 27.5 21.25 27.5C19.175 27.5 17.5 25.825 17.5 23.75C17.5 21.675 19.175 20 21.25 20C23.325 20 25 21.675 25 23.75Z" fill="#35A800" fill-rule="evenodd"/>
                      <path d="M23 33C23 34.8565 23.7375 36.637 25.0503 37.9497C26.363 39.2625 28.1435 40 30 40C31.8565 40 33.637 39.2625 34.9497 37.9497C36.2625 36.637 37 34.8565 37 33" stroke="#35A800" stroke-linecap="round" stroke-width="4"/>
                    </svg>
                    <div class="flex-1">
                      <div class="text-white/50 text-xs mb-0.5">Status</div>
                      <div class="text-white font-semibold">Completed</div>
                      <progress class="rounded overflow-hidden h-[5px] completed w-full mt-1" max="100" value="100"></progress>
                    </div>
                    <input v-model="picked" class="accent-green-400 w-5 h-5" name="end-session" type="radio" value="completed"/>
                  </label>
                </div>

                <!-- End Note -->
                <div v-show="picked === 'completed'" class="mt-4">
                  <label class="text-white/60 text-xs uppercase tracking-wide block mb-1">End Note</label>
                  <textarea v-model="endNote" rows="3"
                    class="w-full bg-white/10 border border-white/20 text-white rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:border-red-400/60 placeholder-white/30"
                    placeholder="Add a note about this session…"></textarea>
                </div>

                <div class="flex justify-center mt-5">
                  <button @click="endPractice"
                    class="flex items-center gap-3 px-8 py-3 rounded-2xl bg-[#C00000] hover:bg-red-700 text-white font-bold text-base transition-colors shadow-lg shadow-red-900/30">
                    <img class="w-5 h-5" src="../../assets/img/login/assteslogin/ballbutton.png"/>
                    Finish Training
                  </button>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </Layout>
</template>
<style scoped>
.pitch-btn {
  @apply w-full py-2 rounded-xl border border-white/20 bg-white/5 text-white text-base transition-colors cursor-pointer;
  font-weight: 500;
}

.active-btn-contact,
.active-btn-trajectory {
  @apply bg-[#C00000] border-red-400/60 text-white shadow-md shadow-red-900/40;
}

progress.in-proress::-webkit-progress-value {
  background: #FFB457;
}

progress.completed::-webkit-progress-value {
  background: #35A800;
}

progress::-webkit-progress-bar {
  background: rgba(255,255,255,0.1);
}
</style>
