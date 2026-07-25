import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(__dirname, '../..')
const source = relative => fs.readFileSync(path.join(root, relative), 'utf8')

describe('Data Hub Phase 2B.1 mapping foundation', () => {
  const page = source('resources/js/pages/data-hub/ImportData.vue')
  const mapping = source('resources/js/components/data-hub/ColumnMapping.vue')
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
    expect(mapping).toContain('Map to concept')
    expect(mapping).toContain('Store as unknown')
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
})
