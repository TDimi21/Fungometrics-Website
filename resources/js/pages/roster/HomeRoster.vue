<script setup>
import Layout from '@/layout/Layout.vue'
import { InputBase, BigButtonField, InutTel, LabelField } from '@/components/form'
import { SearchIcon, ArrowHeadRightIcon, ArrowRightIcon } from '@/components/icons'
import {ref, onMounted, reactive} from 'vue'
import { CoachTable, PlayerTable, ModalSearchPlayer, ModalSearchCoach, RosterCard } from '@/components/roster'
import { useUserStore } from '@/store/user'
import {useTeamStore} from "@/store/team";
import { usePlayerStore } from '@/store/players.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { toast } from "@/utils/AlertPlugin"
import Loader from "@/components/Loader.vue";
import axios from "axios";
import { storeToRefs } from 'pinia'

const { axiosGet, axiosDelete } = useAxiosAuth()
const {userData} = useUserStore();
const {team } = useTeamStore();
const playerStore = usePlayerStore()

const { players } = storeToRefs(playerStore)

const pages = ref([])
const api_url = process.env.API_ENDPOINT;
const isLoading = reactive({status: true})
const token = JSON.parse(localStorage.getItem('auth')).token

const searchCoach = ref('')
const coachesDataDefault = ref([])
const searchPlayer = ref('')
const playersDataDefault = ref([])

//Constantes tablas
const isLoadingCoach = ref(false)
const isLoadingPlayer = ref(false)
const tableDataPlayers = ref([])
const playerLinks = ref([])
const tableDataCoaches = ref([])

//Constantes para modals
const isOpenModalCoach = ref(false)
const isOpenModalPlayer = ref(false)
const isAddPlayer = ref(false)
const isAddCoach = ref(false)

let dataCoach = reactive({
  firstName: '',
  lastName: '',
  mobileNumber: '',
})

let dataPlayer = reactive({
  firstName: '',
  lastName: '',
  mobileNumber: '',
})

const changeBool = ()=>{
  isOpenModalPlayer.value = !isOpenModalPlayer.value
  getPlayerByTeam()
}

const changeBoolCoach = ()=>{
  isOpenModalCoach.value = !isOpenModalCoach.value
}

const getCoachesByRoster = async(page = 1) => {
  const data = {}
  try {
    isLoadingPlayer.value = true
    await axiosGet(`coach/roster/coaches`, data)
      .then((response) => {
        if (response) {
          tableDataCoaches.value = response.data.data
          coachesDataDefault.value = tableDataCoaches.value
          // pages.value = response.data.meta.links
        }
      })
    tableDataCoaches.value.splice(0, 0, userData)
  } catch (error) {
    tableDataCoaches.value.splice(0, 0, userData)
    // await toast.fire({
    //   icon: 'error',
    //   title: 'Error get data',
    //   text: 'Yo can try with a different type of user',
    // })
  } finally {
    isLoadingPlayer.value = false
  }
}

const getPlayerByTeam = async(page = 1) => {
  const data = {}
  try {
    isLoadingPlayer.value = true
    await axiosGet(`coach/teams/${team.id}`, data)
      .then((response) => {
        if (response) {
          tableDataPlayers.value = response.data.data
          playersDataDefault.value = tableDataPlayers.value
          playerLinks.value = response.data.links
          playerStore.setPlayers(tableDataPlayers.value)
        }
      })

  } catch (error) {
    // await toast.fire({
    //   icon: 'error',
    //   title: 'Error get data',
    //   text: 'Yo can try with a different type of user',
    // })
  } finally {
    isLoadingPlayer.value = false
  }
}

const searchCoahByName = async () =>{
  if(searchCoach.value.length == 0){
    tableDataCoaches.value = coachesDataDefault.value;
  }else if(searchCoach.value.length >= 1 && searchCoach.value.length <= 2){
    await toast.fire({
      icon: 'error',
      title: 'Error get data',
      text: 'Please enter at least three letters to perform the search',
    })
  }else{
    const newArray = ref([])
    coachesDataDefault.value.forEach(element => {
      if(element.name.full.toLowerCase().includes(searchCoach.value.toLowerCase())){
        newArray.value.push(element)
      }
    });

    if(newArray.value.length > 0){
      tableDataCoaches.value = newArray.value
    }else{
      searchCoach.value = ""
      await toast.fire({
        icon: 'error',
        title: 'Error get data',
        text: 'Not  Coach Found',
      })
    }
  }
}

const searchPlayerByName = async () =>{
  if(searchPlayer.value.length == 0){
    tableDataPlayers.value = playersDataDefault.value;
  }else if(searchPlayer.value.length >= 1 && searchPlayer.value.length <= 2){
    await toast.fire({
      icon: 'error',
      title: 'Error get data',
      text: 'Please enter at least three letters to perform the search',
    })
  }else{
    const newArray = ref([])
    playersDataDefault.value.forEach(element => {
      if(element.name.full.toLowerCase().includes(searchPlayer.value.toLowerCase())){
        newArray.value.push(element)
      }
    });

    if(newArray.value.length > 0){
      tableDataPlayers.value = newArray.value
    }else{
      searchPlayer.value = ""
      await toast.fire({
        icon: 'error',
        title: 'Error get data',
        text: 'Not  Player Found',
      })
    }
  }
}

const submitAddPlayer = async () => {
  isLoading.status =!isLoading.status;
  if(dataPlayer.mobileNumber == "" || dataPlayer.firstName == "" || dataPlayer.lastName == ""){
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You must complete all the fields',
    })
    isLoading.status =!isLoading.status;
  }else{
    let dataForm = new FormData();
    dataForm.append('phone', dataPlayer.mobileNumber)
    dataForm.append('team', team.id)
    dataForm.append('name[first]', dataPlayer.firstName)
    dataForm.append('name[last]', dataPlayer.lastName)
    const config = {
      headers: { Authorization: `Bearer ${token}` }
    };
    await axios.post(api_url+'coach/add/players', dataForm, config).then(async function (response) {
      console.log(response.data);

      let tempResponse = response.data.data
      let playerToSetInStore = {
        "id": tempResponse.id,
        "name": {
          "first": tempResponse.profile.first_name,
          "last": tempResponse.profile.last_name,
          "full": tempResponse.profile.first_name + tempResponse.profile.last_name
        },
        "avatar":"https://fungometrics.s3.amazonaws.com/logo.png",
        "body":{
          "ft":null,
          "inch":null,
          "weight":null,
          "full_height":"'”",
          "weight_data":" lb"
        },
        "born": {
          "date":null,
          "age":0
        },
        "number_in_shirt":null,
        "throw_side":null,
        "hit_side":null,
        "positions":[]
      }

      toast.fire({
        icon: 'success',
        title: 'Player Register',
        text: response.data.message,
      })
      /* reload data */
      getPlayerByTeam()
      players.value.push(playerToSetInStore)
      dataPlayer.firstName = '',
      dataPlayer.lastName = '',
      dataPlayer.mobileNumber = '',
      isLoading.status =!isLoading.status;
      isAddPlayer.value = false
      router.go(router.currentRoute)
    }).catch(async function (error){
      if (error.response.data.code === '001V' || error.response.status === 422 ) {
        const errorsObject = error.response.data.data.errors
        let errorMessage = ''
        let isAllow = false
        for (const [key, value] of Object.entries(errorsObject)) {
          if(!isAllow){
            isAllow = true
            errorMessage = value
          }
        }
        await toast.fire({
          icon: 'warning',
          title: 'Player Warning !!!',
          text: errorMessage,
        })
      } else {
        await toast.fire({
          icon: 'error',
          title: 'Player Error !!!',
          text: "strike 3 is out, have a internal problem, " +error.response.data.message,
        })
      }
      isLoading.status =!isLoading.status;
    })
  }
}

const submitAddCoach = async () => {
  isLoading.status =!isLoading.status;
  if(dataCoach.mobileNumber == "" || dataCoach.firstName == "" || dataCoach.lastName == ""){
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You must complete all the fields',
    })
    isLoading.status =!isLoading.status;
  }else{
    let dataForm = new FormData();
    dataForm.append('phone', dataCoach.mobileNumber)
    dataForm.append('team', team.id)
    dataForm.append('name[first]', dataCoach.firstName)
    dataForm.append('name[last]', dataCoach.lastName)
    const config = {
      headers: { Authorization: `Bearer ${token}` }
    };
    await axios.post(api_url+'coach/add/coaches', dataForm, config).then(async function (response) {
      toast.fire({
        icon: 'success',
        title: 'Coach Register',
        text: response.data.message,
      })
      dataCoach.firstName = '',
      dataCoach.lastName = '',
      dataCoach.mobileNumber = '',
      isLoading.status =!isLoading.status;
      isAddCoach.value = false
      getCoachesByRoster()
    }).catch(async function (error){
      console.log(error.response);
      if (error.response.data.code === '001V' || error.response.status === 422 ) {
        const errorsObject = error.response.data.data.errors
        let errorMessage = ''
        let isAllow = false
        for (const [key, value] of Object.entries(errorsObject)) {
          if(!isAllow){
            isAllow = true
            errorMessage = value
          }
        }
        await toast.fire({
          icon: 'warning',
          title: 'Coach Warning !!!',
          text: errorMessage,
        })
      } else {
        await toast.fire({
          icon: 'error',
          title: 'Coach Error !!!',
          text: "strike 3 is out, have a internal problem, " +error.response.data.message,
        })
      }
      isLoading.status =!isLoading.status;
    })
  }
}

onMounted(() => {
  getPlayerByTeam()
  getCoachesByRoster()
})

const deleteCoach = (deleteItem) =>{
  if(Object.keys(deleteItem).length > 0){
    axiosDelete(`coach/remove/coach/`, deleteItem.id).then((response)=>{
      console.log(response.data)
      toast.fire({
        icon: 'success',
        title: 'removed coach',
        text: 'coach already removed',
      })
      tableDataCoaches.value = []
      getCoachesByRoster()
    }).catch((error)=>{
      isOpenDelteModal = false
      toast.fire({
        icon: 'error',
        title: 'Not removed coach',
        text: 'Sorry it is not possible remove this information',
      })
    })
  }
}

const updateTable = (item) => {
  getPlayerByTeam()
}
</script>

<template>
  <Layout>
    <Loader v-show="!isLoading.status" />

    <!-- ── Page wrapper ─────────────────────────────────────────────── -->
    <div class="min-h-screen bg-app-bg px-4 md:px-8 py-8">

      <!-- Page title -->
      <div class="flex items-center gap-3 mb-8">
        <div class="w-1 h-8 bg-app-red rounded-full" />
        <h1 class="text-white text-2xl md:text-3xl font-bold tracking-wide">Roster</h1>
        <span class="ml-auto text-app-muted text-sm">
          {{ tableDataCoaches.length }} coaches · {{ tableDataPlayers.length }} players
        </span>
      </div>

      <!-- ══════════════ COACHES ══════════════ -->
      <section class="mb-10">
        <!-- Section header bar -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
          <div class="flex items-center gap-2">
            <span class="text-app-blue text-xs font-bold uppercase tracking-widest">Coaches</span>
            <span class="bg-app-blue/20 text-app-blue text-xs font-bold px-2 py-0.5 rounded-full border border-app-blue/30">
              {{ tableDataCoaches.length }}
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
            <!-- Search coaches -->
            <div class="flex items-center gap-2 bg-app-card border border-white/10 rounded-xl px-3 py-2">
              <svg class="w-4 h-4 text-app-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
              </svg>
              <input
                v-model="searchCoach"
                @input="searchCoahByName"
                type="search"
                placeholder="Search coaches…"
                class="bg-transparent text-white text-sm placeholder-app-muted outline-none w-36"
              />
            </div>
            <!-- Add from existing -->
            <button
              @click="isOpenModalCoach = true"
              class="flex items-center gap-1.5 bg-app-navy border border-white/10 hover:border-app-blue/50
                     text-app-blue text-xs font-bold px-4 py-2.5 rounded-xl transition"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Add Existing
            </button>
            <!-- Create new coach -->
            <button
              @click="isAddCoach = true"
              class="flex items-center gap-1.5 bg-app-red hover:bg-app-red-hover
                     text-white text-xs font-bold px-4 py-2.5 rounded-xl transition"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Create Coach
            </button>
          </div>
        </div>

        <!-- Coach cards grid -->
        <div v-if="isLoadingCoach" class="text-app-muted text-center py-10">Loading coaches…</div>
        <div v-else-if="!tableDataCoaches.length" class="text-app-muted text-center py-10">No coaches found</div>
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          <RosterCard
            v-for="item in tableDataCoaches"
            :key="item.id"
            :item="item"
            type="coach"
            :idTeam="team.id"
            @remove-item="deleteCoach($event)"
          />
        </div>
      </section>

      <!-- ══════════════ PLAYERS ══════════════ -->
      <section>
        <!-- Section header bar -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
          <div class="flex items-center gap-2">
            <span class="text-app-red text-xs font-bold uppercase tracking-widest">Players</span>
            <span class="bg-app-red/20 text-app-red text-xs font-bold px-2 py-0.5 rounded-full border border-app-red/30">
              {{ tableDataPlayers.length }}
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
            <!-- Search players -->
            <div class="flex items-center gap-2 bg-app-card border border-white/10 rounded-xl px-3 py-2">
              <svg class="w-4 h-4 text-app-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
              </svg>
              <input
                v-model="searchPlayer"
                @input="searchPlayerByName"
                type="search"
                placeholder="Search players…"
                class="bg-transparent text-white text-sm placeholder-app-muted outline-none w-36"
              />
            </div>
            <!-- Add from existing -->
            <button
              @click="isOpenModalPlayer = true"
              class="flex items-center gap-1.5 bg-app-navy border border-white/10 hover:border-app-red/50
                     text-app-red text-xs font-bold px-4 py-2.5 rounded-xl transition"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Add Existing
            </button>
            <!-- Create new player -->
            <button
              @click="isAddPlayer = true"
              class="flex items-center gap-1.5 bg-app-red hover:bg-app-red-hover
                     text-white text-xs font-bold px-4 py-2.5 rounded-xl transition"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Create Player
            </button>
          </div>
        </div>

        <!-- Player cards grid -->
        <div v-if="isLoadingPlayer" class="text-app-muted text-center py-10">Loading players…</div>
        <div v-else-if="!tableDataPlayers.length" class="text-app-muted text-center py-10">No players found</div>
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          <RosterCard
            v-for="item in tableDataPlayers"
            :key="item.id"
            :item="item"
            type="player"
            :idTeam="team.id"
            @remove-item="updateTable(true)"
          />
        </div>
      </section>
    </div>

    <!-- ══ Modals ══════════════════════════════════════════════════════ -->
    <div v-if="isOpenModalCoach">
      <ModalSearchCoach :isOpen="isOpenModalCoach" @closeModal="changeBoolCoach" />
      <div class="opacity-70 fixed inset-0 z-40 bg-app-bg" />
    </div>
    <div v-if="isOpenModalPlayer">
      <ModalSearchPlayer :isOpen="isOpenModalPlayer" @closeModal="changeBool()" />
      <div class="opacity-70 fixed inset-0 z-40 bg-app-bg" />
    </div>

    <!-- ══ Create Player modal ═════════════════════════════════════════ -->
    <div v-if="isAddPlayer" class="fixed inset-0 z-50 flex justify-center items-center px-4">
      <div class="modal-dark w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-white text-lg font-bold">Create Player</h2>
          <button @click="isAddPlayer = false" class="text-app-muted hover:text-white transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <form class="flex flex-col gap-4">
          <div>
            <label class="field-label">First Name *</label>
            <InputBase v-model="dataPlayer.firstName" />
          </div>
          <div>
            <label class="field-label">Last Name *</label>
            <InputBase v-model="dataPlayer.lastName" />
          </div>
          <div>
            <label class="field-label">Mobile Number *</label>
            <InutTel v-model="dataPlayer.mobileNumber" />
          </div>
        </form>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="isAddPlayer = false" class="btn-ghost">Cancel</button>
          <button @click="submitAddPlayer" class="btn-primary">Add Player</button>
        </div>
      </div>
      <div class="opacity-70 fixed inset-0 z-40 bg-app-bg" />
    </div>

    <!-- ══ Create Coach modal ══════════════════════════════════════════ -->
    <div v-if="isAddCoach" class="fixed inset-0 z-50 flex justify-center items-center px-4">
      <div class="modal-dark w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-white text-lg font-bold">Create Coach</h2>
          <button @click="isAddCoach = false" class="text-app-muted hover:text-white transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <form class="flex flex-col gap-4">
          <div>
            <label class="field-label">First Name *</label>
            <InputBase v-model="dataCoach.firstName" />
          </div>
          <div>
            <label class="field-label">Last Name *</label>
            <InputBase v-model="dataCoach.lastName" />
          </div>
          <div>
            <label class="field-label">Mobile Number *</label>
            <InutTel v-model="dataCoach.mobileNumber" />
          </div>
        </form>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="isAddCoach = false" class="btn-ghost">Cancel</button>
          <button @click="submitAddCoach" class="btn-primary">Add Coach</button>
        </div>
      </div>
      <div class="opacity-70 fixed inset-0 z-40 bg-app-bg" />
    </div>

  </Layout>
</template>

<style scoped>
/* Dark modal container */
.modal-dark {
  @apply relative z-50 bg-app-navy border border-white/10 rounded-2xl p-6 shadow-2xl;
}

/* Form field label */
.field-label {
  @apply block text-app-muted text-xs font-fungo-700 uppercase tracking-wide mb-1;
}

/* Primary red button */
.btn-primary {
  @apply bg-app-red hover:bg-app-red-hover text-white text-sm font-fungo-700 px-5 py-2.5 rounded-xl transition;
}

/* Ghost button */
.btn-ghost {
  @apply bg-white/5 hover:bg-white/10 text-white/60 hover:text-white text-sm font-fungo-700 px-5 py-2.5 rounded-xl border border-white/10 transition;
}
</style>

