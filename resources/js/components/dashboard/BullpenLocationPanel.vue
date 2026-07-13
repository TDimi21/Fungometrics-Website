<script setup>
// Bullpen heat / velocity map — the catcher's-view pitch-location panel from the
// app's bullpen velocity screen. Filter by pitch type, see strike %, and toggle
// between the average-velocity grid and the density heatmap. Reuses the existing
// session panels; fed by team pitches that have a location and/or a velocity.
import { ref, computed } from 'vue'
import BullpenZoneMap from '@/components/dashboard/BullpenZoneMap.vue'

const props = defineProps({
  pitches: { type: Array, default: () => [] }, // { pitch_mark, velocity, is_strike, pitch_type }
})

const FILTERS = [
  { key: 'ALL', label: 'All' }, { key: 'FB', label: 'Fastball' }, { key: 'CB', label: 'Curveball' },
  { key: 'CH', label: 'Change-Up' }, { key: 'SL', label: 'Slider' }, { key: 'OTHER', label: 'Other' },
]
const KNOWN = ['FB', 'CB', 'CH', 'SL']
const filter = ref('ALL')
const view = ref('heatmap') // 'heatmap' | 'grid'

const typeOf = (p) => String(p.pitch_type ?? p.type_throw ?? p.intended_pitch_type ?? '').toUpperCase()

const filteredPitches = computed(() => {
  const all = Array.isArray(props.pitches) ? props.pitches : []
  if (filter.value === 'ALL') return all
  if (filter.value === 'OTHER') return all.filter((p) => !KNOWN.includes(typeOf(p)))
  return all.filter((p) => typeOf(p) === filter.value)
})

const strikePct = computed(() => {
  const p = filteredPitches.value
  if (!p.length) return null
  const strikes = p.filter((x) => x.is_strike).length
  return Math.round((strikes / p.length) * 1000) / 10
})
</script>

<template>
  <div class="blp">
    <div class="blp-top">
      <button v-for="f in FILTERS" :key="f.key" class="blp-chip" :class="{ 'blp-chip--on': filter === f.key }" @click="filter = f.key">{{ f.label }}</button>
    </div>

    <div class="blp-fieldwrap">
      <BullpenZoneMap :pitches="filteredPitches" :mode="view === 'grid' ? 'grid' : 'heatmap'" />
      <div v-if="!filteredPitches.length" class="blp-empty">No pitches with a location or velocity yet</div>
    </div>

    <div class="blp-footer">
      <div class="blp-strike">
        <span class="blp-strike-l">Strike %</span>
        <b class="blp-strike-v">{{ strikePct != null ? `${strikePct}%` : '—' }}</b>
        <span class="blp-count">{{ filteredPitches.length }} pitch{{ filteredPitches.length === 1 ? '' : 'es' }}</span>
      </div>
      <div class="blp-toggle">
        <button class="blp-tbtn" :class="{ 'blp-tbtn--on': view === 'grid' }" @click="view = 'grid'">Velo Grid</button>
        <button class="blp-tbtn blp-tbtn--heat" :class="{ 'blp-tbtn--on': view === 'heatmap' }" @click="view = 'heatmap'">Heatmap</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.blp { width: 100%; }
.blp-top { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.blp-chip { font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 8px; cursor: pointer; color: rgba(255,255,255,.55); background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1); transition: .15s; }
.blp-chip:hover { color: #fff; border-color: rgba(255,255,255,.25); }
.blp-chip--on { color: #fff; background: #C00000; border-color: #C00000; }
.blp-fieldwrap { position: relative; border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,.08); background: #070e18; }
.blp-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.4); font-size: 13px; text-align: center; padding: 0 16px; pointer-events: none; }
.blp-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 10px; }
.blp-strike { display: flex; align-items: baseline; gap: 8px; }
.blp-strike-l { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,.4); }
.blp-strike-v { font-size: 20px; font-weight: 900; color: #37D67A; font-variant-numeric: tabular-nums; }
.blp-count { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.3); }
.blp-toggle { display: flex; gap: 6px; }
.blp-tbtn { font-size: 11px; font-weight: 800; padding: 6px 14px; border-radius: 8px; cursor: pointer; color: rgba(255,255,255,.6); background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12); transition: .15s; }
.blp-tbtn:hover { color: #fff; }
.blp-tbtn--on { color: #fff; background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.3); }
.blp-tbtn--heat.blp-tbtn--on { background: #C00000; border-color: #C00000; }
</style>
