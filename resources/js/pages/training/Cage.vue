<script setup xmlns="http://www.w3.org/1999/html">
import Layout from "../../layout/Layout.vue"
import {useTeamStore} from "../../store/team";
import {useTrainingStore} from "../../store/training";
import {reactive, ref} from "vue";
import GridCatcher from "./GridCatcher.vue";
import BattingLogoPractice from "../../components/graphics/BattingLogoPractice.vue";
import GridField from "./GridField.vue";
import VelocityInput from "../../components/VelocityInput.vue";
import {toast} from "@/utils/AlertPlugin"
import {useAxiosAuth} from '@/composables/axios-auth.js'
import {Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot} from '@headlessui/vue'

import Loader from "../../components/Loader.vue";
import router from "../../../router";

const {team, teams} = useTeamStore();
const {axiosPost, axiosPut} = useAxiosAuth()
const isLoading = reactive({status: true})
const training = useTrainingStore();
const playerCard = ref(training.trainingActive.players[0]);
const playerList = [...training.trainingActive.players];
const balls = ref(0);
const sort = ref(0);
const change = ref(0);
const endNote = ref('');
const picked = ref('progress');
const dataProcess = ref({
  pitch: '',
  field: '',
  contact: '',
  trajectory: '',
  player: playerCard.value.id,
  team: team.id,
  velocity: 0,
  practice: training.trainingActive.id
});

const isOpen = ref(false)

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
  sort.value += 1;
}


const setTrajectory = (event) => {
  if (dataProcess.value.field === '' && dataProcess.value.contact === '') {
    clearStyle('active-btn-trajectory')
    toast.fire({
      icon: 'warning',
      title: 'Validation',
      text: 'If you not select a field and type of contact You dont set a Trajectory !!! ',
    })
  } else {
    clearStyle('active-btn-trajectory')
    event.target.classList.add('active-btn-trajectory')
    dataProcess.value.trajectory = event.target.value
  }
}
const setContact = (event) => {
  if (dataProcess.value.field === '' && event.target.value !== 'MF') {
    clearStyle('active-btn-contact')
    toast.fire({
      icon: 'warning',
      title: 'Validation',
      text: 'If you not select a field zone only you register Miss/Foul contact !!! ',
    })
  } else {
    clearStyle('active-btn-contact')
    event.target.classList.add('active-btn-contact')
    dataProcess.value.contact = event.target.value
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
const save = async () => {
  isLoading.status = !isLoading.status;
  let dataToSave = {
    practice_id: dataProcess.value.practice,
    team_id: dataProcess.value.team,
    batter_id: dataProcess.value.player,
    is_contact: dataProcess.value.field.point !== undefined,
    pitch_location: dataProcess.value.pitch.position,
    pitch_mark: dataProcess.value.pitch.point,
    quality_of_contact: dataProcess.value.contact === '' ? dataProcess.value.contact : 'N',
    type_of_hit: dataProcess.value.contact === 'MF' ? 'SM' : dataProcess.value.trajectory,
    field_mark: dataProcess.value.field.point,
    field_direction: dataProcess.value.field.zone,
    velocity: dataProcess.value.velocity,
    sort: sort.value
  }
  try {
    if (dataToSave.pitch_mark !== undefined && dataProcess.value.contact !== '') {
      if (dataToSave.velocity === 0 && dataProcess.value.contact !== '' && dataToSave.field_mark !== undefined) {
        isLoading.status = !isLoading.status;
        toast.fire({
          icon: 'warning',
          title: 'Validation',
          text: 'to save the record the velocity data must be greater than 0 and set a trajectory  !!! ',
        })
        return;
      } else {
        if (dataProcess.value.contact !== 'MF' && dataProcess.value.trajectory === '') {
          isLoading.status = !isLoading.status;
          toast.fire({
            icon: 'warning',
            title: 'Validation',
            text: 'to save the record set a trajectory  !!! ',
          })
          return;
        } else {
          if (dataProcess.value.contact === 'MF' && dataToSave.velocity !== 0 && dataProcess.value.trajectory === '') {
            isLoading.status = !isLoading.status;
            toast.fire({
              icon: 'warning',
              title: 'Validation',
              text: 'to save the record set a trajectory  !!! ',
            })
            return;
          }
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
          })
        }
      }
    } else {
      isLoading.status = !isLoading.status;
      toast.fire({
        icon: 'warning',
        timer: 3500,
        title: 'Validation',
        text: 'To save result, You must select pitch area and contact data !!! ',
      })
      return;
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
  dataProcess.value.pitch = '';
  dataProcess.value.field = '';
  dataProcess.value.contact = '';
  dataProcess.value.trajectory = '';
  clearStyle('active-btn-trajectory');
  clearStyle('active-btn-contact');
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
        await axiosPut('training/' + dataProcess.value.practice, dataEnd).then(async (response) => {
          if (response) {
            isLoading.status = !isLoading.status;
            toast.fire({
              icon: 'success',
              title: 'End training',
              text: 'Finished session' + dataProcess.value.practice,
            })
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
</script>
<template>
  <Loader v-show="!isLoading.status"/>
  <Layout>
    <div class="grid justify-center">
      <div class="">
        <div class="flex gap-4 lg:gap-12 justify-evenly lg:justify-start h-[6em] bg-white rounded rounded-lg">
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
              <img :src="playerCard.picture" alt="" class="img-player">
            </div>
            <div
              class="flex flex-col justify-center gap-x-2 mx-auto text-fungo-darkblue font-fungo-400 text-[16px] lg:w-[195px] lg:-ml-10">
              <div class="font-fungo-800">{{ playerCard.name.full }}</div>
              <div>Jersey: <span class="text-fungo-red font-fungo-800">{{ playerCard.shirt_number }}</span></div>
            </div>
          </div>
          <div v-if="training.trainingActive.players.length > 1 "
               class="grid content-center">
            <div class="text-fungo-darkblue font-fungo-800 text-[16px] lg:w-[195px] px-3">Change player</div>
            <select class="text-[12px] w-[90%]" @change="changeData($event)">
              <option v-for="player in playerList " :value="player.id">{{ player.name.full }}</option>
            </select>
          </div>
        </div>
      </div>
      <div class="w-full mt-5 order-1 xl:col-span-5">

        <div class="grid grid-cols-3 text-[16px] text-center gap-1">
          <div class="grid grid-cols-2 bg-white balls-count relative  shadow-lg shadow-lg">
            <div class="grid items-center text-fungo-red font-fungo-300 text-[16px]">Total Pitches
              :
            </div>
            <div
              class="ball-number grid items-center justify-items-end h-[90%]
              justify-evenly  text-fungo-darkblue font-fungo-800 text-[1.5em] ">
              {{ balls }}

            </div>
          </div>
          <div class="grid content-center  text-[16px] xl:text-[24px]  text-fungo-blue2">
            <button>show statistics ></button>
          </div>
          <div class="grid content-center text-[16px] xl:text-[24px] text-fungo-blue2">
            <button @click="openModal">end practice ></button>
          </div>
        </div>

      </div>
    </div>
    <div class="grid grid-flow-row md:grid-cols-2 xl:grid-cols-3 justify-center mt-2 xl:mt-5 gap-3">
      <div class="xl:bg-white">
        <div class="h-fit w-fit flex-grow mx-auto ">
          <GridCatcher :key="change" v-model="dataProcess.pitch"/>
        </div>
      </div>
      <div class=" bg-white p-3 ">
        <div class="grid grid-cols-2 gap-0.5 justify-items-center text-[24px]">
          <div id="content-contact" class="grid gap-y-1.5">
            <div class="text-fungo-red text-center ">Contact</div>
            <button class="button-ct " value="W"
                    @click="setContact($event)">
              Weak
            </button>
            <button class="button-ct " value="A"
                    @click="setContact($event)">Avg
            </button>
            <button class="button-ct " value="H"
                    @click="setContact($event)">Hard
            </button>
            <button class="button-ct " value="MF"
                    @click="setContact($event)">Miss/F
            </button>
          </div>
          <div id="content-contact" class="grid gap-y-0.5">
            <div class="text-fungo-red text-center">Pitch</div>
            <button class="button-ct " value="GB" @click="setTrajectory($event)">GB</button>
            <button class="button-ct " value="PF"
                    @click="setTrajectory($event)">PF
            </button>
            <button class="button-ct " value="FB"
                    @click="setTrajectory($event)">FB
            </button>
            <button class="button-ct " value="LD"
                    @click="setTrajectory($event)">LD
            </button>
          </div>
        </div>
      </div>
      <div class="  rounded p-2 gap-3 text-fungo-darkblue bg-white">
        <div class="md:h-[325px]">
          <VelocityInput :key="change" v-model="dataProcess.velocity"/>
          <div class="grid mt-5">
            <button
              class="rounded-xl rounded-l-3xl border bg-fungo-darkblue text-white mx-auto"
              @click="save">
              <div
                class="grid grid-cols-2 w-[200px]">
                <div class="m-1 p-1"><img
                  class="w-[20px] h-[20px] xl:w-[30px] xl:h-[30px]"
                  src="../../assets/img/login/assteslogin/ballbutton.png"></div>
                <div class="grid content-center items-center mr-8 text-[20px] -ml-12">Save</div>
              </div>
            </button>
          </div>
        </div>
      </div>
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
</style>
