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

  it('supports the three player-identification layouts and bounded structure controls', () => {
    for (const layout of ['players_in_rows', 'players_in_columns', 'single_player_session']) {
      expect(structure).toContain(layout)
    }
    expect(structure).toContain('Header row')
    expect(structure).toContain('First data row')
    expect(structure).toContain('playerColumn')
    expect(structure).toContain('metricColumn')
    expect(structure).toContain(':min=')
  })

  it('refreshes the player scan when structure choices change and finalizes on confirmation', () => {
    expect(structure).toContain("defineEmits(['preview', 'apply'])")
    expect(structure).toContain("emit('preview', structure())")
    expect(structure).toContain("emit('apply', structure())")
    expect(structure).toContain('Looks good — Continue to Player Mapping')
    expect(page).toContain("form.append('structure'")
  })

  it('remains inspection-only', () => {
    expect(page).not.toMatch(/axiosPost\(['"`][^'"`]*(?:canonical-event|external-session|practice-result|assessment-result)/)
    expect(page).not.toMatch(/localStorage|sessionStorage|indexedDB/i)
  })
})
