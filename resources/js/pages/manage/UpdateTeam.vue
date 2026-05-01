<script setup>
import { useRouter, useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { InputImage, InputBase, LabelField, SelectField} from '@/components/form'
import { ArrowRightIcon } from '@/components/icons'
import { reactive, onMounted, ref } from 'vue'
import {states} from '../../utils'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { toast } from "@/utils/AlertPlugin"
import { useTeamStore } from '@/store/team.js'
import Loader from '@/components/Loader.vue'

const { axiosPost } = useAxiosAuth()
const teamStore = useTeamStore()
const router = useRouter()
const route = useRoute()

let team = ''
const isLoading = ref(false)

const { teams } = storeToRefs(teamStore)

if (route.params.id != '') {
  team = teams.value.find(team => team.id == route.params.id)
}
const { updateTeams } = teamStore

const coach = reactive({
  name: team.name,
  zip: team.zip,
  state: team.state,
  logo: team != undefined ? team.logo : ''
});

const sendData = async() => {
  const fileTemp = coach.logo.files[0]

  let dataForm = new FormData();
  dataForm.append('name', coach.name)
  dataForm.append('zip', coach.zip)
  dataForm.append('state', coach.state)
  dataForm.append('logo', fileTemp)

  try {
    isLoading.value = !isLoading.value

    if(coach.logo.files[0] == null){
      dataForm.delete('logo')
      dataForm.append('logo', team.logo)
    }
    await axiosPost(`coach/edit/teams/${team.id}`, dataForm).then(async(response) => {
      if (response.data.status == "success") {
        updateTeams(response.data.data)
        toast.fire({
          icon: 'success',
          title: 'Team updated',
          text: 'Team update succefully'
        })
        router.push('/manage')
      }
    })

  } catch (error) {
    isLoading.value = !isLoading.value
    let errorsMssg = ''
    Object.values(error.response.data.data.errors).forEach((error) => {
      errorsMssg = errorsMssg + error[0]
    });
    await toast.fire({
      icon: 'warning',
      title: 'Failed to create team',
      text: errorsMssg
    })
  } finally {
    isLoading.value != isLoading.value
  }

}

</script>

<template>
  <Loader v-show="isLoading"/>
    <Layout class="debug">
      <div class="flex flex-row w-[100%] items-center px-4 mt-9">
        <!-- <p>{{ route.params }}</p>         -->
        <h1 class="text-fungo-red w-[100%] text-2xl md:text-[50px] font-fungo-700 my-5 text-center">
          {{ route.params.id ? 'Update' : 'Create New' }} Team
        </h1>
        <RouterLink to="/manage" class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]">
          <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
        </RouterLink>
      </div>
      <section class="bg-fungo-gray4 w-full h-auto absolute left-0 px-[10%] md:px-[8%] mt-[150px] lg:mt-[80px]">
        <div class="w-[100%] px-4 my-9">
          <h1 class="text-fungo-darkblue text-2xl md:text-[40px] font-fungo-700 my-5 text-center">Team</h1>
          <div class="flex flex-col justify-center items-center">
            <div class="flex flex-col w-1/4">
              <InputImage v-model="coach.logo" label="Team logo"/>
            </div>
          </div>
          <div class="form-header mt-11 w-[100%] flex justify-between flex-col lg:flex-row">
            <div class="box-input-col">
              <LabelField :required="true" class="text-fungo-darkblue mt-8 mb-5" text="Team Name"/>
              <InputBase v-model="coach.name"/>
            </div>
            <div class="box-input-col w-[100%] lg:w-1/4">
              <LabelField :required="true" class="text-fungo-darkblue mb-5" text="State"/>
              <SelectField v-model="coach.state" :options="states"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="text-fungo-darkblue mb-5" text="Zip code"/>
              <InputBase v-model="coach.zip" inputType="number"/>
            </div>
          </div>
          <div class="w-[100%] flex justify-center px-4 my-9">
            <button
              @click="sendData"
              class="grid place-items-center grid-flow-col flex-row rounded-button-right w-[250px] lg:w-[300px]
              px-2 py-1 text-xl md:text-[16px] lg:text-[20px] bg-fungo-red text-white hover:bg-fungo-red-hover" type="submit">
              <img alt="button register coach" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0" src="../../assets/img/login/assteslogin/ballbutton.svg">
              <span class="mx-2">
                {{ route.params.id ? 'Update' : 'Create' }}
              </span>
              <div class="text-white mx-2 animate-bounce-r">
                <ArrowRightIcon color="ffffff" w="50" h="50"/>
              </div>
            </button>
          </div>

        </div>
      </section>
    </Layout>
</template>

<style scoped>
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

@keyframes bouncel {
  0%, 100% {
    transform: translateX(25%);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: none;
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
}

.animate-bounce-l {
  animation: bouncel 1s infinite;
}

.rounded-button-right {
  border-radius: 30px 10px 10px 30px;
}

.rounded-button-left {
  border-radius: 10px 30px 30px 10px;
}

</style>
