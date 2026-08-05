<script setup>
import { ref } from 'vue'

defineProps({
  player: { type: Object, default: () => ({}) },
  metrics: { type: Array, default: () => [] },
})

const imageFailed = ref(false)
const trendGlyph = (trend) => ['up', 'improving'].includes(trend) ? '↑' : ['down', 'declining'].includes(trend) ? '↓' : '—'
</script>

<template>
  <section class="pd-card identity-card" data-testid="player-identity">
    <div class="identity-hero" :style="player.picture && !imageFailed ? { backgroundImage: `linear-gradient(90deg, rgba(4,15,26,.35), rgba(4,15,26,.96)), url(${player.picture})` } : {}">
      <div class="player-photo" aria-label="Player photo">
        <img v-if="player.picture && !imageFailed" :src="player.picture" :alt="`${player.name} profile`" @error="imageFailed = true">
        <svg v-else data-testid="player-photo-fallback" viewBox="0 0 80 80" role="img" aria-label="Player silhouette">
          <circle cx="40" cy="27" r="15" fill="currentColor" />
          <path d="M12 74c2-20 13-31 28-31s26 11 28 31" fill="currentColor" />
        </svg>
      </div>
      <div>
        <p class="eyebrow">Player Development</p>
        <h1>{{ player.name }} <span v-if="player.jersey">#{{ player.jersey }}</span></h1>
        <p class="level">{{ player.level }} · {{ player.position || player.positions?.join(', ') || 'Position Needs Data' }}</p>
      </div>
    </div>

    <dl class="bio-grid">
      <div><dt>Throws</dt><dd>{{ player.throws || 'Needs Data' }}</dd></div>
      <div><dt>Bats</dt><dd>{{ player.bats || 'Needs Data' }}</dd></div>
      <div><dt>Height</dt><dd>{{ player.height || 'Needs Data' }}</dd></div>
      <div><dt>Age</dt><dd>{{ player.age ?? 'Needs Data' }}</dd></div>
      <div><dt>Team</dt><dd>{{ player.team || 'Needs Data' }}</dd></div>
      <div><dt>Role</dt><dd>{{ player.role || 'Needs Data' }}</dd></div>
    </dl>

    <div class="quick-grid" data-testid="quick-metrics">
      <article v-for="metric in metrics" :key="metric.key" class="quick-metric">
        <p>{{ metric.label }}</p>
        <strong>{{ metric.display_value || 'Needs Data' }}</strong>
        <span v-if="metric.percentile !== null && metric.percentile !== undefined">{{ Math.round(metric.percentile) }}th percentile</span>
        <span v-else-if="metric.available" class="trend">{{ trendGlyph(metric.trend) }} Trend</span>
        <span v-else>Needs Data</span>
      </article>
    </div>
  </section>
</template>

<style scoped>
.pd-card{background:linear-gradient(145deg,#071522,#091c2b);border:1px solid #254154;border-radius:12px;overflow:hidden;color:#f8fafc;box-shadow:0 10px 30px #02081255}.identity-hero{min-height:122px;padding:18px;display:flex;align-items:center;gap:15px;background-size:cover;background-position:center}.player-photo{width:76px;height:76px;flex:0 0 76px;border-radius:50%;overflow:hidden;border:3px solid #ef3340;background:#d7dde2;color:#1b2730;display:grid;place-items:center}.player-photo img{width:100%;height:100%;object-fit:cover}.player-photo svg{width:70px;height:70px}.eyebrow{font-size:9px;letter-spacing:.18em;text-transform:uppercase;color:#7dd3fc}.identity-hero h1{font-size:22px;font-weight:900;text-transform:uppercase;line-height:1.05}.identity-hero h1 span{color:#ef3340}.level{margin-top:4px;font-size:11px;text-transform:uppercase;color:#a9b8c5}.bio-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));padding:12px 16px;border-top:1px solid #20394b;gap:8px 14px}.bio-grid div{display:flex;justify-content:space-between;gap:8px;font-size:11px}.bio-grid dt{color:#6f8798;text-transform:uppercase}.bio-grid dd{text-align:right;color:#e8eef3;font-weight:700}.quick-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;padding:0 12px 12px}.quick-metric{min-width:0;border:1px solid #156486;border-radius:7px;padding:9px;background:#061828}.quick-metric p{font-size:9px;text-transform:uppercase;color:#89a2b3}.quick-metric strong{display:block;font-size:13px;overflow-wrap:anywhere}.quick-metric span{display:block;margin-top:2px;color:#4dd4bd;font-size:9px}.quick-metric span:not(.trend){color:#7790a2}
</style>
