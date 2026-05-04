<script>
const BASE_GRID = 60
</script>

<script setup>
import { ref, computed } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { usePlayerStore } from '@/store/players'
import ArrowDownIcon from '@/components/icons/ArrowDownIcon.vue'

const props = defineProps({
  item:     { type: Object,  default: null },
  gridSize: { type: Number,  default: 5    },
  baseGrid: { type: Number,  default: 60   },
  // optional external filters (mirrors RN props)
  pitchTypeFilter:   { type: [Number, String], default: null },
  selectedPlayerKey: { type: [Number, String], default: null },
  namePlayerFilter:  { type: String,           default: null },
})

// ─── helpers (direct port from RN) ──────────────────────────────────────────
function getPitchMark(item) {
  return item?.pitch_mark || item?.pitch_location || item?.field_mark || 0
}

function getCellIndex(pitchMark, gridSize, baseGrid) {
  if (!pitchMark || pitchMark <= 0) return null
  // Grid is row-major: points 1-60 = row 1 left→right, 61-120 = row 2, etc.
  const baseRow = Math.floor((pitchMark - 1) / baseGrid) + 1
  const baseCol = ((pitchMark - 1) % baseGrid) + 1
  if (gridSize === baseGrid) return { row: baseRow, col: baseCol }
  const bucket = baseGrid / gridSize
  const row = Math.min(gridSize, Math.ceil(baseRow / bucket))
  const col = Math.min(gridSize, Math.ceil(baseCol / bucket))
  return { row, col }
}

// ─── store / fetch ───────────────────────────────────────────────────────────
const { axiosGet }  = useAxiosAuth()
const playerStore   = usePlayerStore()
const isLoading     = ref(false)
const pitches       = ref([])
const selectedPlayerId = ref(props.item?.id ?? null)
const activePitchType  = ref('ALL')

const PITCH_TYPES = [
  { key: 'ALL',   label: 'All'   },
  { key: 'FB',    label: 'FB'    },
  { key: 'CV',    label: 'CV'    },
  { key: 'CH',    label: 'CH'    },
  { key: 'OTHER', label: 'Other' },
]

const KNOWN_TYPES = ['FB', 'CV', 'CH']

const fetchData = async (playerId) => {
  if (!playerId) return
  try {
    isLoading.value = true
    pitches.value   = []
    const res       = await axiosGet(`coach/pitcher/velocity/${playerId}`)
    pitches.value   = res.data.data ?? []
  } catch {
    pitches.value   = []
  } finally {
    isLoading.value = false
  }
}

const onPlayerChange = () => fetchData(selectedPlayerId.value)

if (props.item?.id) fetchData(props.item.id)

// ─── aggregation (port of useMemo block) ────────────────────────────────────
const grid = computed(() => {
  const gridSize = props.gridSize
  const baseGrid = props.baseGrid
  const agg = new Map()

  pitches.value.forEach((item) => {
    // built-in pitch type filter buttons
    if (activePitchType.value !== 'ALL') {
      const t = String(item?.type_throw ?? '').toUpperCase()
      if (activePitchType.value === 'OTHER') {
        if (KNOWN_TYPES.includes(t)) return
      } else {
        if (t !== activePitchType.value) return
      }
    }
    // pitch-type filter (external prop)
    if (props.pitchTypeFilter && Number(props.pitchTypeFilter) > 0) {
      if (Number(item?.type_of_throw_id) !== Number(props.pitchTypeFilter)) return
    }
    // external player-key filter (when used inside a modal with full data set)
    if (props.selectedPlayerKey) {
      const key = item?.player_id || item?.pitcher_id || item?.id_player || item?.id || item?.user_id || null
      if (String(key) !== String(props.selectedPlayerKey)) return
    }
    // name filter
    if (props.namePlayerFilter) {
      const name = String(item?.player_name || item?.name || '').toLowerCase()
      if (!name.includes(String(props.namePlayerFilter).toLowerCase())) return
    }

    const mark = getPitchMark(item)
    const cell = getCellIndex(mark, gridSize, baseGrid)
    if (!cell) return

    const mph = Number(item?.miles_per_hour || item?.pitch_velocity || item?.velocity || 0)
    if (!mph) return

    const k       = `${cell.row}-${cell.col}`
    const current = agg.get(k) || { sum: 0, count: 0, row: cell.row, col: cell.col }
    current.sum  += mph
    current.count += 1
    agg.set(k, current)
  })

  const cells = []
  let minAvg = null
  let maxAvg = null

  agg.forEach((val) => {
    const avg = val.count ? val.sum / val.count : 0
    if (avg) {
      minAvg = minAvg == null ? avg : Math.min(minAvg, avg)
      maxAvg = maxAvg == null ? avg : Math.max(maxAvg, avg)
    }
    cells.push({ row: val.row, col: val.col, avg, count: val.count })
  })

  return {
    cells,
    minAvg: minAvg ?? 0,
    maxAvg: maxAvg ?? 0,
  }
})

// ─── color (port of getCellBackground) ──────────────────────────────────────
const cellBg = (avg) => {
  const { minAvg, maxAvg } = grid.value
  if (!avg || !maxAvg || maxAvg <= 0) return 'rgba(255,0,0,0.08)'
  if (minAvg === maxAvg)              return 'rgba(255,0,0,0.45)'
  const t     = (avg - minAvg) / (maxAvg - minAvg)
  const alpha = (0.12 + t * 0.68).toFixed(3)
  return `rgba(255,0,0,${alpha})`
}

// ─── cell positioning ────────────────────────────────────────────────────────
const cellStyle = (cell) => {
  const pct = 100 / props.gridSize
  return {
    position:        'absolute',
    left:            `${(cell.col - 1) * pct}%`,
    top:             `${(cell.row - 1) * pct}%`,
    width:           `${pct}%`,
    height:          `${pct}%`,
    display:         'flex',
    alignItems:      'center',
    justifyContent:  'center',
    backgroundColor: cellBg(cell.avg),
    boxSizing:       'border-box',
    borderWidth:     '0.25px',
    borderStyle:     'solid',
    borderColor:     'rgba(255,255,255,0.08)',
  }
}
</script>

<template>
  <div class="w-full">
    <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">
      Velocity Zone Chart
    </h3>

    <!-- Player dropdown — only shown when no fixed item prop -->
    <div v-if="!props.item" class="relative mb-4">
      <select
        v-model="selectedPlayerId"
        @change="onPlayerChange"
        class="w-full rounded-lg border border-white/20 bg-white/10 text-white text-sm py-2 pl-3 pr-8 appearance-none outline-none cursor-pointer"
      >
        <option :value="null" disabled>Select a pitcher…</option>
        <option
          v-for="player in playerStore.players"
          :key="player.id"
          :value="player.id"
          class="text-black"
        >
          {{ player.name?.full ?? player.name }}
        </option>
      </select>
      <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
        <ArrowDownIcon color="ffffff" />
      </div>
    </div>

    <!-- Pitch type filter buttons — shown once a player is selected -->
    <div v-if="selectedPlayerId && !isLoading" class="flex gap-1.5 mb-3 flex-wrap justify-center">
      <button
        v-for="pt in PITCH_TYPES"
        :key="pt.key"
        @click="activePitchType = pt.key"
        :class="[
          'px-3 py-1 rounded-full text-xs font-bold tracking-wide transition-colors',
          activePitchType === pt.key
            ? 'bg-red-600 text-white'
            : 'bg-white/10 text-white/60 hover:bg-white/20 hover:text-white'
        ]"
      >{{ pt.label }}</button>
    </div>

    <div v-if="isLoading" class="flex items-center justify-center h-40 text-gray-400 text-sm">
      Loading…
    </div>
    <div v-else-if="!selectedPlayerId" class="flex items-center justify-center h-40 text-gray-500 text-sm">
      Select a pitcher above to view velocity zones.
    </div>
    <div v-else-if="pitches.length === 0" class="flex items-center justify-center h-40 text-gray-500 text-sm">
      No pitch velocity data available.
    </div>

    <div v-else>
      <!-- grid container — mirrors RN <View style={styles.container}> -->
      <div
        class="vzc-container"
        style="position:relative; width:100%; aspect-ratio:1/1; max-width:440px; margin:0 auto;"
      >
        <!-- background image @ 0.6 opacity — mirrors RN <Image style={[styles.background,{opacity:0.6}]}> -->
        <div class="vzc-bg" />

        <!-- overlay — mirrors RN <View style={styles.overlay}> -->
        <div style="position:absolute;inset:0;">
          <div
            v-for="(cell, idx) in grid.cells"
            :key="`${cell.row}-${cell.col}-${idx}`"
            :style="cellStyle(cell)"
          >
            <span class="vzc-cell-text">{{ cell.avg.toFixed(0) }}</span>
          </div>
        </div>
      </div>

      <p class="text-center text-xs text-gray-400 mt-2">
        {{ pitches.length }} pitches · {{ props.gridSize }}×{{ props.gridSize }} grid · avg mph per zone
      </p>
    </div>
  </div>
</template>

<style scoped>
.vzc-container {
  overflow: hidden;
}

.vzc-bg {
  position: absolute;
  inset: 0;
  background-image: url('../../assets/img/training/catcherimagenew.png');
  background-repeat: no-repeat;
  background-size: 100% 100%;
  background-position: center;
  opacity: 0.6;
}

.vzc-cell-text {
  color: #ffffff;
  font-size: clamp(8px, 1.8vw, 14px);
  font-weight: 600;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.85);
  line-height: 1;
}
</style>
