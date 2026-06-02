<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team.js'
import { storeToRefs } from 'pinia'

const { axiosPost } = useAxiosAuth()
const teamStore = useTeamStore()
const { team } = storeToRefs(teamStore)

// ─── state ────────────────────────────────────────────────────────────────────
const items = ref([])
const loading = ref(false)
const animPaused = ref(false)
const activeCategory = ref('ev')  // ev | pitch | distance | fitness

const CATEGORIES = [
  { key: 'ev',       label: 'Exit Velo',   option: 1,  unit: 'mph', icon: '⚡' },
  { key: 'pitch',    label: 'Pitch Velo',  option: 4,  unit: 'mph', icon: '🔥' },
  { key: 'distance', label: 'Long Toss',   option: 8,  unit: 'ft',  icon: '🎯' },
  { key: 'fitness',  label: 'Strength',    option: 10, unit: 'lbs', icon: '💪' },
]

// ─── helpers ──────────────────────────────────────────────────────────────────
const resolveTeamId = (t) => t?.id_team ?? t?.id ?? null

const fetchCategory = async (cat) => {
  const teamId = resolveTeamId(team.value)
  if (!teamId) return []
  try {
    const { data } = await axiosPost('table/' + teamId, { option: cat.option, range: 0 })
    const rows = data?.data?.all ?? []
    return rows
      .filter(r => r.name && (r.velocity ?? r.value ?? r.weight ?? 0) > 0)
      .slice(0, 10)
      .map((r, i) => ({
        rank:  i + 1,
        name:  r.name,
        value: r.velocity ?? r.value ?? r.weight ?? 0,
        unit:  cat.unit,
        icon:  cat.icon,
        label: cat.label,
      }))
  } catch (_) {
    return []
  }
}

// ─── load all categories in parallel, flatten into one ticker list ────────────
const load = async () => {
  const teamId = resolveTeamId(team.value)
  if (!teamId || loading.value) return
  loading.value = true
  try {
    const results = await Promise.all(CATEGORIES.map(fetchCategory))
    // Interleave: take top-3 from each category so the ticker is varied
    const merged = []
    const maxRows = Math.max(...results.map(r => r.length))
    for (let i = 0; i < Math.min(maxRows, 3); i++) {
      results.forEach(catRows => { if (catRows[i]) merged.push(catRows[i]) })
    }
    items.value = merged
  } finally {
    loading.value = false
  }
}

// ─── computed ticker string ───────────────────────────────────────────────────
const tickerItems = computed(() => {
  if (!items.value.length) return []
  return items.value
})

// ─── refresh every 5 minutes ─────────────────────────────────────────────────
let refreshTimer = null
onMounted(() => {
  load()
  refreshTimer = setInterval(load, 5 * 60 * 1000)
})
onUnmounted(() => clearInterval(refreshTimer))

// Re-fetch when team changes
watch(() => resolveTeamId(team.value), (newId, oldId) => {
  if (newId && newId !== oldId) load()
})
</script>

<template>
  <div
    v-if="tickerItems.length > 0"
    class="ticker-bar"
    @mouseenter="animPaused = true"
    @mouseleave="animPaused = false"
    aria-label="Top performers ticker"
    role="marquee"
  >
    <!-- Static label on the left -->
    <div class="ticker-label">
      <span class="ticker-label-icon">🏆</span>
      <span class="ticker-label-text">TOP&nbsp;10</span>
    </div>

    <!-- Divider -->
    <div class="ticker-divider"></div>

    <!-- Scrolling track — we duplicate the list so it loops seamlessly -->
    <div class="ticker-viewport">
      <div class="ticker-track" :class="{ 'ticker-track--paused': animPaused }">
        <!-- List rendered twice for seamless loop -->
        <template v-for="pass in 2" :key="pass">
          <span
            v-for="(item, idx) in tickerItems"
            :key="`${pass}-${idx}`"
            class="ticker-item"
          >
            <span class="ticker-item-icon">{{ item.icon }}</span>
            <span class="ticker-item-label">{{ item.label }}:</span>
            <span class="ticker-item-rank">#{{ item.rank }}</span>
            <span class="ticker-item-name">{{ item.name }}</span>
            <span class="ticker-item-value">{{ item.value }}<span class="ticker-item-unit"> {{ item.unit }}</span></span>
            <span class="ticker-sep" aria-hidden="true">·</span>
          </span>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── container ──────────────────────────────────────────────────────────────── */
.ticker-bar {
  display: flex;
  align-items: center;
  height: 36px;
  overflow: hidden;
  background: linear-gradient(90deg, rgba(192,0,0,0.18) 0%, rgba(0,20,60,0.55) 100%);
  border-bottom: 1px solid rgba(255,255,255,0.08);
  border-top: 1px solid rgba(192,0,0,0.2);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  position: relative;
  z-index: 36;
  width: 100%;
  flex-shrink: 0;
}

/* ── static label ────────────────────────────────────────────────────────────── */
.ticker-label {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  padding: 0 10px 0 12px;
  height: 100%;
  background: rgba(192,0,0,0.85);
  border-right: 1px solid rgba(255,255,255,0.15);
}
.ticker-label-icon {
  font-size: 14px;
  line-height: 1;
}
.ticker-label-text {
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.12em;
  color: #fff;
  white-space: nowrap;
  text-transform: uppercase;
}

/* ── divider ────────────────────────────────────────────────────────────────── */
.ticker-divider {
  width: 1px;
  height: 14px;
  background: rgba(255,255,255,0.18);
  flex-shrink: 0;
}

/* ── scrolling viewport ─────────────────────────────────────────────────────── */
.ticker-viewport {
  flex: 1;
  overflow: hidden;
  height: 100%;
  display: flex;
  align-items: center;
  /* fade edges */
  mask-image: linear-gradient(90deg, transparent 0px, black 28px, black calc(100% - 28px), transparent 100%);
  -webkit-mask-image: linear-gradient(90deg, transparent 0px, black 28px, black calc(100% - 28px), transparent 100%);
}

/* ── track ──────────────────────────────────────────────────────────────────── */
.ticker-track {
  display: flex;
  align-items: center;
  white-space: nowrap;
  animation: ticker-scroll 60s linear infinite;
  will-change: transform;
}
.ticker-track--paused {
  animation-play-state: paused;
}

@keyframes ticker-scroll {
  0%   { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

/* ── items ──────────────────────────────────────────────────────────────────── */
.ticker-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0 10px;
  font-size: 13px;
  color: rgba(255,255,255,0.9);
}
.ticker-item-icon {
  font-size: 13px;
}
.ticker-item-label {
  font-weight: 700;
  font-size: 12px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: rgba(255,200,200,0.85);
}
.ticker-item-rank {
  font-size: 11px;
  font-weight: 800;
  color: #C00000;
  background: rgba(192,0,0,0.18);
  border-radius: 3px;
  padding: 0 4px;
}
.ticker-item-name {
  font-weight: 600;
  color: #fff;
}
.ticker-item-value {
  font-weight: 900;
  color: #FED7AA;
  font-size: 13px;
  letter-spacing: 0.02em;
}
.ticker-item-unit {
  font-weight: 400;
  font-size: 11px;
  color: rgba(255,255,255,0.55);
}
.ticker-sep {
  color: rgba(255,255,255,0.2);
  font-size: 16px;
  padding-left: 10px;
}
</style>
