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
    "player",
    "swings",
    "< to -45",
    "-44 to -31",
    "-30 to -16",
    "-15 to -6",
    "-5 to 5",
    "6 to 15",
    "16 to 30",
    "31 to 44",
    "45 to <"
  ],
  team: [
    "swings",
    "< to -45",
    "-44 to -31",
    "-30 to -16",
    "-15 to -6",
    "-5 to 5",
    "6 to 15",
    "16 to 30",
    "31 to 44",
    "45 to <"
  ]
})

const tableHeadings = [
  "player", "Pitches", "<-45", "-44 to -31", "-30 to -16", "-15 to -6", "-5 to 5", "6 to 15", "16 to 30", "31 to 44", ">45"
]
</script>
<template>
  <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      Cage Session - Spray Angle - Totals
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
