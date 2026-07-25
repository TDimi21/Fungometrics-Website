<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  players: { type: Array, required: true },
  mappings: { type: Object, required: true },
  teamPlayers: { type: Array, required: true },
  platformName: { type: String, default: 'Imported' },
  confirmedDuplicates: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:mapping', 'confirm:duplicate', 'refresh-roster'])
const filter = ref('needs_review')
const showMatched = ref(false)
const filters = [
  ['needs_review', 'Needs Review'], ['all', 'All'], ['not_importing', 'Not Importing'], ['connected', 'Connected'],
  ['pitcher', 'Pitchers'], ['batter', 'Batters'],
]
const mappedValue = player => props.mappings[player.source_key] || ''
const status = player => {
  const value = mappedValue(player)
  if (value) return 'connected'
  return Object.prototype.hasOwnProperty.call(props.mappings, player.source_key) ? 'not_importing' : 'needs_review'
}
const visible = computed(() => props.players.filter(player => {
  if (filter.value === 'all') return true
  if (['pitcher', 'batter'].includes(filter.value)) return player.roles.includes(filter.value)
  return status(player) === filter.value
}))
const summary = computed(() => ({
  found: props.players.length,
  connected: props.players.filter(player => status(player) === 'connected').length,
  suggested: props.players.filter(player => !mappedValue(player) && player.suggested_player).length,
  notImporting: props.players.filter(player => status(player) === 'not_importing').length,
  needsReview: props.players.filter(player => status(player) === 'needs_review').length,
  eligibleRows: props.players.filter(player => mappedValue(player)).reduce((sum, player) => sum + Number(player.row_count || 0), 0),
  excludedRows: props.players.filter(player => !mappedValue(player)).reduce((sum, player) => sum + Number(player.row_count || 0), 0),
}))
const duplicateGroups = computed(() => {
  const groups = {}
  props.players.forEach(player => {
    const target = mappedValue(player)
    if (target) (groups[target] ||= []).push(player)
  })
  return Object.entries(groups).filter(([, players]) => players.length > 1)
})
const duplicateFor = player => duplicateGroups.value.find(([target]) => target === mappedValue(player))
const rosterLabel = player => [
  player.name,
  player.graduation_year ? `Class of ${player.graduation_year}` : null,
  player.primary_position || null,
  [player.throw_side ? `${player.throw_side} throws` : null, player.hit_side ? `${player.hit_side} hits` : null].filter(Boolean).join(' / ') || null,
].filter(Boolean).join(' · ')
</script>

<template>
  <section class="player-mapping">
    <header class="summary">
      <div><span>Player mapping</span><h3>Connect {{ platformName }} players to your FMTRX roster</h3><p>Map each unique player once. Every matching source row will use the approved decision.</p></div>
      <div class="counts"><b>{{ summary.found }}<small>Players found</small></b><b>{{ summary.connected }}<small>Connected</small></b><b>{{ summary.suggested }}<small>Suggested</small></b><b>{{ summary.notImporting }}<small>Not Importing</small></b><b>{{ summary.needsReview }}<small>Needs Review</small></b><b>{{ summary.eligibleRows }}<small>Eligible rows</small></b><b>{{ summary.excludedRows }}<small>Excluded rows</small></b></div>
    </header>
    <nav class="filters"><button v-for="[key,label] in filters" :key="key" :class="{active:filter===key}" @click="filter=key">{{ label }}</button><button class="refresh" @click="emit('refresh-roster')">↻ Refresh roster</button></nav>
    <div v-if="duplicateGroups.length" class="duplicate-panel">
      <article v-for="[target, identities] in duplicateGroups" :key="target">
        <strong>Multiple imported identities map to {{ teamPlayers.find(player=>player.id===target)?.name || 'one roster player' }}</strong>
        <span>{{ identities.map(player=>player.source_name).join(' · ') }}</span>
        <label><input type="checkbox" :checked="confirmedDuplicates.includes(target)" @change="emit('confirm:duplicate',target,$event.target.checked)"> Confirm these are the same person</label>
      </article>
    </div>
    <div class="cards">
      <article v-for="player in visible" :key="player.source_key" :class="['player-card',status(player)]">
        <div class="identity"><span>{{ platformName }} player</span><h4>{{ player.source_name }}</h4><small v-if="player.external_player_id">ID: {{ player.external_player_id }}</small><div class="tags"><b v-for="role in player.roles" :key="role">{{ role }}</b></div><p>{{ player.row_count }} rows<span v-if="player.tracked_batted_ball_count != null"> · {{ player.tracked_batted_ball_count }} tracked batted balls</span><span v-if="player.batter_row_count && player.tracked_batted_ball_count == null"> · {{ player.batter_row_count }} batting</span><span v-if="player.pitcher_row_count"> · {{ player.pitcher_row_count }} pitching</span></p><p v-if="player.source_team_names?.length">{{ player.source_team_names.join(', ') }}</p></div>
        <div class="suggestion"><span>{{ player.identity_missing ? 'Session assignment' : 'Best suggestion' }}</span><strong>{{ player.suggested_player?.display_name || (player.identity_missing ? 'Player name not found' : 'No roster suggestion') }}</strong><small v-if="player.suggested_player">{{ player.suggested_player.confidence }}% · {{ player.suggested_player.resolution_source.replaceAll('_',' ') }}</small><small v-else>{{ player.assignment_help || 'Player not on roster. Only connect players this team manages.' }}</small></div>
        <label class="selector"><span>FMTRX roster player</span><select :value="mappedValue(player)" @change="emit('update:mapping',player.source_key,$event.target.value)"><option value="">— Not Importing —</option><option v-for="candidate in teamPlayers" :key="candidate.id" :value="candidate.id">{{ rosterLabel(candidate) }}</option></select><small>{{ status(player).replace('_',' ') }}<template v-if="duplicateFor(player)"> · duplicate target</template></small><div><button v-if="status(player)!=='not_importing'" @click="emit('update:mapping',player.source_key,'')">Set to Not Importing</button><button v-if="Object.prototype.hasOwnProperty.call(mappings,player.source_key)" @click="emit('update:mapping',player.source_key,null)">Clear Selection</button></div></label>
      </article>
      <p v-if="!visible.length" class="empty">No players match this filter.</p>
    </div>
    <p v-if="!summary.connected" class="approval-help">Connect at least one imported player to an FMTRX roster player before continuing.</p>
    <button v-if="summary.connected && filter==='not_importing'" class="matched-toggle" @click="showMatched=!showMatched;filter=showMatched?'connected':'not_importing'">{{ showMatched ? 'Return to Not Importing' : `Review ${summary.connected} Connected Players` }}</button>
  </section>
</template>

<style scoped>
.player-mapping{display:grid;gap:14px}.summary{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;padding:18px;border:1px solid rgba(255,255,255,.1);border-radius:14px;background:rgba(5,12,29,.55)}span{color:#94a3b8;font-size:9px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.summary h3{margin:4px 0;color:#fff}.summary p,.identity p{color:#94a3b8;font-size:11px}.counts{display:flex;gap:18px}.counts b{color:#fff;font-size:22px}.counts small{display:block;color:#94a3b8;font-size:8px;text-transform:uppercase}.filters{display:flex;flex-wrap:wrap;gap:7px}.filters button,.matched-toggle,.selector button{padding:9px 12px;border:1px solid rgba(255,255,255,.1);border-radius:8px;background:rgba(255,255,255,.04);color:#cbd5e1;font-size:9px;font-weight:900;text-transform:uppercase}.filters .active{border-color:#ff2b4a;background:#ff2b4a;color:#fff}.filters .refresh{margin-left:auto}.duplicate-panel{display:grid;gap:8px}.duplicate-panel article{padding:13px;border:1px solid rgba(255,190,64,.35);border-radius:10px;background:rgba(255,190,64,.08);color:#ffe3ac}.duplicate-panel span,.duplicate-panel label{display:block;margin-top:5px;color:#ffe3ac}.cards{display:grid;gap:10px}.player-card{display:grid;grid-template-columns:1fr 1fr 1.5fr;gap:18px;padding:17px;border:1px solid rgba(255,255,255,.1);border-radius:13px;background:rgba(5,12,29,.55)}.player-card.not_importing{border-left:3px solid #ffb43b}.player-card.connected{border-left:3px solid #3bd39a}.identity,.suggestion,.selector{display:flex;flex-direction:column;gap:5px}.identity h4,.suggestion strong{color:#fff;font-size:15px}.identity small,.suggestion small,.selector small{color:#94a3b8;font-size:10px}.tags{display:flex;gap:5px}.tags b{padding:3px 6px;border-radius:5px;background:rgba(64,160,255,.12);color:#72b9ff;font-size:8px;text-transform:uppercase}.selector select{min-height:44px;padding:0 10px;border:1px solid rgba(255,255,255,.15);border-radius:9px;background:#0b142c;color:#fff}.selector>div{display:flex;gap:6px}.empty{padding:22px;color:#94a3b8;text-align:center}.matched-toggle{justify-self:start}@media(max-width:850px){.summary{align-items:flex-start;flex-direction:column}.counts{width:100%;justify-content:space-between}.player-card{grid-template-columns:1fr}}
.counts{flex-wrap:wrap}.player-card.needs_review{border-left:3px solid #8f7cff}.approval-help{color:#ff8798;font-size:11px}
</style>
