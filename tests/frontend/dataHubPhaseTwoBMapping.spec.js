import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(__dirname, '../..')
const source = relative => fs.readFileSync(path.join(root, relative), 'utf8')

describe('Data Hub Phase 2B.1 mapping foundation', () => {
  const page = source('resources/js/pages/data-hub/ImportData.vue')
  const mapping = source('resources/js/components/data-hub/ColumnMapping.vue')
  const selector = source('resources/js/components/data-hub/ConceptSelector.vue')
  const unknown = source('resources/js/pages/data-hub/UnknownColumns.vue')
  const stepper = source('resources/js/components/data-hub/ImportStepper.vue')

  it('adds column mapping before review and requires explicit approval', () => {
    expect(stepper).toContain("'Column Mapping', 'Review'")
    expect(page).toContain('Approve Mapping')
    expect(page).toContain("axiosPost('data-hub/mappings/approve'")
    expect(page).toMatch(/step\.value === 5[\s\S]*approveColumnMapping/)
  })

  it('shows mapping evidence and all governed actions', () => {
    expect(mapping).toContain('sample_values')
    expect(mapping).toContain('inferred_data_type')
    expect(mapping).toContain('Connected Baseball Concept')
    expect(mapping).toContain('Not Importing')
    expect(mapping).toContain('Remember as unknown')
    expect(mapping).toContain('Submit new concept')
    expect(mapping).toContain('relationship_type')
  })

  it('provides a team-scoped unknown-column governance page', () => {
    expect(unknown).toContain("axiosGet('data-hub/unknown-columns'")
    expect(unknown).toContain('resolved_concept_id')
    expect(unknown).toContain("update(row,'archived')")
    expect(unknown).toContain("axiosPost('data-hub/concept-submissions'")
  })

  it('does not add event, practice, statistics, or import writes', () => {
    expect(page).not.toMatch(/axiosPost\(['"`][^'"`]*(?:external-session|canonical-event|practice|statistics|import)/)
    expect(unknown).not.toMatch(/localStorage|sessionStorage|indexedDB/i)
  })

  it('groups concepts in the required order with destination recommendations', () => {
    expect(selector).toContain("'session_context', 'hitting', 'pitching', 'throwing', 'strength', 'mobility'")
    expect(selector).toContain("'speed_agility', 'body_composition', 'recovery', 'assessment', 'game_outcome'")
    expect(selector).toContain("'defense', 'vision', 'mental_performance'")
    expect(selector).toContain('Other / Deprecated')
    expect(selector).toContain('Recommended for {{ destination }}')
    expect(selector).toContain("'Live AB'")
    expect(selector).toContain('Bullpen:')
    expect(selector).toContain('Cage:')
    expect(selector).toContain('Strength:')
    expect(selector).toContain('Mobility:')
    expect(selector).toContain('Recovery:')
  })

  it('keeps special actions above searchable grouped concepts', () => {
    const notImporting = selector.indexOf('— Not Importing —')
    const unknown = selector.indexOf('Store as Unknown')
    const submit = selector.indexOf('Submit New Concept')
    const conceptList = selector.indexOf('class="concept-list"')
    expect(notImporting).toBeGreaterThan(-1)
    expect(unknown).toBeGreaterThan(notImporting)
    expect(submit).toBeGreaterThan(unknown)
    expect(conceptList).toBeGreaterThan(submit)
    expect(selector).toContain('concept.display_name, concept.canonical_key, concept.definition')
    expect(selector).toContain("map(word => word[0]).join('')")
    expect(selector).toContain('...(concept.aliases || [])')
  })

  it('supports recommended compatible and all filters with disabled incompatibilities', () => {
    expect(selector).toContain("['recommended','compatible','all']")
    expect(selector).toContain("view.value === 'compatible'")
    expect(selector).toContain("view.value === 'recommended'")
    expect(selector).toContain(':disabled="compatibility(item).level === \'incompatible\'"')
    expect(selector).toContain('compatibility(item).reason')
    expect(selector).toContain("key:'hitting',label:'Hitting'")
    expect(selector).toContain("key:'pitching',label:'Pitching'")
    expect(selector).toContain("key:'session_context',label:'User / Session'")
    expect(selector).toContain("category.value !== 'all' && conceptDomain !== category.value")
  })

  it('supports accessible keyboard navigation, selection scrolling, and a mobile sheet', () => {
    expect(selector).toContain("event.key === 'ArrowDown'")
    expect(selector).toContain("event.key === 'ArrowUp'")
    expect(selector).toContain("event.key === 'Enter'")
    expect(selector).toContain("event.key === 'Escape'")
    expect(selector).toContain("scrollIntoView({ block: 'nearest' })")
    expect(selector).toContain('role="dialog"')
    expect(selector).toContain('role="listbox"')
    expect(selector).toContain('aria-selected')
    expect(selector).toContain('@media(max-width:700px)')
    expect(selector).toContain('align-items:flex-end')
  })

  it('uses an isolated selector instance for each source column', () => {
    expect(mapping).toContain('<ConceptSelector')
    expect(mapping).toContain(':source-column="column"')
    expect(mapping).toContain("@select=\"update(column,{ baseball_concept_id:$event, action:'map' })\"")
    expect(selector).toContain("emit('select', concept.id)")
  })
})
