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
    "throws",
    "No Hops",
    "1 Hop",
    "2 Hop",
    "3 Hop",
  ],
  team: [
    "throws",
    "No Hops",
    "1 Hop",
    "2 Hop",
    "3 Hop",
  ]
})

const tableHeadings = [
  "player", "Throws", "No Hops", "1 hop", "2 hops", "3 hops"
]
</script>
<template>
  <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      Long Toss - Max Distance
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
