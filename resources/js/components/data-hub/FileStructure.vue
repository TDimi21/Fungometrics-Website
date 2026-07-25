<script setup>
import { computed, reactive } from 'vue'

const props = defineProps({
  inspection: { type: Object, required: true },
  applying: { type: Boolean, default: false },
})
const emit = defineEmits(['apply'])
const model = props.inspection.normalized_inspection
const form = reactive({
  layout: model.detected_layout,
  header_row: model.header_row,
  first_data_row: model.first_data_row,
  player_column: model.player_column || '',
  metric_column: model.metric_column || '',
  player_id_column: model.player_id_column || '',
  date_column: model.date_column || '',
  ignored_rows_text: (model.ignored_rows || []).join(', '),
  worksheet_index: model.selected_worksheet_index || 0,
  ignored_rows: [...(model.ignored_rows || [])],
  ignored_columns: [...(model.ignored_columns || [])],
})
const layouts = [
  ['players_in_rows', 'Players are listed in rows'],
  ['players_in_columns', 'Players are listed across columns'],
  ['events_in_rows', 'Each row is an event'],
  ['worksheet_per_player', 'One worksheet per player'],
  ['single_player_session', 'One player owns the entire file'],
  ['unknown', 'Use FMTRX detected layout'],
]
const headers = computed(() => model.metric_header_candidates || [])
const letters = index => {
  let value = index + 1
  let label = ''
  while (value) {
    value--
    label = String.fromCharCode(65 + value % 26) + label
    value = Math.floor(value / 26)
  }
  return label
}
const apply = () => emit('apply', {
  ...form,
  ignored_rows: form.ignored_rows_text.split(',').map(value => Number(value.trim())).filter(Number.isInteger),
})
</script>

<template>
  <section class="structure">
    <header><span>File structure confirmation</span><h3>How is this spreadsheet organized?</h3><p>FMTRX detected <strong>{{ model.detected_layout.replaceAll('_',' ') }}</strong> at {{ Math.round(model.layout_confidence * 100) }}% confidence. Confirm or correct it before Player Mapping.</p></header>
    <div class="layout-options"><label v-for="[key,label] in layouts" :key="key"><input v-model="form.layout" type="radio" :value="key"> {{ label }}</label></div>
    <div class="controls">
      <label v-if="model.worksheets.length > 1">Worksheet<select v-model.number="form.worksheet_index"><option v-for="(sheet,index) in model.worksheets" :key="sheet.name" :value="index">{{ sheet.name }} · {{ sheet.row_count }} rows</option></select></label>
      <label>Header row<input v-model.number="form.header_row" type="number" min="1" :max="model.worksheets[form.worksheet_index]?.row_count || 1"></label>
      <label>First data row<input v-model.number="form.first_data_row" type="number" :min="form.header_row + 1" :max="(model.worksheets[form.worksheet_index]?.row_count || 1) + 1"></label>
      <label>Player-name column<select v-model="form.player_column"><option value="">No player column</option><option v-for="header in headers" :key="header">{{ header }}</option></select></label>
      <label>Metric-name column<select v-model="form.metric_column"><option value="">Not selected</option><option v-for="header in headers" :key="header">{{ header }}</option></select></label>
      <label>Player-ID column<select v-model="form.player_id_column"><option value="">Not selected</option><option v-for="header in headers" :key="header">{{ header }}</option></select></label>
      <label>Date column<select v-model="form.date_column"><option value="">Not selected</option><option v-for="header in headers" :key="header">{{ header }}</option></select></label>
      <label>Rows to ignore<input v-model="form.ignored_rows_text" type="text" placeholder="Example: 1, 2, 9"></label>
    </div>
    <fieldset><legend>Columns to ignore</legend><label v-for="header in headers" :key="header"><input v-model="form.ignored_columns" type="checkbox" :value="header"> {{ header }}</label></fieldset>
    <div class="preview"><table><thead><tr><th>#</th><th v-for="(header,index) in headers" :key="header">{{ letters(index) }} · {{ header }}</th></tr></thead><tbody><tr v-for="row in model.preview_rows" :key="row.row_number"><th>{{ row.row_number }}</th><td v-for="header in headers" :key="header" :class="{player:header===form.player_column}">{{ row.values[header] }}</td></tr></tbody></table></div>
    <p v-if="inspection.warnings.length" class="warning">{{ inspection.warnings.join(' ') }}</p>
    <button type="button" :disabled="applying" @click="apply">{{ applying ? 'Refreshing preview…' : 'Confirm Structure & Refresh Preview' }}</button>
  </section>
</template>

<style scoped>
.structure{display:grid;gap:16px}.structure header{padding:18px;border:1px solid rgba(255,255,255,.1);border-radius:14px;background:rgba(5,12,29,.55)}span{color:#ff4964;font-size:9px;font-weight:900;text-transform:uppercase}.structure h3{margin:5px 0;color:#fff}.structure p{color:#94a3b8}.layout-options{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}.layout-options label,.controls label{padding:12px;border:1px solid rgba(255,255,255,.1);border-radius:9px;color:#d8e1ef}.controls{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.controls label{display:grid;gap:7px;font-size:10px;text-transform:uppercase}.controls select,.controls input{min-height:40px;padding:0 9px;border:1px solid rgba(255,255,255,.15);border-radius:7px;background:#0b142c;color:#fff}.preview{max-height:360px;overflow:auto;border:1px solid rgba(255,255,255,.1);border-radius:11px}table{min-width:100%;border-collapse:collapse;color:#d8e1ef;font-size:10px}th,td{padding:9px;border:1px solid rgba(255,255,255,.07);white-space:nowrap}thead{position:sticky;top:0;background:#101a35}.player{background:rgba(59,211,154,.12)}button{justify-self:start;padding:12px 16px;border:0;border-radius:9px;background:#ff2b4a;color:#fff;font-weight:900}.warning{color:#ffd38a}@media(max-width:700px){.layout-options,.controls{grid-template-columns:1fr}}
fieldset{display:flex;flex-wrap:wrap;gap:8px;padding:12px;border:1px solid rgba(255,255,255,.1);border-radius:9px;color:#d8e1ef}fieldset label{font-size:10px}
</style>
