<script setup>
import { ref, computed, onMounted } from 'vue'
import { PrintCatcherData } from '@/components/shared'
import { useGetPlayerAb } from '@/composables/useGetPlayerAb.js'

const props = defineProps({
  tableData: { type: Object, required: false, default: () => ({}) },
  ballData:  { type: [Object, Array], required: false, default: () => ({}) },
})

const { getPlayerInfo } = useGetPlayerAb()

const buttonsGroup = ref([
  { text: 'ALL',       typeHit: 'All' },
  { text: 'FASTBALL',  typeHit: 'FB'  },
  { text: 'CURVEBALL', typeHit: 'CB'  },
  { text: 'CHANGE-UP', typeHit: 'CH'  },
  { text: 'SLIDER',    typeHit: 'SL'  },
])

const PITCH_ROWS = [
  { key: 'FB %',    label: 'FB %',  color: '#1565C0' },
  { key: 'CH %',    label: 'CH %',  color: '#6a1b9a' },
  { key: 'CB %',    label: 'CV %',  color: '#33691e' },
  { key: 'SL %',    label: 'SL %',  color: '#bf360c' },
  { key: 'OTHER %', label: 'OTH %', color: '#4e342e' },
  { key: 'TOTAL',   label: 'TOTAL', color: '#263238', isTotal: true },
]

const currentIndex = ref(0)
const coordinates = ref([])
const strikePercentage = ref(0)
const activeRow = ref('1')

const getALlPitchMark = () => {
  const src = props.tableData['pitcher-pitch-breakdown']?.team_totals?.['TOTAL-PITCH-LOCATION'] ?? []
  src.forEach(el => coordinates.value.push(el.pitch_mark))
}

const filterPointsByFirstRowTable = () => {
  coordinates.value = []
  activeRow.value = '1'
  getALlPitchMark()
  getStrikePercentage(props.ballData)
}

const filterByTrajectory = (index, type) => {
  coordinates.value = []
  currentIndex.value = index
  const src = activeRow.value === '1'
    ? props.tableData['pitcher-pitch-breakdown']?.team_totals?.['TOTAL-PITCH-LOCATION'] ?? []
    : props.tableData['pitcher-pitch-breakdown']?.players?.[activeRow.value]?.['TOTAL-PITCH-LOCATION'] ?? []
  src.forEach(item => {
    if (type === 'All' || item.type_throw === type) coordinates.value.push(item.pitch_mark)
  })
}

const filterPointsByRowTable = (player, id) => {
  coordinates.value = []
  activeRow.value = id
  player['TOTAL-PITCH-LOCATION']?.forEach(el => coordinates.value.push(el.pitch_mark))
}

const getStrikePercentage = (items) => {
  if (!Array.isArray(items) || !items.length) return
  let strikes = 0
  items.forEach(p => { if (p.pitching?.zone === 'S') strikes++ })
  strikePercentage.value = ((strikes * 100) / items.length).toFixed(2)
}

onMounted(() => { getALlPitchMark(); getStrikePercentage(props.ballData) })

const pitchBreakdown = computed(() => props.tableData['pitcher-pitch-breakdown'] ?? { team_totals: {}, players: {} })
const playerIds = computed(() => Object.keys(pitchBreakdown.value.players ?? {}))
const teamVal  = (row) => pitchBreakdown.value.team_totals?.[row.key] ?? '–'
const playerVal = (id, row) => pitchBreakdown.value.players?.[id]?.[row.key] ?? '–'
</script>

<template>
  <section class="mt-6 rounded-xl bg-app-card text-white p-5 shadow-lg overflow-x-auto">
    <div class="flex flex-col xl:flex-row gap-6">

      <!-- Left: filter buttons + strike % -->
      <div class="flex flex-col gap-2 min-w-[150px]">
        <div class="bg-app-surface rounded-lg border-l-4 border-app-red p-3 flex justify-between items-center mb-2">
          <span class="text-app-red text-xs font-semibold uppercase">Strike%</span>
          <span class="text-xl font-bold text-white">{{ strikePercentage }}%</span>
        </div>
        <p class="text-app-muted text-xs uppercase font-semibold mb-1">Filter</p>
        <button
          v-for="(btn, i) in buttonsGroup" :key="i"
          class="py-2 px-4 rounded-lg font-semibold text-sm border transition-colors uppercase tracking-wide"
          :class="currentIndex === i ? 'bg-app-red border-app-red text-white' : 'border-white/20 text-white/70 hover:text-white hover:border-white/50'"
          @click="filterByTrajectory(i, btn.typeHit)"
        >{{ btn.text }}</button>
      </div>

      <!-- Center: catcher heatmap -->
      <div class="flex-shrink-0 w-full xl:w-[260px]">
        <PrintCatcherData :ballCoordinates="coordinates"/>
      </div>

      <!-- Right: pitch-type % table (rows = type, cols = pitchers) -->
      <div class="flex-1 min-w-0">
        <h3 class="text-app-gold font-semibold tracking-widest mb-3 text-xs uppercase">Pitch Type Breakdown</h3>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr>
              <th class="w-[80px] py-2"></th>
              <th class="py-2 px-3 text-center text-app-gold font-bold text-xs uppercase">TEAM</th>
              <th v-for="id in playerIds" :key="id" class="py-2 px-3 text-center text-white/70 font-medium text-xs uppercase whitespace-nowrap">
                {{ getPlayerInfo(id).name?.last ?? id }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in PITCH_ROWS" :key="row.key" class="border-t border-white/5">
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
