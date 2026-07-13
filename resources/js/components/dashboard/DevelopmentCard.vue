<script setup>
// A single player's "Development Card" — the polished, photo-forward card used in
// the Player Development Board carousel. Self-contained: it derives status/score
// colours internally so it can be dropped anywhere the board data is available.
import { computed } from 'vue'

const props = defineProps({
  player: { type: Object, required: true },
  team: { type: Object, default: () => ({}) },
})

const ACCENT = {
  hot: '#f59e0b', improving: '#22c55e', steady: '#eab308', needs_work: '#ef4444', no_data: '#64748b',
}
const STATUS_TEXT = {
  hot: 'Hot', improving: 'Improving', steady: 'Steady', needs_work: 'Needs Work', no_data: 'No Data',
}
const SESSION_TYPES = [
  { key: 'batting', label: 'BP' }, { key: 'bullpen', label: 'Bull' }, { key: 'cage', label: 'Cage' },
  { key: 'exit_velocity', label: 'EV' }, { key: 'long_toss', label: 'LT' }, { key: 'weight_ball', label: 'WB' },
]
const METRICS = [
  { key: 'batting', label: 'Batting FPS' }, { key: 'bullpen', label: 'Bullpen BPS' },
  { key: 'cage', label: 'Cage FCS' }, { key: 'ev', label: 'Exit Velo EVS' },
]
const WEIGHT_ROOM = [
  { key: 'body_weight', label: 'Wt' }, { key: 'bench_press', label: 'Bench' },
  { key: 'front_squat', label: 'FSQ' }, { key: 'power_clean', label: 'PC' },
]

const scoreColor = (s) => {
  if (s === null || s === undefined || Number.isNaN(Number(s))) return '#64748B'
  const n = Number(s)
  if (n >= 90) return '#2ECC71'
  if (n >= 80) return '#27AE60'
  if (n >= 70) return '#F39C12'
  if (n >= 60) return '#E67E22'
  return '#E74C3C'
}
const trendIcon = (t) => (t === 'up' ? '↑' : t === 'down' ? '↓' : '→')
const trendClass = (t) => (t === 'up' ? 'dc-up' : t === 'down' ? 'dc-down' : 'dc-flat')

const status = computed(() => props.player?.status || 'no_data')
const accent = computed(() => ACCENT[status.value] || ACCENT.no_data)
const statusText = computed(() => STATUS_TEXT[status.value] || 'No Data')
const overall = computed(() => {
  const s = props.player?.scores?.overall
  return s != null ? Math.round(s) : null
})
const name = computed(() => props.player?.name || 'Player')
const initials = computed(() => {
  const parts = String(name.value).trim().split(/\s+/)
  return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase() || '?'
})

const teamLogo = computed(() =>
  props.team?.logo || props.team?.picture || props.team?.image || props.team?.logo_url || props.team?.team_logo || null)
const teamName = computed(() => props.team?.name || props.team?.team_name || '')
const teamMonogram = computed(() => {
  const parts = String(teamName.value).trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return 'FM'
  return (parts.length === 1 ? parts[0].slice(0, 3) : parts.map((p) => p[0]).join('')).toUpperCase().slice(0, 3)
})

const metricVal = (key) => {
  const s = props.player?.scores?.[key]
  return s != null ? Math.round(s) : null
}
const metricPrev = (key) => {
  const s = props.player?.prev_scores?.[key]
  return s != null ? Math.round(s) : null
}
const fitnessVal = (key) => {
  const v = props.player?.fitness?.[key]
  return (v == null || Number(v) === 0) ? '—' : v
}
const fitnessRank = (key) => {
  const r = props.player?.fitness_rank?.[key]
  return (r && r.rank && r.total) ? `#${r.rank}/${r.total}` : ''
}
</script>

<template>
  <article class="dc" :style="{ '--accent': accent }">
    <!-- upper region: identity + photo -->
    <div class="dc-top">
      <div class="dc-photo" aria-hidden="true">
        <img v-if="player.picture" :src="player.picture" :alt="name" />
        <div v-else class="dc-photo-fallback">{{ player.jersey != null ? `#${player.jersey}` : initials }}</div>
      </div>
      <div class="dc-accent" aria-hidden="true"></div>
      <div class="dc-texture" aria-hidden="true"></div>

      <div class="dc-top-content">
        <header class="dc-head">
          <div class="dc-logo">
            <img v-if="teamLogo" :src="teamLogo" :alt="teamName || 'Team'" />
            <span v-else>{{ teamMonogram }}</span>
          </div>
          <div class="dc-wordmark">Development<br>Card</div>
        </header>

        <div class="dc-identity">
          <div class="dc-avatar">
            <img v-if="player.picture" :src="player.picture" :alt="name" />
            <span v-else>{{ initials }}</span>
          </div>
          <div class="dc-id-text">
            <div class="dc-player-label">Player</div>
            <div class="dc-name">{{ name }}<span v-if="player.jersey != null" class="dc-jersey">#{{ player.jersey }}</span></div>
          </div>
        </div>

        <div class="dc-stats">
          <div class="dc-stat">
            <span class="dc-stat-lbl">Status</span>
            <span class="dc-stat-val" :style="{ color: accent }">{{ statusText }}</span>
          </div>
          <div class="dc-stat">
            <span class="dc-stat-lbl">Score</span>
            <span class="dc-stat-val" :style="{ color: scoreColor(overall) }">{{ overall ?? '—' }}</span>
          </div>
          <div class="dc-stat">
            <span class="dc-stat-lbl">Trend</span>
            <span class="dc-stat-val dc-trend" :class="trendClass(player.trend)">{{ trendIcon(player.trend) }}</span>
          </div>
        </div>

        <div class="dc-sessions">
          <div class="dc-sess-lbl">Sessions (30d)</div>
          <div class="dc-pills">
            <span
              v-for="st in SESSION_TYPES" :key="st.key"
              class="dc-pill" :class="{ 'dc-pill--on': (player.coverage?.[st.key] ?? 0) > 0 }"
            >{{ st.label }} {{ player.coverage?.[st.key] ?? 0 }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- footer region: metrics + weight room (full width, solid) -->
    <div class="dc-footer">
      <div class="dc-metrics">
        <div v-for="m in METRICS" :key="m.key" class="dc-metric">
          <div class="dc-metric-lbl">{{ m.label }}</div>
          <div class="dc-metric-val" :style="{ color: scoreColor(metricVal(m.key)) }">{{ metricVal(m.key) ?? '—' }}</div>
          <div class="dc-metric-prev">prev: {{ metricPrev(m.key) ?? '—' }}</div>
        </div>
      </div>

      <div class="dc-weight">
        <div class="dc-weight-lbl">Weight Room Standing</div>
        <div class="dc-weight-grid">
          <div v-for="w in WEIGHT_ROOM" :key="w.key" class="dc-weight-item">
            {{ w.label }}: <b>{{ fitnessVal(w.key) }}</b>
            <span class="dc-weight-rank">{{ fitnessRank(w.key) || '—' }}</span>
          </div>
        </div>
      </div>
    </div>
  </article>
</template>

<style scoped>
.dc {
  position: relative;
  border-radius: 20px;
  overflow: hidden;
  background: linear-gradient(135deg, #0c1f38 0%, #081221 58%, #060d18 100%);
  border: 1px solid rgba(255, 255, 255, .08);
  box-shadow: 0 18px 44px rgba(0, 0, 0, .45);
  color: #e6edf6;
  isolation: isolate;
}

/* ── upper region ── */
.dc-top { position: relative; min-height: 236px; overflow: hidden; }
.dc-photo { position: absolute; inset: 0 0 0 auto; width: 58%; }
.dc-photo img { width: 100%; height: 100%; object-fit: cover; object-position: center 18%; }
.dc-photo-fallback {
  width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
  font-size: 62px; font-weight: 900; color: rgba(255, 255, 255, .05); letter-spacing: -2px;
  background: radial-gradient(120% 90% at 80% 20%, rgba(34, 197, 94, .10), transparent 60%);
}
/* fade the photo's left edge into the card so content stays readable */
.dc-photo::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(100deg, #081221 6%, rgba(8, 18, 33, .72) 34%, rgba(8, 18, 33, .12) 62%, transparent 88%);
}
/* diagonal status-coloured wedge on the left */
.dc-accent {
  position: absolute; inset: 0;
  background: linear-gradient(118deg, color-mix(in srgb, var(--accent) 42%, transparent) 0%, transparent 46%);
  clip-path: polygon(0 0, 60% 0, 34% 100%, 0 100%);
  opacity: .5;
}
/* faint geometric texture top-right */
.dc-texture {
  position: absolute; inset: 0; opacity: .5;
  background-image:
    repeating-linear-gradient(60deg, rgba(255, 255, 255, .035) 0 1px, transparent 1px 16px),
    repeating-linear-gradient(-60deg, rgba(255, 255, 255, .03) 0 1px, transparent 1px 22px);
  -webkit-mask-image: linear-gradient(to left, #000 0%, transparent 55%);
          mask-image: linear-gradient(to left, #000 0%, transparent 55%);
}
.dc-top-content { position: relative; z-index: 2; padding: 16px 18px; max-width: 66%; }

.dc-head { display: flex; align-items: center; gap: 11px; margin-bottom: 16px; }
.dc-logo {
  width: 38px; height: 38px; border-radius: 9px; flex: none; overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .12);
  font-size: 11px; font-weight: 900; letter-spacing: .5px; color: #cfe0f2;
}
.dc-logo img { width: 100%; height: 100%; object-fit: contain; }
.dc-wordmark {
  font-size: 15px; line-height: .98; font-weight: 900; letter-spacing: 1.5px;
  text-transform: uppercase; color: #fff; text-shadow: 0 1px 8px rgba(0, 0, 0, .5);
}

.dc-identity { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
.dc-avatar {
  width: 40px; height: 40px; border-radius: 50%; flex: none; overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  background: rgba(34, 197, 94, .18); border: 2px solid color-mix(in srgb, var(--accent) 55%, transparent);
  font-size: 14px; font-weight: 900; color: #fff;
}
.dc-avatar img { width: 100%; height: 100%; object-fit: cover; }
.dc-player-label { font-size: 9px; font-weight: 800; letter-spacing: 1.6px; text-transform: uppercase; color: rgba(255, 255, 255, .4); }
.dc-name { font-size: 18px; font-weight: 900; color: #fff; line-height: 1.05; text-shadow: 0 1px 6px rgba(0, 0, 0, .55); }
.dc-jersey { font-size: 12px; font-weight: 800; color: rgba(255, 255, 255, .45); margin-left: 6px; }

.dc-stats { display: flex; gap: 20px; margin-bottom: 15px; }
.dc-stat { display: flex; flex-direction: column; gap: 2px; }
.dc-stat-lbl { font-size: 9px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase; color: rgba(255, 255, 255, .38); }
.dc-stat-val { font-size: 17px; font-weight: 900; }
.dc-trend.dc-up { color: #22c55e; }
.dc-trend.dc-down { color: #ef4444; }
.dc-trend.dc-flat { color: rgba(255, 255, 255, .4); }

.dc-sess-lbl { font-size: 9px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase; color: rgba(255, 255, 255, .38); margin-bottom: 6px; }
.dc-pills { display: flex; flex-wrap: wrap; gap: 5px; }
.dc-pill {
  font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 6px; white-space: nowrap;
  color: rgba(255, 255, 255, .28); background: rgba(255, 255, 255, .03); border: 1px solid rgba(255, 255, 255, .07);
}
.dc-pill--on { color: #dbeafe; background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .2); }

/* ── footer region ── */
.dc-footer { position: relative; z-index: 2; background: #070e1a; border-top: 1px solid rgba(255, 255, 255, .07); }
.dc-metrics { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; padding: 14px 16px; }
.dc-metric { text-align: center; }
.dc-metric-lbl { font-size: 8.5px; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: rgba(255, 255, 255, .34); margin-bottom: 3px; }
.dc-metric-val { font-size: 22px; font-weight: 900; font-variant-numeric: tabular-nums; line-height: 1; }
.dc-metric-prev { font-size: 9px; color: rgba(255, 255, 255, .3); margin-top: 3px; }
.dc-weight { padding: 0 16px 14px; }
.dc-weight-lbl { font-size: 9px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase; color: rgba(255, 255, 255, .32); margin-bottom: 7px; }
.dc-weight-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.dc-weight-item { font-size: 11px; color: rgba(255, 255, 255, .62); white-space: nowrap; }
.dc-weight-item b { color: #fff; font-weight: 800; }
.dc-weight-rank { color: #fca5a5; font-weight: 700; margin-left: 3px; }

@media (max-width: 380px) {
  .dc-top-content { max-width: 74%; }
  .dc-photo { width: 48%; }
  .dc-weight-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
