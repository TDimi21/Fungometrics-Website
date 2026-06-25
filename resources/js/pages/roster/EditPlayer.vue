<script setup>
import Layout from '@/layout/Layout.vue'
import {
  InputBase,
  InputImage,
  InutTel,
  LabelField
} from '@/components/form'
import { ArrowDownIcon, ArrowRightIcon } from '@/components/icons'
import { reactive, ref } from 'vue'
import {useTeamStore} from "@/store/team";
import {usePlayerStore} from "../../store/players";
import Loader from "@/components/Loader.vue";
import {useRouter, useRoute} from "vue-router"
import { playerTypes }  from '../../utils'
import {toast} from "../../utils/AlertPlugin";
import axios from 'axios'

const {team} = useTeamStore();
const {players} = usePlayerStore();
const route = useRoute()
const router = useRouter()
let isLoading = reactive({status:true});
const token = JSON.parse(localStorage.getItem('auth')).token
const id = route.params.id
let player = ref({
  id: '',
  type: [],
  heightFt: 0,
  heightInch: 0,
  firstName: '',
  lastName: '',
  born: '',
  email: '',
  mobileNumber: '',
  pastTeam: '',
  currentTeam: '',
  number_in_shirt: '',
  avatar: '',
  weigth: 0,
  sides: {
    pitch: '',
    hit: '',
  },
})

for (let index = 0; index < players.length; index++) {
  if(id === players[index].id){
    const item = players[index]
      player.value.heightFt = item.body.ft,
      player.value.heightInch = item.body.inch,
      player.value.firstName = item.name.first,
      player.value.lastName = item.name.last,
      player.value.born = item.born.date,
      player.value.email = item.email,
      player.value.mobileNumber = item.phone,
      player.value.currentTeam = team.name,
      player.value.id = item.id,
      player.value.number_in_shirt = item.shirt_number,
      player.value.avatar = item.avatar,
      player.value.weigth = item.body.weight
      player.value.sides.pitch = item.throw_side ?? ''
      player.value.sides.hit = item.hit_side ?? ''
      // player.value.type = item.positions

      item.positions.forEach(types => {
        const value = String(types?.position ?? '').trim()
        if (value && !player.value.type.includes(value)) {
          player.value.type.push(value)
        }
      })
  }
}

const normalizeType = (type) => String(type ?? '').trim().toUpperCase()

const isTypeSelected = (type) => {
  const target = normalizeType(type)
  return player.value.type.some((value) => normalizeType(value) === target)
}

const typeClicked = (type) => {
  const target = normalizeType(type)
  if (!target) return

  if (isTypeSelected(type)) {
    player.value.type = player.value.type.filter((value) => normalizeType(value) !== target)
  } else {
    player.value.type.push(String(type).trim())
  }
}

const submitUpdate = async () => {
  let playerPosition =[];
  isLoading.status =!isLoading.status;
  // InputImage emits the selected File via v-model; undefined when unchanged so the
  // existing avatar is preserved (backend ignores non-file picture values).
  const imageTemp = player.value.avatar instanceof File ? player.value.avatar : undefined
  let dataForm = new FormData();
  dataForm.append('email', player.value.email)
  dataForm.append('phone', player.value.mobileNumber)
  if(imageTemp == undefined){
    dataForm.append('picture', "https://fungometrics.s3.amazonaws.com/updatedlogo.png")
  }else{
    dataForm.append('picture', imageTemp)
  }
  dataForm.append('profile[name][first]', player.value.firstName)
  dataForm.append('profile[name][last]', player.value.lastName)
  dataForm.append('player[born]', player.value.born)
  dataForm.append('player[ft]', player.value.heightFt)
  dataForm.append('player[inch]', player.value.heightInch)
  // dataForm.append('player[weight]', player.value.weigth)
  dataForm.append('player[shirt]', player.value.number_in_shirt)
  dataForm.append('player[sides][pitch]', player.value.sides.pitch)
  dataForm.append('player[sides][hit]', player.value.sides.hit)
  player.value.type.forEach(function (item,key){
    dataForm.append(`positions[${key}][position]`, item)
  });
  const api_url = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || '';
  const config = {
    headers: { Authorization: `Bearer ${token}` }
  };

  await axios.post(api_url+'edit/players/'+player.value.id, dataForm, config).then(async function (response) {
    toast.fire({
      icon: 'success',
      title: 'Player information update',
      text: response.data.message,
    })

    isLoading.status =!isLoading.status;
    await router.replace("/roster")

  }).catch(async function (error){
    console.log(error.response);
    isLoading.status =!isLoading.status;
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
      toast.fire({
        icon: 'error',
        title: 'Player Error !!!',
        text: "strike 3 is out, have a internal problem, " +error.response.data.message,
      })
    }
  })
}
</script>

<template>
  <Layout>
    <Loader v-show="!isLoading.status"/>
    <div class="edit-player-page w-full px-4 md:px-8 py-6 md:py-10">
      <div class="max-w-6xl mx-auto practice-shell p-5 md:p-8">
        <div class="relative mb-7">
          <h1 class="text-white text-3xl md:text-[42px] text-center font-fungo-700 tracking-wide">Edit Profile Player</h1>
          <RouterLink to="/roster" class="absolute right-0 top-1/2 -translate-y-1/2 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]">
            <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
          </RouterLink>
        </div>

        <section class="profile-card">
          <div class="profile-card-header">
            <h2 class="profile-card-title">Player</h2>
          </div>

          <form class="profile-card-body" @submit.prevent="submitUpdate">
            <div class="top-grid">
              <div class="image-holder">
                <InputImage label="Picture" v-model="player.avatar" inputClasses="h-52"/>
              </div>

              <div class="player-meta">
                <div>
                  <LabelField text="Position" :required="true"/>
                  <div class="types-grid">
                    <button
                      v-for="(type) in playerTypes"
                      :key="type"
                      type="button"
                      @click="typeClicked(type)"
                      class="btn-type-player"
                      :class="{'active-button' : isTypeSelected(type) }"
                    >{{ type }}</button>
                  </div>
                </div>

                <div class="height-grid">
                  <div class="height-row">
                    <LabelField text="Height ft"/>
                    <InputBase v-model="player.heightFt" inputClasses="w-full" />
                  </div>
                  <div class="height-row">
                    <LabelField text="Height inch"/>
                    <InputBase v-model="player.heightInch" inputClasses="w-full" />
                  </div>
                </div>
              </div>
            </div>

            <div class="inputs-grid player-grid mt-4">
              <div>
                <LabelField text="First name" :required="true"/>
                <InputBase v-model="player.firstName" />
              </div>
              <div>
                <LabelField text="Last name" :required="true"/>
                <InputBase v-model="player.lastName" />
              </div>
              <div>
                <LabelField text="Born" :required="true"/>
                <InputBase v-model="player.born" inputType="date"/>
              </div>

              <div>
                <LabelField text="E-Mail address" :required="true"/>
                <InputBase v-model="player.email" inputType="email"/>
              </div>
              <div>
                <LabelField text="Mobile number" :required="true"/>
                <InutTel v-model="player.mobileNumber" />
              </div>
              <div>
                <LabelField :required="true" text="Current team"/>
                <div class="relative w-full">
                  <select class="team-select" v-model="player.currentTeam" style="z-index: 9" disabled>
                    <option selected :value="team.name">{{ team.name }}</option>
                  </select>
                  <div class="arrow-position"> <ArrowDownIcon color="E2E8F0"/> </div>
                </div>
              </div>

              <div>
                <LabelField text="Number of shirt" :required="true"/>
                <InputBase v-model="player.number_in_shirt" inputType="text"/>
              </div>

              <div>
                <LabelField text="Throws L/R" :required="true"/>
                <select v-model="player.sides.pitch" class="team-select">
                  <option value="">Select</option>
                  <option value="L">L</option>
                  <option value="R">R</option>
                </select>
              </div>

              <div>
                <LabelField text="Bats L/R/S" :required="true"/>
                <select v-model="player.sides.hit" class="team-select">
                  <option value="">Select</option>
                  <option value="L">L</option>
                  <option value="R">R</option>
                  <option value="S">S</option>
                </select>
              </div>
            </div>

            <div class="action-row">
              <button class="btn-edit-profile rounded-button-right" type="submit">
                <img alt="button register coach" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0" src="../../assets/img/login/assteslogin/ballbutton.svg">
                <span class="mx-2">Update Player</span>
                <div class="text-white mx-2 animate-bounce-r"><ArrowRightIcon color="ffffff" w="50" h="50"/></div>
              </button>
            </div>
          </form>
        </section>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.arrow-position{
  z-index: 0;
  position: absolute;
  top: 7px;
  right: 8px;
}

.active-button {
  background-color: #C00000!important;
  color: white !important;
  border-color: #C00000!important;
}

@keyframes bounce {
  0%, 100% {
    transform: translateX(-25%);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: none;
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
}

.animate-bounce-r {
  animation: bounce 1s infinite;
}

.btn-edit-profile{
  @apply grid place-items-center grid-flow-col flex-row w-[250px] lg:w-[300px] rounded-t-[30px] rounded-r-[10px] rounded-b-[10px] rounded-l-[30px]
    px-2 py-2 text-base md:text-[16px] lg:text-[18px] bg-fungo-red text-white hover:bg-fungo-red-hover font-fungo-700 tracking-wide
}

.edit-player-page {
  background: #060b14;
}

.practice-shell {
  background: rgba(10, 16, 32, 0.58);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 20px;
  box-shadow: 0 18px 45px rgba(0, 0, 0, 0.28);
}

.profile-card {
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 1rem;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.72);
}

.profile-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 1rem;
  background: rgba(10, 16, 32, 0.9);
  border-bottom: 1px solid rgba(255, 255, 255, 0.14);
}

.profile-card-title {
  color: #ffffff;
  font-size: 1.35rem;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.profile-card-body {
  padding: 1rem;
  background: rgba(15, 23, 42, 0.45);
}

.top-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
}

.image-holder {
  max-width: 420px;
}

.player-meta {
  display: grid;
  gap: 1rem;
}

.types-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.5rem;
  margin-top: 0.35rem;
}

.height-grid {
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 0.7rem;
}

.height-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.35rem;
}

.inputs-grid {
  display: grid;
  gap: 0.9rem;
}

.player-grid {
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

.action-row {
  margin-top: 1.2rem;
  display: flex;
  justify-content: center;
}

.btn-type-player {
  @apply rounded-md border py-2 w-full h-10 cursor-pointer text-center;
  background: rgba(10, 16, 32, 0.9);
  border-color: rgba(255, 255, 255, 0.26);
  color: #e2e8f0;
  font-weight: 800;
}

.team-select {
  width: 100%;
  min-width: 0;
  height: 2.7rem;
  padding: 0.5rem 0.7rem;
  border-radius: 0.55rem;
  border: 1px solid rgba(255, 255, 255, 0.22) !important;
  background: rgba(10, 16, 32, 0.9) !important;
  color: #f8fafc !important;
  font-size: 0.95rem;
  appearance: none;
}

.edit-player-page :deep(label),
.edit-player-page :deep(input),
.edit-player-page :deep(select),
.edit-player-page :deep(option),
.edit-player-page :deep(span),
.edit-player-page :deep(h1),
.edit-player-page :deep(h2),
.edit-player-page :deep(h3),
.edit-player-page :deep(button),
.edit-player-page :deep(a) {
  font-weight: 800;
}

.edit-player-page :deep(.profile-card-body label) {
  color: #e2e8f0;
  font-size: 0.95rem;
  margin-bottom: 0.35rem;
}

.edit-player-page :deep(.profile-card-body input),
.edit-player-page :deep(.profile-card-body select) {
  width: 100% !important;
  min-width: 0;
  height: 2.7rem;
  padding: 0.5rem 0.7rem;
  border-radius: 0.55rem;
  border: 1px solid rgba(255, 255, 255, 0.22) !important;
  background: rgba(10, 16, 32, 0.9) !important;
  color: #f8fafc !important;
  font-size: 0.95rem;
}

.edit-player-page :deep(.profile-card-body input:focus),
.edit-player-page :deep(.profile-card-body select:focus) {
  outline: none;
  border-color: rgba(192, 0, 0, 0.65) !important;
  box-shadow: 0 0 0 2px rgba(192, 0, 0, 0.12);
}

.edit-player-page :deep(.image-input-label) {
  color: #ffffff !important;
  font-weight: 900 !important;
}

.edit-player-page :deep(.image-edit-btn) {
  background: #002060 !important;
}

.edit-player-page :deep(.image-preview-panel) {
  background: rgba(10, 16, 32, 0.95) !important;
  border-color: rgba(255, 255, 255, 0.22) !important;
}

.edit-player-page :deep(.profile-card-body .input-tel-decorator) {
  height: calc(2.7rem - 2px);
}

@media (min-width: 768px) {
  .profile-card-header {
    padding: 0.9rem 1.6rem;
  }

  .profile-card-body {
    padding: 1.4rem 1.6rem 1.6rem;
  }

  .top-grid {
    grid-template-columns: 1fr 1.4fr;
    align-items: start;
  }

  .height-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .player-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .types-grid {
    grid-template-columns: repeat(9, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .profile-card-title {
    font-size: 1.35rem;
  }
}
</style>
