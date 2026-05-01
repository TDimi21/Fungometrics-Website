<script setup>
import Player from './Player.vue';
import Coach from './Coach.vue';
import { useRoute, useRouter } from 'vue-router';
import { onMounted, reactive, ref } from 'vue';
import { useAxiosAuth } from '@/composables/axios-auth.js'
import Loader from "../../components/Loader.vue";
import {toast} from "../../utils/AlertPlugin";
const isLoading = reactive({status: false})

const { axiosGet, axiosPost } = useAxiosAuth();

const route = useRoute();
const router = useRouter();

const id = route.params.id;
const user = ref(null);

onMounted(async function () {

  isLoading.status = true;

  const { data } = await axiosGet(`complete/${id}`)

  user.value = data.data.user

  isLoading.status = false;
});

const updatePlayer = async (formData) => {
  const {data} = await axiosPost(`complete/${user.value.id}/player`, formData);
  console.log(data);
  toast.fire({
    icon: 'success',
    title: 'Player Register',
    text: data.data.message,
  })
  await router.replace("/login/player")
}


const updateCoach = async (formData) => {
  isLoading.status = true;
  const {data} = await axiosPost(`complete/${user.value.id}/coach`, formData);
  console.log(data);
  toast.fire({
        icon: 'success',
        title: 'Coach Register',
        text: data.data.message,
  })
  await router.replace("/login/coach")
}
</script>

<template>
  <Loader v-show="isLoading.status"/>

  <div v-if="user">
    <Player v-if="user.type === 'player'" :user="user" @update="updatePlayer"/>
    <Coach v-else :user="user" @update="updateCoach"/>
  </div>
</template>
