import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(__dirname, '../..')
const source = relative => fs.readFileSync(path.join(root, relative), 'utf8')

describe('Data Hub TrackMan Player Mapping', () => {
  const page = source('resources/js/pages/data-hub/ImportData.vue')
  const component = source('resources/js/components/data-hub/PlayerMapping.vue')
  const columns = source('resources/js/components/data-hub/ColumnMapping.vue')
  const review = source('resources/js/components/data-hub/InspectionReview.vue')

  it('keys decisions by stable source identity and approves before column mapping', () => {
    expect(page).toContain('mappings[player.source_key]')
    expect(page).toContain("axiosPost('data-hub/player-mappings/approve'")
    expect(page).toMatch(/step\.value === 4[\s\S]*approvePlayerMapping/)
    expect(page).toContain('Approve Player Mapping')
  })

  it('shows summary filters roster metadata and review-first behavior', () => {
    expect(component).toContain('Players found')
    expect(component).toContain('Eligible rows')
    expect(component).toContain('Needs Review')
    expect(component).toContain('Not Importing')
    expect(component).toContain('Pitchers')
    expect(component).toContain('Batters')
    expect(component).toContain('graduation_year')
    expect(component).toContain('primary_position')
    expect(component).toContain('Review ${summary.connected} Connected Players')
  })

  it('uses intentional Not Importing selection and duplicate confirmation', () => {
    expect(component).toContain('— Not Importing —')
    expect(component).toContain('Set to Not Importing')
    expect(component).toContain('Confirm these are the same person')
    expect(page).toContain('confirmed_duplicate_targets')
  })

  it('preserves mapping for destination-only changes and clears it for team or file changes', () => {
    expect(page).toMatch(/const setTeam[\s\S]*Object\.keys\(mappings\)/)
    expect(page).toMatch(/const setFile[\s\S]*Object\.keys\(mappings\)/)
    expect(page).toContain('@update:session-type="setSessionType"')
    expect(page).toMatch(/const setSessionType[\s\S]*mappingApproved\.value = false/)
  })

  it('does not create sessions events practices profiles or statistics', () => {
    expect(page).not.toMatch(/axiosPost\(['"`][^'"`]*(?:external-session|canonical-event|practice|profile|statistics)/)
  })

  it('applies the connected-player and connected-concept import gates', () => {
    expect(columns).toContain('— Not Importing —')
    expect(columns).toContain('Connected Baseball Concept')
    expect(columns).toContain('Compatible only')
    expect(columns).toContain('Multiple source columns are connected to')
    expect(page).toContain('confirmed_duplicate_concepts')
    expect(page).toContain('warning_confirmed')
    expect(review).toContain('Connected / Not Importing')
    expect(review).toContain('Total events')
    expect(review).toContain('Ignored')
  })
})
