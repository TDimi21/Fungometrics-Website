import { describe, expect, it } from 'vitest'
import {
  barColor,
  barPercent,
  buildCoachProfile,
  buildProfile,
  buildSchoolTeamText,
  buildStrengthLine,
  formatGradYearShort,
  formatHeight,
  formatPositions,
  hasSleepLoggedToday,
  mapDashboardSummary,
  mapRecentSession,
  metricRowsForTab,
  metricValue,
  normalizeImageSrc,
  normalizeMode,
  pick,
  recapTypeKey,
} from '../../resources/js/features/player-home/lib/playerHomeAdapter.js'
import {
  buildLongTossReport,
  buildWeightedReport,
  fmtReport,
} from '../../resources/js/features/player-home/lib/trainingReports.js'
import {
  isStrikeZoneMark,
  markToColRow,
  TRAINING_OPTION_IDS,
} from '../../resources/js/features/player-home/lib/constants.js'

describe('playerHomeAdapter formatters', () => {
  it('formats height from feet/inches and composed fallbacks', () => {
    expect(formatHeight(6, 2)).toBe('6\' 2"')
    expect(formatHeight(5, null)).toBe('5\' 0"')
    expect(formatHeight(undefined, undefined, ' 6ft 1 ')).toBe('6ft 1')
    expect(formatHeight(undefined, undefined, undefined)).toBe('—')
  })

  it('shortens graduation years', () => {
    expect(formatGradYearShort(2027)).toBe("'27")
    expect(formatGradYearShort('Class of 2026')).toBe("'26")
    expect(formatGradYearShort('7')).toBe(null)
    expect(formatGradYearShort(null)).toBe(null)
  })

  it('formats positions from strings, objects, and keyed maps', () => {
    expect(formatPositions(['SS', '2B'])).toBe('SS, 2B')
    expect(formatPositions([{ abbreviation: 'C' }, { name: 'Pitcher' }])).toBe('C, Pitcher')
    expect(formatPositions({ a: 'RF' })).toBe('RF')
    expect(formatPositions(null)).toBe('—')
  })

  it('normalizes image sources against the API host and rejects junk', () => {
    expect(normalizeImageSrc('https://cdn.example.com/x.png', 'https://api.example.com/api')).toBe('https://cdn.example.com/x.png')
    expect(normalizeImageSrc('storage/avatar.png', 'https://api.example.com/api')).toBe('https://api.example.com/storage/avatar.png')
    expect(normalizeImageSrc('[object File]', 'https://api.example.com/api')).toBe(null)
    expect(normalizeImageSrc(null)).toBe(null)
  })

  it('coalesces the latest non-zero fitness metric across rows', () => {
    const rows = [
      { bench_press: 0, dead_lift: null },
      { bench_press: '225', dead_lift: '315' },
    ]
    expect(metricValue(rows, 'bench_press', 'bench')).toBe('225')
    expect(metricValue(rows, 'dead_lift')).toBe('315')
    expect(metricValue(rows, 'front_squat')).toBe('-')
    expect(buildStrengthLine(rows)).toContain('Bench 225')
  })

  it('picks the first meaningful value', () => {
    expect(pick(null, '', '  ', 'x', 'y')).toBe('x')
    expect(pick(undefined, 0)).toBe(0)
  })

  it('normalizes training modes', () => {
    expect(normalizeMode('weight_ball')).toBe('WB')
    expect(normalizeMode({ modes: 'Long Toss' })).toBe('LT')
    expect(normalizeMode('EV')).toBe('EV')
    expect(normalizeMode('')).toBe(null)
  })
})

describe('dashboard-summary payload mapping', () => {
  const payload = {
    counts: { batting: 3, bullpen: 1, cage: 2, training: 4, weighted: 1, exitVel: 2, longToss: 1 },
    breakdowns: {
      batting: { swings: 25, avgEV: 88.4, maxEV: 101.2, hardPct: 40.0, missPct: 12.0 },
      bullpen: { total: 30, strikePct: 63.3, pitchTypeStats: [{ type: 'FB', strikePct: 70.0, avgMph: 82.1, strikes: 14, count: 20 }] },
      longToss: { throws: 12, maxDist: 250.0, avgDist: 190.0, hop0: 240.0, hop0Count: 6, hop1Count: 3, hopTotal: 12, hop0Pct: 50.0 },
    },
    recent_sessions: [
      { id: 'p1', type: 'B', mode: 'HP', date: '2026-08-01T10:00:00Z', total_balls: 40, is_completed: true, end_note: 'good work' },
      { id: 'p2', type: 'T', mode: 'WB', date: '2026-08-02T10:00:00Z', total_balls: 20, is_completed: false, end_note: null },
    ],
  }
  const mapped = mapDashboardSummary(payload)

  it('maps counts and breakdowns with defaults for missing sections', () => {
    expect(mapped.counts.batting).toBe(3)
    expect(mapped.breakdowns.batting.swings).toBe(25)
    expect(mapped.breakdowns.batting.avgEV).toBe(88.4)
    // cage was absent from the payload → safe defaults, not undefined
    expect(mapped.breakdowns.cage.swings).toBe(0)
    expect(mapped.breakdowns.cage.avgEV).toBe(null)
    expect(mapped.breakdowns.bullpen.byType).toEqual(mapped.breakdowns.bullpen.pitchTypeStats)
  })

  it('maps recent sessions into recap rows with labels and report types', () => {
    const [batting, weighted] = mapped.recentSessions
    expect(batting._label).toBe('Batting Practice')
    expect(batting._reportType).toBe('batting')
    expect(batting.is_completed).toBe(2)
    expect(weighted._label).toBe('Weighted Ball')
    expect(weighted._reportType).toBe('weight_ball')
    expect(weighted.is_completed).toBe(1)
    expect(recapTypeKey(batting)).toBe('batting')
    expect(recapTypeKey(weighted)).toBe('weight_ball')
  })

  it('handles a null payload without exploding', () => {
    const empty = mapDashboardSummary(null)
    expect(empty.counts.batting).toBe(0)
    expect(empty.breakdowns.longToss.hopTotal).toBe(0)
    expect(empty.recentSessions).toEqual([])
  })

  it('builds metric bar rows per tab from the breakdowns', () => {
    const rows = metricRowsForTab('bullpen', mapped.breakdowns)
    expect(rows.find((r) => r.label === 'Overall Strike %').value).toBe(63.3)
    expect(metricRowsForTab('bp', mapped.breakdowns)).toEqual([])
    const ltRows = metricRowsForTab('longToss', mapped.breakdowns)
    expect(ltRows.find((r) => r.label === 'Max Distance').value).toBe(250.0)
  })

  it('scales and colors gauge bars', () => {
    expect(barPercent({ value: 75, min: 50, max: 100 })).toBe(50)
    expect(barPercent({ value: null, min: 0, max: 100 })).toBe(0)
    expect(barColor({ value: 95, min: 0, max: 100 })).toBe('#22c55e')
    expect(barColor({ value: 5, min: 0, max: 100 })).toBe('#ef4444')
  })
})

describe('profile view models', () => {
  const userData = {
    name: { first: 'Sam', last: 'Hitter', full: 'Sam Hitter' },
    profile: { first_name: 'Sam', last_name: 'Hitter' },
    player: { height_in_ft: 6, height_in_inch: 1, number_in_shirt: 12, hit_side: 'R', throw_side: 'R', grad_year: 2027 },
    team: { name: 'Falcons' },
  }

  it('builds the profile and coach profile blocks', () => {
    const profile = buildProfile(userData, { body_weight: 185 })
    expect(profile.height).toBe('6\' 1"')
    expect(profile.weight).toBe(185)

    const coach = buildCoachProfile(userData)
    expect(coach.fullName).toBe('Sam Hitter')
    expect(coach.jersey).toBe('12')
    expect(coach.bats).toBe('R')
    expect(buildSchoolTeamText(coach)).toBe('Falcons')
  })
})

describe('sleep check-in detection', () => {
  it('detects a valid sleep log for today only', () => {
    const now = new Date('2026-08-06T12:00:00')
    expect(hasSleepLoggedToday([{ fitness_date: '2026-08-06', sleep_hours: 8, sleep_quality_1_to_5: 4 }], now)).toBe(true)
    expect(hasSleepLoggedToday([{ fitness_date: '2026-08-05', sleep_hours: 8, sleep_quality_1_to_5: 4 }], now)).toBe(false)
    expect(hasSleepLoggedToday([{ fitness_date: '2026-08-06', sleep_hours: 0, sleep_quality_1_to_5: 4 }], now)).toBe(false)
    expect(hasSleepLoggedToday([], now)).toBe(false)
  })
})

describe('training report builders', () => {
  it('builds the weighted report from byWeight groups', () => {
    const report = buildWeightedReport({
      throws: 12,
      maxVelo: 84.0,
      avgVelo: 76.0,
      byWeight: [
        { weight: 5, count: 6, avgVelo: 78.0, maxVelo: 84.0 },
        { weight: 7, count: 6, avgVelo: 72.0, maxVelo: 78.0 },
      ],
    })
    expect(report.title).toBe('Weighted Ball Velocity Curve')
    expect(report.rows).toHaveLength(2)
    expect(report.rows[1].expected).toBeCloseTo(78 * 0.94, 5)
    expect(report.tiles[0].value).toBe('84 mph')
  })

  it('returns null reports when there is no data', () => {
    expect(buildWeightedReport({ byWeight: [] })).toBe(null)
    expect(buildLongTossReport({ throws: 0 })).toBe(null)
  })

  it('formats report values', () => {
    expect(fmtReport(88.25, ' mph')).toBe('88.3 mph')
    expect(fmtReport(90, '%')).toBe('90%')
    expect(fmtReport(undefined)).toBe('—')
  })
})

describe('shared constants', () => {
  it('exposes the training option ids once', () => {
    expect(TRAINING_OPTION_IDS.WB).toEqual([45, 46, 47])
    expect(TRAINING_OPTION_IDS.EV).toEqual([35, 36, 37, 38])
    expect(TRAINING_OPTION_IDS.LT).toEqual([39, 40, 41, 42, 43, 44])
  })

  it('decodes location marks and classifies the strike zone', () => {
    expect(markToColRow(1)).toEqual({ col: 1, row: 1 })
    expect(markToColRow(61)).toEqual({ col: 2, row: 1 })
    // col 19, row 18 → in-zone corner; col 1, row 1 → way outside
    expect(isStrikeZoneMark((19 - 1) * 60 + 18)).toBe(true)
    expect(isStrikeZoneMark(1)).toBe(false)
    expect(isStrikeZoneMark(0)).toBe(false)
  })
})
