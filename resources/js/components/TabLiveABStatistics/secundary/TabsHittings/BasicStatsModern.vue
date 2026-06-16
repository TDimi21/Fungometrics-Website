<script setup>
// PROOF OF CONCEPT — modern LiveAB "Hitters · Basic Stats" table.
// Computes stats client-side from ball_x_ball using the ported app scoring
// logic + field adapter, rendered in the dark `app-*` palette.
import { computed } from 'vue'
import { adaptBallByBall } from '@/utils/liveabBallAdapter.js'

const props = defineProps({
  ballByBall: { type: Array, default: () => [] },
})

const CATEGORIES = [
  { key: 'H', label: 'H' },
  { key: 'AB', label: 'AB' },
  { key: 'PA', label: 'PA' },
  { key: 'TB', label: 'TOTAL BASES' },
  { key: '1B', label: '1B' },
  { key: '2B', label: '2B' },
  { key: '3B', label: '3B' },
  { key: 'HR', label: 'HR' },
  { key: 'K', label: 'K' },
  { key: 'BB', label: 'BB' },
  { key: 'HBP', label: 'HBP' },
  { key: 'BA', label: 'BA' },
  { key: 'OBP', label: 'OBP' },
  { key: 'SLG', label: 'SLG' },
  { key: 'OPS', label: 'OPS' },
]

// Ported from the app's _deriveOutcomeType (LiveABStatsScreen).
const deriveOutcomeType = (ball) => {
  const pr = String(ball?.play_result || '').trim().toUpperCase().replace(/\s+/g, '_')
  const tb = String(ball?.total_bases ?? '').trim().toUpperCase()
  if (['ERROR', 'ROE'].includes(pr)) return 'ERROR'
  if (
    pr.includes('STRIKEOUT') ||
    ['OUT', 'GROUND_OUT', 'FLY_OUT', 'LINE_OUT', 'POP_OUT', 'FOUL_OUT', 'FORCE_OUT', 'TAG_OUT', 'DOUBLE_PLAY', 'TRIPLE_PLAY', 'SAC_BUNT', 'SAC_FLY', 'INFIELD_FLY'].includes(pr)
  ) return 'OUT'
  if (['WALK', 'BB', 'IBB'].includes(pr) || ['4', 'BB', 'BB/HBB'].includes(tb)) return 'BB'
  if (pr === 'HBP' || ['6', 'HBP'].includes(tb)) return 'HBP'
  if (['1B', '1', 'SINGLE'].includes(pr) || ['1B', '1'].includes(tb)) return '1B'
  if (['2B', '2', 'DOUBLE'].includes(pr) || ['2B', '2'].includes(tb)) return '2B'
  if (['3B', '3', 'TRIPLE'].includes(pr) || ['3B', '3'].includes(tb)) return '3B'
  if (['HR', 'HOME_RUN'].includes(pr) || ['HR', '5'].includes(tb)) return 'HR'
  if (pr === 'K' || ['K', '7'].includes(tb)) return 'K'
  if (['8', 'OUT/E'].includes(tb)) return 'OUT'
  return ''
}

// Ported verbatim from the app's calculateHitterStats.
const calculateHitterStats = (data) => {
  const s = { AB: 0, PA: 0, H: 0, TBase: 0, b1: 0, b2: 0, b3: 0, HR: 0, k: 0, BB: 0, HBP: 0, Outs: 0 }

  data.forEach((el) => {
    const normalizedTB = String(el.total_bases ?? '').trim().toUpperCase()
    const playResult = String(el.play_result || '').trim().toUpperCase().replace(/\s+/g, '_')
    const outcomeType = deriveOutcomeType(el)

    const isStrikeout =
      normalizedTB === '7' || normalizedTB === 'K' || playResult === 'K' ||
      playResult.includes('STRIKEOUT') ||
      playResult === 'BUNT_FOUL_STRIKE_THREE' ||
      playResult === 'DROPPED_THIRD_STRIKE_SAFE' ||
      playResult === 'DROPPED_THIRD_STRIKE_OUT'

    const isWalk =
      normalizedTB === '4' || normalizedTB === 'BB' || normalizedTB === 'BB/HBB' ||
      playResult === 'WALK' || playResult === 'IBB' || playResult === 'BB'

    const isHbp = normalizedTB === '6' || normalizedTB === 'HBP' || playResult === 'HBP'

    const isCompletedPA = normalizedTB !== '' || isStrikeout || isWalk || isHbp || !!playResult

    if (isCompletedPA) s.PA += 1
    if (isCompletedPA && !isWalk && !isHbp && playResult !== 'CI') s.AB += 1

    switch (normalizedTB) {
      case '1B': case '1': s.b1 += 1; s.H += 1; s.TBase += 1; break
      case '2B': case '2': s.b2 += 1; s.H += 1; s.TBase += 2; break
      case '3B': case '3': s.b3 += 1; s.H += 1; s.TBase += 3; break
      case 'HR': case '5': s.HR += 1; s.H += 1; s.TBase += 4; break
      case 'BB': case 'BB/HBB': case '4': s.BB += 1; break
      case 'HBP': case '6': s.HBP += 1; break
      case 'K': case '7': s.k += 1; break
      default: break
    }

    if (outcomeType === 'OUT') s.Outs += 1
    if (isStrikeout && normalizedTB !== '7' && normalizedTB !== 'K') s.k += 1
  })

  const div = (n, d) => (d ? (n / d).toFixed(3) : '0.000')
  return {
    AB: s.AB, PA: s.PA, H: s.H, TB: s.TBase,
    '1B': s.b1, '2B': s.b2, '3B': s.b3, HR: s.HR, K: s.k, BB: s.BB, HBP: s.HBP,
    BA: div(s.H, s.AB),
    OBP: div(s.H + s.BB + s.HBP, s.PA),
    SLG: div(s.TBase, s.AB),
    OPS: (parseFloat(div(s.H + s.BB + s.HBP, s.PA)) + parseFloat(div(s.TBase, s.AB))).toFixed(3),
  }
}

const adapted = computed(() => adaptBallByBall(props.ballByBall).filter((b) => b.batter_id))

const players = computed(() => {
  const seen = new Map()
  for (const b of adapted.value) {
    if (!seen.has(b.batter_id)) seen.set(b.batter_id, b.batter_name || b.batter_id)
  }
  return [...seen.entries()].map(([id, name]) => ({ id, name }))
})

const teamStats = computed(() => calculateHitterStats(adapted.value))
const playerStats = computed(() =>
  players.value.map((p) => ({
    ...p,
    stats: calculateHitterStats(adapted.value.filter((b) => b.batter_id === p.id)),
  }))
)
</script>

<template>
  <section class="mt-6 rounded-xl bg-app-card text-white overflow-x-auto p-4 shadow-lg">
    <h3 class="text-app-gold font-semibold tracking-wide mb-4 px-1">HITTERS — BASIC STATS</h3>
    <table class="w-full border-collapse text-sm">
      <thead>
        <tr class="text-left">
          <th class="py-2 px-3 text-app-muted uppercase font-medium sticky left-0 bg-app-card">Category</th>
          <th class="py-2 px-3 text-center text-app-gold uppercase font-semibold whitespace-nowrap">Team Total</th>
          <th
            v-for="p in playerStats"
            :key="p.id"
            class="py-2 px-3 text-center uppercase font-medium whitespace-nowrap"
          >
            {{ p.name }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="cat in CATEGORIES"
          :key="cat.key"
          class="border-t border-white/5 even:bg-white/[0.02]"
        >
          <td class="py-2 px-3 text-app-muted font-medium sticky left-0 bg-app-card">{{ cat.label }}</td>
          <td class="py-2 px-3 text-center text-app-gold font-semibold">{{ teamStats[cat.key] }}</td>
          <td v-for="p in playerStats" :key="p.id" class="py-2 px-3 text-center">
            {{ p.stats[cat.key] }}
          </td>
        </tr>
      </tbody>
    </table>
    <p v-if="!adapted.length" class="text-app-muted text-center py-6">No hitter data for this session.</p>
  </section>
</template>
