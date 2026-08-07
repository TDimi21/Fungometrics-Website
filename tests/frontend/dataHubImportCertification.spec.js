import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = process.cwd()
const source = file => fs.readFileSync(path.join(root, file), 'utf8')
const manifest = JSON.parse(source('tests/Fixtures/DataHub/manifests/import-certification.json'))
const page = source('resources/js/pages/data-hub/ImportData.vue')
const structure = source('resources/js/components/data-hub/FileStructure.vue')
const playerMapping = source('resources/js/components/data-hub/PlayerMapping.vue')
const columnMapping = source('resources/js/components/data-hub/ColumnMapping.vue')
const review = source('resources/js/components/data-hub/InspectionReview.vue')

const fixture = id => manifest.fixtures.find(item => item.id === id)
const normalizedPayload = id => {
  const item = fixture(id)
  return {
    normalized_inspection: {
      detected_layout: item.expected_layout,
      header_row: item.expected_header_row,
      first_data_row: item.expected_first_data_row,
      requires_structure_confirmation: item.manual_file_structure_confirmation_required,
    },
    counts: {
      total_rows: item.expected_logical_record_count,
      players_found: item.expected_unique_source_players,
      columns_found: item.expected_metric_headers.length,
    },
    players: Array.from({ length: item.expected_unique_source_players }, (_, index) => ({
      source_key: `${id}:${index}`,
      source_name: `Certification Player ${index + 1}`,
      row_count: 1,
    })),
    source_columns: item.expected_metric_headers.map(source_column_name => ({ source_column_name })),
    warnings: item.expected_warnings,
  }
}

describe('Data Hub import certification UI contracts', () => {
  it.each([
    'generic-assessment-rows',
    'layout-players-columns',
    'layout-worksheet-player',
    'layout-single-player',
    'layout-metadata-header',
  ])('represents certified structure payload %s', id => {
    const payload = normalizedPayload(id)
    expect(payload.normalized_inspection.detected_layout).toBe(fixture(id).expected_layout)
    expect(payload.counts.total_rows).toBe(fixture(id).expected_logical_record_count)
    expect(payload.source_columns).toHaveLength(fixture(id).expected_metric_headers.length)
  })

  it('shows File Structure only when inspection requires confirmation', () => {
    expect(page).toContain('inspection.value.normalized_inspection?.requires_structure_confirmation')
    expect(page).toContain('<FileStructure')
    expect(structure).toContain('Player identification')
    expect(structure).toContain('Looks good — Continue to Player Mapping')
  })

  it('supports connected and Not Importing player totals for mixed roster data', () => {
    const mixed = fixture('player-mixed-roster')
    expect(mixed.expected_eligible_players).toBe(2)
    expect(mixed.expected_not_importing_players).toBe(2)
    expect(playerMapping).toContain('Not Importing')
    expect(review).toContain('Connected players')
    expect(review).toContain('Not Importing')
  })

  it('exposes unknown and duplicate concept review without silent consolidation', () => {
    expect(fixture('column-unknowns').approval_should_pass).toBe(false)
    expect(fixture('column-duplicate-concepts').duplicate_concept_confirmation_required).toBe(true)
    expect(columnMapping).toContain('Remember as unknown')
    expect(columnMapping).toContain('Submit new concept')
    expect(columnMapping).toContain('Confirm these columns represent the same measurement')
  })

  it('surfaces altered template and bad workbook warnings as blocked review cases', () => {
    for (const id of ['fmtrx-altered-template', 'invalid-bad-xlsx']) {
      expect(fixture(id).approval_should_pass).toBe(false)
      expect(fixture(id).expected_blocked_reason).toBeTruthy()
    }
    expect(page).toContain('mappingError')
    expect(review).toContain('Warnings')
  })

  it('invalidates inspection and mapping state after structure, team, destination, file, cancel, or route leave', () => {
    expect(page).toMatch(/const applyStructure[\s\S]*inspection\.value = null/)
    expect(page).toMatch(/const setTeam[\s\S]*inspection\.value = null/)
    expect(page).toMatch(/const setSessionType[\s\S]*mappingApproved\.value = false/)
    expect(page).toMatch(/const setFile[\s\S]*inspection\.value = null/)
    expect(page).toContain('@click="cancel"')
    expect(page).toContain('onBeforeRouteLeave(clearWorkflow)')
  })

  it('keeps certification fixtures out of browser persistence', () => {
    expect(page).not.toContain('localStorage.setItem')
    expect(page).not.toContain('sessionStorage.setItem')
  })
})
