<script setup xmlns="http://www.w3.org/1999/html">
import Layout from "../../layout/Layout.vue"
import {useTeamStore} from "../../store/team";
import {useTrainingStore} from "../../store/training";
import {onMounted, reactive, ref, computed, onUnmounted} from "vue";
import GridCatcher from "./GridCatcher.vue";
import BattingLogoPractice from "../../components/graphics/BattingLogoPractice.vue";
import GridField from "./GridField.vue";
import VelocityInput from "../../components/VelocityInput.vue";
import {toast} from "@/utils/AlertPlugin"
import {useAxiosAuth} from '@/composables/axios-auth.js'
import {Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot} from '@headlessui/vue'
import { usePlayerStore } from '@/store/players.js'
import Loader from "../../components/Loader.vue";
import {useRouter} from 'vue-router'
import DefaultImg from '@/assets/img/noavatar.png'
import { useUserStore } from "@/store/user";

const router = useRouter()
const {team, teams} = useTeamStore();
const {axiosPost, axiosPut} = useAxiosAuth()
const { userData } = useUserStore();
const { players } = usePlayerStore()
const isLoading = reactive({status: true})
const training = useTrainingStore();
const playerCard = ref(training.trainingActive.players[0]);
const playerList = [...training.trainingActive.players];
const playerToAddList = ref([]);
const balls = ref(0);
const sort = ref( training.trainingActive.sort ?? 0);
const change = ref(0);
const changePitcher = ref(0);
const endNote = ref('');
const picked = ref('progress');
const dataProcess = ref({
  pitch: '',
  field: '',
  contact: training.trainingActive.quality_of_contact ?? '',
  trajectory: training.trainingActive.type_of_hit ?? '',
  player: '',
  team: team.id,
  velocity: training.trainingActive.velocity ?? 0,
  practice: training.trainingActive.id
});
const dataPlayer = ref('');

const dataEdit = ref({
  field: {},
  pitch: {}
})

const currentPlayerID = ref('')

const isOpen = ref(false)
const isOpenAdd = ref(false)

onMounted(() => {
  currentPlayerID.value = localStorage.getItem('currentPlayerID')

  if (currentPlayerID.value == null) {
    localStorage.setItem('currentPlayerID', playerCard.value.id)
    currentPlayerID.value = playerCard.value.id
  }

  let player = training.getPlayerInfo(currentPlayerID.value)
  if(player){
    balls.value = player.balls ?? 0
  }else {
    balls.value = 0
  }

  playerCard.value = playerList.find((item) => item.id === currentPlayerID.value)

  setBallhit()
  setEditContactAndTrajectory()
  compareListPlayers()
})

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

const setBallhit = () => {
  dataEdit.value.field.zone = training.trainingActive.field_direction,
  dataEdit.value.field.point = training.trainingActive.field_mark
  dataEdit.value.pitch.position = training.trainingActive.pitch_location,
  dataEdit.value.pitch.point = training.trainingActive.pitch_mark
  dataProcess.value.pitch = dataEdit.value.pitch
  dataProcess.value.field = dataEdit.value.field
}

const setEditContactAndTrajectory = () => {
  const contact = training.trainingActive.quality_of_contact
  const trajectory = training.trainingActive.type_of_hit
  let butonActiveContact = document.querySelector("button[value='"+contact+"'].ct")
  let butonActiveTrajectory = document.querySelector("button[value='"+trajectory+"'].tj")
  if (butonActiveTrajectory !== null) butonActiveTrajectory.classList.add('active-btn-trajectory')
  if (butonActiveContact !== null) butonActiveContact.classList.add('active-btn-contact')
}


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
    training.addPLayerInfo(lastIdSelected, {'balls': balls.value,'name': playerCard.value.name.full})
  }else {
    if(balls.value > player.balls) {
      training.addPLayerInfo(lastIdSelected, {'balls': balls.value, 'name': playerCard.value.name.full})
    }
  }

  balls.value = 0
  const elementID = event.target.value;
  playerCard.value = playerList.find((item) => item.id === elementID)
  dataProcess.value.player = playerCard.value.id

  player = training.getPlayerInfo(playerCard.value.id)
  if(player){
    balls.value = player.balls
  }else {
    balls.value = 0
  }

  change.value += 1;
  changePitcher.value++
  localStorage.removeItem('pitch')
  dataProcess.value.velocity = 0
  localStorage.setItem('currentPlayerID', elementID)
  // sort.value += 1;
}


const setTrajectory = (event) => {
  clearStyle('active-btn-trajectory')
  if(dataProcess.value.trajectory == event.target.value){
    event.target.classList.remove('active-btn-trajectory')
    dataProcess.value.trajectory = 'TK'
  }else {
    event.target.classList.add('active-btn-trajectory')
    dataProcess.value.trajectory = event.target.value
  }
}

const setContact = (event) => {
  clearStyle('active-btn-contact')
  if(dataProcess.value.contact == event.target.value){
    event.target.classList.remove('active-btn-contact')
    dataProcess.value.contact = null
  }else {
    if (dataProcess.value.field.foul && event.target.value !== 'MF') {
      event.target.classList.remove('active-btn-contact')
      toast.fire({
        icon: 'warning',
        title: 'Validation',
        text: 'If field zone is marked in foul zone you only select Miss/Foul contact !!! ',
      })
      dataProcess.value.contact = null
    } else {
      event.target.classList.add('active-btn-contact')
      dataProcess.value.contact = event.target.value

    }
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
      responseTraining.value.lineup.forEach(element => {
        sortValue.value.push(element.sort)
      })

      let dataToAdd = {
        player: dataPlayer.value,
        pitching: false,
        batting: true,
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
          training.setCountBallsTraining(balls.value)
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

  let zoneCatcher = '-'

  if (dataProcess.value.pitch != "") {
    if(Object.keys(dataProcess.value.pitch).includes('strike')){
      zoneCatcher = dataProcess.value.pitch.strike.status ? 'S' : 'B'
    }
  }

  isLoading.status = !isLoading.status;
  let dataToSave = {
    practice_id: dataProcess.value.practice,
    team_id: dataProcess.value.team,
    batter_id: playerCard.value.id,
    is_contact: dataProcess.value.field == '' ? false : true,
    pitch_location: dataProcess.value.pitch.position ? dataProcess.value.pitch.position : '',
    pitch_mark: dataProcess.value.pitch.point ? dataProcess.value.pitch.point : null,
    quality_of_contact: dataProcess.value.contact !== '' ? dataProcess.value.contact : 'N',
    type_of_hit: dataProcess.value.contact === 'MF' ? 'SM' : dataProcess.value.trajectory == null || dataProcess.value.trajectory == '' ? 'TK' : dataProcess.value.trajectory,
    field_mark: dataProcess.value.field != '' ? dataProcess.value.field != null ? dataProcess.value.field.point: null : null,
    field_direction: dataProcess.value.field != '' ? dataProcess.value.field != null ? dataProcess.value.field.zone : '-' : '-',
    velocity: dataProcess.value.velocity,
    sort: sort.value,
    zone: zoneCatcher
  }

  try {

    if(training.trainingActive.sort != null){
      await axiosPut('result/batting/'+training.trainingActive.id, {
        'is_contact': dataToSave.is_contact,
        'pitch_location': dataToSave.pitch_location,
        'quality_of_contact' : dataToSave.quality_of_contact,
        'type_of_hit' : dataToSave.type_of_hit,
        'field_mark' : dataToSave.field_mark,
        'pitch_mark' : dataToSave.pitch_mark,
        'field_direction' : dataToSave.field_direction,
        'velocity' : dataToSave.velocity,
        'sort' : training.trainingActive.sort,
      }).then(async (response) => {
        if(response){
          isLoading.status = !isLoading.status;
          toast.fire({
            icon: 'success',
            title: 'Update training',
            text: 'Training updated',
          })
        }
        router.push({
          path: '/training/batting'
        })
      })
    }else {
      /* remove all validation */
      await axiosPost('result/batting', dataToSave).then(async (response) => {
        if (response) {
          isLoading.status = !isLoading.status;
          toast.fire({
            icon: 'success',
            title: 'Save training',
            text: 'Training saved',
          })
        }
        balls.value++
        sort.value += 1;
        training.addPLayerInfo(playerCard.value.id, {'balls': balls.value,'name': playerCard.value.name.full})
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
    zoneCatcher = '-'
  }
  localStorage.removeItem('pitch')
  change.value += 1;
  changePitcher.value++
  dataProcess.value.velocity = 0;
  dataProcess.value.pitch = '';
  dataProcess.value.field = '';
  dataProcess.value.contact = '';
  dataProcess.value.trajectory = '';
  dataProcess.value.zone = 'T'
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
            if (userData.type == "player") {
              training.addPLayerInfo(userData.id, {
                'balls': 0,
              })
              await router.push('/player-dashboard')
            } else {
              await router.push('/dashboard')
            }

            training.setCountBallsTraining(0);
          }
        })
      }
    } catch (error) {
      console.log(error);
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
  let link = router.resolve({name: 'training.stats', params: {'idPractice': training.trainingActive.id, 'type' : 'B' }})
  window.open(link.href)
}
onUnmounted(() => {
  localStorage.removeItem('currentPlayerID')
})
const disableTrajectory = computed(() => {
  return dataProcess.value.contact == 'MF' ? true : false
})
</script>
<template>
  <Loader v-show="!isLoading.status"/>
  <Layout>
    <div class="grid justify-center" :class="userData.type == 'player' ? 'pt-14' : ''">
      <div class="">
        <div class="flex flex-col md:flex-row gap-4 lg:gap-12 justify-evenly lg:justify-start h-auto md:h-[6em] bg-white rounded rounded-lg">
          <div class="flex">

            <BattingLogoPractice class="" color="082247" height="75" width="75"/>

            <div class="lg:ml-5">
              <h3 class="text-fungo-red  text-[0.6em] lg:text-[1.2em] fungo-700  mt-4 lg:mt-2">batting
                practice</h3>
              <h1 class="text-fungo-darkblue text-[1.2em] font-fungo-800 lg:text-[1.7em] lg:-mt-2">Hitter</h1>
            </div>
          </div>
          <div
            class="border bg-fungo-gray7 grid grid-cols-1 lg:grid-cols-2 min-w-[150px] w-[150px] lg:w-[300px] lg:min-w-[300px] content-center justify-evenly">
            <div
              class="hidden ml-3  lg:inline-flex ">
              <img :src="playerCard.picture ? playerCard.picture : DefaultImg" :alt="playerCard.name.full" class="img-player">
            </div>
            <div
              class="flex flex-col justify-center gap-x-2 mx-auto text-fungo-darkblue font-fungo-400 text-[16px] lg:w-[195px] lg:-ml-10">
              <div class="font-fungo-800">{{ playerCard.name.full }}</div>
              <div>Jersey: <span class="text-fungo-red font-fungo-800">{{ playerCard.shirt_number ?? playerCard.shirt_number }}</span></div>
            </div>
          </div>
          <template v-if="userData.type !== 'player'">
            <div v-if="training.trainingActive.players.length > 1 " class="grid grid-cols-2 content-center">
              <div>
                <div class="text-fungo-darkblue font-fungo-800 text-[16px] lg:w-[195px] px-3">Change player</div>
                <select class="text-[12px] w-[90%] rounded-lg" @change="changeData($event)">
                  <option v-for="player in playerList " :value="player.id" :selected="player.id == currentPlayerID">{{ player.name.full }}</option>
                </select>
              </div>
              <div class="grid content-end">
                <div @click="isOpenAdd = true" class="text-white bg-fungo-darkblue font-fungo-800 text-center
                  py-2 rounded-2xl cursor-pointer text-[16px] max-w-[150px]">
                  Add Player
                </div>
              </div>
            </div>
            <div v-else class="grid content-center mr-10">
              <div @click="isOpenAdd = true" class="text-white bg-fungo-darkblue font-fungo-800 text-center
                  py-2 rounded-2xl cursor-pointer text-[16px] max-w-[150px] px-4">
                Add Player
              </div>
            </div>
          </template>
        </div>
      </div>
      <div class="w-full mt-5 order-1 xl:col-span-5">

        <div v-if="training.trainingActive.quality_of_contact == null" class="grid grid-cols-3 text-[16px] text-center gap-1">
          <div class="grid grid-cols-2 bg-white balls-count relative  shadow-lg shadow-lg">
            <div class="grid items-center text-fungo-red font-fungo-300 text-[16px]">Balls
              :
            </div>
            <div
              class="ball-number grid items-center justify-items-end h-[90%]
              justify-evenly  text-fungo-darkblue font-fungo-800 text-[1.5em] ">
              {{ balls }}

            </div>
          </div>
          <div class="grid content-center text-[16px] xl:text-[24px] text-fungo-blue focus:accent-green-500">
            <button
              @click="openStatistics"
            >
              show statistics
              <svg class="inline fill-fungo-blue" height="20" viewBox="0 0 40 41" width="20"
                   xmlns="http://www.w3.org/2000/svg">
                <path clip-rule="evenodd" d="M8.64783 8.99553V31.9497H31.602V20.4726h3.2792v11.4771c0 1.8036-1.4756 3.2792-3.2792 3.2792H8.64783c-1.81995 0-3.27918-1.4756-3.27918-3.2792V8.99553c0-1.80355 1.45923-3.27918 3.27918-3.27918H20.1249v3.27918H8.64783zm14.75907 0V5.71635H34.884V17.1935h-3.2791v-5.8862L15.4877 27.4245l-2.3118-2.3118L29.293 8.99553h-5.8861z"
                      fill-rule="evenodd"/>
              </svg>
            </button>
          </div>
          <div class="grid content-center text-[16px] xl:text-[24px] text-fungo-blue">
            <button @click="openModal">end practice ></button>
          </div>
        </div>

      </div>
    </div>
    <div class="grid grid-flow-row md:grid-cols-2 xl:flex xl:flex-row	 justify-center mt-2 xl:mt-5 gap-3">
      <div class="xl:bg-white xl:flex-grow-0">
        <div class="h-fit w-fit  mx-auto ">
          <GridCatcher :key="changePitcher" v-model="dataProcess.pitch" :pitch-mark="dataEdit.pitch"/>
        </div>
      </div>
      <div class="w-full xl:w-[470px] bg-white my-auto xl:flex-grow-0">
        <GridField :key="change" v-model="dataProcess.field" :pitchMark="dataEdit.field"/>
      </div>
      <div class=" bg-white p-3 ">
        <div class="grid grid-cols-2 gap-0.5 justify-items-center text-[24px]">
          <div id="content-contact" class="grid gap-y-1.5">
            <div class="text-fungo-red text-center ">Contact</div>
            <button class="button-ct ct" value="W"
                    @click="setContact($event)">
              Weak
            </button>
            <button class="button-ct ct" value="A"
                    @click="setContact($event)">Avg
            </button>
            <button class="button-ct ct" value="H"
                    @click="setContact($event)">Hard
            </button>
            <button class="button-ct ct" value="MF"
                    @click="setContact($event)">Miss/F
            </button>
          </div>
          <div id="content-contact" class="grid gap-y-0.5">
            <div class="text-fungo-red text-center">Trajectory</div>
            <button :disabled="disableTrajectory" class="button-ct tj" value="GB" @click="setTrajectory($event)">GB</button>
            <button :disabled="disableTrajectory" class="button-ct tj" value="PF"
                    @click="setTrajectory($event)">PF
            </button>
            <button :disabled="disableTrajectory" class="button-ct tj" value="FB"
                    @click="setTrajectory($event)">FB
            </button>
            <button :disabled="disableTrajectory" class="button-ct tj" value="LD"
                    @click="setTrajectory($event)">LD
            </button>
          </div>
        </div>
      </div>
      <div class="  rounded p-2 gap-3 text-fungo-darkblue bg-white">
        <div class="md:h-[325px]">
          <div class="grid mb-1 text-center">Velocity:</div>
          <VelocityInput :key="changePitcher" v-model="dataProcess.velocity" />
          <div class="grid mt-5">
            <button
              class="rounded-xl rounded-l-3xl border bg-fungo-darkblue text-white mx-auto"
              @click="save">
              <div
                class="grid grid-cols-2 w-[200px]">
                <div class="m-1 p-1"><img
                  class="w-[20px] h-[20px] xl:w-[30px] xl:h-[30px]"
                  src="../../assets/img/login/assteslogin/ballbutton.png"></div>
                <div class="grid content-center items-center mr-8 text-[20px] -ml-12">{{training.trainingActive.quality_of_contact != null ? 'Change':'Save'}}</div>
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>

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
                <option v-for="player in playerToAddList " :value="player.id">{{ player.name.full }}</option>
              </select>
            </form>
          </div>
          <div class="flex flex-row justify-center">
            <div class="justify-center">
              <button @click="addPlayer()" class="grid place-items-center grid-flow-col flex-row rounded-xl w-[200px] lg:w-[250px]
                  px-2 py-1 text-xl md:text-[12px] lg:text-[16px] bg-fungo-red text-white hover:bg-fungo-red-hover" type="submit">
                  Add
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
    </div>

    <TransitionRoot :show="isOpen" appear as="template">
      <Dialog as="div" class="relative z-10" @close="closeModal">
        <TransitionChild
          as="template"
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-black bg-opacity-25"/>
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div
            class="flex min-h-full items-center justify-center p-4 text-center"
          >
            <TransitionChild
              as="template"
              enter="duration-300 ease-out"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel
                class="w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all"
              >
                <DialogTitle
                  as="h2"
                  class="text-2xl font-medium leading-6 text-fungo-red mb-3"
                >
                  End Batting Practice
                </DialogTitle>
                <div class="container">
                  <div class="grid grid-flow-row gap-3">
                    <div class=" p-5 border bg-fungo-gray4 grid grid-cols-3 place-items-center">
                      <div>
                        <svg class="w-[40px] h-[40px]" fill="none" viewBox="0 0 60 60"
                             xmlns="http://www.w3.org/2000/svg">
                          <path clip-rule="evenodd"
                                d="M5 30C5 16.2 16.175 5 29.975 5C43.8 5 55 16.2 55 30C55 43.8 43.8 55 29.975 55C16.175 55 5 43.8 5 30ZM10 30C10 41.05 18.95 50 30 50C41.05 50 50 41.05 50 30C50 18.95 41.05 10 30 10C18.95 10 10 18.95 10 30ZM38.75 27.5C40.825 27.5 42.5 25.825 42.5 23.75C42.5 21.675 40.825 20 38.75 20C36.675 20 35 21.675 35 23.75C35 25.825 36.675 27.5 38.75 27.5ZM25 23.75C25 25.825 23.325 27.5 21.25 27.5C19.175 27.5 17.5 25.825 17.5 23.75C17.5 21.675 19.175 20 21.25 20C23.325 20 25 21.675 25 23.75Z"
                                fill="#082247"
                                fill-rule="evenodd"/>
                          <path
                            d="M37 39C37 37.1435 36.2625 35.363 34.9497 34.0503C33.637 32.7375 31.8565 32 30 32C28.1435 32 26.363 32.7375 25.0503 34.0503C23.7375 35.363 23 37.1435 23 39"
                            stroke="#082247" stroke-linecap="round" stroke-width="4"/>
                        </svg>
                      </div>
                      <div class="grid grid-flow-row3 gap-1">
                        <div class="text-fungo-blue font-fungo-700 text-sm">Status</div>
                        <div class="text-fungo-darkblue font-fungo-700 "> In progress</div>
                        <div>
                          <progress
                            class="rounded overflow-hidden h-[7px] in-proress w-[70px]"
                            max="100"
                            value="50"
                          >
                          </progress>
                        </div>
                      </div>
                      <div><input v-model="picked" checked class="h-[30px] w-[30px]" name="end-session" type="radio"
                                  value="progress"></div>
                    </div>
                    <div class=" p-5 border bg-fungo-gray4 grid grid-cols-3 place-items-center">
                      <div>
                        <svg class="w-[40px] h-[40px]" fill="none" viewBox="0 0 60 60"
                             xmlns="http://www.w3.org/2000/svg">
                          <path clip-rule="evenodd"
                                d="M5 30C5 16.2 16.175 5 29.975 5C43.8 5 55 16.2 55 30C55 43.8 43.8 55 29.975 55C16.175 55 5 43.8 5 30ZM10 30C10 41.05 18.95 50 30 50C41.05 50 50 41.05 50 30C50 18.95 41.05 10 30 10C18.95 10 10 18.95 10 30ZM38.75 27.5C40.825 27.5 42.5 25.825 42.5 23.75C42.5 21.675 40.825 20 38.75 20C36.675 20 35 21.675 35 23.75C35 25.825 36.675 27.5 38.75 27.5ZM25 23.75C25 25.825 23.325 27.5 21.25 27.5C19.175 27.5 17.5 25.825 17.5 23.75C17.5 21.675 19.175 20 21.25 20C23.325 20 25 21.675 25 23.75Z"
                                fill="#082247"
                                fill-rule="evenodd"/>
                          <path
                            d="M23 33C23 34.8565 23.7375 36.637 25.0503 37.9497C26.363 39.2625 28.1435 40 30 40C31.8565 40 33.637 39.2625 34.9497 37.9497C36.2625 36.637 37 34.8565 37 33"
                            stroke="#082247" stroke-linecap="round" stroke-width="4"/>
                        </svg>
                      </div>
                      <div class="grid grid-flow-row3 gap-1">
                        <div class="text-fungo-blue font-fungo-700 text-sm">Status</div>
                        <div class="text-fungo-darkblue font-fungo-700 "> Completed</div>
                        <div>
                          <progress
                            class="rounded overflow-hidden h-[7px] completed w-[70px]"
                            max="100"
                            value="100"
                          >
                          </progress>
                        </div>
                      </div>
                      <div><input v-model="picked" class="h-[30px] w-[30px]" name="end-session" type="radio"
                                  value="completed"></div>
                    </div>
                  </div>
                  <div v-show="picked === 'completed'" class="grid grid-flow gap-1 mt-3">End
                    Note<textarea v-model="endNote"></textarea></div>
                  <div class="grid mt-5">
                    <button
                      class="rounded-xl rounded-l-3xl border bg-fungo-red text-white mx-auto"
                      @click="endPractice">
                      <div
                        class="grid grid-cols-2 w-[200px]">
                        <div class="m-1 p-1"><img
                          class="w-[20px] h-[20px] xl:w-[30px] xl:h-[30px]"
                          src="../../assets/img/login/assteslogin/ballbutton.png"></div>
                        <div class="grid content-center items-center mr-8 text-[16px] font-fungo-700 -ml-12">Finish
                          Training
                        </div>
                      </div>
                    </button>
                  </div>
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
.dash-table-container {
  position: relative;
  left: 0;
}

.box-input-col {
  @apply flex flex-col w-[100%];
}

.dash-body {
  @apply h-full  w-full  flex flex-col justify-between;
}

.capitalize {
  text-transform: capitalize;
}

.img-size {
  @apply w-[18em] h-[22.1em] ;
}

.ct * {
  border: 1px solid #1a73e8;
}

.img-player {
  height: 75px;
  width: 75px;
  border: 5px solid #d9d9d9;
  border-radius: 100px;
}

.active-btn-trajectory, .active-btn-contact {
  @apply bg-fungo-darkblue text-white;
}

.balls-count {
  border-left: solid red 2px;
  border-bottom-right-radius: 10px;
  border-top-right-radius: 10px;
}

.ball-number {
  @apply bg-fungo-gray7;
  border-bottom-right-radius: 10px;
  border-top-right-radius: 10px;
}

.button-ct {
  @apply border border-fungo-darkblue rounded h-[2em] w-[5em]
        disabled:cursor-not-allowed disabled:bg-fungo-gray2;
}

progress.in-proress::-webkit-progress-value {
  background: #FFB457;
}

progress.completed::-webkit-progress-value {
  background: #35A800;
}

progress::-webkit-progress-bar {
  background: #DBDFF1;
}
</style>
