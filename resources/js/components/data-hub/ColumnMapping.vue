<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  columns: { type: Array, default: () => [] },
  concepts: { type: Array, default: () => [] },
  domains: { type: Array, default: () => [] },
  entries: { type: Object, required: true },
})
const emit = defineEmits(['update:entry', 'submit-concept'])
const search = ref('')
const domain = ref('')
const expanded = ref('')

const filteredConcepts = computed(() => props.concepts.filter(concept => {
  const matchesSearch = !search.value || `${concept.display_name} ${concept.definition} ${concept.canonical_key}`.toLowerCase().includes(search.value.toLowerCase())
  return matchesSearch && (!domain.value || concept.domain_id === domain.value)
}))
const domainName = id => props.domains.find(item => item.id === id)?.name || '—'
const concept = id => props.concepts.find(item => item.id === id)
const update = (column, patch) => emit('update:entry', column.source_column_name, { ...props.entries[column.source_column_name], ...patch })
</script>

<template>
  <section class="mapping-shell">
    <div class="mapping-toolbar">
      <input v-model="search" type="search" placeholder="Search Baseball Concepts">
      <select v-model="domain"><option value="">All domains</option><option v-for="item in domains" :key="item.id" :value="item.id">{{ item.name }}</option></select>
    </div>
    <article v-for="column in columns" :key="column.source_column_name" class="mapping-row">
      <div class="source"><strong>{{ column.source_column_name }}</strong><span>{{ column.sample_values?.join(' · ') || 'No sample values' }}</span></div>
      <select :value="entries[column.source_column_name]?.baseball_concept_id || ''" @change="update(column,{ baseball_concept_id:$event.target.value || null, action:$event.target.value ? 'map' : 'store_unknown' })">
        <option value="">Unresolved</option>
        <option v-for="item in filteredConcepts" :key="item.id" :value="item.id">{{ item.display_name }} · {{ domainName(item.domain_id) }}{{ item.canonical_unit_key ? ` (${item.canonical_unit_key})` : '' }}</option>
      </select>
      <select :value="entries[column.source_column_name]?.source_unit_key || ''" @change="update(column,{ source_unit_key:$event.target.value || null })">
        <option value="">Source unit</option><option value="mph">mph</option><option value="deg">degrees</option><option value="ft">feet</option><option value="in">inches</option><option value="rpm">rpm</option><option value="sec">seconds</option>
      </select>
      <select :value="entries[column.source_column_name]?.action || 'store_unknown'" @change="update(column,{ action:$event.target.value, baseball_concept_id:$event.target.value === 'map' ? entries[column.source_column_name]?.baseball_concept_id : null })">
        <option value="map">Map to concept</option><option value="ignore">Ignore</option><option value="store_unknown">Store as unknown</option><option value="submit_new">Submit new concept</option>
      </select>
      <div class="status">
        <b>{{ entries[column.source_column_name]?.confidence || 0 }}%</b>
        <span>{{ entries[column.source_column_name]?.resolution_source || 'unresolved' }}</span>
        <button type="button" @click="expanded = expanded === column.source_column_name ? '' : column.source_column_name">Details</button>
      </div>
      <div v-if="expanded === column.source_column_name" class="details">
        <template v-if="concept(entries[column.source_column_name]?.baseball_concept_id)">
          <strong>{{ concept(entries[column.source_column_name].baseball_concept_id).definition }}</strong>
          <span>Canonical unit: {{ concept(entries[column.source_column_name].baseball_concept_id).canonical_unit_key || 'None' }}</span>
          <span>Relationship: {{ entries[column.source_column_name]?.relationship_type || 'Exact/approved source mapping' }}</span>
        </template>
        <span>Type: {{ column.details?.inferred_data_type || 'unknown' }}</span><span>Min: {{ column.details?.minimum ?? '—' }}</span><span>Max: {{ column.details?.maximum ?? '—' }}</span><span>Average: {{ column.details?.average == null ? '—' : Number(column.details.average).toFixed(2) }}</span><span>Unique: {{ column.details?.unique_value_count ?? '—' }}</span>
        <button v-if="entries[column.source_column_name]?.action === 'submit_new'" type="button" @click="emit('submit-concept', column)">Submit candidate</button>
      </div>
    </article>
  </section>
</template>

<style scoped>
.mapping-shell{display:grid;gap:9px}.mapping-toolbar{display:grid;grid-template-columns:2fr 1fr;gap:10px;margin-bottom:6px}.mapping-toolbar input,.mapping-toolbar select,.mapping-row select{min-height:43px;padding:0 12px;border:1px solid rgba(255,255,255,.13);border-radius:9px;background:#111a32;color:#fff}.mapping-row{display:grid;grid-template-columns:minmax(150px,1.1fr) minmax(220px,1.8fr) 130px 170px 110px;gap:9px;align-items:center;padding:13px;border:1px solid rgba(255,255,255,.09);border-radius:12px;background:rgba(255,255,255,.035)}.source{display:flex;min-width:0;flex-direction:column;gap:4px}.source strong{color:#fff}.source span,.status span,.details span{overflow:hidden;color:#94a3b8;font-size:10px;text-overflow:ellipsis}.status{display:flex;flex-direction:column;gap:3px}.status b{color:#62ddb0}.status button,.details button{border:0;background:transparent;color:#ff6078;font-size:10px;text-align:left}.details{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.08);color:#e2e8f0;font-size:11px}@media(max-width:900px){.mapping-row{grid-template-columns:1fr}.details{grid-column:1}.mapping-toolbar{grid-template-columns:1fr}}
</style>
