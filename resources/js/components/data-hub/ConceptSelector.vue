<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { compatibilityForConcept } from '@/utils/dataHubConceptCompatibility.js'

const props = defineProps({
  concepts: { type: Array, default: () => [] },
  domains: { type: Array, default: () => [] },
  selectedConceptId: { type: String, default: '' },
  destination: { type: String, required: true },
  sourceColumn: { type: Object, required: true },
})
const emit = defineEmits(['select', 'special'])

const groupOrder = [
  'session_context', 'hitting', 'pitching', 'throwing', 'strength', 'mobility',
  'speed_agility', 'body_composition', 'recovery', 'assessment', 'game_outcome',
  'defense', 'vision', 'mental_performance',
]
const recommendations = {
  'Live AB': [
    'session_context.player_identity', 'session_context.event_date', 'session_context.event_identifier',
    'pitching.release_velocity', 'pitching.tagged_pitch_type', 'pitching.automatic_pitch_type',
    'pitching.spin_rate', 'pitching.spin_axis', 'pitching.induced_vertical_break',
    'pitching.horizontal_break', 'pitching.plate_location_height', 'pitching.plate_location_side',
    'hitting.exit_velocity', 'hitting.launch_angle', 'hitting.spray_angle', 'hitting.trajectory',
    'hitting.projected_distance', 'game_outcome.play_result', 'pitching.strike_result',
  ],
  Bullpen: [
    'session_context.player_identity', 'session_context.event_date', 'session_context.event_number',
    'session_context.event_time', 'pitching.release_velocity',
    'pitching.tagged_pitch_type', 'pitching.automatic_pitch_type', 'pitching.spin_rate',
    'pitching.true_spin_rate', 'pitching.spin_efficiency', 'pitching.spin_direction_clock',
    'pitching.spin_axis', 'pitching.induced_vertical_break', 'pitching.vertical_break', 'pitching.horizontal_break',
    'pitching.extension', 'pitching.release_height', 'pitching.release_side',
    'pitching.release_angle', 'pitching.horizontal_release_angle', 'pitching.gyro_degree',
    'pitching.plate_location_height', 'pitching.plate_location_side', 'pitching.strike_result',
  ],
  Cage: [
    'session_context.player_identity', 'session_context.event_date', 'hitting.exit_velocity',
    'hitting.launch_angle', 'hitting.spray_angle', 'hitting.projected_distance',
    'hitting.last_tracked_distance', 'hitting.hang_time', 'hitting.maximum_height',
    'hitting.ball_spin_rate', 'hitting.ball_spin_axis', 'hitting.contact_quality',
    'hitting.trajectory', 'hitting.trajectory_automatic', 'hitting.field_direction',
    'hitting.inbound_pitch_velocity', 'hitting.inbound_pitch_type', 'hitting.hand_speed',
    'hitting.bat_velocity', 'hitting.trigger_to_impact', 'hitting.attack_angle',
    'hitting.impact_momentum', 'hitting.point_of_impact_x', 'hitting.point_of_impact_y',
    'hitting.point_of_impact_z', 'hitting.bat_material', 'hitting.inbound_pitch_angle',
    'hitting.batter_side', 'hitting.hittrax_points', 'game_outcome.simulated_play_result',
    'hitting.bat_equipment', 'hitting.swing_details', 'hitting.bat_speed',
    'hitting.blast_plane_score', 'hitting.blast_connection_score', 'hitting.blast_rotation_score',
    'hitting.rotational_acceleration', 'hitting.on_plane_efficiency', 'hitting.early_connection',
    'hitting.connection_at_impact', 'hitting.vertical_bat_angle', 'hitting.blast_swing_power',
    'hitting.time_to_contact', 'hitting.peak_hand_speed',
  ],
  'Batting Practice': [
    'session_context.player_identity', 'session_context.event_date', 'hitting.exit_velocity',
    'hitting.launch_angle', 'hitting.spray_angle', 'hitting.projected_distance',
    'hitting.last_tracked_distance', 'hitting.hang_time', 'hitting.maximum_height',
    'hitting.ball_spin_rate', 'hitting.ball_spin_axis', 'hitting.contact_quality',
    'hitting.trajectory', 'hitting.trajectory_automatic', 'hitting.field_direction',
    'hitting.inbound_pitch_velocity', 'hitting.inbound_pitch_type', 'hitting.hand_speed',
    'hitting.bat_velocity', 'hitting.trigger_to_impact', 'hitting.attack_angle',
    'hitting.impact_momentum', 'hitting.point_of_impact_x', 'hitting.point_of_impact_y',
    'hitting.point_of_impact_z', 'hitting.bat_material', 'hitting.inbound_pitch_angle',
    'hitting.batter_side', 'hitting.hittrax_points', 'game_outcome.simulated_play_result',
    'hitting.bat_equipment', 'hitting.swing_details', 'hitting.bat_speed',
    'hitting.blast_plane_score', 'hitting.blast_connection_score', 'hitting.blast_rotation_score',
    'hitting.rotational_acceleration', 'hitting.on_plane_efficiency', 'hitting.early_connection',
    'hitting.connection_at_impact', 'hitting.vertical_bat_angle', 'hitting.blast_swing_power',
    'hitting.time_to_contact', 'hitting.peak_hand_speed',
  ],
  Strength: [
    'session_context.player_identity', 'session_context.event_date', 'strength.bench_press',
    'strength.front_squat', 'strength.back_squat', 'strength.deadlift', 'strength.power_clean',
    'strength.hand_grip', 'strength.vertical_jump', 'strength.broad_jump',
    'body_composition.body_weight',
  ],
  Mobility: [
    'session_context.player_identity', 'session_context.event_date', 'mobility.hip',
    'mobility.shoulder', 'mobility.ankle', 'mobility.hip_flexor', 'mobility.rotational',
  ],
  Recovery: [
    'session_context.player_identity', 'session_context.event_date', 'recovery.sleep_duration',
    'recovery.sleep_quality', 'recovery.recovery_score', 'recovery.mobility_score',
    'recovery.strength_score',
  ],
}

const open = ref(false)
const query = ref('')
const view = ref('recommended')
const category = ref('all')
const activeIndex = ref(0)
const collapsed = ref({})
const searchInput = ref(null)
const optionElements = ref([])
const selected = computed(() => props.concepts.find(item => item.id === props.selectedConceptId))
const domainById = computed(() => Object.fromEntries(props.domains.map(item => [item.id, item])))
const normalizedQuery = computed(() => query.value.trim().toLowerCase())
const recommendedKeys = computed(() => new Set(recommendations[props.destination] || [
  'session_context.player_identity', 'session_context.event_date',
]))
const searchable = concept => [
  concept.display_name, concept.canonical_key, concept.definition,
  concept.display_name?.split(/\s+/).map(word => word[0]).join(''),
  domainById.value[concept.domain_id]?.name, ...(concept.aliases || []),
].filter(Boolean).join(' ').toLowerCase()
const matchesQuery = concept => !normalizedQuery.value || searchable(concept).includes(normalizedQuery.value)
const compatibility = concept => compatibilityForConcept(props.destination, concept, props.domains)
const included = concept => {
  if (!matchesQuery(concept)) return false
  const conceptDomain = domainById.value[concept.domain_id]?.key
  if (category.value !== 'all' && conceptDomain !== category.value) return false
  if (view.value === 'recommended') {
    return recommendedKeys.value.has(concept.canonical_key)
      || conceptDomain === 'session_context'
  }
  if (view.value === 'compatible') return compatibility(concept).level === 'compatible'
  return true
}
const recommended = computed(() => props.concepts.filter(concept =>
  recommendedKeys.value.has(concept.canonical_key) && included(concept)
))
const grouped = computed(() => {
  const used = new Set(view.value === 'recommended' ? recommended.value.map(item => item.id) : [])
  const groups = groupOrder.map(key => {
    const domain = props.domains.find(item => item.key === key)
    return {
      key,
      name: domain?.name || key.replaceAll('_', ' '),
      concepts: props.concepts.filter(item =>
        item.domain_id === domain?.id && !used.has(item.id) && included(item)
      ),
    }
  }).filter(group => group.concepts.length)
  const knownDomains = new Set(groupOrder)
  const other = props.concepts.filter(item =>
    !used.has(item.id)
    && !knownDomains.has(domainById.value[item.domain_id]?.key)
    && included(item)
  )
  if (other.length) groups.push({ key: 'other', name: 'Other / Deprecated', concepts: other })
  return groups
})
const flatOptions = computed(() => {
  const output = []
  if (view.value === 'recommended') output.push(...recommended.value)
  grouped.value.forEach(group => {
    if (!collapsed.value[group.key]) output.push(...group.concepts)
  })
  return output
})

const openSelector = async () => {
  open.value = true
  query.value = ''
  const index = flatOptions.value.findIndex(item => item.id === props.selectedConceptId)
  activeIndex.value = Math.max(0, index)
  await nextTick()
  searchInput.value?.focus()
  optionElements.value[activeIndex.value]?.scrollIntoView({ block: 'nearest' })
}
const close = () => { open.value = false }
const choose = concept => {
  if (compatibility(concept).level === 'incompatible') return
  emit('select', concept.id)
  close()
}
const chooseSpecial = action => {
  emit('special', action)
  close()
}
const keydown = event => {
  if (event.key === 'Escape') return close()
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeIndex.value = Math.min(activeIndex.value + 1, flatOptions.value.length - 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeIndex.value = Math.max(activeIndex.value - 1, 0)
  } else if (event.key === 'Enter' && flatOptions.value[activeIndex.value]) {
    event.preventDefault()
    choose(flatOptions.value[activeIndex.value])
  }
  nextTick(() => optionElements.value[activeIndex.value]?.scrollIntoView({ block: 'nearest' }))
}
const toggleGroup = key => { collapsed.value = { ...collapsed.value, [key]: !collapsed.value[key] } }
watch([query, view, category], () => { activeIndex.value = 0 })
const onWindowKeydown = event => { if (open.value && event.key === 'Escape') close() }
window.addEventListener('keydown', onWindowKeydown)
onBeforeUnmount(() => window.removeEventListener('keydown', onWindowKeydown))
</script>

<template>
  <div class="concept-selector">
    <button class="selector-trigger" type="button" :aria-expanded="open" aria-haspopup="dialog" @click="openSelector">
      <span v-if="selected"><strong>{{ selected.display_name }}</strong><small>{{ domainById[selected.domain_id]?.name }} · {{ selected.canonical_unit_key || 'No unit' }}</small></span>
      <span v-else><strong>— Not Importing —</strong><small>Select a Baseball Concept</small></span>
      <b aria-hidden="true">⌄</b>
    </button>
    <Teleport to="body">
      <div v-if="open" class="selector-backdrop" @mousedown.self="close">
        <section class="selector-panel" role="dialog" aria-modal="true" :aria-label="`Select Baseball Concept for ${sourceColumn.source_column_name}`" @keydown="keydown">
          <header><div><span>Column Mapping</span><h3>Select Baseball Concept</h3><p>{{ sourceColumn.source_column_name }}</p></div><button type="button" aria-label="Close concept selector" @click="close">×</button></header>
          <div class="selector-controls">
            <input ref="searchInput" v-model="query" type="search" aria-label="Search Baseball Concepts" placeholder="Search names, keys, definitions, or aliases">
            <nav aria-label="Concept visibility">
              <button v-for="mode in ['recommended','compatible','all']" :key="mode" type="button" :class="{ active: view === mode }" @click="view = mode">{{ mode === 'all' ? 'All Concepts' : mode }}</button>
            </nav>
            <nav class="category-tabs" aria-label="Concept area">
              <button v-for="item in [{key:'all',label:'All Areas'},{key:'hitting',label:'Hitting'},{key:'pitching',label:'Pitching'},{key:'session_context',label:'User / Session'}]" :key="item.key" type="button" :class="{ active: category === item.key }" @click="category = item.key">{{ item.label }}</button>
            </nav>
          </div>
          <div class="special-options" aria-label="Mapping actions">
            <button type="button" @click="chooseSpecial('ignore')">— Not Importing —</button>
            <button type="button" @click="chooseSpecial('store_unknown')">Store as Unknown</button>
            <button type="button" @click="chooseSpecial('submit_new')">Submit New Concept</button>
          </div>
          <div class="concept-list" role="listbox" :aria-activedescendant="flatOptions[activeIndex]?.id">
            <template v-if="view === 'recommended' && recommended.length">
              <h4>Recommended for {{ destination }}</h4>
              <button v-for="item in recommended" :id="item.id" :key="`recommended-${item.id}`" ref="optionElements" type="button" role="option" class="concept-option" :class="{ active: flatOptions[activeIndex]?.id === item.id }" :aria-selected="selectedConceptId === item.id" @click="choose(item)">
                <span><strong>{{ item.display_name }}</strong><small>{{ domainById[item.domain_id]?.name }} · {{ item.canonical_unit_key || 'No canonical unit' }}</small><em>{{ item.definition }}</em></span><b v-if="selectedConceptId === item.id">✓</b>
              </button>
            </template>
            <section v-for="group in grouped" :key="group.key" class="concept-group">
              <button class="group-heading" type="button" :aria-expanded="!collapsed[group.key]" @click="toggleGroup(group.key)"><span>{{ group.name }}</span><b>{{ collapsed[group.key] ? '+' : '−' }}</b></button>
              <button v-for="item in collapsed[group.key] ? [] : group.concepts" :id="item.id" :key="item.id" ref="optionElements" type="button" role="option" class="concept-option" :class="{ active: flatOptions[activeIndex]?.id === item.id }" :disabled="compatibility(item).level === 'incompatible'" :aria-selected="selectedConceptId === item.id" :title="compatibility(item).level === 'incompatible' ? compatibility(item).reason : item.definition" @click="choose(item)">
                <span><strong>{{ item.display_name }}</strong><small>{{ group.name }} · {{ item.canonical_unit_key || 'No canonical unit' }}</small><em>{{ item.definition }}</em><i v-if="compatibility(item).level === 'incompatible'">{{ compatibility(item).reason }}</i></span><b v-if="selectedConceptId === item.id">✓</b>
              </button>
            </section>
            <p v-if="!flatOptions.length" class="no-results">No Baseball Concepts match this search.</p>
          </div>
        </section>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.selector-trigger{width:100%;min-height:49px;display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:1px solid rgba(255,255,255,.13);border-radius:9px;background:#111a32;color:#fff;text-align:left}.selector-trigger span{display:grid;gap:2px}.selector-trigger small{color:#94a3b8;font-size:9px}.selector-backdrop{position:fixed;z-index:10000;inset:0;display:grid;place-items:center;padding:20px;background:rgba(2,7,20,.78);backdrop-filter:blur(6px)}.selector-panel{width:min(680px,100%);max-height:min(760px,90vh);display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(255,255,255,.15);border-radius:18px;background:#091329;box-shadow:0 30px 90px rgba(0,0,0,.55);color:#fff}.selector-panel>header{display:flex;justify-content:space-between;padding:20px 22px;border-bottom:1px solid rgba(255,255,255,.1)}.selector-panel header span{color:#ff4964;font-size:9px;font-weight:800;text-transform:uppercase}.selector-panel h3{margin:3px 0;font-size:22px}.selector-panel p{margin:0;color:#94a3b8;font-size:11px}.selector-panel header button{border:0;background:transparent;color:#94a3b8;font-size:28px}.selector-controls{position:sticky;top:0;z-index:3;padding:14px 18px 10px;background:#091329}.selector-controls input{width:100%;min-height:44px;padding:0 13px;border:1px solid rgba(255,255,255,.14);border-radius:9px;background:#111a32;color:#fff}.selector-controls nav{display:flex;gap:6px;margin-top:9px}.selector-controls button,.special-options button{padding:8px 11px;border:1px solid rgba(255,255,255,.12);border-radius:7px;background:transparent;color:#cbd5e1;font-size:9px;text-transform:capitalize}.selector-controls button.active{border-color:#ff2b4a;background:#ff2b4a;color:#fff}.special-options{display:flex;gap:7px;padding:0 18px 12px;border-bottom:1px solid rgba(255,255,255,.1)}.special-options button:first-child{color:#ffb43b}.concept-list{overflow-y:auto;padding:0 18px 18px}.concept-list>h4,.group-heading{position:sticky;top:0;z-index:2;width:100%;margin:0;padding:12px 8px 7px;border:0;background:#091329;color:#ff7187;font-size:9px;font-weight:800;letter-spacing:.08em;text-align:left;text-transform:uppercase}.group-heading{display:flex;justify-content:space-between;color:#8ea4c9}.concept-option{width:100%;display:flex;align-items:center;justify-content:space-between;padding:10px;border:1px solid transparent;border-bottom-color:rgba(255,255,255,.07);background:transparent;color:#fff;text-align:left}.concept-option:hover,.concept-option:focus,.concept-option.active{border-color:rgba(255,43,74,.55);border-radius:9px;background:rgba(255,43,74,.09);outline:0}.concept-option:disabled{cursor:not-allowed;opacity:.42}.concept-option span{display:grid;gap:3px}.concept-option small,.concept-option em,.concept-option i{color:#94a3b8;font-size:9px;font-style:normal}.concept-option i{color:#ff7187}.concept-option b{color:#62ddb0}.no-results{padding:30px;text-align:center;color:#94a3b8}@media(max-width:700px){.selector-backdrop{align-items:flex-end;padding:0}.selector-panel{width:100%;max-height:92vh;border-radius:18px 18px 0 0}.selector-controls nav,.special-options{overflow-x:auto}.selector-controls button,.special-options button{flex:0 0 auto}}
.selector-controls .category-tabs{padding-top:8px;border-top:1px solid rgba(255,255,255,.08)}.selector-controls .category-tabs button{font-weight:800}
</style>
