<script setup>
import { ref } from 'vue'
import TableSesionsTotal from './reusables/DataTableStadisticsCage.vue'

const props = defineProps({
  players: {
    type: Object,
    default: () => {}
  },
  team: {
    type: Object,
    default: () => {}
  },
})


const keys = ref({
  player: [
    'player',
    "swings",
    "GB",
    "LD",
    "FLY",
  ],
  team: [
    "swings",
    "GB",
    "LD",
    "FLY",
  ]
})
const tableHeadings = [
  "player", "Swings", "GB", "LD", "Fly"
]
</script>
<template>
  <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      Exit Velocity - Percentage Swings
    </h1>
    <TableSesionsTotal :data="{
        players: props.players,
        team: props.team
      }"
      :headings="tableHeadings"
      :keyTeam="keys.team"
      :keyPlayer="keys.player"
      />
  </div>
</template>
