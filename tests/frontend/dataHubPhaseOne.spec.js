import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import { DATA_HUB_MAX_FILE_SIZE_BYTES } from '../../resources/js/data/dataHubConfig.js'
import { DATA_HUB_DESTINATION_GROUPS, DATA_HUB_PLATFORMS, DATA_HUB_SESSION_TYPES } from '../../resources/js/data/dataHubPlatforms.js'
import { nextDataHubStep, validateDataHubFile } from '../../resources/js/utils/dataHubWorkflow.js'

const root = path.resolve(__dirname, '../..')
const source = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8')

describe('Data Hub Phase 1', () => {
  it('provides a coach-only import entry point beside the account actions', () => {
    const layout = source('resources/js/layout/Layout.vue')
    const router = source('resources/router/index.js')

    expect(layout).toContain('to="/data-hub"')
    expect(layout).toContain('v-if="canImportData"')
    expect(layout).toContain("accessStore.canAccess('data_hub_import')")
    expect(router).toContain('path: "/data-hub"')
    expect(router).toContain('path: "/data-hub/import"')
    expect(router).toContain('coachOnly: true')
    expect(router).toContain("entitlement: 'data_hub_import'")
    expect(router).toContain("if (to.meta?.coachOnly && userData.type !== 'coach' && !isAdmin)")
  })

  it('hands the foundation forward to the five-step Phase 2A inspection experience', () => {
    const page = source('resources/js/pages/data-hub/ImportData.vue')

    expect(page).toContain('PlatformSelector')
    expect(page).toContain('FileDropzone')
    expect(page).toContain('DestinationSelector')
    expect(page).toContain('PlayerMapping')
    expect(page).toContain('InspectionReview')
    expect(page).toContain('Finish Inspection')
  })

  it('offers the complete grouped FMTRX destination catalog independent of platform', () => {
    const page = source('resources/js/pages/data-hub/ImportData.vue')
    const selector = source('resources/js/components/data-hub/DestinationSelector.vue')

    expect(DATA_HUB_DESTINATION_GROUPS.map(group => group.label)).toEqual([
      'Game & Competition', 'Hitting', 'Pitching', 'Throwing', 'Performance Testing',
    ])
    expect(DATA_HUB_SESSION_TYPES).toEqual(expect.arrayContaining([
      'Live AB', 'Cage', 'Batting Practice', 'Bullpen', 'Pitching Practice',
      'Long Toss', 'Weighted Balls', 'Exit Velocity', 'Assessment', 'Strength',
      'Mobility', 'Speed & Agility', 'Recovery',
    ]))
    expect(page).toContain('computed(() => DATA_HUB_SESSION_TYPES)')
    expect(page).not.toContain('sessionTypes.includes(type)')
    expect(selector).toContain('<optgroup')
    expect(selector).toContain('The source platform does not restrict your destination.')
  })

  it('offers team-scoped FMTRX template downloads with priority groups', () => {
    const dashboard = source('resources/js/pages/data-hub/DataHubDashboard.vue')
    const downloads = source('resources/js/components/data-hub/TemplateDownloads.vue')

    expect(dashboard).toContain('TemplateDownloads')
    expect(downloads).toContain("axiosGet('data-hub/templates')")
    expect(downloads).toContain('data-hub/templates/download')
    expect(downloads).toContain('Priority web-form templates')
    expect(downloads).toContain('Ball-by-ball templates')
    expect(downloads).toContain('FMTRX Player ID')
    expect(downloads).not.toMatch(/localStorage|sessionStorage|indexedDB/i)
  })

  it('does not persist selected source files in browser storage', () => {
    const page = source('resources/js/pages/data-hub/ImportData.vue')
    const dropzone = source('resources/js/components/data-hub/FileDropzone.vue')
    const combined = `${page}\n${dropzone}`

    expect(combined).not.toMatch(/localStorage|sessionStorage|indexedDB/i)
    expect(page).toContain('onBeforeRouteLeave')
    expect(page).toContain('clearWorkflow()')
    expect(page).toContain('selectedFile.value = null')
  })

  it('does not invent row or player counts before inspection exists', () => {
    const summary = source('resources/js/components/data-hub/ImportSummary.vue')

    expect(summary.match(/Not analyzed yet/g)).toHaveLength(2)
    expect(summary).toContain('Ready for future analysis')
  })

  it('accepts valid CSV and XLSX browser files', () => {
    expect(validateDataHubFile(
      { name: 'session.csv', size: 128, type: 'text/csv' },
      DATA_HUB_PLATFORMS[0],
    ).valid).toBe(true)
    expect(validateDataHubFile(
      {
        name: 'session.xlsx',
        size: 128,
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      },
      DATA_HUB_PLATFORMS[0],
    ).valid).toBe(true)
  })

  it('rejects invalid, empty, and oversized files with inline-safe errors', () => {
    expect(validateDataHubFile({ name: 'session.pdf', size: 128, type: 'application/pdf' }).error)
      .toContain('CSV, XLSX, or TSV')
    expect(validateDataHubFile({ name: 'empty.csv', size: 0, type: 'text/csv' }).error)
      .toContain('empty')
    expect(validateDataHubFile({
      name: 'large.csv',
      size: DATA_HUB_MAX_FILE_SIZE_BYTES + 1,
      type: 'text/csv',
    }).error).toContain('larger')
  })

  it('treats MIME metadata as advisory and enforces platform compatibility', () => {
    const advisory = validateDataHubFile(
      { name: 'session.csv', size: 128, type: 'application/octet-stream' },
      DATA_HUB_PLATFORMS[0],
    )
    expect(advisory.valid).toBe(true)
    expect(advisory.warning).toContain('unexpected file type')

    const csvOnly = { name: 'CSV only', fileTypes: ['csv'] }
    expect(validateDataHubFile(
      { name: 'session.xlsx', size: 128, type: '' },
      csvOnly,
    ).valid).toBe(false)
  })

  it('does not allow workflow steps to be skipped', () => {
    const empty = { platform: null, file: null, fileValid: false, team: null, sessionType: '' }
    expect(nextDataHubStep(1, empty)).toBe(1)
    expect(nextDataHubStep(1, { ...empty, platform: DATA_HUB_PLATFORMS[0] })).toBe(2)
    expect(nextDataHubStep(2, { ...empty, file: {}, fileValid: false })).toBe(2)
    expect(nextDataHubStep(2, { ...empty, file: {}, fileValid: true })).toBe(3)
    expect(nextDataHubStep(3, { ...empty, team: {}, sessionType: '' })).toBe(3)
    expect(nextDataHubStep(3, { ...empty, team: {}, sessionType: 'Cage' })).toBe(4)
  })

  it('clears workflow state on cancel, route leave, and logout while retaining import confirmation', () => {
    const page = source('resources/js/pages/data-hub/ImportData.vue')

    expect(page).toContain('const clearWorkflow = () =>')
    expect(page).toMatch(/const cancel[\s\S]*?clearWorkflow\(\)/)
    expect(page).toMatch(/const finishInspection[\s\S]*?inspectionComplete\.value = true/)
    expect(page).toContain('Import complete.')
    expect(page).toMatch(/onBeforeRouteLeave[\s\S]*?clearWorkflow\(\)/)
    expect(page).toContain("window.addEventListener('fmtrx-logout', clearWorkflow)")
    expect(page).toContain("router.push('/data-hub')")
  })

  it('clears a selected file when a newly selected platform is incompatible', () => {
    const page = source('resources/js/pages/data-hub/ImportData.vue')

    expect(page).toContain('!platformSupportsFile(nextPlatform, selectedFile.value)')
    expect(page).toMatch(/!platformSupportsFile[\s\S]*?selectedFile\.value = null/)
  })
})
