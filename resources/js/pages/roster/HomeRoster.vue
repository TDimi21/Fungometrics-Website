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
const teamJoinCode = ref('')

const claimInviteModal = reactive({
  open: false,
  playerName: '',
  playerPhone: '',
  message: '',
})

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

      if (!teamJoinCode.value) {
        await fetchTeamJoinCode()
      }

      openClaimInviteForPlayer({
        firstName: dataPlayer.firstName,
        lastName: dataPlayer.lastName,
        phone: dataPlayer.mobileNumber,
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
  fetchTeamJoinCode()
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

const normalizeDigits = (value = '') => String(value).replace(/\D+/g, '')

const copyToClipboard = async (value, successTitle = 'Copied') => {
  const text = String(value || '').trim()
  if (!text) return

  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(text)
      await toast.fire({ icon: 'success', title: successTitle })
      return
    }
  } catch (_) {}

  try {
    const input = document.createElement('textarea')
    input.value = text
    input.setAttribute('readonly', '')
    input.style.position = 'absolute'
    input.style.left = '-9999px'
    document.body.appendChild(input)
    input.select()
    document.execCommand('copy')
    document.body.removeChild(input)
    await toast.fire({ icon: 'success', title: successTitle })
  } catch (_) {
    await toast.fire({ icon: 'warning', title: 'Unable to copy automatically' })
  }
}

const fetchTeamJoinCode = async () => {
  if (!team?.id) return
  try {
    const response = await axiosGet(`coach/teams/${team.id}/code`)
    teamJoinCode.value = response?.data?.data?.join_code || response?.data?.join_code || ''
  } catch (_) {
    teamJoinCode.value = ''
  }
}

const buildPlayerClaimMessage = ({ playerName, coachName, teamName, teamCode }) => {
  const code = String(teamCode || '').trim().toUpperCase()
  return [
    `Hi ${playerName}!`,
    `Coach ${coachName} has added you to ${teamName} on Fungometrics.`,
    'Download the Fungometrics app, then tap "CLAIM CODE" on the login screen.',
    `Use your cell number and team code${code ? ` (${code})` : ''} to claim your profile.`,
  ].join('\n')
}

const openClaimInviteForPlayer = ({ firstName, lastName, phone }) => {
  const playerName = `${firstName || ''} ${lastName || ''}`.trim() || 'Player'
  const coachName = String(
    userData?.name?.full || `${userData?.name?.first || ''} ${userData?.name?.last || ''}`
  ).trim() || 'your coach'

  claimInviteModal.playerName = playerName
  claimInviteModal.playerPhone = normalizeDigits(phone)
  claimInviteModal.message = buildPlayerClaimMessage({
    playerName,
    coachName,
    teamName: team?.name || 'the team',
    teamCode: teamJoinCode.value,
  })
  claimInviteModal.open = true
}
</script>

<template>
  <Layout>
    <Loader v-show="!isLoading.status" />

    <!-- ── Page wrapper ─────────────────────────────────────────────── -->
    <div class="min-h-screen bg-[#060b14] text-white">
      <div class="w-full px-4 py-6 lg:px-8 lg:py-8 pb-28 md:pb-12">

      <!-- Page title -->
      <div class="flex items-center gap-3 mb-5">
        <div class="w-1 h-7 bg-[#C00000] rounded-full" />
        <h1 class="text-2xl font-black tracking-wide text-white">Roster</h1>
        <span class="ml-auto text-white/30 text-sm hidden md:block">
          {{ tableDataCoaches.length }} coaches · {{ tableDataPlayers.length }} players
        </span>
      </div>

      <!-- ══════════════ COACHES ══════════════ -->
      <section class="mb-5 rounded-2xl border border-white/10 bg-[#0a1020]/80 backdrop-blur-xl p-5 shadow-xl">
        <!-- Section header bar -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
          <div class="flex items-center gap-2">
            <span class="text-[#7DD3FC] text-xs font-black uppercase tracking-widest">Coaches</span>
            <span class="bg-sky-500/10 text-sky-300 text-xs font-black px-2 py-0.5 rounded-full border border-sky-500/30">
              {{ tableDataCoaches.length }}
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
            <!-- Search coaches -->
            <div class="flex items-center gap-2 bg-[#0b1324]/80 border border-white/10 rounded-xl px-3 py-2">
              <svg class="w-4 h-4 text-app-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
              </svg>
              <input
                v-model="searchCoach"
                @input="searchCoahByName"
                type="search"
                placeholder="Search coaches…"
                class="bg-transparent text-white text-sm placeholder-white/35 outline-none w-36"
              />
            </div>
            <!-- Add from existing -->
            <button
              @click="isOpenModalCoach = true"
              class="flex items-center gap-1.5 bg-[#0b1324] border border-white/10 hover:border-sky-400/50
                     text-sky-300 text-xs font-black px-4 py-2.5 rounded-xl transition"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Add Existing
            </button>
            <!-- Create new coach -->
            <button
              @click="isAddCoach = true"
              class="flex items-center gap-1.5 bg-[#C00000] hover:bg-[#9B0000]
                text-white text-xs font-black px-4 py-2.5 rounded-xl transition"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Create Coach
            </button>
          </div>
        </div>

        <!-- Coach cards grid -->
        <div v-if="isLoadingCoach" class="text-white/25 text-center py-10">Loading coaches…</div>
        <div v-else-if="!tableDataCoaches.length" class="text-white/25 text-center py-10">No coaches found</div>
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
      <section class="rounded-2xl border border-white/10 bg-[#0a1020]/80 backdrop-blur-xl p-5 shadow-xl">
        <!-- Section header bar -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
          <div class="flex items-center gap-2">
            <span class="text-[#FCA5A5] text-xs font-black uppercase tracking-widest">Players</span>
            <span class="bg-red-500/10 text-red-300 text-xs font-black px-2 py-0.5 rounded-full border border-red-500/30">
              {{ tableDataPlayers.length }}
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
            <!-- Search players -->
            <div class="flex items-center gap-2 bg-[#0b1324]/80 border border-white/10 rounded-xl px-3 py-2">
              <svg class="w-4 h-4 text-app-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
              </svg>
              <input
                v-model="searchPlayer"
                @input="searchPlayerByName"
                type="search"
                placeholder="Search players…"
                class="bg-transparent text-white text-sm placeholder-white/35 outline-none w-36"
              />
            </div>
            <!-- Add from existing -->
            <button
              @click="isOpenModalPlayer = true"
              class="flex items-center gap-1.5 bg-[#0b1324] border border-white/10 hover:border-red-400/50
                     text-red-300 text-xs font-black px-4 py-2.5 rounded-xl transition"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Add Existing
            </button>
            <!-- Create new player -->
            <button
              @click="isAddPlayer = true"
              class="flex items-center gap-1.5 bg-[#C00000] hover:bg-[#9B0000]
                text-white text-xs font-black px-4 py-2.5 rounded-xl transition"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Create Player
            </button>

            <button
              v-if="teamJoinCode"
              @click="copyToClipboard(teamJoinCode, 'Team claim code copied')"
              class="flex items-center gap-1.5 bg-white/5 border border-white/10 hover:border-sky-400/50
                     text-white text-xs font-black px-4 py-2.5 rounded-xl transition"
            >
              Team Code:
              <span class="text-sky-300">{{ teamJoinCode }}</span>
            </button>
          </div>
        </div>

        <!-- Player cards grid -->
        <div v-if="isLoadingPlayer" class="text-white/25 text-center py-10">Loading players…</div>
        <div v-else-if="!tableDataPlayers.length" class="text-white/25 text-center py-10">No players found</div>
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
    </div>

    <!-- ══ Modals ══════════════════════════════════════════════════════ -->
    <div v-if="isOpenModalCoach">
      <ModalSearchCoach :isOpen="isOpenModalCoach" @closeModal="changeBoolCoach" />
      <div class="opacity-70 fixed inset-0 z-40 bg-[#060b14]" />
    </div>
    <div v-if="isOpenModalPlayer">
      <ModalSearchPlayer :isOpen="isOpenModalPlayer" @closeModal="changeBool()" />
      <div class="opacity-70 fixed inset-0 z-40 bg-[#060b14]" />
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

          <div class="rounded-xl border border-app-blue/30 bg-app-blue/10 px-3 py-2">
            <p class="text-app-blue text-xs font-bold uppercase tracking-wider">Claim Code</p>
            <p class="text-white text-sm mt-1">
              Players claim from the app with their mobile number + team code
              <span class="font-bold text-app-blue">{{ teamJoinCode ? ` ${teamJoinCode}` : ' (not available)' }}</span>.
            </p>
          </div>
        </form>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="isAddPlayer = false" class="btn-ghost">Cancel</button>
          <button @click="submitAddPlayer" class="btn-primary">Add Player</button>
        </div>
      </div>
      <div class="opacity-70 fixed inset-0 z-40 bg-[#060b14]" />
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
      <div class="opacity-70 fixed inset-0 z-40 bg-[#060b14]" />
    </div>

    <!-- ══ Claim invite modal (app parity) ════════════════════════════ -->
    <div v-if="claimInviteModal.open" class="fixed inset-0 z-50 flex justify-center items-center px-4">
      <div class="modal-dark w-full max-w-lg">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-white text-lg font-bold">Player Claim Instructions</h2>
          <button @click="claimInviteModal.open = false" class="text-app-muted hover:text-white transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <div class="rounded-xl border border-white/10 bg-app-card px-4 py-3 mb-4">
          <p class="text-app-muted text-xs uppercase tracking-widest">Team code</p>
          <p class="text-app-blue text-xl font-black tracking-[0.2em]">{{ teamJoinCode || '—' }}</p>
        </div>

        <p class="text-white/80 text-sm mb-2">
          Share this with
          <span class="text-white font-bold"> {{ claimInviteModal.playerName }}</span>
          <span v-if="claimInviteModal.playerPhone" class="text-app-muted"> ({{ claimInviteModal.playerPhone }})</span>
        </p>

        <textarea
          :value="claimInviteModal.message"
          readonly
          class="w-full h-40 rounded-xl bg-[#0b1328] border border-white/10 text-white/90 text-sm p-3 resize-none"
        />

        <div class="flex justify-end gap-3 mt-4">
          <button @click="copyToClipboard(teamJoinCode, 'Team claim code copied')" class="btn-ghost">Copy Team Code</button>
          <button @click="copyToClipboard(claimInviteModal.message, 'Claim message copied')" class="btn-primary">Copy Message</button>
        </div>
      </div>
      <div class="opacity-70 fixed inset-0 z-40 bg-[#060b14]" @click="claimInviteModal.open = false" />
    </div>

  </Layout>
</template>

<style scoped>
/* Dark modal container */
.modal-dark {
  position: relative;
  z-index: 50;
  background: #081226;
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .45);
}

/* Form field label */
.field-label {
  display: block;
  color: rgba(226, 232, 240, 0.7);
  font-size: 0.75rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.25rem;
}

/* Primary red button */
.btn-primary {
  background: #C00000;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 800;
  padding: 0.625rem 1.25rem;
  border-radius: 0.75rem;
  transition: background 0.2s;
}
.btn-primary:hover { background: #9B0000; }

/* Ghost button */
.btn-ghost {
  background: rgba(255, 255, 255, 0.05);
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.875rem;
  font-weight: 800;
  padding: 0.625rem 1.25rem;
  border-radius: 0.75rem;
  border: 1px solid rgba(255, 255, 255, 0.15);
  transition: all 0.2s;
}
.btn-ghost:hover { background: rgba(255,255,255,0.1); color: #fff; }
</style>

