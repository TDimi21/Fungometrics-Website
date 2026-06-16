<script setup>
import { ref, computed, onMounted } from 'vue'
import { PrintFieldData } from '@/components/shared'
import { useGetPlayerAb } from '@/composables/useGetPlayerAb.js'

const props = defineProps({
  tableData: { type: Object, required: false, default: () => ({}) },
})

const { getPlayerInfo } = useGetPlayerAb()

const buttonsGroup = ref([
  { text: 'ALL',    typeHit: 'All' },
  { text: 'LEFT',   typeHit: 'L'   },
  { text: 'MIDDLE', typeHit: 'C'   },
  { text: 'RIGHT',  typeHit: 'R'   },
])

const TRAJ_ROWS = [
  { key: 'TOTAL-FB',    label: 'FLY BALL',    color: '#558b2f' },
  { key: 'TOTAL-LD',    label: 'LINE DRIVE',   color: '#1565c0' },
  { key: 'TOTAL-GB',    label: 'GROUND BALL',  color: '#2e7d32' },
  { key: 'TOTAL-SM',    label: 'SW/MISS',      color: '#bf360c' },
  { key: 'TOTAL-FOUL',  label: 'FOUL',         color: '#6a1b9a' },
  { key: 'TOTAL-SWINGS',label: 'TOTAL',        color: '#263238', isTotal: true },
]

const currentIndex = ref(0)
const coordinates = ref([])
const activeRow = ref('1')

const getALlPitchMark = () => {
  const src = props.tableData['hitter-trajectory']?.team_totals?.['SPRAY-PITCH-LOCATION'] ?? []
  src.forEach(el => coordinates.value.push({ point: el.field_mark, feature: el.type_of_hit }))
}

const filterPointsByFirstRowTable = () => {
  coordinates.value = []
  activeRow.value = '1'
  getALlPitchMark()
}

const filterByTrajectory = (index, type) => {
  coordinates.value = []
  currentIndex.value = index
  const src = activeRow.value === '1'
    ? props.tableData['hitter-trajectory']?.team_totals?.['SPRAY-PITCH-LOCATION'] ?? []
    : props.tableData['hitter-trajectory']?.players?.[activeRow.value]?.['SPRAY-PITCH-LOCATION'] ?? []
  src.forEach(item => {
    if (type === 'All' || (item.field_direction !== null && item.field_direction?.includes(type)))
      coordinates.value.push({ point: item.field_mark, feature: item.type_of_hit })
  })
}

const filterPointsByRowTable = (player, id) => {
  coordinates.value = []
  activeRow.value = id
  player['SPRAY-PITCH-LOCATION']?.forEach(el =>
    coordinates.value.push({ point: el.field_mark, feature: el.type_of_hit })
  )
}

onMounted(getALlPitchMark)

const trajData = computed(() => props.tableData['hitter-trajectory'] ?? { team_totals: {}, players: {} })
const playerIds = computed(() => Object.keys(trajData.value.players ?? {}))

const teamVal   = (row) => trajData.value.team_totals?.[row.key] ?? '–'
const playerVal = (id, row) => trajData.value.players?.[id]?.[row.key] ?? '–'
</script>

<template>
  <section class="mt-6 rounded-xl bg-app-card text-white p-5 shadow-lg overflow-x-auto">
    <div class="flex flex-col xl:flex-row gap-6">

      <!-- Left: direction filter buttons -->
      <div class="flex flex-col gap-2 min-w-[150px]">
        <p class="text-app-muted text-xs uppercase font-semibold mb-1">Direction</p>
        <button
          v-for="(btn, i) in buttonsGroup" :key="i"
          class="py-2 px-4 rounded-lg font-semibold text-sm border transition-colors uppercase tracking-wide"
          :class="currentIndex === i ? 'bg-app-red border-app-red text-white' : 'border-white/20 text-white/70 hover:text-white hover:border-white/50'"
          @click="filterByTrajectory(i, btn.typeHit)"
        >{{ btn.text }}</button>

        <!-- Direction legend -->
        <div class="mt-4 flex flex-col gap-1 text-xs text-white/60">
          <div class="flex items-center gap-2"><img src="@/assets/img/training/balltraining-green.svg" class="w-4 h-4"> Left</div>
          <div class="flex items-center gap-2"><img src="@/assets/img/training/balltraining.svg" class="w-4 h-4"> Middle</div>
          <div class="flex items-center gap-2"><img src="@/assets/img/training/balltraining-blue.svg" class="w-4 h-4"> Right</div>
        </div>
      </div>

      <!-- Center: spray chart -->
      <div class="flex-shrink-0 w-full xl:w-[340px] flex flex-col">
        <PrintFieldData :fieldCoordinates="coordinates" typeOfCondition="trajectory" class="flex-1 min-h-0"/>
      </div>

      <!-- Right: trajectory table (rows = type, cols = players) — horizontal scroll for many players -->
      <div class="flex-1 min-w-0 overflow-x-auto">
        <h3 class="text-app-gold font-semibold tracking-widest mb-3 text-xs uppercase">Trajectory Breakdown</h3>
        <table class="min-w-max text-sm border-collapse">
          <thead>
            <tr>
              <th class="w-[100px] py-2"></th>
              <th class="py-2 px-3 text-center text-app-gold font-bold text-xs uppercase">TEAM</th>
              <th v-for="id in playerIds" :key="id" class="py-2 px-3 text-center text-white/70 font-medium text-xs uppercase whitespace-nowrap">
                {{ getPlayerInfo(id).name?.last ?? id }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in TRAJ_ROWS" :key="row.key" class="border-t border-white/5">
              <td class="py-3 px-3 font-bold text-xs text-center rounded-l text-white" :style="{ backgroundColor: row.color }">
                {{ row.label }}
              </td>
              <td class="py-3 px-4 text-center font-semibold" :class="row.isTotal ? 'text-white' : 'text-app-gold'">
                {{ teamVal(row) }}
              </td>
              <td v-for="id in playerIds" :key="id" class="py-3 px-4 text-center text-white/80">
                {{ playerVal(id, row) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </section>
</template>
