import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(__dirname, '../..')
const source = relative => fs.readFileSync(path.join(root, relative), 'utf8')
const report = source('resources/js/components/rapsodo/RapsodoPitchingSessionReport.vue')
const movement = source('resources/js/components/rapsodo/RapsodoMovementChart.vue')
const summary = source('resources/js/components/rapsodo/RapsodoPitchTypeSummary.vue')
const page = source('resources/js/pages/data-hub/RapsodoPitchingSessionReportPage.vue')
const router = source('resources/router/index.js')
const dataHub = source('resources/js/pages/data-hub/DataHubDashboard.vue')
const playerDashboard = source('resources/js/features/player-home/pages/PlayerHomeDashboard.vue')
const playerProfileCard = source('resources/js/features/player-home/components/ProfileCard.vue')
const sessions = source('resources/js/pages/training/AllSessions.vue')

describe('Rapsodo Pitching Session Report', () => {
  it('registers coach and player routes that share the same report page', () => {
    expect(router).toContain('/data-hub/imports/:batch/rapsodo-report')
    expect(router).toContain('/player/reports/rapsodo/:batch')
    expect(router.match(/RapsodoPitchingSessionReportPage/g)?.length).toBeGreaterThanOrEqual(3)
  })

  it('loads the authorized completed-import report and handles all primary states', () => {
    expect(page).toContain('data-hub/imports/${route.params.batch}/rapsodo-report')
    expect(page).toContain('Loading Rapsodo Pitching Session Report')
    expect(page).toContain("errorCode==='unauthorized'")
    expect(page).toContain("errorCode==='no_valid_pitches'")
    expect(page).toContain('Unable to load report')
  })

  it('renders the mapped player, session strip, and five-pitch table structure', () => {
    expect(report).toContain('Mapped FMTRX player')
    expect(report).toContain('report.session.total_pitches')
    expect(report).toContain('RapsodoPitchTypeSummary')
    expect(summary).toContain('v-for="row in rows"')
    expect(summary).toContain('Total Spin')
    expect(summary).toContain('True Spin')
  })

  it('plots every movement point and keeps a text-accessible pitch legend', () => {
    expect(movement).toContain('v-for="point in points"')
    expect(movement).toContain('horizontal_break')
    expect(movement).toContain('vertical_break')
    expect(movement).toContain('Pitch type legend')
    expect(movement).toContain('centroid-label')
    expect(movement).toContain('<title>')
  })

  it('shows pitch usage and explicit velocity ranges with release summaries', () => {
    expect(report).toContain('Pitch usage')
    expect(report).toContain('usage_percentage')
    expect(report).toContain('minimum_velocity')
    expect(report).toContain('average_velocity')
    expect(report).toContain('maximum_velocity')
    expect(report).toContain('average_release_height')
    expect(report).toContain('average_release_side')
  })

  it('keeps spin efficiency and Rapsodo source strike percentage separate', () => {
    expect(report).toContain('Average spin efficiency')
    expect(report).toContain('Rapsodo source strike percentage')
    expect(report).toContain('are not combined into a score')
    expect(report).not.toContain('Command Score')
  })

  it('renders deterministic coach review and missing-data boundaries', () => {
    expect(report).toContain('Session observations')
    expect(report).toContain('report.insights')
    expect(report).toContain('Pitch location is unavailable')
    expect(report).toContain('No external age or competition-level benchmark')
  })

  it('provides mobile cards and print-to-PDF behavior', () => {
    expect(summary).toContain('mobile-cards')
    expect(report).toContain('Print / Save PDF')
    expect(report).toContain('window.print()')
    expect(report).toContain('@media print')
    expect(report).toContain('page-break-inside:avoid')
  })

  it('links only eligible completed Rapsodo imports from report locations', () => {
    expect(dataHub).toContain("item.platform === 'Rapsodo'")
    expect(dataHub).toContain('View Report')
    expect(playerDashboard).toContain("axiosGet('data-hub/rapsodo-reports')")
    expect(playerProfileCard).toContain('View Rapsodo Report')
    expect(sessions).toContain("axiosGet('data-hub/rapsodo-reports')")
    expect(sessions).toContain("_reportType: 'rapsodo'")
  })

  it('uses text labels in addition to pitch colors', () => {
    expect(movement).toContain('{{ row.pitch_type }}')
    expect(movement).toContain('{{ row.display_name }}')
    expect(report).toContain('{{ row.pitch_type }} · {{ row.display_name }}')
  })
})
