import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = process.cwd()
const source = file => fs.readFileSync(path.join(root, file), 'utf8')
const review = source('resources/js/components/data-hub/InspectionReview.vue')
const page = source('resources/js/pages/data-hub/ImportData.vue')
const semantic = JSON.parse(source('tests/Fixtures/DataHub/manifests/semantic-equivalence.json'))

describe('Data Hub Translation Review', () => {
  it('presents Source Language to FMTRX Baseball Language summary', () => {
    expect(review).toContain('Translation summary')
    expect(review).toContain('Language <b>→</b> FMTRX Baseball Language')
    expect(review).toContain('Source rows')
    expect(review).toContain('Connected players')
    expect(review).toContain('Baseball concepts')
    expect(review).toContain('Eligible rows')
  })

  it('shows complete Player Translation decisions and exact row totals', () => {
    expect(review).toContain('Player Translation')
    expect(review).toContain('Source player')
    expect(review).toContain('FMTRX player')
    expect(review).toContain('Manual coach approval')
    expect(review).toContain('Remembered mapping')
    expect(review).toContain('player.row_count')
    expect(review).toMatch(/eligibleRows[\s\S]*connectedPlayers\.value\.reduce/)
    expect(review).toMatch(/excludedRows[\s\S]*notImportingPlayers\.value\.reduce/)
  })

  it('shows concept, domain, definition, units, transformation, and canonical key', () => {
    for (const label of [
      'Concept Translation', 'Source column', 'FMTRX concept', 'Canonical unit:',
      'Source unit:', 'Transformation:', 'Relationship:',
    ]) expect(review).toContain(label)
    expect(review).toContain('canonical_key')
    expect(review).toContain('<code>')
    expect(review).toContain('resolution_source')
    expect(review).toContain('compatibility(entry).level')
  })

  it('shows controlled-value transformations while preserving raw inspection preview', () => {
    expect(review).toContain('Controlled-Value Translation')
    expect(review).toContain('controlled_value_transformations')
    expect(review).toContain('Raw source values remain preserved in inspection state.')
    expect(review).toContain('JSON.stringify(row, null, 2)')
  })

  it('shows warnings, confirmations, unavailable columns, and Not Importing summary', () => {
    for (const label of [
      'Warnings and Confirmations', 'Compatibility approvals',
      'Duplicate player confirmations', 'Duplicate concept confirmations',
      'Not Importing Summary', 'Players Not Importing', 'Columns Not Importing',
      'Unknown columns', 'Unavailable columns',
    ]) expect(review).toContain(label)
    expect(page).toContain(':confirmed-warning-columns="confirmedWarningColumns"')
    expect(page).toContain(':confirmed-duplicate-targets="confirmedDuplicateTargets"')
    expect(page).toContain(':confirmed-duplicate-concepts="confirmedDuplicateConcepts"')
  })

  it('uses native expandable sections and responsive mobile layouts', () => {
    expect((review.match(/<details/g) || []).length).toBeGreaterThanOrEqual(6)
    expect(review).toContain('<summary>')
    expect(review).toContain('@media(max-width:800px)')
    expect(review).toContain('@media(max-width:520px)')
    expect(review).toMatch(/max-width:800px[\s\S]*grid-template-columns:1fr/)
  })

  it('certifies four exact equivalence groups and required non-equivalence guards', () => {
    expect(semantic.equivalence_groups.map(group => group.id)).toEqual([
      'exit_velocity', 'launch_angle', 'spray_angle', 'release_velocity',
    ])
    expect(semantic.non_equivalence_cases.map(item => item.id)).toEqual([
      'inbound_vs_release_velocity',
      'bat_speed_vs_exit_velocity',
      'hand_speed_vs_bat_speed',
      'projected_vs_measured_carry',
      'simulated_vs_real_result',
      'tagged_vs_automatic_trajectory',
      'automatic_vs_tagged_trajectory',
      'spin_axis_vs_clock',
      'vertical_vs_induced_break',
      'true_spin_vs_total_spin',
    ])
    expect(semantic.non_equivalence_cases.find(item => item.id === 'inbound_vs_release_velocity')).toMatchObject({
      canonical_key: 'hitting.inbound_pitch_velocity',
      must_not_equal: 'pitching.release_velocity',
      semantic_relationship: 'related_but_separate',
    })
  })

  it('keeps the final review explicitly inspection-only', () => {
    expect(review).toContain('Inspection only.')
    expect(review).toContain('No FMTRX import, session, event, assessment, profile, or statistics record will be created.')
  })
})
