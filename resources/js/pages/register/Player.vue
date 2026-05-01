<script setup>
import { reactive,ref } from 'vue'
import {useRouter} from "vue-router"

import { playerTypes }  from '../../utils'
import BannerLeftRegister from "../../components/register/BannerLeftRegister.vue";
import { LabelField, BigButtonField, InputBase, PasswordField, InutTel, InputImage } from "../../components/form";
import {useUserStore} from "../../store/user"
import {useAuthStore} from "../../store/auth";
import BannerPlayer from "../../assets/img/register/banner-player.jpg";
import Loader from "../../components/Loader.vue";
import axios from "axios";
import {toast} from "../../utils/AlertPlugin";


const {isLogged,setToken} = useAuthStore();
const props = defineProps({
  user: Object
})
const emits = defineEmits(['update']);

const imgPath = BannerPlayer;
let isLoading = reactive({status:true});
const userStore = useUserStore();
let player = reactive({
  type: [],
  heightFt: 0,
  heightInch: 0,
  weight: 0,
  firstName: props.user?.profile?.first_name,
  lastName: props.user?.profile?.last_name,
  born: '',
  email: '',
  password: '',
  confirmPassword: '',
  mobileNumber: props.user?.phone,
  number_in_shirt: '',
})
const router = useRouter()




const submitPlayer = async () => {
  if (player.password != player.confirmPassword) {
    toast.fire({
      icon: 'warning',
      title: 'Validation',
      text: 'The passwords are not same',
    })
  } else {
    let playerPosition =[];
    isLoading.status =!isLoading.status;
    const imageTemp = player.avatar.files[0] ;
    let dataForm = new FormData();
    dataForm.append('email', player.email.toLowerCase())
    dataForm.append('password', player.password)
    dataForm.append('phone', player.mobileNumber)
    dataForm.append('picture', imageTemp)
    dataForm.append('profile[name][first]', player.firstName)
    dataForm.append('profile[name][last]', player.lastName)
    dataForm.append('player[born]', player.born)
    dataForm.append('player[ft]', player.heightFt)
    dataForm.append('player[inch]', player.heightInch)
    dataForm.append('player[shirt]', player.number_in_shirt)
    // dataForm.append('player[weight]', player.weight)
    player.type.forEach(function (item,key){
      dataForm.append(`positions[${key}][position]`, item)
    });

    if(props.user)
    {
      isLoading.status = false;
      emits('update', dataForm);
      return ;
    }

    const api_url = process.env.API_ENDPOINT;
    await axios.post(api_url+'player/register', dataForm
    ).then(async function (response) {
      // await userStore.setData(response.data.data.user);
      // setToken(response.data.data.token);
      // isLogged.status = !isLogged.status;
      toast.fire({
        icon: 'success',
        title: 'Player Register',
        text: response.data.message,
      })

      isLoading.status =!isLoading.status;
      await router.replace("/login/player")

    }).catch(async function (error){
      console.log('err', error.response.data.data);
      let errors = []

      Object.values(error.response.data.data.errors).forEach((item) => {
        errors.push('\n' + item[0])
      })

      if (error.response.data.code === '001V' || error.response.status === 422 ) {
        toast.fire({
          icon: 'warning',
          title: 'Player Warning !!!',
          text: errors,
        })
      } else {
        toast.fire({
          icon: 'error',
          title: 'Player Error !!!',
          text: "strike 3 is out, have a internal problem, " +error.response.data.message,
        })
      }
      isLoading.status =!isLoading.status;
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
  <Loader v-show="!isLoading.status"/>
  <div class="flex flex-row h-screen flex-nowrap">
    <BannerLeftRegister :backgroundImage="imgPath" title="Player" />

    <section class="w-full lg:w-[65%]" >

      <RouterLink to="/"
                  class="absolute right-6 top-6"><img src="@/assets/img/register/cancel.svg" alt="Icon close view"></RouterLink>

      <form class="w-full h-full" @submit.prevent="submitPlayer">

        <!-- header form -->
        <div class="form-header">
          <div class="flex flex-col w-1/2 lg:w-1/4">
            <InputImage label="Picture" v-model="player.avatar" inputClasses="h-52"/>
          </div>
          <div class="flex flex-col w-[90%] lg:w-2/5 lg:ml-11">

            <!-- type player -->
            <div class="flex flex-col">
              <LabelField text="Type of player" :required="true" class="mt-8 mb-5 text-fungo-darkblue"/>
              <div class="flex flex-row justify-between">
                <input v-for="(type) in playerTypes" type="button" :value="type" @click="typeClicked(type)"
                  class="btn-type-player" :class="{'active-button' : player.type.includes(type) }">
              </div>
            </div>

            <!-- player height -->
            <div class="flex flex-col mt-4">
              <LabelField text="Height of player (ft and inch*)" class="mt-8 mb-5 text-fungo-darkblue"/>
              <div class="flex flex-row justify-between mt-4">
                <LabelField text="ft" class="mb-5 text-fungo-darkblue"/>
                <InputBase v-model="player.heightFt" :inputType="'number'" inputClasses="w-10/12" />
              </div>
              <div class="flex flex-row justify-between mt-6">
                <LabelField text="inch" class="mb-5 text-fungo-darkblue"/>
                <InputBase v-model="player.heightInch" :inputType="'number'" inputClasses="w-10/12" />
              </div>
            </div>
            <!-- <div class="flex flex-col mt-4">
                <LabelField text="Weight of player " :required="true"/>
                <div class="flex flex-row justify-between mt-4">
                  <LabelField text="lb"/>
                  <InputBase v-model="player.weight" :inputType="'number'" inputClasses="w-10/12" />
                </div>
              </div> -->
          </div>
        </div>
        <!-- end header form -->

        <!-- body form -->
        <div class="form-body">

          <!-- first row -->
          <div class="flex flex-col justify-between lg:flex-row">
            <div class="box-input-col">
              <LabelField text="First name" :required="true" class="mb-5 text-fungo-darkblue"/>
              <InputBase v-model="player.firstName" />
            </div>
            <div class="box-input-col">
              <LabelField text="Last name" :required="true" class="mb-5 text-fungo-darkblue"/>
              <InputBase v-model="player.lastName" />
            </div>
            <div class="box-input-col">
              <LabelField text="Born" :required="true" class="mb-5 text-fungo-darkblue"/>
              <InputBase v-model="player.born" inputType="date"/>
            </div>
          </div>

          <!-- second row -->
          <div class="flex flex-col justify-between lg:flex-row">
            <div class="box-input-col">
              <LabelField text="E-Mail address" :required="true" class="mb-5 text-fungo-darkblue"/>
              <InputBase v-model="player.email" inputType="email"/>
            </div>
            <div class="box-input-col">
              <LabelField text="Password" :required="true" class="mb-5 text-fungo-darkblue"/>
              <PasswordField v-model="player.password"/>
            </div>
            <div class="box-input-col">
              <LabelField text="Confirm password" :required="true" class="mb-5 text-fungo-darkblue"/>
              <PasswordField v-model="player.confirmPassword"/>
            </div>
          </div>

          <!-- third row -->
          <div class="flex flex-col justify-between lg:flex-row">
            <div class="box-input-col">
              <LabelField text="Mobile number" :required="true" class="mb-5 text-fungo-darkblue"/>
              <InutTel v-model="player.mobileNumber"/>
            </div>
            <div class="box-input-col">
              <LabelField text="Number of shirt" :required="true"/>
              <InputBase v-model="player.number_in_shirt" :inputType="'number'"/>
            </div>
          </div>

          <!-- fourth row -->
          <div class="flex flex-col justify-end mt-4 lg:flex-row lg:mt-0">
            <div class="justify-end box-input-col">
              <BigButtonField label="Register" type="submit" color="red" />
            </div>
          </div>
        </div>
        <!-- end body form -->
      </form>
    </section>
  </div>
</template>

<style scoped>
.box-input-col {
  @apply flex flex-col w-full lg:w-[31%];
}

.form-header {
  @apply bg-[#F7F8F9] h-[80%] md:h-[54%] lg:h-[43%] flex flex-col lg:flex-row justify-center items-center;
}
.form-body {
  @apply bg-[#E7EAEE] h-auto lg:h-[57%] px-20 py-12 2xl:px-28 2xl:pt-10 2xl:pb-20 flex flex-col justify-between;
}
.btn-type-player {
  @apply rounded-md bg-white border-[1px] border-black py-2 w-10 h-10 ml-1 cursor-pointer text-fungo-darkblue;
}
.active-button {
  background-color: #E10600!important;
  color: white;
  border-color: #E10600!important;
}
</style>
