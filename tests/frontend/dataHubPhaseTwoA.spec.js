import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(__dirname, '../..')
const source = relative => fs.readFileSync(path.join(root, relative), 'utf8')

describe('Data Hub Phase 2A', () => {
  const page = source('resources/js/pages/data-hub/ImportData.vue')
  const mapping = source('resources/js/components/data-hub/PlayerMapping.vue')
  const review = source('resources/js/components/data-hub/InspectionReview.vue')

  it('uploads only after destination approval and exposes loading/failure states', () => {
    expect(page).toContain("axiosPost('data-hub/inspect', form)")
    expect(page).toContain('Approve & Inspect')
    expect(page).toContain('inspecting.value = true')
    expect(page).toContain('inspectionError.value')
  })

  it('requires every player resolution and explicit duplicate confirmation', () => {
    expect(page).toContain('unresolved')
    expect(page).toContain('hasDuplicates')
    expect(page).toContain('confirmedDuplicateTargets')
    expect(mapping).toContain('Skip Player')
    expect(mapping).toContain('Player not on roster')
  })

  it('releases file and clears inspection state on every lifecycle boundary', () => {
    expect(page).toMatch(/inspection\.value = response\.data\.data[\s\S]*selectedFile\.value = null/)
    expect(page).toContain("window.addEventListener('fmtrx-logout', clearWorkflow)")
    expect(page).toContain('onBeforeRouteLeave(clearWorkflow)')
    expect(page).toMatch(/const cancel[\s\S]*clearWorkflow\(\)/)
    expect(page).toMatch(/const finishInspection[\s\S]*clearWorkflow\(\)/)
  })

  it('shows normalized totals and never issues an import request', () => {
    expect(review).toContain('inspection.counts.total_rows')
    expect(review).toContain('inspection.counts.usable_rows')
    expect(review).toContain('inspection.counts.invalid_rows')
    expect(review).toContain('Normalized sample records')
    expect(page).not.toMatch(/axiosPost\(['"`][^'"`]*(?:import|session|statistics)/)
  })

  it('uses no persistent browser storage for the inspection workflow', () => {
    expect(page).not.toMatch(/localStorage|sessionStorage|indexedDB/i)
  })
})
