<script setup>
import { ref } from 'vue';
import {BigButtonField, InputBase, LabelField, PasswordField, SwitchField} from '../../components/form';
import {useAxiosAuth} from "@/composables/axios-auth.js";
import { useRoute, useRouter } from 'vue-router'
import {toast} from "../../utils/AlertPlugin";


const { axiosPost } = useAxiosAuth()

const route = useRoute()
const router = useRouter();
console.log(route);

import loginCoachImg from '../../assets/img/login/bg-login-coach.jpg'
const imgPath = loginCoachImg;
const formData = ref({email: route.query.email, token: route.params.token, password: '', password_confirmation: ''})

console.log(formData.value);
const res = ref();

const submitForm = async () => {

  const { data } = await axiosPost('recover-password', formData.value);

  res.value = data;

  formData.value.password = '';
  formData.value.password_confirmation = '';
  if(res.value.status !== 'error'){
    toast.fire({
        icon: 'success',
        title: res.value.message,
      })
    router.replace('/');
  }
}
</script>

<template>
  <div :style="{ backgroundImage: `url('${ imgPath }')` }"
    class="grid h-screen bg-no-repeat bg-cover place-items-center">
    <div class="grid w-full h-full grid-flow-row bg-gradient place-items-center">
      <div class="">
      <RouterLink to="/">
        <div class="">
          <img class="mx-auto" alt="Fungo's logo" src="../../assets/img/login/assteslogin/logo-fungo.png">
        </div>
      </RouterLink>
        <h2 class="text-4xl text-white font-fungo-300 ">Reset password</h2>
        <!-- form section -->
        <span
          class="px-4 py-2 rounded"
          :class="{
            'bg-red-500 text-red-50': res.status === 'error',
            'bg-green-500 text-green-50': res.status !== 'error',
          }"
          v-if="res"
        >
          {{ res.message }}
        </span>

        <FormKit type="form" :actions="false" @submit="submitForm" >
          <div class="mt-5">

            <FormKit
              type="password"
              label="Password"
              validation="required|length:8"
              name="password"
              v-model="formData.password"
              validation-visibility="blur"
              input-class="$reset h-30 rounded appearance-none bg-transparent border border-fungo-lightblue text-white w-full"
            />
            <FormKit
              type="password"
              label="Password confirmation"
              name="password_confirm"
              validation="required|confirm"
              v-model="formData.password_confirmation"
              validation-visibility="blur"
              input-class="$reset h-30 rounded appearance-none bg-transparent border border-fungo-lightblue text-white w-full"
            />

            <div class="mt-10 md:scale-125">
              <BigButtonField color="red" label="Send recovery email link" type="submit"></BigButtonField>
            </div>
          </div>
        </FormKit>
        <!-- end form section -->
      </div>


    </div>
  </div>
</template>


<style scoped>
.bg-gradient {
  background: linear-gradient(180deg, rgba(8, 34, 71, 0.61) 0%, #082247 100%);
}
</style>
