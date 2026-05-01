<script setup>
import {useTeamStore} from "../store/team";
const {team, teams} = useTeamStore();
const props = defineProps({
  teamItem: {
    required: true
  },
})

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
      <input type="radio" :value="props.teamItem.id">
    </div>
  </div>
</template>


