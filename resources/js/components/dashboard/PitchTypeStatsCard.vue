<script setup>
import { ref, watch } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { usePlayerStore } from '@/store/players.js'
import ArrowDownIcon from '@/components/icons/ArrowDownIcon.vue'
import defaultAvatar from '@/assets/img/login/assteslogin/updatedlogo.png'

const { axiosGet } = useAxiosAuth()
const { players }  = usePlayerStore()

const PITCH_TYPES  = ['FB', 'CB', 'SL', 'CH', 'OTHER']
const PITCH_COLORS = {
  FB:    '#F8A488',
  CB:    '#8676FF',
  SL:    '#ADE8F4',
  CH:    '#FFB457',
  OTHER: '#03F1E3',
}

const idA      = ref('')
const modeA    = ref('bullpen')
const dataA    = ref(null)
const loadingA = ref(false)

const idB      = ref('')
const modeB    = ref('bullpen')
const dataB    = ref(null)
const loadingB = ref(false)

async function fetchStats(id, mode) {
  if (!id) return null
  const endpoint = id === 'team'
    ? (mode === 'liveab' ? 'pitcher-liveab-stats/team' : 'pitcher-stats/team')
    : (mode === 'liveab' ? `pitcher-liveab-stats/${id}` : `pitcher-stats/${id}`)
  const res = await axiosGet(endpoint)
  return res.data.data
}

async function loadA() {
  if (!idA.value) { dataA.value = null; return }
  loadingA.value = true
  try { dataA.value = await fetchStats(idA.value, modeA.value) }
  catch (e) { console.error(e) }
  finally { loadingA.value = false }
}

async function loadB() {
  if (!idB.value) { dataB.value = null; return }
  loadingB.value = true
  try { dataB.value = await fetchStats(idB.value, modeB.value) }
  catch (e) { console.error(e) }
  finally { loadingB.value = false }
}

watch(idA,   loadA)
watch(modeA, loadA)
watch(idB,   loadB)
watch(modeB, loadB)

function avatarSrc(s) { return s?.avatar || defaultAvatar }

function strikeWinner(type) {
  if (!dataA.value || !dataB.value) return null
  const a = dataA.value.pitches[type]?.strike_percent ?? 0
  const b = dataB.value.pitches[type]?.strike_percent ?? 0
  if (a === b) return 'tie'
  return a > b ? 'left' : 'right'
}
</script>

<template>
  <div class="w-full">
    <h3 class="text-sm font-black uppercase tracking-widest text-white mb-3">Pitch Type Breakdown</h3>

    <div class="grid grid-cols-[1fr_32px_1fr] gap-1">

      <!-- LEFT COLUMN -->
      <div class="flex flex-col gap-2">
        <div class="relative">
          <select v-model="idA"
            class="w-full rounded-lg border border-white/20 bg-white/10 text-white text-xs py-2 pl-3 pr-7 appearance-none outline-none cursor-pointer">
            <option value="">Select pitcher…</option>
            <option value="team" class="text-black font-bold">⚾ Team</option>
            <option v-for="p in players" :key="p.id" :value="p.id" class="text-black">
              {{ p.name?.full ?? p.name }}
            </option>
          </select>
          <div class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2">
            <ArrowDownIcon color="ffffff" />
          </div>
        </div>

        <div v-if="idA" class="flex rounded-lg overflow-hidden border border-white/20 text-[10px] font-bold uppercase">
          <button class="flex-1 py-1.5 transition-colors"
            :class="modeA === 'bullpen' ? 'bg-red-500/30 text-red-300' : 'bg-white/5 text-white/30'"
            @click="modeA = 'bullpen'">Bullpen</button>
          <button class="flex-1 py-1.5 transition-colors"
            :class="modeA === 'liveab' ? 'bg-blue-500/30 text-blue-300' : 'bg-white/5 text-white/30'"
            @click="modeA = 'liveab'">Live AB</button>
        </div>

        <div v-if="dataA" class="flex flex-col items-center gap-1 p-2 rounded-lg bg-white/5 border"
          :class="modeA === 'bullpen' ? 'border-red-700/40' : 'border-blue-500/40'">
          <img :src="avatarSrc(dataA)" class="w-8 h-8 rounded-full object-cover border-2"
            :class="modeA === 'bullpen' ? 'border-red-700/50' : 'border-blue-500/50'" alt="" />
          <div class="text-[10px] font-black text-white uppercase text-center leading-tight">{{ dataA.name }}</div>
          <div class="text-[9px] text-white/40">{{ dataA.total }} pitches</div>
          <div class="text-[9px] font-bold uppercase tracking-wide"
            :class="modeA === 'bullpen' ? 'text-red-400' : 'text-blue-400'">
            {{ modeA === 'bullpen' ? 'Bullpen' : 'Live AB' }}
          </div>
        </div>
        <div v-else-if="loadingA" class="h-20 flex items-center justify-center text-white/30 text-xs">Loading…</div>
        <div v-else class="h-20 flex items-center justify-center text-white/20 text-[10px] text-center leading-tight">Select<br>a pitcher</div>
      </div>

      <!-- CENTER -->
      <div></div>

      <!-- RIGHT COLUMN -->
      <div class="flex flex-col gap-2">
        <div class="relative">
          <select v-model="idB"
            class="w-full rounded-lg border border-white/20 bg-white/10 text-white text-xs py-2 pl-3 pr-7 appearance-none outline-none cursor-pointer">
            <option value="">Select pitcher…</option>
            <option value="team" class="text-black font-bold">⚾ Team</option>
            <option v-for="p in players" :key="p.id" :value="p.id" class="text-black">
              {{ p.name?.full ?? p.name }}
            </option>
          </select>
          <div class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2">
            <ArrowDownIcon color="ffffff" />
          </div>
        </div>

        <div v-if="idB" class="flex rounded-lg overflow-hidden border border-white/20 text-[10px] font-bold uppercase">
          <button class="flex-1 py-1.5 transition-colors"
            :class="modeB === 'bullpen' ? 'bg-red-500/30 text-red-300' : 'bg-white/5 text-white/30'"
            @click="modeB = 'bullpen'">Bullpen</button>
          <button class="flex-1 py-1.5 transition-colors"
            :class="modeB === 'liveab' ? 'bg-blue-500/30 text-blue-300' : 'bg-white/5 text-white/30'"
            @click="modeB = 'liveab'">Live AB</button>
        </div>

        <div v-if="dataB" class="flex flex-col items-center gap-1 p-2 rounded-lg bg-white/5 border"
          :class="modeB === 'bullpen' ? 'border-red-700/40' : 'border-blue-500/40'">
          <img :src="avatarSrc(dataB)" class="w-8 h-8 rounded-full object-cover border-2"
            :class="modeB === 'bullpen' ? 'border-red-700/50' : 'border-blue-500/50'" alt="" />
          <div class="text-[10px] font-black text-white uppercase text-center leading-tight">{{ dataB.name }}</div>
          <div class="text-[9px] text-white/40">{{ dataB.total }} pitches</div>
          <div class="text-[9px] font-bold uppercase tracking-wide"
            :class="modeB === 'bullpen' ? 'text-red-400' : 'text-blue-400'">
            {{ modeB === 'bullpen' ? 'Bullpen' : 'Live AB' }}
          </div>
        </div>
        <div v-else-if="loadingB" class="h-20 flex items-center justify-center text-white/30 text-xs">Loading…</div>
        <div v-else class="h-20 flex items-center justify-center text-white/20 text-[10px] text-center leading-tight">Select<br>a pitcher</div>
      </div>
    </div>

    <!-- Stats rows -->
    <div v-if="dataA && dataB" class="mt-4">
      <div class="text-[9px] font-black uppercase tracking-widest text-white/30 mb-2">Strike %</div>

      <div v-for="type in PITCH_TYPES" :key="type" class="mb-4">
        <div class="flex items-center justify-center gap-1.5 mb-1.5">
          <span class="w-1.5 h-1.5 rounded-full" :style="{ background: PITCH_COLORS[type] }"></span>
          <span class="text-[10px] font-black uppercase" :style="{ color: PITCH_COLORS[type] }">{{ type }}</span>
        </div>

        <div class="grid grid-cols-[1fr_32px_1fr] gap-1 items-center">
          <div>
            <div class="flex items-center justify-between mb-0.5">
              <span class="text-[9px] text-white/40">({{ dataA.pitches[type]?.count ?? 0 }})</span>
              <span class="text-[10px] font-bold text-white">{{ dataA.pitches[type]?.strike_percent ?? 0 }}%</span>
            </div>
            <div class="h-2 rounded-full bg-white/10 overflow-hidden flex justify-end">
              <div class="h-full rounded-full transition-all duration-500"
                :style="{
                  width: (dataA.pitches[type]?.strike_percent ?? 0) + '%',
                  background: strikeWinner(type) === 'left' ? PITCH_COLORS[type] : 'rgba(255,255,255,0.25)'
                }"></div>
            </div>
          </div>

          <div class="text-center text-[8px] text-white/20 font-bold">vs</div>

          <div>
            <div class="flex items-center justify-between mb-0.5">
              <span class="text-[10px] font-bold text-white">{{ dataB.pitches[type]?.strike_percent ?? 0 }}%</span>
              <span class="text-[9px] text-white/40">({{ dataB.pitches[type]?.count ?? 0 }})</span>
            </div>
            <div class="h-2 rounded-full bg-white/10 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500"
                :style="{
                  width: (dataB.pitches[type]?.strike_percent ?? 0) + '%',
                  background: strikeWinner(type) === 'right' ? PITCH_COLORS[type] : 'rgba(255,255,255,0.25)'
                }"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Avg Velocity -->
      <div class="mt-2 pt-3 border-t border-white/10">
        <div class="text-[9px] font-black uppercase tracking-widest text-white/30 mb-3">Avg Velocity</div>
        <div class="grid grid-cols-[1fr_32px_1fr] gap-1">
          <div class="grid grid-cols-5 gap-0.5">
            <div v-for="type in PITCH_TYPES" :key="type" class="flex flex-col items-center gap-0.5">
              <span class="text-xs font-black"
                :style="{ color: dataA.pitches[type]?.avg_velo ? PITCH_COLORS[type] : 'rgba(255,255,255,0.15)' }">
                {{ dataA.pitches[type]?.avg_velo ?? '—' }}
              </span>
              <span class="text-[8px] font-bold uppercase" :style="{ color: PITCH_COLORS[type] }">{{ type }}</span>
            </div>
          </div>
          <div></div>
          <div class="grid grid-cols-5 gap-0.5">
            <div v-for="type in PITCH_TYPES" :key="type" class="flex flex-col items-center gap-0.5">
              <span class="text-xs font-black"
                :style="{ color: dataB.pitches[type]?.avg_velo ? PITCH_COLORS[type] : 'rgba(255,255,255,0.15)' }">
                {{ dataB.pitches[type]?.avg_velo ?? '—' }}
              </span>
              <span class="text-[8px] font-bold uppercase" :style="{ color: PITCH_COLORS[type] }">{{ type }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="mt-4 flex items-center justify-center h-20 text-gray-500 text-sm">
      {{ (!dataA && !dataB) ? 'Select pitchers above to compare.' : 'Select a second pitcher to compare.' }}
    </div>
  </div>
</template>
