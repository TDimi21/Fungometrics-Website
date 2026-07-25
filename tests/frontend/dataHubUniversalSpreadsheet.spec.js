import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(__dirname, '../..')
const source = relative => fs.readFileSync(path.join(root, relative), 'utf8')

describe('Data Hub universal spreadsheet inspection', () => {
  const page = source('resources/js/pages/data-hub/ImportData.vue')
  const structure = source('resources/js/components/data-hub/FileStructure.vue')
  const config = source('resources/js/data/dataHubConfig.js')
  const dropzone = source('resources/js/components/data-hub/FileDropzone.vue')

  it('accepts only the structured phase formats', () => {
    expect(config).toContain("['csv', 'xlsx', 'tsv']")
    expect(dropzone).toContain('.csv,.xlsx,.tsv')
    expect(dropzone).not.toMatch(/\\.pdf|\\.png|\\.jpg/)
  })

  it('shows a conditional File Structure panel before Player Mapping', () => {
    expect(page).toContain('structurePending')
    expect(page).toContain('<FileStructure')
    expect(page.indexOf('<FileStructure')).toBeLessThan(page.indexOf('<PlayerMapping'))
    expect(page).toContain('applyStructure')
  })

  it('supports all six layouts and bounded structure controls', () => {
    for (const layout of ['players_in_rows', 'players_in_columns', 'events_in_rows', 'worksheet_per_player', 'single_player_session', 'unknown']) {
      expect(structure).toContain(layout)
    }
    expect(structure).toContain('Header row')
    expect(structure).toContain('First data row')
    expect(structure).toContain('Player-name column')
    expect(structure).toContain('Metric-name column')
    expect(structure).toContain(':max=')
  })

  it('shows a sanitized bounded preview and refreshes after confirmation', () => {
    expect(structure).toContain('preview_rows')
    expect(structure).toContain('row.row_number')
    expect(structure).toContain('Confirm Structure & Refresh Preview')
    expect(page).toContain("form.append('structure'")
  })

  it('remains inspection-only', () => {
    expect(page).not.toMatch(/axiosPost\(['"`][^'"`]*(?:canonical-event|external-session|practice-result|assessment-result)/)
    expect(page).not.toMatch(/localStorage|sessionStorage|indexedDB/i)
  })
})
