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
    "pitches",
    "GB",
    "LD",
    "FLY",
    "FOUL",
    "S/M",
    "TAKE"
  ],
  team: [
    "pitches",
    "GB",
    "LD",
    "FLY",
    "FOUL",
    "S/M",
    "TAKE"
  ]
})

const tableHeadings = [
  "player", "Pitches", "GB", "LD", "Fly", "PF", "Swing/Miss", "Take"
]
</script>
<template>
  <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      Pitching Session - Other - Percentages
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
