import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  createSectionState,
  runSection,
  SESSION_EXPIRED_MESSAGE,
} from '../../resources/js/features/player-home/lib/sectionState.js'

const root = path.resolve(__dirname, '../..')
const source = (relative) => fs.readFileSync(path.join(root, relative), 'utf8')
const featureDir = 'resources/js/features/player-home'

const page = source(`${featureDir}/pages/PlayerHomeDashboard.vue`)
const shim = source('resources/js/pages/dashboard/Player.vue')
const router = source('resources/router/index.js')
const statePanel = source('resources/js/features/shared/components/StatePanel.vue')
const benchmarkPanel = source(`${featureDir}/components/BenchmarkTasksPanel.vue`)
const percentilesPanel = source(`${featureDir}/components/PlayerPercentilesPanel.vue`)
const constants = source(`${featureDir}/lib/constants.js`)

const listFeatureFiles = (dir) => {
  const absolute = path.join(root, dir)
  return fs.readdirSync(absolute, { withFileTypes: true }).flatMap((entry) =>
    entry.isDirectory() ? listFeatureFiles(`${dir}/${entry.name}`) : [`${dir}/${entry.name}`]
  )
}

describe('player home dashboard page', () => {
  it('loads all stats through the single dashboard-summary endpoint', () => {
    expect(page).toContain("axiosGet('player/dashboard-summary')")
    // The old 5-call fan-out and per-session statistics fetches are gone.
    expect(page).not.toContain('player/sessions/batting')
    expect(page).not.toContain('player/sessions/bullpen')
    expect(page).not.toContain('player/sessions/cage')
    expect(page).not.toContain('player/sessions/training')
    expect(page).not.toContain('player/sessions/created')
    expect(page).not.toContain('result/statistics/player')
    // The per-session stats fan-out (statistics/{id}/cage etc.) is gone.
    expect(page).not.toMatch(/statistics\/\$\{[^}]+\}\/(cage|weightball|exitvelocity|longtoss)/)
  })

  it('opens with a Percentiles tab backed by the governed development model', () => {
    expect(constants).toMatch(/STAT_TABS\s*=\s*\[\s*\{ key: 'percentiles', label: 'Percentiles' \}/)
    expect(page).toContain("const activeStatTab = ref('percentiles')")
    expect(page).toContain('PlayerPercentilesPanel')
    expect(percentilesPanel).toContain('buildPlayerDevelopmentDashboard')
    expect(percentilesPanel).toContain('player/development/players/${props.playerId}')
    expect(percentilesPanel).toContain("axiosGet('player/intelligence'")
    expect(percentilesPanel).toContain('Promise.all([')
    expect(percentilesPanel).toContain(':groups="dashboard.percentileGroups"')
  })

  it('keeps the route name and leaves a working shim at the old path', () => {
    expect(router).toContain('name: "playerDashboard"')
    expect(router).toContain('features/player-home/pages/PlayerHomeDashboard.vue')
    expect(shim).toContain('features/player-home/pages/PlayerHomeDashboard.vue')
    expect(shim).toContain('<PlayerHomeDashboard />')
  })

  it('gives each section its own error card with a retry action', () => {
    expect(page).toContain('@retry="loadProfile"')
    expect(page).toContain('@retry="loadStats"')
    expect(page).toContain('@retry="loadRapsodo"')
    expect(statePanel).toContain("$emit('retry')")
    expect(benchmarkPanel).toContain('@retry="loadTasks"')
  })

  it('shows the session-expired message on auth failures and never mentions localhost', () => {
    expect(page).toContain('SESSION_EXPIRED_MESSAGE')
    expect(SESSION_EXPIRED_MESSAGE).toBe('Your session has expired. Please log in again.')
    for (const file of listFeatureFiles(featureDir)) {
      const text = source(file)
      expect(text.toLowerCase(), `${file} mentions localhost`).not.toContain('localhost')
      expect(text, `${file} tells users to log out`).not.toContain('Log out, then log back in')
    }
  })

  it('uses the shared design tokens — no raw hex colors in player-home templates', () => {
    for (const file of listFeatureFiles(featureDir)) {
      if (!file.endsWith('.vue')) continue
      const text = source(file)
      const templateStart = text.indexOf('<template>')
      if (templateStart === -1) continue
      const template = text.slice(templateStart)
      expect(template, `${file} template contains a raw hex color`).not.toMatch(/#[0-9a-fA-F]{3,8}\b/)
    }
  })

  it('shares DashCard and StatePanel from features/shared', () => {
    expect(source(`${featureDir}/components/ProfileCard.vue`)).toContain("features/shared/components/DashCard.vue")
    expect(source(`${featureDir}/components/RecapList.vue`)).toContain("features/shared/components/DashCard.vue")
    expect(source(`${featureDir}/components/SessionCountsCard.vue`)).toContain("features/shared/components/DashCard.vue")
    expect(benchmarkPanel).toContain("features/shared/components/DashCard.vue")
    expect(page).toContain("features/shared/components/StatePanel.vue")
  })

  it('keeps the page an orchestration layer under 300 lines', () => {
    expect(page.split('\n').length).toBeLessThanOrEqual(300)
    expect(page).toContain('PlayerWorkoutsPanel')
    expect(page).toContain('ModalPlayer')
  })
})

describe('per-section fetch state (error → retry recovers)', () => {
  it('captures a failure, then recovers when the retry succeeds', async () => {
    const state = createSectionState()
    let calls = 0
    const fetcher = () => {
      calls += 1
      if (calls === 1) return Promise.reject(new Error('network down'))
      return Promise.resolve('payload')
    }

    const first = await runSection(state, fetcher, "Couldn't load sessions & stats.")
    expect(first).toBe(null)
    expect(state.error).toBe("Couldn't load sessions & stats.")
    expect(state.loading).toBe(false)

    const second = await runSection(state, fetcher, "Couldn't load sessions & stats.")
    expect(second).toBe('payload')
    expect(state.error).toBe('')
    expect(calls).toBe(2)
  })

  it('marks 401/403 responses as an expired session', async () => {
    const state = createSectionState()
    const authError = Object.assign(new Error('unauthorized'), { response: { status: 401 } })
    await runSection(state, () => Promise.reject(authError), 'Couldn\'t load profile & fitness.')
    expect(state.unauthorized).toBe(true)
    expect(state.error).toBe(SESSION_EXPIRED_MESSAGE)
  })
})
