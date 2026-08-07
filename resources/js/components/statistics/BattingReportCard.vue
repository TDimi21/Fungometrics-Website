<script setup>
/**
 * BattingReportCard.vue
 *
 * App-parity batting summary. Ports the visual + scoring language of the mobile
 * app's BattingPracticeSessionReportScreen (FPS report) so the web batting stats
 * feel like a continuation of the app for the same user.
 *
 * Feed it ball-by-ball rows (each row may carry a nested `batting` object with
 * quality_of_contact / type_of_hit / velocity, or those fields at the top level).
 * Renders: hero FPS score, quick stat strip, Exit Velocity bars, Contact Quality
 * (bar + segmented bar), Launch Profile (segmented bar + grid), At-Bat Quality,
 * Performance Score breakdown, and auto-feedback tips.
 */
import { computed } from 'vue'
import StatRow from '@/components/session-report/SessionReportStatRow.vue'
import SegBar from '@/components/session-report/SessionReportSegmentBar.vue'

const props = defineProps({
  balls: { type: Array, default: () => [] },
  // Shown under the hero score ("12 swings", "Team", a player name, etc.)
  subject: { type: String, default: '' },
})

const BP_COLOR = '#F39C12'

// ─── Normalisation (mirrors the app) ────────────────────────────────────────
const normQOC = (raw) => {
  const s = String(raw ?? '').toUpperCase().trim()
  if (s === 'H' || s === 'HARD') return 'H'
  if (s === 'A' || s === 'AVERAGE') return 'A'
  if (s === 'W' || s === 'WEAK') return 'W'
  if (s === 'MF' || s === 'F') return 'MF'
  return ''
}
const normTOH = (raw) => {
  const s = String(raw ?? '').toUpperCase().trim()
  if (s === 'LD' || s.includes('LINE')) return 'LD'
  if (s === 'FB' || s.includes('FLY')) return 'FB'
  if (s === 'PF' || s.includes('POP') || s.includes('FOUL')) return 'PF'
  if (s === 'GB' || s.includes('GROUND')) return 'GB'
  return ''
}

const fpsGrade = (score) => {
  if (score >= 90) return { color: '#2ECC71', letter: 'A+', label: '🔥 Elite Round' }
  if (score >= 80) return { color: '#27AE60', letter: 'A', label: 'Strong Development' }
  if (score >= 70) return { color: '#F39C12', letter: 'B', label: 'Productive' }
  if (score >= 60) return { color: '#E67E22', letter: 'C', label: 'Inconsistent' }
  return { color: '#E74C3C', letter: 'D', label: 'Needs Adjustment' }
}

// ─── FPS computation (identical algorithm to the app) ───────────────────────
const breakdown = computed(() => {
  const balls = Array.isArray(props.balls) ? props.balls : []
  if (!balls.length) return null

  const swings = balls.map((b) => {
    const qoc = normQOC(b.batting?.quality_of_contact ?? b.quality_of_contact ?? '')
    const toh = normTOH(b.batting?.type_of_hit ?? b.type_of_hit ?? b.trajectory ?? '')
    const rawEV = parseFloat(
      b.batting?.velocity ?? b.batting?.exit_velocity ?? b.exit_velocity ?? b.velocity ?? 0,
    )
    const ev = rawEV >= 10 && rawEV <= 125 ? rawEV : null
    return { qoc, toh, ev }
  })

  const total = swings.length
  const swingsWithQOC = swings.filter((s) => s.qoc === 'H' || s.qoc === 'A' || s.qoc === 'W')
  const contactScore = swingsWithQOC.length > 0
    ? swingsWithQOC.reduce((sum, s) => sum + (s.qoc === 'H' ? 100 : s.qoc === 'A' ? 70 : 40), 0) / swingsWithQOC.length
    : 50

  const evSwings = swings.filter((s) => s.ev !== null)
  const avgEV = evSwings.length ? evSwings.reduce((a, s) => a + s.ev, 0) / evSwings.length : 0
  const topEV = evSwings.length ? Math.max(...evSwings.map((s) => s.ev)) : 0
  const evScore = topEV > 0 ? Math.min(100, (avgEV / topEV) * 100) : 0

  const hardCount = swings.filter((s) => s.ev !== null && s.ev >= 90).length
  const avgCount = swings.filter((s) => s.qoc === 'A').length
  const weakCount = swings.filter((s) => s.qoc === 'W').length
  const evTotal = evSwings.length
  const hardPct = evTotal > 0 ? Math.round((hardCount / evTotal) * 100) : 0
  const avgPct = evTotal > 0 ? Math.round((avgCount / evTotal) * 100) : 0
  const weakPct = evTotal > 0 ? Math.round((weakCount / evTotal) * 100) : 0

  const launchScores = swings
    .map((s) => (s.toh === 'LD' ? 100 : s.toh === 'FB' ? 80 : s.toh === 'PF' ? 60 : s.toh === 'GB' ? 50 : null))
    .filter((v) => v !== null)
  const launchScore = launchScores.length > 0 ? launchScores.reduce((a, b) => a + b, 0) / launchScores.length : 50

  const ldCount = swings.filter((s) => s.toh === 'LD').length
  const flyCount = swings.filter((s) => s.toh === 'FB').length
  const pfCount = swings.filter((s) => s.toh === 'PF').length
  const gbCount = swings.filter((s) => s.toh === 'GB').length
  const tohTotal = ldCount + flyCount + pfCount + gbCount
  const ldPct = tohTotal > 0 ? Math.round((ldCount / tohTotal) * 100) : 0
  const flyPct = tohTotal > 0 ? Math.round((flyCount / tohTotal) * 100) : 0
  const pfPct = tohTotal > 0 ? Math.round((pfCount / tohTotal) * 100) : 0
  const gbPct = tohTotal > 0 ? Math.round((gbCount / tohTotal) * 100) : 0

  const compCount = swings.filter((s) => s.qoc === 'H' || (s.qoc === 'A' && (s.toh === 'LD' || s.toh === 'FB'))).length
  const compScore = (compCount / total) * 100
  const missCount = swings.filter((s) => s.qoc === 'MF' || (!s.qoc && !s.toh)).length
  const missPct = (missCount / total) * 100
  const missScore = Math.max(0, 100 - missPct)
  const fps = Math.round(contactScore * 0.3 + evScore * 0.25 + launchScore * 0.2 + compScore * 0.15 + missScore * 0.1)

  return {
    total,
    fps,
    contactScore: Math.round(contactScore),
    evScore: Math.round(evScore),
    launchScore: Math.round(launchScore),
    compScore: Math.round(compScore),
    missScore: Math.round(missScore),
    avgEV: Math.round(avgEV * 10) / 10,
    topEV: Math.round(topEV * 10) / 10,
    compPct: Math.round(compScore),
    missPct: Math.round(missPct),
    evTotal,
    hardPct,
    avgPct,
    weakPct,
    ldPct,
    flyPct,
    pfPct,
    gbPct,
    tohTotal,
  }
})

const grade = computed(() => (breakdown.value ? fpsGrade(breakdown.value.fps) : null))

const FPS_COMPONENTS = [
  { key: 'contactScore', label: '🟢 Contact Quality (30%)' },
  { key: 'evScore', label: '🔵 Exit Velocity (25%)' },
  { key: 'launchScore', label: '🟡 Launch Profile (20%)' },
  { key: 'compScore', label: '🟣 Competitive Swings (15%)' },
  { key: 'missScore', label: '🔴 Miss Control (10%)' },
]

const launchGrid = computed(() => {
  const d = breakdown.value
  if (!d) return []
  return [
    { label: 'Line Drive', pct: d.ldPct, color: '#3498DB' },
    { label: 'Fly Ball', pct: d.flyPct, color: '#2ECC71' },
    { label: 'Pop Fly', pct: d.pfPct, color: '#F39C12' },
    { label: 'Ground Ball', pct: d.gbPct, color: '#E74C3C' },
  ].filter((r) => r.pct > 0)
})

const tips = computed(() => {
  const d = breakdown.value
  if (!d) return []
  const t = []
  if (d.hardPct >= 40) t.push({ icon: '💥', text: `${d.hardPct}% hard contact — you're driving the ball well. Keep attacking the zone.` })
  else if (d.hardPct >= 20) t.push({ icon: '🎯', text: `${d.hardPct}% hard contact. Focus on staying through the ball and using the whole field.` })
  else t.push({ icon: '⚠️', text: 'Hard contact rate is low. Work on bat path and making solid contact before swinging harder.' })
  if (d.avgEV >= 85) t.push({ icon: '⚡', text: `Avg exit velocity of ${d.avgEV} mph is excellent. Real barrel power.` })
  else if (d.avgEV >= 72) t.push({ icon: '📈', text: `Avg exit velocity of ${d.avgEV} mph is solid. Strength training and timing can push this higher.` })
  else if (d.avgEV > 0) t.push({ icon: '🏋️', text: `Avg exit velocity of ${d.avgEV} mph. Work on hip rotation and bat speed.` })
  if (d.ldPct >= 35) t.push({ icon: '🟢', text: `${d.ldPct}% line drives — excellent launch profile for run production.` })
  else if (d.gbPct >= 50) t.push({ icon: '🔻', text: `High ground ball rate (${d.gbPct}%). Elevate your swing plane.` })
  else if (d.flyPct >= 40) t.push({ icon: '🔵', text: `Strong fly ball rate (${d.flyPct}%). Pair with launch-angle control to avoid pop-ups.` })
  if (d.compPct >= 40) t.push({ icon: '🔥', text: `${d.compPct}% competitive swings — game-quality contact consistently.` })
  if (d.missPct >= 30) t.push({ icon: '🔄', text: `${d.missPct}% miss rate. Slow the swing down in drills and focus on contact before adding intensity.` })
  return t
})
</script>

<template>
  <div v-if="breakdown" class="brc">
    <!-- ── Hero + quick stat strip ─────────────────────────────────────── -->
    <div class="grid gap-4 md:grid-cols-[minmax(220px,280px)_1fr] mb-4">
      <!-- Score -->
      <div
        class="brc-card flex flex-col items-center justify-center py-6 text-center"
        :style="{ background: grade.color + '14', borderColor: grade.color + '55' }"
      >
        <span class="rounded-md px-3 py-1 text-[10px] font-black tracking-widest text-white" :style="{ backgroundColor: BP_COLOR }">
          BATTING PERFORMANCE
        </span>
        <div class="mt-3 text-[64px] font-black leading-none" :style="{ color: grade.color }">{{ breakdown.fps }}</div>
        <div class="-mt-1 text-[11px] font-extrabold tracking-[0.18em] text-white/45">FPS</div>
        <div class="mt-1 text-sm font-extrabold" :style="{ color: grade.color }">{{ grade.label }}</div>
        <div class="mt-1 text-[11px] text-white/45">
          {{ breakdown.total }} {{ breakdown.total === 1 ? 'swing' : 'swings' }}<template v-if="subject"> · {{ subject }}</template>
        </div>
      </div>

      <!-- Quick tiles -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="brc-tile">
          <div class="brc-tile-l">Avg Exit Velo</div>
          <div class="brc-tile-v" style="color:#3498DB">{{ breakdown.avgEV || '—' }}<span class="brc-tile-u">mph</span></div>
        </div>
        <div class="brc-tile">
          <div class="brc-tile-l">Top Exit Velo</div>
          <div class="brc-tile-v" style="color:#2ECC71">{{ breakdown.topEV || '—' }}<span class="brc-tile-u">mph</span></div>
        </div>
        <div class="brc-tile">
          <div class="brc-tile-l">Hard Contact</div>
          <div class="brc-tile-v" style="color:#F39C12">{{ breakdown.hardPct }}<span class="brc-tile-u">%</span></div>
        </div>
        <div class="brc-tile">
          <div class="brc-tile-l">Competitive</div>
          <div class="brc-tile-v" style="color:#9B59B6">{{ breakdown.compPct }}<span class="brc-tile-u">%</span></div>
        </div>
      </div>
    </div>

    <!-- ── Detail sections ─────────────────────────────────────────────── -->
    <div class="grid gap-4 lg:grid-cols-2">
      <!-- Exit Velocity -->
      <section class="brc-card p-4">
        <h3 class="brc-h">⚡ Exit Velocity</h3>
        <div class="space-y-3">
          <StatRow label="Avg Exit Velocity" :value="breakdown.avgEV" unit=" mph" :min="40" :max="110" :thresholds="[68, 87]" />
          <StatRow label="Top Exit Velocity" :value="breakdown.topEV" unit=" mph" :min="50" :max="115" :thresholds="[80, 97]" />
        </div>
      </section>

      <!-- Contact Quality -->
      <section class="brc-card p-4">
        <h3 class="brc-h">💥 Contact Quality ({{ breakdown.evTotal }} swings)</h3>
        <div class="space-y-3">
          <StatRow label="Hard Contact %" :value="breakdown.hardPct" unit="%" :min="0" :max="70" :thresholds="[20, 40]" />
          <SegBar :segments="[
            { label: `Hard ${breakdown.hardPct}%`, pct: breakdown.hardPct, color: '#2ECC71' },
            { label: `Avg ${breakdown.avgPct}%`, pct: breakdown.avgPct, color: '#F39C12' },
            { label: `Weak ${breakdown.weakPct}%`, pct: breakdown.weakPct, color: '#E74C3C' },
          ]" />
        </div>
      </section>

      <!-- Launch Profile -->
      <section v-if="breakdown.tohTotal > 0" class="brc-card p-4">
        <h3 class="brc-h">🚀 Launch Profile ({{ breakdown.tohTotal }} swings)</h3>
        <SegBar :segments="[
          { label: `LD ${breakdown.ldPct}%`, pct: breakdown.ldPct, color: '#3498DB' },
          { label: `FB ${breakdown.flyPct}%`, pct: breakdown.flyPct, color: '#2ECC71' },
          { label: `PF ${breakdown.pfPct}%`, pct: breakdown.pfPct, color: '#F39C12' },
          { label: `GB ${breakdown.gbPct}%`, pct: breakdown.gbPct, color: '#E74C3C' },
        ]" />
        <div class="mt-3 grid grid-cols-4 gap-2">
          <div v-for="item in launchGrid" :key="item.label" class="text-center">
            <div class="text-xl font-black" :style="{ color: item.color }">{{ item.pct }}%</div>
            <div class="mt-0.5 text-[9px] text-white/40">{{ item.label }}</div>
          </div>
        </div>
      </section>

      <!-- At-Bat Quality -->
      <section class="brc-card p-4">
        <h3 class="brc-h">🎯 At-Bat Quality</h3>
        <div class="space-y-3">
          <StatRow label="Competitive Swing %" :value="breakdown.compPct" unit="%" :min="0" :max="80" :thresholds="[25, 45]" />
          <StatRow label="Miss Rate" :value="breakdown.missPct" unit="%" :min="0" :max="60" :thresholds="[15, 30]" reverse />
        </div>
      </section>
    </div>

    <!-- Performance Score breakdown -->
    <section class="brc-card mt-4 p-4">
      <h3 class="brc-h">📊 Performance Score Breakdown</h3>
      <div class="space-y-3">
        <StatRow v-for="c in FPS_COMPONENTS" :key="c.key" :label="c.label" :value="breakdown[c.key]" unit="" :min="0" :max="100" :thresholds="[60, 80]" />
        <StatRow label="Batting Performance Score" :value="breakdown.fps" unit="" :min="0" :max="100" :thresholds="[60, 80]" />
      </div>
    </section>

    <!-- Tips -->
    <section v-if="tips.length" class="mt-4">
      <h3 class="brc-h mb-2">💡 What This Means</h3>
      <div class="space-y-2">
        <div v-for="(tip, i) in tips" :key="i" class="brc-card flex items-start gap-3 p-3">
          <span class="text-xl leading-none">{{ tip.icon }}</span>
          <span class="text-sm leading-snug text-white/85">{{ tip.text }}</span>
        </div>
      </div>
    </section>
  </div>

  <div v-else class="brc-card p-6 text-center text-white/55">
    No swing data with contact quality, launch, or exit velocity yet.
  </div>
</template>

<style scoped>
.brc-card {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 14px;
}
.brc-h {
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.85);
  margin-bottom: 12px;
}
.brc-tile {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  padding: 12px 10px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.brc-tile-l {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.4);
  margin-bottom: 4px;
}
.brc-tile-v {
  font-size: 26px;
  font-weight: 900;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}
.brc-tile-u {
  font-size: 12px;
  font-weight: 700;
  opacity: 0.65;
  margin-left: 3px;
}
</style>
