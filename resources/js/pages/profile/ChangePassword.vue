<script setup>
import Layout from '@/layout/Layout.vue'
import {
  PasswordField,
  LabelField
} from '@/components/form'
import { storeToRefs } from 'pinia'
import { ArrowRightIcon } from '@/components/icons'
import {reactive} from 'vue'
import { useUserStore } from '@/store/user'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import Loader from "@/components/Loader.vue";
import {useRouter} from "vue-router"
import { useTeamStore } from '@/store/team.js'
import {states} from '../../utils'
import {toast} from "../../utils/AlertPlugin";

const useTeam = useTeamStore()
const {userData} = useUserStore();
const { team } = storeToRefs(useTeam)
const { axiosPost } = useAxiosAuth()
const isLoading = reactive({status: true})
const router = useRouter()
const token = JSON.parse(localStorage.getItem('auth')).token
const api_url = process.env.API_ENDPOINT;
const passwords = reactive({
  oldPassword: '',
  newPassword: '',
  confirmPassword: '',
});

const changePassword = async () => {
  isLoading.status =!isLoading.status;
  if (passwords.oldPassword.length >= 8 && passwords.newPassword.length >= 8 && passwords.confirmPassword.length >= 8) {
    if (passwords.newPassword.trim() === passwords.confirmPassword.trim()) {
      try {
        let dataForm = new FormData();
        dataForm.append('old', passwords.oldPassword.trim())
        dataForm.append('password', passwords.newPassword.trim())
        dataForm.append('password_confirmation', passwords.confirmPassword.trim())

        await axiosPost('password', dataForm).then(async(response) => {
          if (response.data.status == "success") {
            isLoading.status =!isLoading.status;
            await toast.fire({
              icon: 'success',
              title: 'Password updated',
              text: response.data.message ?? 'password change'
            })
            if (userData.type == 'player') {
              await router.replace("/player-dashboard")
            } else {
              await router.replace("/dashboard")
            }
          }
        })
      } catch (error) {
        if (error.response.data.code === '055-E') {
          isLoading.status =!isLoading.status;
          await toast.fire({
            icon: 'error',
            title: 'Error !!!',
            text: error.response.data.message ?? "the old password not same a new password",
          })
        } else if(error.response.status === 422 || error.response.data.code === '001V') {
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
            icon: 'error',
            title: 'Error password !!!',
            text: errorMessage,
          })
        } else {
          isLoading.status =!isLoading.status;
          await toast.fire({
            icon: 'error',
            title: 'Player Error !!!',
            text: "strike 3 is out, have a internal problem, " +error.response.data.message,
          })
        }
      }

    } else {
      isLoading.status =!isLoading.status;
      toast.fire({
        icon: 'error',
        title: 'Passwords are not same',
        text: 'The new passwords entered are not the same',
      })
    }
  } else {
    isLoading.status =!isLoading.status;
    if (passwords.oldPassword == "" || passwords.newPassword == "" || passwords.confirmPassword == "") {
      toast.fire({
        icon: 'warning',
        title: 'Incomplete fields',
        text: 'All field texts must be filled',
      })
    } else {
      toast.fire({
        icon: 'warning',
        title: 'Passwords length',
        text: 'Please, passwords must have at least 8 characters.',
      })
    }
  }
}

</script>

<template>
  <Layout>
    <Loader v-show="!isLoading.status"/>
    <!-- <h1 class="text-fungo-red text-2xl md:text-[40px] text-center mt-9 pt-5 mb-6 font-fungo-700">Change Password</h1> -->
    <div class="flex flex-row w-[100%] items-center px-4 mt-9 pt-5">
      <h1 class="text-fungo-red w-[100%] text-2xl md:text-[50px] font-fungo-700 my-5 text-center">
        Change Password
      </h1>
      <RouterLink v-if="!userData.type == 'player'" to="/profile" class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]">
        <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
      </RouterLink>
    </div>
    <section class="bg-fungo-gray2 w-full h-auto">
      <section class="bg-fungo-gray4 w-full h-auto absolute left-0 px-[10%] md:px-[1%] mt-10">
        <div class="w-full h-auto px-5 md:px-20 my-9 pt-[2%]">
          <div class="flex items-center justify-center w-full">
            <div v-if="userData.type == 'player'">
              <div class="w-[85px] md:h-[125px] md:w-[125px] rounded-full ring-[7px] ring-fungo-gray8">
                <template v-if="userData.avatar != null">
                  <img :src="userData.avatar" alt="" class="h-[85px] w-[85px] md:h-[125px] md:w-[125px] rounded-full">
                </template>
                <img v-else src="../../assets/img/layout/logofungo-nav.png" alt="" class="h-[85px] w-[85px] md:h-[125px] md:w-[125px] rounded-full">
              </div>
            </div>
            <div v-else>
              <div class="w-[85px] md:h-[125px] md:w-[125px] rounded-full ring-[7px] ring-fungo-gray8">
                <template v-if="team.logo != null">
                  <img :src="team.logo" alt="" class="h-[85px] w-[85px] md:h-[125px] md:w-[125px]">
                </template>
                <img v-else src="../../assets/img/layout/logofungo-nav.png" alt="" class="h-[85px] w-[85px] md:h-[125px] md:w-[125px] rounded-full">
              </div>
            </div>
          </div>
          <div class="mt-10 w-[100%] flex justify-between items-center flex-col">
            <div class="box-input-col">
              <LabelField text="Old password" :required="true"/>
              <PasswordField v-model="passwords.oldPassword"/>
            </div>
            <div class="box-input-col">
              <LabelField text="New password" :required="true"/>
              <PasswordField v-model="passwords.newPassword"/>
            </div>
            <div class="box-input-col">
              <LabelField text="Confirm password" :required="true"/>
              <PasswordField v-model="passwords.confirmPassword"/>
            </div>
          </div>
          <div class="w-[100%] flex justify-center px-4 my-12">
            <button class="btn-edit-profile rounded-button-right" type="submit" @click="changePassword">
              <img alt="button register coach" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0" src="../../assets/img/login/assteslogin/ballbutton.svg">
              <span class="mx-2">Update</span>
              <div class="text-white mx-2 animate-bounce-r"><ArrowRightIcon color="ffffff" w="50" h="50"/></div>
            </button>
          </div>
        </div>
      </section>
      <section class="w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[5%] mt-[140px] lg:mt-[80px]">

      </section>
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

.form-body {
  @apply bg-[#E7EAEE] h-[57%] px-1 md:px-20 py-12 2xl:px-28 2xl:pt-10 2xl:pb-20 flex flex-col w-full;
}

.box-input-col {
  @apply flex flex-col w-full md:w-[22%] my-2;
}

.avatar-Player{
  @apply w-[75px] max-w-[75px] lg:w-[110px] lg:max-w-[200px] h-[75px] lg:h-[100%] object-center object-fill mx-auto rounded-full border-8 my-10
}
</style>
