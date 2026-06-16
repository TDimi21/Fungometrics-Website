<script setup>
import { ref, computed, onMounted } from 'vue'
import { useGetPlayerAb } from '@/composables/useGetPlayerAb.js'

const props = defineProps({
  tableData: { type: Object, required: false, default: () => ({}) },
})

const { getPlayerInfo } = useGetPlayerAb()

const buttonsGroup = ref([
  { text: 'ALL',       typeHit: 'All'   },
  { text: 'FASTBALL',  typeHit: 'FB'    },
  { text: 'CURVEBALL', typeHit: 'CB'    },
  { text: 'CHANGE-UP', typeHit: 'CH'    },
  { text: 'SLIDER',    typeHit: 'SL'    },
  { text: 'OTHER',     typeHit: 'OTHER' },
])

const PITCH_ROWS = [
  { key: 'FB',    label: 'FASTBALL',  color: '#1565C0' },
  { key: 'CH',    label: 'CHANGE-UP', color: '#6a1b9a' },
  { key: 'CB',    label: 'CURVEBALL', color: '#33691e' },
  { key: 'SL',    label: 'SLIDER',    color: '#bf360c' },
  { key: 'OTHER', label: 'OTHER',     color: '#4e342e' },
  { key: 'TOTAL', label: 'TOTAL',     color: '#263238', isTotal: true },
]

const currentIndex = ref(0)
const activeRow = ref('1')
const pitchList = ref([])

const playerIds = computed(() => Object.keys(props.tableData))

const countType = (balls, type) =>
  type === 'TOTAL' ? balls.length : balls.filter(b => b.pitching?.type_throw === type).length

const teamCount = (type) => {
  let total = 0
  Object.values(props.tableData).forEach(balls => { total += countType(balls, type) })
  return total || '–'
}

const playerCount = (id, type) => {
  const balls = props.tableData[id] ?? []
  return countType(balls, type) || '–'
}

const filterByType = (index, typeHit) => {
  currentIndex.value = index
  pitchList.value = []
  if (activeRow.value === '1') {
    Object.values(props.tableData).forEach(balls =>
      balls.forEach(b => {
        if (typeHit === 'All' || b.pitching?.type_throw === typeHit)
          pitchList.value.push(b)
      })
    )
  } else {
    const balls = props.tableData[activeRow.value] ?? []
    balls.forEach(b => {
      if (typeHit === 'All' || b.pitching?.type_throw === typeHit)
        pitchList.value.push(b)
    })
  }
}

const selectPlayer = (id) => {
  activeRow.value = id
  pitchList.value = (props.tableData[id] ?? []).filter(b => b.pitching?.miles_per_hour > 0)
}

const selectTeam = () => {
  activeRow.value = '1'
  pitchList.value = []
  Object.values(props.tableData).forEach(balls =>
    balls.forEach(b => { if (b.pitching?.miles_per_hour > 0) pitchList.value.push(b) })
  )
}

const velColor = (ball) => ball.is_strike ? 'text-app-red' : 'text-app-blue'

onMounted(selectTeam)
</script>

<template>
  <section class="mt-6 rounded-xl bg-app-card text-white p-5 shadow-lg overflow-x-auto">
    <div class="flex flex-col xl:flex-row gap-6">

      <!-- Left: legend + filter buttons -->
      <div class="flex flex-col gap-2 min-w-[160px]">
        <!-- Strike / Ball legend -->
        <div class="flex flex-col gap-2 mb-3">
          <div class="flex items-center justify-between bg-app-surface rounded-lg px-3 py-2 border-l-4 border-app-red">
            <span class="text-xs text-white/70 uppercase">Strike</span>
            <span class="w-3 h-3 rounded-full bg-app-red"></span>
          </div>
          <div class="flex items-center justify-between bg-app-surface rounded-lg px-3 py-2 border-l-4 border-app-blue">
            <span class="text-xs text-white/70 uppercase">Ball</span>
            <span class="w-3 h-3 rounded-full bg-app-blue"></span>
          </div>
        </div>

        <p class="text-app-muted text-xs uppercase font-semibold mb-1">Filter</p>
        <button
          v-for="(btn, i) in buttonsGroup" :key="i"
          class="py-2 px-4 rounded-lg font-semibold text-sm border transition-colors uppercase tracking-wide"
          :class="currentIndex === i ? 'bg-app-red border-app-red text-white' : 'border-white/20 text-white/70 hover:text-white hover:border-white/50'"
          @click="filterByType(i, btn.typeHit)"
        >{{ btn.text }}</button>
      </div>

      <!-- Center: pitch velocity list -->
      <div class="w-full xl:w-[180px] flex-shrink-0">
        <h3 class="text-app-gold font-semibold tracking-widest mb-3 text-xs uppercase">Velocities</h3>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="bg-app-surface">
              <th class="py-2 px-3 text-center text-white/60 text-xs uppercase">PITCH #</th>
              <th class="py-2 px-3 text-center text-white/60 text-xs uppercase">MPH</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(ball, idx) in pitchList" :key="idx"
              class="border-t border-white/5 even:bg-white/[0.02]">
              <td class="py-2 px-3 text-center text-white/60 text-sm">{{ ball.sort + 1 }}</td>
              <td class="py-2 px-3 text-center font-bold text-sm" :class="velColor(ball)">
                {{ ball.pitching?.miles_per_hour ?? '–' }}
              </td>
            </tr>
            <tr v-if="!pitchList.length">
              <td colspan="2" class="py-4 text-center text-app-muted text-xs">No data</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Right: pitch type breakdown table (rows = type, cols = pitchers) -->
      <div class="flex-1 min-w-0">
        <h3 class="text-app-gold font-semibold tracking-widest mb-3 text-xs uppercase">Pitch Type Breakdown</h3>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr>
              <th class="w-[100px] py-2"></th>
              <th
                class="py-2 px-3 text-center font-bold text-xs uppercase cursor-pointer hover:text-white"
                :class="activeRow === '1' ? 'text-app-gold underline' : 'text-app-gold'"
                @click="selectTeam"
              >TEAM</th>
              <th
                v-for="id in playerIds" :key="id"
                class="py-2 px-3 text-center font-medium text-xs uppercase whitespace-nowrap cursor-pointer hover:text-white"
                :class="activeRow === id ? 'text-app-blue underline' : 'text-white/70'"
                @click="selectPlayer(id)"
              >
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
                {{ teamCount(row.key) }}
              </td>
              <td v-for="id in playerIds" :key="id" class="py-3 px-4 text-center text-white/80">
                {{ playerCount(id, row.key) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </section>
</template>
