<script setup>
import { ref } from 'vue';
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
  playersFOUL: {
    type: Object,
    default: () => {}
  },
  teamFOUL: {
    type: Object,
    default: () => {}
  }
})

const keys = ref({
  player: [
    'player',
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
  ],
  playerFOUL: [
    'player',
    'pitches',
    'FB',
    'CH',
    'CB',
    'SL'
  ],
  teamFOUL: [
    'pitches',
    'FB',
    'CH',
    'CB',
    'SL'
  ]
})

const firsTableHeaders = [
  'Player', 'Pitches', 'GB', 'LD', 'Fly', 'PF', 'Swing/Miss', 'Take'
]

const tableHeadings = [
  'player', 'Pitches', 'FB', 'CH', 'CB', 'SL',
]
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2">
    <div>
      <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
        Pitching Session - Slider - Percentages
      </h1>
      <TableSesionsTotal :data="{
        players: props.players,
        team: props.team
      }"
      :headings="firsTableHeaders"
      :keyTeam="keys.team"
      :keyPlayer="keys.player"
      />
    </div>
    <div>
      <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
        Pitching Session - Foul - Percentages
      </h1>
      <TableSesionsTotal :data="{
        players: props.playersFOUL,
        team: props.teamFOUL
      }"
      :headings="tableHeadings"
      :keyTeam="keys.teamFOUL"
      :keyPlayer="keys.playerFOUL"
      />
    </div>
  </div>
</template>
