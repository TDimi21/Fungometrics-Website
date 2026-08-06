<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  inspection: { type: Object, required: true },
  applying: { type: Boolean, default: false },
})
const emit = defineEmits(['preview', 'apply'])
const model = props.inspection.normalized_inspection
const worksheetIndex = ref(model.selected_worksheet_index || 0)
const orientation = ref(
  ['players_in_rows', 'events_in_rows'].includes(model.detected_layout) ? 'column'
    : model.detected_layout === 'players_in_columns' ? 'row'
    : model.detected_layout === 'single_player_session' ? 'single' : ''
)
const playerColumn = ref(model.player_column || '')
const metricColumn = ref(orientation.value === 'row' ? (model.metric_column || '') : '')
const headerRow = ref(model.header_row)
const firstDataRow = ref(model.first_data_row)
const showAdvanced = ref(false)

const headers = computed(() => model.metric_header_candidates || [])
const orientations = [
  ['column', 'Player names run down a column', 'One row per event — every row lists which player it belongs to.'],
  ['row', 'Each player has their own column', 'Player names are the column titles across the top; each column holds one player’s numbers.'],
  ['single', 'This whole file is one player', 'No player column at all — every row belongs to a single roster player you’ll pick next.'],
]
const chooseOrientation = key => {
  orientation.value = key
  playerColumn.value = ''
  metricColumn.value = ''
}
const structure = () => ({
  worksheet_index: worksheetIndex.value,
  header_row: headerRow.value,
  first_data_row: firstDataRow.value,
  layout: orientation.value === 'column' ? 'players_in_rows' : orientation.value === 'row' ? 'players_in_columns' : 'single_player_session',
  ...(orientation.value === 'column' ? { player_column: playerColumn.value } : {}),
  ...(orientation.value === 'row' ? { metric_column: metricColumn.value } : {}),
})
const ready = computed(() => {
  if (orientation.value === 'column') return Boolean(playerColumn.value)
  if (orientation.value === 'row') return Boolean(metricColumn.value)
  return orientation.value === 'single'
})
const visibleWarnings = computed(() => (props.inspection.warnings || []).filter(warning => !warning.includes('Confirm the file structure')))
watch([orientation, playerColumn, metricColumn, headerRow, firstDataRow, worksheetIndex], () => {
  if (ready.value) emit('preview', structure())
}, { immediate: true })
const confirm = () => emit('apply', structure())
</script>

<template>
  <section class="structure">
    <header><span>Player identification</span><h3>Where are the player names in this file?</h3><p>FMTRX read the header row below. Pick how this spreadsheet is organized, then click the column that holds player names.</p></header>

    <label v-if="model.worksheets.length > 1" class="worksheet-picker">Worksheet<select v-model.number="worksheetIndex"><option v-for="(sheet,index) in model.worksheets" :key="sheet.name" :value="index">{{ sheet.name }} · {{ sheet.row_count }} rows</option></select></label>

    <div class="orientation-options">
      <button v-for="[key,label,help] in orientations" :key="key" type="button" :class="{active: orientation===key}" @click="chooseOrientation(key)">
        <strong>{{ label }}</strong><span>{{ help }}</span>
      </button>
    </div>

    <div v-if="orientation==='column' || orientation==='row'" class="header-picker">
      <p>{{ orientation === 'column' ? 'Click the column that lists player names:' : 'Click the column that lists the measurement names (every other column will be treated as a player):' }}</p>
      <div class="header-chips">
        <button v-for="header in headers" :key="header" type="button" :class="{active: (orientation==='column'?playerColumn:metricColumn)===header}" @click="orientation==='column' ? playerColumn=header : metricColumn=header">{{ header }}</button>
      </div>
    </div>

    <div v-if="applying" class="scanning">Scanning for players…</div>
    <div v-else-if="ready" class="players-found">
      <strong>{{ inspection.counts.players_found }} player{{ inspection.counts.players_found === 1 ? '' : 's' }} found</strong>
      <ul v-if="inspection.players.length"><li v-for="player in inspection.players" :key="player.source_key">{{ player.source_name }}<span>{{ player.row_count }} rows</span></li></ul>
      <p v-else class="empty">No player names were found there. Try a different column or orientation.</p>
    </div>

    <button type="button" class="advanced-toggle" @click="showAdvanced = !showAdvanced">{{ showAdvanced ? 'Hide advanced options' : 'This doesn’t look right — adjust header row' }}</button>
    <div v-if="showAdvanced" class="advanced">
      <label>Header row<input v-model.number="headerRow" type="number" min="1"></label>
      <label>First data row<input v-model.number="firstDataRow" type="number" :min="headerRow+1"></label>
    </div>

    <p v-if="visibleWarnings.length" class="warning">{{ visibleWarnings.join(' ') }}</p>
    <button type="button" class="confirm-button" :disabled="!ready || applying" @click="confirm">{{ applying ? 'Scanning…' : 'Looks good — Continue to Player Mapping' }}</button>
  </section>
</template>

<style scoped>
.structure{display:grid;gap:16px}.structure header{padding:18px;border:1px solid rgba(255,255,255,.1);border-radius:14px;background:rgba(5,12,29,.55)}.structure header span{color:#ff4964;font-size:9px;font-weight:900;text-transform:uppercase}.structure h3{margin:5px 0;color:#fff}.structure p{color:#94a3b8}
.worksheet-picker{display:grid;gap:7px;max-width:280px;font-size:10px;color:#94a3b8;text-transform:uppercase}.worksheet-picker select{min-height:40px;padding:0 9px;border:1px solid rgba(255,255,255,.15);border-radius:7px;background:#0b142c;color:#fff}
.orientation-options{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.orientation-options button{display:flex;flex-direction:column;gap:6px;padding:14px;border:1px solid rgba(255,255,255,.12);border-radius:12px;background:rgba(255,255,255,.03);color:#d8e1ef;text-align:left}.orientation-options button strong{color:#fff;font-size:13px}.orientation-options button span{color:#94a3b8;font-size:11px}.orientation-options button.active{border-color:#ff2b4a;background:rgba(255,43,74,.1)}
.header-picker p{margin-bottom:8px;color:#d8e1ef;font-size:12px}.header-chips{display:flex;flex-wrap:wrap;gap:7px}.header-chips button{padding:9px 13px;border:1px solid rgba(255,255,255,.14);border-radius:8px;background:rgba(255,255,255,.04);color:#cbd5e1;font-size:11px;font-weight:700}.header-chips button.active{border-color:#3bd39a;background:rgba(59,211,154,.14);color:#7cf0c4}
.scanning{padding:13px;border:1px solid rgba(255,255,255,.1);border-radius:11px;color:#94a3b8;font-size:12px}
.players-found{padding:14px;border:1px solid rgba(59,211,154,.25);border-radius:12px;background:rgba(59,211,154,.06)}.players-found strong{color:#7cf0c4;font-size:14px}.players-found ul{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px;margin-top:10px;max-height:220px;overflow-y:auto}.players-found li{display:flex;justify-content:space-between;gap:8px;padding:8px 10px;border:1px solid rgba(255,255,255,.08);border-radius:8px;background:rgba(5,12,29,.5);color:#d8e1ef;font-size:11px}.players-found li span{color:#94a3b8}.players-found .empty{margin-top:8px;color:#ffb43b;font-size:12px}
.advanced-toggle{justify-self:start;border:0;background:transparent;color:#8f9bb8;font-size:10px;text-decoration:underline;text-transform:uppercase}.advanced{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding:12px;border:1px solid rgba(255,255,255,.1);border-radius:10px}.advanced label{display:grid;gap:7px;color:#94a3b8;font-size:10px;text-transform:uppercase}.advanced input{min-height:40px;padding:0 9px;border:1px solid rgba(255,255,255,.15);border-radius:7px;background:#0b142c;color:#fff}
.warning{color:#ffd38a;font-size:12px}
.confirm-button{justify-self:start;padding:13px 18px;border:0;border-radius:9px;background:#ff2b4a;color:#fff;font-weight:900;text-transform:uppercase;font-size:11px}.confirm-button:disabled{opacity:.4}
@media(max-width:700px){.orientation-options{grid-template-columns:1fr}.advanced{grid-template-columns:1fr}}
</style>
