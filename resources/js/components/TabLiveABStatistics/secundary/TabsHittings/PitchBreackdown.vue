<script setup>
import { ref, computed, onMounted } from 'vue'
import { PrintCatcherData } from '@/components/shared'
import { useGetPlayerAb } from '@/composables/useGetPlayerAb.js'

const props = defineProps({
  tableData: { type: Object, required: false, default: () => ({}) },
  ballByBall: { type: Array, default: () => [] },
})

const { getPlayerInfo } = useGetPlayerAb()

const buttonsGroup = ref([
  { text: 'ALL',       typeHit: 'All' },
  { text: 'FASTBALL',  typeHit: 'FB'  },
  { text: 'CURVEBALL', typeHit: 'CB'  },
  { text: 'CHANGE-UP', typeHit: 'CH'  },
  { text: 'SLIDER',    typeHit: 'SL'  },
  { text: 'OTHER',     typeHit: 'OTHER'},
])

const PITCH_ROWS = [
  { key: 'FB',    label: 'FB %',  color: '#1565C0' },
  { key: 'CH',    label: 'CH %',  color: '#6a1b9a' },
  { key: 'CB',    label: 'CV %',  color: '#33691e' },
  { key: 'SL',    label: 'SL %',  color: '#bf360c' },
  { key: 'OTHER', label: 'OTH %', color: '#4e342e' },
  { key: 'TOTAL', label: 'TOTAL', color: '#263238', isTotal: true },
]

const currentIndex = ref(0)
const coordinates = ref([])
const activeRow = ref('1')

const getALlPitchMark = () => {
  const src = props.tableData['hitter-pitch-breakdown']?.team_totals?.['TOTAL-PITCH-LOCATION'] ?? []
  src.forEach(el => coordinates.value.push(el.pitch_mark))
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
    ? props.tableData['hitter-pitch-breakdown']?.team_totals?.['TOTAL-PITCH-LOCATION'] ?? []
    : props.tableData['hitter-pitch-breakdown']?.players?.[activeRow.value]?.['TOTAL-PITCH-LOCATION'] ?? []
  src.forEach(item => {
    if (type === 'All' || item.type_of_hit === type) coordinates.value.push(item.pitch_mark)
  })
}

const filterPointsByRowTable = (player, id) => {
  coordinates.value = []
  activeRow.value = id
  player['TOTAL-PITCH-LOCATION']?.forEach(el => coordinates.value.push(el.pitch_mark))
}

onMounted(getALlPitchMark)

// Pitch type % computed from raw ball_x_ball
const pitchStats = computed(() => {
  const zero = () => ({ FB: 0, CH: 0, CB: 0, SL: 0, OTHER: 0, TOTAL: 0 })
  const team = zero()
  const players = {}
  props.ballByBall.forEach(ball => {
    const id = ball.batting?.batter_id
    const pt = ball.pitching?.type_throw
    if (!id || !pt) return
    if (!players[id]) players[id] = zero()
    const k = ['FB','CH','CB','SL'].includes(pt) ? pt : 'OTHER'
    players[id][k]++; players[id].TOTAL++
    team[k]++; team.TOTAL++
  })
  return { team, players }
})

const playerIds = computed(() => Object.keys(pitchStats.value.players))
const pct = (n, d) => d ? ((n / d) * 100).toFixed(1) + '%' : '–'
const teamVal  = (row) => row.isTotal ? pitchStats.value.team.TOTAL : pct(pitchStats.value.team[row.key], pitchStats.value.team.TOTAL)
const playerVal = (id, row) => {
  const p = pitchStats.value.players[id] ?? {}
  return row.isTotal ? (p.TOTAL ?? 0) : pct(p[row.key] ?? 0, p.TOTAL ?? 0)
}
</script>

<template>
  <section class="mt-6 rounded-xl bg-app-card text-white p-5 shadow-lg overflow-x-auto">
    <div class="flex flex-col xl:flex-row gap-6">

      <!-- Left: filter buttons -->
      <div class="flex flex-col gap-2 min-w-[150px]">
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

      <!-- Right: pitch-type % table (rows = type, cols = players) -->
      <div class="flex-1 min-w-0">
        <h3 class="text-app-gold font-semibold tracking-widest mb-3 text-xs uppercase">Pitch Type Breakdown — % Seen per Batter</h3>
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
        <p v-if="!ballByBall.length" class="text-app-muted text-center py-6 text-sm">No pitch data available.</p>
      </div>

    </div>
  </section>
</template>
