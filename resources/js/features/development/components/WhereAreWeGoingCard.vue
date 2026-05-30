<script setup>
defineProps({ trend: { type: Object, default: () => ({}) } })

const toText = (obj, key) => {
  const row = obj?.changes?.[key]
  if (!row) return '—'
  const d = row.delta
  if (d === null || d === undefined) return '—'
  const sign = d > 0 ? '+' : ''
  return `${sign}${d.toFixed(1)}`
}
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Where Are We Going?</h3>
    <p class="mt-1 text-sm text-slate-300">Last 30 days trend: <strong>{{ trend.status || '—' }}</strong></p>
    <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-slate-300 md:grid-cols-3">
      <div>EV change: <strong>{{ toText(trend, 'avg_exit_velocity') }}</strong></div>
      <div>Pitch velo change: <strong>{{ toText(trend, 'avg_pitch_velocity') }}</strong></div>
      <div>Hard contact change: <strong>{{ toText(trend, 'hard_contact_percentage') }}</strong></div>
      <div>Command change: <strong>{{ toText(trend, 'command_score') }}</strong></div>
      <div>Strength change: <strong>{{ toText(trend, 'rotational_power_score') }}</strong></div>
      <div>Sleep change: <strong>{{ toText(trend, 'sleep_hours') }}</strong></div>
    </div>
  </div>
</template>
