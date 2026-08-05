<script setup>
import { computed } from 'vue'
import { compatibilityForConcept } from '@/utils/dataHubConceptCompatibility.js'

const props = defineProps({
  inspection: { type: Object, required: true },
  teamName: { type: String, required: true },
  destination: { type: String, required: true },
  mappings: { type: Object, required: true },
  columnEntries: { type: Object, required: true },
  teamPlayers: { type: Array, default: () => [] },
  concepts: { type: Array, default: () => [] },
  domains: { type: Array, default: () => [] },
  confirmedWarningColumns: { type: Array, default: () => [] },
  confirmedDuplicateTargets: { type: Array, default: () => [] },
  confirmedDuplicateConcepts: { type: Array, default: () => [] },
})

const concept = id => props.concepts.find(item => item.id === id)
const domain = id => props.domains.find(item => item.id === id)
const rosterPlayer = id => props.teamPlayers.find(item => String(item.id) === String(id))
const rosterName = id => {
  const player = rosterPlayer(id)
  return player?.display_name || player?.name || [player?.first_name, player?.last_name].filter(Boolean).join(' ') || 'Connected FMTRX player'
}
const sourceColumn = name => props.inspection.source_columns?.find(item => item.source_column_name === name) || {}
const connectedPlayers = computed(() => props.inspection.players.filter(player => props.mappings[player.source_key]))
const notImportingPlayers = computed(() => props.inspection.players.filter(player => !props.mappings[player.source_key]))
const eligibleRows = computed(() => connectedPlayers.value.reduce((total, player) => total + Number(player.row_count || 0), 0))
const excludedRows = computed(() => notImportingPlayers.value.reduce((total, player) => total + Number(player.row_count || 0), 0))
const entries = computed(() => Object.values(props.columnEntries))
const connectedColumns = computed(() => entries.value.filter(entry => entry.action === 'map' && entry.baseball_concept_id))
const unknownColumns = computed(() => entries.value.filter(entry => ['store_unknown', 'submit_new'].includes(entry.action)))
const ignoredColumns = computed(() => entries.value.filter(entry => entry.action === 'ignore' || (entry.action === 'map' && !entry.baseball_concept_id)))
const unavailableColumns = computed(() => props.inspection.source_columns?.filter(column => column.default_not_importing) || [])
const controlledTransformations = computed(() => entries.value.flatMap(entry =>
  entry.metadata?.controlled_value_transformations || sourceColumn(entry.source_column_name).controlled_value_transformations || []
))
const layout = computed(() =>
  props.inspection.normalized_inspection?.detected_layout
  || props.inspection.detected_format?.display_type
  || props.inspection.detected_format?.data_type
  || 'Platform-defined'
)
const sourceLanguage = computed(() => props.inspection.detected_format?.provider || props.inspection.platform)
const playerResolution = player => player.suggested_matches?.find(match =>
  String(match.player_id) === String(props.mappings[player.source_key])
)
const playerStatus = player => {
  if (!props.mappings[player.source_key]) return 'Not Importing'
  const resolution = playerResolution(player)
  if (resolution?.resolution_source === 'remembered_external_id' || resolution?.resolution_source === 'remembered_name') return 'Remembered mapping'
  if (resolution?.auto_select) return 'Approved automatic match'
  return 'Manual coach approval'
}
const compatibility = entry => {
  const selected = concept(entry.baseball_concept_id)
  return compatibilityForConcept(props.destination, selected, props.domains)
}
const warnings = computed(() => [
  ...(props.inspection.warnings || []),
  ...props.inspection.source_columns.flatMap(column => column.warnings || []),
])
</script>

<template>
  <section class="translation-hero">
    <span>Translation summary</span>
    <h2>{{ sourceLanguage }} Language <b>→</b> FMTRX Baseball Language</h2>
    <div class="translation-flow">
      <div><small>Source rows</small><strong>{{ inspection.counts.total_rows }}</strong></div>
      <b>→</b><div><small>Connected players</small><strong>{{ connectedPlayers.length }}</strong></div>
      <b>→</b><div><small>Baseball concepts</small><strong>{{ connectedColumns.length }}</strong></div>
      <b>→</b><div><small>Eligible rows</small><strong>{{ eligibleRows }}</strong></div>
    </div>
  </section>

  <details open class="review-section">
    <summary><span>Source Summary</span><strong>What FMTRX inspected</strong></summary>
    <div class="summary-grid">
      <div><span>Platform</span><strong>{{ sourceLanguage }}</strong></div>
      <div><span>File</span><strong>{{ inspection.file.name }}</strong><small>{{ inspection.file.extension?.toUpperCase() }}</small></div>
      <div><span>Team</span><strong>{{ teamName }}</strong></div>
      <div><span>Destination</span><strong>{{ destination }}</strong></div>
      <div><span>Detected layout</span><strong>{{ String(layout).replaceAll('_',' ') }}</strong></div>
      <div><span>Total / eligible / excluded rows</span><strong>{{ inspection.counts.total_rows }} / {{ eligibleRows }} / {{ excludedRows }}</strong></div>
      <div><span>Usable / invalid rows</span><strong>{{ inspection.counts.usable_rows }} / {{ inspection.counts.invalid_rows }}</strong></div>
      <div><span>Total / mapped columns</span><strong>{{ inspection.source_columns.length }} / {{ connectedColumns.length }}</strong></div>
      <div><span>Not Importing / unknown</span><strong>{{ ignoredColumns.length }} / {{ unknownColumns.length }}</strong></div>
      <div><span>Connected / Not Importing</span><strong>{{ connectedPlayers.length }} / {{ notImportingPlayers.length }}</strong></div>
      <div v-if="inspection.workbook"><span>Workbook worksheet</span><strong>{{ inspection.workbook.selected_worksheet }}</strong><small>Header row {{ inspection.workbook.header_row }}</small></div>
      <div v-if="inspection.report"><span>Source report</span><strong>{{ inspection.report.header_row ? `Header row ${inspection.report.header_row}` : 'Detected' }}</strong><small>{{ inspection.detected_format.display_type }}</small></div>
    </div>
    <div v-if="inspection.report?.metadata_summary" class="source-metadata">
      <span>Source metadata summary</span>
      <p v-for="(value,key) in inspection.report.metadata_summary" :key="key">{{ key }}: {{ value }}</p>
    </div>
  </details>

  <details open class="review-section">
    <summary><span>Player Translation</span><strong>{{ connectedPlayers.length }} connected · {{ notImportingPlayers.length }} Not Importing</strong></summary>
    <div class="translation-list">
      <article v-for="player in inspection.players" :key="player.source_key" :class="{ excluded: !mappings[player.source_key] }">
        <div><span>Source player</span><strong>{{ player.source_name }}</strong><small>{{ player.external_player_id || 'No external ID' }}</small></div>
        <b>→</b>
        <div><span>FMTRX player</span><strong>{{ mappings[player.source_key] ? rosterName(mappings[player.source_key]) : 'Not Importing' }}</strong><small>{{ player.roles?.join(', ') || 'Player' }} · {{ player.row_count }} rows</small></div>
        <div><span>Resolution</span><strong>{{ playerStatus(player) }}</strong><small>{{ playerResolution(player)?.resolution_source || 'coach_decision' }} · {{ playerResolution(player)?.confidence ?? 100 }}%</small></div>
      </article>
    </div>
  </details>

  <details open class="review-section">
    <summary><span>Concept Translation</span><strong>{{ connectedColumns.length }} connected · {{ ignoredColumns.length }} Not Importing · {{ unknownColumns.length }} unknown</strong></summary>
    <div class="concept-table">
      <article v-for="entry in entries" :key="entry.source_column_name" :class="{ excluded: entry.action !== 'map' }">
        <div><span>Source column</span><strong>{{ entry.source_column_name }}</strong><small>{{ entry.normalized_source_column }} · {{ sourceLanguage }}</small></div>
        <b>→</b>
        <div v-if="entry.action === 'map' && concept(entry.baseball_concept_id)">
          <span>FMTRX concept</span><strong>{{ concept(entry.baseball_concept_id).display_name }}</strong>
          <code>{{ concept(entry.baseball_concept_id).canonical_key }}</code>
        </div>
        <div v-else><span>Decision</span><strong>{{ entry.action === 'store_unknown' ? 'Unknown — retained for review' : entry.action === 'submit_new' ? 'New concept submitted' : 'Not Importing' }}</strong></div>
        <div v-if="entry.action === 'map' && concept(entry.baseball_concept_id)" class="concept-details">
          <span>{{ domain(concept(entry.baseball_concept_id).domain_id)?.display_name || domain(concept(entry.baseball_concept_id).domain_id)?.name || 'Baseball' }}</span>
          <p>{{ concept(entry.baseball_concept_id).definition }}</p>
          <small>Source unit: {{ entry.source_unit_key || 'Not declared' }} · Canonical unit: {{ concept(entry.baseball_concept_id).canonical_unit_key || 'None' }}</small>
          <small>Transformation: {{ entry.transformation_key || 'None' }} · Relationship: {{ entry.relationship_type || 'Approved source mapping' }}</small>
          <small>{{ entry.resolution_source || 'manual' }} · {{ entry.confidence || 0 }}% · {{ compatibility(entry).level }}</small>
        </div>
      </article>
    </div>
  </details>

  <details class="review-section">
    <summary><span>Controlled-Value Translation</span><strong>{{ controlledTransformations.length }} transformations</strong></summary>
    <div v-if="controlledTransformations.length" class="pills">
      <span v-for="translation in controlledTransformations" :key="translation">{{ translation }}</span>
    </div>
    <p v-else>No controlled-value transformations were required. Raw source values remain preserved in inspection state.</p>
  </details>

  <details :open="warnings.length > 0" class="review-section warning-section">
    <summary><span>Warnings and Confirmations</span><strong>{{ warnings.length }} warnings</strong></summary>
    <div class="confirmation-grid">
      <div><span>Compatibility approvals</span><strong>{{ confirmedWarningColumns.length }}</strong></div>
      <div><span>Duplicate player confirmations</span><strong>{{ confirmedDuplicateTargets.length }}</strong></div>
      <div><span>Duplicate concept confirmations</span><strong>{{ confirmedDuplicateConcepts.length }}</strong></div>
      <div><span>Ignored rows</span><strong>{{ inspection.normalized_inspection?.ignored_rows?.length || 0 }}</strong></div>
    </div>
    <p v-for="warning in warnings" :key="warning">{{ warning }}</p>
  </details>

  <details open class="review-section excluded-section">
    <summary><span>Not Importing Summary</span><strong>{{ excludedRows }} rows excluded</strong></summary>
    <div class="summary-grid">
      <div><span>Total events</span><strong>{{ eligibleRows + excludedRows }}</strong></div>
      <div><span>Importing</span><strong>{{ eligibleRows }}</strong></div>
      <div><span>Ignored</span><strong>{{ excludedRows }}</strong></div>
      <div><span>Players Not Importing</span><strong>{{ notImportingPlayers.length }}</strong><small>{{ notImportingPlayers.map(player=>player.source_name).join(', ') || 'None' }}</small></div>
      <div><span>Columns Not Importing</span><strong>{{ ignoredColumns.length }}</strong><small>{{ ignoredColumns.map(entry=>entry.source_column_name).join(', ') || 'None' }}</small></div>
      <div><span>Unknown columns</span><strong>{{ unknownColumns.length }}</strong><small>{{ unknownColumns.map(entry=>entry.source_column_name).join(', ') || 'None' }}</small></div>
      <div><span>Unavailable columns</span><strong>{{ unavailableColumns.length }}</strong><small>{{ unavailableColumns.map(column=>column.source_column_name).join(', ') || 'None' }}</small></div>
    </div>
  </details>

  <details class="review-section">
    <summary><span>Normalized sample records</span><strong>{{ inspection.sample_rows.length }} sample rows</strong></summary>
    <div class="samples"><pre v-for="(row,index) in inspection.sample_rows" :key="index">{{ JSON.stringify(row, null, 2) }}</pre></div>
  </details>

  <div v-if="['blast-motion','rapsodo'].includes(inspection.platform)" class="notice"><strong>Ready to import.</strong> Confirming will save this approved {{ inspection.detected_format?.provider }} session, its {{ inspection.platform === 'rapsodo' ? 'pitches' : 'swings' }}, metrics, and source provenance to the connected player.</div>
  <div v-else class="notice"><strong>Inspection only.</strong> No FMTRX import, session, event, assessment, profile, or statistics record will be created.</div>
</template>

<style scoped>
.translation-hero,.review-section,.notice{margin-top:14px;border:1px solid rgba(255,255,255,.1);border-radius:14px;background:rgba(5,12,29,.5)}.translation-hero{padding:22px;background:linear-gradient(135deg,rgba(255,43,74,.12),rgba(17,31,65,.8))}.translation-hero>span,summary span,.summary-grid span,.translation-list span,.concept-table span,.confirmation-grid span,.source-metadata>span{color:#94a3b8;font-size:9px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.translation-hero h2{margin:6px 0 18px;color:#fff}.translation-hero h2 b,.translation-list>b,.concept-table article>b{color:#ff4964}.translation-flow{display:flex;align-items:center;gap:14px}.translation-flow div{min-width:120px;padding:12px;border:1px solid rgba(255,255,255,.1);border-radius:10px}.translation-flow small,.translation-flow strong{display:block}.translation-flow small,small,p{color:#94a3b8}.translation-flow strong{color:#fff;font-size:22px}.review-section{overflow:hidden}.review-section summary{display:flex;align-items:center;justify-content:space-between;padding:16px;cursor:pointer;list-style:none}.review-section summary::-webkit-details-marker{display:none}.review-section summary:after{content:'+';margin-left:12px;color:#ff4964;font-size:20px}.review-section[open] summary:after{content:'−'}.review-section>p{padding:0 16px 14px}.summary-grid,.confirmation-grid{display:grid;grid-template-columns:repeat(4,1fr);border-top:1px solid rgba(255,255,255,.08)}.summary-grid>div,.confirmation-grid>div{display:flex;min-width:0;flex-direction:column;gap:5px;padding:14px;border:1px solid rgba(255,255,255,.05)}strong{color:#fff}.source-metadata{padding:14px;border-top:1px solid rgba(255,255,255,.08)}.source-metadata p{display:inline-block;margin:6px 14px 0 0}.translation-list,.concept-table{display:grid;gap:8px;padding:0 14px 14px}.translation-list article,.concept-table article{display:grid;grid-template-columns:1fr auto 1fr 1.4fr;gap:14px;align-items:center;padding:14px;border:1px solid rgba(59,211,154,.2);border-radius:10px;background:rgba(59,211,154,.04)}.translation-list article>div,.concept-table article>div{display:flex;min-width:0;flex-direction:column;gap:4px}.translation-list .excluded,.concept-table .excluded{border-color:rgba(255,180,59,.25);background:rgba(255,180,59,.04)}code{color:#77bfff;font-size:10px}.concept-details p{margin:0;font-size:10px}.pills{display:flex;flex-wrap:wrap;gap:8px;padding:0 16px 16px}.pills span{padding:7px 9px;border-radius:7px;background:rgba(119,191,255,.1);color:#b9dcff;font-size:10px}.warning-section{border-color:rgba(255,190,64,.25)}.excluded-section{border-color:rgba(255,180,59,.2)}.samples{max-height:420px;overflow:auto;padding:0 14px 14px}.samples pre{overflow:auto;margin:8px 0;padding:10px;border-radius:8px;background:#07101f;color:#b8c7db;font-size:10px}.notice{padding:15px;color:#b8c7db}.notice strong{color:#64e6b4}@media(max-width:800px){.translation-flow{align-items:stretch;flex-direction:column}.translation-flow>b{display:none}.summary-grid,.confirmation-grid{grid-template-columns:1fr 1fr}.translation-list article,.concept-table article{grid-template-columns:1fr}.translation-list article>b,.concept-table article>b{transform:rotate(90deg);justify-self:start}}@media(max-width:520px){.summary-grid,.confirmation-grid{grid-template-columns:1fr}.review-section summary{align-items:flex-start;flex-direction:column;gap:5px}.translation-hero h2{font-size:22px}}
</style>
