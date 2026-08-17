import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import {
  buildTeamPercentileRows,
  rankTeamPercentileRows,
  teamPercentileMetricOptions,
} from '../../resources/js/features/development/lib/teamPercentileLeaderboard.js'

const dashboardPage = fs.readFileSync(path.resolve(process.cwd(), 'resources/js/pages/dashboard/Index.vue'), 'utf8')

const snapshot = (id, name, age, ageGroup, percentile, raw) => ({
  player_id: id,
  summary: { player: { id, name, age } },
  benchmark_profile: {
    comparison_context: { age_group: ageGroup },
    metrics: [{ metric_key: 'max_exit_velocity', display_name: 'Max Exit Velocity', category: 'hitting', percentile, raw_value: raw, unit: 'mph', label: 'elite', confidence: 'high' }],
  },
})

describe('team percentile leaderboard', () => {
  it('ranks players by their governed age percentile rather than raw metric value', () => {
    const rows = buildTeamPercentileRows([
      snapshot('18', 'Older Player', 18, '17U_18U', 61, 96),
      snapshot('14', 'Younger Player', 14, '13U_14U', 88, 91),
    ])
    const ranked = rankTeamPercentileRows(rows, 'max_exit_velocity')

    expect(ranked.map((row) => row.playerName)).toEqual(['Younger Player', 'Older Player'])
    expect(ranked.map((row) => row.actual)).toEqual([91, 96])
    expect(ranked.map((row) => row.ageGroupLabel)).toEqual(['13U–14U', '17U–18U'])
  })

  it('omits missing and zero measurements and creates one selector option per metric', () => {
    const valid = snapshot('1', 'Valid', 15, '15U_16U', 72, 88)
    valid.benchmark_profile.metrics.push({ metric_key: 'bench_press', percentile: 50, raw_value: 0, unit: 'lb' })
    const rows = buildTeamPercentileRows([valid])

    expect(rows).toHaveLength(1)
    expect(teamPercentileMetricOptions(rows)).toEqual([
      expect.objectContaining({ key: 'max_exit_velocity', playerCount: 1 }),
    ])
  })

  it('caps each metric leaderboard at 25 players', () => {
    const rows = buildTeamPercentileRows(Array.from({ length: 30 }, (_, index) => snapshot(String(index), `Player ${index}`, 16, '15U_16U', index, 80 + index)))
    expect(rankTeamPercentileRows(rows, 'max_exit_velocity')).toHaveLength(25)
  })

  it('gives equal percentiles the same displayed rank', () => {
    const rows = buildTeamPercentileRows([
      snapshot('1', 'Alpha', 14, '13U_14U', 90, 91),
      snapshot('2', 'Beta', 18, '17U_18U', 90, 98),
      snapshot('3', 'Gamma', 16, '15U_16U', 80, 94),
    ])
    expect(rankTeamPercentileRows(rows, 'max_exit_velocity').map((row) => row.rank)).toEqual([1, 1, 3])
  })

  it('loads the team intelligence profile and renders benchmark group, percentile, and actual result columns', () => {
    expect(dashboardPage).toContain('coach/teams/${id}/intelligence?days=365')
    expect(dashboardPage).toContain('Top 25 by Metric')
    expect(dashboardPage).toContain('Benchmark Group')
    expect(dashboardPage).toContain('Age Percentile')
    expect(dashboardPage).toContain('Actual Result')
  })
})
