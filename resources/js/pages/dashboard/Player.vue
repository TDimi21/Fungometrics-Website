<script setup>
import { computed, onMounted, ref } from 'vue'
import Layout from '@/layout/Layout.vue'
import { useUserStore } from '@/store/user'
import { useAxiosAuth } from '@/composables/axios-auth'

const { userData } = useUserStore()
const { axiosGet } = useAxiosAuth()

const loading = ref(false)
const activeTopTab = ref('stats')
const activeStatTab = ref('bp')

const battingSessions = ref([])
const bullpenSessions = ref([])
const cageSessions = ref([])
const trainingSessions = ref([])

const topTabs = [
  { key: 'stats', label: 'STATS' },
  { key: 'recap', label: 'RECAP' },
]

const statTabs = [
  { key: 'bp', label: 'BP Stats' },
  { key: 'bullpen', label: 'Bullpen' },
  { key: 'cage', label: 'Cage' },
  { key: 'weighted', label: 'Weighted' },
  { key: 'exitVel', label: 'Exit Vel' },
]

const fmt = (v, d = 1) => (Number.isFinite(v) ? Number(v.toFixed(d)) : null)
const pct = (num, den) => (den > 0 ? fmt((num / den) * 100, 1) : null)

const asArray = (val) => {
  if (Array.isArray(val)) return val
  if (val && typeof val === 'object') return Object.values(val)
  return []
}

const parseNum = (row, keys) => {
  for (const k of keys) {
    const n = Number(row?.[k])
    if (Number.isFinite(n) && n > 0) return n
  }
  return null
}

const getSessionRows = (session) =>
  asArray(session?.practice_match_result)
    .concat(asArray(session?.results))
    .concat(asArray(session?.batting))
    .concat(asArray(session?.bullpen))
    .concat(asArray(session?.exit_velocity))
    .concat(asArray(session?.weight_ball))

const playerName = computed(() => {
  return userData?.name?.full || userData?.name || 'Player'
})

const playerHeaderMeta = computed(() => {
  const profile = userData?.profile || {}
  return {
    height: profile?.height || '—',
    weight: profile?.weight || '—',
    position: profile?.position || profile?.positions || '—',
  }
})

const battingMetrics = computed(() => {
  const rows = battingSessions.value.flatMap((s) => getSessionRows(s))
  const ev = rows.map((r) => parseNum(r, ['exit_velocity', 'velocity', 'miles_per_hour', 'mph'])).filter((n) => n !== null)
  const la = rows.map((r) => parseNum(r, ['launch_angle', 'angle'])).filter((n) => n !== null)
  const hardHit = ev.filter((v) => v >= 90).length

  return [
    { label: 'Max Exit Velo', value: fmt(Math.max(...ev), 1), unit: 'mph' },
    { label: 'Avg Exit Velo', value: fmt(ev.reduce((a, b) => a + b, 0) / (ev.length || 1), 1), unit: 'mph' },
    { label: 'Hard Hit %', value: pct(hardHit, ev.length), unit: '%' },
    { label: 'Avg Launch Angle', value: fmt(la.reduce((a, b) => a + b, 0) / (la.length || 1), 1), unit: '°' },
  ]
})

const bullpenMetrics = computed(() => {
  const rows = bullpenSessions.value.flatMap((s) => getSessionRows(s))
  const pitches = rows.map((r) => ({
    mph: parseNum(r, ['miles_per_hour', 'velocity', 'pitch_velocity', 'mph']),
    strike:
      Number(r?.is_strike) === 1 ||
      Number(r?.strike) === 1 ||
      String(r?.result || '').toLowerCase().includes('strike') ||
      String(r?.mark || '').toUpperCase().startsWith('S'),
    type:
      String(r?.pitch_type || r?.pitch_name || r?.pitch || r?.name || 'FB')
        .toUpperCase()
        .replace('FASTBALL', 'FB')
        .replace('CHANGEUP', 'CH')
        .replace('SLIDER', 'SL')
        .replace('CURVEBALL', 'CV'),
  }))

  const mphs = pitches.map((p) => p.mph).filter((n) => n !== null)
  const strikes = pitches.filter((p) => p.strike).length
  const byType = ['FB', 'CH', 'SL', 'CV'].map((t) => {
    const rowsByType = pitches.filter((p) => p.type === t)
    const strikePct = pct(rowsByType.filter((p) => p.strike).length, rowsByType.length)
    const avgVelo = fmt(
      rowsByType.map((p) => p.mph).filter((v) => v !== null).reduce((a, b) => a + b, 0) /
        (rowsByType.map((p) => p.mph).filter((v) => v !== null).length || 1),
      1,
    )
    return { type: t, strikePct, avgVelo }
  })

  return {
    summary: [
      { label: 'Max FB Velo', value: fmt(Math.max(...mphs), 1), unit: 'mph' },
      { label: 'Avg FB Velo', value: fmt(mphs.reduce((a, b) => a + b, 0) / (mphs.length || 1), 1), unit: 'mph' },
      { label: `Overall Strike % (${pitches.length} pitches)`, value: pct(strikes, pitches.length), unit: '%' },
      { label: 'Competitive Pitch %', value: pct(strikes, pitches.length), unit: '%' },
    ],
    byType,
  }
})

const cageMetrics = computed(() => {
  const rows = cageSessions.value.flatMap((s) => getSessionRows(s))
  const ev = rows.map((r) => parseNum(r, ['exit_velocity', 'velocity', 'miles_per_hour', 'mph'])).filter((n) => n !== null)
  const la = rows.map((r) => parseNum(r, ['launch_angle', 'angle'])).filter((n) => n !== null)
  const barrels = rows.filter((r) => {
    const velo = parseNum(r, ['exit_velocity', 'velocity', 'miles_per_hour', 'mph'])
    const angle = parseNum(r, ['launch_angle', 'angle'])
    return velo !== null && angle !== null && velo >= 85 && angle >= 8 && angle <= 30
  }).length
  const lineDrive = rows.filter((r) => {
    const angle = parseNum(r, ['launch_angle', 'angle'])
    return angle !== null && angle >= 10 && angle <= 25
  }).length

  return [
    { label: 'Max Exit Velo', value: fmt(Math.max(...ev), 1), unit: 'mph' },
    { label: 'Avg Exit Velo', value: fmt(ev.reduce((a, b) => a + b, 0) / (ev.length || 1), 1), unit: 'mph' },
    { label: 'Barrel %', value: pct(barrels, rows.length), unit: '%' },
    { label: 'Line Drive %', value: pct(lineDrive, rows.length), unit: '%' },
  ]
})

const weightedMetrics = computed(() => {
  const weighted = trainingSessions.value.filter((s) => String(s?.modes || s?.mode || '').toUpperCase() === 'WB')
  const rows = weighted.flatMap((s) => getSessionRows(s))
  const vel = rows.map((r) => parseNum(r, ['velocity', 'miles_per_hour', 'mph'])).filter((n) => n !== null)

  return [
    { label: 'Total Throws', value: rows.length, unit: '' },
    { label: 'Max Throw Velo', value: fmt(Math.max(...vel), 1), unit: 'mph' },
    { label: 'Avg Throw Velo', value: fmt(vel.reduce((a, b) => a + b, 0) / (vel.length || 1), 1), unit: 'mph' },
  ]
})

const exitVelMetrics = computed(() => {
  const evSessions = trainingSessions.value.filter((s) => String(s?.modes || s?.mode || '').toUpperCase() === 'EV')
  const rows = evSessions.flatMap((s) => getSessionRows(s))
  const ev = rows.map((r) => parseNum(r, ['exit_velocity', 'velocity', 'miles_per_hour', 'mph'])).filter((n) => n !== null)
  const hard = ev.filter((n) => n >= 90).length

  return [
    { label: 'Max Exit Velo', value: fmt(Math.max(...ev), 1), unit: 'mph' },
    { label: 'Avg Exit Velo', value: fmt(ev.reduce((a, b) => a + b, 0) / (ev.length || 1), 1), unit: 'mph' },
    { label: 'Hard Hit %', value: pct(hard, ev.length), unit: '%' },
  ]
})

const activeCards = computed(() => {
  if (activeStatTab.value === 'bp') return battingMetrics.value
  if (activeStatTab.value === 'bullpen') return bullpenMetrics.value.summary
  if (activeStatTab.value === 'cage') return cageMetrics.value
  if (activeStatTab.value === 'weighted') return weightedMetrics.value
  return exitVelMetrics.value
})

const loadData = async () => {
  loading.value = true
  try {
    const [battingRes, bullpenRes, cageRes, trainingRes] = await Promise.all([
      axiosGet('player/sessions/batting').catch(() => null),
      axiosGet('player/sessions/bullpen').catch(() => null),
      axiosGet('player/sessions/cage').catch(() => null),
      axiosGet('player/sessions/training').catch(() => null),
    ])

    battingSessions.value = battingRes?.data?.data?.data || []
    bullpenSessions.value = bullpenRes?.data?.data?.data || []
    cageSessions.value = cageRes?.data?.data?.data || []
    trainingSessions.value = trainingRes?.data?.data?.data || []
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <Layout>
    <div class="min-h-full bg-[#0B1230] text-white p-4 md:p-6">
      <div class="max-w-6xl mx-auto space-y-4">
        <section class="rounded-2xl border border-white/10 bg-[#111A3D] p-5">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 class="text-3xl font-black tracking-wide">{{ playerName }}</h1>
              <p class="text-white/60 text-sm mt-1">
                Height: {{ playerHeaderMeta.height }} · Weight: {{ playerHeaderMeta.weight }} · Position: {{ playerHeaderMeta.position }}
              </p>
            </div>
            <RouterLink
              to="/development"
              class="self-start md:self-auto rounded-xl bg-[#FF2D55] px-4 py-2 text-sm font-black uppercase tracking-wider"
            >
              Player Metrics
            </RouterLink>
          </div>
        </section>

        <section class="grid grid-cols-2 gap-3">
          <button
            v-for="tab in topTabs"
            :key="tab.key"
            class="rounded-xl border px-4 py-3 text-lg font-black tracking-[0.2em]"
            :class="activeTopTab === tab.key ? 'border-[#FF2D55] bg-[#FF2D55]/20 text-white' : 'border-white/15 bg-white/5 text-white/70'"
            @click="activeTopTab = tab.key"
          >
            {{ tab.label }}
          </button>
        </section>

        <section v-if="activeTopTab === 'stats'" class="rounded-2xl border border-white/10 bg-[#0D1536] p-4">
          <div class="flex flex-wrap gap-2 mb-4">
            <button
              v-for="tab in statTabs"
              :key="tab.key"
              class="rounded-full border px-4 py-2 text-sm font-black"
              :class="activeStatTab === tab.key ? 'border-[#FF2D55] bg-[#FF2D55] text-white' : 'border-white/20 bg-white/5 text-white/65'"
              @click="activeStatTab = tab.key"
            >
              {{ tab.label }}
            </button>
          </div>

          <div v-if="loading" class="py-10 text-center text-white/50">Loading player stats…</div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div v-for="card in activeCards" :key="card.label" class="rounded-xl border border-white/10 bg-white/5 p-4">
              <div class="text-xs uppercase tracking-widest text-white/55">{{ card.label }}</div>
              <div class="mt-2 text-3xl font-black">
                {{ card.value ?? '—' }}<span v-if="card.value !== null && card.value !== undefined" class="text-base text-white/60 ml-1">{{ card.unit }}</span>
              </div>
            </div>
          </div>

          <div
            v-if="activeStatTab === 'bullpen' && !loading"
            class="mt-4 rounded-xl border border-white/10 bg-white/5 p-4"
          >
            <div class="text-xs uppercase tracking-widest text-white/55 mb-3">Strike % by Pitch Type</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div v-for="pt in bullpenMetrics.byType" :key="pt.type" class="rounded-lg border border-white/10 bg-[#0B1230] p-3">
                <div class="text-xs text-white/55">{{ pt.type }}</div>
                <div class="text-lg font-black mt-1">{{ pt.strikePct ?? '—' }}<span v-if="pt.strikePct !== null" class="text-sm text-white/55">%</span></div>
                <div class="text-sm text-white/65 mt-1">Avg: {{ pt.avgVelo ?? '—' }}<span v-if="pt.avgVelo !== null"> mph</span></div>
              </div>
            </div>
          </div>
        </section>

        <section v-else class="rounded-2xl border border-white/10 bg-[#0D1536] p-6">
          <h2 class="text-xl font-black mb-2">Recap</h2>
          <p class="text-white/60 text-sm">
            Recap mode mirrors mobile HomePlayer summary behavior and will auto-refresh from the same player session feeds.
            Use the STATS tab for live BP, Bullpen, Cage, Weighted, and Exit Velocity metrics.
          </p>
        </section>
      </div>
    </div>
  </Layout>
</template>
