<script setup>
import { computed } from 'vue'
import Layout from '@/layout/Layout.vue'
import DevelopmentLeaderboard from '../components/DevelopmentLeaderboard.vue'
import PlayerDevelopmentBoard from '../components/PlayerDevelopmentBoard.vue'
import dummy from '../data/dummyPlayerDevelopmentData'
import { buildPlayerDevelopmentModel } from '../lib/playerDevelopmentScore'

const players = computed(() => {
  const base = buildPlayerDevelopmentModel(dummy.current, dummy.history, dummy.player.role)
  return [
    { name: 'Carter Jensen', developmentIndex: base.developmentIndex, status: base.status, trend: base.trend.status },
    { name: 'Mason Diaz', developmentIndex: Math.max(0, base.developmentIndex - 5), status: 'Steady', trend: 'steady' },
    { name: 'Ryan Brooks', developmentIndex: Math.min(100, base.developmentIndex + 4), status: 'Improving', trend: 'improving' },
  ].sort((a, b) => (b.developmentIndex ?? 0) - (a.developmentIndex ?? 0))
})
</script>

<template>
  <Layout>
    <div class="mx-auto w-full max-w-7xl space-y-4 px-4 py-6">
      <h1 class="text-2xl font-semibold text-white">Team Development Dashboard</h1>
      <p class="text-sm text-slate-300">Phase 1 uses dummy data. Existing scoring/session screens are unchanged.</p>
      <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <DevelopmentLeaderboard :players="players" />
        <PlayerDevelopmentBoard :players="players" />
      </div>
    </div>
  </Layout>
</template>
