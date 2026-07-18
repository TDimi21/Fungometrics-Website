<script setup>
/**
 * HallOfFameWall.vue — the FMTRX facility "Hall of Fame Wall".
 *
 * ONE rotating leaderboard experience (not many cards). Every 5s it cycles to the
 * next category: the Top-25 board (left) and the #1 featured athlete (right) update
 * together with a color theme, a countdown, and smooth transitions.
 *
 * Every slot in the design is rendered — trend chips, sparklines, and sub-metric
 * tiles show a placeholder ("—" / flat line) when the backend hasn't supplied the
 * value yet, so the card is visually complete and ready to be filled.
 *
 * Each `category` (fully resolved by the parent):
 *   { value, label, subtitle, color, emoji?, unit, bigLabel, placeholder,
 *     rows:     [{ name, avatar, subtitle, value, trend, spark }],
 *     featured: { name, avatar, subtitle, bigValue, trend, spark,
 *                 bio:[{k,v}], subMetrics:[{label,value,unit}] } }
 */
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  fallbackAvatar: { type: String, default: '' },
  interval: { type: Number, default: 12 },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const idx = ref(0)
const countdown = ref(props.interval)
const paused = ref(false)
const showAll = ref(false)
const hofRoot = ref(null)
const isFullscreen = ref(false)
let timer = null

const cats = computed(() => (Array.isArray(props.categories) ? props.categories.filter(Boolean) : []))
const active = computed(() => cats.value[idx.value] ?? cats.value[0] ?? null)
const color = computed(() => active.value?.color || '#ef4444')
const activeLimit = computed(() => Number(active.value?.limit) || 25)
const rankedRows = computed(() => (Array.isArray(active.value?.rows) ? active.value.rows.slice(0, activeLimit.value) : []))
const shouldScrollRows = computed(() => rankedRows.value.length > 5)

const advance = (step = 1) => {
  const n = cats.value.length
  if (!n) return
  idx.value = ((idx.value + step) % n + n) % n
  countdown.value = props.interval
  showAll.value = false
}
const goTo = (i) => { idx.value = i; countdown.value = props.interval; showAll.value = false }

onMounted(() => {
  timer = setInterval(() => {
    if (paused.value || showAll.value || !cats.value.length) return
    countdown.value -= 1
    if (countdown.value <= 0) advance(1)
  }, 1000)
})
const syncFullscreenState = () => {
  const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement
  isFullscreen.value = fullscreenElement === hofRoot.value
}
const toggleFullscreen = async () => {
  const el = hofRoot.value
  if (!el) return
  try {
    if (isFullscreen.value) {
      if (document.exitFullscreen) await document.exitFullscreen()
      else if (document.webkitExitFullscreen) document.webkitExitFullscreen()
    } else if (el.requestFullscreen) {
      await el.requestFullscreen()
    } else if (el.webkitRequestFullscreen) {
      el.webkitRequestFullscreen()
    }
  } catch (error) {
    console.warn('Hall of Fame fullscreen unavailable', error)
  }
}

onMounted(() => {
  document.addEventListener('fullscreenchange', syncFullscreenState)
  document.addEventListener('webkitfullscreenchange', syncFullscreenState)
})
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
  document.removeEventListener('fullscreenchange', syncFullscreenState)
  document.removeEventListener('webkitfullscreenchange', syncFullscreenState)
})
watch(() => cats.value.length, (n) => { if (idx.value >= n) idx.value = 0 })

const clock = computed(() => `00:${String(Math.max(0, countdown.value)).padStart(2, '0')}`)
const onAvatarError = (e) => {
  if (props.fallbackAvatar && e.target.src !== props.fallbackAvatar) e.target.src = props.fallbackAvatar
}

const trendClass = (t) => (t == null ? 'flat' : t > 0 ? 'up' : t < 0 ? 'down' : 'flat')
const trendGlyph = (t) => (t == null ? '—' : t > 0 ? '↑' : t < 0 ? '↓' : '→')
const trendText = (t) => (t == null ? '' : Math.abs(Number(t)))

// Sparkline → SVG points; null/short arrays return null so we draw a placeholder line.
const SPARK_W = 88
const SPARK_H = 26
const sparkPoints = (arr) => {
  if (!Array.isArray(arr) || arr.length < 2) return null
  const nums = arr.map(Number).filter((n) => Number.isFinite(n))
  if (nums.length < 2) return null
  const min = Math.min(...nums)
  const max = Math.max(...nums)
  const span = max - min || 1
  return nums.map((v, i) => `${((i / (nums.length - 1)) * SPARK_W).toFixed(1)},${(SPARK_H - ((v - min) / span) * SPARK_H).toFixed(1)}`).join(' ')
}
const profileChartPoints = (arr) => {
  if (!Array.isArray(arr) || arr.length < 2) return null
  const values = arr.map((point) => Number(point?.velocity)).filter(Number.isFinite)
  if (values.length < 2) return null
  const min = Math.min(...values)
  const max = Math.max(...values)
  const span = max - min || 1
  return values.map((value, i) => `${10 + ((i / (values.length - 1)) * 180)},${52 - ((value - min) / span) * 38}`).join(' ')
}
const profileChartY = (arr, index) => Number(profileChartPoints(arr)?.split(' ')[index]?.split(',')[1] ?? 52)
</script>

<template>
  <div ref="hofRoot" class="hof" :class="{ 'is-fullscreen': isFullscreen }" :style="{ '--accent': color }" @mouseenter="paused = true" @mouseleave="paused = false">
    <div v-if="loading" class="hof-state" role="status">
      <span class="hof-loader" />
      <strong>Building the Hall of Fame</strong>
      <span>Loading the latest team leaders…</span>
    </div>
    <div v-else-if="error" class="hof-state hof-state-error" role="alert">
      <strong>Leaderboard unavailable</strong>
      <span>{{ error }}</span>
    </div>
    <div v-else-if="!active" class="hof-state">
      <strong>No leaderboard data yet</strong>
      <span>Record a team session or assessment to establish the first leader.</span>
    </div>

    <Transition v-else-if="active" name="hof" mode="out-in">
      <div class="hof-stage" :key="active.value">
        <!-- LEFT: Top 5 board -->
        <div class="hof-board">
          <div class="hof-board-head">
            <div>
              <div class="hof-kicker"><span class="hof-icon">{{ active.icon || '★' }}</span> FMTRX Hall of Fame</div>
              <div class="hof-title">{{ active.label }}</div>
              <div class="hof-sub">{{ active.subtitle }}</div>
            </div>
            <div class="hof-timer">Updating in <b>{{ clock }}</b></div>
          </div>

          <div class="hof-cols"><span>Rank</span><span>Player</span><span class="right">Score</span></div>

          <div class="hof-rows" :class="{ 'is-scrolling': shouldScrollRows, 'is-paused': paused }" :style="{ '--scroll-duration': `${interval}s` }">
            <div class="hof-scroll-track">
              <template v-for="copy in (shouldScrollRows ? 2 : 1)" :key="copy">
                <div v-for="(row, i) in rankedRows" :key="`${copy}-${row.id || row.name}-${i}`" class="hof-row" :class="{ 'is-first': i === 0 }">
                  <span class="hof-rank">{{ i + 1 }}</span>
                  <img class="hof-ava" :src="row.avatar || fallbackAvatar" @error="onAvatarError" alt="" />
                  <div class="hof-who">
                    <div class="hof-name">{{ row.name }}</div>
                    <div v-if="row.subtitle" class="hof-pos">{{ row.subtitle }}</div>
                    <div v-if="row.evidence" class="hof-evidence">{{ row.evidence }}</div>
                  </div>
                  <span class="hof-trend" :class="trendClass(row.trend)" :title="row.trend == null ? 'Trend pending' : 'Change since last ranking'">
                    {{ trendGlyph(row.trend) }}<template v-if="row.trend"> {{ trendText(row.trend) }}</template>
                  </span>
                  <span class="hof-score">{{ row.value }}<small v-if="active.unit && row.value !== '—'"> {{ active.unit }}</small></span>
                </div>
              </template>
            </div>
            <div v-if="!rankedRows.length" class="hof-none">
              {{ active.placeholder ? 'Score coming soon' : 'No ranked players yet' }}
            </div>
          </div>

          <button v-if="active.rows.length" class="hof-viewall" @click="showAll = true">
            View Full Top {{ activeLimit }} <span>›</span>
          </button>
        </div>

        <!-- RIGHT: Featured athlete -->
        <div class="hof-feature">
          <template v-if="active.featured">
            <div class="hof-feature-badge"><span>{{ active.icon || '★' }}</span> Featured Athlete</div>
            <div class="hof-rank-badge">Rank #1</div>

            <div class="hof-feature-head">
              <img class="hof-feature-photo" :src="active.featured.avatar || fallbackAvatar" @error="onAvatarError" alt="" />
              <div class="hof-feature-id">
                <div class="hof-feature-name">{{ active.featured.name }}</div>
                <div v-if="active.featured.subtitle" class="hof-feature-pos">{{ active.featured.subtitle }}</div>
                <div v-if="active.featured.bio && active.featured.bio.length" class="hof-bio">
                  <div v-for="b in active.featured.bio" :key="b.k" class="hof-bio-cell">
                    <span class="hof-bio-k">{{ b.k }}</span>
                    <span class="hof-bio-v">{{ b.v }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="hof-bigrow">
              <div class="hof-bigscore">
                <div class="hof-bigscore-lbl">{{ active.bigLabel }}</div>
                <div class="hof-bigscore-val">{{ active.featured.bigValue }}<span v-if="active.unit" class="hof-bigscore-unit">{{ active.unit }}</span></div>
                <div class="hof-bigtrend" :class="trendClass(active.featured.trend)">
                  {{ trendGlyph(active.featured.trend) }}<template v-if="active.featured.trend"> {{ trendText(active.featured.trend) }}</template>
                  <span class="hof-bigtrend-cap">vs prior period</span>
                </div>
              </div>
              <!-- sparkline (placeholder flat line until history is supplied) -->
              <svg class="hof-spark" :viewBox="`0 0 ${SPARK_W} ${SPARK_H}`" preserveAspectRatio="none">
                <template v-if="sparkPoints(active.featured.spark)">
                  <polyline :points="sparkPoints(active.featured.spark)" fill="none" :stroke="color" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </template>
                <line v-else :x1="0" :y1="SPARK_H / 2" :x2="SPARK_W" :y2="SPARK_H / 2" stroke="rgba(255,255,255,0.18)" stroke-width="1.5" stroke-dasharray="3 4" />
              </svg>
            </div>

            <div v-if="active.featured.profileChart && active.featured.profileChart.length > 1" class="hof-profile-chart">
              <div class="hof-profile-chart-title">Velocity by Ball Weight</div>
              <svg viewBox="0 0 200 66" preserveAspectRatio="none" aria-label="Weighted-ball velocity spectrum">
                <line x1="10" y1="52" x2="190" y2="52" stroke="rgba(255,255,255,.12)" stroke-width="1" />
                <polyline :points="profileChartPoints(active.featured.profileChart)" fill="none" :stroke="color" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <g v-for="(point, pointIndex) in active.featured.profileChart" :key="point.weight">
                  <circle :cx="10 + ((pointIndex / (active.featured.profileChart.length - 1)) * 180)" :cy="profileChartY(active.featured.profileChart, pointIndex)" r="3.5" :fill="color" />
                  <text :x="10 + ((pointIndex / (active.featured.profileChart.length - 1)) * 180)" y="64" text-anchor="middle">{{ point.weight }} oz</text>
                  <text :x="10 + ((pointIndex / (active.featured.profileChart.length - 1)) * 180)" :y="profileChartY(active.featured.profileChart, pointIndex) - 6" text-anchor="middle" class="value">{{ point.velocity }}</text>
                </g>
              </svg>
            </div>

            <div v-if="active.featured.subMetrics && active.featured.subMetrics.length" class="hof-metrics">
              <div v-for="m in active.featured.subMetrics" :key="m.label" class="hof-metric">
                <div class="hof-metric-v">{{ m.value }}<small v-if="m.unit && m.value !== '—'"> {{ m.unit }}</small></div>
                <div class="hof-metric-l">{{ m.label }}</div>
              </div>
            </div>
            <div v-if="active.featured.insight" class="hof-insight">
              <div class="hof-insight-title">FMTRX Insight</div>
              <div v-for="line in active.featured.insight.lines" :key="line" class="hof-insight-line">{{ line }}</div>
              <div v-if="active.featured.insight.projected_mph_gain != null" class="hof-insight-projection">
                Projected mound velocity gain
                <strong>{{ active.featured.insight.projected_mph_gain > 0 ? '+' : '' }}{{ active.featured.insight.projected_mph_gain }} mph</strong>
              </div>
            </div>
          </template>
          <div v-else class="hof-feature-empty">
            <div class="hof-feature-badge">Top Performer</div>
            <div class="hof-coming">{{ active.placeholder ? 'This score is coming soon.' : 'Awaiting a ranked athlete for this category.' }}</div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Footer: category dots -->
    <div v-if="active && !loading && !error" class="hof-progress" :style="{ transform: `scaleX(${Math.max(0, countdown) / interval})` }" />
    <div v-if="active && !loading && !error" class="hof-foot">
      <div class="hof-controls">
        <button type="button" class="hof-nav" @click="advance(-1)" aria-label="Previous leaderboard">‹</button>
        <button type="button" class="hof-nav" @click="paused = !paused" :aria-label="paused ? 'Resume rotation' : 'Pause rotation'">{{ paused ? '▶' : 'Ⅱ' }}</button>
        <button type="button" class="hof-nav" @click="advance(1)" aria-label="Next leaderboard">›</button>
      </div>
      <div class="hof-dots">
        <button v-for="(c, i) in cats" :key="c.value" class="hof-dot" :class="{ on: i === idx }"
          :style="i === idx ? { background: color } : {}" @click="goTo(i)" :aria-label="`Show ${c.label}`" />
      </div>
      <div class="hof-foot-actions">
        <div class="hof-refresh">{{ paused ? 'Rotation paused' : `Rotating every ${interval} seconds` }}</div>
        <button type="button" class="hof-present" @click="toggleFullscreen" :aria-label="isFullscreen ? 'Exit TV presentation' : 'Present Hall of Fame on TV'">
          <span aria-hidden="true">{{ isFullscreen ? '↙' : '⛶' }}</span>
          {{ isFullscreen ? 'Exit Presentation' : 'Present on TV' }}
        </button>
      </div>
    </div>

    <!-- Full Top 25 modal -->
    <Transition name="hof-modal">
      <div v-if="showAll && active" class="hof-modal-bg" @click.self="showAll = false">
        <div class="hof-modal" :style="{ '--accent': color }">
          <div class="hof-modal-head">
            <div>
              <div class="hof-modal-title">Top {{ activeLimit }} • {{ active.label }}</div>
              <div class="hof-sub">{{ active.subtitle }}</div>
            </div>
            <button class="hof-modal-x" @click="showAll = false" aria-label="Close">✕</button>
          </div>
          <div class="hof-modal-rows">
            <div v-for="(row, i) in active.rows" :key="row.id || row.name" class="hof-row" :class="{ 'is-first': i === 0 }">
              <span class="hof-rank">{{ i + 1 }}</span>
              <img class="hof-ava" :src="row.avatar || fallbackAvatar" @error="onAvatarError" alt="" />
              <div class="hof-who">
                <div class="hof-name">{{ row.name }}</div>
                <div v-if="row.subtitle" class="hof-pos">{{ row.subtitle }}</div>
              </div>
              <span class="hof-trend" :class="trendClass(row.trend)">{{ trendGlyph(row.trend) }}<template v-if="row.trend"> {{ trendText(row.trend) }}</template></span>
              <span class="hof-score">{{ row.value }}<small v-if="active.unit && row.value !== '—'"> {{ active.unit }}</small></span>
            </div>
            <div v-if="!active.rows.length" class="hof-none">No ranked players yet</div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.hof {
  --accent: #ef4444;
  position: relative;
  border-radius: 18px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background:
    radial-gradient(120% 140% at 85% 0%, color-mix(in srgb, var(--accent) 14%, transparent) 0%, transparent 55%),
    #0a1020;
  box-shadow: 0 18px 45px rgba(0, 0, 0, 0.4), inset 0 0 0 1px rgba(255, 255, 255, 0.02);
  overflow: hidden;
  padding: 18px 18px 12px;
}
.hof-state { min-height: 360px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: rgba(255,255,255,.42); text-align: center; padding: 40px 20px; }
.hof-state strong { color: #fff; font-size: 17px; text-transform: uppercase; letter-spacing: .06em; }
.hof-state-error strong { color: #f87171; }
.hof-loader { width: 28px; height: 28px; border: 3px solid rgba(255,255,255,.12); border-top-color: var(--accent); border-radius: 50%; animation: hof-spin .8s linear infinite; }
@keyframes hof-spin { to { transform: rotate(360deg); } }
.hof-feature-empty { color: rgba(255,255,255,.4); font-size: 13px; padding: 40px 0; }

.hof-stage { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); gap: 18px; align-items: stretch; }
@media (max-width: 860px) { .hof-stage { grid-template-columns: 1fr; } }

/* LEFT board */
.hof-board { display: flex; flex-direction: column; }
.hof-board-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.hof-kicker { display: flex; align-items: center; gap: 7px; color: var(--accent); font-size: 9px; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; margin-bottom: 4px; }
.hof-icon { width: 25px; height: 25px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid color-mix(in srgb, var(--accent) 55%, transparent); background: color-mix(in srgb, var(--accent) 15%, transparent); border-radius: 8px; font-size: 13px; }
.hof-title { font-size: clamp(17px, 1.55vw, 24px); font-weight: 900; letter-spacing: .02em; color: #fff; text-transform: uppercase; }
.hof-sub { font-size: 11px; color: rgba(255,255,255,.42); margin-top: 2px; }
.hof-timer { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.4); white-space: nowrap; }
.hof-timer b { color: var(--accent); font-variant-numeric: tabular-nums; margin-left: 3px; }
.hof-cols { display: grid; grid-template-columns: 34px 1fr auto; gap: 10px; font-size: 9px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: rgba(255,255,255,.3); padding: 0 4px 8px; border-bottom: 1px solid rgba(255,255,255,.07); }
.hof-cols .right { text-align: right; }
.hof-rows { --row-height: 49px; display: flex; flex-direction: column; margin-top: 4px; height: calc(var(--row-height) * 5); overflow: hidden; }
.hof-scroll-track { display: flex; flex-direction: column; }
.hof-rows.is-scrolling .hof-scroll-track { animation: hof-scroll-list var(--scroll-duration, 12s) linear infinite; }
.hof-rows.is-paused .hof-scroll-track { animation-play-state: paused; }
@keyframes hof-scroll-list { to { transform: translateY(-50%); } }
.hof-row { min-height: var(--row-height); box-sizing: border-box; display: grid; grid-template-columns: 34px auto 1fr auto auto; align-items: center; gap: 10px; padding: 9px 4px; border-bottom: 1px solid rgba(255,255,255,.05); }
.hof-row.is-first { background: color-mix(in srgb, var(--accent) 12%, transparent); border-radius: 10px; border-bottom-color: transparent; }
.hof-rank { width: 24px; height: 24px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900; color: rgba(255,255,255,.55); background: rgba(255,255,255,.06); }
.hof-row.is-first .hof-rank { background: var(--accent); color: #0a1020; }
.hof-ava { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,.15); }
.hof-who { min-width: 0; }
.hof-name { font-size: 14px; font-weight: 800; color: #fff; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hof-pos { font-size: 10px; color: rgba(255,255,255,.4); }
.hof-evidence { margin-top: 2px; font-size: 9px; font-weight: 800; color: var(--accent); text-transform: uppercase; letter-spacing: .04em; }
.hof-trend { font-size: 11px; font-weight: 800; font-variant-numeric: tabular-nums; min-width: 18px; text-align: center; }
.hof-trend.up { color: #37d67a; } .hof-trend.down { color: #ef4444; } .hof-trend.flat { color: rgba(255,255,255,.3); }
.hof-score { font-size: 16px; font-weight: 900; font-variant-numeric: tabular-nums; color: #fff; }
.hof-score small { font-size: 8px; font-weight: 800; opacity: .45; text-transform: uppercase; }
.hof-row.is-first .hof-score { color: var(--accent); }
.hof-none { padding: 24px 0; text-align: center; color: rgba(255,255,255,.3); font-size: 12px; }
.hof-viewall { margin-top: auto; padding-top: 12px; align-self: flex-start; font-size: 11px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; color: var(--accent); cursor: pointer; display: inline-flex; align-items: center; gap: 5px; background: none; border: none; }
.hof-viewall span { font-size: 14px; }

/* RIGHT featured */
.hof-feature {
  position: relative; border-radius: 14px; padding: 16px 18px;
  border: 1px solid color-mix(in srgb, var(--accent) 40%, rgba(255,255,255,.08));
  background:
    radial-gradient(90% 120% at 100% 0%, color-mix(in srgb, var(--accent) 22%, transparent) 0%, transparent 60%),
    rgba(255,255,255,0.02);
}
.hof-feature-badge { position: absolute; top: 14px; left: 16px; font-size: 9px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; color: var(--accent); }
.hof-rank-badge { position: absolute; top: 12px; right: 14px; font-size: 10px; font-weight: 900; letter-spacing: .05em; text-transform: uppercase; color: var(--accent); border: 1px solid color-mix(in srgb, var(--accent) 55%, transparent); border-radius: 6px; padding: 3px 8px; background: color-mix(in srgb, var(--accent) 14%, transparent); }
.hof-feature-head { display: flex; gap: 16px; align-items: center; margin-top: 22px; }
.hof-feature-photo { width: 92px; height: 92px; border-radius: 14px; object-fit: cover; object-position: center 18%; border: 2px solid color-mix(in srgb, var(--accent) 50%, rgba(255,255,255,.1)); flex: none; box-shadow: 0 8px 24px rgba(0,0,0,.4); }
.hof-feature-id { min-width: 0; }
.hof-feature-name { font-size: clamp(26px, 2.6vw, 42px); font-weight: 900; letter-spacing: -.01em; color: #fff; line-height: 1; text-transform: uppercase; }
.hof-feature-pos { font-size: 12px; font-weight: 700; color: rgba(255,255,255,.55); margin-top: 4px; }
.hof-bio { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.hof-bio-cell { display: flex; flex-direction: column; align-items: center; min-width: 52px; padding: 5px 8px; border-radius: 8px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); }
.hof-bio-k { font-size: 8px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.35); }
.hof-bio-v { font-size: 12px; font-weight: 800; color: #fff; }

.hof-bigrow { display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; margin-top: 16px; }
.hof-bigscore-lbl { font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: rgba(255,255,255,.42); }
.hof-bigscore-val { font-size: clamp(44px, 5vw, 72px); font-weight: 900; letter-spacing: -.02em; line-height: 1; color: var(--accent); font-variant-numeric: tabular-nums; margin-top: 2px; }
.hof-bigscore-unit { font-size: 15px; font-weight: 800; opacity: .7; margin-left: 6px; }
.hof-bigtrend { font-size: 11px; font-weight: 800; margin-top: 6px; display: flex; align-items: center; gap: 6px; }
.hof-bigtrend.up { color: #37d67a; } .hof-bigtrend.down { color: #ef4444; } .hof-bigtrend.flat { color: rgba(255,255,255,.35); }
.hof-bigtrend-cap { font-weight: 600; color: rgba(255,255,255,.35); }
.hof-spark { width: 96px; height: 30px; flex: none; }
.hof-profile-chart { margin-top: 12px; padding: 9px 10px 5px; border-radius: 10px; border: 1px solid rgba(255,255,255,.07); background: rgba(255,255,255,.025); }
.hof-profile-chart-title { margin-bottom: 2px; color: rgba(255,255,255,.42); font-size: 8px; font-weight: 900; letter-spacing: .1em; text-transform: uppercase; }
.hof-profile-chart svg { display: block; width: 100%; height: 74px; overflow: visible; }
.hof-profile-chart text { fill: rgba(255,255,255,.42); font-size: 6px; font-weight: 800; }
.hof-profile-chart text.value { fill: rgba(255,255,255,.82); font-size: 7px; }

.hof-metrics { margin-top: 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(84px, 1fr)); gap: 8px; }
.hof-metric { background: rgba(255,255,255,.035); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 9px 8px; text-align: center; }
.hof-metric-v { font-size: 16px; font-weight: 900; color: #fff; font-variant-numeric: tabular-nums; line-height: 1; }
.hof-metric-v small { font-size: 9px; font-weight: 700; opacity: .6; }
.hof-metric-l { font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: rgba(255,255,255,.38); margin-top: 4px; }
.hof-insight { margin-top: 12px; padding: 11px 12px; border-radius: 10px; border: 1px solid color-mix(in srgb, var(--accent) 25%, rgba(255,255,255,.07)); background: color-mix(in srgb, var(--accent) 7%, rgba(255,255,255,.02)); }
.hof-insight-title { margin-bottom: 5px; color: var(--accent); font-size: 9px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; }
.hof-insight-line { color: rgba(255,255,255,.72); font-size: 11px; line-height: 1.45; }
.hof-insight-projection { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 7px; padding-top: 7px; border-top: 1px solid rgba(255,255,255,.07); color: rgba(255,255,255,.42); font-size: 9px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
.hof-insight-projection strong { color: var(--accent); font-size: 13px; }
.hof-coming { margin-top: 26px; color: rgba(255,255,255,.45); font-size: 14px; font-weight: 600; }

/* Footer */
.hof-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 14px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,.06); }
.hof-progress { height: 2px; margin: 13px -18px -1px; background: var(--accent); transform-origin: left center; transition: transform 1s linear; opacity: .72; }
.hof-controls { display: flex; align-items: center; gap: 5px; }
.hof-nav { width: 25px; height: 25px; display: inline-flex; align-items: center; justify-content: center; border-radius: 7px; border: 1px solid rgba(255,255,255,.1); background: rgba(255,255,255,.04); color: rgba(255,255,255,.62); cursor: pointer; font-size: 12px; font-weight: 900; }
.hof-nav:hover { color: #fff; border-color: color-mix(in srgb, var(--accent) 55%, transparent); }
.hof-dots { display: flex; gap: 7px; flex-wrap: wrap; }
.hof-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,.16); border: none; cursor: pointer; padding: 0; transition: transform .2s; }
.hof-dot.on { transform: scale(1.35); }
.hof-refresh { font-size: 9px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: rgba(255,255,255,.3); }
.hof-foot-actions { display: flex; align-items: center; gap: 12px; }
.hof-present { display: inline-flex; align-items: center; gap: 7px; padding: 7px 11px; border-radius: 8px; border: 1px solid color-mix(in srgb, var(--accent) 45%, rgba(255,255,255,.1)); background: color-mix(in srgb, var(--accent) 12%, rgba(255,255,255,.03)); color: #fff; cursor: pointer; font-size: 9px; font-weight: 900; letter-spacing: .07em; text-transform: uppercase; white-space: nowrap; }
.hof-present:hover { background: color-mix(in srgb, var(--accent) 24%, rgba(255,255,255,.05)); }
.hof-present span { font-size: 14px; line-height: 1; }

/* TV presentation mode */
.hof:fullscreen, .hof.is-fullscreen {
  width: 100vw;
  height: 100vh;
  box-sizing: border-box;
  border: 0;
  border-radius: 0;
  padding: clamp(24px, 3vw, 56px);
  display: flex;
  flex-direction: column;
  justify-content: center;
  background:
    radial-gradient(110% 130% at 85% 0%, color-mix(in srgb, var(--accent) 20%, transparent) 0%, transparent 58%),
    #060b14;
}
.hof:fullscreen .hof-stage, .hof.is-fullscreen .hof-stage { grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); gap: clamp(24px, 3vw, 56px); align-items: center; }
.hof:fullscreen .hof-title, .hof.is-fullscreen .hof-title { font-size: clamp(28px, 3vw, 52px); }
.hof:fullscreen .hof-name, .hof.is-fullscreen .hof-name { font-size: clamp(17px, 1.4vw, 25px); }
.hof:fullscreen .hof-row, .hof.is-fullscreen .hof-row { padding: clamp(10px, 1.2vh, 18px) 8px; }
.hof:fullscreen .hof-ava, .hof.is-fullscreen .hof-ava { width: clamp(38px, 3vw, 54px); height: clamp(38px, 3vw, 54px); }
.hof:fullscreen .hof-feature-photo, .hof.is-fullscreen .hof-feature-photo { width: clamp(130px, 12vw, 210px); height: clamp(130px, 12vw, 210px); }
.hof:fullscreen .hof-feature-name, .hof.is-fullscreen .hof-feature-name { font-size: clamp(38px, 4vw, 72px); }
.hof:fullscreen .hof-bigscore-val, .hof.is-fullscreen .hof-bigscore-val { font-size: clamp(72px, 8vw, 140px); }
.hof:fullscreen .hof-foot, .hof.is-fullscreen .hof-foot { margin-top: clamp(18px, 2vh, 30px); }
.hof:fullscreen .hof-progress, .hof.is-fullscreen .hof-progress { margin-left: calc(clamp(24px, 3vw, 56px) * -1); margin-right: calc(clamp(24px, 3vw, 56px) * -1); }
@media (max-width: 760px) {
  .hof-foot { gap: 10px; flex-wrap: wrap; }
  .hof-dots { order: 3; width: 100%; justify-content: center; }
  .hof-refresh { display: none; }
}

/* Transition */
.hof-enter-active, .hof-leave-active { transition: opacity .45s ease, transform .45s ease; }
.hof-enter-from { opacity: 0; transform: translateX(18px); }
.hof-leave-to { opacity: 0; transform: translateX(-18px); }
@media (prefers-reduced-motion: reduce) {
  .hof-enter-active, .hof-leave-active { transition: opacity .2s ease; }
  .hof-enter-from, .hof-leave-to { transform: none; }
}

/* Modal */
.hof-modal-bg { position: fixed; inset: 0; z-index: 60; background: rgba(3, 6, 15, 0.72); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 20px; }
.hof-modal { --accent: #ef4444; width: 100%; max-width: 520px; max-height: 82vh; overflow-y: auto; border-radius: 18px; border: 1px solid rgba(255,255,255,.12); background: #0b1223; box-shadow: 0 30px 80px rgba(0,0,0,.55); padding: 18px; }
.hof-modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.hof-modal-title { font-size: 16px; font-weight: 900; color: #fff; text-transform: uppercase; }
.hof-modal-x { background: rgba(255,255,255,.06); border: none; color: #fff; width: 30px; height: 30px; border-radius: 8px; cursor: pointer; font-size: 13px; }
.hof-modal-rows { display: flex; flex-direction: column; }
.hof-modal-enter-active, .hof-modal-leave-active { transition: opacity .2s ease; }
.hof-modal-enter-from, .hof-modal-leave-to { opacity: 0; }
</style>
