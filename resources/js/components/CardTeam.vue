<script setup>
import {useTeamStore} from "../store/team"
import {useAxiosAuth} from '@/composables/axios-auth.js'
import {toast} from "@/utils/AlertPlugin"
import {reactive} from "vue";

const props = defineProps({
  players: Object,
  team:Object,
  teamItem: {
    required: true
  },
  nameInput: { required: true}
})
const {axiosGet} = useAxiosAuth()
const isLoading = reactive({status: true})
const teamStore = useTeamStore();
const emit = defineEmits(['update:players','update:team'])
const changeTeam = async (event)=>{
  let teamId = event.target.value;
  try {
    isLoading.status = !isLoading.status;
    emit('update:team', props.teamItem)
    emit('update:players', [])
    await axiosGet('coach/teams/'+teamId).then(async (response) => {
      emit('update:team', props.teamItem)
      if(response){
        emit('update:players', response.data.data)
     }
    });
  } catch (error) {
    toast.fire({
      icon: 'error',
      title: 'Error get team',
      text: 'Sorry it is not get data of the team' +teamId,
    })

    console.log(error)
  }

}

</script>
<template>
  <div
    class="border border-fungo-gray3 bg-fungo-gray4 rounded grid grid-cols-3 w-[90%] mx-auto my-2">
    <div class="grid place-items-center p-2">
      <img :src="props.teamItem.logo" alt="team logo" class="w-fit h-fit">
    </div>
    <div class=" grid-flow-row text-fungo-darkblue font-fungo-700 text-[12px]">
      <div class="text-fungo-blue2 mt-3">Team</div>
      <div class="">{{ props.teamItem.name }}</div>
    </div>
    <div class="grid place-items-center">
      <input type="radio" :value="props.teamItem.id" :checked="teamStore.team.id === props.teamItem.id"
             :name="nameInput"
             @change="changeTeam($event)" />
    </div>
  </div>
</template>


