<script setup>
import { computed, ref } from 'vue'
import CageFieldStats from './CageFieldStats.vue'
const props = defineProps({ rows: { type: Array, default: () => [] }, players: { type: Array, default: () => [] } })
const tab = ref('ball')
const tabs = { ball: 'Ball by ball', contact: 'Contact', trajectory: 'Trajectory', velocity: 'Velocity' }
const balls = computed(() => props.rows.map(row => {
  const player = props.players.find(p => p.id === row.user_id)
  return { ...row, profile: row.profile || { first_name: player?.name?.full || [player?.name?.first,player?.name?.last].filter(Boolean).join(' ') || 'Player', last_name: '' } }
}).sort((a,b) => Number(b.sort || 0)-Number(a.sort || 0)))
</script>
<template><div><nav aria-label="Cage statistics views"><button v-for="(label,key) in tabs" :key="key" :aria-pressed="tab === key" @click="tab = key">{{ label }}</button></nav><div v-if="tab === 'ball'" class="table-scroll"><table><caption class="sr-only">Current cage session, ball by ball</caption><thead><tr><th>Ball</th><th>Hitter</th><th>Exit velocity</th><th>Launch</th><th>Spray</th><th>Estimated distance</th><th>Ground ball</th></tr></thead><tbody><tr v-for="(ball,index) in balls" :key="ball.id"><td>{{ ball.sort ?? balls.length-index }}</td><td>{{ ball.profile.first_name }} {{ ball.profile.last_name }}</td><td>{{ ball.launch_angle_velocity }} mph</td><td>{{ ball.launch_angle }}°</td><td>{{ ball.spray_angle }}°</td><td>{{ Number(ball.distance_travel || 0).toFixed(0) }} ft</td><td>{{ Number(ball.ground_ball) === 1 ? 'Yes' : 'No' }}</td></tr><tr v-if="!balls.length"><td colspan="7">No hits saved in this session yet.</td></tr></tbody></table></div><CageFieldStats v-else :key="tab" :mode="tab" :balls="balls" team-name="Current session" /></div></template>
<style scoped>nav{display:flex;gap:8px;flex-wrap:wrap;margin:15px 0}button{padding:12px 18px;border-radius:12px;background:#303645;color:white;min-height:44px}button[aria-pressed=true]{background:#ff2451}.table-scroll{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #778399;white-space:nowrap}</style>
