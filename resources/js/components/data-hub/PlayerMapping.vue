<script setup>
import { computed } from 'vue'

const props = defineProps({
  players: { type: Array, required: true },
  mappings: { type: Object, required: true },
  teamPlayers: { type: Array, required: true },
})
const emit = defineEmits(['update:mapping'])
const playerId = player => String(player?.id_player ?? player?.id ?? player?.user_id ?? '')
const playerName = player => player?.name?.full ?? player?.full_name ?? [player?.first_name ?? player?.name?.first, player?.last_name ?? player?.name?.last].filter(Boolean).join(' ')
const duplicateIds = computed(() => {
  const ids = Object.values(props.mappings).filter(value => value && value !== '__skip__')
  return ids.filter((id, index) => ids.indexOf(id) !== index)
})
const status = player => {
  const value = props.mappings[player.external_name]
  if (value === '__skip__') return 'Skipped'
  if (value) return player.suggested_matches?.[0]?.player_id === value ? 'Matched' : 'Manual'
  return player.suggested_matches?.length ? 'Suggested' : 'Unresolved'
}
</script>

<template>
  <div class="mapping-list">
    <article v-for="player in players" :key="player.external_name">
      <div><span>TrackMan player</span><strong>{{ player.external_name }}</strong><small>{{ player.row_count }} rows · {{ player.data_types.join(', ') }}</small></div>
      <div><span>Best suggestion</span><strong>{{ player.suggested_matches?.[0]?.display_name || 'No suggestion' }}</strong><small v-if="player.suggested_matches?.[0]">{{ player.suggested_matches[0].confidence }}% · {{ player.suggested_matches[0].match_type }}</small></div>
      <label>
        <span>FMTRX player</span>
        <select :value="mappings[player.external_name] || ''" @change="emit('update:mapping', player.external_name, $event.target.value)">
          <option value="">Resolve player…</option>
          <option v-for="candidate in teamPlayers" :key="playerId(candidate)" :value="playerId(candidate)">{{ playerName(candidate) }}</option>
          <option value="__skip__">Skip Player</option>
        </select>
        <small :class="{ danger: duplicateIds.includes(mappings[player.external_name]) }">{{ status(player) }}<template v-if="duplicateIds.includes(mappings[player.external_name])"> · duplicate assignment</template></small>
      </label>
      <button type="button" disabled>Create New Player — disabled</button>
    </article>
  </div>
</template>

<style scoped>
.mapping-list{display:grid;gap:12px}.mapping-list article{display:grid;grid-template-columns:1.2fr 1fr 1.4fr auto;gap:16px;align-items:center;padding:16px;border:1px solid rgba(255,255,255,.1);border-radius:13px;background:rgba(5,12,29,.55)}article>div,label{display:flex;flex-direction:column;gap:4px}span{color:#94a3b8;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.1em}strong{color:#fff;font-size:14px}small{color:#94a3b8;font-size:10px}.danger{color:#ff8a9b}select{min-height:42px;padding:0 10px;border:1px solid rgba(255,255,255,.15);border-radius:9px;background:#0b142c;color:#fff}button{min-height:42px;padding:0 12px;border:1px solid rgba(255,255,255,.1);border-radius:9px;color:#64748b;background:rgba(255,255,255,.03)}@media(max-width:850px){.mapping-list article{grid-template-columns:1fr}}
</style>
