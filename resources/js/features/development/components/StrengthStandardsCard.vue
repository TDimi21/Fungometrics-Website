<script setup>
import { ref, computed } from 'vue'
import {
  ALL_STANDARDS,
  STRENGTH_LEVELS,
  STRENGTH_LEVEL_LABELS,
  STRENGTH_LEVEL_COLORS,
  getStrengthLevel,
} from '../data/strengthStandards.js'

const props = defineProps({
  bodyWeight: { type: [Number, String], default: null },
  benchPress: { type: [Number, String], default: null },
  frontSquat: { type: [Number, String], default: null },
  pullUps:    { type: [Number, String], default: null },
  pushUps:    { type: [Number, String], default: null },
})

const activeStandard = ref(0)
const standard = computed(() => ALL_STANDARDS[activeStandard.value])

const playerValues = computed(() => ({
  'Front Squat': props.frontSquat,
  'Bench Press': props.benchPress,
  'Pull Ups':    props.pullUps,
  'Push Ups':    props.pushUps,
}))

const playerValue = computed(() => playerValues.value[standard.value.label])

const playerLevel = computed(() => {
  if (!props.bodyWeight || playerValue.value == null) return null
  return getStrengthLevel(standard.value, parseFloat(props.bodyWeight), playerValue.value)
})

// Find the nearest bodyweight row to display
const nearestRow = computed(() => {
  if (!props.bodyWeight) return null
  const bw = parseFloat(props.bodyWeight)
  const rows = standard.value.rows
  return rows.reduce((a, b) => Math.abs(b.bw - bw) < Math.abs(a.bw - bw) ? b : a)
})

const levelColor = (level) => STRENGTH_LEVEL_COLORS[level] ?? '#94a3b8'

// Show 5 rows around the player's bodyweight (or first 5 if no bw)
const displayRows = computed(() => {
  const rows = standard.value.rows
  if (!props.bodyWeight) return rows.slice(0, 10)
  const bw = parseFloat(props.bodyWeight)
  const idx = rows.findIndex(r => r.bw === nearestRow.value?.bw) ?? 0
  const start = Math.max(0, idx - 4)
  const end = Math.min(rows.length, start + 10)
  return rows.slice(start, end)
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/60 overflow-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between px-4 pt-4 pb-2">
      <div>
        <div class="text-[10px] uppercase tracking-widest text-white/40 mb-0.5">Strength Standards Reference</div>
        <div class="text-xs text-white/55">Industry standards by bodyweight · Use as coaching targets</div>
      </div>
      <!-- Player level badge -->
      <div v-if="playerLevel" class="flex flex-col items-center px-3 py-1.5 rounded-lg border" :style="{ borderColor: levelColor(playerLevel) + '66', backgroundColor: levelColor(playerLevel) + '18' }">
        <span class="text-[9px] font-black uppercase tracking-widest" :style="{ color: levelColor(playerLevel) }">{{ standard.label }}</span>
        <span class="text-sm font-black" :style="{ color: levelColor(playerLevel) }">{{ STRENGTH_LEVEL_LABELS[playerLevel] }}</span>
      </div>
    </div>

    <!-- Lift selector tabs -->
    <div class="flex gap-1 px-4 pb-3">
      <button
        v-for="(s, i) in ALL_STANDARDS" :key="s.label"
        class="px-3 py-1 rounded-lg text-[11px] font-black uppercase tracking-wide transition-all"
        :class="activeStandard === i
          ? 'bg-[#C00000] text-white'
          : 'bg-white/5 text-white/45 hover:text-white hover:bg-white/10'"
        @click="activeStandard = i"
      >{{ s.label }}</button>
    </div>

    <!-- Player's value vs standard -->
    <div v-if="props.bodyWeight && playerValue != null" class="mx-4 mb-3 rounded-lg bg-white/5 border border-white/10 px-3 py-2 flex items-center gap-3">
      <div class="flex-1 text-xs text-white/70">
        Player at <span class="font-bold text-white">{{ props.bodyWeight }} lbs</span> body weight:
        <span class="font-bold text-white ml-1">{{ playerValue }} {{ standard.unit }}</span>
      </div>
      <div v-if="playerLevel" class="text-xs font-black px-2.5 py-1 rounded-full" :style="{ backgroundColor: levelColor(playerLevel) + '25', color: levelColor(playerLevel), border: '1px solid ' + levelColor(playerLevel) + '55' }">
        {{ STRENGTH_LEVEL_LABELS[playerLevel] }}
      </div>
      <div v-else class="text-xs text-white/30 italic">No data</div>
    </div>
    <div v-else-if="!props.bodyWeight" class="mx-4 mb-3 text-xs text-white/30 italic">Enter player bodyweight to see their level</div>

    <!-- Standards table -->
    <div class="px-4 pb-4 overflow-x-auto">
      <table class="w-full text-[11px] border-collapse">
        <thead>
          <tr class="border-b border-white/10">
            <th class="text-left text-white/35 font-bold pb-1.5 pr-3 whitespace-nowrap">Body Wt</th>
            <th v-for="lvl in STRENGTH_LEVELS" :key="lvl"
                class="text-center font-black pb-1.5 px-2 whitespace-nowrap"
                :style="{ color: levelColor(lvl) }">
              {{ STRENGTH_LEVEL_LABELS[lvl] }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in displayRows" :key="row.bw"
            class="border-b border-white/5 transition"
            :class="nearestRow?.bw === row.bw && props.bodyWeight ? 'bg-white/8' : 'hover:bg-white/5'"
          >
            <td class="py-1.5 pr-3 text-white/60 font-bold whitespace-nowrap">
              {{ row.bw }}
              <span v-if="nearestRow?.bw === row.bw && props.bodyWeight" class="ml-1 text-[9px] text-[#C00000] font-black">▶</span>
            </td>
            <td v-for="lvl in STRENGTH_LEVELS" :key="lvl" class="text-center py-1.5 px-2 tabular-nums"
                :class="nearestRow?.bw === row.bw && playerLevel === lvl ? 'font-black' : 'text-white/65'"
                :style="nearestRow?.bw === row.bw && playerLevel === lvl ? { color: levelColor(lvl) } : {}">
              {{ row[lvl] }}<span v-if="standard.unit === 'lbs'" class="text-white/25 ml-0.5 text-[9px]">lb</span>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="mt-2 text-[10px] text-white/25 italic">
        Showing {{ displayRows.length }} bodyweight rows{{ props.bodyWeight ? ' · Highlighted row = closest to player' : '' }} ·
        {{ standard.type === '1rm' ? '1 Rep Max (lbs) · Barbell includes bar (~44 lb)' : 'Repetitions (bodyweight)' }}
      </div>
    </div>
  </div>
</template>
