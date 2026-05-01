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
    "AB",
    "PA",
    "H",
    "TOTAL-BASES",
    "B1",
    "B2",
    "B3",
    "HR",
    "k",
    "BB",
    "HBP",
    "BA",
    "OBP",
    "SLG",
    "OPS"
  ],
  team: [
    "AB",
    "PA",
    "H",
    "TOTAL-BASES",
    "B1",
    "B2",
    "B3",
    "HR",
    "k",
    "BB",
    "HBP",
    "BA",
    "OBP",
    "SLG",
    "OPS"
  ]
})

const tableHeadings = [
  "Hitter", "AB", "PA", "H", "Total Bases	", "1B",
  "2B", "3B", "HR", "K", "BB", "HBP", "BA",
  "OBP", "SLG", "OPS"
]
</script>
<template>
  <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      LiveAb Session - Hitter - Basic
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
