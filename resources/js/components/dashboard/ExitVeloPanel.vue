<script setup>
/**
 * ExitVeloPanel.vue — Exit Velocity (EVS) visualization for the coach dashboard.
 *
 * Exit Velocity training records ONLY velocity + trajectory (Ground / Fly / Line
 * Drive) — no spray or field position — so it must NOT reuse the batting spray
 * field. This panel shows avg/top EV, the GB/FB/LD trajectory mix, and the EV
 * quality distribution, all from the EV-training detail only.
 */
import { computed } from 'vue'

const props = defineProps({
  // perfDetail.ev — the backend `evs` object.
  detail: { type: Object, default: null },
})

const has = computed(() => props.detail && Number(props.detail.total) > 0)

const num = (v) => (v == null || v === '' || Number.isNaN(Number(v)) ? '—' : v)

// Exit velocity only tracks Line Drive / Fly Ball / Ground Ball. Each row shows
// the AVERAGE exit velocity for that trajectory (the distribution is already
// covered by the quality bar below). Bar width scales the mph to a 0–110 range.
const EV_CEILING = 110
const trajRows = computed(() => {
  const d = props.detail || {}
  const row = (key, label, color, raw) => {
    const avg = raw == null || raw === '' || Number.isNaN(Number(raw)) ? null : Number(raw)
    return { key, label, color, avg, pct: avg != null ? Math.max(0, Math.min(100, (avg / EV_CEILING) * 100)) : 0 }
  }
  return [
    row('ld', 'Line Drive', '#37D67A', d.ldAvgEV),
    row('fb', 'Fly Ball', '#3B82F6', d.fbAvgEV),
    row('gb', 'Ground Ball', '#F59E0B', d.gbAvgEV),
  ]
})

const rangeSegs = computed(() => {
  const r = props.detail?.rangePercents || {}
  return [
    { key: 'below', label: 'Below', pct: Number(r.below_average) || 0, color: '#EF4444' },
    { key: 'avg', label: 'Avg', pct: Number(r.average) || 0, color: '#F59E0B' },
    { key: 'above', label: 'Above', pct: Number(r.above_average) || 0, color: '#3B82F6' },
    { key: 'elite', label: 'Elite', pct: Number(r.elite) || 0, color: '#37D67A' },
  ].filter((s) => s.pct > 0)
})
</script>

<template>
  <div class="evp">
    <template v-if="has">
      <!-- Avg / Top callouts -->
      <div class="evp-callouts">
        <div class="evp-callout">
          <div class="evp-c-val" style="color:#37D67A">{{ num(detail.avgEV) }}<span class="evp-c-u">mph</span></div>
          <div class="evp-c-l">Avg Exit Velo</div>
        </div>
        <div class="evp-callout">
          <div class="evp-c-val" style="color:#3B82F6">{{ num(detail.topEV) }}<span class="evp-c-u">mph</span></div>
          <div class="evp-c-l">Top Exit Velo</div>
        </div>
      </div>

      <!-- Avg exit velocity per trajectory (GB / FB / LD only) -->
      <div class="evp-block">
        <div class="evp-block-head">Avg Exit Velocity by Trajectory</div>
        <div class="evp-traj">
          <div v-for="t in trajRows" :key="t.key" class="evp-row">
            <span class="evp-label">{{ t.label }}</span>
            <div class="evp-track"><div class="evp-fill" :style="{ width: t.pct + '%', background: t.color }" /></div>
            <span class="evp-pct" :style="{ color: t.color }">{{ t.avg != null ? t.avg : '—' }}<em v-if="t.avg != null"> mph</em></span>
          </div>
        </div>
      </div>

      <!-- EV quality distribution -->
      <div v-if="rangeSegs.length" class="evp-block">
        <div class="evp-block-head">Exit Velocity Quality</div>
        <div class="evp-segbar">
          <div v-for="s in rangeSegs" :key="s.key" :style="{ flex: s.pct, background: s.color }" />
        </div>
        <div class="evp-seglegend">
          <span v-for="s in rangeSegs" :key="s.key" class="evp-seg-l" :style="{ color: s.color }">{{ s.label }} {{ s.pct }}%</span>
        </div>
      </div>
    </template>

    <div v-else class="evp-empty">No exit velocity swings yet</div>
  </div>
</template>

<style scoped>
.evp { display: flex; flex-direction: column; gap: 14px; min-height: 220px; justify-content: center; padding: 6px 2px; }
.evp-callouts { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.evp-callout { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 14px; text-align: center; }
.evp-c-val { font-size: 34px; font-weight: 900; line-height: 1; font-variant-numeric: tabular-nums; }
.evp-c-u { font-size: 13px; font-weight: 700; opacity: 0.65; margin-left: 4px; }
.evp-c-l { margin-top: 6px; font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255,255,255,0.4); }

.evp-block-head { font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255,255,255,0.42); margin-bottom: 8px; }
.evp-traj { display: flex; flex-direction: column; gap: 8px; }
.evp-row { display: flex; align-items: center; gap: 10px; }
.evp-label { width: 92px; flex-shrink: 0; font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.82); }
.evp-track { flex: 1; height: 10px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden; }
.evp-fill { height: 100%; border-radius: 999px; transition: width 0.4s; }
.evp-pct { width: 66px; text-align: right; font-size: 14px; font-weight: 900; font-variant-numeric: tabular-nums; }
.evp-pct em { font-style: normal; font-size: 10px; font-weight: 600; opacity: 0.7; }

.evp-segbar { display: flex; height: 22px; border-radius: 6px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); }
.evp-seglegend { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 6px; }
.evp-seg-l { font-size: 11px; font-weight: 800; }

.evp-empty { text-align: center; color: rgba(255,255,255,0.4); font-size: 13px; padding: 40px 0; }
</style>
