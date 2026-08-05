import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(__dirname, '../..')
const source = relative => fs.readFileSync(path.join(root, relative), 'utf8')

describe('Data Hub Rapsodo pitching import', () => {
  const platforms = source('resources/js/data/dataHubPlatforms.js')
  const page = source('resources/js/pages/data-hub/ImportData.vue')
  const players = source('resources/js/components/data-hub/PlayerMapping.vue')
  const columns = source('resources/js/components/data-hub/ColumnMapping.vue')
  const review = source('resources/js/components/data-hub/InspectionReview.vue')

  it('enables Rapsodo XLSX with pitching destinations and defaults to Bullpen', () => {
    expect(platforms).toMatch(/key:\s*'rapsodo'[\s\S]*fileTypes:\s*\['xlsx'\]/)
    expect(platforms).toMatch(/key:\s*'rapsodo'[\s\S]*sessionTypes:\s*\['Bullpen',\s*'Pitching Practice',\s*'Assessment'\]/)
    expect(page).toContain("nextKey === 'rapsodo'")
    expect(page).toContain("? 'Bullpen' :")
  })

  it('uses controlled session assignment when the workbook has no player identity', () => {
    expect(players).toContain('identity_missing')
    expect(players).toContain('Session assignment')
    expect(page).toContain('remember_mapping')
  })

  it('surfaces source units, transformations, and validation warnings', () => {
    expect(columns).toContain('controlled_value_transformations')
    expect(columns).toContain('source_unit_key')
    expect(columns).toContain('percent')
    expect(review).toContain('workbook')
    expect(review).toContain('worksheet')
    expect(review).toContain('controlled_value_transformations')
    expect(review).toContain('source_unit_key')
  })

  it('commits only through the governed Rapsodo import endpoint', () => {
    expect(page).toContain("axiosPost('data-hub/inspect'")
    expect(page).toContain("axiosPost('data-hub/imports/rapsodo', form)")
    expect(page).toContain('Import Rapsodo Data')
    expect(page).toContain('View Rapsodo Report')
    expect(review).toContain("['blast-motion','rapsodo'].includes(inspection.platform)")
    expect(review).toContain("inspection.platform === 'rapsodo' ? 'pitches' : 'swings'")
    expect(page).not.toMatch(/axiosPost\(['"`][^'"`]*(?:canonical-event|external-session|bullpen|practice-result)/)
    expect(page).not.toMatch(/localStorage|sessionStorage|indexedDB/i)
  })
})
