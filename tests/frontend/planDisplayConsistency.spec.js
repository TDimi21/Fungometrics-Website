import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

const source = fs.readFileSync(path.resolve(process.cwd(), 'resources/js/utils/plans.js'), 'utf8')
const unavailable = [
  'ai_recommendations', 'team_recaps', 'player_recaps', 'shareable_profile', 'recruiting_profile',
  'practice_sessions', 'manage_multiple_teams', 'unlimited_players', 'view_advanced_stats',
  'personal_stats', 'view_own_stats', 'heat_maps', 'export_stats', 'ai_analytics', 'team_switching',
]

describe('web plan display fallback', () => {
  it.each(unavailable)('does not advertise unavailable entitlement %s', entitlement => {
    const matrix = source.slice(source.indexOf('export const PLAN_FEATURES'), source.indexOf('export const FEATURE_META'))
    expect(matrix).not.toContain(`'${entitlement}'`)
  })
})
