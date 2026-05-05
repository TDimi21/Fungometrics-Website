<script>
const BASE_GRID = 60
</script>

<script setup>
import { ref, watch, computed, nextTick } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { usePlayerStore } from '@/store/players'
import ArrowDownIcon from '@/components/icons/ArrowDownIcon.vue'

const GRID_LINES  = 5
const BLUR_RADIUS = 8
const BLUR_SIGMA  = 3.5

const PITCH_TYPES = [
  { key: 'ALL', label: 'All' },
  { key: 'FB',  label: 'FB'  },
  { key: 'CB',  label: 'CB'  },
  { key: 'SL',  label: 'SL'  },
  { key: 'CH',  label: 'CH'  },
  { key: 'OTHER', label: 'Other' },
]
const KNOWN_PITCH_TYPES = ['FB', 'CB', 'SL', 'CH']

const VIEW_TYPES = [
  { key: 'SM',  label: 'Swing & Miss' },
  { key: 'TK',  label: 'Take'         },
  { key: 'ALL', label: 'All'          },
]

// Gradient stops for each view
const STOPS = [
  { t: 0,    r: 0,   g: 0,   b: 255, a: 0    },
  { t: 0.15, r: 0,   g: 40,  b: 255, a: 0.5  },
  { t: 0.35, r: 0,   g: 180, b: 220, a: 0.7  },
  { t: 0.55, r: 0,   g: 220, b: 80,  a: 0.85 },
  { t: 0.75, r: 255, g: 220, b: 0,   a: 0.9  },
  { t: 1,    r: 255, g: 30,  b: 0,   a: 0.95 },
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
    a:  a.a + (b.a - a.a) * tt,
  }
}

// ─── props / state ────────────────────────────────────────────────────────────
const props = defineProps({
  item: { type: Object, default: null },
})

const { axiosGet }     = useAxiosAuth()
const playerStore      = usePlayerStore()
const isLoading        = ref(false)
const pitches          = ref([])
const selectedPlayerId = ref(props.item?.id ?? null)
const activeView       = ref('SM')
const activePitchType  = ref('ALL')
const canvasRef        = ref(null)

// ─── fetch ────────────────────────────────────────────────────────────────────
const fetchData = async (playerId) => {
  if (!playerId) return
  try {
    isLoading.value = true
    pitches.value   = []
    const res       = await axiosGet(`coach/pitcher/smtake/${playerId}`)
    pitches.value   = res.data.data ?? []
  } catch {
    pitches.value = []
  } finally {
    isLoading.value = false
  }
}
const onPlayerChange = () => fetchData(selectedPlayerId.value)
if (props.item?.id) fetchData(props.item.id)

// ─── canvas draw ──────────────────────────────────────────────────────────────
function drawHeatmap() {
  const canvas = canvasRef.value
  if (!canvas || !pitches.value.length) return
  const size = BASE_GRID
  const ctx  = canvas.getContext('2d')
  ctx.clearRect(0, 0, size, size)

  const counts = new Float32Array(size * size)

  pitches.value.forEach((item) => {
    if (activePitchType.value !== 'ALL') {
      const t = String(item?.type_throw ?? '').toUpperCase()
      if (activePitchType.value === 'OTHER') {
        if (KNOWN_PITCH_TYPES.includes(t)) return
      } else {
        if (t !== activePitchType.value) return
      }
    }
    const traj = String(item?.trajectory ?? '').toUpperCase()
    if (activeView.value === 'SM' && traj !== 'SM') return
    if (activeView.value === 'TK' && traj !== 'TK') return
    if (activeView.value === 'ALL' && traj !== 'SM' && traj !== 'TK') return

    const mark = item?.pitch_mark || 0
    if (!mark || mark <= 0) return
    const row = Math.floor((mark - 1) / size)
    const col = (mark - 1) % size
    if (row >= 0 && row < size && col >= 0 && col < size)
      counts[row * size + col] += 1
  })

  // Gaussian blur
  const r = BLUR_RADIUS, sigma = BLUR_SIGMA
  const kernel = []
  for (let y = -r; y <= r; y++)
    for (let x = -r; x <= r; x++)
      kernel.push({ x, y, w: Math.exp(-(x * x + y * y) / (2 * sigma * sigma)) })

  const blurred = new Float32Array(size * size)
  for (let row = 0; row < size; row++) {
    for (let col = 0; col < size; col++) {
      let sum = 0, wsum = 0
      for (const { x, y, w } of kernel) {
        const rr = row + y, cc = col + x
        if (rr < 0 || rr >= size || cc < 0 || cc >= size) continue
        const v = counts[rr * size + cc]
        if (!v) continue
        sum += v * w; wsum += w
      }
      blurred[row * size + col] = wsum ? sum / wsum : 0
    }
  }

  let maxVal = 0, minVal = Infinity
  blurred.forEach((v) => { if (v > 0) { maxVal = Math.max(maxVal, v); minVal = Math.min(minVal, v) } })
  if (maxVal === 0) return

  const imageData = ctx.createImageData(size, size)
  const d = imageData.data
  for (let i = 0; i < size * size; i++) {
    const v = blurred[i]
    if (v <= 0) { d[i * 4 + 3] = 0; continue }
    const tRaw = (v - minVal) / (maxVal - minVal)
    const t    = Math.pow(Math.max(0, Math.min(1, tRaw)), 0.55)
    const c    = interpolateColor(t)
    d[i * 4]     = c.r
    d[i * 4 + 1] = c.g
    d[i * 4 + 2] = c.bl
    d[i * 4 + 3] = Math.round(c.a * 255)
  }
  ctx.putImageData(imageData, 0, 0)
}

watch(
  [pitches, activePitchType, activeView],
  () => nextTick(drawHeatmap),
  { deep: false }
)

// ─── footer label ──────────────────────────────────────────────────────────────
const filteredCount = computed(() =>
  pitches.value.filter((item) => {
    const traj = String(item?.trajectory ?? '').toUpperCase()
    if (activeView.value === 'SM')  return traj === 'SM'
    if (activeView.value === 'TK')  return traj === 'TK'
    return traj === 'SM' || traj === 'TK'
  }).length
)
const footerLabel = computed(() => {
  if (activeView.value === 'SM') return `${filteredCount.value} Swing & Miss Pitches`
  if (activeView.value === 'TK') return `${filteredCount.value} Take Pitches`
  return `${filteredCount.value} Total Pitches`
})
</script>

<template>
  <div class="w-full">
    <!-- Player dropdown -->
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

    <template v-if="selectedPlayerId && !isLoading">
      <!-- View toggle -->
      <div class="flex gap-1.5 mb-2 justify-center flex-wrap">
        <button
          v-for="v in VIEW_TYPES"
          :key="v.key"
          @click="activeView = v.key"
          :class="[
            'px-3 py-1 rounded-full text-xs font-bold tracking-wide transition-colors',
            activeView === v.key
              ? (v.key === 'SM' ? 'bg-red-700 text-white' : v.key === 'TK' ? 'bg-blue-600 text-white' : 'bg-purple-600 text-white')
              : 'bg-white/10 text-white/60 hover:bg-white/20 hover:text-white',
          ]"
        >{{ v.label }}</button>
      </div>

      <!-- Pitch type filter -->
      <div class="flex gap-1.5 mb-3 justify-center flex-wrap">
        <button
          v-for="pt in PITCH_TYPES"
          :key="pt.key"
          @click="activePitchType = pt.key"
          :class="[
            'px-2.5 py-0.5 rounded-full text-xs font-semibold tracking-wide transition-colors',
            activePitchType === pt.key
              ? 'bg-white/30 text-white'
              : 'bg-white/8 text-white/40 hover:bg-white/15 hover:text-white/70',
          ]"
        >{{ pt.label }}</button>
      </div>
    </template>

    <div v-if="isLoading" class="flex items-center justify-center h-40 text-gray-400 text-sm">
      Loading…
    </div>
    <div v-else-if="!selectedPlayerId" class="flex items-center justify-center h-40 text-gray-500 text-sm">
      Select a pitcher above to view zones.
    </div>
    <div v-else-if="pitches.length === 0" class="flex items-center justify-center h-40 text-gray-500 text-sm">
      No S/M or Take data available.
    </div>

    <div v-else class="smt-wrapper">
      <!-- legend -->
      <div class="smt-legend">
        <span class="smt-legend-label">Least</span>
        <div class="smt-legend-bar"></div>
        <span class="smt-legend-label">Most</span>
      </div>

      <div class="smt-container">
        <div class="smt-bg" />
        <canvas ref="canvasRef" :width="60" :height="60" class="smt-canvas" />
        <div class="smt-gridlines">
          <div v-for="i in GRID_LINES + 1" :key="`h-${i}`" class="smt-hline" :style="{ top: `${((i-1)/GRID_LINES)*100}%` }" />
          <div v-for="i in GRID_LINES + 1" :key="`v-${i}`" class="smt-vline" :style="{ left: `${((i-1)/GRID_LINES)*100}%` }" />
        </div>
      </div>

      <div class="smt-footer">{{ footerLabel }}</div>
    </div>
  </div>
</template>

<style scoped>
.smt-wrapper {
  width: 100%;
  max-width: 440px;
  margin: 0 auto;
  border-radius: 8px;
  overflow: hidden;
}
.smt-legend {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  padding: 4px 8px;
  background: rgba(0,0,0,0.6);
  border-radius: 6px;
}
.smt-legend-label {
  color: #fff;
  font-size: 0.7rem;
  font-weight: 600;
  white-space: nowrap;
}
.smt-legend-bar {
  flex: 1;
  height: 12px;
  border-radius: 4px;
  background: linear-gradient(
    to right,
    rgba(0,40,255,0.5),
    rgba(0,180,220,0.7),
    rgba(0,220,80,0.85),
    rgba(255,220,0,0.9),
    rgba(255,30,0,0.95)
  );
}
.smt-container {
  position: relative;
  width: 100%;
  aspect-ratio: 1 / 1;
  overflow: hidden;
}
.smt-bg {
  position: absolute;
  inset: 0;
  background-image: url('../../assets/img/training/catcherimagenew.png');
  background-repeat: no-repeat;
  background-size: 100% 100%;
  background-position: center;
  opacity: 0.6;
}
.smt-canvas {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  image-rendering: smooth;
  image-rendering: -webkit-optimize-contrast;
}
.smt-gridlines {
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.smt-hline {
  position: absolute;
  left: 0; right: 0;
  height: 1px;
  background-color: rgba(255,255,255,0.45);
}
.smt-vline {
  position: absolute;
  top: 0; bottom: 0;
  width: 1px;
  background-color: rgba(255,255,255,0.45);
}
.smt-footer {
  background-color: #7f1d1d;
  color: #ffffff;
  text-align: center;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  padding: 8px 12px;
}
</style>
import { useAxiosAuth } from '@/composables/axios-auth.js'
