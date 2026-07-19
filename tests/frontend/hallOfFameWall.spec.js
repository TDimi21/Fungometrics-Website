import fs from 'fs'
import path from 'path'
import { describe, expect, it } from 'vitest'

const component = fs.readFileSync(
  path.resolve(process.cwd(), 'resources/js/components/dashboard/HallOfFameWall.vue'),
  'utf8',
)
const dashboard = fs.readFileSync(
  path.resolve(process.cwd(), 'resources/js/pages/dashboard/Index.vue'),
  'utf8',
)

describe('Hall of Fame rotating leaderboard', () => {
  it('is one unified 40/60 stage that rotates every five seconds', () => {
    expect(component).toContain('class="hof-stage"')
    expect(component).toContain('grid-template-columns: minmax(0, 2fr) minmax(0, 3fr)')
    expect(component).toContain('interval: { type: Number, default: 5 }')
    expect(component).toContain('setInterval')
    expect(component).toContain('mode="out-in"')
  })

  it('shows a top five, a featured athlete, the full top ten, icons, units, and controls', () => {
    expect(component).toContain('active.value.rows.slice(0, 5)')
    expect(component).toContain('View Full Top 10')
    expect(component).toContain('Featured Athlete')
    expect(component).toContain("active.icon || '★'")
    expect(component).toContain('active.unit')
    expect(component).toContain('Previous leaderboard')
    expect(component).toContain('Pause rotation')
  })

  it('prioritizes real loading and error states before the empty state', () => {
    expect(component.indexOf('v-if="loading"')).toBeLessThan(component.indexOf('v-else-if="error"'))
    expect(component.indexOf('v-else-if="error"')).toBeLessThan(component.indexOf('v-else-if="!active"'))
  })

  it('loads the authoritative server wall for the selected team and range', () => {
    expect(dashboard).toContain('coach/leaderboard/${id}?range=${top10Range.value}')
    expect(dashboard).toContain("data?.status !== 'success'")
    expect(dashboard).toContain('leaderboardServer.value = null')
    expect(dashboard).toContain(':loading="leaderboardLoading"')
    expect(dashboard).toContain(':error="leaderboardError"')
  })

  it('is safe for long-running TV presentation and clears every global resource', () => {
    expect(component).toContain('if (timer) clearInterval(timer)')
    expect(component).toContain("document.removeEventListener('fullscreenchange', syncFullscreenState)")
    expect(component).toContain("document.removeEventListener('webkitfullscreenchange', syncFullscreenState)")
    expect(component).toContain('Math.max(0, countdown.value)')
    expect(component).toContain('Math.max(0, countdown) / interval')
  })

  it('invalidates stale responses when team or entitlement access changes', () => {
    expect(dashboard).toContain('[canViewPerformanceOverview, activeTeamId]')
    expect(dashboard).toContain('++leaderboardRequestId')
    expect(dashboard).toContain('if (requestId !== leaderboardRequestId) return')
    expect(dashboard).toContain('leaderboardServer.value = null')
  })
})
