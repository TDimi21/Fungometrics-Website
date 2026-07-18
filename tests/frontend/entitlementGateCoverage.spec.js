import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(__dirname, '../..')
const source = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8')

describe('web entitlement gate coverage', () => {
  it('routes paid entry points through the Laravel-backed access store', () => {
    const router = source('resources/router/index.js')

    expect(router).toContain("entitlement: 'view_assessment_reports'")
    expect(router).toContain("entitlement: 'arm_care'")
    expect(router).toContain("entitlement: 'liveab_sessions'")
    expect(router).toContain("entitlement: 'view_session_report'")
    expect(router).toContain("entitlement: 'planner_create'")
    expect(router).toContain('useAccessStore')
    expect(router).not.toMatch(/subscription_plan/)
    expect(router).not.toMatch(/hasFeature\s*\(/)
  })

  it('disables administrator toggles that the coverage registry marks incomplete', () => {
    const page = source('resources/js/pages/admin/AdminPlans.vue')

    expect(page).toContain("['fully_wired', 'platform_wired', 'composite_wired'].includes")
    expect(page).toContain('Incomplete')
    expect(page).toContain(
      ':disabled="activePlan.legacy || isImmutable(item.key) || isIncomplete(item.key)"',
    )
  })
})
