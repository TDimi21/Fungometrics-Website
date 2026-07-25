import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(__dirname, '../..')
const source = relative => fs.readFileSync(path.join(root, relative), 'utf8')

describe('Data Hub Blast Motion inspection', () => {
  const platforms = source('resources/js/data/dataHubPlatforms.js')
  const page = source('resources/js/pages/data-hub/ImportData.vue')
  const players = source('resources/js/components/data-hub/PlayerMapping.vue')
  const columns = source('resources/js/components/data-hub/ColumnMapping.vue')
  const selector = source('resources/js/components/data-hub/ConceptSelector.vue')
  const review = source('resources/js/components/data-hub/InspectionReview.vue')

  it('enables Blast CSV and defaults its destination to Batting Practice', () => {
    expect(platforms).toMatch(/key:\s*'blast-motion'[\s\S]*fileTypes:\s*\['csv'\]/)
    expect(page).toContain("nextKey === 'blast-motion'")
    expect(page).toContain("'Batting Practice'")
  })

  it('keeps Player Mapping and controlled session assignment visible', () => {
    expect(players).toContain('identity_missing')
    expect(players).toContain('Session assignment')
    expect(players).toContain('FMTRX roster player')
    expect(page).toContain('Approve Player Mapping')
  })

  it('shows Blast units, unavailable warnings, and source-specific labels', () => {
    expect(columns).toContain('g_force')
    expect(columns).toContain('kw')
    expect(columns).toContain('source_specific')
    expect(columns).toContain('source-warning')
    expect(selector).toContain('hitting.blast_plane_score')
    expect(selector).toContain('hitting.peak_hand_speed')
  })

  it('shows report metadata, header row, translations, and review-only notice', () => {
    expect(review).toContain('inspection.report')
    expect(review).toContain('metadata_summary')
    expect(review).toContain('Translation summary')
    expect(review).toContain('Unavailable columns')
    expect(review).toContain('Inspection only.')
  })

  it('preserves approvals through normal backward navigation and performs no import write', () => {
    expect(page).toContain('playerMappingApproved')
    expect(page).toContain('mappingApproved')
    expect(page).toContain('step.value -= 1')
    expect(page).not.toMatch(/axiosPost\(['"`][^'"`]*(?:canonical-event|external-session|batting-practice-result|cage-result)/)
  })
})
