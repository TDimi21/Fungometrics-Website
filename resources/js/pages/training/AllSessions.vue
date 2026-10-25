<script setup>
import { playerSessionHistory } from '@/utils/sessionPagination'
import { useUserStore } from '@/store/user'
import { ref, computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { useTeamStore } from '@/store/team'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import Layout from '@/layout/Layout.vue'

const user = useUserStore()
const playerScope = computed(() => user.userData.type === 'player' || route.query?.scope === 'player')
const loadError = ref('')
const router = useRouter()
const route = useRoute()
const { axiosGet } = useAxiosAuth()
const { team } = storeToRefs(useTeamStore())

const loading  = ref(true)
const sessions = ref([])
const filter   = ref('all')

const TYPE_COLOR = {
  batting:       { bg: 'bg-sky-500/20',     border: 'border-sky-500/40',     text: 'text-sky-300',     dot: '#38BDF8', label: 'BATTING'      },
  bullpen:       { bg: 'bg-violet-500/20',  border: 'border-violet-500/40',  text: 'text-violet-300',  dot: '#A78BFA', label: 'BULLPEN'      },
  cage:          { bg: 'bg-emerald-500/20', border: 'border-emerald-500/40', text: 'text-emerald-300', dot: '#34D399', label: 'CAGE'         },
  live:          { bg: 'bg-orange-500/20',  border: 'border-orange-500/40',  text: 'text-orange-300',  dot: '#FB923C', label: 'LIVE AB'      },
  long_toss:     { bg: 'bg-pink-500/20',    border: 'border-pink-500/40',    text: 'text-pink-300',    dot: '#F472B6', label: 'LONG TOSS'   },
  weight_ball:   { bg: 'bg-yellow-500/20',  border: 'border-yellow-500/40',  text: 'text-yellow-300',  dot: '#FBBF24', label: 'WEIGHT BALL' },
  exit_velocity: { bg: 'bg-red-500/20',     border: 'border-red-500/40',     text: 'text-red-300',     dot: '#F87171', label: 'EXIT VEL'    },
  rapsodo:      { bg: 'bg-teal-500/20',    border: 'border-teal-500/40',    text: 'text-teal-300',    dot: '#2DD4BF', label: 'RAPSODO'     },
}

const SESSION_REPORT_TYPE = {
  batting: 'batting', bullpen: 'bullpen', cage: 'cage',
  live: 'live_ab',
  long_toss: 'long_toss', weight_ball: 'weight_ball', exit_velocity: 'exit_velocity',
}


const SESSION_MODE_TO_FILTER = {
  EV: 'exit_velocity',
  LT: 'long_toss',
  WB: 'weight_ball',
  HP: 'live',
}

const FILTERS = [
  { key: 'all',           label: 'All'          },
  { key: 'batting',       label: 'Batting'      },
  { key: 'bullpen',       label: 'Bullpen'      },
  { key: 'cage',          label: 'Cage'         },
  { key: 'live',          label: 'Live AB'      },
  { key: 'long_toss',     label: 'Long Toss'    },
  { key: 'weight_ball',   label: 'Weight Ball'  },
  { key: 'exit_velocity', label: 'Exit Vel'     },
  { key: 'rapsodo',      label: 'Rapsodo'      },
]

const formatDate = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
}

const normalizeMode = (modeLike) => {
  const mode = String(modeLike || '').trim().toUpperCase()
  if (!mode) return null
  if (mode === 'EV') return 'EV'
  if (mode === 'WB') return 'WB'
  if (mode === 'LT' || mode.includes('LONG')) return 'LT'
  if (mode === 'HP') return 'HP'
  return mode
}

const tryLen = (arr) => (Array.isArray(arr) ? arr.length : undefined)

const computeTotalBalls = (session, sourceType) => {
  const modeHint = normalizeMode(session?.mode || session?.modes)
  const explicitCount =
    (typeof session?.total_balls === 'number' ? session.total_balls : undefined) ??
    (typeof session?.total_ball === 'number' ? session.total_ball : undefined) ??
    (typeof session?.balls === 'number' ? session.balls : undefined) ??
    (typeof session?.pitches_count === 'number' ? session.pitches_count : undefined) ??
    (typeof session?.total_pitches === 'number' ? session.total_pitches : undefined) ??
    (typeof session?.throws_count === 'number' ? session.throws_count : undefined)

  if (typeof explicitCount === 'number') return explicitCount

  if (sourceType === 'B') {
    const v = tryLen(session?.batting) ?? tryLen(session?.practice_match_result) ?? tryLen(session?.results)
    if (v !== undefined) return v
  }

  if (sourceType === 'P') {
    const v = tryLen(session?.bullpen) ?? tryLen(session?.pitchers) ?? tryLen(session?.practice_match_result) ?? tryLen(session?.results) ?? tryLen(session?.pitches)
    if (v !== undefined) return v
  }

  if (sourceType === 'C') {
    const v = tryLen(session?.cage) ?? tryLen(session?.batters) ?? tryLen(session?.practice_match_result) ?? tryLen(session?.results)
    if (v !== undefined) return v
  }

  if (sourceType === 'T') {
    const trainingCount = tryLen(session?.long_toss) ?? tryLen(session?.weight_ball) ?? tryLen(session?.exit_velocity)
    if (trainingCount !== undefined) return trainingCount
    const v = tryLen(session?.practice_match_result) ?? tryLen(session?.results)
    if (v !== undefined) return v
    if (modeHint === 'LT') {
      const lt = tryLen(session?.throws)
      if (lt !== undefined) return lt
    }
  }

  return 0
}

const mapPlayerSession = (session, sourceType, createdBySelf = false) => {
  const mode = normalizeMode(session?.mode || session?.modes)
  const date = session?.created_at || session?.updated_at || session?.date || session?.started || null
  const resolvedType = sourceType || String(session?.type || '').toUpperCase()

  let type = 'live'
  if (resolvedType === 'B') type = 'batting'
  else if (resolvedType === 'P') type = 'bullpen'
  else if (resolvedType === 'C') type = 'cage'
  else if (resolvedType === 'L') type = 'live'
  else if (resolvedType === 'T') type = SESSION_MODE_TO_FILTER[mode] || 'live'

  const reportType = resolvedType === 'T'
    ? SESSION_REPORT_TYPE[type] || null
    : SESSION_REPORT_TYPE[type] || null

  return {
    ...session,
    _type: type,
    _date: date,
    _reportType: reportType,
    total_balls: computeTotalBalls(session, resolvedType),
    is_completed: session?.is_completed === true || [1, 2, '1', '2'].includes(session?.is_completed),
    created_by_self: createdBySelf,
  }
}

const playerNames = (session) => {
  const lu = session.lineup ?? []
  if (!lu.length) return null
  const names = lu.slice(0, 2).map(p => p.name?.full ?? `${p.name?.first ?? ''} ${p.name?.last ?? ''}`.trim()).filter(Boolean)
  const extra = lu.length > 2 ? ` +${lu.length - 2}` : ''
  return names.join(', ') + extra
}

const filtered = computed(() =>
  filter.value === 'all' ? sessions.value : sessions.value.filter(s => s._type === filter.value)
)

const sessionTotals = computed(() => {
  const total = sessions.value.length
  const done = sessions.value.filter((s) => s.is_completed === true || s.is_completed === 1 || s.is_completed === 2).length
  return { total, done }
})

const loadCoachSessions = async () => {
  if (!team.value?.id) return
  const { data } = await axiosGet('coach/sessions/lasts/' + team.value.id)
  const d = data?.data ?? {}
  const all = []
  for (const [type, items] of Object.entries(d)) {
    if (Array.isArray(items)) items.forEach((item) => all.push({ ...item, _type: type }))
  }
  const reports = await axiosGet('data-hub/rapsodo-reports').catch(() => null)
  ;(reports?.data?.data || []).forEach((item) => all.push({
    ...item, _type: 'rapsodo', _date: item.completed_at, _reportType: 'rapsodo', total_balls: item.pitch_count,
    is_completed: 2, lineup: [{ name: { full: item.player_name } }],
  }))
  all.sort((a, b) => new Date(b.updated_at ?? b.created_at) - new Date(a.updated_at ?? a.created_at))
  sessions.value = all
}

const loadPlayerSessions = async () => {
  const [batting, bullpen, cage, longToss, exitVelocity, weightedBalls, liveab, rapsodoRes] = await Promise.all([
    ...['B', 'P', 'C', 'LT', 'EV', 'WB', 'L'].map(type => playerSessionHistory(axiosGet, type)),
    axiosGet('data-hub/rapsodo-reports').catch(() => {
      loadError.value = 'Imported reports could not be loaded. Refresh to retry.';
      return null;
    }),
  ])
  sessions.value = [
    ...batting.map(s => mapPlayerSession(s, 'B')),
    ...bullpen.map(s => mapPlayerSession(s, 'P')),
    ...cage.map(s => mapPlayerSession(s, 'C')),
    ...[...longToss, ...exitVelocity, ...weightedBalls].map(s => mapPlayerSession(s, 'T')),
    ...liveab.map(s => mapPlayerSession(s, 'L')),
    ...(rapsodoRes?.data?.data || []).map(item => ({
      ...item, _type: 'rapsodo', _date: item.completed_at, _reportType: 'rapsodo', total_balls: item.pitch_count,
      is_completed: true, lineup: [{ name: { full: item.player_name } }],
    })),
  ].filter(s => s?.id).sort((a, b) => new Date(b?._date || 0) - new Date(a?._date || 0))
}

onMounted(async () => {
  loading.value = true
  try {
    if (playerScope.value) {
      await loadPlayerSessions()
    } else {
      await loadCoachSessions()
    }
  } catch (e) {
    loadError.value = 'Session reports could not be loaded. Refresh to retry.'
  } finally {
    loading.value = false
  }
})

const openReport = (session) => {
  if (session?._reportType === 'rapsodo') {
    router.push(session.report_path || { name: 'player.rapsodo-report', params: { batch: session.id } })
    return
  }
  const type = session?._reportType || SESSION_REPORT_TYPE[session._type]
  if (!type) return
  const note = session?.end_note || null
  const date = session?._date ?? session?.updated_at ?? session?.created_at ?? null
  router.push({
    name: 'session.report',
    params: { id: session.id, type },
    query: { date, note, scope: playerScope.value ? 'player' : undefined },
  })
}
</script>

<template>
  <Layout>
    <div class="min-h-screen bg-[#060b14] text-white pb-20">

      <!-- Header -->
      <div class="px-4 pt-5">
        <div class="rounded-2xl border border-white/10 bg-[#0b1230]/75 p-4">
          <div class="flex items-center gap-3">
            <button @click="router.back()"
              class="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition shrink-0">
              <svg class="w-4 h-4 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
            </button>
            <div class="min-w-0">
              <h1 class="truncate text-white font-black text-xl tracking-wide">Session Reports</h1>
              <p class="truncate text-white/45 text-xs uppercase tracking-wider">{{ playerScope ? 'Player Sessions' : team?.name }}</p>
            </div>
          </div>

          <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
              <p class="text-[10px] font-black tracking-widest uppercase text-white/55">Total Sessions</p>
              <p class="mt-1 text-2xl font-black">{{ sessionTotals.total }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
              <p class="text-[10px] font-black tracking-widest uppercase text-white/55">Completed</p>
              <p class="mt-1 text-2xl font-black">{{ sessionTotals.done }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3 px-5 pt-4 pb-2">
        <button @click="router.back()"
          class="w-8 h-8 hidden items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition shrink-0">
          <svg class="w-4 h-4 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <span v-if="!loading" class="ml-auto text-white/40 text-xs uppercase tracking-wider">{{ filtered.length }} shown</span>
      </div>

      <!-- Filter chips -->
      <div class="flex gap-2 px-4 py-2 overflow-x-auto scrollbar-hide">
        <button
          v-for="f in FILTERS" :key="f.key"
          @click="filter = f.key"
          class="shrink-0 px-3.5 py-1.5 rounded-full text-[11px] font-black uppercase tracking-wider border transition"
          :class="filter === f.key
            ? 'bg-[#ff2d55] border-[#ff2d55] text-white'
            : 'bg-white/5 border-white/15 text-white/60 hover:border-white/30 hover:text-white/85'"
        >{{ f.label }}</button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-20">
        <svg class="animate-spin w-7 h-7 text-red-400" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
      </div>

      <p v-if="loadError" role="alert" class="px-5 py-4">{{ loadError }}</p>

      <!-- Empty -->
      <div v-else-if="!filtered.length" class="flex flex-col items-center py-24 text-center px-6">
        <p class="text-white/25 text-4xl mb-3">⚾</p>
        <p class="text-white/40 font-bold">No sessions found</p>
        <p class="text-white/20 text-sm mt-1">Try a different filter</p>
      </div>

      <!-- Session list -->
      <div v-else class="px-4 space-y-2 mt-2">
        <div
          v-for="session in filtered"
          :key="session.id"
          @click="openReport(session)"
          class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition group"
          :class="[
            TYPE_COLOR[session._type]?.bg ?? 'bg-white/5',
            TYPE_COLOR[session._type]?.border ?? 'border-white/10',
            (session._reportType || SESSION_REPORT_TYPE[session._type]) ? 'hover:brightness-125 cursor-pointer' : 'opacity-70 cursor-default'
          ]"
        >
          <!-- Type badge -->
          <span
            class="shrink-0 text-[10px] font-black uppercase tracking-wider px-2.5 py-1.5 rounded-lg border"
            :class="[TYPE_COLOR[session._type]?.bg, TYPE_COLOR[session._type]?.border, TYPE_COLOR[session._type]?.text]"
          >{{ TYPE_COLOR[session._type]?.label ?? session._type }}</span>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <p class="text-white/85 text-sm font-bold truncate">
              {{ formatDate(session._date ?? session.updated_at ?? session.created_at) }}
            </p>
            <p v-if="playerNames(session)" class="text-white/40 text-xs truncate mt-0.5">
              {{ playerNames(session) }}
            </p>
          </div>

          <!-- Done badge -->
          <span
            v-if="session.is_completed === true || session.is_completed === 1 || session.is_completed === 2"
            class="shrink-0 text-[10px] font-black text-emerald-400 border border-emerald-500/40 bg-emerald-500/10 px-2 py-1 rounded-lg"
          >✓ DONE</span>

          <span
            v-if="session._reportType || SESSION_REPORT_TYPE[session._type]"
            class="text-[10px] font-black tracking-wider uppercase text-white/45"
          >Report ›</span>
        </div>
      </div>

    </div>
  </Layout>
</template>
