<script setup>
import { ref, computed, onMounted } from 'vue'
import { PrintFieldData } from '@/components/shared'
import { useGetPlayerAb } from '@/composables/useGetPlayerAb.js'

const props = defineProps({
  tableData: { type: Object, required: false, default: () => ({}) },
})

const { getPlayerInfo } = useGetPlayerAb()

const buttonsGroup = ref([
  { text: 'ALL',      typeHit: 'All'  },
  { text: 'GB',       typeHit: 'GB'   },
  { text: 'FLY BALL', typeHit: 'FB'   },
  { text: 'LINE DR.', typeHit: 'LD'   },
  { text: 'SW/MISS',  typeHit: 'SM'   },
])

// Rows matching app Contact tab — trajectory categories
const CONTACT_ROWS = [
  { key: 'TOTAL-GB',    label: 'GB',      color: '#2e7d32' },
  { key: 'TOTAL-FB',    label: 'Fly',     color: '#558b2f' },
  { key: 'TOTAL-LD',    label: 'LD',      color: '#1565c0' },
  { key: 'TOTAL-SM',    label: 'Sw/Miss', color: '#bf360c' },
  { key: 'TOTAL-FOUL',  label: 'Foul',    color: '#6a1b9a' },
  { key: 'TOTAL-SWINGS',label: 'TOTAL',   color: '#263238', isTotal: true },
]

const currentIndex = ref(0)
const coordinates = ref([])
const activeRow = ref('1')

const getALlPitchMark = () => {
  const src = props.tableData['hitter-contact']?.team_totals?.['TOTAL-PITCH-LOCATION'] ?? []
  src.forEach(el => coordinates.value.push({ point: el.field_mark, feature: el.quality_of_contact }))
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
    ? props.tableData['hitter-contact']?.team_totals?.['TOTAL-PITCH-LOCATION'] ?? []
    : props.tableData['hitter-contact']?.players?.[activeRow.value]?.['TOTAL-PITCH-LOCATION'] ?? []
  src.forEach(item => {
    if (type === 'All' || item.type_of_hit === type)
      coordinates.value.push({ point: item.field_mark, feature: item.quality_of_contact })
  })
}

const filterPointsByRowTable = (player, id) => {
  coordinates.value = []
  activeRow.value = id
  player['TOTAL-PITCH-LOCATION']?.forEach(el =>
    coordinates.value.push({ point: el.field_mark, feature: el.quality_of_contact })
  )
}

onMounted(getALlPitchMark)

// Stats from hitter-trajectory (matches app's Contact table categories)
const trajData = computed(() => props.tableData['hitter-trajectory'] ?? { team_totals: {}, players: {} })
const playerIds = computed(() => Object.keys(trajData.value.players ?? {}))

const teamVal  = (row) => trajData.value.team_totals?.[row.key] ?? '–'
const playerVal = (id, row) => trajData.value.players?.[id]?.[row.key] ?? '–'
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

      <!-- Center: field spray map -->
      <div class="flex-shrink-0 w-full xl:w-[260px]">
        <PrintFieldData :fieldCoordinates="coordinates" typeOfCondition="qtyContact"/>
      </div>

      <!-- Right: trajectory stats table (rows = type, cols = players) -->
      <div class="flex-1 min-w-0">
        <h3 class="text-app-gold font-semibold tracking-widest mb-3 text-xs uppercase">Contact Breakdown</h3>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr>
              <th class="w-[90px] py-2"></th>
              <th class="py-2 px-3 text-center text-app-gold font-bold text-xs uppercase">TEAM</th>
              <th v-for="id in playerIds" :key="id" class="py-2 px-3 text-center text-white/70 font-medium text-xs uppercase whitespace-nowrap">
                {{ getPlayerInfo(id).name?.last ?? id }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in CONTACT_ROWS" :key="row.key" class="border-t border-white/5">
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
