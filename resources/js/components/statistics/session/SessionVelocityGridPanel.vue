<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  rows: { type: Array, default: () => [] },
  baseGrid: { type: Number, default: 60 },
  gridSize: { type: Number, default: 5 },
  markMode: { type: String, default: 'pitch' }, // pitch | field
  background: { type: String, default: 'catcher' }, // catcher | field
})

const activePitchType = ref('ALL')

const TYPE_OPTIONS = [
  { key: 'ALL', label: 'ALL' },
  { key: 'FB', label: 'FASTBALL' },
  { key: 'CB', label: 'CURVEBALL' },
  { key: 'CH', label: 'CHANGE-UP' },
  { key: 'SL', label: 'SLIDER' },
  { key: 'OTHER', label: 'OTHER' },
]

const normalizePitchType = (row) => {
  const raw = String(
    row?.type_throw ??
    row?.type_of_throw ??
    row?.pitch_type ??
    row?.pitch_name ??
    row?.type ??
    '',
  ).trim().toUpperCase()

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

const rowPassesPitchType = (row) => {
  if (activePitchType.value === 'ALL') return true
  return normalizePitchType(row) === activePitchType.value
}

const getVelocity = (row) => {
  const raw = row?.velocity ?? row?.miles_per_hour ?? row?.exit_velocity ?? row?.launch_angle_velocity ?? row?.weighted_velocity ?? null
  const num = Number(raw)
  return Number.isFinite(num) && num > 0 ? num : null
}

const getMark = (row) => {
  if (props.markMode === 'field') {
    const raw = row?.field_mark ?? row?.ground_location_id ?? row?.field_location ?? null
    return Number(raw)
  }
  const raw = row?.pitch_mark ?? row?.pitch_location ?? row?.zone ?? null
  return Number(raw)
}

const markToCell = (mark) => {
  if (!Number.isFinite(mark) || mark <= 0) return null
  const maxMark = props.baseGrid * props.baseGrid
  if (mark > maxMark) return null

  const rowBase = Math.floor((mark - 1) / props.baseGrid) + 1
  const colBase = ((mark - 1) % props.baseGrid) + 1
  const bucket = props.baseGrid / props.gridSize

  return {
    row: Math.min(props.gridSize, Math.ceil(rowBase / bucket)),
    col: Math.min(props.gridSize, Math.ceil(colBase / bucket)),
  }
}

const cells = computed(() => {
  const map = {}
  for (let r = 1; r <= props.gridSize; r += 1) {
    for (let c = 1; c <= props.gridSize; c += 1) {
      map[`${r}-${c}`] = { sum: 0, count: 0, row: r, col: c }
    }
  }

  ;(Array.isArray(props.rows) ? props.rows : []).forEach((row) => {
    if (!rowPassesPitchType(row)) return
    const velo = getVelocity(row)
    if (velo == null) return
    const cell = markToCell(getMark(row))
    if (!cell) return

    const key = `${cell.row}-${cell.col}`
    map[key].sum += velo
    map[key].count += 1
  })

  let min = null
  let max = 0
  const arr = Object.values(map).map((entry) => {
    const avg = entry.count > 0 ? entry.sum / entry.count : 0
    if (avg > 0) {
      min = min == null ? avg : Math.min(min, avg)
      max = Math.max(max, avg)
    }
    return { ...entry, avg }
  })

  return {
    values: arr,
    min: min ?? 0,
    max,
    samples: arr.reduce((sum, c) => sum + c.count, 0),
  }
})

const cellColor = (avg) => {
  if (!avg || cells.value.max <= 0) return 'rgba(0,0,0,0.55)'
  const min = cells.value.min || 0
  const tRaw = cells.value.max > min ? (avg - min) / (cells.value.max - min) : 0
  const t = Math.pow(Math.max(0, Math.min(1, tRaw)), 0.6)
  const r = Math.round(255 * t)
  const g = Math.round(100 - (50 * t))
  const b = Math.round(255 - (255 * t))
  const a = 0.2 + 0.75 * t
  return `rgba(${r},${g},${b},${a})`
}

const cellStyle = (cell) => {
  const pct = 100 / props.gridSize
  return {
    left: `${(cell.col - 1) * pct}%`,
    top: `${(cell.row - 1) * pct}%`,
    width: `${pct}%`,
    height: `${pct}%`,
    backgroundColor: cellColor(cell.avg),
  }
}

const backgroundClass = computed(() => {
  return props.background === 'field' ? 'svz-bg-field' : 'svz-bg-catcher'
})
</script>

<template>
  <div>
    <div class="mb-3 flex flex-wrap gap-2 justify-center">
      <button
        v-for="opt in TYPE_OPTIONS"
        :key="opt.key"
        class="px-3 py-1 rounded-full text-xs font-bold tracking-wide border transition"
        :class="activePitchType === opt.key ? 'bg-[#ff2d55] border-[#ff2d55] text-white' : 'bg-white/5 border-white/15 text-white/70 hover:bg-white/10'"
        @click="activePitchType = opt.key"
      >
        {{ opt.label }}
      </button>
    </div>

    <div class="svz-wrap">
      <div :class="['svz-bg', backgroundClass]"></div>

      <div class="svz-grid">
        <div
          v-for="cell in cells.values"
          :key="`cell-${cell.row}-${cell.col}`"
          class="svz-cell"
          :style="cellStyle(cell)"
        >
          <span v-if="cell.avg > 0" class="svz-value">{{ cell.avg.toFixed(1) }}</span>
        </div>
      </div>

      <div class="svz-lines">
        <div
          v-for="i in gridSize + 1"
          :key="`h-${i}`"
          class="svz-line-h"
          :style="{ top: `${((i - 1) / gridSize) * 100}%` }"
        />
        <div
          v-for="i in gridSize + 1"
          :key="`v-${i}`"
          class="svz-line-v"
          :style="{ left: `${((i - 1) / gridSize) * 100}%` }"
        />
      </div>
    </div>

    <p class="mt-2 text-center text-xs text-white/60">
      {{ cells.samples }} velocity samples · {{ gridSize }}×{{ gridSize }} average mph grid
    </p>
  </div>
</template>

<style scoped>
.svz-wrap {
  position: relative;
  width: min(100%, 680px);
  aspect-ratio: 1 / 1;
  margin: 0 auto;
  overflow: hidden;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.14);
}

.svz-bg {
  position: absolute;
  inset: 0;
  opacity: 0.65;
  background-repeat: no-repeat;
  background-position: center;
  background-size: 100% 100%;
}

.svz-bg-catcher {
  background-image: url('@/assets/img/training/catcherimagenew.png');
}

.svz-bg-field {
  background-image: url('@/assets/img/training/fieldbatting.png');
}

.svz-grid {
  position: absolute;
  inset: 0;
}

.svz-cell {
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: center;
}

.svz-value {
  font-size: clamp(8px, 1.4vw, 13px);
  font-weight: 800;
  color: #fff;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.9);
}

.svz-lines {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.svz-line-h,
.svz-line-v {
  position: absolute;
  background: rgba(255, 255, 255, 0.45);
}

.svz-line-h {
  left: 0;
  right: 0;
  height: 1px;
}

.svz-line-v {
  top: 0;
  bottom: 0;
  width: 1px;
}
</style>
