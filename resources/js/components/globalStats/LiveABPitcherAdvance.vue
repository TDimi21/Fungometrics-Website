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
    "Hits",
    "P/BF",
    "<=3 %",
    "FPS%",
    "FPSo%",
    "FPSw%",
    "FPSh%",
    "SM%",
    "K/BF%",
    "Weak%",
    "BABIP",
    "LD%",
    "GB%",
    "Fly%"
  ],
  team: [
    "TOTAL_PITCHES",
    "Hits",
    "P/BF",
    "<=3 %",
    "FPS%",
    "FPSo%",
    "FPSw%",
    "FPSh%",
    "SM%",
    "K/BF%",
    "Weak%",
    "BABIP",
    "LD%",
    "GB%",
    "Fly%"
  ]
})

const tableHeadings = [
  "player", "swing", "hits", "p/bf", "<=3%", "fps%","fpso%",
  "fpsw%", "FPSh%", "SM%", "K/BF%", "Weak%", "babip", "ld%",
  "gb%", "fly%"
]
</script>
<template>
  <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      LiveAB Session - PITCHER - ADVANCE
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
