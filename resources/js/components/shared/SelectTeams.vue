<script setup>
import { useTeamStore } from "@/store/team";

const { team } = useTeamStore();

const props = defineProps({
  teams: {
    required: true
  },
})

</script>
<template>

  <div class="rounded-xl bg-white h-min w-min p-[1px]">
    <div class="container">
      <div class="text-fungo-red font-fungo-700 ml-5">Select team</div>

      <div v-for="(element, index) in props.teams ">
        <div class="border border-fungo-gray3 bg-fungo-gray4 rounded grid grid-cols-3 w-[90%] mx-auto my-2">
          <div class="grid place-items-center p-2">
            <img :src="element.logo" alt="team logo" class="w-fit h-fit">
          </div>
          <div class=" grid-flow-row text-fungo-darkblue font-fungo-700 text-[12px]">
            <div class="text-fungo-blue2 mt-3">Team</div>
            <div class="">{{ element.name }}</div>
          </div>
          <div class="grid place-items-center">
            <!-- <input type="radio" :value="props.teamItem.id" :checked="team.id === props.teamItem.id" name="team-select"> -->
            <input v-on:click="$emit('select', element.id)" type="radio" :value="element.id" :checked="team.id === element.id" name="team-select">
          </div>
        </div>
      </div>
    </div>

  </div>

</template>

<style lang="css" scoped>
.container {
  @apply max-h-[150px] min-h-[150px] md:max-h-[300px] md:min-h-[300px] md:w-[300px] grid overflow-y-auto py-2 my-2 xl:w-[500px] overflow-x-hidden;
}

::-webkit-scrollbar {
  width: 6px;
  height: 2px;
}
::-webkit-scrollbar-button{
  height: 10px;
}

::-webkit-scrollbar-thumb {
  background: #e41111;
  border: 0px none #ffffff;
  border-radius: 5px;
}

::-webkit-scrollbar-track {
  border: 7px solid #fff;
  border-radius: 4px;
}

::-webkit-scrollbar-track:hover {
  background: #e41111;
}

::-webkit-scrollbar-corner {
  background: transparent;
}
</style>
