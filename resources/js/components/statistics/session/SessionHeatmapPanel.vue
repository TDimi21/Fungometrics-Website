<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'

const props = defineProps({
  rows: { type: Array, default: () => [] },
  baseGrid: { type: Number, default: 60 },
  gridSize: { type: Number, default: 5 },
  markMode: { type: String, default: 'pitch' }, // pitch | field
  background: { type: String, default: 'catcher' }, // catcher | field
  filterMode: { type: String, default: 'all' }, // all | sm
  filterOptions: { type: String, default: 'pitch' }, // pitch | batting-spray
})

const BLUR_RADIUS = 5
const BLUR_SIGMA = 2.2

const activeFilter = ref('ALL')
const canvasRef = ref(null)

const TYPE_OPTIONS = [
  { key: 'ALL', label: 'ALL' },
  { key: 'FB', label: 'FASTBALL' },
  { key: 'CB', label: 'CURVEBALL' },
  { key: 'CH', label: 'CHANGE-UP' },
  { key: 'SL', label: 'SLIDER' },
  { key: 'OTHER', label: 'OTHER' },
]

const BATTING_SPRAY_OPTIONS = [
  { key: 'ALL', label: 'ALL' },
  { key: 'HARD', label: 'HARD' },
  { key: 'AVG', label: 'AVG' },
  { key: 'WEAK', label: 'WEAK' },
  { key: 'LD', label: 'LD' },
  { key: 'GB', label: 'GB' },
  { key: 'FB', label: 'FB' },
  { key: 'PF', label: 'PF' },
]

const filterOptionsList = computed(() => {
  return props.filterOptions === 'batting-spray'
    ? BATTING_SPRAY_OPTIONS
    : TYPE_OPTIONS
})

const STOPS = [
  { t: 0, r: 0, g: 70, b: 255 },
  { t: 0.45, r: 0, g: 210, b: 120 },
  { t: 0.7, r: 255, g: 220, b: 0 },
  { t: 1, r: 255, g: 50, b: 0 },
]

const normalizePitchType = (row) => {
  const raw = String(
    row?.type_throw ??
      row?.type_of_throw ??
      row?.pitch_type ??
      row?.pitch_name ??
      row?.type ??
      '',
  )
    .trim()
    .toUpperCase()

  if (!raw) {
    const id = Number(row?.type_of_throw_id ?? row?.type_id ?? 0)
    if (id === 1) return 'FB'
    if (id === 2) return 'CH'
    if (id === 3) return 'SL'
    if (id === 4) return 'CB'
    return 'OTHER'
  }

  if (['FB', 'FASTBALL'].includes(raw)) return 'FB'
  if (['CB', 'CV', 'CURVEBALL', 'CURVE'].includes(raw)) return 'CB'
  if (['CH', 'CHANGEUP', 'CHANGE-UP'].includes(raw)) return 'CH'
  if (['SL', 'SLIDER'].includes(raw)) return 'SL'
  return 'OTHER'
}

const normalizeQualityOfContact = (row) => {
  const raw = String(row?.quality_of_contact ?? row?.contact_quality ?? '')
    .trim()
    .toUpperCase()

  if (['H', 'HARD'].includes(raw)) return 'HARD'
  if (['A', 'AVG', 'AVERAGE'].includes(raw)) return 'AVG'
  if (['W', 'WEAK'].includes(raw)) return 'WEAK'
  return ''
}

const normalizeHitTrajectory = (row) => {
  const raw = String(row?.type_of_hit ?? row?.trajectory ?? row?.hit_type ?? '')
    .trim()
    .toUpperCase()

  if (raw.includes('LD') || raw.includes('LINE')) return 'LD'
  if (raw.includes('GB') || raw.includes('GROUND')) return 'GB'
  if (raw.includes('PF') || raw.includes('POP')) return 'PF'
  if (raw.includes('FB') || raw.includes('FLY')) return 'FB'
  return ''
}

const rowPassesFilter = (row) => {
  if (activeFilter.value === 'ALL') return true

  if (props.filterOptions === 'batting-spray') {
    if (['HARD', 'AVG', 'WEAK'].includes(activeFilter.value)) {
      return normalizeQualityOfContact(row) === activeFilter.value
    }
    if (['LD', 'GB', 'FB', 'PF'].includes(activeFilter.value)) {
      return normalizeHitTrajectory(row) === activeFilter.value
    }
    return true
  }

  return normalizePitchType(row) === activeFilter.value
}

const isSwingMissRow = (row) => {
  const textFields = [
    row?.trajectory,
    row?.type_of_hit,
    row?.type_of_hit_msg,
    row?.result,
    row?.pitch_result,
    row?.ball_strike,
    row?.contact_trajectory,
    row?.play_result,
  ]
    .map((v) => String(v ?? '').toUpperCase().trim())
    .filter(Boolean)

  const hasSmText = textFields.some((value) => {
    return (
      value === 'SM' ||
      value === 'S/M' ||
      value === 'SWING_MISS' ||
      value === 'SWING & MISS' ||
      value === 'SWING AND MISS' ||
      value === 'WHIFF' ||
      value.includes('S/M') ||
      value.includes('SWING') && value.includes('MISS')
    )
  })

  if (hasSmText) return true

  const numericMarkers = [
    Number(row?.trajectory_id ?? row?.trajectoryId ?? NaN),
    Number(row?.type_of_hit_id ?? row?.typeOfHitId ?? NaN),
  ]
  return numericMarkers.some((v) => Number.isFinite(v) && v === 5)
}

const getMark = (row) => {
  if (props.markMode === 'field') {
    return Number(row?.field_mark ?? row?.ground_location_id ?? row?.field_location ?? 0)
  }
  return Number(row?.pitch_mark ?? row?.pitch_location ?? row?.zone ?? 0)
}

const filteredRows = computed(() => {
  return (Array.isArray(props.rows) ? props.rows : []).filter((row) => {
    if (!rowPassesFilter(row)) return false
    if (props.filterMode === 'sm' && !isSwingMissRow(row)) return false
    const mark = getMark(row)
    const maxMark = props.baseGrid * props.baseGrid
    return Number.isFinite(mark) && mark > 0 && mark <= maxMark
  })
})

const backgroundClass = computed(() => {
  return props.background === 'field' ? 'smap-bg-field' : 'smap-bg-catcher'
})

const interpolateColor = (tIn) => {
  const t = Math.max(0, Math.min(1, tIn))
  let a = STOPS[0]
  let b = STOPS[STOPS.length - 1]
  for (let i = 0; i < STOPS.length - 1; i += 1) {
    if (t >= STOPS[i].t && t <= STOPS[i + 1].t) {
      a = STOPS[i]
      b = STOPS[i + 1]
      break
    }
  }
  const span = b.t - a.t || 1
  const tt = (t - a.t) / span
  return {
    r: Math.round(a.r + (b.r - a.r) * tt),
    g: Math.round(a.g + (b.g - a.g) * tt),
    bl: Math.round(a.b + (b.b - a.b) * tt),
    a: 0.2 + 0.75 * t,
  }
}

const drawHeatmap = () => {
  const canvas = canvasRef.value
  if (!canvas) return

  const size = Number(props.baseGrid) || 60
  const ctx = canvas.getContext('2d')
  ctx.clearRect(0, 0, size, size)

  if (filteredRows.value.length === 0) return

  const counts = new Float32Array(size * size)
  filteredRows.value.forEach((row) => {
    const mark = getMark(row)
    if (!mark || mark <= 0) return
    const r = Math.floor((mark - 1) / size)
    const c = (mark - 1) % size
    if (r >= 0 && r < size && c >= 0 && c < size) counts[r * size + c] += 1
  })

  const kernel = []
  for (let y = -BLUR_RADIUS; y <= BLUR_RADIUS; y += 1) {
    for (let x = -BLUR_RADIUS; x <= BLUR_RADIUS; x += 1) {
      kernel.push({ x, y, w: Math.exp(-(x * x + y * y) / (2 * BLUR_SIGMA * BLUR_SIGMA)) })
    }
  }

  const blurred = new Float32Array(size * size)
  for (let r = 0; r < size; r += 1) {
    for (let c = 0; c < size; c += 1) {
      let sum = 0
      kernel.forEach(({ x, y, w }) => {
        const rr = r + y
        const cc = c + x
        if (rr < 0 || rr >= size || cc < 0 || cc >= size) return
        const v = counts[rr * size + cc]
        if (!v) return
        sum += v * w
      })
      blurred[r * size + c] = sum
    }
  }

  let maxVal = 0
  let minVal = null
  blurred.forEach((v) => {
    if (v > 0) {
      maxVal = Math.max(maxVal, v)
      minVal = minVal == null ? v : Math.min(minVal, v)
    }
  })
  if (maxVal <= 0) return

  const min = minVal ?? 0
  const img = ctx.createImageData(size, size)
  const data = img.data

  for (let i = 0; i < size * size; i += 1) {
    const v = blurred[i]
    if (v <= 0) {
      data[i * 4 + 3] = 0
      continue
    }
    const tRaw = maxVal > min ? (v - min) / (maxVal - min) : 0
    const t = Math.pow(Math.max(0, Math.min(1, tRaw)), 0.6)
    const c = interpolateColor(t)
    data[i * 4] = c.r
    data[i * 4 + 1] = c.g
    data[i * 4 + 2] = c.bl
    data[i * 4 + 3] = Math.round(c.a * 255)
  }

  ctx.putImageData(img, 0, 0)
}

watch([filteredRows, () => props.baseGrid, activeFilter], () => nextTick(drawHeatmap), { deep: false })

watch(() => props.filterOptions, () => {
  activeFilter.value = 'ALL'
})

onMounted(() => {
  nextTick(drawHeatmap)
})
</script>

<template>
  <div>
    <div class="mb-3 flex flex-wrap gap-2 justify-center">
      <button
        v-for="opt in filterOptionsList"
        :key="opt.key"
        class="px-3 py-1 rounded-full text-xs font-bold tracking-wide border transition"
        :class="activeFilter === opt.key ? 'bg-[#ff2d55] border-[#ff2d55] text-white' : 'bg-white/5 border-white/15 text-white/70 hover:bg-white/10'"
        @click="activeFilter = opt.key"
      >
        {{ opt.label }}
      </button>
    </div>

    <div class="smap-wrap">
      <div :class="['smap-bg', backgroundClass]"></div>

      <canvas
        ref="canvasRef"
        class="smap-canvas"
        :width="baseGrid"
        :height="baseGrid"
      ></canvas>

      <div class="smap-lines">
        <div
          v-for="i in gridSize + 1"
          :key="`h-${i}`"
          class="smap-line-h"
          :style="{ top: `${((i - 1) / gridSize) * 100}%` }"
        />
        <div
          v-for="i in gridSize + 1"
          :key="`v-${i}`"
          class="smap-line-v"
          :style="{ left: `${((i - 1) / gridSize) * 100}%` }"
        />
      </div>
    </div>

    <p class="mt-2 text-center text-xs text-white/60">
      <span v-if="filterMode === 'sm'">{{ filteredRows.length }} Swing &amp; Miss Pitches</span>
      <span v-else>{{ filteredRows.length }} pitches shown</span>
    </p>
  </div>
</template>

<style scoped>
.smap-wrap {
  position: relative;
  width: min(100%, 680px);
  aspect-ratio: 1 / 1;
  margin: 0 auto;
  overflow: hidden;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.14);
}

.smap-bg {
  position: absolute;
  inset: 0;
  opacity: 0.7;
  background-repeat: no-repeat;
  background-position: center;
  background-size: 100% 100%;
}

.smap-bg-catcher {
  background-image: url('@/assets/img/training/catcherimagenew.png');
}

.smap-bg-field {
  background-image: url('@/assets/img/training/fieldbatting.png');
}

.smap-canvas {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  image-rendering: auto;
}

.smap-lines {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.smap-line-h,
.smap-line-v {
  position: absolute;
  background: rgba(255, 255, 255, 0.5);
}

.smap-line-h {
  left: 0;
  right: 0;
  height: 1px;
}

.smap-line-v {
  top: 0;
  bottom: 0;
  width: 1px;
}
</style>
