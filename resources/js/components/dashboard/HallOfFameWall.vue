<script setup>
/**
 * HallOfFameWall.vue — the FMTRX facility "Hall of Fame Wall".
 *
 * ONE rotating leaderboard experience (not many cards). Every 5s it cycles to the
 * next category: the Top-5 board (left) and the #1 featured athlete (right) update
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
  interval: { type: Number, default: 5 },
})

const idx = ref(0)
const countdown = ref(props.interval)
const paused = ref(false)
const showAll = ref(false)
let timer = null

const cats = computed(() => (Array.isArray(props.categories) ? props.categories.filter(Boolean) : []))
const active = computed(() => cats.value[idx.value] ?? cats.value[0] ?? null)
const color = computed(() => active.value?.color || '#ef4444')

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
onBeforeUnmount(() => timer && clearInterval(timer))
watch(() => cats.value.length, (n) => { if (idx.value >= n) idx.value = 0 })

const clock = computed(() => `00:${String(Math.max(0, countdown.value)).padStart(2, '0')}`)
const onAvatarError = (e) => { if (props.fallbackAvatar) e.target.src = props.fallbackAvatar }

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
</script>

<template>
  <div class="hof" :style="{ '--accent': color }" @mouseenter="paused = true" @mouseleave="paused = false">
    <div v-if="!active" class="hof-empty">No leaderboard data yet</div>

    <Transition v-else name="hof" mode="out-in">
      <div class="hof-stage" :key="active.value">
        <!-- LEFT: Top 5 board -->
        <div class="hof-board">
          <div class="hof-board-head">
            <div>
              <div class="hof-title">Top 10 {{ active.label }}</div>
              <div class="hof-sub">{{ active.subtitle }}</div>
            </div>
            <div class="hof-timer">Updating in <b>{{ clock }}</b></div>
          </div>

          <div class="hof-cols"><span>Rank</span><span>Player</span><span class="right">Score</span></div>

          <div class="hof-rows">
            <div v-for="(row, i) in active.rows.slice(0, 5)" :key="i" class="hof-row" :class="{ 'is-first': i === 0 }">
              <span class="hof-rank">{{ i + 1 }}</span>
              <img class="hof-ava" :src="row.avatar || fallbackAvatar" @error="onAvatarError" alt="" />
              <div class="hof-who">
                <div class="hof-name">{{ row.name }}</div>
                <div v-if="row.subtitle" class="hof-pos">{{ row.subtitle }}</div>
              </div>
              <span class="hof-trend" :class="trendClass(row.trend)" :title="row.trend == null ? 'Trend pending' : 'Change since last ranking'">
                {{ trendGlyph(row.trend) }}<template v-if="row.trend"> {{ trendText(row.trend) }}</template>
              </span>
              <span class="hof-score">{{ row.value }}</span>
            </div>
            <div v-if="!active.rows.length" class="hof-none">
              {{ active.placeholder ? 'Score coming soon' : 'No ranked players yet' }}
            </div>
          </div>

          <button v-if="active.rows.length" class="hof-viewall" @click="showAll = true">
            View Full Top 10 <span>›</span>
          </button>
        </div>

        <!-- RIGHT: Featured athlete -->
        <div class="hof-feature">
          <template v-if="active.featured">
            <div class="hof-feature-badge">Top Performer</div>
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
                  <span class="hof-bigtrend-cap">vs last 30 days</span>
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

            <div v-if="active.featured.subMetrics && active.featured.subMetrics.length" class="hof-metrics">
              <div v-for="m in active.featured.subMetrics" :key="m.label" class="hof-metric">
                <div class="hof-metric-v">{{ m.value }}<small v-if="m.unit && m.value !== '—'"> {{ m.unit }}</small></div>
                <div class="hof-metric-l">{{ m.label }}</div>
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
    <div class="hof-foot">
      <div class="hof-dots">
        <button v-for="(c, i) in cats" :key="c.value" class="hof-dot" :class="{ on: i === idx }"
          :style="i === idx ? { background: color } : {}" @click="goTo(i)" :aria-label="`Show ${c.label}`" />
      </div>
      <div class="hof-refresh">Data refreshes every {{ interval }} seconds</div>
    </div>

    <!-- Full Top 10 modal -->
    <Transition name="hof-modal">
      <div v-if="showAll && active" class="hof-modal-bg" @click.self="showAll = false">
        <div class="hof-modal" :style="{ '--accent': color }">
          <div class="hof-modal-head">
            <div>
              <div class="hof-modal-title">Top 10 {{ active.label }}</div>
              <div class="hof-sub">{{ active.subtitle }}</div>
            </div>
            <button class="hof-modal-x" @click="showAll = false" aria-label="Close">✕</button>
          </div>
          <div class="hof-modal-rows">
            <div v-for="(row, i) in active.rows" :key="i" class="hof-row" :class="{ 'is-first': i === 0 }">
              <span class="hof-rank">{{ i + 1 }}</span>
              <img class="hof-ava" :src="row.avatar || fallbackAvatar" @error="onAvatarError" alt="" />
              <div class="hof-who">
                <div class="hof-name">{{ row.name }}</div>
                <div v-if="row.subtitle" class="hof-pos">{{ row.subtitle }}</div>
              </div>
              <span class="hof-trend" :class="trendClass(row.trend)">{{ trendGlyph(row.trend) }}<template v-if="row.trend"> {{ trendText(row.trend) }}</template></span>
              <span class="hof-score">{{ row.value }}</span>
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
.hof-empty, .hof-feature-empty { color: rgba(255,255,255,.4); font-size: 13px; padding: 40px 0; }

.hof-stage { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); gap: 18px; align-items: stretch; }
@media (max-width: 860px) { .hof-stage { grid-template-columns: 1fr; } }

/* LEFT board */
.hof-board { display: flex; flex-direction: column; }
.hof-board-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.hof-title { font-size: 17px; font-weight: 900; letter-spacing: .02em; color: #fff; text-transform: uppercase; }
.hof-sub { font-size: 11px; color: rgba(255,255,255,.42); margin-top: 2px; }
.hof-timer { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.4); white-space: nowrap; }
.hof-timer b { color: var(--accent); font-variant-numeric: tabular-nums; margin-left: 3px; }
.hof-cols { display: grid; grid-template-columns: 34px 1fr auto; gap: 10px; font-size: 9px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: rgba(255,255,255,.3); padding: 0 4px 8px; border-bottom: 1px solid rgba(255,255,255,.07); }
.hof-cols .right { text-align: right; }
.hof-rows { display: flex; flex-direction: column; margin-top: 4px; }
.hof-row { display: grid; grid-template-columns: 34px auto 1fr auto auto; align-items: center; gap: 10px; padding: 9px 4px; border-bottom: 1px solid rgba(255,255,255,.05); }
.hof-row.is-first { background: color-mix(in srgb, var(--accent) 12%, transparent); border-radius: 10px; border-bottom-color: transparent; }
.hof-rank { width: 24px; height: 24px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900; color: rgba(255,255,255,.55); background: rgba(255,255,255,.06); }
.hof-row.is-first .hof-rank { background: var(--accent); color: #0a1020; }
.hof-ava { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,.15); }
.hof-who { min-width: 0; }
.hof-name { font-size: 14px; font-weight: 800; color: #fff; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hof-pos { font-size: 10px; color: rgba(255,255,255,.4); }
.hof-trend { font-size: 11px; font-weight: 800; font-variant-numeric: tabular-nums; min-width: 18px; text-align: center; }
.hof-trend.up { color: #37d67a; } .hof-trend.down { color: #ef4444; } .hof-trend.flat { color: rgba(255,255,255,.3); }
.hof-score { font-size: 16px; font-weight: 900; font-variant-numeric: tabular-nums; color: #fff; }
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
.hof-feature-name { font-size: 26px; font-weight: 900; letter-spacing: -.01em; color: #fff; line-height: 1; text-transform: uppercase; }
.hof-feature-pos { font-size: 12px; font-weight: 700; color: rgba(255,255,255,.55); margin-top: 4px; }
.hof-bio { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.hof-bio-cell { display: flex; flex-direction: column; align-items: center; min-width: 52px; padding: 5px 8px; border-radius: 8px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); }
.hof-bio-k { font-size: 8px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.35); }
.hof-bio-v { font-size: 12px; font-weight: 800; color: #fff; }

.hof-bigrow { display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; margin-top: 16px; }
.hof-bigscore-lbl { font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: rgba(255,255,255,.42); }
.hof-bigscore-val { font-size: 44px; font-weight: 900; letter-spacing: -.02em; line-height: 1; color: var(--accent); font-variant-numeric: tabular-nums; margin-top: 2px; }
.hof-bigscore-unit { font-size: 15px; font-weight: 800; opacity: .7; margin-left: 6px; }
.hof-bigtrend { font-size: 11px; font-weight: 800; margin-top: 6px; display: flex; align-items: center; gap: 6px; }
.hof-bigtrend.up { color: #37d67a; } .hof-bigtrend.down { color: #ef4444; } .hof-bigtrend.flat { color: rgba(255,255,255,.35); }
.hof-bigtrend-cap { font-weight: 600; color: rgba(255,255,255,.35); }
.hof-spark { width: 96px; height: 30px; flex: none; }

.hof-metrics { margin-top: 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(84px, 1fr)); gap: 8px; }
.hof-metric { background: rgba(255,255,255,.035); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 9px 8px; text-align: center; }
.hof-metric-v { font-size: 16px; font-weight: 900; color: #fff; font-variant-numeric: tabular-nums; line-height: 1; }
.hof-metric-v small { font-size: 9px; font-weight: 700; opacity: .6; }
.hof-metric-l { font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: rgba(255,255,255,.38); margin-top: 4px; }
.hof-coming { margin-top: 26px; color: rgba(255,255,255,.45); font-size: 14px; font-weight: 600; }

/* Footer */
.hof-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 14px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,.06); }
.hof-dots { display: flex; gap: 7px; flex-wrap: wrap; }
.hof-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,.16); border: none; cursor: pointer; padding: 0; transition: transform .2s; }
.hof-dot.on { transform: scale(1.35); }
.hof-refresh { font-size: 9px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: rgba(255,255,255,.3); }

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
