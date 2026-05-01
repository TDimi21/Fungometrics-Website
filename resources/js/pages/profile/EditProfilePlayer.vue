<script setup>
import Layout from '@/layout/Layout.vue'
import { LabelField, InputBase, InutTel, InputImage } from "../../components/form";
import { ArrowRightIcon } from '@/components/icons'
import { playerTypes }  from '../../utils'
import {reactive} from 'vue'
import { useUserStore } from '@/store/user'
import Loader from "@/components/Loader.vue";
import {useRouter} from "vue-router"
import {states} from '../../utils'
import {toast} from "../../utils/AlertPlugin";

const {userData} = useUserStore();
const isLoading = reactive({status: true})
const router = useRouter()
const token = JSON.parse(localStorage.getItem('auth')).token
const api_url = process.env.API_ENDPOINT;
let player = reactive({
  type: [],
  heightFt: userData.ft ?? 0,
  heightInch: userData.inch ?? 0,
  born: userData.born.date ?? '',
  shirt_number: userData.shirt_number,
  firstName: userData.name.first,
  lastName: userData.name.last,
  email: userData.email,
  mobileNumber: userData.phone,
  avatar: userData.avatar ?? '../../assets/img/layout/logofungo-nav.png',
  sides: {
    pitch: userData.throw_side,
    hit: userData.hit_side
  },
  weight: userData.weight ?? 0,
})

userData.positions.forEach(types => {
  player.type.push(types.position)
})

const submitUpdate = async () => {
  if(player.firstName == "" || player.lastName == "" || player.born == "" || player.type.length == 0 ||
    player.mobileNumber == "" || player.shirt_number == "" || player.heightFt == "" ||
    player.weight == "" || player.sides.pitch == "" || player.sides.hit == "" ){

    await toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You must complete all the fields of player',
    })
  }else{
    isLoading.status =!isLoading.status;
    let dataForm = new FormData();
    dataForm.append('email', player.email)
    dataForm.append('phone', player.mobileNumber)
    if(player.avatar instanceof File){
      dataForm.append('picture', player.avatar)
    }else{
      dataForm.append('picture', userData.avatar ?? '')
    }

    dataForm.append('profile[name][first]', player.firstName)
    dataForm.append('profile[name][last]', player.lastName)
    dataForm.append('player[born]', player.born)
    dataForm.append('player[ft]', player.heightFt)
    dataForm.append('player[inch]', player.heightInch == "0" || player.heightInch == "" ? 0 : player.heightInch)
    dataForm.append('player[shirt]', player.shirt_number)
    dataForm.append('player[sides][pitch]', player.sides.pitch)
    dataForm.append('player[sides][hit]', player.sides.hit)
    dataForm.append('player[weight]', player.weight)
    player.type.forEach(function (item,key){
      dataForm.append(`positions[${key}][position]`, item)
    });
    const api_url = process.env.API_ENDPOINT;
    const config = {
      headers: { Authorization: `Bearer ${token}` }
    };

    await axios.post(api_url+'edit/players/'+userData.id, dataForm, config).then(async function (response) {
      if(response){
        console.log(response.data.data);
        const data = response.data.data
        userData.name.first = data.profile.first_name
        userData.name.last = data.profile.last_name
        userData.name.full = `${data.profile.first_name} ${data.profile.last_name}`
        userData.avatar = data.profile.picture,
        userData.phone = data.phone
        userData.born.date = data.player.born_date
        userData.city = data.profile.city
        userData.state = data.profile.state
        userData.hit_side = data.player.hit_side
        userData.shirt_number = data.player.number_in_shirt
        userData.positions = data.positions
        userData.throw_side = data.player.throw_side
        userData.weight = data.player.weight
        userData.zip = data.profile.zip
        userData.inch = data.player.height_in_inch
        userData.ft = data.player.height_in_ft
        console.log(userData);
        isLoading.status =!isLoading.status;
        toast.fire({
          icon: 'success',
          title: 'Player information update',
          text: response.data.message,
        })

        await router.replace("/player-dashboard")
      }
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
          isLoading.status =!isLoading.status;
          await toast.fire({
            icon: 'warning',
            title: 'Player Warning !!!',
            text: errorMessage,
          })
      } else {
        isLoading.status =!isLoading.status;
        console.log(error.response.data.message);
        toast.fire({
          icon: 'error',
          title: 'Player Error !!!',
          text: "strike 3 is out, have a internal problem, " +error.response.data.message,
        })
      }
    })
  }
}

const typeClicked = (type) => {
  if (player.type.includes(type)) {
    player.type = player.type.filter((key) => key !== type )
  } else {
    player.type.push(type)
  }
}
</script>

<template>
  <Layout>
    <Loader v-show="!isLoading.status"/>
      <h1 class="text-fungo-red text-2xl md:text-[40px] text-center pt-9 mb-6 font-fungo-700">Edit Profile Player</h1>
      <section class="w-full min-h-[1050px] md:min-h-[750px] mt-[5%] lg:mt-[5]">
        <form class="w-full h-full" @submit.prevent="submitUpdate">
          <div class="form-header">
            <div class="flex flex-col w-2/4 md:w-1/4">
              <InputImage label="Picture" v-model="player.avatar" inputClasses="h-52"/>
            </div>
            <div class="flex flex-col w-4/5 md:w-2/5 mt-5 md:mt-0 ml-0 md:ml-11">
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
                  <InputBase v-model="player.heightFt" :inputType="'number'" inputClasses="w-10/12" />
                </div>
                <div class="flex flex-row justify-between mt-6">
                  <LabelField text="inch"/>
                  <InputBase v-model="player.heightInch" :inputType="'number'" inputClasses="w-10/12" />
                </div>
              </div>
              <div class="flex flex-col mt-4">
                <LabelField text="Weight of player *"/>
                <div class="flex flex-row justify-between mt-4">
                  <LabelField text="lb"/>
                  <InputBase v-model="player.weight" :inputType="'number'" inputClasses="w-10/12" />
                </div>
              </div>
            </div>
          </div>
          <div class="form-body">
            <div class="flex flex-col md:flex-row justify-between">
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
            <div class="flex flex-col md:flex-row justify-between">
              <div class="box-input-col">
                <LabelField text="E-Mail address" :required="true"/>
                <InputBase v-model="player.email" inputType="email" :enableInput="true"/>
              </div>
              <div class="box-input-col">
                <LabelField text="Mobile number" :required="true"/>
                <InutTel v-model="player.mobileNumber"/>
              </div>
              <div class="box-input-col-2">
                <LabelField text="Number of shirt" :required="true"/>
                <InputBase v-model="player.shirt_number" :inputType="'number'"/>
              </div>
            </div>
            <div class="flex flex-col md:flex-row justify-around">
              <div class="box-input-col">
                <LabelField text="Throws L/R" :required="true"/>
                <InputBase v-model="player.sides.pitch"/>
              </div>
              <div class="box-input-col">
                <LabelField text="Hits L/R/S" :required="true"/>
                <InputBase v-model="player.sides.hit"/>
              </div>
            </div>
            <div class="flex flex-row justify-center lg:justify-end mt-[3%]">
              <button class="btn-edit-profile rounded-button-right" type="submit" @click="submitEditPlayer">
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
<style lang="css" scoped>

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
  @apply flex flex-col w-[100%] md:w-[31%] text-fungo-darkblue mb-5;
}

.box-input-col-2 {
  @apply flex flex-col w-[100%] md:w-[31%] text-black mb-5;
}

.form-header {
  @apply bg-[#F7F8F9] h-[100%] md:h-[43%] flex flex-col md:flex-row justify-center items-center py-6;
}
.form-body {
  @apply bg-[#E7EAEE] h-[57%] px-20 py-12 2xl:px-28 2xl:pt-10 2xl:pb-20 flex flex-col justify-between;
}
.btn-type-player {
  @apply rounded-md bg-white border-[1px] border-black py-2 w-10 h-10 ml-1 cursor-pointer text-fungo-darkblue;
}
.active-button {
  background-color: #E10600!important;
  color: white;
  border-color: #E10600!important;
}

::-webkit-scrollbar {
  width: 4px;
  height: 4px;
}
::-webkit-scrollbar-button {
  width: 0px;
  height: 0px;
}
::-webkit-scrollbar-thumb {
  background: #e41111;
  border: 0px none #ffffff;
  border-radius: 5px;
}
::-webkit-scrollbar-thumb:hover {
  background: #ffffff;
}
::-webkit-scrollbar-thumb:active {
  background: #000000;
}
::-webkit-scrollbar-track {
  background: #666666;
  border: 22px solid #918383;
  border-radius: 4px;
}
::-webkit-scrollbar-track:hover {
  background: #e41111;
}
::-webkit-scrollbar-track:active {
  background: #333333;
}
::-webkit-scrollbar-corner {
  background: transparent;
}
</style>
