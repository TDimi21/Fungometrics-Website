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
    expect(router).toContain("name: 'development.player'")
    expect(router).toContain("coach: 'view_advanced_stats'")
    expect(router).toContain("player: 'development_graphs'")
    expect(router).toContain('useAccessStore')
    expect(router).not.toMatch(/subscription_plan/)
    expect(router).not.toMatch(/hasFeature\s*\(/)
  })

  it('uses one deterministic global navigation guard', () => {
    const router = source('resources/router/index.js')
    const app = source('resources/js/app.js')

    expect(router.match(/router\.beforeEach\(/g)).toHaveLength(1)
    expect(router).toContain('if (!access.loaded) accessSummary = await access.refresh()')
    expect(app.match(/void refreshAndEnforceAccess\(\)/g) ?? []).toHaveLength(1)
  })

  it('provides an authenticated, non-checkout purchase destination', () => {
    const router = source('resources/router/index.js')
    const page = source('resources/js/pages/Purchase.vue')
    expect(router).toContain('path: "/purchase"')
    expect(router).toContain('name: "purchase"')
    expect(page).toContain('Web checkout is not available')
    expect(page).toContain("me/billing/revenuecat/products")
    expect(page).not.toMatch(/purchasePackage|createPaymentIntent|checkout\.sessions/i)
  })

  it('protects and clears the mounted performance overview from authoritative access', () => {
    const dashboard = source('resources/js/pages/dashboard/Index.vue')

    expect(dashboard).toContain("access.canAccess('performance_overview')")
    expect(dashboard).toContain('if (!canViewPerformanceOverview.value)')
    expect(dashboard).toContain('clearPerformanceOverview()')
    expect(dashboard).toContain('[() => access.loaded, canViewPerformanceOverview, activeTeamId]')
    expect(dashboard).toContain('await access.refresh({ team_id: resolvedTeamId })')
    expect(dashboard).toContain('requestId !== performanceRequestId')
    expect(dashboard).toContain("String(activeTeamId.value) !== String(teamId)")
    expect(dashboard).toContain('v-if="!access.loaded"')
    expect(dashboard).toContain('v-else-if="!canViewPerformanceOverview"')
    expect(dashboard).not.toMatch(/subscription_plan/)
  })

  it('preserves the selected team when its dashboard has no recent data', () => {
    const dashboard = source('resources/js/pages/dashboard/Index.vue')

    expect(dashboard).toContain("Preserve the user's selected team")
    expect(dashboard).not.toContain('auto-pick the first team that does')
    expect(dashboard).not.toContain('const teamWithRoster = teamsList.find')
  })

  it('keeps the deprecated practice control out of runtime route authority', () => {
    const router = source('resources/router/index.js')

    expect(router).toMatch(/name:\s*["']practice\.planner["']/)
    expect(router).toContain("entitlement: 'planner_create'")
    expect(router).not.toContain("entitlement: 'practice_sessions'")
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
