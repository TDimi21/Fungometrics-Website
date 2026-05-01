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
    "AB",
    "PA",
    "H",
    "PA_BB",
    "BB_K",
    "C_PERCENTS",
    "XBH",
    "PS_PA",
    "2SAVG",
    "6+",
    "6+ %",
    "HARDHIT",
    "LDP",
    "GBP",
    "FLYP",
    "BABIP"
  ],
  team: [
    "AB",
    "PA",
    "H",
    "PA_BB",
    "BB_K",
    "C_PERCENTS",
    "XBH",
    "PS_PA",
    "2SAVG",
    "6+",
    "6+ %",
    "HARDHIT",
    "LDP",
    "GBP",
    "FLYP",
    "BABIP"
  ]
})

const tableHeadings = [
  "Hitter", "AB", "PA", "H", "PA/BB", "BB/K",
  "C%", "XBH", "Ps/PA", "2s AVG", "6+", "6+ %", "HardHit %",
  "LD%", "GB%", "FLY%", "BABIP%"
]
</script>
<template>
  <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      LiveAb Session - Hitter - Advanced
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
