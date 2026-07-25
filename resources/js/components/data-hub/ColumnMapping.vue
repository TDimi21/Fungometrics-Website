<script setup>
import { computed, ref } from 'vue'
import { compatibilityForConcept, rankConceptsForDestination } from '@/utils/dataHubConceptCompatibility.js'

const props = defineProps({
  columns: { type: Array, default: () => [] },
  concepts: { type: Array, default: () => [] },
  domains: { type: Array, default: () => [] },
  entries: { type: Object, required: true },
  destination: { type: String, required: true },
  confirmedWarnings: { type: Array, default: () => [] },
  confirmedDuplicates: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:entry', 'submit-concept', 'confirm:warning', 'confirm:duplicate'])
const search = ref('')
const domain = ref('')
const filter = ref('needs_review')
const compatibleOnly = ref(false)
const expanded = ref('')

const filteredConcepts = computed(() => rankConceptsForDestination(props.concepts.filter(concept => {
  const matchesSearch = !search.value || `${concept.display_name} ${concept.definition} ${concept.canonical_key}`.toLowerCase().includes(search.value.toLowerCase())
  const compatibility = compatibilityForConcept(props.destination, concept, props.domains)
  return matchesSearch && (!domain.value || concept.domain_id === domain.value) && (!compatibleOnly.value || compatibility.level === 'compatible')
}), props.destination, props.domains))
const domainName = id => props.domains.find(item => item.id === id)?.name || '—'
const concept = id => props.concepts.find(item => item.id === id)
const compatibility = column => compatibilityForConcept(props.destination, concept(props.entries[column.source_column_name]?.baseball_concept_id), props.domains)
const status = column => {
  const entry = props.entries[column.source_column_name]
  if (entry?.action === 'store_unknown' || entry?.action === 'submit_new') return 'unknown'
  if (entry?.action !== 'map' || !entry?.baseball_concept_id) return 'not_importing'
  if (compatibility(column).level !== 'compatible' || Number(entry.confidence || 0) < 100) return 'needs_review'
  return 'connected'
}
const visibleColumns = computed(() => props.columns.filter(column => filter.value === 'all' || status(column) === filter.value))
const summary = computed(() => ({
  found: props.columns.length,
  connected: props.columns.filter(column => status(column) === 'connected').length,
  suggested: props.columns.filter(column => props.entries[column.source_column_name]?.baseball_concept_id && Number(props.entries[column.source_column_name]?.confidence || 0) < 100).length,
  notImporting: props.columns.filter(column => status(column) === 'not_importing').length,
  unknown: props.columns.filter(column => status(column) === 'unknown').length,
  needsReview: props.columns.filter(column => status(column) === 'needs_review').length,
}))
const duplicateGroups = computed(() => {
  const groups = {}
  props.columns.forEach(column => {
    const entry = props.entries[column.source_column_name]
    if (entry?.action === 'map' && entry.baseball_concept_id) (groups[entry.baseball_concept_id] ||= []).push(column)
  })
  return Object.entries(groups).filter(([, columns]) => columns.length > 1)
})
const update = (column, patch) => emit('update:entry', column.source_column_name, { ...props.entries[column.source_column_name], ...patch })
</script>

<template>
  <section class="mapping-shell">
    <header class="column-summary"><div v-for="(value,key) in summary" :key="key"><strong>{{ value }}</strong><span>{{ key.replace(/([A-Z])/g,' $1') }}</span></div></header>
    <div class="mapping-toolbar">
      <input v-model="search" type="search" placeholder="Search Baseball Concepts">
      <select v-model="domain"><option value="">All domains</option><option v-for="item in domains" :key="item.id" :value="item.id">{{ item.name }}</option></select>
      <label><input v-model="compatibleOnly" type="checkbox"> Compatible only</label>
    </div>
    <nav class="column-filters"><button v-for="item in ['needs_review','connected','not_importing','unknown','all']" :key="item" :class="{active:filter===item}" @click="filter=item">{{ item.replace('_',' ') }}</button></nav>
    <section v-if="duplicateGroups.length" class="duplicate-columns"><article v-for="[conceptId,items] in duplicateGroups" :key="conceptId"><strong>Multiple source columns are connected to {{ concept(conceptId)?.display_name }}.</strong><span>{{ items.map(item=>item.source_column_name).join(' · ') }}</span><label><input type="checkbox" :checked="confirmedDuplicates.includes(conceptId)" @change="emit('confirm:duplicate',conceptId,$event.target.checked)"> Confirm these columns represent the same measurement</label></article></section>
    <article v-for="column in visibleColumns" :key="column.source_column_name" :class="['mapping-row',status(column)]">
      <div class="source"><strong>{{ column.source_column_name }}</strong><span>{{ column.sample_values?.join(' · ') || 'No sample values' }}</span></div>
      <select :value="entries[column.source_column_name]?.baseball_concept_id || ''" @change="update(column,{ baseball_concept_id:$event.target.value || null, action:$event.target.value ? 'map' : 'ignore' })">
        <option value="">— Not Importing —</option>
        <option v-for="item in filteredConcepts" :key="item.id" :value="item.id">{{ item.display_name }} · {{ domainName(item.domain_id) }}{{ item.canonical_unit_key ? ` (${item.canonical_unit_key})` : '' }}</option>
      </select>
      <select :value="entries[column.source_column_name]?.source_unit_key || ''" @change="update(column,{ source_unit_key:$event.target.value || null })">
        <option value="">Source unit</option><option value="mph">mph</option><option value="deg">degrees</option><option value="ft">feet</option><option value="in">inches</option><option value="rpm">rpm</option><option value="sec">seconds</option>
      </select>
      <select :value="entries[column.source_column_name]?.action || 'ignore'" @change="update(column,{ action:$event.target.value, baseball_concept_id:$event.target.value === 'map' ? entries[column.source_column_name]?.baseball_concept_id : null })">
        <option value="map">Connected Baseball Concept</option><option value="ignore">Not Importing</option><option value="store_unknown">Remember as unknown</option><option value="submit_new">Submit new concept</option>
      </select>
      <div class="status">
        <b>{{ entries[column.source_column_name]?.confidence || 0 }}%</b>
        <span>{{ entries[column.source_column_name]?.action === 'map' ? (entries[column.source_column_name]?.resolution_source || 'manual') : status(column).replace('_',' ') }}</span>
        <em v-if="entries[column.source_column_name]?.action === 'map'" :class="compatibility(column).level">{{ compatibility(column).level }}</em>
        <button type="button" @click="expanded = expanded === column.source_column_name ? '' : column.source_column_name">Details</button>
      </div>
      <label v-if="compatibility(column).level === 'warning'" class="override"><input type="checkbox" :checked="confirmedWarnings.includes(column.source_column_name)" @change="emit('confirm:warning',column.source_column_name,$event.target.checked)"> Confirm warning</label>
      <p v-if="compatibility(column).level === 'incompatible'" class="incompatible-message">{{ compatibility(column).reason }}</p>
      <div v-if="expanded === column.source_column_name" class="details">
        <template v-if="concept(entries[column.source_column_name]?.baseball_concept_id)">
          <strong>{{ concept(entries[column.source_column_name].baseball_concept_id).definition }}</strong>
          <span>Canonical unit: {{ concept(entries[column.source_column_name].baseball_concept_id).canonical_unit_key || 'None' }}</span>
          <span>Relationship: {{ entries[column.source_column_name]?.relationship_type || 'Exact/approved source mapping' }}</span>
          <span>Canonical key: {{ concept(entries[column.source_column_name].baseball_concept_id).canonical_key }}</span>
          <span>{{ compatibility(column).reason }}</span>
        </template>
        <span>Type: {{ column.details?.inferred_data_type || 'unknown' }}</span><span>Min: {{ column.details?.minimum ?? '—' }}</span><span>Max: {{ column.details?.maximum ?? '—' }}</span><span>Average: {{ column.details?.average == null ? '—' : Number(column.details.average).toFixed(2) }}</span><span>Unique: {{ column.details?.unique_value_count ?? '—' }}</span>
        <button v-if="entries[column.source_column_name]?.action === 'submit_new'" type="button" @click="emit('submit-concept', column)">Submit candidate</button>
      </div>
    </article>
  </section>
</template>

<style scoped>
.mapping-shell{display:grid;gap:9px}.mapping-toolbar{display:grid;grid-template-columns:2fr 1fr;gap:10px;margin-bottom:6px}.mapping-toolbar input,.mapping-toolbar select,.mapping-row select{min-height:43px;padding:0 12px;border:1px solid rgba(255,255,255,.13);border-radius:9px;background:#111a32;color:#fff}.mapping-row{display:grid;grid-template-columns:minmax(150px,1.1fr) minmax(220px,1.8fr) 130px 170px 110px;gap:9px;align-items:center;padding:13px;border:1px solid rgba(255,255,255,.09);border-radius:12px;background:rgba(255,255,255,.035)}.source{display:flex;min-width:0;flex-direction:column;gap:4px}.source strong{color:#fff}.source span,.status span,.details span{overflow:hidden;color:#94a3b8;font-size:10px;text-overflow:ellipsis}.status{display:flex;flex-direction:column;gap:3px}.status b{color:#62ddb0}.status button,.details button{border:0;background:transparent;color:#ff6078;font-size:10px;text-align:left}.details{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.08);color:#e2e8f0;font-size:11px}@media(max-width:900px){.mapping-row{grid-template-columns:1fr}.details{grid-column:1}.mapping-toolbar{grid-template-columns:1fr}}
.column-summary{display:flex;flex-wrap:wrap;gap:10px;padding:12px;border:1px solid rgba(255,255,255,.1);border-radius:11px}.column-summary div{min-width:90px}.column-summary strong,.column-summary span{display:block}.column-summary strong{color:#fff;font-size:20px}.column-summary span{color:#94a3b8;font-size:8px;text-transform:uppercase}.column-filters{display:flex;flex-wrap:wrap;gap:6px}.column-filters button{padding:7px 10px;border:1px solid rgba(255,255,255,.12);border-radius:7px;background:transparent;color:#cbd5e1;font-size:9px;text-transform:uppercase}.column-filters .active{border-color:#ff2b4a;background:#ff2b4a;color:#fff}.duplicate-columns article{padding:12px;border:1px solid rgba(255,190,64,.35);border-radius:9px;background:rgba(255,190,64,.08);color:#ffe3ac}.duplicate-columns span,.duplicate-columns label{display:block;margin-top:5px}.mapping-row.connected{border-left:3px solid #3bd39a}.mapping-row.needs_review{border-left:3px solid #8f7cff}.mapping-row.not_importing{border-left:3px solid #ffb43b}.status em{font-size:8px;font-style:normal;text-transform:uppercase}.status em.compatible{color:#3bd39a}.status em.warning{color:#ffb43b}.status em.incompatible,.incompatible-message{color:#ff6078}.override,.incompatible-message{grid-column:1/-1;font-size:10px}
</style>
