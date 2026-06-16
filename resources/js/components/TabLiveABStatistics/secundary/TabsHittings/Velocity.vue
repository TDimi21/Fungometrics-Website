<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  tableData: { type: Object, required: false, default: () => ({}) },
})

const buttonsGroup = ref([
  { text: 'ALL',      typeHit: 'All' },
  { text: 'GB',       typeHit: 'GB'  },
  { text: 'FLY BALL', typeHit: 'FB'  },
  { text: 'LINE DR.', typeHit: 'LD'  },
])

// Matching app's exit-velocity breakdown rows
const EV_ROWS = [
  { key: 'MAX', label: 'MAX EV',      color: '#37474f' },
  { key: 'LD',  label: 'LINE DRIVE',  color: '#1565c0' },
  { key: 'FB',  label: 'FLY BALL',    color: '#558b2f' },
  { key: 'GB',  label: 'GROUND BALL', color: '#2e7d32' },
]

const TRAJ_MAP = { GB: 'GB', FB: 'FB', LD: 'LD' }

const currentIndex = ref(0)
const playerVelocities = ref([])
const viewMode = ref('list') // 'list' | 'field' (field not yet implemented)

const filterByTrajectory = (index, typeHit) => {
  playerVelocities.value = []
  currentIndex.value = index
  Object.values(props.tableData).forEach(player => {
    player.forEach(ball => {
      if (typeHit === 'All' || ball.batting?.type_of_hit === typeHit)
        if (ball.batting?.velocity > 0) playerVelocities.value.push(ball)
    })
  })
}

// Max EV for a player array, optional trajectory filter
const maxEV = (balls, traj = null) => {
  let max = 0
  balls.forEach(b => {
    if ((traj === null || b.batting?.type_of_hit === traj) && b.batting?.velocity > max)
      max = b.batting.velocity
  })
  return max > 0 ? max.toFixed(2) : '0.00'
}

// Team-level helpers
const teamMaxEV = computed(() => {
  let max = 0
  Object.values(props.tableData).forEach(p => p.forEach(b => { if (b.batting?.velocity > max) max = b.batting.velocity }))
  return max > 0 ? max.toFixed(2) : '0.00'
})

const teamEVByTraj = (traj) => {
  let max = 0
  Object.values(props.tableData).forEach(p =>
    p.forEach(b => { if (b.batting?.type_of_hit === traj && b.batting?.velocity > max) max = b.batting.velocity })
  )
  return max > 0 ? max.toFixed(2) : '0.00'
}

const evRowTeamVal = (row) => row.key === 'MAX' ? teamMaxEV.value : teamEVByTraj(row.key)
const evRowPlayerVal = (balls, row) => row.key === 'MAX' ? maxEV(balls) : maxEV(balls, row.key)

// Color-code velocity value in the list
const velColor = (ball) => {
  const dir = ball.batting?.pitch_location ?? ''
  if (dir.includes('L')) return 'text-green-400'
  if (dir.includes('C')) return 'text-yellow-400'
  return 'text-blue-400'
}

const playerIds = computed(() => Object.keys(props.tableData))

onMounted(() => filterByTrajectory(0, 'All'))
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

        <!-- Direction legend -->
        <div class="mt-4 flex flex-col gap-1 text-xs">
          <div class="flex items-center gap-2 text-green-400"><span class="w-2 h-2 rounded-full bg-green-400"></span> Left</div>
          <div class="flex items-center gap-2 text-yellow-400"><span class="w-2 h-2 rounded-full bg-yellow-400"></span> Mid</div>
          <div class="flex items-center gap-2 text-blue-400"><span class="w-2 h-2 rounded-full bg-blue-400"></span> Right</div>
        </div>

        <!-- List / Field toggle -->
        <div class="mt-4 flex gap-2">
          <button @click="viewMode = 'list'"
            class="flex-1 py-1 rounded text-xs font-semibold border transition-colors"
            :class="viewMode === 'list' ? 'bg-app-red border-app-red text-white' : 'border-white/20 text-white/60'">
            List
          </button>
          <button @click="viewMode = 'field'"
            class="flex-1 py-1 rounded text-xs font-semibold border transition-colors"
            :class="viewMode === 'field' ? 'bg-app-red border-app-red text-white' : 'border-white/20 text-white/60'">
            Field
          </button>
        </div>
      </div>

      <!-- Velocity list -->
      <div class="w-full xl:w-[180px] flex-shrink-0">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="bg-app-surface">
              <th class="py-2 px-3 text-center text-white/60 text-xs uppercase">SWING #</th>
              <th class="py-2 px-3 text-center text-white/60 text-xs uppercase">EXIT VEL</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(ball, idx) in playerVelocities" :key="idx"
              class="border-t border-white/5 even:bg-white/[0.02]">
              <td class="py-2 px-3 text-center text-white/60 text-sm">{{ ball.sort + 1 }}</td>
              <td class="py-2 px-3 text-center font-bold text-sm" :class="velColor(ball)">
                {{ ball.batting?.velocity ?? '–' }}
              </td>
            </tr>
            <tr v-if="!playerVelocities.length">
              <td colspan="2" class="py-4 text-center text-app-muted text-xs">No data</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Max EV breakdown table (rows = trajectory type, cols = players) -->
      <div class="flex-1 min-w-0">
        <h3 class="text-app-gold font-semibold tracking-widest mb-3 text-xs uppercase">Exit Velocity Breakdown</h3>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr>
              <th class="w-[110px] py-2"></th>
              <th class="py-2 px-3 text-center text-app-gold font-bold text-xs uppercase">TEAM</th>
              <th v-for="id in playerIds" :key="id" class="py-2 px-3 text-center text-white/70 font-medium text-xs uppercase whitespace-nowrap">
                {{ props.tableData[id]?.[0]?.batting?.profile?.first_name?.charAt(0) ?? '' }}.
                {{ props.tableData[id]?.[0]?.batting?.profile?.last_name ?? id }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in EV_ROWS" :key="row.key" class="border-t border-white/5">
              <td class="py-3 px-3 font-bold text-xs text-center rounded-l text-white" :style="{ backgroundColor: row.color }">
                {{ row.label }}
              </td>
              <td class="py-3 px-4 text-center text-app-gold font-semibold">
                {{ evRowTeamVal(row) }}
              </td>
              <td v-for="id in playerIds" :key="id" class="py-3 px-4 text-center text-white/80">
                {{ evRowPlayerVal(props.tableData[id], row) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </section>
</template>
