<script setup>
import Layout from '@/layout/Layout.vue'
import {
  SelectField,
  InputBase,
  InputImage,
  InutTel,
  PasswordField,
  LabelField,
  BigButtonField
} from '@/components/form'
import { ArrowDownIcon, ArrowRightIcon } from '@/components/icons'
import { reactive, ref } from 'vue'
import { useUserStore } from '@/store/user'
import {useTeamStore} from "@/store/team";
import {usePlayerStore} from "../../store/players";
import Loader from "@/components/Loader.vue";
import {useRouter, useRoute} from "vue-router"
import { playerTypes }  from '../../utils'
import {toast} from "../../utils/AlertPlugin";

const {team, teams} = useTeamStore();
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
      // player.value.type = item.positions

      item.positions.forEach(types => {
        player.value.type.push(types.position)
      })
  }
}

const typeClicked = (type) => {
  if (player.value.type.includes(type)) {
    player.value.type = player.value.type.filter((key) => key !== type )
  } else {
    player.value.type.push(type)
  }
}

const submitUpdate = async () => {
  let playerPosition =[];
  isLoading.status =!isLoading.status;
  const imageTemp = player.value.avatar.files[0]
  let dataForm = new FormData();
  dataForm.append('email', player.value.email)
  dataForm.append('phone', player.value.mobileNumber)
  if(imageTemp == undefined){
    dataForm.append('picture', "https://fungometrics.s3.amazonaws.com/logo.png")
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
  player.value.type.forEach(function (item,key){
    dataForm.append(`positions[${key}][position]`, item)
  });
  const api_url = process.env.API_ENDPOINT;
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
    <div class="flex flex-row w-[100%] items-center px-4 my-9">
      <h1 class="text-fungo-red w-[100%] text-2xl md:text-[50px] font-fungo-700 my-5 text-center">Edit Profile Player</h1>
      <RouterLink to="/roster" class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]">
        <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
      </RouterLink>
    </div>
    <section class="bg-fungo-gray2 absolute left-0 w-full h-auto">
      <form class="w-full h-full" @submit.prevent="submitUpdate">
        <div class="form-header">
          <div class="flex flex-col w-1/4">
            <InputImage label="Picture" v-model="player.avatar" inputClasses="h-52"/>
          </div>
          <div class="flex flex-col w-2/5 ml-11">
            <div class="flex flex-col">
              <LabelField text="Type of player" :required="true"/>
              <div class="flex flex-row justify-between">
                <input v-for="(type) in playerTypes" type="button" :value="type" @click="typeClicked(type)"
                class="btn-type-player" :class="{'active-button' : player.type.includes(type) }">
              </div>
            </div>
            <div class="flex flex-col mt-4">
              <LabelField text="Height of player (ft and inch*)"/>
              <div class="flex flex-row justify-between mt-4">
                <LabelField text="ft"/>
                <InputBase v-model="player.heightFt" inputClasses="w-10/12" />
              </div>
              <div class="flex flex-row justify-between mt-6">
                <LabelField text="inch"/>
                <InputBase v-model="player.heightInch" inputClasses="w-10/12" />
              </div>
              <!-- <div class="flex flex-row justify-between mt-6">
                <LabelField text="wt [lb]"/>
                <InputBase v-model="player.weigth" inputClasses="w-10/12" />
              </div> -->
            </div>
          </div>
        </div>
        <div class="form-body">
          <div class="flex flex-row justify-between">
            <div class="box-input-col">
              <LabelField text="First name" :required="true"/>
              <InputBase v-model="player.firstName" />
            </div>
            <div class="box-input-col">
              <LabelField text="Last name" :required="true"/>
              <InputBase v-model="player.lastName" />
            </div>
            <div class="box-input-col">
              <LabelField text="Born" :required="true"/>
              <InputBase v-model="player.born" inputType="date"/>
            </div>
          </div>
          <div class="flex flex-row justify-between">
            <div class="box-input-col">
              <LabelField text="E-Mail address" :required="true"/>
              <InputBase v-model="player.email" inputType="email"/>
            </div>
            <div class="box-input-col">
              <LabelField text="Mobile number" :required="true"/>
              <InutTel v-model="player.mobileNumber" />
            </div>
            <div class="box-input-col">
              <LabelField :required="true" text="Current team"/>
              <div class="relative w-full">
                <select class="bg-white h-10 appearance-none bg-none w-full" v-model="player.currentTeam" style="z-index: 9" disabled>
                  <!-- <option class="text-fungo-darkblue" v-for="(item, index) in teams" :value="item.name">{{ item.name }}</option> -->
                  <option selected class="text-fungo-darkblue" :value="team.name">{{ team.name }}</option>
                </select>
                <div class="arrow-position"> <ArrowDownIcon color="26364D"/> </div>
              </div>
            </div>
          </div>
          <div class="box-input-col">
            <LabelField text="Number of shirt" :required="true"/>
            <InputBase v-model="player.number_in_shirt" inputType="text"/>
          </div>
          <!-- <div class="flex flex-row justify-between">
            <div class="box-input-col">
              <LabelField text="Mobile number" :required="true"/>
              <InutTel v-model="player.mobileNumber" />
            </div>
            <div class="box-input-col3">
              <LabelField :required="true" text="Current team"/>
              <div class="relative w-full">
                <select class="bg-white h-10 appearance-none bg-none w-full" v-model="player.currentTeam" style="z-index: 9" disabled>
                  <option selected class="text-fungo-darkblue" :value="team.name">{{ team.name }}</option>
                </select>
                <div class="arrow-position"> <ArrowDownIcon color="26364D"/> </div>
              </div>
            </div>
          </div> -->
          <div class="w-[100%] flex justify-center px-4 my-8">
            <button class="btn-edit-profile rounded-button-right" type="submit">
              <img alt="button register coach" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0" src="../../assets/img/login/assteslogin/ballbutton.svg">
              <span class="mx-2">Update</span>
              <div class="text-white mx-2 animate-bounce-r"><ArrowRightIcon color="ffffff" w="50" h="50"/></div>
            </button>
          </div>
        </div>
      </form>
    </section>
  </Layout>
</template>

<style scoped>
.arrow-position{
  z-index: 0;
  position: absolute;
  top: 0;
  right: 0;
}

.active-button {
  background-color: #E10600!important;
  color: white !important;
  border-color: #E10600!important;
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
    px-2 py-1 text-xl md:text-[16px] lg:text-[20px] bg-fungo-red text-white hover:bg-fungo-red-hover
}

.box-input-col {
  @apply flex flex-col w-full md:w-[31%];
}

.form-header {
  @apply bg-[#F7F8F9] h-[43%] flex flex-row justify-center items-center py-10;
}
.form-body {
  @apply bg-[#E7EAEE] h-[57%] px-20 py-12 2xl:px-28 2xl:pt-10 2xl:pb-20 flex flex-col justify-between space-y-4;
}
.btn-type-player {
  @apply rounded-md bg-white border-[1px] border-black py-2 w-10 h-10 ml-1 cursor-pointer text-fungo-darkblue;
}
</style>
