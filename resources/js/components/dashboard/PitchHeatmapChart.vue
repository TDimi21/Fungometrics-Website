<script setup>
import { ref, watch, nextTick, computed } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { usePlayerStore } from '@/store/players'
import ArrowDownIcon from '@/components/icons/ArrowDownIcon.vue'

const BASE_GRID   = 60
const GRID_LINES  = 5
const BLUR_RADIUS = 5
const BLUR_SIGMA  = 2.2

const PITCH_TYPES = [
  { key: 'ALL',   label: 'All'   },
  { key: 'FB',    label: 'FB'    },
  { key: 'CB',    label: 'CB'    },
  { key: 'SL',    label: 'SL'    },
  { key: 'CH',    label: 'CH'    },
]
const KNOWN_TYPES = ['FB', 'CB', 'SL', 'CH']

const TRAJ_TYPES = [
  { key: 'ALL', label: 'All' },
  { key: 'SM',  label: 'Swing & Miss' },
  { key: 'TK',  label: 'Take' },
]

const STOPS = [
  { t: 0,    r: 0,   g: 70,  b: 255 },
  { t: 0.45, r: 0,   g: 210, b: 120 },
  { t: 0.7,  r: 255, g: 220, b: 0   },
  { t: 1,    r: 255, g: 50,  b: 0   },
]

function interpolateColor(t) {
  const c = Math.max(0, Math.min(1, t))
  let a = STOPS[0], b = STOPS[STOPS.length - 1]
  for (let i = 0; i < STOPS.length - 1; i++) {
    if (c >= STOPS[i].t && c <= STOPS[i + 1].t) { a = STOPS[i]; b = STOPS[i + 1]; break }
  }
  const span = b.t - a.t || 1
  const tt   = (c - a.t) / span
  return {
    r:  Math.round(a.r  + (b.r  - a.r)  * tt),
    g:  Math.round(a.g  + (b.g  - a.g)  * tt),
    bl: Math.round(a.b  + (b.b  - a.b)  * tt),
    a:  0.2 + 0.75 * c,
  }
}

function drawOnCanvas(canvas, pitchData, pitchType, trajectory) {
  if (!canvas) return
  const size = BASE_GRID
  const ctx  = canvas.getContext('2d')
  ctx.clearRect(0, 0, size, size)
  if (!pitchData.length) return

  const counts = new Float32Array(size * size)
  pitchData.forEach((item) => {
    const traj = String(item?.trajectory ?? '').toUpperCase()
    if (traj !== 'SM' && traj !== 'TK') return
    if (trajectory !== 'ALL' && traj !== trajectory) return
    if (pitchType !== 'ALL') {
      const t = String(item?.type_throw ?? '').toUpperCase()
      if (pitchType === 'OTHER') { if (KNOWN_TYPES.includes(t)) return }
      else { if (t !== pitchType) return }
    }
    const mark = item?.pitch_mark || item?.pitch_location || 0
    if (!mark || mark <= 0) return
    const row = Math.floor((mark - 1) / size)
    const col = (mark - 1) % size
    if (row >= 0 && row < size && col >= 0 && col < size)
      counts[row * size + col] += 1
  })

  const r = BLUR_RADIUS, sigma = BLUR_SIGMA
  const kernel = []
  for (let y = -r; y <= r; y++)
    for (let x = -r; x <= r; x++)
      kernel.push({ x, y, w: Math.exp(-(x * x + y * y) / (2 * sigma * sigma)) })

  const blurred = new Float32Array(size * size)
  for (let row = 0; row < size; row++) {
    for (let col = 0; col < size; col++) {
      let sum = 0
      for (const { x, y, w } of kernel) {
        const rr = row + y, cc = col + x
        if (rr < 0 || rr >= size || cc < 0 || cc >= size) continue
        const v = counts[rr * size + cc]
        if (!v) continue
        sum += v * w
      }
      blurred[row * size + col] = sum
    }
  }

  let maxVal = 0, minVal = null
  blurred.forEach((v) => { if (v > 0) { maxVal = Math.max(maxVal, v); minVal = minVal == null ? v : Math.min(minVal, v) } })
  if (maxVal === 0) return
  const minV = minVal ?? 0

  const imageData = ctx.createImageData(size, size)
  const d = imageData.data
  for (let i = 0; i < size * size; i++) {
    const v = blurred[i]
    if (v <= 0) { d[i * 4 + 3] = 0; continue }
    const tRaw = maxVal > minV ? (v - minV) / (maxVal - minV) : 0
    const t    = Math.pow(Math.max(0, Math.min(1, tRaw)), 0.6)
    const c    = interpolateColor(t)
    d[i * 4]     = c.r
    d[i * 4 + 1] = c.g
    d[i * 4 + 2] = c.bl
    d[i * 4 + 3] = Math.round(c.a * 255)
  }
  ctx.putImageData(imageData, 0, 0)
}

const props = defineProps({
  item:              { type: Object,           default: null },
  pitchTypeFilter:   { type: [Number, String], default: null },
  selectedPlayerKey: { type: [Number, String], default: null },
  namePlayerFilter:  { type: String,           default: null },
})

const { axiosGet } = useAxiosAuth()
const playerStore  = usePlayerStore()

const compareMode      = ref(false)
const activePitchType  = ref('ALL')
const activeTrajectory = ref('ALL')

const playerAId  = ref(props.item?.id ?? null)
const pitchesA   = ref([])
const loadingA   = ref(false)
const canvasRefA = ref(null)

const playerBId  = ref(null)
const pitchesB   = ref([])
const loadingB   = ref(false)
const canvasRefB = ref(null)

async function fetchPlayer(playerId, pitchesRef, loadingRef) {
  if (!playerId) { pitchesRef.value = []; return }
  try {
    loadingRef.value = true
    pitchesRef.value = []
    const res = await axiosGet(`coach/pitcher/smtake/${playerId}`)
    pitchesRef.value = res.data.data ?? []
  } catch {
    pitchesRef.value = []
  } finally {
    loadingRef.value = false
  }
}

if (props.item?.id) fetchPlayer(props.item.id, pitchesA, loadingA)

const onChangeA = () => fetchPlayer(playerAId.value, pitchesA, loadingA)
const onChangeB = () => fetchPlayer(playerBId.value, pitchesB, loadingB)

function redrawA() { drawOnCanvas(canvasRefA.value, pitchesA.value, activePitchType.value, activeTrajectory.value) }
function redrawB() { drawOnCanvas(canvasRefB.value, pitchesB.value, activePitchType.value, activeTrajectory.value) }

watch([pitchesA, activePitchType, activeTrajectory], () => nextTick(redrawA), { deep: false })
watch([pitchesB, activePitchType, activeTrajectory], () => nextTick(redrawB), { deep: false })
watch(compareMode, () => nextTick(() => { redrawA(); redrawB() }))

function countPitches(list, trajectory) {
  return list.filter((item) => {
    const traj = String(item?.trajectory ?? '').toUpperCase()
    if (traj !== 'SM' && traj !== 'TK') return false
    return trajectory === 'ALL' || traj === trajectory
  }).length
}
const pitchCountA = computed(() => countPitches(pitchesA.value, activeTrajectory.value))
const pitchCountB = computed(() => countPitches(pitchesB.value, activeTrajectory.value))

function playerName(id) {
  if (!id) return null
  const p = playerStore.players?.find(pl => pl.id === id || pl.id === Number(id))
  return p ? (p.name?.full ?? p.name) : null
}
</script>

<template>
  <div class="w-full">
    <!-- Header -->
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-sm font-black uppercase tracking-widest text-white">
        Pitch Heatmap (S/M &amp; Take)
      </h3>
      <button
        @click="compareMode = !compareMode"
        :class="[
          'text-xs font-bold px-3 py-1 rounded-full transition-colors',
          compareMode ? 'bg-yellow-500 text-black' : 'bg-white/10 text-white/70 hover:bg-white/20 hover:text-white',
        ]"
      >{{ compareMode ? 'Single' : 'Compare' }}</button>
    </div>

    <!-- Player selectors -->
    <div :class="compareMode ? 'grid grid-cols-2 gap-2 mb-3' : 'mb-4'">
      <div class="relative">
        <select
          v-model="playerAId"
          @change="onChangeA"
          class="w-full rounded-lg border border-white/20 bg-white/10 text-white text-sm py-2 pl-3 pr-8 appearance-none outline-none cursor-pointer"
        >
          <option :value="null" disabled>{{ compareMode ? 'Player A…' : 'Select a pitcher…' }}</option>
          <option v-for="player in playerStore.players" :key="player.id" :value="player.id" class="text-black">
            {{ player.name?.full ?? player.name }}
          </option>
        </select>
        <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
          <ArrowDownIcon color="ffffff" />
        </div>
      </div>
      <div v-if="compareMode" class="relative">
        <select
          v-model="playerBId"
          @change="onChangeB"
          class="w-full rounded-lg border border-white/20 bg-white/10 text-white text-sm py-2 pl-3 pr-8 appearance-none outline-none cursor-pointer"
        >
          <option :value="null" disabled>Player B…</option>
          <option v-for="player in playerStore.players" :key="player.id" :value="player.id" class="text-black">
            {{ player.name?.full ?? player.name }}
          </option>
        </select>
        <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
          <ArrowDownIcon color="ffffff" />
        </div>
      </div>
    </div>

    <!-- Pitch type filter -->
    <div class="flex gap-1.5 mb-2 flex-wrap justify-center">
      <button
        v-for="pt in PITCH_TYPES" :key="pt.key"
        @click="activePitchType = pt.key"
        :class="[
          'px-3 py-1 rounded-full text-xs font-bold tracking-wide transition-colors',
          activePitchType === pt.key ? 'bg-red-600 text-white' : 'bg-white/10 text-white/60 hover:bg-white/20 hover:text-white',
        ]"
      >{{ pt.label }}</button>
    </div>

    <!-- Trajectory filter -->
    <div class="flex gap-1.5 mb-3 flex-wrap justify-center">
      <button
        v-for="tr in TRAJ_TYPES" :key="tr.key"
        @click="activeTrajectory = tr.key"
        :class="[
          'px-3 py-1 rounded-full text-xs font-bold tracking-wide transition-colors',
          activeTrajectory === tr.key ? 'bg-blue-600 text-white' : 'bg-white/10 text-white/60 hover:bg-white/20 hover:text-white',
        ]"
      >{{ tr.label }}</button>
    </div>

    <!-- Charts grid -->
    <div :class="compareMode ? 'grid grid-cols-2 gap-3' : ''">

      <!-- Player A -->
      <div>
        <div v-if="compareMode && playerAId" class="text-center text-xs font-bold text-white/80 mb-1 truncate">
          {{ playerName(playerAId) ?? 'Player A' }}
        </div>
        <div v-if="loadingA" class="flex items-center justify-center h-32 text-gray-400 text-xs">Loading…</div>
        <div v-else-if="!playerAId" class="flex items-center justify-center h-32 text-gray-500 text-xs">Select a pitcher.</div>
        <div v-else-if="pitchesA.length === 0" class="flex items-center justify-center h-32 text-gray-500 text-xs">No data.</div>
        <template v-else>
          <div class="phc-legend mb-1">
            <span class="phc-legend-label">Least</span>
            <div class="phc-legend-bar"></div>
            <span class="phc-legend-label">Most</span>
          </div>
          <div class="phc-container">
            <div class="phc-bg" />
            <canvas ref="canvasRefA" :width="60" :height="60" class="phc-canvas" />
            <div class="phc-gridlines">
              <div v-for="i in GRID_LINES + 1" :key="`ha-${i}`" class="phc-hline" :style="{ top: `${((i-1)/GRID_LINES)*100}%` }" />
              <div v-for="i in GRID_LINES + 1" :key="`va-${i}`" class="phc-vline" :style="{ left: `${((i-1)/GRID_LINES)*100}%` }" />
            </div>
          </div>
          <p class="text-center text-xs text-gray-400 mt-1">{{ pitchCountA }} pitches</p>
        </template>
      </div>

      <!-- Player B -->
      <div v-if="compareMode">
        <div v-if="playerBId" class="text-center text-xs font-bold text-white/80 mb-1 truncate">
          {{ playerName(playerBId) ?? 'Player B' }}
        </div>
        <div v-if="loadingB" class="flex items-center justify-center h-32 text-gray-400 text-xs">Loading…</div>
        <div v-else-if="!playerBId" class="flex items-center justify-center h-32 text-gray-500 text-xs">Select player B.</div>
        <div v-else-if="pitchesB.length === 0" class="flex items-center justify-center h-32 text-gray-500 text-xs">No data.</div>
        <template v-else>
          <div class="phc-legend mb-1">
            <span class="phc-legend-label">Least</span>
            <div class="phc-legend-bar"></div>
            <span class="phc-legend-label">Most</span>
          </div>
          <div class="phc-container">
            <div class="phc-bg" />
            <canvas ref="canvasRefB" :width="60" :height="60" class="phc-canvas" />
            <div class="phc-gridlines">
              <div v-for="i in GRID_LINES + 1" :key="`hb-${i}`" class="phc-hline" :style="{ top: `${((i-1)/GRID_LINES)*100}%` }" />
              <div v-for="i in GRID_LINES + 1" :key="`vb-${i}`" class="phc-vline" :style="{ left: `${((i-1)/GRID_LINES)*100}%` }" />
            </div>
          </div>
          <p class="text-center text-xs text-gray-400 mt-1">{{ pitchCountB }} pitches</p>
        </template>
      </div>

    </div>
  </div>
</template>

<style scoped>
.phc-legend {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 3px 8px;
  background: rgba(0,0,0,0.6);
  border-radius: 6px;
}
.phc-legend-label {
  color: #fff;
  font-size: 0.65rem;
  font-weight: 600;
  white-space: nowrap;
}
.phc-legend-bar {
  flex: 1;
  height: 10px;
  border-radius: 4px;
  background: linear-gradient(
    to right,
    rgba(0,70,255,0.45),
    rgba(0,210,120,0.7),
    rgba(255,220,0,0.87),
    rgba(255,50,0,0.95)
  );
}
.phc-container {
  position: relative;
  width: 100%;
  aspect-ratio: 1 / 1;
  overflow: hidden;
  border-radius: 8px;
}
.phc-bg {
  position: absolute;
  inset: 0;
  background-image: url('../../assets/img/training/catcherimagenew.png');
  background-repeat: no-repeat;
  background-size: 100% 100%;
  background-position: center;
  opacity: 0.6;
}
.phc-canvas {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  image-rendering: pixelated;
}
.phc-gridlines {
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.phc-hline {
  position: absolute;
  left: 0; right: 0;
  height: 1px;
  background-color: rgba(255,255,255,0.45);
}
.phc-vline {
  position: absolute;
  top: 0; bottom: 0;
  width: 1px;
  background-color: rgba(255,255,255,0.45);
}
</style>
