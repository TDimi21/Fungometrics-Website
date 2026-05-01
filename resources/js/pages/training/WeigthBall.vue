<script setup xmlns="http://www.w3.org/1999/html">
import Layout from "../../layout/Layout.vue"
import WeightBallIconVue from "../../components/icons/WeightBallIcon.vue";
import {useTeamStore} from "../../store/team";
import {useTrainingStore} from "../../store/training";
import {reactive, ref, onMounted} from "vue";
import GridCatcher from "./GridCatcher.vue";
import TrainingModeIconVue from "../../components/icons/TrainingModeIcon.vue";
import GridField from "./GridField.vue";
import VelocityInput from "../../components/VelocityInput.vue";
import {toast} from "@/utils/AlertPlugin"
import {useAxiosAuth} from '@/composables/axios-auth.js'
import {Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot} from '@headlessui/vue'
import { usePlayerStore } from '@/store/players.js'
import Loader from "../../components/Loader.vue";
import router from "../../../router";
import DefaultImg from '@/assets/img/noavatar.png'

const {team, teams} = useTeamStore();
const {axiosPost, axiosPut, axiosGet} = useAxiosAuth()
const { players } = usePlayerStore()
const isLoading = reactive({status: true})
const training = useTrainingStore();
const playerCard = ref(training.trainingActive.players[0]);
const playerList = [...training.trainingActive.players];
const playerToAddList = ref([]);
const sort = ref(training.trainingActive.sort ?? 0);
const set = ref(training.trainingActive.set ?? 1);
const throws = ref(0);
const throwsForSet = ref(0);
const change = ref(0);
const endNote = ref('');
const picked = ref('progress');
const dataPlayer = ref('');
const dataProcess = ref({
  weight: training.trainingActive.weight ?? 1,
  player: playerCard.value.id,
  team: team.id,
  set: training.trainingActive.set ?? 1,
  sort: training.trainingActive.sort ?? playerCard.value.sort,
  velocity: training.trainingActive.velocity ?? 0,
  practice: training.trainingActive.id
});

const isOpen = ref(false)
const isOpenAdd = ref(false)

function closeModal() {
  isOpen.value = false
}

function openModal() {
  isOpen.value = true
}

const changeData = (event) => {
  const elementID = event.target.value;
  playerCard.value = playerList.find((item) => item.id === elementID)
  change.value += 1;
  localStorage.removeItem('pitch')
  dataProcess.value.velocity = 0
  dataProcess.value.player = playerCard.value.id
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
          training.setCountBallsTraining(set.value)
          let dataCount = {
            "balls": 0,
            "bxs": 0,
            "set": 1
          }
          training.countThrowArray[dataPlayer.value] = dataCount
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
  let dataToSave = {
    practice_id: dataProcess.value.practice,
    team_id: dataProcess.value.team,
    // user_id: dataProcess.value.player,
    user_id: playerCard.value.id,
    velocity: dataProcess.value.velocity,
    weight: dataProcess.value.weight,
    sort: training.trainingActive.sort,
    set: training.countThrowArray[playerCard.value.id].set
    // set: set.value,
  }
  try {
    if(dataProcess.value.weight != null && dataProcess.value.weight > 0){
      if(training.trainingActive.set != null){
        await axiosPut('result/weightball/' + training.trainingActive.id, {
          'weight' : dataToSave.weight,
          'velocity' : dataToSave.velocity,
          'sort' : dataToSave.sort,
          'set' : dataToSave.set,
        }).then(async (response) => {
        if (response) {
          toast.fire({
            icon: 'success',
            title: 'Update training',
            text: 'Training updated',
          })
          router.push({
            path: '/training/training-mode'
          })
        }
      })

      }else {
      await axiosPost('result/weightball', dataToSave).then(async (response) => {
      if (response) {
          isLoading.status = !isLoading.status;
          // throws.value++;
          // throwsForSet.value++;
          training.countThrowArray[playerCard.value.id].balls++
          training.countThrowArray[playerCard.value.id].bxs++
          dataProcess.value.weight = dataProcess.value.weight;
          sort.value += 1;
          toast.fire({
            icon: 'success',
            title: 'Save training',
            text: 'Training saved',
          })
        }
      })
    }
    }else {
      toast.fire({
        icon: 'error',
        title: 'Save training',
        text: '',
      });
    }

  } catch (error) {
    isLoading.status = !isLoading.status;
    toast.fire({
      icon: 'error',
      title: 'Not save training',
      text: 'Sorry it is not possible save the information in this moment',
    })
  }
  localStorage.removeItem('pitch')
  change.value += 1;
  dataProcess.value.velocity = 0;
}

const changeSet = () => {
  // set.value++;
  // throwsForSet.value = 0;
  training.countThrowArray[playerCard.value.id].set++
  training.countThrowArray[playerCard.value.id].bxs = 0
}

const endPractice = async () => {
  if (picked.value !== 'completed') {
    training.countThrowArray = {}
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
            training.countThrowArray = {}
            training.setCountBallsTraining(0);
            await router.push('/dashboard')
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

const goToStadistics = () => {
  // router.push({ name: 'training.statsMode', params: { 'idPractice': dataProcess.value.practice, 'mode': "WB" } })
  let link = router.resolve({ name: 'training.statsMode', params: { 'idPractice': dataProcess.value.practice, 'mode': "WB" } })
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

  const getInfoThrows = async ()=>{
    // // const { data } = await axiosGet('coach/teams')
    // console.log(training.trainingActive.id);
    // isLoading.status = !isLoading.status;
    // await axiosGet(`statistics/${training.trainingActive.id}/longtoss`).then((response)=> {
    //   if (response) {
    //     let infoObject = response.data.data.sets;
    //     training.countThrowArray = infoObject
    //   }
    // }).finally((_)=>{
    //   isLoading.status = !isLoading.status;
    // })
    isLoading.status = !isLoading.status;
    await axiosGet(`statistics/${training.trainingActive.id}/weightball`).then((response)=> {
      if (response) {
        let infoObject = response.data.data.sets;
        if(infoObject.length != training.trainingActive.players.length){
          training.trainingActive.players.forEach(item => {
            if (infoObject[item.id] == undefined) {
              let data = {
                "balls": 0,
                "bxs": 0,
                "set": 1
              }
              infoObject[item.id] = data
            }
          });
          training.countThrowArray = infoObject
        }else{
          training.countThrowArray = infoObject
        }
      }
    }).catch(() => {
      let playersArray = training.trainingActive.players
      playersArray.forEach(item => {
        let data = {
          "balls": 0,
          "bxs": 0,
          "set": 1
        }
        training.countThrowArray[item.id] = data
      })
    }).finally((_)=>{
      isLoading.status = !isLoading.status;
    })
  }

  const validationValueWeight = event => {
    if (event.target.value == 0 || event.target.value < 0) {
      toast.fire({
        icon: 'error',
        title: 'Weight',
        text: "Weight ball can't be less at 1",
      })
      dataProcess.value.weight = 1;
    }
  }

  onMounted(() => {
    getInfoThrows()
    compareListPlayers()
  })

</script>
<template>
  <Loader v-show="!isLoading.status"/>
  <Layout>
    <div class="grid justify-center mt-8">
      <div class="">
        <div class="flex flex-col md:flex-row gap-4 lg:gap-12 justify-evenly lg:justify-start h-auto md:h-[6em] bg-white rounded rounded-lg">
          <div class="flex">

            <TrainingModeIconVue class="w-32 mt-2" color="082247" height="75" width="75"/>

            <div class="lg:ml-5">
              <h3 class="text-fungo-red  text-[0.6em] lg:text-[1.2em] fungo-700  mt-4 lg:mt-2">Training mode</h3>
              <h1 class="text-fungo-darkblue text-[1.2em] font-fungo-800 lg:text-[1.7em] lg:-mt-2">Weighted Balls</h1>
            </div>
          </div>
          <div
            class="border bg-fungo-gray7 grid grid-cols-1 lg:grid-cols-2 min-w-[150px] w-[150px] lg:w-[300px] lg:min-w-[300px] content-center justify-evenly">
            <div
              class="hidden ml-3  lg:inline-flex ">
              <img :src="playerCard.picture ? playerCard.picture : DefaultImg" alt="" class="img-player">
            </div>
            <div
              class="flex flex-col justify-center gap-x-2 mx-auto text-fungo-darkblue font-fungo-400 text-[16px] lg:w-[195px] lg:-ml-10">
              <div class="font-fungo-800">{{ playerCard.name.full }}</div>
              <div>Jersey: <span class="text-fungo-red font-fungo-800">{{ playerCard.shirt_number }}</span></div>
            </div>
          </div>
          <!-- <div v-if="training.trainingActive.players.length > 1 "
               class="grid content-center">
            <div class="text-fungo-darkblue font-fungo-800 text-[16px] lg:w-[195px] px-3">Change player</div>
            <select class="text-[12px] w-[90%]" @change="changeData($event)">
              <option v-for="player in playerList " :value="player.id">{{ player.name.full }}</option>
            </select>
          </div> -->
          <div v-if="training.trainingActive.players.length > 1 " class="grid grid-cols-2 content-center">
            <div>
              <div class="text-fungo-darkblue font-fungo-800 text-[16px] lg:w-[195px] px-3">Change player</div>
              <select class="text-[12px] w-[90%] rounded-lg" @change="changeData($event)">
                <option v-for="player in playerList " :value="player.id">{{ player.name.full }}</option>
              </select>
            </div>
            <div class="grid content-end" v-if="training.trainingActive.players.length < players.length">
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
        </div>
      </div>
    </div>
    <div class="grid grid-flow-row md:grid-cols-2 xl:grid-cols-3 justify-center mt-2 xl:mt-5 gap-3">
      <div class="mx-10 px-10">
        <div class="flex justify-center">
          <div class="mt-5 order-1 xl:col-span-5">
            <div class="grid grid-cols-2 bg-white balls-count relative shadow-lg mb-8">
              <div class="flex items-center text-fungo-darkblue font-fungo-800 text-[16px] px-2">
                Set :
              </div>
              <div
                class="ball-number grid items-center justify-items-end h-[90%]
                justify-evenly  text-fungo-darkblue font-fungo-800 text-[1.5em]">
                {{ training.countThrowArray[playerCard.id].set }}
                <!-- {{ set }} -->
              </div>
            </div>
            <div class="text-[16px] xl:text-[24px] text-fungo-blue2 mb-4">
              <button @click="changeSet">new set +</button>
            </div>

            <div v-if="training.trainingActive.set == null" class="text-[16px] xl:text-[24px] text-fungo-blue2 mb-4">
              <button @click="goToStadistics">show statistics ></button>
            </div>
            <div v-if="training.trainingActive.set == null" class="text-[16px] xl:text-[24px] text-fungo-blue2 mb-4">
              <button @click="openModal">end weighted balls practice ></button>
            </div>
            <div v-if="training.trainingActive.set == null">
              <h2 class="text-fungo-darkblue font-fungo-800">Training Statistics</h2>
              <div class="flex w-full justify-between bg-white p-4 mb-2">
                <span>Throws in set</span>
                <span>{{ training.countThrowArray[playerCard.id].bxs }}</span>
                <!-- <span>{{ throwsForSet }}</span> -->
              </div>
              <div class="flex w-full justify-between bg-white p-4">
                <span>Total Throws</span>
                <span>{{ training.countThrowArray[playerCard.id].balls }}</span>
                <!-- <span>{{ throws }}</span> -->
              </div>
            </div>

          </div>
        </div>
      </div>
      <div class="bg-white p-3 ">
        <div class="grid grid-rows-1 grid-flow-col justify-start">
          <WeightBallIconVue></WeightBallIconVue>
          <h2 class="pl-3 text-[30px] font-fungo-800 text-fungo-red place-self-center">Weighted balls</h2>
        </div>
        <h3 class="text-fungo-red mx-8 font-bold">Select weight of ball</h3>
        <div class="mx-8 grid grid-rows-1 grid-flow-col justify-between rounded-md">
          <button class="rounded-md active px-4"  @click="dataProcess.weight--" :disabled="dataProcess.weight <= 1">-</button>
          <div class="text-fungo-darkblue">
            <input v-model="dataProcess.weight" @change="validationValueWeight" class="h-10 rounded appearance-none max-w-[100%]" type="number" autocomplete="off">
          </div>
          <button class="rounded-md active px-4"  @click="dataProcess.weight++">+</button>
        </div>
        <div class="flex justify-center my-8">
          <div class="text-center text-[30px] font-fungo-800 text-fungo-darkblue">
            <h2>Velocity</h2>
            <div class="md:h-[325px]">
          <VelocityInput label-velocity="" max-velocity="99999" :key="change" v-model="dataProcess.velocity"/>
          <div class="grid mt-5">
            <button
              class="rounded-xl rounded-l-3xl border bg-fungo-darkblue text-white mx-auto"
              @click="save">
              <div
                class="grid grid-cols-2 w-[200px]">
                <div class="m-1 p-1"><img
                  class="w-[20px] h-[20px] xl:w-[30px] xl:h-[30px]"
                  src="../../assets/img/login/assteslogin/ballbutton.png"></div>
                <div class="grid content-center items-center mr-8 text-[20px] -ml-12">{{ training.trainingActive.set == null ? 'Save': 'Change'}}</div>
              </div>
            </button>
          </div>
        </div>
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
  @apply border border-fungo-darkblue rounded h-[2em] w-[5em]  focus:bg-fungo-darkblue focus:text-white;
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

.active {
  @apply bg-fungo-darkblue text-white
}

input[type=number]::-webkit-inner-spin-button,
  input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }

  input[type=number] { -moz-appearance:textfield; }
</style>
