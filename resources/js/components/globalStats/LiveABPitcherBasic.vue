<script setup>
import { ref } from 'vue'
import TableSesionsTotal from './reusables/DataTableStadistics.vue'

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
    "player",
    "TOTAL_PITCHES",
    "BF",
    "BALL",
    "STRIKES",
    "STRIKES %",
    "FB",
    "FB %",
    "CH",
    "CH %",
    "CB",
    "CB %",
    "SL",
    "SL %",
    "OTHER",
    "OTHER %"
  ],
  team: [
    "TOTAL_PITCHES",
    "BF",
    "BALL",
    "STRIKES",
    "STRIKES %",
    "FB",
    "FB %",
    "CH",
    "CH %",
    "CB",
    "CB %",
    "SL",
    "SL %",
    "OTHER",
    "OTHER %"
  ]
})

const tableHeadings = [
  "PITCHER", "Total Pitches", "BF", "Ball", "Strike", "Strike%",
  "FB", "FBs%", "CH", "CHs%", "CB", "CBs%", "SL", "SLs%", "OTHER", "OTHER%"
]
</script>
<template>
  <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      LiveAB Session - PITCHER - BASIC
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
